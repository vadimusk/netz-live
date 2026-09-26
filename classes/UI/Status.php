<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;

/** Outputs the status. */
class Status {
  /**
   * The age, in seconds, beyond which the newest data is shown as stale rather
   * than merely timestamped. Normal lag is around 35 minutes, so this sits well
   * above that — an ordinary delay isn't flagged — and near the watchdog's own
   * sense of the data not advancing, so the badge and the alert agree.
   */
  private const STALE_AFTER = 5400;

  /**
   * Outputs the status.
   *
   * @param Datum  $datum      The datum
   * @param string $time       The time
   * @param string $locale     The locale ('de' or 'en')
   * @param bool   $help       Whether to show the help
   * @param ?int   $ageSeconds The age of the data in seconds, shown beside the
   *                           time on the live panel; null elsewhere
   * @param bool   $estimate   Whether this is the estimated row for the
   *                           quarter hour running now
   * @param ?float $price      For the estimated row, the day-ahead price of
   *                           that quarter hour, which is known rather than
   *                           estimated; null shows a dash
   * @param ?string $switch    The switch between the reported and the present
   *                           quarter hour, shown in place of the time
   */
  public static function output(
    Datum  $datum,
    string $time,
    string $locale,
    bool   $help = false,
    ?int   $ageSeconds = null,
    bool   $estimate = false,
    ?float $price = null,
    ?string $switch = null
  ): void {
?>
          <dl>
            <dt><?= I18n::t('status.time', $locale) ?><?php if ($help) { ?> <span data-help="time"></span><?php } ?></dt>
            <dd><?= $switch ?? $time ?><?php if ($ageSeconds !== null) { ?><span class="age<?= $ageSeconds > self::STALE_AFTER ? ' stale' : '' ?>"><?= I18n::age($ageSeconds, $locale) ?></span><?php } elseif ($estimate) { ?><span class="age"><?= I18n::t('now.note', $locale) ?> <span data-help="now"></span></span><?php } ?></dd>
            <dt><?= I18n::t('status.price', $locale) ?><?php if ($help) { ?>  <span data-help="price"></span><?php } ?></dt>
            <dd><?php if ($estimate && $price === null) { ?>—<?php } else { ?><?= Value::formatPrice($estimate ? $price : $datum->price, $locale) ?><abbr>/MWh</abbr><?php } ?></dd>
            <dt><?= I18n::t('status.emissions', $locale) ?><?php if ($help) { ?> <span data-help="emissions"></span><?php } ?></dt>
            <dd class="<?= Emissions::get((int)$datum->emissions)->class() ?>"><?= $estimate ? '<span class="est">≈' . (int)$datum->emissions . '</span>' : (int)$datum->emissions ?><abbr>g/kWh</abbr></dd>
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
