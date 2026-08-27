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
 * ENTSO-E publishes about half an hour after the quarter hour ends, and the
 * cron adds up to five more, so anything under about forty minutes is normal.
 * Ninety minutes means several publication rounds have been missed — and
 * because the fallback to SMARD costs hours, this has to fire well before
 * that would look like business as usual.
 */
const STALE_AFTER = 90 * 60;

/** How long to wait before repeating an alert about the same problem. */
const REPEAT_AFTER = 6 * 60 * 60;

/** Where the last alert is remembered, so a long outage isn't reported hourly. */
const STATE_FILE = '/var/lib/netz-live/watchdog.state';

/**
 * Touched on every clean check, so that the watchdog itself can be seen to be
 * running.
 *
 * A watchdog that reports only problems is silent whether all is well or it
 * stopped running months ago. The modification time of this file tells the two
 * apart.
 */
const HEARTBEAT_FILE = '/var/lib/netz-live/watchdog.ok';

/**
 * The origin to request when checking that the site is actually being served,
 * and the host to ask it for.
 *
 * The database being current says nothing about anyone being able to read the
 * page: the web server can be down while updates carry on writing files
 * perfectly happily. This checks the origin directly rather than the public
 * URL, so that an outage at the CDN isn't mistaken for one here — the CDN
 * keeps serving cached pages anyway, and nothing on this machine can fix it.
 */
const SITE_ORIGIN = 'http://127.0.0.1/';
const SITE_HOST   = 'netz.vterskov.de';

$verbose = in_array('--verbose', $argv, true);
$problem = check();

if ($problem === null) {
  if ($verbose) {
    echo "OK\n";
  }

  ensureDirectory();
  @touch(HEARTBEAT_FILE);

  // clearing the state means a problem that comes back after being fixed is
  // reported again immediately rather than being silenced by the repeat window
  @unlink(STATE_FILE);
  exit(0);
}

echo date('c') . ' ' . $problem;

if (shouldReport($problem)) {
  list($attempted, $delivered) = notify($problem);

  if ($attempted === 0) {
    echo ' [no alert channel configured]';
  } elseif ($delivered === 0) {
    // the alarm bell itself is broken, which is worse than the problem it was
    // ringing about: this line is what the update log will show for it
    echo ' [ALERT NOT DELIVERED: ' . $attempted . ' channel(s) all failed]';
  } else {
    echo ' [alerted via ' . $delivered . ' of ' . $attempted . ' channel(s)]';
  }
} else {
  echo ' [already reported, alert suppressed]';
}

echo "\n";

exit(1);

/**
 * Returns a description of the site not being served, or null if it is.
 */
function checkServed(): ?string {
  $handle = curl_init(SITE_ORIGIN);

  curl_setopt_array($handle, [
    CURLOPT_HTTPHEADER     => ['Host: ' . SITE_HOST],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15,
    CURLOPT_FOLLOWLOCATION => true,
    // the redirect to HTTPS comes back to this same machine, whose
    // certificate is issued for the public name rather than for 127.0.0.1
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_RESOLVE        => [SITE_HOST . ':443:127.0.0.1']
  ]);

  $body = curl_exec($handle);
  $code = curl_getinfo($handle, CURLINFO_RESPONSE_CODE);

  if ($body === false) {
    return 'site not being served: ' . curl_error($handle);
  }

  if ($code !== 200) {
    return 'site not being served: HTTP ' . $code;
  }

  return null;
}

/** Creates the directory holding the state and heartbeat files. */
function ensureDirectory(): void {
  $directory = dirname(STATE_FILE);

  if (!is_dir($directory)) {
    @mkdir($directory, 0755, true);
  }
}

/**
 * Returns a description of what is wrong, or null if everything is fine.
 */
function check(): ?string {
  $served = checkServed();

  if ($served !== null) {
    return $served;
  }

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
  ensureDirectory();

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
function notify(string $problem): array {
  $message   = 'Stromnetz: Live — ' . $problem;
  $attempted = 0;
  $delivered = 0;

  $token = (string)getenv('ALERT_TELEGRAM_TOKEN');
  $chat  = (string)getenv('ALERT_TELEGRAM_CHAT');

  if ($token !== '' && $chat !== '') {
    $attempted ++;
    $delivered += post(
      'https://api.telegram.org/bot' . $token . '/sendMessage',
      ['chat_id' => $chat, 'text' => $message]
    ) ? 1 : 0;
  }

  $webhook = (string)getenv('ALERT_WEBHOOK');

  if ($webhook !== '') {
    $attempted ++;
    $delivered += post($webhook, ['text' => $message]) ? 1 : 0;
  }

  return [$attempted, $delivered];
}

/**
 * Posts JSON to a URL, returning whether it was accepted.
 *
 * The response is checked rather than discarded, because a monitor that
 * cannot tell a delivered alert from a rejected one is not monitoring
 * anything: a revoked token or a blocked bot would leave the site failing
 * quietly behind an alarm that reports itself as ringing.
 *
 * @param string $url  The URL
 * @param array  $data The data
 */
function post(string $url, array $data): bool {
  $handle = curl_init($url);

  curl_setopt_array($handle, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode($data),
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 15
  ]);

  curl_exec($handle);

  $code  = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
  $error = curl_errno($handle);

  curl_close($handle);

  return $error === 0 && $code >= 200 && $code < 300;
}
