<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Source;

/** Functions for outputting sparklines. */
class Sparklines {
  /** The height of sparklines. */
  const HEIGHT = 26;

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
    $this->range = max(array_map(
      fn ($datum) => max(
        $datum->sources->maximum(Kind::Generation->sources()),
        2 * $datum->sources->maximum(Kind::Transfers->sources()),
        -2 * $datum->sources->minimum(Kind::Transfers->sources()),
      ),
      $series
    ));
  }

  /**
   * Outputs a sparkline.
   *
   * @param Source $source The source
   */
  public function output(Source $source): void {
    $isTransfers = in_array($source, Kind::Transfers->sources());

    $line = new Line(
      self::HEIGHT,
      $isTransfers ? -$this->range / 2 : 0,
      $this->range
    );

    $points = array_chunk(
      array_map(fn ($datum) => $datum->sources, $this->series),
      self::SMOOTHING
    );

    foreach ($points as $maps) {
      $line->add(
        array_sum(array_map(fn ($map) => $map->get($source), $maps))
        / self::SMOOTHING
      );
    }

    echo '<svg viewBox="0 -1 ';
    echo count($points) - 1;
    echo ' ';
    echo self::HEIGHT + 2;
    echo '" width="';
    echo count($points) - 1;
    echo '" height="';
    echo self::HEIGHT + 2;
    echo '" preserveAspectRatio="none">';

    $line->output();

    echo '</svg>';
  }
}
