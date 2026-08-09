<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Updates pricing data from the Energy-Charts API
 * (https://api.energy-charts.info). This is day-ahead auction data for the
 * DE-LU bidding zone, licensed CC BY 4.0 from the Bundesnetzagentur /
 * SMARD.de.
 */
class Pricing {
  public const KEYS = [
    'price'
  ];

  /**
   * Updates the pricing data.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    $time = $database->getLatestQuarterHourTimestamp();

    $rawData = @file_get_contents(sprintf(
      'https://api.energy-charts.info/price?bzn=DE-LU&start=%s&end=%s',
      gmdate('Y-m-d\\TH:i\\Z', $time - 24 * 60 * 60),
      gmdate('Y-m-d\\TH:i\\Z', $time)
    ));

    if ($rawData === false) {
      throw new DataException('Failed to read data');
    }

    $jsonData = json_decode($rawData, true);

    if (
      !is_array($jsonData)
      || !isset($jsonData['unix_seconds']) || !is_array($jsonData['unix_seconds'])
      || !isset($jsonData['price']) || !is_array($jsonData['price'])
      || count($jsonData['unix_seconds']) !== count($jsonData['price'])
    ) {
      throw new DataException('Missing data');
    }

    $data = [];

    foreach ($jsonData['unix_seconds'] as $index => $seconds) {
      $data[] = self::getDatum($seconds, $jsonData['price'][$index]);
    }

    $database->update(self::KEYS, array_filter($data));
  }

  /**
   * Returns the datum for a data point, or null if the value is unavailable.
   *
   * @param mixed $seconds The Unix timestamp
   * @param mixed $value   The price
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
      throw new DataException('Invalid price: ' . $value);
    }

    return [Time::normaliseUnix($seconds, 15), $value];
  }
}
