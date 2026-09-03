<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Kind;

/** A graph. */
enum Graph: int {
  case Price      = 0;
  case Emissions  = 1;
  case Demand     = 2;
  case Generation = 3;
  case Transfers  = 4;
  case Visits     = 5;

  /**
   * Returns a description of the graph.
   *
   * @param string $locale The locale ('de' or 'en')
   */
  public function describe(string $locale): string {
    return I18n::t('graph.' . match ($this) {
      self::Price      => 'price',
      self::Emissions  => 'emissions',
      self::Demand     => 'demand',
      self::Generation => 'generation',
      self::Transfers  => 'transfers',
      self::Visits     => 'visits'
    }, $locale);
  }

  /** Returns the value prefix. */
  public function prefix(): string {
    return $this === self::Price ? '€' : '';
  }

  /** Returns the value suffix. */
  public function suffix(): string {
    return match ($this) {
      self::Emissions => 'g',
      self::Demand, self::Generation, self::Transfers  => 'GW',
      default => ''
    };
  }

  /** Returns the number of decimal places to show. */
  public function decimalPlaces(): int {
    return match ($this) {
      self::Price, self::Generation, self::Transfers => 2,
      self::Demand => 1,
      self::Emissions, self::Visits => 0
    };
  }

  /**
   * How far the prediction is typically wrong, per drawn line, one entry per
   * quarter hour it reaches ahead.
   *
   * Measured against what later arrived, over 168 to 176 predictions per
   * horizon, using forecasts captured as they were published rather than
   * refetched afterwards. Two shapes show up in it. The lines the forecast
   * informs — solar and wind — grow only two and a half times across the hour,
   * because the forecast keeps hold of them. The lines that are simply carried
   * forward grow four and a half times, which is what persistence does, and is
   * why gas ends up a wider band than solar despite being the calmer series.
   *
   * Beyond the fifth quarter hour the last step is repeated, which understates
   * the width; nothing reaches that far while the site is healthy.
   */
  private const UNCERTAINTY = [
    'lignite'    => [0.096, 0.178, 0.250, 0.320, 0.384, 0.445, 0.506, 0.568, 0.617, 0.661, 0.702, 0.743, 0.785, 0.834, 0.882, 0.927, 0.969, 1.006, 1.037, 1.061, 1.087, 1.108, 1.121, 1.131],
    'hardCoal'   => [0.079, 0.152, 0.221, 0.293, 0.364, 0.433, 0.503, 0.571, 0.637, 0.705, 0.771, 0.826, 0.877, 0.924, 0.967, 1.009, 1.049, 1.084, 1.115, 1.142, 1.166, 1.185, 1.209, 1.232],
    'gas'        => [0.151, 0.294, 0.433, 0.570, 0.705, 0.840, 0.976, 1.114, 1.249, 1.381, 1.513, 1.633, 1.744, 1.851, 1.956, 2.055, 2.148, 2.232, 2.313, 2.386, 2.453, 2.516, 2.577, 2.637],
    'solar'      => [0.101, 0.167, 0.223, 0.255, 0.255, 0.255, 0.255, 0.255, 0.255, 0.255, 0.256, 0.256, 0.256, 0.256, 0.256, 0.256, 0.260, 0.264, 0.264, 0.264, 0.264, 0.264, 0.264, 0.264],
    'wind'       => [0.253, 0.413, 0.491, 0.527, 0.556, 0.557, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.558, 0.560, 0.563, 0.566, 0.566, 0.566],
    'hydro'      => [0.046, 0.062, 0.073, 0.083, 0.094, 0.104, 0.113, 0.123, 0.129, 0.135, 0.141, 0.147, 0.147, 0.147, 0.147, 0.149, 0.149, 0.153, 0.156, 0.156, 0.160, 0.160, 0.165, 0.165],
    'nuclear'    => [0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000],
    'biomass'    => [0.024, 0.045, 0.065, 0.085, 0.105, 0.125, 0.144, 0.163, 0.181, 0.199, 0.217, 0.235, 0.253, 0.272, 0.290, 0.306, 0.319, 0.331, 0.342, 0.351, 0.359, 0.364, 0.368, 0.371],
    'other'      => [0.023, 0.041, 0.054, 0.063, 0.069, 0.073, 0.073, 0.073, 0.074, 0.076, 0.076, 0.077, 0.080, 0.082, 0.082, 0.082, 0.082, 0.082, 0.082, 0.082, 0.083, 0.084, 0.087, 0.091],
    'fossils'    => [0.276, 0.533, 0.783, 1.031, 1.275, 1.517, 1.766, 2.013, 2.261, 2.505, 2.750, 2.972, 3.193, 3.409, 3.612, 3.804, 3.982, 4.134, 4.272, 4.400, 4.511, 4.611, 4.711, 4.807],
    'renewables' => [0.293, 0.473, 0.587, 0.646, 0.672, 0.679, 0.690, 0.690, 0.690, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.697, 0.699, 0.713],
    'others'     => [0.039, 0.071, 0.099, 0.123, 0.146, 0.167, 0.187, 0.207, 0.227, 0.246, 0.263, 0.282, 0.302, 0.322, 0.342, 0.359, 0.373, 0.386, 0.400, 0.412, 0.420, 0.428, 0.433, 0.436],
    'transfers'  => [0.476, 0.789, 1.067, 1.345, 1.758, 2.025, 2.284, 2.567, 2.846, 3.117, 3.385, 3.672, 3.953, 4.221, 4.486, 4.759, 5.030, 5.320, 5.601, 5.880, 6.150, 6.405, 6.628, 6.845],
    'demand'     => [0.497, 0.845, 1.134, 1.417, 1.749, 2.044, 2.338, 2.633, 2.911, 3.180, 3.444, 3.725, 4.004, 4.271, 4.524, 4.780, 5.026, 5.262, 5.486, 5.692, 5.857, 5.975, 6.049, 6.104],
    'emissions'  => [3.304, 5.956, 8.028, 9.635, 9.658, 11.066, 12.465, 13.773, 15.206, 16.556, 17.777, 18.739, 19.603, 20.430, 21.303, 22.153, 22.865, 23.268, 23.600, 23.992, 24.276, 24.416, 24.416, 24.431]
  ];

  /**
   * Returns how wide the band around a predicted point should be, or null
   * where that line carries no measured uncertainty.
   *
   * @param string $class The line's class
   * @param int    $step  How many quarter hours ahead the point is, from one
   */
  public static function uncertainty(string $class, int $step): ?float {
    $widths = self::UNCERTAINTY[$class] ?? null;

    if ($widths === null) {
      return null;
    }

    return $widths[min($step, count($widths)) - 1];
  }

  /**
   * Returns the levels for colouring lines, as an array mapping classes to
   * minimum values.
   *
   * @return ?array<string,int>
   */
  public function levels(): ?array {
    if ($this !== self::Emissions) {
      return null;
    }

    $levels = [];

    foreach (Emissions::cases() as $level) {
      $levels[$level->class()] = $level->minimum();
    }

    return $levels;
  }

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
      self::Price => [
        $datum->price
      ],
      self::Emissions => [
        $datum->emissions
      ],
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
      self::Visits => [
        $datum->visits
      ]
    };
  }
}
