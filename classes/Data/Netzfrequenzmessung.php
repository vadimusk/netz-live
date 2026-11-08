<?php

namespace KateMorley\Grid\Data;

/**
 * Takes the grid frequency from netzfrequenzmessung.de, measured by Dr.
 * Gobmaier GmbH with meters of its own.
 *
 * The service publishes only the reading of the moment, with no history
 * behind it, so the hour the sparkline draws has to be gathered as it
 * happens: frequency.php takes a reading every ten seconds from a cron job of
 * its own and keeps the past hour on disk, and update.php reads the hour back
 * rather than asking anyone.
 *
 * Ten seconds is the rate the operator was asked about. In November 2026 they
 * allowed the site to use the readings free of charge for three years, at up
 * to two requests a second and at least 450 milliseconds apart. The address
 * carries an access number of the site's own, which is kept in the
 * environment rather than the repository, so that nobody else's polling is
 * counted against it.
 */
class Netzfrequenzmessung {
  private const URL = 'https://dat.netzfrequenzmessung.de:9080/replay.php';

  /** The number of seconds to allow for a request. */
  private const TIMEOUT = 5;

  /** Where the past hour of readings is kept. */
  private const READINGS = '/var/lib/netz-live/frequency-readings.json';

  /**
   * How far a reading's own time may lie from the clock here, in seconds.
   *
   * The service answers with the reading of the moment, a second or so old. A
   * reading much older than that comes from a meter or a server that has
   * stopped, repeating a value that no longer describes now.
   */
  private const MAXIMUM_SKEW = 60;

  /** Returns whether the site has an access number to read with. */
  public static function configured(): bool {
    return (string)getenv('NETZFREQUENZMESSUNG_NR') !== '';
  }

  /**
   * Takes one reading, returning its Unix timestamp and frequency in hertz.
   *
   * @return array{int,float}
   *
   * @throws DataException If the reading was unavailable or invalid
   */
  public static function measure(): array {
    $handle = curl_init(
      self::URL . '?' . http_build_query([
        'nr' => getenv('NETZFREQUENZMESSUNG_NR')
      ])
    );

    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CONNECTTIMEOUT => self::TIMEOUT,
      CURLOPT_TIMEOUT        => self::TIMEOUT
    ]);

    $body = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if ($code !== 200 || !is_string($body)) {
      throw new DataException('Request failed with status ' . $code);
    }

    // the answer is a small XML document holding the frequency in <f> and
    // the moment it was measured in <z>, in UTC; it also carries the phase
    // angle and the deviation of grid time, which the site has no use for
    $document = new \DOMDocument();

    if (!@$document->loadXML($body)) {
      throw new DataException('Malformed response');
    }

    $value = trim($document->getElementsByTagName('f')->item(0)?->textContent ?? '');
    $time  = \DateTimeImmutable::createFromFormat(
      '!d.m.Y H:i:s',
      trim($document->getElementsByTagName('z')->item(0)?->textContent ?? ''),
      new \DateTimeZone('UTC')
    );

    if (!is_numeric($value) || $time === false) {
      throw new DataException('Malformed reading');
    }

    $hertz = (float)$value;
    $time  = $time->getTimestamp();

    if (abs(time() - $time) > self::MAXIMUM_SKEW) {
      throw new DataException(
        'Reading from ' . gmdate('Y-m-d H:i:s', $time) . ' UTC is not current'
      );
    }

    // the synchronous area disconnects at 47.5 and 51.5 hertz, so a reading
    // outside that is a fault in the meter rather than in the grid
    if ($hertz < 47.5 || $hertz > 51.5) {
      throw new DataException('Implausible reading of ' . $hertz . ' Hz');
    }

    return [$time, $hertz];
  }

  /**
   * Adds a reading to the hour kept, dropping those that have aged out of it.
   *
   * The file is written beside its final place and renamed over it, so that
   * update.php, reading it at any moment, never finds it half written.
   *
   * @param int   $time  The Unix timestamp of the reading
   * @param float $hertz The frequency, in hertz
   *
   * @throws DataException If the readings could not be stored
   */
  public static function record(int $time, float $hertz): void {
    $readings = self::readings();

    $readings[$time] = $hertz;

    ksort($readings);

    $temporary = self::READINGS . '.tmp';

    if (
      @file_put_contents($temporary, json_encode($readings)) === false
      || !@rename($temporary, self::READINGS)
    ) {
      throw new DataException('Failed to store the reading');
    }
  }

  /**
   * Returns the readings of the past hour, oldest first.
   *
   * @return array<int,float> The frequencies, keyed by Unix timestamp
   */
  public static function readings(): array {
    $body = @file_get_contents(self::READINGS);

    if ($body === false) {
      return [];
    }

    $data = json_decode($body, true);

    if (!is_array($data)) {
      return [];
    }

    $from     = time() - Frequency::PERIOD;
    $readings = [];

    foreach ($data as $time => $hertz) {
      if (is_int($time) && $time >= $from && (is_int($hertz) || is_float($hertz))) {
        $readings[$time] = (float)$hertz;
      }
    }

    ksort($readings);

    return $readings;
  }
}
