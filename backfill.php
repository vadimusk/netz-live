<?php

// Imports the historic archive from SMARD, which reaches back to the start of
// 2015. This is a one-off: the regular update only ever looks at the past day,
// so without this the year and all-time views hold only as much as the site
// has been running for.
//
// Usage: php backfill.php [--from=YYYY-MM-DD] [--to=YYYY-MM-DD] [--source=smard|entsoe]
//
// --source=entsoe reads the same archive from the platform SMARD itself
// republishes, for the day SMARD is no longer there to ask.
//
// Safe to re-run: quarter hours are written by primary key, so an interrupted
// import can be resumed by passing the week it stopped at.

use KateMorley\Grid\Database;
use KateMorley\Grid\Environment;
use KateMorley\Grid\Data\DataException;
use KateMorley\Grid\Data\Entsoe;
use KateMorley\Grid\Data\Smard;
use KateMorley\Grid\Data\Time;

spl_autoload_register(function ($class) {
  require_once(
    __DIR__ . '/classes/' . strtr(substr($class, 16), '\\', '/') . '.php'
  );
});

Environment::load(__DIR__ . '/.env');

/** The earliest quarter hour SMARD holds. */
const ARCHIVE_START = '2015-01-01';

/** Generation series, including nuclear, which ran until April 2023. */
const GENERATION = [
  'lignite'            => 1223,
  'hard_coal'          => 4069,
  'gas'                => 4071,
  'biomass'            => 4066,
  'solar'              => 4068,
  'wind_onshore'       => 4067,
  'wind_offshore'      => 1225,
  'hydro'              => 1226,
  'nuclear'            => 1224,
  'pumped_generation'  => 4070,
  'other_renewable'    => 1228,
  'other_conventional' => 1227
];

const PUMPED_CONSUMPTION = 4387;

/** Export and import series for each neighbour. */
const TRANSFERS = [
  'austria'        => [4740, 4884],
  'belgium'        => [4992, 4994],
  'czech_republic' => [4744, 4888],
  'denmark'        => [4736, 4880],
  'france'         => [4737, 4881],
  'luxembourg'     => [4738, 4882],
  'netherlands'    => [4739, 4883],
  'norway'         => [4988, 4990],
  'poland'         => [4741, 4885],
  'sweden'         => [4742, 4886],
  'switzerland'    => [4743, 4887]
];

/**
 * Day-ahead price series. Germany, Austria and Luxembourg shared a bidding
 * zone until it was split on 1st October 2018, so the earlier years carry the
 * joint price. Both are read for every week and the German one wins where it
 * exists: the two series overlap around the split, and neither ends exactly on
 * a Monday.
 */
const PRICE_JOINT = 251;
const PRICE_DE_LU = 4169;

/**
 * The ENTSO-E production type codes making up each generation column, in the
 * same order as GENERATION above, and the domains each border is drawn
 * against. Used by --source=entsoe.
 */
const ENTSOE_GENERATION = [
  'lignite'            => ['B02'],
  'hard_coal'          => ['B05'],
  'gas'                => ['B04'],
  'biomass'            => ['B01'],
  'solar'              => ['B16'],
  'wind_onshore'       => ['B19'],
  'wind_offshore'      => ['B18'],
  'hydro'              => ['B11', 'B12'],
  'nuclear'            => ['B14'],
  'pumped_generation'  => ['B10'],
  'other_renewable'    => ['B09', 'B15'],
  'other_conventional' => ['B03', 'B06', 'B17', 'B20']
];

const ENTSOE_PUMPED_CONSUMPTION = 'B10';

const ENTSOE_TRANSFERS = [
  'austria'        => [Entsoe::CONTROL_AREA, ['10YAT-APG------L']],
  'belgium'        => [Entsoe::CONTROL_AREA, ['10YBE----------2']],
  'czech_republic' => [Entsoe::CONTROL_AREA, ['10YCZ-CEPS-----N']],
  'denmark'        => [Entsoe::BIDDING_ZONE, ['10YDK-1--------W', '10YDK-2--------M']],
  'france'         => [Entsoe::CONTROL_AREA, ['10YFR-RTE------C']],
  'luxembourg'     => [Entsoe::CONTROL_AREA, ['10YLU-CEGEDEL-NQ']],
  'netherlands'    => [Entsoe::CONTROL_AREA, ['10YNL----------L']],
  'norway'         => [Entsoe::BIDDING_ZONE, ['10YNO-2--------T']],
  'poland'         => [Entsoe::CONTROL_AREA, ['10YPL-AREA-----S']],
  'sweden'         => [Entsoe::BIDDING_ZONE, ['10Y1001A1001A47J']],
  'switzerland'    => [Entsoe::CONTROL_AREA, ['10YCH-SWISSGRIDZ']]
];

