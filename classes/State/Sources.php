<?php

namespace KateMorley\Grid\State;

/** Details of power sources. */
class Sources {
  private float $coal;
  private float $gas;
  private float $solar;
  private float $wind;
  private float $hydro;
  private float $nuclear;
  private float $biomass;
  private float $belgium;
  private float $denmark;
  private float $france;
  private float $ireland;
  private float $netherlands;
  private float $norway;
  private float $pumped;
  private float $battery;

  /**
   * Constructs a new instance.
   *
   * @param array<string,float> $map An array mapping keys to values
   */
  public function __construct(array $map) {
    $this->coal        = $map['coal'];
    $this->gas         = $map['ocgt'] + $map['ccgt'];
    $this->solar       = $map['embedded_solar'];
    $this->wind        = $map['embedded_wind'] + $map['wind'];
    $this->hydro       = $map['hydro'];
    $this->nuclear     = $map['nuclear'];
    $this->biomass     = $map['biomass'];
    $this->belgium     = $map['nemo'];
    $this->denmark     = $map['viking'];
    $this->france      = $map['ifa'] + $map['ifa2'] + $map['eleclink'];
    $this->ireland     = $map['moyle'] + $map['ewic'] + $map['greenlink'];
    $this->netherlands = $map['britned'];
    $this->norway      = $map['nsl'];
    $this->pumped      = $map['pumped'];
    $this->battery     = 0;
  }

  /**
   * Returns the value for a source.
   *
   * @param Source $source The source
   */
  public function get(Source $source): float {
    $property = $source->value;
    return $this->$property;
  }

  /**
   * Returns the sum of the values of a set of sources.
   *
   * @param ?array<Source> $sources The sources; defaults to all sources
   */
  public function sum(?array $sources = null): float {
    return array_sum(array_map(
      fn ($source) => $this->get($source),
      $sources ?? Source::cases()
    ));
  }

  /**
   * Returns the minimum of the values of a set of sources.
   *
   * @param array<Source> $sources The sources
   */
  public function minimum(array $sources): float {
    return min(array_map(fn ($source) => $this->get($source), $sources));
  }

  /**
   * Returns the maximum of the values of a set of sources.
   *
   * @param array<Source> $sources The sources
   */
  public function maximum(array $sources): float {
    return max(array_map(fn ($source) => $this->get($source), $sources));
  }
}
