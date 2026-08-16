<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates generation and cross-border flow data from SMARD
 * (https://www.smard.de), run by the Bundesnetzagentur.
 */
class Generation {
  public const KEYS = [
    'lignite',
    'hard_coal',
    'gas',
    'biomass',
    'solar',
    'wind_onshore',
    'wind_offshore',
    'hydro',
    'nuclear',
    'pumped_generation',
    'pumped_consumption',
    'other_renewable',
    'other_conventional',
    'austria',
    'belgium',
    'czech_republic',
    'denmark',
    'france',
    'luxembourg',
    'netherlands',
    'norway',
    'poland',
    'sweden',
    'switzerland'
  ];

  // the two groups are written separately, because the flows are published
  // behind the generation and shouldn't hold it back
  private const GENERATION_COLUMNS = [
    'lignite',
    'hard_coal',
    'gas',
    'biomass',
    'solar',
    'wind_onshore',
    'wind_offshore',
    'hydro',
    'pumped_generation',
    'pumped_consumption',
    'other_renewable',
    'other_conventional'
  ];

  private const TRANSFER_COLUMNS = [
    'austria',
    'belgium',
    'czech_republic',
    'denmark',
    'france',
    'luxembourg',
    'netherlands',
    'norway',
    'poland',
    'sweden',
    'switzerland'
  ];

  /**
   * Maps each generation column to its SMARD series ID.
   *
   * Nuclear power has a series of its own, which isn't read here: Germany shut
   * down its last reactors in April 2023, and SMARD stopped publishing weekly
   * files for it at the start of 2024, so asking for the current week would
   * fail. The historic import fills the column for the years it ran, and it
   * stays at zero from April 2023 onwards, which is the truth.
   */
  private const GENERATION_SERIES = [
    'lignite'            => 1223,
    'hard_coal'          => 4069,
    'gas'                => 4071,
    'biomass'            => 4066,
    'solar'              => 4068,
    'wind_onshore'       => 4067,
    'wind_offshore'      => 1225,
    'hydro'              => 1226,
    'pumped_generation'  => 4070,
    'other_renewable'    => 1228,
    'other_conventional' => 1227
  ];

  /**
   * The series ID for pumped storage consumption, which SMARD reports under
   * consumption rather than generation, as a positive figure.
   */
  private const PUMPED_CONSUMPTION_SERIES = 4387;

  /**
   * Maps each neighbouring grid to its SMARD export and import series IDs.
   *
   * Physical flows are used rather than the scheduled commercial exchanges.
   * Germany sits inside the Continental European synchronous grid, where power
   * reaches a buyer along whichever lines carry it, so a sale to one neighbour
   * can flow through another: over a day the two series agree on the country's
   * overall balance to within a few hundred megawatts, but disagree per
   * neighbour by a gigawatt or more, at times even in direction. Flows are what
   * actually happened, at the cost of being published a little later.
   */
  private const TRANSFER_SERIES = [
    'austria'        => [4740, 4884],
    'belgium'        => [4992, 4994],
    'czech_republic' => [4744, 4888],
    'denmark'        => [4736, 4880],
    'france'         => [4737, 4881],
    'luxembourg'     => [4738, 4882],
    'netherlands'    => [4739, 4883],
    'norway'         => [4988, 4990],
    'poland'         => [4741, 4885],
    'sweden'         => [4742, 4886],
    'switzerland'    => [4743, 4887]
  ];

  /** The period read on each update, in seconds. */
  private const PERIOD = 24 * 60 * 60;

  /**
   * Updates the generation data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    $from = time() - self::PERIOD;

    $generation = self::readGeneration($from);

    // flows are held to the quarter hours the generation reaches. They are
    // published within minutes of each other and either can arrive first, so
    // letting the flows write a quarter hour of their own would show a full
    // set of cross-border figures against a generation mix of nothing until
    // the generation caught up a few minutes later.
    $transfers = array_intersect_key(self::readTransfers($from), $generation);

    $database->updateGeneration(
      self::rows($generation, self::GENERATION_COLUMNS),
      self::GENERATION_COLUMNS,
      self::rows($transfers, self::TRANSFER_COLUMNS),
      self::TRANSFER_COLUMNS,
      count($transfers) === 0 ? null : max(array_keys($transfers))
    );
  }

  /**
   * Reads the generation series and returns an array mapping times to an array
   * mapping columns to values.
   *
   * @param int $from The earliest Unix timestamp of interest
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  private static function readGeneration(int $from): array {
    $series = Smard::read(
      array_merge(
        array_values(self::GENERATION_SERIES),
        [self::PUMPED_CONSUMPTION_SERIES]
      ),
      $from
    );

    $times = self::allTimes($series);
    $data  = [];

    foreach (self::GENERATION_SERIES as $column => $id) {
      self::carryForward($data, $column, $series[$id], $times);
    }

    // consumption is stored as a negative value, so that adding it to
    // generation gives the net signed power of the pumped storage fleet
    self::carryForward(
      $data,
      'pumped_consumption',
      array_map(fn ($value) => -$value, $series[self::PUMPED_CONSUMPTION_SERIES]),
      $times
    );

    return self::complete($data, self::GENERATION_COLUMNS);
  }

  /**
   * Reads the flow series and returns an array mapping times to an array
   * mapping columns to values.
   *
   * @param int $from The earliest Unix timestamp of interest
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  private static function readTransfers(int $from): array {
    $series = Smard::read(
      array_merge(...array_values(self::TRANSFER_SERIES)),
      $from
    );

    $times = self::allTimes($series);
    $data  = [];

    // SMARD reports exports as positive values and imports as negative ones,
    // both from Germany's point of view, so negating their sum gives the net
    // import: positive when Germany is drawing power from a neighbour
    foreach (self::TRANSFER_SERIES as $column => list($export, $import)) {
      $net = [];

      foreach ($times as $time) {
        if (isset($series[$export][$time]) && isset($series[$import][$time])) {
          $net[$time] = round(-($series[$export][$time] + $series[$import][$time]), 3);
        }
      }

      self::carryForward($data, $column, $net, $times);
    }

    return self::complete($data, self::TRANSFER_COLUMNS);
  }

  /**
   * Returns the union of times across a set of series, in ascending order.
   *
   * @param array<int,array<string,float>> $series
   *
   * @return array<string>
   */
  private static function allTimes(array $series): array {
    $times = [];

    foreach ($series as $values) {
      $times += $values;
    }

    ksort($times);

    return array_keys($times);
  }

  /**
   * Merges a column into the data array, carrying the last known value
   * forward over quarter hours the series hasn't reached yet.
   *
   * SMARD occasionally leaves a single series unpublished for hours — a
   * stall on one transmission system operator's side, not the usual
   * few-minute gap between files — and without this, requiring every column
   * at once in complete() would drop every quarter hour until that one
   * series caught up, freezing the whole site over a single stalled reading
   * rather than just that one.
   *
   * @param array                $data   The data array to merge into
   * @param string               $column The column
   * @param array<string,float>  $values The column's values, keyed by time
   * @param array<string>        $times  The times to fill, in order
   */
  private static function carryForward(
    array  &$data,
    string $column,
    array  $values,
    array  $times
  ): void {
    $last = null;

    foreach ($times as $time) {
      if (isset($values[$time])) {
        $last = $values[$time];
      }

      if ($last !== null) {
        $data[$time][$column] = $last;
      }
    }
  }

  /**
   * Filters out times that not every column reaches.
   *
   * This is the fallback for a column that has no value at all within the
   * period read — most often the very first quarter hours of a new
   * database, before carryForward() has anything to carry.
   *
   * @param array         $data    The data
   * @param array<string> $columns The columns each time must cover
   *
   * @return array<string,array<string,float>>
   */
  private static function complete(array $data, array $columns): array {
    $complete = array_filter(
      $data,
      fn ($values) => count($values) === count($columns)
    );

    ksort($complete);

    return $complete;
  }

  /**
   * Builds insertable rows.
   *
   * @param array         $data    The data
   * @param array<string> $columns The columns to include
   */
  private static function rows(array $data, array $columns): array {
    return array_map(
      fn ($time) => array_merge(
        [$time],
        array_map(fn ($column) => $data[$time][$column], $columns)
      ),
      array_keys($data)
    );
  }
}
