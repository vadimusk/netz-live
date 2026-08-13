<?php

namespace KateMorley\Grid\State;

use KateMorley\Grid\UI\I18n;

/** A kind of power source. */
enum Kind: string {
  case Generation      = 'generation';
  case Fossils         = 'fossils';
  case Renewables      = 'renewables';
  case Others          = 'others';
  case Transfers       = 'transfers';
  case Interconnectors = 'interconnectors transfers';
  case Storage         = 'storage transfers';

  /**
   * Returns a description of the kind of power source.
   *
   * @param string $locale The locale ('de' or 'en')
   */
  public function describe(string $locale): string {
    return I18n::t('kind.' . explode(' ', $this->value)[0], $locale);
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
        Source::Lignite,
        Source::HardCoal,
        Source::Gas,
        Source::Solar,
        Source::Wind,
        Source::Hydro,
        Source::Nuclear,
        Source::Biomass,
        Source::Other
      ],
      self::Fossils => [
        Source::Lignite,
        Source::HardCoal,
        Source::Gas
      ],
      self::Renewables => [
        Source::Solar,
        Source::Wind,
        Source::Hydro
      ],
      self::Others => [
        Source::Nuclear,
        Source::Biomass,
        Source::Other
      ],
      self::Transfers => [
        Source::Austria,
        Source::Belgium,
        Source::CzechRepublic,
        Source::Denmark,
        Source::France,
        Source::Luxembourg,
        Source::Netherlands,
        Source::Norway,
        Source::Poland,
        Source::Sweden,
        Source::Switzerland,
        Source::Pumped
      ],
      self::Interconnectors => [
        Source::Austria,
        Source::Belgium,
        Source::CzechRepublic,
        Source::Denmark,
        Source::France,
        Source::Luxembourg,
        Source::Netherlands,
        Source::Norway,
        Source::Poland,
        Source::Sweden,
        Source::Switzerland
      ],
      self::Storage => [
        Source::Pumped
      ]
    };
  }
}
