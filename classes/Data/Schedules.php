<?php

namespace KateMorley\Grid\Data;

use KateMorley\Grid\Database;

/**
 * Reads the day-ahead scheduled exchanges across each border from ENTSO-E.
 *
 * The estimate uses them to divide what the borders carry between the
 * countries. What crosses a border physically is not what was traded across
 * it — power takes every path the network offers, so the meshed borders to
 * the south and east carry flows nobody scheduled — but it moves with it, and
 * the schedule is known the day before, where the physical flow is reported an
 * hour or more late. See Prediction for how far it follows.
 *
 * Luxembourg is left out: it shares Germany's bidding zone, so nothing is
 * traded across that border, and its flow is carried forward instead.
 *
 * Kept beside the forecasts, for the same reason as they are: a schedule is a
 * different kind of number from a measured flow, and nothing here can
 * overwrite one.
 */
class Schedules {
  /** The window read, in seconds either side of now; see Forecast. */
  private const PAST   = 24 * 60 * 60;
  private const FUTURE = 12 * 60 * 60;

  /**
   * Updates the schedules.
   *
   * @param Database $database The database instance
   *
   * @throws DataException If the data was invalid
   */
  public static function update(Database $database): void {
    // settled once a day, so twice an hour is plenty; and at twenty-two
    // requests, one per direction of each border, reading them every five
    // minutes beside the flows would spend most of ENTSO-E's sixty a minute
    if (!Time::isHalfHourly(time())) {
      return;
    }

    $schedules = Entsoe::readSchedules(
      self::domains(),
      time() - self::PAST,
      time() + self::FUTURE
    );

    if (count($schedules) === 0) {
      throw new DataException('No schedules');
    }

    // each border is written on its own, so one the platform has not answered
    // for keeps what it had rather than being cleared: the estimate carries a
    // border without a schedule forward, and the rest need not wait for it
    foreach ($schedules as $column => $values) {
      $rows = [];

      foreach ($values as $time => $value) {
        $rows[] = [$time, round($value, 3)];
      }

      $database->updateForecasts([$column], $rows);
    }

    $missing = array_diff(self::keys(), array_keys($schedules));

    if (count($missing) !== 0) {
      echo '(no schedule for ' . implode(', ', $missing) . ') ';
    }
  }

  /**
   * Returns the columns a schedule is read for.
   *
   * @return array<string>
   */
  public static function keys(): array {
    return array_keys(self::domains());
  }

  /**
   * Returns the borders as Entsoe::readSchedules takes them: the same
   * neighbours as the flows, but traded against the DE-LU bidding zone.
   *
   * @return array<string,array{0:string,1:array<string>}>
   */
  private static function domains(): array {
    $domains = [];

    foreach (Generation::TRANSFER_DOMAINS as $column => list($domain, $neighbours)) {
      if ($column !== 'luxembourg') {
        $domains[$column] = [Entsoe::BIDDING_ZONE, $neighbours];
      }
    }

    return $domains;
  }
}
