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

  /** Returns a description of the graph. */
  public function describe(): string {
    return match ($this) {
      self::Price      => 'Price per MWh',
      self::Emissions  => 'Emissions per kWh',
      self::Demand     => 'Demand',
      self::Generation => 'Generation',
      self::Transfers  => 'Transfers',
      self::Visits     => 'Weekly visits'
    };
  }

  /** Returns the value prefix. */
  public function prefix(): string {
    return $this === self::Price ? '£' : '';
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
   * Returns the values to show for a datum.
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
