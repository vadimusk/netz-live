<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;

/** Outputs the status. */
class Status {
  /**
   * Outputs the status.
   *
   * @param Datum  $datum  The datum
   * @param string $time   The time
   * @param string $locale The locale ('de' or 'en')
   * @param bool   $help   Whether to show the help
   */
  public static function output(
    Datum  $datum,
    string $time,
    string $locale,
    bool   $help = false
  ): void {
?>
          <dl>
            <dt><?= I18n::t('status.time', $locale) ?><?php if ($help) { ?> <span data-help="time"></span><?php } ?></dt>
            <dd><?= $time ?></dd>
            <dt><?= I18n::t('status.price', $locale) ?><?php if ($help) { ?>  <span data-help="price"></span><?php } ?></dt>
            <dd><?= Value::formatPrice($datum->price, $locale) ?><abbr>/MWh</abbr></dd>
            <dt><?= I18n::t('status.emissions', $locale) ?><?php if ($help) { ?> <span data-help="emissions"></span><?php } ?></dt>
            <dd><?= (int)$datum->emissions ?><abbr>g/kWh</abbr></dd>
          </dl>
<?php
  }

  /**
   * Formats a time as HTML and returns it.
   *
   * @param int    $time   The time
   * @param string $locale The locale ('de' or 'en')
   */
  public static function time(int $time, string $locale): string {
    $local = (new \DateTime('@' . $time))->setTimezone(
      new \DateTimeZone('Europe/Berlin')
    );

    return (
      '<time datetime="'
      . gmdate('Y-m-d\TH:i:s\Z', $time)
      . '">'
      . ($locale === 'de'
        ? $local->format('H:i')
        : $local->format('g:i') . '<abbr>' . $local->format('a') . '</abbr>')
      . '</time>'
      . self::age($time, $locale)
    );
  }

  /**
   * Returns the age of the data as HTML, or an empty string if it is recent
   * enough not to need explaining.
   *
   * Cross-border flows are published a couple of hours after the fact, and
   * the site waits for them, so the time shown is not the current one. Saying
   * how far behind it is stops that reading as a stuck page.
   *
   * @param int    $time   The time of the data
   * @param string $locale The locale ('de' or 'en')
   */
  private static function age(int $time, string $locale): string {
    $hours = (int)floor((time() - $time) / 3600);

    if ($hours < 1) {
      return '';
    }

    return (
      '<abbr class="age">'
      . I18n::t('status.age', $locale, [$hours])
      . '</abbr>'
    );
  }
}
