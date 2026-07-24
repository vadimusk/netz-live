<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Transfers;

/** Functions for outputting sparklines. */
class Sparklines {
  /** The height of sparklines. */
  const HEIGHT = 28;

  /** The padding above sparklines. */
  const PADDING = 2;

  /** The smoothing factor. */
  const SMOOTHING = 3;

  /**
   * The series.
   *
   * @var array<Datum>
   */
  private array $series;

  /** The range covered by the sparklines. */
  private float $range;

  /**
   * Constructs a new instance.
   *
   * @param array<Datum> $series The series to show in sparklines
   */
  public function __construct(array $series) {
    $this->series = $series;

    // we want all sparklines to have the same scale, with transfers shown
    // symmetrically about the axis
    $this->range = max(array_merge(
      array_map(fn ($datum) => $datum->generation->getMaximum(),     $series),
      array_map(fn ($datum) => $datum->transfers->getMinimum() * -2, $series),
      array_map(fn ($datum) => $datum->transfers->getMaximum() *  2, $series),
    ));
  }

  /**
   * Outputs a sparkline.
   *
   * @param string $key The source key
   */
  public function output(string $key): void {
    $isTransfers = isset(Transfers::KEYS[$key]);

    $line = new Line(
      self::HEIGHT,
      $isTransfers ? -$this->range / 2 : 0,
      $this->range
    );

    $points = array_chunk(array_map(
      fn ($datum) => $isTransfers ? $datum->transfers : $datum->generation,
      $this->series,
    ), self::SMOOTHING);

    foreach ($points as $maps) {
      $line->add(
        array_sum(array_map(fn ($map) => $map->get($key), $maps))
        / self::SMOOTHING
      );
    }

    echo '<svg viewBox="0 -';
    echo self::PADDING;
    echo ' ';
    echo count($points) - 1;
    echo ' ';
    echo self::HEIGHT + self::PADDING;
    echo '" width="';
    echo count($points) - 1;
    echo '" height="';
    echo self::HEIGHT + self::PADDING;
    echo '" preserveAspectRatio="none">';
    $line->output();
    echo '</svg>';
  }
}
