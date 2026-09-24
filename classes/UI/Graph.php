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
   * quarter hour it reaches ahead, from a quarter of an hour to six hours.
   *
   * Measured by building the estimate exactly as Prediction does from every
   * quarter hour of October 2025 to September 2026 as the anchor — some
   * 34,000 per reach, across every season — using the day-ahead forecast as it
   * was published, and comparing with what later arrived; the carbon intensity
   * against the official figure the solid line shows. Each entry is the mean
   * absolute error, never narrower than the one before it, so the real figure
   * lands inside the band six or seven times in ten.
   *
   * The lines built from a forecast — solar, wind and demand — widen slowly,
   * because the forecast keeps hold of their shape. The ones carried forward,
   * hydro and biomass, widen the way persistence does. Solar and wind are
   * wider at long reach than they were in the previous table, which was
   * measured on a forecast refetched after the fact and so flattered it.
   */
  private const UNCERTAINTY = [
    'lignite'    => [0.110, 0.197, 0.274, 0.344, 0.408, 0.465, 0.518, 0.566, 0.610, 0.651, 0.689, 0.724, 0.755, 0.784, 0.811, 0.835, 0.857, 0.878, 0.897, 0.913, 0.928, 0.942, 0.954, 0.964],
    'hardCoal'   => [0.062, 0.112, 0.157, 0.199, 0.237, 0.273, 0.306, 0.338, 0.367, 0.395, 0.421, 0.445, 0.468, 0.489, 0.509, 0.528, 0.547, 0.564, 0.581, 0.597, 0.612, 0.626, 0.639, 0.652],
    'gas'        => [0.105, 0.183, 0.249, 0.310, 0.368, 0.422, 0.472, 0.520, 0.565, 0.609, 0.650, 0.689, 0.726, 0.761, 0.795, 0.827, 0.857, 0.885, 0.913, 0.938, 0.962, 0.985, 1.007, 1.027],
    'solar'      => [0.103, 0.183, 0.256, 0.323, 0.386, 0.443, 0.496, 0.545, 0.591, 0.632, 0.669, 0.703, 0.734, 0.762, 0.787, 0.809, 0.828, 0.846, 0.862, 0.876, 0.888, 0.899, 0.909, 0.918],
    'wind'       => [0.209, 0.362, 0.488, 0.601, 0.703, 0.797, 0.883, 0.962, 1.034, 1.099, 1.158, 1.212, 1.260, 1.305, 1.346, 1.385, 1.422, 1.456, 1.487, 1.515, 1.543, 1.568, 1.591, 1.614],
    'hydro'      => [0.038, 0.054, 0.067, 0.078, 0.090, 0.100, 0.109, 0.117, 0.125, 0.132, 0.139, 0.146, 0.151, 0.156, 0.161, 0.165, 0.169, 0.172, 0.175, 0.178, 0.180, 0.183, 0.185, 0.186],
    'nuclear'    => [0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000, 0.000],
    'biomass'    => [0.034, 0.066, 0.097, 0.129, 0.159, 0.189, 0.218, 0.247, 0.274, 0.300, 0.324, 0.348, 0.369, 0.389, 0.407, 0.424, 0.438, 0.451, 0.462, 0.471, 0.479, 0.484, 0.488, 0.491],
    'other'      => [0.017, 0.026, 0.033, 0.039, 0.044, 0.049, 0.052, 0.056, 0.059, 0.062, 0.065, 0.067, 0.069, 0.072, 0.074, 0.077, 0.079, 0.081, 0.082, 0.084, 0.085, 0.087, 0.088, 0.090],
    'fossils'    => [0.200, 0.353, 0.487, 0.610, 0.725, 0.830, 0.926, 1.014, 1.096, 1.172, 1.242, 1.307, 1.367, 1.423, 1.477, 1.527, 1.575, 1.620, 1.663, 1.703, 1.741, 1.777, 1.812, 1.844],
    'renewables' => [0.258, 0.447, 0.607, 0.750, 0.884, 1.006, 1.116, 1.218, 1.310, 1.393, 1.468, 1.536, 1.597, 1.652, 1.701, 1.747, 1.788, 1.826, 1.861, 1.892, 1.920, 1.945, 1.966, 1.987],
    'others'     => [0.041, 0.076, 0.111, 0.144, 0.177, 0.208, 0.239, 0.269, 0.297, 0.324, 0.350, 0.374, 0.397, 0.418, 0.437, 0.455, 0.470, 0.484, 0.495, 0.505, 0.513, 0.520, 0.524, 0.528],
    'transfers'  => [0.398, 0.600, 0.772, 0.927, 1.077, 1.216, 1.346, 1.467, 1.577, 1.682, 1.779, 1.869, 1.950, 2.026, 2.096, 2.163, 2.221, 2.274, 2.326, 2.374, 2.417, 2.455, 2.493, 2.529],
    'demand'     => [0.339, 0.453, 0.544, 0.619, 0.698, 0.768, 0.830, 0.886, 0.940, 0.991, 1.038, 1.082, 1.124, 1.163, 1.201, 1.237, 1.271, 1.301, 1.329, 1.355, 1.379, 1.403, 1.425, 1.447],
    'emissions'  => [2.9, 5.1, 7.1, 8.8, 10.5, 12.0, 13.3, 14.6, 15.7, 16.8, 17.8, 18.7, 19.6, 20.4, 21.1, 21.8, 22.5, 23.1, 23.6, 24.1, 24.6, 25.1, 25.5, 25.9]
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
