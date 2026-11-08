<?php

namespace KateMorley\Grid\Data;

/**
 * Reads the grid frequency: the readings frequency.php has gathered from
 * netzfrequenzmessung.de (see Netzfrequenzmessung), or, when there are none
 * recent enough, the past hour from Energy-Charts
 * (https://api.energy-charts.info), run by Fraunhofer ISE.
 *
 * Frequency is the one quantity on this site that describes now rather than a
 * quarter hour that has already finished. The readings gathered here are
 * seconds old when the page is built, where generation is half an hour
 * behind; Energy-Charts publishes at one-second resolution but reaches the
 * site around two and a half minutes after the fact.
 *
 * Energy-Charts was the only source until its measurement stopped on 5
 * November 2026. It is now asked only when the readings fail, which also
 * leaves its small request allowance to the carbon intensity and the price.
 *
 * It is deliberately not stored in the database. Averaging it into the
 * quarter-hourly table would destroy the second-by-second movement that makes
 * it worth showing, and the eleven years of history hold nothing to put in
 * such a column. Only the past hour is kept, for the sparkline.
 *
 * The value is not German. The whole Continental European synchronous area
 * turns in step, so Germany, France, Poland and Spain report the same number —
 * verified as identical at 1999 of 2000 shared timestamps, the exception
 * differing by 0.0001 Hz. The country parameter selects whose copy to read,
 * not whose grid is measured, and the page says so.
 */
class Frequency {
  private const URL = 'https://api.energy-charts.info/frequency';

  /** The span read, in seconds: the hour behind the sparkline. */
  public const PERIOD = 3600;

  /**
   * How old the newest gathered reading may be before Energy-Charts is asked
   * instead, in seconds.
   *
   * Readings arrive every ten seconds, so this is a dozen missed in a row:
   * frequency.php has stopped running, or the service has stopped answering.
   */
  private const MEASURED_MAXIMUM_AGE = 120;

  /** The number of seconds to allow for a request. */
  private const TIMEOUT = 20;

  /** The number of points the hour is reduced to for the sparkline. */
  private const POINTS = 120;

  /**
   * Where the last good reading is kept.
   *
   * Energy-Charts rate-limits, and when the gathered readings have stopped as
   * well, a single refused request would otherwise blank the band until the
   * next run. The band carries the reading's own timestamp, so standing in
   * with the last one is self-describing rather than a claim about now.
   */
  private const CACHE = '/var/lib/netz-live/frequency.json';

  /**
   * How old a stored reading may be before it stops standing in, in seconds.
   *
   * Past this the band is dropped rather than shown stale: the whole point of
   * the figure is that it describes the present, and a quarter of an hour is
   * already several times the lag it normally carries.
   */
  private const CACHE_MAXIMUM_AGE = 900;

  /** The nominal frequency of the synchronous area, in hertz. */
  public const NOMINAL = 50.0;

  /** The latest frequency, in hertz. */
  public readonly float $hertz;

  /** The Unix timestamp of the latest frequency. */
  public readonly int $time;

  /**
   * The past hour, reduced to a fixed number of points.
   *
   * @var array<float>
   */
  public readonly array $series;

  /**
   * Constructs a new instance.
   *
   * @param array<float> $series The reduced series
   */
  private function __construct(float $hertz, int $time, array $series) {
    $this->hertz  = $hertz;
    $this->time   = $time;
    $this->series = $series;
  }

  /** The deviation from nominal, in millihertz. */
  public function deviation(): float {
    return round(1000 * ($this->hertz - self::NOMINAL));
  }

  /**
   * Reads the past hour and returns it.
   *
   * @throws DataException If the data was invalid
   */
  public static function read(): self {
    try {
      $reading = self::measured();
    } catch (DataException $exception) {
      // said out loud for the same reason the SMARD fallback is: a stand-in
      // nobody notices is how a source stays broken without anyone knowing
      echo '(Energy-Charts fallback: ' . $exception->getMessage() . ') ';

      try {
        $reading = self::fetch();
      } catch (DataException $exception) {
        $cached = self::cached();

        if ($cached === null) {
          throw $exception;
        }

        echo '(cached: ' . $exception->getMessage() . ') ';

        return $cached;
      }
    }

    self::store($reading);

    return $reading;
  }

  /**
   * Returns the past hour of the readings frequency.php has gathered.
   *
   * @throws DataException If there is no recent reading
   */
  private static function measured(): self {
    $readings = Netzfrequenzmessung::readings();

    if (count($readings) === 0) {
      throw new DataException('No readings from netzfrequenzmessung.de');
    }

    $latest = array_key_last($readings);
    $age    = time() - $latest;

    if ($age > self::MEASURED_MAXIMUM_AGE) {
      throw new DataException(
        'Newest reading from netzfrequenzmessung.de is ' . $age . ' seconds old'
      );
    }

    return new self($readings[$latest], $latest, self::reduce($readings));
  }

