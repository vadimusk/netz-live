<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates pricing data from SMARD (https://www.smard.de). This is day-ahead
 * auction data for the DE-LU bidding zone, licensed CC BY 4.0 from the
 * Bundesnetzagentur.
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
    $series = Smard::read(
      [self::SERIES],
      $database->getLatestQuarterHourTimestamp() - 24 * 60 * 60,
      1
    );

    $data = [];

    foreach ($series[self::SERIES] as $time => $value) {
      $data[] = [$time, $value];
    }

    $database->update(self::KEYS, $data);
  }
}
