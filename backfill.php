<?php

// Imports the historic archive from SMARD, which reaches back to the start of
// 2015. This is a one-off: the regular update only ever looks at the past day,
// so without this the year and all-time views hold only as much as the site
// has been running for.
//
// Usage: php backfill.php [--from=YYYY-MM-DD] [--to=YYYY-MM-DD]
//
// Safe to re-run: quarter hours are written by primary key, so an interrupted
// import can be resumed by passing the week it stopped at.

use KateMorley\Grid\Database;
use KateMorley\Grid\Environment;
use KateMorley\Grid\Data\DataException;
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

$options = getopt('', ['from::', 'to::']);
$from    = strtotime(($options['from'] ?? ARCHIVE_START) . ' UTC');
$to      = strtotime(($options['to'] ?? 'now') . ' UTC');

// the current week is left to the regular update, which handles part-published
// quarter hours properly
$weeks = Smard::weeksBetween($from, $to - 7 * 24 * 60 * 60);

$database = new Database();
$columns  = array_merge(
  array_keys(GENERATION),
  ['pumped_consumption'],
  array_keys(TRANSFERS),
  ['price']
);

echo 'Importing ' . count($weeks) . " weeks\n";

$total = 0;
$start = microtime(true);

foreach ($weeks as $index => $week) {
  $label = gmdate('Y-m-d', intdiv($week, 1000));
  $rows  = readWeek($week);

  if (count($rows) === 0) {
    echo '  ' . $label . " no data\n";
    continue;
  }

  $database->insertQuarterHours($columns, $rows);
  $total += count($rows);

  $done    = $index + 1;
  $elapsed = microtime(true) - $start;
  $left    = ($elapsed / $done) * (count($weeks) - $done);

  printf(
    "  %s %5d quarter hours (%d/%d, %d%%, %s left)\n",
    $label,
    count($rows),
    $done,
    count($weeks),
    100 * $done / count($weeks),
    formatDuration($left)
  );
}

echo 'Wrote ' . number_format($total) . " quarter hours\n";

readEmissions($database, $from, $to);

echo 'Rebuilding records and aggregates… ';
$database->finishUpdate();
echo "done\n";

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
