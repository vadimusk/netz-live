<?php

namespace KateMorley\Grid\State;

use KateMorley\Grid\Data\Emissions;
use KateMorley\Grid\Data\Forecast;

/**
 * Builds the quarter hours between the newest confirmed data and now.
 *
 * The measured mix is always an hour or so behind, because a quarter hour must
 * end before the operators can report it. These are the quarter hours that have
 * happened but haven't been published: they are estimated rather than measured,
 * shown as a dashed line, and never written to the database.
 *
 * Everything is anchored to the last confirmed quarter hour: its values set
 * the level, and the day-ahead forecast supplies only the change since then.
 * A forecast reading high or low keeps its shape without carrying its offset
 * in, which is most of its error, and the dashed line starts where the solid
 * one ends instead of jumping to the forecast's own level.
 *
 * - **Solar and wind** move by the forecast's change.
 * - **Demand** moves by the demand forecast's change, weighted by season and
 *   corrected for solar (see LOAD_WEIGHT). Over October 2025 to September 2026
 *   that is 0.4GW out in the first half hour and 1.3GW three to six hours on,
 *   where holding demand, as this used to, is 6 to 7GW out by then.
 * - **Coal and gas** take FOSSIL_SHARE of whatever the demand asks for beyond
 *   what solar and wind now give, since dispatchable plant is what answers a
 *   change in demand; the rest of the mix is carried forward.
 * - **The borders** take what is left, so that the equation the panel prints,
 *   demand = generation + transfers, holds of every estimated quarter hour.
 * - **The carbon intensity** is moved by how much the estimated mix changes
 *   the calculated figure, rather than replaced by it: the official figure the
 *   solid line shows can sit tens of grams from the calculated one.
 */
class Prediction {
  /**
   * The gap below which the estimate is drawn as a dashed line alone, without
   * the band around it.
   *
   * The dashed line always runs: the stretch it covers has happened and has
   * not been published, and saying so costs nothing. The band is the part
   * that needs a reason. When the source is keeping up, the estimate is three
   * or four quarter hours and the band around it is a few pixels wide — a
   * smudge at the end of every line, carrying no reading anybody could take.
   * It appears once the source actually falls behind, and grows with the
   * delay: on the day the platform stalled thirteen hours it covered half the
   * graph, which is exactly when its width is worth showing.
   */
  public const BAND_LAG = 60 * 60;

  /**
   * The share of the change in demand, beyond what solar and wind cover, that
   * coal and gas are moved to meet; the borders take the rest.
   *
   * Nought — holding coal and gas where they were — puts every gigawatt of a
   * change in demand onto the borders, and was the worst setting for the
   * transfers, the fossil lines and the carbon intensity alike. Swept over the
   * year to September 2026, three tenths is best for the fossil lines and
   * within three per cent of the best for the transfers, the generation as a
   * whole and the carbon intensity.
   */
  private const FOSSIL_SHARE = 0.3;

  /**
   * How the demand forecast's change maps onto the demand the panel shows,
   * which is the generation plus the transfers, and how that shifts with the
   * season.
   *
   * The two are not the same quantity, and how they differ follows the sun.
   * Taken as it comes, the forecast ran three and a half gigawatts high three
   * to six hours on from a September midday, and as far low from an early
   * morning — a shift, not noise. Fitted on September alone, the panel's
   * demand moved by 0.85 of the forecast's change plus 0.12 of the solar
   * forecast's; but tested on the rest of the year that fit did worse than the
   * forecast untouched, 2.5GW out three to six hours on in March and April
   * against 1.5GW. Fitted month by month, the weights drift smoothly with the
   * season — the load weight from about 0.85 in high summer to 1.0 in winter,
   * the solar term from a tenth to nothing — so each is a constant plus a
   * multiple of season(), which is +1 in mid-July and -1 in mid-January.
   *
   * Fitted on alternate months from October 2025 to September 2026 and tested
   * on the others, that is 1.31GW out three to six hours on, against 1.46GW
   * for fixed weights and 1.54GW for the forecast as it comes; left out one
   * month at a time, it wins ten months of the twelve. Fitted on the whole
   * year, the values are these.
   */
  private const LOAD_WEIGHT  = 0.92;
  private const LOAD_SEASON  = -0.07;
  private const SOLAR_SEASON = 0.10;

  /** The columns the generation forecast covers. */
  private const COLUMNS = [
    'solar',
    'wind_onshore',
    'wind_offshore'
  ];

  /** The dispatchable columns moved to meet the demand. */
  private const FOSSILS = [
    'lignite',
    'hard_coal',
    'gas'
  ];

  /**
   * The columns that take up the difference between the estimated generation
   * and the estimated demand. Pumped storage is left out even though it counts
   * among the transfers, because it answers to the price rather than to a
   * surplus.
   */
  private const INTERCONNECTORS = [
    'austria',
    'belgium',
    'czech_republic',
    'denmark',
    'france',
    'luxembourg',
    'netherlands',
    'norway',
    'poland',
    'sweden',
    'switzerland'
  ];

