<?php

namespace KateMorley\Grid\Data;

/**
 * Reads quarter-hourly data from SMARD (https://www.smard.de), the electricity
 * market data platform of the Bundesnetzagentur, the German federal regulator.
 *
 * SMARD publishes the same figures as the Energy-Charts API this site used
 * previously — both ultimately come from the transmission system operators via
 * ENTSO-E — but publishes them around three hours sooner, because it is the
 * first stop rather than a republisher. SMARD's own documentation puts its
 * target at one hour after the fact.
 *
 * Each series lives in its own file, one file per calendar week, so a series
 * covering the past day means one request, or two when the day spans a Monday.
 */
class Smard {
  private const URL
    = 'https://www.smard.de/app/chart_data/%1$d/DE/%1$d_DE_quarterhour_%2$d.json';

  /** SMARD refuses requests that don't identify themselves as a browser. */
  private const USER_AGENT = 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36'
    . ' (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

  /** The number of seconds to allow for a request. */
  private const TIMEOUT = 30;

  /**
   * The divisor converting a reported value to the stored unit.
   *
   * Values are megawatt hours produced within a quarter hour, so multiplying
   * by four gives megawatts, and dividing by a thousand gives the gigawatts
   * stored in the database.
   */
  private const DIVISOR = 250;

  /**
   * Reads a set of series and returns them as an array mapping each series ID
   * to an array mapping normalised times to values in gigawatts.
   *
   * @param array<int> $ids     The series IDs
   * @param int        $from    The earliest Unix timestamp of interest
   * @param float      $divisor The divisor converting reported values to the
   *                             stored unit; defaults to the power one
   *
   * @return array<int,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  public static function read(
    array $ids,
    int   $from,
    float $divisor = self::DIVISOR
  ): array {
    $series = self::readWeeks($ids, self::weeks($from), $divisor);

    foreach ($series as $id => $values) {
      $series[$id] = array_filter(
        $values,
        fn ($time) => $time >= $from,
        ARRAY_FILTER_USE_KEY
      );

      if (count($series[$id]) === 0) {
        throw new DataException('No data for series ' . $id);
      }
    }

    return $series;
  }

  /**
   * Reads a set of series over a set of weeks, returning them as an array
   * mapping each series ID to an array mapping Unix timestamps to values.
   *
   * Series that have no file for a week come back empty rather than raising:
   * over the years covered by the archive, interconnectors get built and
   * reactors get shut down, so a gap is a fact about the grid, not an error.
   *
   * @param array<int> $ids     The series IDs
   * @param array<int> $weeks   The week timestamps, in milliseconds
   * @param float      $divisor The divisor converting reported values to the
   *                            stored unit
   *
   * @return array<int,array<int,float>>
   *
   * @throws DataException If the data was invalid
   */
  public static function readWeeks(
    array $ids,
    array $weeks,
    float $divisor = self::DIVISOR
  ): array {
    $requests = [];

    foreach ($ids as $id) {
      foreach ($weeks as $week) {
        $requests[] = [$id, sprintf(self::URL, $id, $week)];
      }
    }

    $series = array_fill_keys($ids, []);

    foreach (self::fetch($requests) as list($id, $body)) {
      foreach (self::parse($body, $divisor) as $time => $value) {
        $series[$id][$time] = $value;
      }
    }

    return $series;
  }

  /**
   * Returns the week timestamps, in milliseconds, covering a period.
   *
   * @param int  $from The start of the period
   * @param ?int $to   The end of the period; defaults to now
   *
   * @return array<int>
   */
  public static function weeksBetween(int $from, ?int $to = null): array {
    return self::weeks($from, $to);
  }

  /**
   * Returns the week timestamps, in milliseconds, of the files covering a
   * period running from a time until now.
   *
   * Files are named after the Monday that starts the week, at midnight German
   * local time.
   *
   * @param int  $from The earliest Unix timestamp of interest
   * @param ?int $to   The latest Unix timestamp of interest; defaults to now
   *
   * @return array<int>
   */
  private static function weeks(int $from, ?int $to = null): array {
    $zone  = new \DateTimeZone('Europe/Berlin');
    $week  = (new \DateTime('@' . $from))->setTimezone($zone);
    $week->modify('monday this week')->setTime(0, 0);

    $latest = (new \DateTime('@' . ($to ?? time())))->setTimezone($zone);
    $latest->modify('monday this week')->setTime(0, 0);

    $weeks = [];

    while ($week <= $latest) {
      $weeks[] = $week->getTimestamp() * 1000;
      $week->modify('+1 week');
    }

    return $weeks;
  }

  /**
   * Performs a set of requests in parallel and returns the responses.
   *
   * A missing file isn't an error: a series can legitimately have no file for
   * a week it doesn't reach into yet.
   *
   * @param array $requests An array of series ID and URL pairs
   *
   * @return array An array of series ID and response body pairs
   *
   * @throws DataException If a request failed
   */
  private static function fetch(array $requests): array {
    $multi   = curl_multi_init();
    $handles = [];

    foreach ($requests as $index => list($id, $url)) {
      $handle = curl_init($url);

      curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERAGENT      => self::USER_AGENT,
        CURLOPT_TIMEOUT        => self::TIMEOUT,
        CURLOPT_ENCODING       => '',
        CURLOPT_FAILONERROR    => false
      ]);

      curl_multi_add_handle($multi, $handle);
      $handles[$index] = $handle;
    }

    do {
      $status = curl_multi_exec($multi, $running);

      if ($running) {
        curl_multi_select($multi, 1);
      }
    } while ($running && $status === CURLM_OK);

    $responses = [];
    $failures  = 0;

    foreach ($handles as $index => $handle) {
      $body = curl_multi_getcontent($handle);
      $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

      if ($code === 200 && is_string($body)) {
        $responses[] = [$requests[$index][0], $body];
      } elseif ($code !== 404) {
        $failures ++;
      }

      curl_multi_remove_handle($multi, $handle);
    }

    curl_multi_close($multi);

    if ($failures !== 0) {
      throw new DataException(
        'Failed to read ' . $failures . ' of ' . count($requests) . ' files'
      );
    }

    return $responses;
  }

  /**
   * Parses a response body and returns an array mapping Unix timestamps to
   * values in gigawatts.
   *
   * @param string $body    The response body
   * @param float  $divisor The divisor converting reported values to the
   *                         stored unit
   *
   * @return array<int,float>
   *
   * @throws DataException If the data was invalid
   */
  private static function parse(string $body, float $divisor): array {
    $data = json_decode($body, true);

    if (!is_array($data) || !isset($data['series']) || !is_array($data['series'])) {
      throw new DataException('Missing series');
    }

    $values = [];

    foreach ($data['series'] as $point) {
      if (!is_array($point) || count($point) !== 2) {
        throw new DataException('Invalid data point');
      }

      list($milliseconds, $value) = $point;

      if (!is_int($milliseconds)) {
        throw new DataException('Invalid time: ' . $milliseconds);
      }

      // trailing quarter hours that haven't been reported yet are present in
      // the file with a null value
      if ($value === null) {
        continue;
      }

      if (!is_int($value) && !is_float($value)) {
        throw new DataException('Invalid value: ' . $value);
      }

      $values[intdiv($milliseconds, 1000)] = round($value / $divisor, 3);
    }

    return $values;
  }
}
