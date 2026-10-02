<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\Data\Frequency as FrequencyReading;
use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\State;

/**
 * Outputs the band describing the quarter hour running now: demand, which is
 * estimated, the price, which is known, and the grid frequency, which is
 * measured.
 *
 * Each figure carries a small line of the hours behind it, drawn the way the
 * graphs further down draw theirs: solid for what was reported or is known,
 * dashed for what is estimated. The demand line turns dashed where the reports
 * stop; the price line never does, because the price was fixed the day before.
 * So the band says in its lines, not only in its labels, which of the three is
 * a measurement, which a certainty and which a guess.
 */
class NowBand {
  /** The drawing size of a line, in its own units. */
  private const WIDTH  = 160;
  private const HEIGHT = 36;

  /**
   * How far back a line reaches, in seconds, and the least it shows of the
   * reported stretch before the reports stop, so a long stall still draws the
   * point the estimate grew from.
   */
  private const WINDOW = 8 * 60 * 60;
  private const LEAD   = 2 * 60 * 60;

  /**
   * Outputs the band.
   *
   * @param State             $state     The state
   * @param ?FrequencyReading $frequency The grid frequency, if it was read
   * @param string            $locale    The locale ('de' or 'en')
   */
  public static function output(
    State             $state,
    ?FrequencyReading $frequency,
    string            $locale
  ): void {
    $now      = intdiv(time(), 900) * 900;
    $estimate = $state->predicted[$now] ?? null;
    $price    = ($state->upcomingPrices[$now] ?? null)?->price
      ?? ($now <= $state->time ? $state->latest->price : null);
    $from     = min($now - self::WINDOW, $state->time - self::LEAD);

    $reported = array_filter(
      $state->daySeries,
      fn ($time) => $time >= $from && $time <= $state->time,
      ARRAY_FILTER_USE_KEY
    );

?>
        <div class="now-band">
          <div class="head">
            <span class="live"></span><?= I18n::t('now.label', $locale) ?> <?= Status::time($now, $locale) ?> <span data-help="now"></span>
          </div>
          <div class="cell">
            <span class="label"><?= I18n::t('equation.demand', $locale) ?></span>
<?php if ($estimate === null) { ?>
            <span class="value">—</span>
            <span class="note"><?= I18n::t('now.none', $locale) ?></span>
<?php } else { ?>
            <span class="value"><span class="est">≈<?= Value::formatTotalPower(self::demand($estimate), $locale) ?></span><abbr>GW</abbr></span>
            <span class="note"><?= I18n::t('now.estimated', $locale) ?> ±<?= I18n::number((float)Graph::uncertainty('demand', count($state->predicted)), 1, $locale) ?></span>
<?php } ?>
<?= self::line(
      self::points($reported, fn ($datum) => self::demand($datum)),
      $estimate === null ? [] : self::points(
        [$state->time => $state->latest] + $state->predicted,
        fn ($datum) => self::demand($datum)
      ),
      $from,
      $now
    ) ?>
          </div>
          <div class="cell">
            <span class="label"><?= I18n::t('status.price', $locale) ?></span>
            <span class="value"><?= $price === null ? '—' : Value::formatPrice($price, $locale) . '<abbr>/MWh</abbr>' ?></span>
            <span class="note"><?= I18n::t('now.priceFixed', $locale) ?></span>
<?= self::line(
      self::points($reported + $state->upcomingPrices, fn ($datum) => $datum->price),
      [],
      $from,
      $now
    ) ?>
          </div>
<?php if ($frequency !== null) { Frequency::cell($frequency, $locale); } ?>
        </div>
<?php
  }

  /**
   * Returns the demand as the panel shows it, from the rounded parts, so the
   * band and the panel never differ in the last digit.
   *
   * @param Datum $datum The datum
   */
  private static function demand(Datum $datum): float {
    return round(Kind::Generation->get($datum->sources), 1)
      + round(Kind::Transfers->get($datum->sources), 1);
  }

  /**
   * Maps a series of data onto values, keyed by time.
   *
   * @param array<int,Datum> $series The series
   * @param callable         $value  Returns the value for a datum
   *
   * @return array<int,float>
   */
  private static function points(array $series, callable $value): array {
    $points = [];

    foreach ($series as $time => $datum) {
      $points[$time] = (float)$value($datum);
    }

    return $points;
  }

  /**
   * Returns a small line as SVG: the solid points, then the dashed ones,
   * which begin where the solid ones end, and a dot at the present. Where the
   * reports stop is left to the change from solid to dashed: the estimate
   * rarely reaches more than an hour, so a second dot there would sit almost
   * on top of the first.
   *
   * @param array<int,float> $solid  The solid points, keyed by time
   * @param array<int,float> $dashed The dashed points, keyed by time
   * @param int              $from   The start of the window
   * @param int              $to     The end of the window
   */
  private static function line(array $solid, array $dashed, int $from, int $to): string {
    ksort($solid);
    ksort($dashed);

    $all = array_merge(array_values($solid), array_values($dashed));

    if (count($all) < 2) {
      return '';
    }

    $minimum = min($all);
    $maximum = max($all);
    $padding = max(($maximum - $minimum) * 0.12, 0.01);
    $minimum -= $padding;
    $maximum += $padding;

    $x = fn ($time) => round(self::WIDTH * ($time - $from) / max(1, $to - $from), 2);
    $y = fn ($value) => round(self::HEIGHT * (1 - ($value - $minimum) / ($maximum - $minimum)), 2);

    $polyline = function (array $points, string $class) use ($x, $y): string {
      $coordinates = [];

      foreach ($points as $time => $value) {
        $coordinates[] = $x($time) . ',' . $y($value);
      }

      return count($coordinates) < 2
        ? ''
        : '<polyline class="' . $class . '" points="' . implode(' ', $coordinates) . '"/>';
    };

    $last = count($dashed) !== 0 ? $dashed : $solid;

    return self::frame(
      self::WIDTH,
      self::HEIGHT,
      $polyline($solid, 'solid') . $polyline($dashed, 'dashed'),
      [[$x(array_key_last($last)), $y(end($last)), '']]
    );
  }

  /**
   * Returns a small line as SVG, with the present marked on it.
   *
   * The line is drawn in its own units and stretched to fill its cell, which
   * would stretch a dot drawn with it into an oval, and the present sits on
   * the right-hand edge, where the drawing would cut a dot in half. So the
   * marks are drawn in a frame around the drawing instead, measured in pixels
   * and placed by percentage: they stay round, and may reach past the edge.
   *
   * Each mark is a dot and, under it, a ring that spreads from it and fades,
   * the way a ticker marks its newest point.
   *
   * @param int                              $width  The width of the line's units
   * @param int                              $height The height of the line's units
   * @param string                           $line   The line, as SVG in its own units
   * @param array<array{float,float,string}> $dots   The marks, each a position in
   *                                                 the line's units and a class
   *                                                 giving its colour, or none
   */
  public static function frame(int $width, int $height, string $line, array $dots): string {
    $svg = '<svg aria-hidden="true"><svg viewBox="0 0 ' . $width . ' ' . $height
      . '" preserveAspectRatio="none" width="100%" height="100%">' . $line . '</svg>';

    foreach ($dots as [$x, $y, $class]) {
      $at = rtrim(' ' . $class) . '" cx="' . round(100 * $x / $width, 2)
        . '%" cy="' . round(100 * $y / $height, 2) . '%" r="3.5"/>';

      $svg .= '<circle class="ping' . $at . '<circle class="now' . $at;
    }

    return $svg . "</svg>\n";
  }
}
