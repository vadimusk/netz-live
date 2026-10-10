<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates pricing data from SMARD (https://www.smard.de). This is day-ahead
 * auction data for the DE-LU bidding zone, licensed CC BY 4.0 from the
 * Bundesnetzagentur.
 *
 * ENTSO-E fills whatever SMARD leaves empty. Both publish the same auction
 * result and agree to the cent, but SMARD can leave a whole day null in its
 * weekly file while already carrying the next: on 13 September 2026 it
 * published Monday's prices and left Sunday blank, and every quarter hour of
 * Sunday was stored as a price of nought.
 *
 * Energy-Charts fills what both leave empty. On 10 October 2026 SMARD left
 * the day blank again, and ENTSO-E's document for it held only the second of
 * its two series, which is not the auction price the others publish (see
 * Entsoe::readPrices) — 35.64 against 10.25 for the first quarter hour — so
 * the whole day was stored as nought. Energy-Charts carried it, matching
 * SMARD's figures wherever both have one. It is asked only when a quarter
 * hour up to now is still missing, since its allowance is small and the
 * frequency and the carbon intensity have no other source.
 */
class Pricing {
  public const KEYS = [
    'price'
  ];

  /** The SMARD series ID for the DE-LU day-ahead price. */
  private const SERIES = 4169;

  private const ENERGY_CHARTS_URL = 'https://api.energy-charts.info/price';

  /**
   * Updates the pricing data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    // prices are already in euros per megawatt hour, and being settled at
    // auction the day before, they run ahead of the generation rather than
    // behind it; the database discards the quarter hours not yet reached
    $latest  = $database->getLatestQuarterHourTimestamp();
    $from    = $latest - 24 * 60 * 60;
    $prices  = [];
    $failure = null;

    try {
      $prices = Smard::read([self::SERIES], $from, 1)[self::SERIES];
    } catch (DataException $e) {
      $failure = $e;
    }

    // a quarter hour SMARD has no price for is taken from ENTSO-E rather than
    // left to default to nought, which the page would show as free power.
    // Only the window SMARD was asked for is filled. ENTSO-E answers in whole
    // delivery days, so it returns quarter hours before the window, and SMARD
    // reads just the current week's file, so tomorrow is routinely missing
    // from it; counting either as a gap would report a fallback every day.
    $start   = Time::normaliseUnix($from, 15);
    $reached = Time::normaliseUnix($latest, 15);

    try {
      $filled = 0;

      foreach (Entsoe::readPrices(['price' => Entsoe::BIDDING_ZONE], $from)['price'] ?? [] as $time => $value) {
        if ($time >= $start && !isset($prices[$time])) {
          $prices[$time] = $value;

          if ($time <= $reached) {
            $filled ++;
          }
        }
      }

      if ($filled !== 0) {
        echo '(' . $filled . ' from ENTSO-E) ';
      }
    } catch (DataException $e) {
      $failure ??= $e;
    }

    // the quarter hours up to the one running now are the ones the page
    // shows, so a gap among them is worth one more request
    $missing = [];

    for ($time = $from; $time <= time(); $time += 900) {
      $key = Time::normaliseUnix($time, 15);

      if ($key >= $start && !isset($prices[$key])) {
        $missing[$key] = true;
      }
    }

    if (count($missing) !== 0) {
      try {
        $filled = 0;

        // asked as far ahead as the day is settled, so the hours to come are
        // not each a request of their own as now reaches them
        foreach (self::readEnergyCharts($from, time() + 12 * 60 * 60) as $time => $value) {
          if ($time >= $start && !isset($prices[$time])) {
            $prices[$time] = $value;

            if (isset($missing[$time])) {
              $filled ++;
            }
          }
        }

        echo '(' . $filled . ' of ' . count($missing) . ' missing from Energy-Charts) ';
      } catch (DataException $e) {
        $failure ??= $e;
      }
    }

    if (count($prices) === 0) {
      throw $failure ?? new DataException('No prices from any source');
    }

    $data     = [];
    $upcoming = [];

    foreach ($prices as $time => $value) {
      $data[] = [$time, $value];

      if ($time > $reached) {
        $upcoming[] = [$time, $value];
      }
    }

    // settled a day ahead, so the price runs past the newest generation. The
    // record takes only the quarter hours the generation has reached, since a
    // row holding a price and nothing else reads as a grid that stopped; the
    // rest are kept aside, so the page can say what power costs right now.
    $database->updateExisting(self::KEYS, $data);
    $database->updateUpcomingPrices($upcoming);
  }

  /**
   * Reads the DE-LU day-ahead price from Energy-Charts, returning an array
   * mapping normalised times to euros per megawatt hour.
   *
   * @param int $from The start of the window
   * @param int $to   The end of the window
   *
   * @return array<string,float>
   *
   * @throws DataException If the data was invalid
   */
  private static function readEnergyCharts(int $from, int $to): array {
    $rawData = @file_get_contents(self::ENERGY_CHARTS_URL . '?' . http_build_query([
      'bzn'   => 'DE-LU',
      'start' => gmdate('Y-m-d\TH:i\Z', $from),
      'end'   => gmdate('Y-m-d\TH:i\Z', $to)
    ]));

    if ($rawData === false) {
      throw new DataException('Failed to read Energy-Charts prices');
    }

    $jsonData = json_decode($rawData, true);

    if (
      !is_array($jsonData)
      || !isset($jsonData['unix_seconds'], $jsonData['price'])
      || !is_array($jsonData['unix_seconds'])
      || !is_array($jsonData['price'])
    ) {
      throw new DataException('Missing Energy-Charts prices');
    }

    $prices = [];

    foreach ($jsonData['unix_seconds'] as $index => $seconds) {
      $value = $jsonData['price'][$index] ?? null;

      if (is_int($seconds) && $seconds % 900 === 0 && (is_int($value) || is_float($value))) {
        $prices[Time::normaliseUnix($seconds, 15)] = (float)$value;
      }
    }

    return $prices;
  }
}
