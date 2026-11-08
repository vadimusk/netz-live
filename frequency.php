<?php

// Takes the grid frequency every ten seconds, for update.php to show.
//
// Run from cron once a minute, separately from update.php. The source
// publishes only the reading of the moment, so the hour behind the sparkline
// has to be gathered here as it happens (see
// classes/Data/Netzfrequenzmessung.php). Each run takes six readings, on the
// minute and every ten seconds after it, and prints a line only when one of
// them fails.
//
// Usage: php frequency.php

use KateMorley\Grid\Data\DataException;
use KateMorley\Grid\Data\Netzfrequenzmessung;
use KateMorley\Grid\Environment;

spl_autoload_register(function ($class) {
  require_once(
    __DIR__ . '/classes/' . strtr(substr($class, 16), '\\', '/') . '.php'
  );
});

Environment::load(__DIR__ . '/.env');

/** The number of seconds between readings. */
const INTERVAL = 10;

/**
 * Held while a run is taking readings.
 *
 * A run ends a few seconds before the next begins, but a slow request could
 * stretch one past the minute; the next then leaves without asking, rather
 * than doubling the rate the operator agreed to.
 */
const LOCK_FILE = '/var/lib/netz-live/frequency.lock';

if (!Netzfrequenzmessung::configured()) {
  echo "NETZFREQUENZMESSUNG_NR is not set\n";
  exit(1);
}

$lock = @fopen(LOCK_FILE, 'c');

if ($lock === false) {
  echo 'Cannot open ' . LOCK_FILE . "\n";
  exit(1);
}

if (!flock($lock, LOCK_EX | LOCK_NB)) {
  exit;
}

$minute   = intdiv(time(), 60) * 60;
$attempts = 0;
$failures = [];

for ($offset = 0; $offset < 60; $offset += INTERVAL) {
  $wait = $minute + $offset - microtime(true);

  // cron starts a run a second or two after the minute, so a moment up to
  // half an interval past is still taken, a little late; one cron started
  // later than that for is skipped rather than made up for, which would put
  // the readings closer together than agreed
  if ($wait < -INTERVAL / 2) {
    continue;
  }

  if ($wait > 0) {
    usleep((int)round($wait * 1000000));
  }

  $attempts ++;

  try {
    [$time, $hertz] = Netzfrequenzmessung::measure();

    Netzfrequenzmessung::record($time, $hertz);
  } catch (DataException $exception) {
    $failures[] = $exception->getMessage();
  }
}

if (count($failures) !== 0) {
  echo gmdate('Y-m-d H:i', $minute) . ' UTC: ' . count($failures) . ' of '
    . $attempts . ' readings failed (' . implode('; ', array_unique($failures))
    . ")\n";
}
