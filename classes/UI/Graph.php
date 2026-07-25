<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Kind;

/** Outputs a graph. */
enum Graph: int {
  case Price      = 0;
  case Emissions  = 1;
  case Demand     = 2;
  case Generation = 3;
  case Transfers  = 4;
  case Visits     = 5;

  /**
   * The graph height.
   *
   * Chosen to be large enough to allow pixel-perfect placement, but not so
   * large as to increase the output size due to excessive precision.
   */
  private const HEIGHT = 500;

  /**
   * Returns the classes for the lines.
   *
   * @return array<string>
   */
  public function classes(): array {
    return match ($this) {
      self::Price => [
        'price'
      ],
      self::Emissions => [
        'emissions'
      ],
      self::Demand => [
        'demand',
        Kind::Fossils->value,
        Kind::Renewables->value,
        Kind::Others->value,
        Kind::Transfers->value
      ],
      self::Generation => array_map(
        fn ($source) => $source->value,
        Kind::Generation->sources()
      ),
      self::Transfers => array_map(
        fn ($source) => $source->value,
        Kind::Transfers->sources()
      ),
      self::Visits => [
        'visits'
      ]
    };
  }

  /**
   * Returns the values to show.
   *
   * @param Datum $datum The datum
   *
   * @return array<float>
   */
  public function get(Datum $datum): array {
    return match ($this) {
      self::Price => [$datum->price],
      self::Emissions => [$datum->emissions],
      self::Demand => [
        round(Kind::Fossils->get($datum->sources), 1)
        + round(Kind::Renewables->get($datum->sources), 1)
        + round(Kind::Others->get($datum->sources), 1)
        + round(Kind::Transfers->get($datum->sources), 1),
        round(Kind::Fossils->get($datum->sources), 1),
        round(Kind::Renewables->get($datum->sources), 1),
        round(Kind::Others->get($datum->sources), 1),
        round(Kind::Transfers->get($datum->sources), 1)
      ],
      self::Generation => array_map(
        fn ($source) => $source->get($datum->sources),
        Kind::Generation->sources()
      ),
      self::Transfers => array_map(
        fn ($source) => $source->get($datum->sources),
        Kind::Transfers->sources()
      ),
      self::Visits => [$datum->visits]
    };
  }

  /**
   * Outputs a graph.
   *
   * @param array<Datum> $series        The series
   * @param Axes         $axes          The axes information
   * @param string       $prefix        The value prefix
   * @param string       $suffix        The value suffix
   * @param int          $timeStep      The time step
   * @param string       $timeFormat    The time format
   * @param int          $decimalPlaces The number of decimal places for values
   */
  public function output(
    array  $series,
    Axes   $axes,
    string $prefix,
    string $suffix,
    int    $timeStep,
    string $timeFormat,
    int    $decimalPlaces
  ): void {
    $minimum = $axes->getMinimum($this);
    $maximum = $axes->getMaximum($this);
    $step    = $axes->getStep($this);

    echo '<div class="graph" data-prefix="';
    echo $prefix;
    echo '" data-suffix="';
    echo $suffix;
    echo '"';
    if ($this === self::Transfers) {
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
    $this->outputLines($series, $minimum, $maximum - $minimum);
    $this->outputOverlay($series, $timeFormat, $decimalPlaces);
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
   * @param int          $minimum The minimum value
   * @param int          $range   The value range
   */
  private function outputLines(
    array $series,
    int   $minimum,
    int   $range
  ): void {
    // avoid division by zero for new instances with only a single point
    if ($range === 0) {
      $range = 1;
    }

    $lines = array_map(
      fn ($_) => new Line(self::HEIGHT, $minimum, $range),
      $this->classes()
    );

    foreach ($series as $datum) {
      foreach ($this->get($datum) as $key => $value) {
        $lines[$key]->add($value);
      }
    }

    foreach ($this->classes() as $index => $class) {
      $lines[$index]->output($class);
    }
  }

  /**
   * Outputs the overlay.
   *
   * @param array<Datum> $series        The series
   * @param string       $timeFormat    The time format
   * @param int          $decimalPlaces The number of decimal places for values
   */
  private function outputOverlay(
    array  $series,
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
        $this->get($datum)
      ));
      echo '"/>';

      $index ++;
    }

    echo '</g>';
  }
}
