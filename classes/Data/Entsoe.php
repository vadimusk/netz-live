<?php

namespace KateMorley\Grid\Data;

/**
 * Reads quarter-hourly data from the ENTSO-E Transparency Platform
 * (https://transparency.entsoe.eu), the pan-European publication platform
 * operated by the association of transmission system operators.
 *
 * This is the source SMARD itself reads: the German transmission system
 * operators submit to ENTSO-E, and the Bundesnetzagentur republishes what
 * arrives there. Reading it directly removes a republishing step that costs
 * hours — measured against SMARD on the same quarter hours, the figures are
 * identical to the last digit, but arrive around half an hour after the
 * quarter hour rather than one to five hours after it.
 *
 * Two details of the format matter, and getting either wrong produces figures
 * that look plausible and are wrong:
 *
 * Germany has two identifiers. The DE-LU bidding zone includes Luxembourg,
 * whose generation shows up as roughly two hundred megawatts of extra solar
 * and biomass. The control area is Germany alone, and is what SMARD reports,
 * so it is what this uses. The Nordic borders are the exception: those
 * interconnectors are drawn against the bidding zone, and asking for them
 * against the control area returns nothing at all.
 *
 * Points are published as curve type A03, a variable sized block, meaning a
 * point appears only where the value changes and holds until the next one. A
 * missing position is not missing data, it is the previous value repeated.
 * Reading it as a gap loses a third of the small conventional series.
 */
class Entsoe {
  private const URL = 'https://web-api.tp.entsoe.eu/api';

  /** Germany's control area: the country alone, as SMARD reports it. */
  public const CONTROL_AREA = '10Y1001A1001A83F';

  /** The DE-LU bidding zone, which the Nordic borders are drawn against. */
  public const BIDDING_ZONE = '10Y1001A1001A82H';

  /** The number of seconds to allow for a request. */
  private const TIMEOUT = 60;

  /** The largest window a single request will return before timing out. */
  private const MAXIMUM_DAYS = 31;

  /**
   * Production types whose value may not be held forward indefinitely, and how
   * many steps they may be held for.
   *
   * Curve type A03 means a point holds until the next one or until the period
   * ends, which is right for a border whose flow genuinely has not changed. It
   * is wrong for weather-driven generation: there, a period declaring a single
   * point across hours means the operators have not published yet, not that
   * the wind held to the watt. On 3 Sep 2026 that wrote onshore wind as exactly
   * 10.22 GW for eleven consecutive quarter hours, which the core-type check
   * then waved through because a value was present, and which the platform
   * later withdrew. Measured over four weeks a genuine run of identical values
   * lasts two quarter hours; four is comfortably clear of that.
   */
  private const HOLD_LIMIT = [
    'B16' => 4,
    'B18' => 4,
    'B19' => 4
  ];

  /** The resolutions the platform publishes, in minutes. */
  private const RESOLUTIONS = [
    'PT15M' => 15,
    'PT30M' => 30,
    'PT60M' => 60,
    'PT1H'  => 60
  ];

  /**
   * Reads actual generation per production type.
   *
   * Returns an array with a "generation" and a "consumption" key, each mapping
   * a production type code to an array mapping normalised times to gigawatts.
   * The two are separated because pumped storage appears in both, once as the
   * power the fleet produced and once as the power it drew.
   *
   * @param int  $from   The earliest Unix timestamp of interest
   * @param ?int $to     The latest Unix timestamp of interest; defaults to now
   * @param string $domain The domain to read; defaults to the control area
   *
   * @return array{generation:array<string,array<string,float>>,consumption:array<string,array<string,float>>}
   *
   * @throws DataException If the data was invalid
   */
  public static function readGeneration(
    int    $from,
    ?int   $to     = null,
    string $domain = self::CONTROL_AREA
  ): array {
    $body = self::fetch([self::query([
      'documentType' => 'A75',
      'processType'  => 'A16',
      'in_Domain'    => $domain
    ], $from, $to)])[0];

    $result = ['generation' => [], 'consumption' => []];

    if ($body === null) {
      return $result;
    }

    foreach (self::series($body) as list($type, $inZone, $values)) {
      $key = $inZone ? 'generation' : 'consumption';

      foreach ($values as $time => $value) {
        $result[$key][$type][$time] = $value;
      }
    }

    return $result;
  }