/** The bidding zones the day-ahead price has been published for. */
const ENTSOE_PRICES = [
  'de_lu' => '10Y1001A1001A82H',
  'joint' => '10Y1001A1001A63L'
];

$options = getopt('', ['from::', 'to::', 'source::']);
$source  = $options['source'] ?? 'smard';

if (!in_array($source, ['smard', 'entsoe'], true)) {
  exit("--source must be smard or entsoe\n");
}
$from    = strtotime(($options['from'] ?? ARCHIVE_START) . ' UTC');
$to      = strtotime(($options['to'] ?? 'now') . ' UTC');

// the tail is left to the regular update, which handles part-published
// quarter hours properly
$periods = ($source === 'entsoe')
  ? months($from, $to - 7 * 24 * 60 * 60)
  : array_map(
      fn ($week) => [intdiv($week, 1000), null],
      Smard::weeksBetween($from, $to - 7 * 24 * 60 * 60)
    );

$database = new Database();
$columns  = array_merge(
  array_keys(GENERATION),
  ['pumped_consumption'],
  array_keys(TRANSFERS),
  ['price']
);

echo 'Importing ' . count($periods) . ' ' . ($source === 'entsoe' ? 'months' : 'weeks')
  . ' from ' . strtoupper($source) . "\n";

$total = 0;
$start = microtime(true);

foreach ($periods as $index => list($periodFrom, $periodTo)) {
  $label = gmdate('Y-m-d', $periodFrom);
  $rows  = ($source === 'entsoe')
    ? readPeriod($periodFrom, $periodTo)
    : readWeek($periodFrom * 1000);

  if (count($rows) === 0) {
    echo '  ' . $label . " no data\n";
    continue;
  }

  $database->insertQuarterHours($columns, $rows);
  $total += count($rows);

  $done    = $index + 1;
  $elapsed = microtime(true) - $start;
  $left    = ($elapsed / $done) * (count($periods) - $done);

  printf(
    "  %s %5d quarter hours (%d/%d, %d%%, %s left)\n",
    $label,
    count($rows),
    $done,
    count($periods),
    100 * $done / count($periods),
    formatDuration($left)
  );
}

echo 'Wrote ' . number_format($total) . " quarter hours\n";

readEmissions($database, $from, $to);

echo 'Rebuilding records and aggregates… ';
$database->finishUpdate();
echo "done\n";

/**
 * Returns the calendar months covering a period, as start and end timestamps.
 *
 * ENTSO-E answers a month comfortably and times out on a year, so the month
 * is the unit the import walks in.
 *
 * @param int $from The start of the period
 * @param int $to   The end of the period
 *
 * @return array<array{0:int,1:int}>
 */
function months(int $from, int $to): array {
  $months = [];
  $start  = strtotime(gmdate('Y-m-01 00:00:00', $from) . ' UTC');

  while ($start < $to) {
    $next     = strtotime('+1 month', $start);
    $months[] = [$start, min($next, $to)];
    $start    = $next;
  }

  return $months;
}

/**
 * Reads every series for a period from ENTSO-E and returns rows ready to
 * insert.
 *
 * This is the alternative to the SMARD import, and exists so that the archive
 * can still be rebuilt if SMARD ever stops carrying it: both republish the
 * same submissions, and the figures agree to the last digit. It is the slower
 * of the two, because each border is a request of its own and the platform
 * allows sixty a minute, so expect hours rather than minutes for the full
 * eleven years.
 *
 * @param int $from The start of the period
 * @param int $to   The end of the period
 */
function readPeriod(int $from, int $to): array {
  $read   = retry(fn () => Entsoe::readGeneration($from, $to));
  $flows  = retry(fn () => Entsoe::readFlows(ENTSOE_TRANSFERS, $from, $to));
  $prices = retry(fn () => Entsoe::readPrices(ENTSOE_PRICES, $from, $to));

  // the times come from the generation, which is the series that has to be
  // there: a quarter hour with flows but no generation isn't worth a row
  $times = [];

  foreach (ENTSOE_GENERATION as $codes) {
    foreach ($codes as $code) {
      $times += $read['generation'][$code] ?? [];
    }
  }

  ksort($times);

  $rows = [];

  foreach (array_keys($times) as $time) {
    // Entsoe already returns times normalised and quoted for the database
    $row = [$time];

    // a type with no value for a quarter hour counts as zero. Over the
    // archive's eleven years interconnectors get built and reactors shut
    // down, so an absent series means the thing itself wasn't there
    foreach (ENTSOE_GENERATION as $codes) {
      $sum = 0;

      foreach ($codes as $code) {
        $sum += $read['generation'][$code][$time] ?? 0;
      }

      $row[] = round($sum, 3);
    }

    $row[] = -round($read['consumption'][ENTSOE_PUMPED_CONSUMPTION][$time] ?? 0, 3);

    foreach (array_keys(ENTSOE_TRANSFERS) as $column) {
      $row[] = $flows[$column][$time] ?? 0;
    }

    // the joint zone's price stands in for the years before the 2018 split
    $row[] = $prices['de_lu'][$time] ?? $prices['joint'][$time] ?? 0;

    $rows[] = $row;
  }

  return $rows;
}

