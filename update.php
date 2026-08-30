<?php

// Updates the site

use KateMorley\Grid\Database;
use KateMorley\Grid\Environment;
use KateMorley\Grid\Data\DataException;
use KateMorley\Grid\Data\Emissions;
use KateMorley\Grid\Data\Forecast;
use KateMorley\Grid\Data\Frequency;
use KateMorley\Grid\Data\Generation;
use KateMorley\Grid\Data\Pricing;
use KateMorley\Grid\Data\Visits;
use KateMorley\Grid\UI\Favicon;
use KateMorley\Grid\UI\UI;

spl_autoload_register(function ($class) {
  require_once(
    __DIR__
    . '/classes/'
    . strtr(substr($class, 16), '\\', '/')
    . '.php'
  );
});

Environment::load(__DIR__ . '/.env');

// without the database there is nothing any of the steps below can do, so
// this exits rather than throwing an uncaught fatal: the message stays
// readable in the log, and the non-zero status lets the watchdog tell a
// failed run from a run that had nothing to write
try {
  $database = new Database();
} catch (\Throwable $e) {
  echo 'Database unavailable: ' . $e->getMessage() . "\n";
  exit(1);
}

// read fresh on every run and never stored: see classes/Data/Frequency.php
$frequency = null;

foreach ([
  'Updating generation… ' => function (Database $database) {
    Generation::update($database);
  },

  'Updating emissions…  ' => function (Database $database) {
    Emissions::update($database);
  },

  'Updating pricing…    ' => function (Database $database) {
    Pricing::update($database);
  },

  // read after the measured data, and isolated like every other step: the
  // forecast only fills the gap between the newest confirmed quarter hour and
  // now, so losing it costs the dashed tail and nothing else
  'Updating forecast…   ' => function (Database $database) {
    Forecast::update($database);
  },

  'Updating visits…     ' => function (Database $database) {
    Visits::update($database);
  },

  'Finishing update…    ' => function (Database $database) {
    $database->finishUpdate();
  },

  // last before the pages are written, so the figure on them is as close to
  // now as it can be. A failure here costs the band and nothing else.
  'Reading frequency…   ' => function (Database $database) use (&$frequency) {
    $frequency = Frequency::read();
  },

  'Outputting files…    ' => function (Database $database) use (&$frequency) {
    $state = $database->getState();

    ob_start();
    (new UI($state, 'de', $frequency))->output();
    file_put_contents(__DIR__ . '/public/index.html', ob_get_clean(), LOCK_EX);

    if (!is_dir(__DIR__ . '/public/en')) {
      mkdir(__DIR__ . '/public/en');
    }

    ob_start();
    (new UI($state, 'en', $frequency))->output();
    file_put_contents(__DIR__ . '/public/en/index.html', ob_get_clean(), LOCK_EX);

    file_put_contents(
      __DIR__ . '/public/favicon.svg',
      Favicon::create($state->latest->sources),
      LOCK_EX
    );

    // the sitemap carries the time of the newest data as its last-modified
    // date, so a crawler can tell the pages are still being updated
    $modified = gmdate('c', $state->time);

    file_put_contents(
      __DIR__ . '/public/sitemap.xml',
      '<?xml version="1.0" encoding="UTF-8"?>'
      . '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'
      . ' xmlns:xhtml="http://www.w3.org/1999/xhtml">'
      . UI::sitemapEntry('', $modified)
      . UI::sitemapEntry('en/', $modified)
      . '</urlset>',
      LOCK_EX
    );
  }
] as $action => $callback) {
  echo $action;

  $start = microtime(true);

  try {
    $callback($database);

    echo 'OK';

    $database->clearErrors($action);
  } catch (DataException $e) {
    $error = $e->getMessage();
    echo 'ERROR: ' . $error;

    if (
      $database->getErrorCount($action, $error)
      >= (int)getenv('ERROR_REPORTING_THRESHOLD')
    ) {
      $database->clearErrors($action);

      if ((int)getenv('ERROR_REPORTING_THRESHOLD') > 0) {
        trigger_error(trim($action) . ' ' . $error);
      }
    }
  } catch (\Throwable $e) {
    // anything a step didn't anticipate is still that step's problem: the
    // later ones, in particular writing the pages, shouldn't be skipped
    // because an earlier one broke in a way nobody predicted
    echo 'FAILED: ' . get_class($e) . ': ' . $e->getMessage();
  }

  echo ' (' . sprintf('%0.3f', microtime(true) - $start) . " seconds)\n";
}