  /**
   * Reads physical cross-border flows.
   *
   * Each border is given as a column name and a list of the neighbouring
   * domains that make it up, because a neighbour can be split across several
   * bidding zones: Denmark reports as DK1 and DK2, whose flows sum to the
   * country's.
   *
   * Returns an array mapping each column to an array mapping normalised times
   * to the net import in gigawatts, positive where Germany is drawing power.
   *
   * @param array<string,array{0:string,1:array<string>}> $borders
   * @param int  $from The earliest Unix timestamp of interest
   * @param ?int $to   The latest Unix timestamp of interest; defaults to now
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  public static function readFlows(array $borders, int $from, ?int $to = null): array {
    $queries = [];
    $index   = [];

    foreach ($borders as $column => list($domain, $neighbours)) {
      foreach ($neighbours as $neighbour) {
        foreach ([['export', $neighbour, $domain], ['import', $domain, $neighbour]] as
          list($direction, $in, $out)
        ) {
          $index[count($queries)] = [$column, $neighbour . '/' . $direction];
          $queries[] = self::query(
            ['documentType' => 'A11', 'in_Domain' => $in, 'out_Domain' => $out],
            $from,
            $to
          );
        }
      }
    }

    // each direction of each border is kept apart until every one of them has
    // been filled forward, because they are published independently
    $sides = [];

    foreach (self::fetch($queries) as $position => $body) {
      list($column, $side) = $index[$position];

      $sides[$column][$side] = [];

      if ($body === null) {
        continue;
      }

      foreach (self::series($body) as list($type, $inZone, $values)) {
        foreach ($values as $time => $value) {
          $sides[$column][$side][$time] = $value;
        }
      }
    }

    $flows = [];

    foreach ($sides as $column => $directions) {
      $times = [];

      foreach ($directions as $values) {
        $times += $values;
      }

      ksort($times);
      $times = array_keys($times);

      $sums   = [];
      $counts = [];

      foreach ($directions as $side => $values) {
        // a direction whose flow has not changed carries no point, so filling
        // the last value forward is what the format means rather than a
        // patch over missing data. Summing without it would read a direction
        // that simply held steady as nothing at all
        $sign = str_ends_with($side, '/import') ? 1 : -1;
        $last = null;

        foreach ($times as $time) {
          if (isset($values[$time])) {
            $last = $values[$time];
          }

          if ($last === null) {
            continue;
          }

          $sums[$time]   = ($sums[$time] ?? 0) + $sign * $last;
          $counts[$time] = ($counts[$time] ?? 0) + 1;
        }
      }

      // a quarter hour only counts once every direction has reached it, so a
      // border is never reported from half its lines
      foreach ($sums as $time => $sum) {
        if ($counts[$time] === count($directions)) {
          $flows[$column][$time] = round($sum, 3);
        }
      }
    }

    return $flows;
  }

  /**
   * Reads day-ahead prices, in euros per megawatt hour.
   *
   * Several domains may be given because the bidding zone has changed:
   * Germany, Austria and Luxembourg shared one until it was split on 1st
   * October 2018, so the earlier years carry the joint price. They are
   * returned separately and the caller decides which wins.
   *
   * @param array<string,string> $domains Column keys mapped to domains
   * @param int  $from The earliest Unix timestamp of interest
   * @param ?int $to   The latest Unix timestamp of interest; defaults to now
   *
   * @return array<string,array<string,float>>
   *
   * @throws DataException If the data was invalid
   */
  public static function readPrices(array $domains, int $from, ?int $to = null): array {
    $queries = [];
    $keys    = [];

    foreach ($domains as $key => $domain) {
      $keys[count($queries)] = $key;
      $queries[] = self::query(
        ['documentType' => 'A44', 'in_Domain' => $domain, 'out_Domain' => $domain],
        $from,
        $to
      );
    }

    $prices = [];

    foreach (self::fetch($queries) as $position => $body) {
      if ($body === null) {
        continue;
      }

      // prices are already per megawatt hour, so they are not scaled
      foreach (self::series($body, 1) as list($type, $inZone, $values)) {
        foreach ($values as $time => $value) {
          $prices[$keys[$position]][$time] = round($value, 2);
        }
      }
    }

    return $prices;
  }

