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

  /** Returns a description of the period. */
  public function describe(): string {
    return match ($this) {
      self::Day  => 'Past day',
      self::Week => 'Past week',
      self::Year => 'Past year',
      self::All  => 'All time'
    };
  }

  /** Returns an HTML description of the period. */
  public function describeHtml(): string {
    return match ($this) {
      self::Day  => '<span>Past </span>day',
      self::Week => '<span>Past </span>week',
      self::Year => '<span>Past </span>year',
      self::All  => 'All<span> time</span>'
    };
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

  /**
   * Formats a timestamp during the period.
   *
   * @param int $timestamp The timestamp
   */
  public function format(int $timestamp): string {
    return date(match ($this) {
      self::Day  => 'g:ia',
      self::Week => 'l',
      self::Year => 'd/m/Y',
      self::All  => 'Y'
    }, $timestamp);
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
