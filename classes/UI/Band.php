<?php

namespace KateMorley\Grid\UI;

/**
 * A band of uncertainty drawn around the predicted tail of a line.
 *
 * The predicted quarter hours are an estimate, and an estimate drawn as a
 * crisp line invites the reader to compare it with what later arrives and find
 * it wrong. A band that widens as it reaches further says the same thing more
 * honestly: somewhere in here, less and less precisely.
 *
 * Unlike Line, which emits a compact relative path because it can run to
 * hundreds of points, a band covers a handful of quarter hours and is written
 * out in absolute coordinates, tracing the upper edge forwards and the lower
 * edge back.
 */
class Band {
  /** @var array<int> */
  private array $upper = [];

  /** @var array<int> */
  private array $lower = [];

  /**
   * Constructs a new instance.
   *
   * @param int   $height  The height of the graph
   * @param float $minimum The minimum value
   * @param float $range   The range of values
   * @param int   $startX  The x co-ordinate the band starts at
   */
  public function __construct(
    private int   $height,
    private float $minimum,
    private float $range,
    private int   $startX
  ) {
  }

  /**
   * Adds a point.
   *
   * @param float $value     The value
   * @param float $halfWidth How far the band reaches either side of it
   */
  public function add(float $value, float $halfWidth): void {
    $this->upper[] = $this->y($value + $halfWidth);
    $this->lower[] = $this->y($value - $halfWidth);
  }

  /**
   * Returns the y co-ordinate for a value.
   *
   * @param float $value The value
   */
  private function y(float $value): int {
    return (int)round(
      $this->height * (1 - ($value - $this->minimum) / $this->range)
    );
  }

  /**
   * Outputs the band as an SVG path element.
   *
   * @param string $class The class for the path
   */
  public function output(string $class): void {
    // two points make a sliver worth drawing; one makes nothing
    if (count($this->upper) < 2) {
      return;
    }

    $points = [];

    foreach ($this->upper as $index => $y) {
      $points[] = ($this->startX + $index) . ' ' . $y;
    }

    foreach (array_reverse($this->lower, true) as $index => $y) {
      $points[] = ($this->startX + $index) . ' ' . $y;
    }

    echo '<path class="';
    echo $class;
    echo ' band" d="M';
    echo implode('L', $points);
    echo 'Z"/>';
  }
}
