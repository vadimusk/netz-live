<?php

namespace KateMorley\Grid\UI;

/** Formats values. */
class Value {
  /**
   * Formats a power value.
   *
   * @param float  $value  The value
   * @param string $locale The locale ('de' or 'en')
   */
  public static function formatPower(float $value, string $locale): string {
    return self::format($value, 2, $locale);
  }

  /**
   * Formats a total power value.
   *
   * @param float  $value  The value
   * @param string $locale The locale ('de' or 'en')
   */
  public static function formatTotalPower(float $value, string $locale): string {
    return self::format($value, 1, $locale);
  }

  /**
   * Formats a percentage.
   *
   * @param float  $value  The value, as a fraction
   * @param string $locale The locale ('de' or 'en')
   */
  public static function formatPercentage(float $value, string $locale): string {
    return self::format(100 * $value, 1, $locale);
  }

  /**
   * Formats a value as a percentage of a total.
   *
   * @param float  $value  The value
   * @param float  $total  The total the value is a share of
   * @param string $locale The locale ('de' or 'en')
   */
  public static function formatShare(
    float  $value,
    float  $total,
    string $locale
  ): string {
    return self::formatPercentage(self::share($value, $total), $locale);
  }

  /**
   * Returns a value as a fraction of a total, treating a total of zero as a
   * share of zero rather than dividing by it. A period can legitimately hold
   * no data at all: until a new database has collected a couple of days, the
   * past week and past year cover no rows.
   *
   * @param float $value The value
   * @param float $total The total the value is a share of
   */
  public static function share(float $value, float $total): float {
    return $total == 0.0 ? 0.0 : $value / $total;
  }

  /**
   * Formats a price.
   *
   * @param float  $value  The value
   * @param string $locale The locale ('de' or 'en')
   */
  public static function formatPrice(float $value, string $locale): string {
    return self::format($value, 2, $locale, '€');
  }

  /**
   * Formats a value.
   *
   * @param float  $value,        The value
   * @param int    $decimalPlaces The number of decimal places to show
   * @param string $locale        The locale ('de' or 'en')
   * @param string $prefix        An option prefix
   */
  private static function format(
    float  $value,
    int    $decimalPlaces,
    string $locale,
    string $prefix = ''
  ): string {
    return (
      ($value < 0 ? '−' : '')
      . $prefix
      . I18n::number(abs($value), $decimalPlaces, $locale)
    );
  }
}