/**
 * Reads every series for a week and returns rows ready to insert.
 *
 * @param int $week The week timestamp, in milliseconds
 */
function readWeek(int $week): array {
  $generation = retry(fn () => Smard::readWeeks(
    array_merge(array_values(GENERATION), [PUMPED_CONSUMPTION]),
    [$week]
  ));

  $transfers = retry(fn () => Smard::readWeeks(
    array_merge(...array_values(TRANSFERS)),
    [$week]
  ));

  $prices = retry(fn () => Smard::readWeeks(
    [PRICE_JOINT, PRICE_DE_LU],
    [$week],
    1
  ));

  // the times come from the generation, which is the series that has to be
  // there: a quarter hour with flows but no generation isn't worth a row
  $times = [];

  foreach (GENERATION as $id) {
    $times += $generation[$id];
  }

  ksort($times);

  $rows = [];

  foreach (array_keys($times) as $time) {
    $row = [Time::normaliseUnix($time, 15)];

    // a series with no value for a quarter hour is stored as zero. Over the
    // archive's eleven years interconnectors get built and reactors shut down,
    // so an absent series means the thing itself wasn't there
    foreach (GENERATION as $id) {
      $row[] = $generation[$id][$time] ?? 0;
    }

    $row[] = -($generation[PUMPED_CONSUMPTION][$time] ?? 0);

    foreach (TRANSFERS as list($export, $import)) {
      $row[] = round(
        -(($transfers[$export][$time] ?? 0) + ($transfers[$import][$time] ?? 0)),
        3
      );
    }

    $row[] = $prices[PRICE_DE_LU][$time]
      ?? $prices[PRICE_JOINT][$time]
      ?? 0;

    $rows[] = $row;
  }

  return $rows;
}

/**
 * Reads the official emissions series year by year and writes it.
 *
 * @param Database $database The database instance
 * @param int      $from     The start of the period
 * @param int      $to       The end of the period
 */
function readEmissions(Database $database, int $from, int $to): void {
  for ($year = (int)gmdate('Y', $from); $year <= (int)gmdate('Y', $to); $year ++) {
    echo '  emissions ' . $year . ' ';

    $data = retry(function () use ($year) {
      $rawData = @file_get_contents(sprintf(
        'https://api.energy-charts.info/co2eq?country=de&start=%d-01-01&end=%d-01-01',
        $year,
        $year + 1
      ));

      if ($rawData === false) {
        throw new DataException('Failed to read emissions');
      }

      $jsonData = json_decode($rawData, true);

      if (
        !isset($jsonData['unix_seconds']) || !is_array($jsonData['unix_seconds'])
        || !isset($jsonData['co2eq']) || !is_array($jsonData['co2eq'])
      ) {
        throw new DataException('Missing emissions data');
      }

      $data = [];

      foreach ($jsonData['unix_seconds'] as $index => $seconds) {
        $value = $jsonData['co2eq'][$index] ?? null;

        if ($value !== null && $seconds % 900 === 0) {
          $data[] = [Time::normaliseUnix($seconds, 15), $value];
        }
      }

      return $data;
    });

    foreach (array_chunk($data, 500) as $chunk) {
      $database->updateExisting(['emissions'], $chunk);
    }

    echo count($data) . " quarter hours\n";
  }
}

/**
 * Runs a callback, retrying on failure.
 *
 * @param callable $callback The callback
 */
function retry(callable $callback): mixed {
  for ($attempt = 1; ; $attempt ++) {
    try {
      return $callback();
    } catch (DataException $e) {
      if ($attempt === 4) {
        throw $e;
      }

      sleep($attempt * 5);
    }
  }
}

/**
 * Formats a duration in seconds.
 *
 * @param float $seconds The duration
 */
function formatDuration(float $seconds): string {
  if ($seconds < 90) {
    return round($seconds) . 's';
  }

  if ($seconds < 5400) {
    return round($seconds / 60) . 'm';
  }

  return round($seconds / 3600, 1) . 'h';
}
