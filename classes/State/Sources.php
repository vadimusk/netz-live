<?php

namespace KateMorley\Grid\State;

/** Details of power sources. */
class Sources {
  private float $lignite;
  private float $hardCoal;
  private float $gas;
  private float $solar;
  private float $wind;
  private float $hydro;
  private float $biomass;
  private float $other;
  private float $austria;
  private float $belgium;
  private float $czechRepublic;
  private float $denmark;
  private float $france;
  private float $luxembourg;
  private float $netherlands;
  private float $norway;
  private float $poland;
  private float $sweden;
  private float $switzerland;
  private float $pumped;

  /**
   * Constructs a new instance.
   *
   * @param array<string,float> $map An array mapping keys to values
   */
  public function __construct(array $map) {
    $this->lignite      = $map['lignite'];
    $this->hardCoal     = $map['hard_coal'];
    $this->gas          = $map['gas'];
    $this->solar        = $map['solar'];
    $this->wind         = $map['wind_onshore'] + $map['wind_offshore'];
    $this->hydro        = $map['hydro'];
    $this->biomass      = $map['biomass'];
    $this->other        = $map['other_renewable'] + $map['other_conventional'];
    $this->austria       = $map['austria'];
    $this->belgium       = $map['belgium'];
    $this->czechRepublic = $map['czech_republic'];
    $this->denmark       = $map['denmark'];
    $this->france        = $map['france'];
    $this->luxembourg    = $map['luxembourg'];
    $this->netherlands   = $map['netherlands'];
    $this->norway        = $map['norway'];
    $this->poland        = $map['poland'];
    $this->sweden        = $map['sweden'];
    $this->switzerland   = $map['switzerland'];

    // pumped storage consumption is already reported as a negative value, so
    // adding it to generation gives the net signed power: positive when
    // generating, negative when pumping
    $this->pumped = $map['pumped_generation'] + $map['pumped_consumption'];
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
