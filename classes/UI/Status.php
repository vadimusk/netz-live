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
   * @param array<string,int> $estimated The control areas estimated in the
   *                           quarter hour shown, mapped to the time each last
   *                           reported, named under the time on the live panel
   */
  public static function output(
    Datum  $datum,
    string $time,
    string $locale,
    bool   $help = false,
    ?int   $ageSeconds = null,
    array  $estimated = []
  ): void {
?>
          <dl>
            <dt><?= I18n::t('status.time', $locale) ?><?php if ($help) { ?> <span data-help="time"></span><?php } ?></dt>
            <dd><?= $time ?><?php if ($ageSeconds !== null) { ?><span class="age<?= $ageSeconds > self::STALE_AFTER ? ' stale' : '' ?>"><?= I18n::age($ageSeconds, $locale) ?></span><?php } ?><?= self::estimated($estimated, $locale) ?></dd>
            <dt><?= I18n::t('status.price', $locale) ?><?php if ($help) { ?>  <span data-help="price"></span><?php } ?></dt>
            <dd><?= Value::formatPrice($datum->price, $locale) ?><abbr>/MWh</abbr></dd>
            <dt><?= I18n::t('status.emissions', $locale) ?><?php if ($help) { ?> <span data-help="emissions"></span><?php } ?></dt>
            <dd class="<?= Emissions::get((int)$datum->emissions)->class() ?>"><?= (int)$datum->emissions ?><abbr>g/kWh</abbr></dd>
          </dl>
<?php
  }

  /**
   * Returns the note naming the control areas estimated in the quarter hour
   * shown, or nothing where every operator has reported it.
   *
   * An operator a quarter hour behind the others is ordinary and is named
   * plainly. One that has been silent longer than the data is ever late is
   * named with how long, in the colour stale data takes, since the figure
   * then holds a guess at a quarter of the country.
   *
   * @param array<string,int> $estimated The areas, mapped to the time each
   *                                     last reported
   * @param string            $locale    The locale ('de' or 'en')
   */
  private static function estimated(array $estimated, string $locale): string {
    $html = '';

    foreach ($estimated as $name => $time) {
      $silent = time() - $time;
      $stale  = $silent > self::STALE_AFTER;

      $html .= '<span class="age estimate' . ($stale ? ' stale' : '') . '">'
        . ($stale
          ? sprintf(I18n::t('status.estimated.since', $locale), $name, I18n::duration($silent, $locale))
          : sprintf(I18n::t('status.estimated', $locale), $name))
        . '</span>';
    }

    return $html;
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