  /**
   * Fetches the past hour from Energy-Charts.
   *
   * @throws DataException If the data was invalid
   */
  private static function fetch(): self {
    $to   = time();
    $from = $to - self::PERIOD;

    $handle = curl_init(
      self::URL . '?' . http_build_query([
        'country' => 'de',
        'start'   => gmdate('Y-m-d\TH:i', $from),
        'end'     => gmdate('Y-m-d\TH:i', $to)
      ])
    );

    curl_setopt_array($handle, [
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_TIMEOUT        => self::TIMEOUT,
      CURLOPT_ENCODING       => ''
    ]);

    $body = curl_exec($handle);
    $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

    if ($code !== 200 || !is_string($body)) {
      throw new DataException('Request failed with status ' . $code);
    }

    $data = json_decode($body, true);

    if (
      !is_array($data)
      || !isset($data['unix_seconds'], $data['data'])
      || !is_array($data['unix_seconds'])
      || !is_array($data['data'])
      || count($data['unix_seconds']) !== count($data['data'])
    ) {
      throw new DataException('Malformed response');
    }

    $values = [];

    foreach ($data['unix_seconds'] as $index => $seconds) {
      $value = $data['data'][$index];

      if (!is_int($seconds) || (!is_int($value) && !is_float($value))) {
        continue;
      }

      // a reading far from nominal is a fault in the feed rather than in the
      // grid: the synchronous area disconnects at 47.5 and 51.5 hertz, so
      // anything outside that never happened
      if ($value < 47.5 || $value > 51.5) {
        continue;
      }

      $values[$seconds] = (float)$value;
    }

    if (count($values) === 0) {
      throw new DataException('No usable readings');
    }

    ksort($values);

    $latest = array_key_last($values);

    return new self($values[$latest], $latest, self::reduce($values));
  }

  /**
   * Stores a reading, so that a later failure has something to fall back on.
   *
   * Failure to store is ignored: the directory belongs to the deployment
   * rather than the repository, and a site that cannot write a cache should
   * still show the figure it just read.
   */
  private static function store(self $reading): void {
    @file_put_contents(
      self::CACHE,
      json_encode([
        'hertz'  => $reading->hertz,
        'time'   => $reading->time,
        'series' => $reading->series
      ]),
      LOCK_EX
    );
  }

  /** Returns the stored reading, or null where there is no usable one. */
  private static function cached(): ?self {
    $body = @file_get_contents(self::CACHE);

    if ($body === false) {
      return null;
    }

    $data = json_decode($body, true);

    if (
      !is_array($data)
      || !isset($data['hertz'], $data['time'], $data['series'])
      || !is_array($data['series'])
      || time() - $data['time'] > self::CACHE_MAXIMUM_AGE
    ) {
      return null;
    }

    return new self(
      (float)$data['hertz'],
      (int)$data['time'],
      array_map(fn ($value) => (float)$value, $data['series'])
    );
  }

  /**
   * Reduces a second-by-second series to a fixed number of points by
   * averaging within equal buckets.
   *
   * An hour is up to 3600 readings and the sparkline is a couple of hundred
   * pixels wide, so drawing them all would spend bytes on detail no one can
   * see. The mean is used rather than a sample, so that a brief excursion
   * still bends the line rather than being missed between samples.
   *
   * @param array<int,float> $values The values, keyed by timestamp
   *
   * @return array<float>
   */
  private static function reduce(array $values): array {
    $times = array_keys($values);
    $first = $times[0];
    $last  = $times[count($times) - 1];
    $span  = max(1, $last - $first);

    $sums   = array_fill(0, self::POINTS, 0.0);
    $counts = array_fill(0, self::POINTS, 0);

    foreach ($values as $time => $value) {
      $bucket = min(
        self::POINTS - 1,
        (int)floor(self::POINTS * ($time - $first) / $span)
      );

      $sums[$bucket]   += $value;
      $counts[$bucket] ++;
    }

    $series = [];
    $last   = null;

    foreach ($sums as $bucket => $sum) {
      if ($counts[$bucket] !== 0) {
        $last = $sum / $counts[$bucket];
      }

      // a bucket the feed left empty holds the previous value rather than
      // dropping to nothing, which would draw a cliff that never happened
      if ($last !== null) {
        $series[] = round($last, 4);
      }
    }

    return $series;
  }
}
