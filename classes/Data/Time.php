<?php

namespace KateMorley\Grid\Data;

/** Functions for handling times. */
class Time {
  /**
   * Normalises a time and returns it as a "YYYY-MM-DD HH:MM:SS" string.
   *
   * @param string $time     The time
   * @param int    $interval The time interval, in minutes
   *
   * @throws DataException If the time is invalid
   */
  public static function normalise(string $time, int $interval): string {
    if (!preg_match(
      '/^(\d\d\d\d)-(\d\d)-(\d\d)(T| )(2[0-3]|[01]\d):([0-5]\d)(:00)?Z?$/',
      $time,
      $matches
    )) {
      throw new DataException('Invalid time format: ' . $time);
    }

    if (!checkdate((int)$matches[2], (int)$matches[3], (int)$matches[1])) {
      throw new DataException('Invalid date: ' . $time);
    }

    if ((int)$matches[6] % $interval !== 0) {
      throw new DataException(
        'Not a multiple of ' . $interval . ' minutes: ' . $time
      );
    }

    return '"' . str_replace(['T', 'Z'], [' ', ''], $time) . '"';
  }

  /**
   * Normalises a Unix timestamp and returns it as a "YYYY-MM-DD HH:MM:SS"
   * string.
   *
   * @param int $seconds  The Unix timestamp, in seconds
   * @param int $interval The time interval, in minutes
   *
   * @throws DataException If the time is invalid
   */
  public static function normaliseUnix(int $seconds, int $interval): string {
    return self::normalise(gmdate('Y-m-d\\TH:i:s\\Z', $seconds), $interval);
  }
}
