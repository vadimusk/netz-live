<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Reads forecast generation from Energy-Charts (https://api.energy-charts.info).
 *
 * Only the weather-driven sources are forecast, because only they have a
 * forecast worth having: solar and wind are what move between one quarter hour
 * and the next, while coal, gas and biomass are dispatched slowly enough that
 * carrying the last confirmed figure forward costs little.
 *
 * The forecasts are kept in their own table rather than alongside the measured
 * quarter hours. Nothing here can overwrite a confirmed figure, which is the
 * point: a forecast is a different kind of number and is never allowed to
 * become part of the record.
 */
class Forecast {
  /** The columns written, which are also the production types requested. */
  public const KEYS = [
    'solar',
    'wind_onshore',
    'wind_offshore'
  ];

  /**
   * The demand forecast, read from the day-ahead endpoint and stored beside
   * the generation ones.
   *
   * It is a day-ahead product and so does not sharpen as the hour approaches:
   * measured against what the grid actually drew, it sits at about 2.4GW
   * whatever the delay. Holding the last confirmed demand beats it while the
   * source is less than about an hour and a half behind, and loses badly past
   * that — 9GW against 2.3GW at six hours — which is exactly the stretch the
   * estimate is drawn over during an outage.
   */
  public const LOAD = 'load';

  private const URL = 'https://api.energy-charts.info/public_power_forecast';

  /**
   * The version two endpoint, the only one carrying a demand forecast. It
   * answers in a different shape — rows of an ISO timestamp and a values
   * object rather than parallel arrays — so it is parsed separately.
   */
  private const URL_LOAD = 'https://api.energy-charts.info/v2/public_power_forecast';

  /**
   * The window read, in seconds either side of now.
   *
   * The past reaches back a day because the anchor the prediction is built on
   * is the newest confirmed quarter hour, which during an upstream stall can
   * be many hours old, and anchoring needs the forecast for that quarter hour
   * as well as for the ones being predicted.
   */
  private const PAST   = 24 * 60 * 60;
  private const FUTURE = 3 * 60 * 60;

  /**
   * Updates the forecast data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    $now  = time();
    $from = $now - self::PAST;
    $to   = $now + self::FUTURE;

    $series = [];

    foreach (self::KEYS as $type) {
      $series[$type] = self::read($type, $from, $to);

      // While the upstream platform is unwell the endpoint answers for some
      // production types and returns nothing but nulls for others. Treating a
      // missing series as zero would put a midday collapse of solar power into
      // the forecast, so a type that came back empty fails the step instead,
      // leaving the forecast already stored to stand.
      if (count($series[$type]) === 0) {
        throw new DataException('No forecast values for ' . $type);
      }
    }

    // the demand forecast is optional: without it the prediction falls back to
    // holding the last confirmed demand, which is what it did before
    $load = [];

    try {
      $load = self::readLoad();
    } catch (DataException $e) {
      $load = [];
    }

    // only quarter hours every series reaches are written, for the same
    // reason: a row is a mix, and a mix missing one of its parts is wrong
    // rather than incomplete
    $times = array_keys($series[self::KEYS[0]]);

    foreach (self::KEYS as $type) {
      $times = array_intersect($times, array_keys($series[$type]));
    }

    if (count($times) === 0) {
      throw new DataException('No quarter hours common to every series');
    }

    sort($times);

    $rows = [];

    foreach ($times as $time) {
      $row = [$time];

      foreach (self::KEYS as $type) {
        $row[] = $series[$type][$time];
      }

      $row[] = $load[$time] ?? 0;
      $rows[] = $row;
    }

    $database->updateForecasts(
      array_merge(self::KEYS, [self::LOAD]),
      $rows
    );
  }

  /**
   * Reads the day-ahead demand forecast, returning an array mapping normalised
   * times to values in gigawatts.
   *
   * @return array<string,float>
   *
   * @throws DataException If the data was invalid
   */
  private static function readLoad(): array {
    $rawData = @file_get_contents(
      self::URL_LOAD . '?country=de&forecast_type=day-ahead&production_type=load'
    );

    if ($rawData === false) {
      throw new DataException('Failed to read load');
    }

    $jsonData = json_decode($rawData, true);

    if (!is_array($jsonData) || !isset($jsonData['data']) || !is_array($jsonData['data'])) {
      throw new DataException('Missing load data');
    }

    $values = [];

    foreach ($jsonData['data'] as $row) {
      if (!is_array($row) || !isset($row['timestamp']) || !isset($row['values'])) {
        continue;
      }

      $value   = reset($row['values']);
      $seconds = strtotime($row['timestamp']);

      if ($value === null || (!is_int($value) && !is_float($value))
        || $seconds === false || $seconds % 900 !== 0
      ) {
        continue;
      }

      $values[Time::normaliseUnix($seconds, 15)] = round($value / 1000, 3);
    }

    if (count($values) === 0) {
      throw new DataException('No load values');
    }

    return $values;
  }

  /**
   * Reads one production type, returning an array mapping normalised times to
   * values in gigawatts.
   *
   * @param string $type The production type
   * @param int    $from The start of the window
   * @param int    $to   The end of the window
   *
   * @return array<string,float>
   *
   * @throws DataException If the data was invalid
   */
  private static function read(string $type, int $from, int $to): array {
    $rawData = @file_get_contents(
      self::URL
      . '?country=de&production_type='
      . rawurlencode($type)
      . '&start='
      . $from
      . '&end='
      . $to
    );

    if ($rawData === false) {
      throw new DataException('Failed to read ' . $type);
    }

    $jsonData = json_decode($rawData, true);

    if (
      !is_array($jsonData)
      || !isset($jsonData['unix_seconds']) || !is_array($jsonData['unix_seconds'])
      || !isset($jsonData['forecast_values']) || !is_array($jsonData['forecast_values'])
      || count($jsonData['unix_seconds']) !== count($jsonData['forecast_values'])
    ) {
      throw new DataException('Missing forecast data for ' . $type);
    }

    $values = [];

    foreach ($jsonData['unix_seconds'] as $index => $seconds) {
      $value = $jsonData['forecast_values'][$index];

      if (!is_int($seconds)) {
        throw new DataException('Invalid time: ' . $seconds);
      }

      // quarter hours the forecast doesn't cover are present but empty, and
      // the whole window comes back empty while the upstream platform is down
      if ($value === null) {
        continue;
      }

      if (!is_int($value) && !is_float($value)) {
        throw new DataException('Invalid forecast value: ' . $value);
      }

      if ($seconds % 900 !== 0) {
        continue;
      }

      // reported in megawatts, stored as the gigawatts used everywhere else
      $values[Time::normaliseUnix($seconds, 15)] = round($value / 1000, 3);
    }

    return $values;
  }
}