  /**
   * Returns the query string for a request.
   *
   * @param array<string,string> $parameters The document parameters
   * @param int                  $from       The earliest timestamp of interest
   * @param ?int                 $to         The latest timestamp of interest
   *
   * @throws DataException If the window is longer than a single request allows
   */
  private static function query(array $parameters, int $from, ?int $to): string {
    $to ??= time() + 3600;

    if ($to - $from > self::MAXIMUM_DAYS * 86400) {
      throw new DataException(
        'Window longer than ' . self::MAXIMUM_DAYS . ' days: split it'
      );
    }

    $token = getenv('ENTSOE_API_TOKEN');

    if ($token === false || $token === '') {
      throw new DataException('ENTSOE_API_TOKEN is not set');
    }

    return self::URL . '?' . http_build_query($parameters + [
      // the platform takes and returns UTC, which is what the database stores,
      // so no local time is involved anywhere in this class
      'periodStart'   => gmdate('YmdHi', $from),
      'periodEnd'     => gmdate('YmdHi', $to),
      'securityToken' => $token
    ]);
  }

  /**
   * Performs a set of requests in parallel and returns the response bodies,
   * keyed by their position in the request list.
   *
   * A request that matched no data comes back as null rather than raising: a
   * border can legitimately have no flow published for a window, and over the
   * years covered by the archive interconnectors get built.
   *
   * @param array<string> $urls The URLs
   *
   * @return array<int,?string>
   *
   * @throws DataException If a request failed
   */
  private static function fetch(array $urls): array {
    $multi   = curl_multi_init();
    $handles = [];

    foreach ($urls as $position => $url) {
      $handle = curl_init($url);

      curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => self::TIMEOUT,
        CURLOPT_ENCODING       => '',
        CURLOPT_FAILONERROR    => false
      ]);

