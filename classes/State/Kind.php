<?php

namespace KateMorley\Grid\State;

/** A kind of power source. */
enum Kind: string {
  case Generation      = 'generation';
  case Fossils         = 'fossils';
  case Renewables      = 'renewables';
  case Others          = 'others';
  case Transfers       = 'transfers';
  case Interconnectors = 'interconnectors transfers';
  case Storage         = 'storage transfers';

  /** Returns a description of the kind of power source. */
  public function describe(): string {
    return match ($this) {
      self::Generation      => 'Generation',
      self::Fossils         => 'Fossil fuels',
      self::Renewables      => 'Renewables',
      self::Others          => 'Other sources',
      self::Transfers       => 'Transfers',
      self::Interconnectors => 'Interconnectors',
      self::Storage         => 'Storage'
    };
  }

  /**
   * Returns the value of the kind.
   *
   * @param Sources $sources The sources
   */
  public function get(Sources $sources): float {
    return $sources->sum($this->sources());
  }

  /**
   * Returns the list of power sources of this kind.
   *
   * @return array<Source>
   */
  public function sources(): array {
    return match ($this) {
      self::Generation => [
        Source::Coal,
        Source::Gas,
        Source::Solar,
        Source::Wind,
        Source::Hydro,
        Source::Nuclear,
        Source::Biomass
      ],
      self::Fossils => [
        Source::Coal,
        Source::Gas
      ],
      self::Renewables => [
        Source::Solar,
        Source::Wind,
        Source::Hydro
      ],
      self::Others => [
        Source::Nuclear,
        Source::Biomass
      ],
      self::Transfers => [
        Source::Belgium,
        Source::Denmark,
        Source::France,
        Source::Ireland,
        Source::Netherlands,
        Source::Norway,
        Source::Pumped
      ],
      self::Interconnectors => [
        Source::Belgium,
        Source::Denmark,
        Source::France,
        Source::Ireland,
        Source::Netherlands,
        Source::Norway
      ],
      self::Storage => [
        Source::Pumped,
        Source::Battery
      ]
    };
  }
}