  /**
   * Builds the predicted quarter hours, returning an array mapping times to
   * data.
   *
   * @param int                            $time      The time of the newest
   *                                                   confirmed quarter hour
   * @param array<string,mixed>            $map       The newest confirmed row
   * @param array<int,array<string,float>> $forecasts The forecasts, mapping
   *                                                   times to columns
   * @param int                            $now       The current time
   *
   * @return array<int,Datum>
   */
  public static function build(
    int   $time,
    array $map,
    array $forecasts,
    int   $now
  ): array {
    // the quarter hour that has most recently begun is the one standing in for
    // "now"; it is still running, so the forecast is all there is for it
    $latest = intdiv($now, 900) * 900;

    // everything is measured from the confirmed quarter hour, so without its
    // forecast there is nothing to measure from
    if ($time <= 0 || $latest <= $time || !self::usable($forecasts[$time] ?? null)) {
      return [];
    }

    $anchorSources = (new Datum($map))->sources;
    $demand        = Kind::Generation->get($anchorSources) + Kind::Transfers->get($anchorSources);
    $calculated    = Emissions::calculate($map);
    $season        = self::season($time);

    $predicted = [];

    for ($t = $time + 900; $t <= $latest; $t += 900) {
      if (!self::usable($forecasts[$t] ?? null)) {
        // the forecast has run out, and a gap in the middle of a line is worse
        // than a line that stops early
        break;
      }

      $row = $map;
      unset($row['time']);

      foreach (self::COLUMNS as $column) {
        // generation cannot be negative, and anchoring an overnight solar
        // forecast can otherwise push it slightly below zero
        $row[$column] = max(0.0, round(
          (float)($map[$column] ?? 0) + $forecasts[$t][$column] - $forecasts[$time][$column],
          3
        ));
      }

      $target = $demand
        + (self::LOAD_WEIGHT + self::LOAD_SEASON * $season)
          * ($forecasts[$t][Forecast::LOAD] - $forecasts[$time][Forecast::LOAD])
        + self::SOLAR_SEASON * $season
          * ($forecasts[$t]['solar'] - $forecasts[$time]['solar']);

      self::dispatch($row, $map, $target);
      self::balance($row, $target);

      $row['emissions'] = max(0, (int)round(
        (float)($map['emissions'] ?? 0) + Emissions::calculate($row) - $calculated
      ));

      $predicted[$t] = new Datum($row);
    }

    return $predicted;
  }

  /**
   * Returns whether the estimate is drawn with a band around it, which it is
   * only once the source has fallen far enough behind for the width to mean
   * something.
   *
   * @param int $time The time of the newest confirmed quarter hour
   * @param int $now  The current time
   */
  public static function banded(int $time, int $now): bool {
    return $now - $time > self::BAND_LAG;
  }

  /**
   * Returns where a moment falls in the year: +1 in mid-July, -1 in
   * mid-January, following a cosine in between.
   *
   * @param int $time The Unix timestamp
   */
  private static function season(int $time): float {
    return cos(2 * M_PI * ((int)gmdate('z', $time) + 1 - 196) / 365.25);
  }

  /**
   * Returns whether a forecast row can be built on: every generation column
   * and a demand. Rows written before the demand was required carry nought in
   * place of it, which anchored against a real figure would read as the grid
   * losing fifty gigawatts.
   *
   * @param ?array<string,float> $forecast The forecast row
   */
  private static function usable(?array $forecast): bool {
    if ($forecast === null || ($forecast[Forecast::LOAD] ?? 0) <= 0) {
      return false;
    }

    foreach (self::COLUMNS as $column) {
      if (!isset($forecast[$column])) {
        return false;
      }
    }

    return true;
  }

  /**
   * Moves coal and gas to meet their share of the change in demand that solar
   * and wind do not already cover, each in proportion to what it is already
   * running at, since those are the plants that are warm.
   *
   * @param array<string,mixed> $row    The predicted row, modified in place
   * @param array<string,mixed> $anchor The last confirmed row
   * @param float               $demand The estimated demand
   */
  private static function dispatch(array &$row, array $anchor, float $demand): void {
    $anchorSources = (new Datum($anchor))->sources;
    $sources       = (new Datum($row))->sources;

    $residual = ($demand - Kind::Generation->get($anchorSources) - Kind::Transfers->get($anchorSources))
      - (Kind::Generation->get($sources) - Kind::Generation->get($anchorSources));

    $running = 0;

    foreach (self::FOSSILS as $column) {
      $running += (float)($anchor[$column] ?? 0);
    }

    if ($running <= 0) {
      return;
    }

    foreach (self::FOSSILS as $column) {
      $value = (float)($anchor[$column] ?? 0);

      $row[$column] = max(0.0, round(
        $value + self::FOSSIL_SHARE * $residual * $value / $running,
        3
      ));
    }
  }

  /**
   * Moves the interconnectors so that the estimated demand comes out, leaving
   * the equation the panel prints — demand = generation + transfers — true of
   * the predicted quarter hour as it is of the measured ones.
   *
   * The difference is spread across the borders in proportion to what each is
   * already carrying, since a surplus leaves along the lines already in use.
   * The per-country figures this produces are not shown anywhere: the country
   * table and the transfers graph are drawn from confirmed data only, and this
   * shows up solely in the total.
   *
   * @param array<string,mixed> $row    The predicted row, modified in place
   * @param float               $demand The estimated demand
   */
  private static function balance(array &$row, float $demand): void {
    $sources = (new Datum($row))->sources;

    // what the borders have to carry for that demand to come out, with pumped
    // storage counted separately since it is carried rather than moved
    $target = $demand
      - Kind::Generation->get($sources)
      - Source::Pumped->get($sources);

    $current = 0;
    $weights = 0;

    foreach (self::INTERCONNECTORS as $column) {
      $current += (float)($row[$column] ?? 0);
      $weights += abs((float)($row[$column] ?? 0));
    }

    $difference = $target - $current;

    foreach (self::INTERCONNECTORS as $column) {
      $value = (float)($row[$column] ?? 0);

      // with every border sitting at zero there is no pattern to follow, so
      // the difference is shared equally rather than divided by nothing
      $share = $weights > 0
        ? abs($value) / $weights
        : 1 / count(self::INTERCONNECTORS);

      $row[$column] = round($value + $difference * $share, 3);
    }
  }
}
