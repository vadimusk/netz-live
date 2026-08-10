<?php

namespace KateMorley\Grid\UI;

/** A line on a graph. */
class Line {
  /**
   * The y offsets of successive points on the line.
   *
   * @var array<int>
   */
  private array $offsets = [];

  /** The last y co-ordinate. */
  private int $lastY = 0;

  /**
   * Constructs a new instance.
   *
   * @param int   $height  The height of the graph
   * @param float $minimum The minimum value
   * @param float $range   The range of values
   */
  public function __construct(
    private int   $height,
    private float $minimum,
    private float $range
  ) {
  }

  /**
   * Adds a point.
   *
   * @param int $value The value
   */
  public function add(float $value): void {
    $y = round($this->height * (1 - ($value - $this->minimum) / $this->range));
    $this->offsets[] = $y - $this->lastY;
    $this->lastY = $y;
  }

  /**
   * Outputs the line as an SVG path element.
   *
   * @param string $class An optional class for the path
   */
  public function output(?string $class = null): void {
    echo '<path';

    if ($class !== null) {
      echo ' class="';
      echo $class;
      echo '"';
    }

    // a series can cover no data at all — until a new database has collected
    // a couple of days, the past week and past year hold no rows — in which
    // case the path is left without a definition rather than given an empty
    // one, which browsers reject. The element itself stays, because the key
    // shown on hovering a graph pairs paths with values by position.
    if (count($this->offsets) === 0) {
      echo '/>';
      return;
    }

    echo ' d="m0 ';
    echo array_shift($this->offsets);

    while (count($this->offsets) > 0) {
      $dy = array_shift($this->offsets);

      $steps = 1;
      while (count($this->offsets) > 0 && $this->offsets[0] === $dy) {
        array_shift($this->offsets);
        $steps ++;
      }

      echo ' ';
      echo $steps;
      echo ' ';
      echo $steps * $dy;
    }

    echo '"/>';
  }
}
