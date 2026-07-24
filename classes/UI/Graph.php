<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;

/** Outputs a graph. */
class Graph {
  /**
   * The graph height.
   *
   * Chosen to be large enough to allow pixel-perfect placement, but not so
   * large as to increase the output size due to excessive precision.
   */
  private const HEIGHT = 500;

  /**
   * Outputs a graph.
   *
   * @param array<Datum> $series        The series
   * @param Axes         $axes          The axes information
   * @param int          $map           The map key
   * @param string       $prefix        The value prefix
   * @param string       $suffix        The value suffix
   * @param int          $timeStep      The time step
   * @param string       $timeFormat    The time format
   * @param int          $decimalPlaces The number of decimal places for values
   */
  public static function output(
    array  $series,
    Axes   $axes,
    int    $map,
    string $prefix,
    string $suffix,
    int    $timeStep,
    string $timeFormat,
    int    $decimalPlaces
  ): void {
    $minimum = $axes->getMinimum($map);
    $maximum = $axes->getMaximum($map);
    $step    = $axes->getStep($map);

    echo '<div class="graph" data-prefix="';
    echo $prefix;
    echo '" data-suffix="';
    echo $suffix;
    echo '"';
    if ($map === Datum::TRANSFERS) {
      echo ' data-transfers="true"';
    }
    echo '>';

    self::outputValueAxis($minimum, $maximum, $step, $prefix, $suffix);
    self::outputTimeAxis($series, $timeStep, $timeFormat);

    echo '<svg viewBox="-0.5 0 ';
    echo count($series);
    echo ' ';
    echo self::HEIGHT;
    echo '" width="';
    echo count($series);
    echo '" height="';
    echo self::HEIGHT;
    echo '" preserveAspectRatio="none">';
    self::outputLines($series, $map, $minimum, $maximum - $minimum);
    self::outputOverlay($series, $map, $timeFormat, $decimalPlaces);
    echo "</svg></div>\n";
  }

  /**
   * Outputs the value axis.
   *
   * @param int    $minimum The minimum value
   * @param int    $maximum The maximum value
   * @param int    $step    The value step
   * @param string $prefix  The value prefix
   * @param string $suffix  The value suffix
   */
  private static function outputValueAxis(
    int    $minimum,
    int    $maximum,
    int    $step,
    string $prefix,
    string $suffix
  ): void {
    echo '<div>';

    for ($label = $maximum; $label >= $minimum; $label -= $step) {
      echo '<div>';

      if ($label < 0) {
        echo '−';
      }

      echo $prefix;
      echo number_format(abs($label));
      echo $suffix;
      echo '</div><div';

      if ($label === 0) {
        echo ' class="axis"';
      }

      echo '></div>';
    }

    echo '</div>';
  }

  /**
   * Outputs the time axis.
   *
   * @param array<Datum> $series The series
   * @param string       $step   The time step
   * @param string       $format The time format
   */
  private static function outputTimeAxis(
    array  $series,
    string $step,
    string $format
  ): void {
    echo '<div>';

    $index = ceil($step / 2);

    foreach ($series as $time => $_) {
      if ($index % $step === 0) {
        echo '<div>';
        echo date($format, $time);
        echo '</div>';
      }

      $index ++;
    }

    echo '</div>';
  }

  /**
   * Outputs the lines.
   *
   * @param array<Datum> $series  The series
   * @param int          $map     The map key
   * @param int          $minimum The minimum value
   * @param int          $range   The value range
   */
  private static function outputLines(
    array $series,
    int   $map,
    int   $minimum,
    int   $range
  ): void {
    // avoid division by zero for new instances with only a single point
    if ($range === 0) {
      $range = 1;
    }

    $lines = array_map(
      fn ($_) => new Line(self::HEIGHT, $minimum, $range),
      iterator_to_array($series[array_key_first($series)]->get($map))
    );

    foreach ($series as $datum) {
      foreach ($datum->get($map) as $key => $value) {
        $lines[$key]->add($value);
      }
    }

    foreach ($lines as $key => $line) {
      $line->output($key);
    }
  }

  /**
   * Outputs the overlay.
   *
   * @param array<Datum> $series        The series
   * @param int          $map           The map key
   * @param string       $timeFormat    The time format
   * @param int          $decimalPlaces The number of decimal places for values
   */
  private static function outputOverlay(
    array  $series,
    int    $map,
    string $timeFormat,
    int    $decimalPlaces
  ): void {
    echo '<g transform="translate(-0.5 0)">';

    $index = 0;

    foreach ($series as $time => $datum) {
      echo '<rect x="';
      echo $index;
      echo '" y="0" width="1" height="';
      echo self::HEIGHT;
      echo '" data-time="';
      echo date($timeFormat, $time);
      echo '" data-values="';
      echo implode(' ', array_map(
        fn ($value) => number_format($value, $decimalPlaces),
        iterator_to_array($datum->get($map))
      ));
      echo '"/>';

      $index ++;
    }

    echo '</g>';
  }
}
