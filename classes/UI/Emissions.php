<?php

namespace KateMorley\Grid\UI;

/** Emissions levels. */
enum Emissions {
  case Low;
  case Medium;
  case High;

  /** The threshold between the low and medium levels. */
  const LOW_MEDIUM = 50;

  /** The threshold between the medium and high levels. */
  const MEDIUM_HIGH = 100;

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
