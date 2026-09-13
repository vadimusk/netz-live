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
 */
class Pricing {
  public const KEYS = [
    'price'
  ];

  /** The SMARD series ID for the DE-LU day-ahead price. */
  private const SERIES = 4169;

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
    try {
      $filled  = 0;
      $start   = Time::normaliseUnix($from, 15);
      $reached = Time::normaliseUnix($latest, 15);

      foreach (Entsoe::readPrices(['price' => Entsoe::BIDDING_ZONE], $from)['price'] ?? [] as $time => $value) {
        if ($time >= $start && $time <= $reached && !isset($prices[$time])) {
          $prices[$time] = $value;
          $filled ++;
        }
      }

      if ($filled !== 0) {
        echo '(' . $filled . ' from ENTSO-E) ';
      }
    } catch (DataException $e) {
      $failure ??= $e;
    }

    if (count($prices) === 0) {
      throw $failure ?? new DataException('No prices from either source');
    }

    $data = [];

    foreach ($prices as $time => $value) {
      $data[] = [$time, $value];
    }

    // settled a day ahead, so the price runs past the newest generation. Only
    // the quarter hours the generation has reached are written, since a row
    // holding a price and nothing else reads as a grid that stopped.
    $database->updateExisting(self::KEYS, $data);
  }
}
