<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates generation data from the Energy-Charts API
 * (https://api.energy-charts.info), which is run by Fraunhofer ISE and draws
 * on data from the German transmission system operators via ENTSO-E and the
 * Bundesnetzagentur.
 */
class Generation {
  public const KEYS = [
    'lignite',
    'hard_coal',
    'gas',
    'coal_gas',
    'oil',
    'biomass',
    'waste',
    'geothermal',
    'other',
    'solar',
    'wind_onshore',
    'wind_offshore',
    'hydro_run_of_river',
    'hydro_reservoir',
    'pumped_generation',
    'pumped_consumption',
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

  // maps the "name" field of each /public_power production type to a column;
  // series not listed here (e.g. the aggregate "Cross border electricity
  // trading" figure, "Load", and the renewable share percentages) are ignored
  // since the per-country breakdown is read separately from /cbpf
  private const GENERATION_SERIES = [
    'Fossil brown coal / lignite'      => 'lignite',
    'Fossil hard coal'                 => 'hard_coal',
    'Fossil gas'                       => 'gas',
    'Fossil coal-derived gas'          => 'coal_gas',
    'Fossil oil'                       => 'oil',
    'Biomass'                          => 'biomass',
    'Waste'                            => 'waste',
    'Geothermal'                       => 'geothermal',
    'Others'                           => 'other',
    'Solar'                            => 'solar',
    'Wind onshore'                     => 'wind_onshore',
    'Wind offshore'                    => 'wind_offshore',
    'Hydro Run-of-River'               => 'hydro_run_of_river',
    'Hydro water reservoir'            => 'hydro_reservoir',
    'Hydro pumped storage'             => 'pumped_generation',
    'Hydro pumped storage consumption' => 'pumped_consumption'
  ];

  // maps the "name" field of each /cbet country to a column; positive values
  // are imports into Germany, negative values are exports
  private const TRANSFER_SERIES = [
    'Austria'        => 'austria',
    'Belgium'        => 'belgium',
    'Czech Republic' => 'czech_republic',
    'Denmark'        => 'denmark',
    'France'         => 'france',
    'Luxembourg'     => 'luxembourg',
    'Netherlands'    => 'netherlands',
    'Norway'         => 'norway',
    'Poland'         => 'poland',
    'Sweden'         => 'sweden',
    'Switzerland'    => 'switzerland'
  ];

  /**
   * Updates the generation data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    $columns = array_flip(self::KEYS);
    $data    = [];

    $generationTimes = self::ingest(
      'https://api.energy-charts.info/public_power?country=de&start=%s&end=%s',
      'production_types',
      self::GENERATION_SERIES,
      $columns,
      1000, // MW -> GW
      $data
    );

    // Physical flows are used rather than the scheduled commercial exchanges
    // from /cbet. Germany sits inside the Continental European synchronous
    // grid, where power reaches a buyer along whichever lines carry it, so a
    // sale to one neighbour can flow through another: over a day the two
    // series agree on the country's overall balance to within a few hundred
    // megawatts, but disagree per neighbour by a gigawatt or more, at times
    // even in direction. Flows are what actually happened, at the cost of
    // being published a couple of hours later.
    $transferTimes = self::ingest(
      'https://api.energy-charts.info/cbpf?country=de&start=%s&end=%s',
      'countries',
      self::TRANSFER_SERIES,
      $columns,
      1, // already GW
      $data
    );

    // Only quarter hours carried by both series are committed. Flows lag
    // generation, and the lag isn't reported as missing data: the trailing
    // quarter hours come back with most neighbours at exactly zero, so
    // taking whatever the endpoint returned would write those zeros in as
    // though they were real. The site therefore runs as far behind as the
    // flow data does.
    $completeTimes = array_intersect(
      $generationTimes,
      self::withReportedTransfers($transferTimes, $data, $columns)
    );

    $database->updateGeneration(array_intersect_key(
      $data,
      array_flip($completeTimes)
    ));
  }

  /**
   * Filters out quarter hours whose flows haven't been reported yet.
   *
   * The endpoint doesn't mark them as missing: it returns the quarter hour
   * with every neighbour at exactly zero. Germany borders eleven grids, and
   * all eleven sitting at precisely zero is not something that happens, so
   * a row like that is taken to mean the figures haven't arrived.
   *
   * @param array<string>     $times         The times to filter
   * @param array             $data          The data collected so far
   * @param array<string,int> $columnIndexes A map from column to row index
   *
   * @return array<string> The times whose flows have been reported
   */
  private static function withReportedTransfers(
    array $times,
    array $data,
    array $columnIndexes
  ): array {
    $rows = array_map(
      fn ($column) => $columnIndexes[$column] + 1,
      array_values(self::TRANSFER_SERIES)
    );

    return array_values(array_filter(
      $times,
      function ($time) use ($data, $rows) {
        foreach ($rows as $row) {
          if ($data[$time][$row] != 0) {
            return true;
          }
        }

        return false;
      }
    ));
  }

  /**
   * Reads a series of named data from the Energy-Charts API and merges it
   * into the data array.
   *
   * @param string             $urlPattern The URL, with %s placeholders for
   *                                        the start and end times
   * @param string             $listKey    The key of the list of series
   * @param array<string,string> $columns  A map from series name to column
   * @param array<string,int>    $columnIndexes A map from column to 1-based
   *                                             row index
   * @param float              $divisor    The divisor to convert to the
   *                                        stored unit
   * @param array              $data       The data array to merge into
   *
   * @return array<string> The times found in this endpoint's response
   *
   * @throws DataException If the data was invalid
   */
  private static function ingest(
    string $urlPattern,
    string $listKey,
    array  $columns,
    array  $columnIndexes,
    float  $divisor,
    array  &$data
  ): array {
    $rawData = @file_get_contents(sprintf(
      $urlPattern,
      gmdate('Y-m-d\\TH:i\\Z', time() - 24 * 60 * 60),
      gmdate('Y-m-d\\TH:i\\Z', time())
    ));

    if ($rawData === false) {
      throw new DataException('Failed to read data');
    }

    $jsonData = json_decode($rawData, true);

    if (
      !is_array($jsonData)
      || !isset($jsonData['unix_seconds']) || !is_array($jsonData['unix_seconds'])
      || !isset($jsonData[$listKey]) || !is_array($jsonData[$listKey])
    ) {
      throw new DataException('Missing data');
    }

    $times = array_map(
      fn ($seconds) => is_int($seconds)
        ? Time::normaliseUnix($seconds, 15)
        : throw new DataException('Invalid time: ' . $seconds),
      $jsonData['unix_seconds']
    );

    foreach ($times as $time) {
      if (!isset($data[$time])) {
        $data[$time] = array_fill(0, count(self::KEYS) + 1, 0);
        $data[$time][0] = $time;
      }
    }

    foreach ($jsonData[$listKey] as $series) {
      if (
        !is_array($series)
        || !isset($series['name']) || !is_string($series['name'])
        || !isset($series['data']) || !is_array($series['data'])
      ) {
        throw new DataException('Invalid series');
      }

      $column = $columns[$series['name']] ?? null;

      if ($column === null) {
        continue;
      }

      if (count($series['data']) !== count($times)) {
        throw new DataException('Mismatched data length: ' . $series['name']);
      }

      $row = $columnIndexes[$column] + 1;

      foreach ($series['data'] as $index => $value) {
        if ($value === null) {
          continue;
        }

        if (!is_int($value) && !is_float($value)) {
          throw new DataException(
            'Invalid value for ' . $series['name'] . ': ' . $value
          );
        }

        $data[$times[$index]][$row] = round($value / $divisor, 2);
      }
    }

    return $times;
  }
}
