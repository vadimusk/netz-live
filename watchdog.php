<?php

// Checks that the site is still being updated, and reports when it isn't.
//
// Run from cron, separately from update.php. The failure this exists for is
// the quiet one: update.php can run every five minutes, exit cleanly, log
// nothing but OK, and still write no new data — that is exactly what a stalled
// upstream series produced, unnoticed for eighteen hours. Counting errors
// cannot catch it, because there is no error. Only the age of the newest
// quarter hour gives it away.
//
// Usage: php watchdog.php [--verbose]

use KateMorley\Grid\Environment;

spl_autoload_register(function ($class) {
  require_once(
    __DIR__ . '/classes/' . strtr(substr($class, 16), '\\', '/') . '.php'
  );
});

Environment::load(__DIR__ . '/.env');

/**
 * How old the newest quarter hour may be before something is wrong, in
 * seconds.
 *
 * SMARD publishes around 45 minutes after the fact and the cron adds up to
 * five more, so anything under an hour is normal. Two hours means several
 * publication rounds have been missed.
 */
const STALE_AFTER = 2 * 60 * 60;

/** How long to wait before repeating an alert about the same problem. */
const REPEAT_AFTER = 6 * 60 * 60;

/** Where the last alert is remembered, so a long outage isn't reported hourly. */
const STATE_FILE = '/var/lib/netz-live/watchdog.state';

$verbose = in_array('--verbose', $argv, true);
$problem = check();

if ($problem === null) {
  if ($verbose) {
    echo "OK\n";
  }

  // clearing the state means a problem that comes back after being fixed is
  // reported again immediately rather than being silenced by the repeat window
  @unlink(STATE_FILE);
  exit(0);
}

echo date('c') . ' ' . $problem;

if (shouldReport($problem)) {
  $channels = notify($problem);

  echo $channels === 0
    ? ' [no alert channel configured]'
    : ' [alerted via ' . $channels . ' channel(s)]';
} else {
  echo ' [already reported, alert suppressed]';
}

echo "\n";

exit(1);

/**
 * Returns a description of what is wrong, or null if everything is fine.
 */
function check(): ?string {
  try {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $connection = new \mysqli(
      getenv('DATABASE_HOSTNAME'),
      getenv('DATABASE_USERNAME'),
      getenv('DATABASE_PASSWORD'),
      getenv('DATABASE_DATABASE')
    );
  } catch (\Throwable $e) {
    return 'database unavailable: ' . $e->getMessage();
  }

  $latest = $connection->query(
    'SELECT MAX(time) FROM past_quarter_hours'
  )->fetch_row()[0];

  if ($latest === null) {
    return 'no data at all in past_quarter_hours';
  }

  $age = time() - strtotime($latest . ' UTC');

  if ($age > STALE_AFTER) {
    return sprintf(
      'data has not advanced for %.1f hours (newest quarter hour is %s UTC)',
      $age / 3600,
      $latest
    );
  }

  return null;
}

/**
 * Returns whether to send an alert, recording that one was sent.
 *
 * @param string $problem The problem
 */
function shouldReport(string $problem): bool {
  $directory = dirname(STATE_FILE);

  if (!is_dir($directory)) {
    @mkdir($directory, 0755, true);
  }

  // the kind of problem rather than its exact wording, so that an ageing
  // figure in the message doesn't read as a new problem every time
  $kind = explode(':', $problem)[0];
  $now  = time();

  if (is_readable(STATE_FILE)) {
    $state = json_decode((string)file_get_contents(STATE_FILE), true);

    if (
      is_array($state)
      && ($state['kind'] ?? null) === $kind
      && $now - ($state['time'] ?? 0) < REPEAT_AFTER
    ) {
      return false;
    }
  }

  @file_put_contents(
    STATE_FILE,
    json_encode(['kind' => $kind, 'time' => $now]),
    LOCK_EX
  );

  return true;
}

/**
 * Sends an alert through whichever channels are configured, returning how
 * many were used.
 *
 * @param string $problem The problem
 */
function notify(string $problem): int {
  $message  = 'Stromnetz: Live — ' . $problem;
  $channels = 0;

  $token = (string)getenv('ALERT_TELEGRAM_TOKEN');
  $chat  = (string)getenv('ALERT_TELEGRAM_CHAT');

  if ($token !== '' && $chat !== '') {
    post(
      'https://api.telegram.org/bot' . $token . '/sendMessage',
      ['chat_id' => $chat, 'text' => $message]
    );

    $channels ++;
  }

  $webhook = (string)getenv('ALERT_WEBHOOK');

  if ($webhook !== '') {
    post($webhook, ['text' => $message]);

    $channels ++;
  }

  return $channels;
}

/**
 * Posts JSON to a URL, ignoring the response.
 *
 * @param string $url  The URL
 * @param array  $data The data
 */
function post(string $url, array $data): void {
  $handle = curl_init($url);

  curl_setopt_array($handle, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15
  ]);

  curl_exec($handle);
}
