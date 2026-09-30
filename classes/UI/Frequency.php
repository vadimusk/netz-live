<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\Data\Frequency as Reading;

/** Outputs the grid frequency band. */
class Frequency {
  /** The width of the sparkline's coordinate space. */
  private const WIDTH = 480;

  /** The height of the sparkline's coordinate space. */
  private const HEIGHT = 40;

  /**
   * The smallest deviation the sparkline's scale will show, in hertz.
   *
   * Frequency spends most of the day within a few tens of millihertz of
   * nominal, so scaling to the hour's own range alone would magnify ordinary
   * noise into a dramatic line. Holding the scale open to at least this much
   * either side keeps a calm hour looking calm.
   */
  private const MINIMUM_RANGE = 0.05;

  /** Deviations within this many millihertz read as normal. */
  private const NORMAL = 20;

  /** Deviations beyond this many millihertz read as strained. */
  private const STRAINED = 50;

  /**
   * Outputs the frequency as a cell of the band describing now: the only
   * figure there that is measured rather than estimated or fixed ahead.
   *
   * @param Reading $reading The reading
   * @param string  $locale  The locale ('de' or 'en')
   */
  public static function cell(Reading $reading, string $locale): void {
    $deviation = $reading->deviation();
    $class     = self::class_($deviation);
?>
          <div class="cell frequency">
            <span class="label"><?= I18n::t('frequency.heading', $locale) ?> <span data-help="frequency"></span></span>
            <span class="value <?= $class ?>"><?= I18n::number($reading->hertz, 3, $locale) ?><abbr>Hz</abbr></span>
            <span class="note"><span class="<?= $class ?>"><?= ($deviation > 0 ? '+' : ($deviation < 0 ? '−' : '±')) . abs((int)$deviation) ?>&#8201;mHz</span> · <?= I18n::t('frequency.area', $locale) ?> · <?= Status::time($reading->time, $locale) ?></span>
<?= self::sparkline($reading->series, $class) ?>
          </div>
<?php
  }

  /**
   * Returns the class describing how far a deviation is from nominal.
   *
   * @param float $deviation The deviation, in millihertz
   */
  private static function class_(float $deviation): string {
    $size = abs($deviation);

    if ($size <= self::NORMAL) {
      return 'low';
    }

    return $size <= self::STRAINED ? 'medium' : 'high';
  }

  /**
   * Returns the sparkline as SVG, ending in a dot coloured as the figure is.
   *
   * @param array<float> $series The series
   * @param string       $class  The class describing the latest deviation
   */
  private static function sparkline(array $series, string $class): string {
    if (count($series) < 2) {
      return '';
    }

    // the scale always contains nominal, so the reference line is always on
    // the chart and the shape is read against the thing it deviates from
    $minimum = min(min($series), Reading::NOMINAL - self::MINIMUM_RANGE);
    $maximum = max(max($series), Reading::NOMINAL + self::MINIMUM_RANGE);
    $range   = $maximum - $minimum;

    $y = fn ($value) => round(
      self::HEIGHT * (1 - ($value - $minimum) / $range),
      2
    );

    $points = [];

    foreach ($series as $index => $value) {
      $points[] = round(self::WIDTH * $index / (count($series) - 1), 2)
        . ',' . $y($value);
    }

    return '        ' . NowBand::frame(
      self::WIDTH,
      self::HEIGHT,
      '<line x1="0" y1="' . $y(Reading::NOMINAL) . '" x2="' . self::WIDTH
        . '" y2="' . $y(Reading::NOMINAL) . '"/>'
        . self::bands($y)
        . '<mask id="frequency-line" maskUnits="userSpaceOnUse" mask-type="alpha">'
        . '<polyline points="' . implode(' ', $points) . '"/>'
        . '</mask>',
      [[(float)self::WIDTH, $y(end($series)), 'now ' . $class]]
    );
  }

  /**
   * Returns the coloured level bands as SVG.
   *
   * The line takes its colour from the band it passes through, the same way
   * the emissions graph does: the bands are full-width rectangles and the
   * line is an alpha mask over them, so one line changes colour partway along
   * rather than being flat. Unlike emissions, whose scale runs one way, the
   * normal band sits in the middle here and the strained ones lie either side
   * of it, so there are five bands rather than three.
   *
   * @param callable $y Maps a frequency to its vertical position
   */
  private static function bands(callable $y): string {
    $normal   = self::NORMAL / 1000;
    $strained = self::STRAINED / 1000;

    $clamp = fn ($value) => max(0.0, min((float)self::HEIGHT, $value));

    // boundary positions, from the top of the chart downwards
    $edges = [
      0.0,
      $clamp($y(Reading::NOMINAL + $strained)),
      $clamp($y(Reading::NOMINAL + $normal)),
      $clamp($y(Reading::NOMINAL - $normal)),
      $clamp($y(Reading::NOMINAL - $strained)),
      (float)self::HEIGHT
    ];

    $classes = ['high', 'medium', 'low', 'medium', 'high'];

    $svg = '<g mask="url(#frequency-line)">';

    foreach ($classes as $index => $class) {
      $top    = $edges[$index];
      $height = $edges[$index + 1] - $top;

      // a band the scale has squeezed to nothing is left out rather than
      // drawn as a zero-height rectangle
      if ($height <= 0.01) {
        continue;
      }

      $svg .= '<rect class="' . $class . '" x="0" y="' . round($top, 2)
        . '" width="' . self::WIDTH . '" height="' . round($height, 2) . '"/>';
    }

    return $svg . '</g>';
  }
}
