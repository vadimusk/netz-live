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
   * Outputs the band.
   *
   * @param Reading $reading The reading
   * @param string  $locale  The locale ('de' or 'en')
   * @param bool    $help    Whether to show the help
   */
  public static function output(
    Reading $reading,
    string  $locale,
    bool    $help = false
  ): void {
    $deviation = $reading->deviation();
    $time      = (new \DateTime('@' . $reading->time))->setTimezone(
      new \DateTimeZone('Europe/Berlin')
    );
?>
      <div id="frequency">
        <dl>
          <dt><?= I18n::t('frequency.heading', $locale) ?><?php if ($help) { ?> <span data-help="frequency"></span><?php } ?></dt>
          <dd class="<?= self::class_($deviation) ?>"><?= I18n::number($reading->hertz, 3, $locale) ?><abbr>Hz</abbr></dd>
        </dl>
<?= self::sparkline($reading->series) ?>
        <p>
          <span class="<?= self::class_($deviation) ?>"><?= ($deviation > 0 ? '+' : ($deviation < 0 ? '−' : '±')) . abs((int)$deviation) ?><abbr>mHz</abbr></span>
          <span><?= I18n::t('frequency.area', $locale) ?></span>
          <time datetime="<?= gmdate('c', $reading->time) ?>"><?= $time->format('H:i') ?></time>
        </p>
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
   * Returns the sparkline as SVG.
   *
   * @param array<float> $series The series
   */
  private static function sparkline(array $series): string {
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

    return '        <svg viewBox="0 0 ' . self::WIDTH . ' ' . self::HEIGHT
      . '" preserveAspectRatio="none" aria-hidden="true">'
      . '<line x1="0" y1="' . $y(Reading::NOMINAL) . '" x2="' . self::WIDTH
      . '" y2="' . $y(Reading::NOMINAL) . '"/>'
      . self::bands($y)
      . '<mask id="frequency-line" maskUnits="userSpaceOnUse" mask-type="alpha">'
      . '<polyline points="' . implode(' ', $points) . '"/>'
      . '</mask>'
      . "</svg>\n";
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
