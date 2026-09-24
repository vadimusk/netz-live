<?php

namespace KateMorley\Grid\Data;

/** Functions for handling times. */
class Time {
  /** The interval the update runs at from cron, in seconds. */
  private const INTERVAL = 5 * 60;

  /**
   * Returns whether an update starting at a time is one of the two an hour,
   * on the hour and the half hour, that do the work there is no need to do
   * every five minutes.
   *
   * Counted by wall-clock slot rather than by run, so it needs no state, and
   * one update in six is picked however late a run starts within its slot.
   *
   * @param int $time The Unix timestamp
   */
  public static function isHalfHourly(int $time): bool {
    return intdiv($time, self::INTERVAL) % 6 === 0;
  }

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
