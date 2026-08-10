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
            <dd class="<?= Emissions::get((int)$datum->emissions)->class() ?>"><?= (int)$datum->emissions ?><abbr>g/kWh</abbr></dd>
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
    );
  }
}
