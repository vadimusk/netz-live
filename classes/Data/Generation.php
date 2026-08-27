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

  /**
   * Maps each generation column to the ENTSO-E production type codes that
   * make it up.
   *
   * ENTSO-E reports finer categories than SMARD does, so several of them add
   * up to one stored column: run-of-river and reservoir hydro are one figure
   * here, as are the four small conventional types. Summing them reproduces
   * SMARD's own series exactly, verified over four days of quarter hours.
   *
   * Nuclear is absent for the same reason it is absent from the SMARD map:
   * the last reactors shut down in April 2023, the column is filled for the
   * years they ran by the historic import, and zero from then on is the truth.
   */
  private const GENERATION_TYPES = [
    'lignite'            => ['B02'],
    'hard_coal'          => ['B05'],
    'gas'                => ['B04'],
    'biomass'            => ['B01'],
    'solar'              => ['B16'],
    'wind_onshore'       => ['B19'],
    'wind_offshore'      => ['B18'],
    'hydro'              => ['B11', 'B12'],
    'pumped_generation'  => ['B10'],
    'other_renewable'    => ['B09', 'B15'],
    'other_conventional' => ['B03', 'B06', 'B17', 'B20']
  ];

  /**
   * The production types that must be published before a quarter hour counts.
   *
   * These carry all but a fraction of a percent of German generation and are
   * published together. The rest — geothermal, other renewables, the odds and
   * ends of conventional plant — run hours behind, and waiting for them would
   * throw away the freshness this source exists for.
   */
  private const CORE_TYPES = [
    'B01', 'B02', 'B04', 'B05', 'B10', 'B11', 'B12', 'B16', 'B18', 'B19'
  ];

  /** The production type reported as consumption for pumped storage. */
  private const PUMPED_CONSUMPTION_TYPE = 'B10';

  /**
   * Maps each neighbour to the German domain its border is drawn against, and
   * the neighbouring domains that make it up.
   *
   * Most borders belong to the control area, but the Nordic interconnectors
   * are drawn against the DE-LU bidding zone and return nothing at all when
   * asked for against the control area. Denmark is two bidding zones, whose
   * flows sum to the country's.
   */
  private const TRANSFER_DOMAINS = [
    'austria'        => [Entsoe::CONTROL_AREA, ['10YAT-APG------L']],
    'belgium'        => [Entsoe::CONTROL_AREA, ['10YBE----------2']],
    'czech_republic' => [Entsoe::CONTROL_AREA, ['10YCZ-CEPS-----N']],
    'denmark'        => [Entsoe::BIDDING_ZONE, ['10YDK-1--------W', '10YDK-2--------M']],
    'france'         => [Entsoe::CONTROL_AREA, ['10YFR-RTE------C']],
    'luxembourg'     => [Entsoe::CONTROL_AREA, ['10YLU-CEGEDEL-NQ']],
    'netherlands'    => [Entsoe::CONTROL_AREA, ['10YNL----------L']],
    'norway'         => [Entsoe::BIDDING_ZONE, ['10YNO-2--------T']],
    'poland'         => [Entsoe::CONTROL_AREA, ['10YPL-AREA-----S']],
    'sweden'         => [Entsoe::BIDDING_ZONE, ['10Y1001A1001A47J']],
    'switzerland'    => [Entsoe::CONTROL_AREA, ['10YCH-SWISSGRIDZ']]
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

    try {
      $generation = self::readGeneration($from);
      $transfers  = self::readTransfers($from);
    } catch (DataException $exception) {
      // ENTSO-E is the fresher source, and also the one with a key that can
      // expire and a format that can change. SMARD carries the same figures
      // hours later, so falling back beats publishing nothing — but it is
      // written into the update log rather than passing silently, because a
      // fallback nobody notices is how the site ends up hours behind again.
      echo '(SMARD fallback: ' . $exception->getMessage() . ') ';

      $generation = self::readGenerationFromSmard($from);
      $transfers  = self::readTransfersFromSmard($from);
    }

    // flows are held to the quarter hours the generation reaches. They are
    // published within minutes of each other and either can arrive first, so
    // letting the flows write a quarter hour of their own would show a full
    // set of cross-border figures against a generation mix of nothing until
    // the generation caught up a few minutes later.
    $transfers = array_intersect_key($transfers, $generation);

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
    $read  = Entsoe::readGeneration($from);
    $times = self::coveredTimes($read['generation'], self::CORE_TYPES);
    $data  = [];

    foreach (self::GENERATION_TYPES as $column => $codes) {
      self::carryForward(
        $data,
        $column,
        self::combine($read['generation'], $codes, $times),
        $times
      );
    }

    // consumption is stored as a negative value, so that adding it to
    // generation gives the net signed power of the pumped storage fleet
    self::carryForward(
      $data,
      'pumped_consumption',
      array_map(
        fn ($value) => -$value,
        self::combine($read['consumption'], [self::PUMPED_CONSUMPTION_TYPE], $times)
      ),
      $times
    );

    return self::complete($data, self::GENERATION_COLUMNS);
  }

  /**
   * Reads the generation series from SMARD.
   *
   * Kept as the fallback for the days ENTSO-E cannot be reached: it carries
   * the same figures, arriving hours later, which beats carrying none.
   *
   * @param int $from The earliest Unix timestamp of interest
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  private static function readGenerationFromSmard(int $from): array {
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

    self::carryForward(
      $data,
      'pumped_consumption',
      array_map(fn ($value) => -$value, $series[self::PUMPED_CONSUMPTION_SERIES]),
      $times
    );

    return self::complete($data, self::GENERATION_COLUMNS);
  }

  /**
   * Returns the times every one of a set of production types reaches.
   *
   * A quarter hour is only worth writing once the whole core mix covers it:
   * an intersection rather than a union, so that a row is never assembled by
   * carrying one type forward past the point where the rest of them stop.
   *
   * @param array<string,array<string,float>> $series The series by type
   * @param array<string>                     $codes  The types to require
   *
   * @return array<string>
   */
  private static function coveredTimes(array $series, array $codes): array {
    $times = null;

    foreach ($codes as $code) {
      $values = $series[$code] ?? [];
      $times  = ($times === null)
        ? $values
        : array_intersect_key($times, $values);
    }

    if ($times === null || count($times) === 0) {
      throw new DataException('No quarter hour covered by every core type');
    }

    ksort($times);

    return array_keys($times);
  }

  /**
   * Sums a set of production types into one column's series.
   *
   * Each type is carried forward independently before the sum, because they
   * are published as curve type A03 and reach different distances: a type
   * that has not been published yet counts as nothing, which is what it
   * contributed, and one that simply held steady keeps its last figure.
   *
   * @param array<string,array<string,float>> $series The series by type
   * @param array<string>                     $codes  The types to sum
   * @param array<string>                     $times  The times to fill
   *
   * @return array<string,float>
   */
  private static function combine(array $series, array $codes, array $times): array {
    $totals = array_fill_keys($times, 0.0);

    foreach ($codes as $code) {
      $last = 0.0;

      foreach ($times as $time) {
        if (isset($series[$code][$time])) {
          $last = $series[$code][$time];
        }

        $totals[$time] += $last;
      }
    }

    // rounded once, after the sum: rounding the parts first drifts from the
    // figure the Bundesnetzagentur publishes for the whole
    return array_map(fn ($value) => round($value, 3), $totals);
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
    $flows = Entsoe::readFlows(self::TRANSFER_DOMAINS, $from);
    $times = self::allTimes($flows);
    $data  = [];

    foreach (self::TRANSFER_COLUMNS as $column) {
      self::carryForward($data, $column, $flows[$column] ?? [], $times);
    }

    return self::complete($data, self::TRANSFER_COLUMNS);
  }

  /**
   * Reads the flow series from SMARD, as the fallback.
   *
   * @param int $from The earliest Unix timestamp of interest
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  private static function readTransfersFromSmard(int $from): array {
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
