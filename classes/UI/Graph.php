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
    'lignite'    => [0.094, 0.173, 0.244, 0.311, 0.375],
    'hardCoal'   => [0.078, 0.148, 0.215, 0.286, 0.354],
    'gas'        => [0.152, 0.295, 0.435, 0.571, 0.705],
    'solar'      => [0.097, 0.159, 0.212, 0.242, 0.270],
    'wind'       => [0.249, 0.405, 0.481, 0.520, 0.563],
    'hydro'      => [0.045, 0.061, 0.072, 0.082, 0.093],
    'nuclear'    => [0.000, 0.000, 0.000, 0.000, 0.000],
    'biomass'    => [0.024, 0.045, 0.066, 0.086, 0.106],
    'other'      => [0.023, 0.042, 0.055, 0.063, 0.070],
    'fossils'    => [0.273, 0.528, 0.775, 1.017, 1.256],
    'renewables' => [0.289, 0.463, 0.577, 0.637, 0.705],
    'others'     => [0.040, 0.073, 0.100, 0.125, 0.149],
    'transfers'  => [0.477, 0.795, 1.074, 1.349, 1.627],
    'demand'     => [0.500, 0.854, 1.151, 1.437, 1.726],
    'emissions'  => [3.311, 5.908, 7.940, 9.513, 11.003]
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