      curl_multi_add_handle($multi, $handle);
      $handles[$position] = $handle;
    }

    do {
      $status = curl_multi_exec($multi, $running);

      if ($running) {
        curl_multi_select($multi, 1);
      }
    } while ($running && $status === CURLM_OK);

    $bodies   = [];
    $failures = [];

    foreach ($handles as $position => $handle) {
      $body = curl_multi_getcontent($handle);
      $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

      if ($code === 200 && is_string($body) && $body !== '') {
        $bodies[$position] = $body;
      } elseif ($code === 400 && is_string($body) && self::isNoData($body)) {
        // the platform answers "no matching data found" with a 400 and an
        // acknowledgement document, which is an answer rather than a failure
        $bodies[$position] = null;
      } else {
        $bodies[$position] = null;
        $failures[]        = ($code === 0 ? 'timeout' : (string)$code);
      }

      curl_multi_remove_handle($multi, $handle);
    }

    curl_multi_close($multi);

    if (count($failures) !== 0) {
      throw new DataException(
        'Failed ' . count($failures) . ' of ' . count($urls)
        . ' requests (' . implode(', ', array_unique($failures)) . ')'
      );
    }

    ksort($bodies);

    return $bodies;
  }

  /** Returns whether an acknowledgement document reports an empty result. */
  private static function isNoData(string $body): bool {
    return str_contains($body, 'Acknowledgement_MarketDocument')
      && str_contains($body, 'No matching data found');
  }

  /**
   * Parses a response body and returns its time series.
   *
   * Each entry is the production type, whether the series is generation into
   * the zone rather than consumption out of it, and the values keyed by
   * normalised time. Flow documents carry no production type, and report as
   * an empty one.
   *
   * @return array<array{0:string,1:bool,2:array<string,float>}>
   *
   * @throws DataException If the data was invalid
   */
  private static function series(string $body, float $divisor = 1000): array {
    $document = new \DOMDocument();

    if (!@$document->loadXML($body)) {
      throw new DataException('Invalid XML');
    }

    $xpath  = new \DOMXPath($document);
    $series = [];

    // the namespace differs between generation and flow documents, so
    // everything here matches on local names rather than declaring one
    foreach ($xpath->query('//*[local-name()="TimeSeries"]') as $node) {
      $type   = self::text($xpath, './/*[local-name()="psrType"]', $node) ?? '';
      $inZone = self::text($xpath, './*[local-name()="inBiddingZone_Domain.mRID"]', $node) !== null;
      $values = [];

      foreach ($xpath->query('./*[local-name()="Period"]', $node) as $period) {
        $interval = './*[local-name()="timeInterval"]/*[local-name()=';
        $start    = self::text($xpath, $interval . '"start"]', $period);
        $finish   = self::text($xpath, $interval . '"end"]', $period);
        $step     = self::RESOLUTIONS[
          self::text($xpath, './*[local-name()="resolution"]', $period) ?? ''
        ] ?? null;

        if ($start === null || $finish === null || $step === null) {
          throw new DataException('Missing period bounds or resolution');
        }

        $origin = strtotime($start);
        $until  = strtotime($finish);

        if ($origin === false || $until === false || $until <= $origin) {
          throw new DataException('Invalid period: ' . $start . ' to ' . $finish);
        }

        $points = [];

        foreach ($xpath->query('./*[local-name()="Point"]', $period) as $point) {
          $position = self::text($xpath, './*[local-name()="position"]', $point);

          // generation and flow documents carry a quantity in megawatts;
          // price documents carry an amount per megawatt hour instead
          $quantity = self::text($xpath, './*[local-name()="quantity"]', $point)
            ?? self::text($xpath, './*[local-name()="price.amount"]', $point);

          if ($position === null || $quantity === null || !is_numeric($quantity)) {
            throw new DataException('Invalid point');
          }

          // gigawatts, from the megawatts the platform reports. Deliberately
          // unrounded: several of these series are summed into one column, and
          // rounding the parts before the sum drifts from the figure the
          // Bundesnetzagentur publishes for the whole. Rounding happens once,
          // after the sum.
          $points[(int)$position] = (float)$quantity / $divisor;
        }

        // curve type A03: a point appears only where the value changes, and
        // holds until the next one or until the period ends. The period
        // declares how far it reaches, and that declaration is what bounds
        // the fill — a border whose flow has held steady all day publishes a
        // single point, and reading only the published positions would lose
        // every quarter hour but the first.
        $steps = intdiv($until - $origin, $step * 60);
        $last  = null;
        $held  = 0;
        $limit = self::HOLD_LIMIT[$type] ?? PHP_INT_MAX;

        for ($index = 1; $index <= $steps; $index ++) {
          if (isset($points[$index])) {
            $last = $points[$index];
            $held = 0;
          } else {
            $held ++;
          }

          if ($last === null) {
            continue;
          }

          // past the limit the value is a placeholder rather than a reading,
          // so the quarter hour is left absent and the core-type check can see
          // that the mix is incomplete
          if ($held >= $limit) {
            continue;
          }

          // an hourly or half-hourly period, as the older archive years use,
          // covers several of the quarter hours the database stores
          for ($offset = 0; $offset < $step; $offset += 15) {
            $values[Time::normaliseUnix(
              $origin + ($index - 1) * $step * 60 + $offset * 60,
              15
            )] = $last;
          }
        }
      }

      if (count($values) !== 0) {
        $series[] = [$type, $inZone, $values];
      }
    }

    return $series;
  }

  /** Returns the text content of the first node matching an expression. */
  private static function text(\DOMXPath $xpath, string $expression, \DOMNode $context): ?string {
    $nodes = $xpath->query($expression, $context);

    return ($nodes !== false && $nodes->length !== 0) ? $nodes->item(0)->textContent : null;
  }
}
