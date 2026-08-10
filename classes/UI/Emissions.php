<?php

namespace KateMorley\Grid\UI;

/** Emissions levels. */
enum Emissions {
  case Low;
  case Medium;
  case High;

  /*
   * The thresholds are the thirds of the German grid's own range. Over the
   * past year the carbon intensity ran from 94 to 766g/kWh with a median of
   * 382, and a third of the time it sat below 296 and a third above 458, so
   * the levels are set at 300 and 450. Upstream's 50 and 100, taken from the
   * UK's Clean Power 2030 target, would leave this grid red at all times:
   * it spends 95% of the year above 141g/kWh.
   */

  /** The threshold between the low and medium levels. */
  const LOW_MEDIUM = 300;

  /** The threshold between the medium and high levels. */
  const MEDIUM_HIGH = 450;

  /** Returns a class for the emissions level. */
  public function class(): string {
    return match ($this) {
      self::Low    => 'low',
      self::Medium => 'medium',
      self::High   => 'high'
    };
  }

  /** Returns the minimum value for the emissions level. */
  public function minimum(): int {
    return match ($this) {
      self::Low    => 0,
      self::Medium => self::LOW_MEDIUM,
      self::High   => self::MEDIUM_HIGH
    };
  }

  /**
   * Returns the emissions level for an emissions value.
   *
   * @param int $emissions The emissions value
   */
  public static function get(int $emissions): Emissions {
    if ($emissions <= self::LOW_MEDIUM) {
      return Self::Low;
    } elseif ($emissions <= self::MEDIUM_HIGH) {
      return self::Medium;
    } else {
      return self::High;
    }
  }
}
