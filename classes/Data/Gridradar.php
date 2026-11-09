<?php

namespace KateMorley\Grid\Data;

/**
 * Takes the grid frequency from Gridradar (https://www.gridradar.net), the
 * last of the sources Frequency falls back on.
 *
 * Gridradar measures the Continental European synchronous area with phasor
 * measurement units of its own. The free access the site has gives the median
 * of their one-second means a quarter of an hour behind, which is why it is
 * asked only after Energy-Charts, a couple of minutes behind, has failed too:
 * it keeps the band showing something true rather than nothing, and the band
 * carries the reading's own time.
 *
 * Free access cannot ask for a span. Each answer carries on from the end of
 * the one before, up to an hour, and a request within ten seconds of the last
 * is refused. So the readings are kept on disk and each answer is added to
 * them, which keeps the hour behind the sparkline whole however rarely or
 * often this is asked.
 *
 * In November 2026 Gridradar allowed the site to show the data on condition
 * that the figure itself links to www.gridradar.net: not a page explaining
 * it, and not a tooltip. The band does so whenever it shows Gridradar's
 * reading. The token belongs to the owner's account and is kept in the
 * environment rather than the repository.
 */
class Gridradar {
  private const URL = 'https://api.gridradar.net/query';

  /** The number of seconds to allow for a request. */
  private const TIMEOUT = 20;

  /** Where the past hour of readings is kept. */
  private const READINGS = '/var/lib/netz-live/gridradar-readings.json';

  /** Returns whether the site has a token to read with. */
  public static function configured(): bool {
    return (string)getenv('GRIDRADAR_TOKEN') !== '';
  }

  /**
   * Asks for the readings since the last answer, adds them to those kept, and
   * returns the hour up to the newest, oldest first.
   *
   * @return array<int,float> The frequencies in hertz, keyed by Unix timestamp
   *
   * @throws DataException If the readings were unavailable or invalid
   */
  public static function read(): array {
    if (!self::configured()) {
      throw new DataException('GRIDRADAR_TOKEN is not set');
    }

    $handle = curl_init(self::URL);

    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => self::TIMEOUT,
      CURLOPT_POST           => true,
      CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . getenv('GRIDRADAR_TOKEN'),
        'Content-Type: application/json'
      ],
      CURLOPT_POSTFIELDS     => json_encode([
        'metric' => 'frequency-ucte-median-1s',
        'format' => 'json',
        'ts'     => 's'
      ])
    ]);

    $body = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if ($code !== 200 || !is_string($body)) {
      throw new DataException('Request failed with status ' . $code);
    }

    // the answer is a list of series in the shape Grafana reads, here a
    // single one whose points are [hertz, seconds]
    $data = json_decode($body, true);

    if (
      !is_array($data)
      || !isset($data[0]['datapoints'])
      || !is_array($data[0]['datapoints'])
    ) {
      throw new DataException('Malformed response');
    }

    $readings = self::stored();

    foreach ($data[0]['datapoints'] as $point) {
      if (
        !is_array($point)
        || !isset($point[0], $point[1])
        || !is_numeric($point[0])
        || !is_numeric($point[1])
      ) {
        continue;
      }

      $hertz = (float)$point[0];

      // the synchronous area disconnects at 47.5 and 51.5 hertz, so a reading
      // outside that is a fault in the measurement rather than in the grid
      if ($hertz < 47.5 || $hertz > 51.5) {
        continue;
      }

      $readings[(int)$point[1]] = $hertz;
    }

    if (count($readings) === 0) {
      throw new DataException('No usable readings');
    }

    ksort($readings);

    $from     = array_key_last($readings) - Frequency::PERIOD;
    $readings = array_filter(
      $readings,
      fn ($time) => $time >= $from,
      ARRAY_FILTER_USE_KEY
    );

    // failure to store is ignored, as for the frequency cache: the next
    // answer then starts after these readings, and the hour shows a gap, but
    // the figure just read is still worth showing
    $temporary = self::READINGS . '.tmp';

    if (@file_put_contents($temporary, json_encode($readings)) !== false) {
      @rename($temporary, self::READINGS);
    }

    return $readings;
  }

  /**
   * Returns the readings kept from earlier answers.
   *
   * @return array<int,float> The frequencies in hertz, keyed by Unix timestamp
   */
  private static function stored(): array {
    $body = @file_get_contents(self::READINGS);

    if ($body === false) {
      return [];
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
      return [];
    }

    $readings = [];

    foreach ($data as $time => $hertz) {
      if (is_int($time) && (is_int($hertz) || is_float($hertz))) {
        $readings[$time] = (float)$hertz;
      }
    }

    return $readings;
  }
}
