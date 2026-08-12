<?php

// Updates the site

use KateMorley\Grid\Database;
use KateMorley\Grid\Environment;
use KateMorley\Grid\Data\DataException;
use KateMorley\Grid\Data\Emissions;
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

$database = new Database();

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

  'Updating visits…     ' => function (Database $database) {
    Visits::update($database);
  },

  'Finishing update…    ' => function (Database $database) {
    $database->finishUpdate();
  },

  'Outputting files…    ' => function (Database $database) {
    $state = $database->getState();

    ob_start();
    (new UI($state, 'de'))->output();
    file_put_contents(__DIR__ . '/public/index.html', ob_get_clean(), LOCK_EX);

    if (!is_dir(__DIR__ . '/public/en')) {
      mkdir(__DIR__ . '/public/en');
    }

    ob_start();
    (new UI($state, 'en'))->output();
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
  }

  echo ' (' . sprintf('%0.3f', microtime(true) - $start) . " seconds)\n";
}
