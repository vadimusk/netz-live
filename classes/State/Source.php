<?php

namespace KateMorley\Grid\State;

/** A power source. */
enum Source: string {
  case Coal        = 'coal';
  case Gas         = 'gas';
  case Solar       = 'solar';
  case Wind        = 'wind';
  case Hydro       = 'hydro';
  case Nuclear     = 'nuclear';
  case Biomass     = 'biomass';
  case Belgium     = 'belgium';
  case Denmark     = 'denmark';
  case France      = 'france';
  case Ireland     = 'ireland';
  case Netherlands = 'netherlands';
  case Norway      = 'norway';
  case Pumped      = 'pumped';
  case Battery     = 'battery';

  /** Returns a description of the source. */
  public function describe(): string {
    return match ($this) {
      self::Coal        => 'Coal',
      self::Gas         => 'Gas',
      self::Solar       => 'Solar',
      self::Wind        => 'Wind',
      self::Hydro       => 'Hydro',
      self::Nuclear     => 'Nuclear',
      self::Biomass     => 'Biomass',
      self::Belgium     => 'Belgium',
      self::Denmark     => 'Denmark',
      self::France      => 'France',
      self::Ireland     => 'Ireland',
      self::Netherlands => 'Netherlands',
      self::Norway      => 'Norway',
      self::Pumped      => 'Pumped',
      self::Battery     => 'Battery'
    };
  }

  /**
   * Returns the value of the source.
   *
   * @param Sources $sources The sources
   */
  public function get(Sources $sources): float {
    return $sources->get($this);
  }
}
