<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Reads the day-ahead forecast of solar, both winds and the demand: from
 * ENTSO-E, where the operators file it, and from Energy-Charts
 * (https://api.energy-charts.info) when ENTSO-E cannot be read.
 *
 * The two are the same figures. Energy-Charts republishes the operators'
 * filing: compared over the week to 4 October 2026, the four series matched to
 * two or three megawatts on average, against levels of tens of gigawatts, as
 * long as ENTSO-E is asked for the control area rather than the DE-LU bidding
 * zone, which adds Luxembourg. So the estimate, calibrated on Energy-Charts'
 * copy, is unchanged by reading either. Energy-Charts was read alone until
 * that day, when its API refused every request for hours and the forecast
 * table ran down towards the end of the dashed line; ENTSO-E comes first now
 * because it is the source, takes two requests rather than four paced ones,
 * and leaves Energy-Charts' small allowance to the frequency and the carbon
 * intensity, which have no other source.
 *
 * Only the weather-driven sources are forecast among the generation, because
 * only they have a forecast worth having: coal and gas are dispatched to meet
 * the demand, and the estimate moves them from the demand forecast instead.
 *
 * The day-ahead forecast rather than the one revised through the day, which
 * this read until September 2026. The revised one should be the sharper, but
 * it failed in exactly the stalls the estimate exists for: on 24 September it
 * had returned nothing for solar or onshore wind for twelve hours while the
 * measurements stood two hours behind, so no estimate was drawn at all. The
 * day-ahead one is published the day before and is complete. Anchored to the
 * last confirmed quarter hour it came within 0.62GW of the generation at half
 * an hour, where the revised one, captured live earlier that month, came
 * within 0.67GW. And it is not rewritten afterwards — fetched again weeks
 * later it matches what was published to the rounding — so it could be
 * measured over every stored quarter hour rather than a few days of captures.
 *
 * The forecasts are kept in their own table rather than alongside the measured
 * quarter hours. Nothing here can overwrite a confirmed figure, which is the
 * point: a forecast is a different kind of number and is never allowed to
 * become part of the record.
 */
class Forecast {
  /** The generation columns written, which are also the types requested. */
  public const KEYS = [
    'solar',
    'wind_onshore',
    'wind_offshore'
  ];

  /**
   * The demand forecast's column. Named `demand` rather than `load` because
   * `load` is reserved in MariaDB and every statement naming it would need
   * quoting.
   */
  public const LOAD = 'demand';

  /** What Energy-Charts, and Entsoe::readForecasts, call the demand. */
  private const LOAD_TYPE = 'load';

  private const URL = 'https://api.energy-charts.info/v2/public_power_forecast';

  /**
   * The window read, in seconds either side of now.
   *
   * The past reaches back a day because the anchor the prediction is built on
   * is the newest confirmed quarter hour, which during an upstream stall can
   * be many hours old, and anchoring needs the forecast for that quarter hour
   * as well as for the ones being predicted. Older rows stay in the table for
   * as long as Database keeps them, so a longer stall is still covered.
   *
   * The future reaches twelve hours because the forecast is read only twice
   * an hour, and a few failed reads in a row should not leave the estimate
   * with nothing to run on.
   */
  private const PAST   = 24 * 60 * 60;
  private const FUTURE = 12 * 60 * 60;

  /**
   * The pause between one series and the next, in seconds.
   *
   * Energy-Charts allows two requests a minute from one address, with a burst
   * of four, and the carbon intensity has already spent one of the four by the
   * time this runs, with the frequency still to come. Four series back to back
   * was one too many: the fourth was refused, and the frequency after it. The
   * demand came fourth, which is why, while it was read as optional, the
   * forecast table held a demand on only a fraction of its rows. Spaced like
   * this the bucket refills as it goes, at the cost of a minute on the two
   * updates an hour that read the forecast.
   */
  private const PACE = 20;

  /**
   * Updates the forecast data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    // The day-ahead forecast is published once a day, so reading it every
    // five minutes would spend the platforms' allowances for nothing. Twice
    // an hour is plenty.
    if (!Time::isHalfHourly(time())) {
      return;
    }

    $from = time() - self::PAST;
    $to   = time() + self::FUTURE;

    // said out loud, as the SMARD fallback is: a stand-in nobody notices is
    // how a source stays broken without anyone knowing
    try {
      $series = self::complete(Entsoe::readForecasts($from, $to));
    } catch (DataException $exception) {
      echo '(Energy-Charts fallback: ' . $exception->getMessage() . ') ';

      $series = self::readEnergyCharts($from, $to);
    }

    // only quarter hours every series reaches are written, for the same
    // reason: a row is a set, and one missing a part is wrong rather than
    // incomplete
    $times = array_keys($series[self::LOAD_TYPE]);

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

      $row[] = $series[self::LOAD_TYPE][$time];
      $rows[] = $row;
    }

    $database->updateForecasts(
      array_merge(self::KEYS, [self::LOAD]),
      $rows
    );
  }

  /**
   * Returns a set of series if every type came back with values.
   *
   * A type that came back empty fails the read, leaving the forecast already
   * stored to stand: written as zero, a missing solar series would be a midday
   * collapse, and a missing demand a grid that had stopped.
   *
   * @param array<string,array<string,float>> $series The series, by type
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If a type has no values
   */
  private static function complete(array $series): array {
    foreach (array_merge(self::KEYS, [self::LOAD_TYPE]) as $type) {
      if (count($series[$type] ?? []) === 0) {
        throw new DataException('No forecast values for ' . $type);
      }
    }

    return $series;
  }

  /**
   * Reads every type from Energy-Charts, a type at a time (see PACE).
   *
   * @param int $from The start of the window
   * @param int $to   The end of the window
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  private static function readEnergyCharts(int $from, int $to): array {
    $series = [];

    foreach (array_merge(self::KEYS, [self::LOAD_TYPE]) as $index => $type) {
      if ($index > 0) {
        sleep(self::PACE);
      }

      $series[$type] = self::read($type, $from, $to);

      // checked as each arrives, so a type that fails spends no more of the
      // allowance on the ones after it
      if (count($series[$type]) === 0) {
        throw new DataException('No forecast values for ' . $type);
      }
    }

    return $series;
  }

  /**
   * Reads one type from Energy-Charts, returning an array mapping normalised
   * times to values in gigawatts.
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
    $rawData = @file_get_contents(self::URL . '?' . http_build_query([
      'country'         => 'de',
      'forecast_type'   => 'day-ahead',
      'production_type' => $type,
      'start'           => gmdate('Y-m-d\TH:i\Z', $from),
      'end'             => gmdate('Y-m-d\TH:i\Z', $to)
    ]));

    if ($rawData === false) {
      throw new DataException('Failed to read ' . $type);
    }

    $jsonData = json_decode($rawData, true);

    if (!is_array($jsonData) || !isset($jsonData['data']) || !is_array($jsonData['data'])) {
      throw new DataException('Missing forecast data for ' . $type);
    }

    $values = [];

    foreach ($jsonData['data'] as $row) {
      if (!is_array($row) || !isset($row['timestamp']) || !isset($row['values']) || !is_array($row['values'])) {
        continue;
      }

      // rows carry an ISO timestamp with its offset, and one value keyed by
      // the type; quarter hours the forecast does not reach are present but
      // empty
      $value   = reset($row['values']);
      $seconds = strtotime($row['timestamp']);

      if ($value === null || $value === false || (!is_int($value) && !is_float($value))
        || $seconds === false || $seconds % 900 !== 0
      ) {
        continue;
      }

      // reported in megawatts, stored as the gigawatts used everywhere else
      $values[Time::normaliseUnix($seconds, 15)] = round($value / 1000, 3);
    }

    return $values;
  }
}
