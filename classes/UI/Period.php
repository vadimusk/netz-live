<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\State;

/** A time period. */
enum Period {
  case Day;
  case Week;
  case Year;
  case All;

  /** Returns an ID for the period. */
  public function id(): string {
    return match ($this) {
      self::Day  => 'day',
      self::Week => 'week',
      self::Year => 'year',
      self::All  => 'all'
    };
  }

  /**
   * Returns a description of the period.
   *
   * @param string $locale The locale ('de' or 'en')
   */
  public function describe(string $locale): string {
    return I18n::t('period.' . $this->id(), $locale);
  }

  /**
   * Returns an HTML description of the period.
   *
   * @param string $locale The locale ('de' or 'en')
   */
  public function describeHtml(string $locale): string {
    return I18n::t('period.' . $this->id() . '.html', $locale);
  }

  /** Returns the interval between tickmarks on graphs covering the period. */
  public function tickmarkInterval(): int {
    return match ($this) {
      self::Day  => 12,
      self::Week => 1,
      self::Year => 13,
      self::All  => 1
    };
  }

  // PHP's date() formatting codes for weekday names aren't localisable
  // without the intl extension, so short German weekday names are mapped by
  // hand here (Monday first, matching ISO-8601 numeric weekday order)
  private const WEEKDAYS_DE = ['Mo', 'Di', 'Mi', 'Do', 'Fr', 'Sa', 'So'];

  /**
   * Formats a timestamp during the period.
   *
   * @param int    $timestamp The timestamp
   * @param string $locale    The locale ('de' or 'en')
   */
  public function format(int $timestamp, string $locale): string {
    $local = (new \DateTime('@' . $timestamp))->setTimezone(
      new \DateTimeZone('Europe/Berlin')
    );

    if ($this === self::Week) {
      return $locale === 'de'
        ? self::WEEKDAYS_DE[(int)$local->format('N') - 1]
        : $local->format('l');
    }

    return $local->format(match ($this) {
      self::Day  => $locale === 'de' ? 'H:i' : 'g:ia',
      self::Year => 'd.m.Y',
      self::All  => 'Y'
    });
  }

  /**
   * Returns the datum for the period.
   *
   * @param State $state The state
   */
  public function datum(State $state): Datum {
    return match ($this) {
      self::Day  => $state->day,
      self::Week => $state->week,
      self::Year => $state->year,
      self::All  => $state->all
    };
  }

  /**
   * Returns the series for the period.
   *
   * @param State $state The state
   *
   * @return array<int,Datum>
   */
  public function series(State $state): array {
    return match ($this) {
      self::Day  => $state->daySeries,
      self::Week => $state->weekSeries,
      self::Year => $state->yearSeries,
      self::All  => $state->allSeries
    };
  }
}
