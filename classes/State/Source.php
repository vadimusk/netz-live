<?php

namespace KateMorley\Grid\State;

use KateMorley\Grid\UI\I18n;

/** A power source. */
enum Source: string {
  case Lignite       = 'lignite';
  case HardCoal      = 'hardCoal';
  case Gas           = 'gas';
  case Solar         = 'solar';
  case Wind          = 'wind';
  case Hydro         = 'hydro';
  case Nuclear       = 'nuclear';
  case Biomass       = 'biomass';
  case Other         = 'other';
  case Austria       = 'austria';
  case Belgium       = 'belgium';
  case CzechRepublic = 'czechRepublic';
  case Denmark       = 'denmark';
  case France        = 'france';
  case Luxembourg    = 'luxembourg';
  case Netherlands   = 'netherlands';
  case Norway        = 'norway';
  case Poland        = 'poland';
  case Sweden        = 'sweden';
  case Switzerland   = 'switzerland';
  case Pumped        = 'pumped';

  /**
   * Returns a description of the source.
   *
   * @param string $locale The locale ('de' or 'en')
   */
  public function describe(string $locale): string {
    return I18n::t('source.' . $this->value, $locale);
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
