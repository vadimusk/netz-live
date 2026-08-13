<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates emissions data.
 *
 * The official carbon intensity comes from the Energy-Charts API
 * (https://api.energy-charts.info), run by Fraunhofer ISE. It is the only
 * figure here not published by SMARD, and it arrives around three hours after
 * the fact, where the generation it describes is barely an hour old.
 *
 * Rather than let the whole page wait for it, the quarter hours it hasn't
 * reached yet are filled in with a figure calculated from the generation mix,
 * and overwritten with the official one as soon as that arrives.
 */
class Emissions {
  public const KEYS = [
    'emissions'
  ];

  /**
   * Emission factors in grams of CO2 equivalent per kilowatt hour.
   *
   * These were calibrated by fitting the official series against SMARD's
   * generation mix over a fortnight, holding the renewables at zero and
   * keeping each factor within the range its technology can plausibly take.
   * The fitted values are direct combustion emissions rather than lifecycle
   * ones, which is what the official figures turn out to track: lignite at
   * 1074 and hard coal at 720 sit where the literature puts them, and the
   * high figure for other conventional sources reflects its make-up of blast
   * furnace and coke oven gases, which really are that carbon intense.
   *
   * Checked against the official series over 105 hours it had not been fitted
   * to, this reproduces it to a mean error of 7 g/kWh, with 99% of quarter
   * hours within 20 g/kWh, against values ranging from 111 to 718.
   */
  private const FACTORS = [
    'lignite'            => 1074,
    'hard_coal'          => 720,
    'gas'                => 340,
    'other_conventional' => 2000,
    'biomass'            => 400
  ];

  /**
   * A constant added to the emissions total, in gigawatts times grams per
   * kilowatt hour.
   *
   * SMARD's breakdown accounts for around 440 MW less generation than the
   * Energy-Charts one it is calibrated against, mostly oil and waste that
   * SMARD groups differently. This stands in for the emissions of that
   * missing sliver, which is close enough to constant to be treated as one.
   */
  private const OFFSET = 1.046;

  /**
   * The columns making up the generation the intensity is measured against.
   *
   * Pumped storage is excluded, on both the generating and the consuming side,
   * because it returns power drawn from the grid rather than adding to it;
   * including it made the calculated figures noticeably worse.
   */
  private const DENOMINATOR = [
    'lignite',
    'hard_coal',
    'gas',
    'biomass',
    'solar',
    'wind_onshore',
    'wind_offshore',
    'hydro',
    'nuclear',
    'other_renewable',
    'other_conventional'
  ];

  /**
   * Updates the emissions data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    $official = self::readOfficial();

    $database->updateExisting(self::KEYS, array_values($official));

    $database->updateComputedEmissions(
      self::FACTORS,
      self::DENOMINATOR,
      self::OFFSET,
      count($official) === 0 ? null : max(array_keys($official))
    );
  }

  /**
   * Reads the official emissions data, returning an array mapping normalised
   * times to rows.
   *
   * @return array<string,array>
   *
   * @throws DataException If the data was invalid
   */
  private static function readOfficial(): array {
    $rawData = @file_get_contents(sprintf(
      'https://api.energy-charts.info/co2eq?country=de&start=%s&end=%s',
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
      || !isset($jsonData['co2eq']) || !is_array($jsonData['co2eq'])
      || count($jsonData['unix_seconds']) !== count($jsonData['co2eq'])
    ) {
      throw new DataException('Missing data');
    }

    $data = [];

    foreach ($jsonData['unix_seconds'] as $index => $seconds) {
      $datum = self::getDatum($seconds, $jsonData['co2eq'][$index]);

      if ($datum !== null) {
        $data[$datum[0]] = $datum;
      }
    }

    return $data;
  }

  /**
   * Returns the datum for a data point, or null if the value is unavailable.
   *
   * @param mixed $seconds The Unix timestamp
   * @param mixed $value   The emissions value
   *
   * @throws DataException If the data was invalid
   */
  private static function getDatum(mixed $seconds, mixed $value): ?array {
    if (!is_int($seconds)) {
      throw new DataException('Invalid time: ' . $seconds);
    }

    if ($value === null) {
      return null;
    }

    if (!is_int($value) && !is_float($value)) {
      throw new DataException('Invalid emissions value: ' . $value);
    }

    return [Time::normaliseUnix($seconds, 15), $value];
  }
}
