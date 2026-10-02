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
 * - **The borders and pumped storage** divide what is left between them, so
 *   that the equation the panel prints, demand = generation + transfers, holds
 *   of every estimated quarter hour: each border by its day-ahead schedule,
 *   pumped storage by the price (see split()).
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

  /** The borders, which with pumped storage make up the transfers. */
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
   * How far each border's physical flow follows a change in its day-ahead
   * schedule; a border missing here, Luxembourg, has no schedule and is
   * carried forward.
   *
   * Fitted from October 2025 to September 2026 on the change in flow against
   * the change in schedule, over every reach from a quarter hour to six hours.
   * Fitted on alternate months instead, most move by a few hundredths, and the
   * Czech border, the least steady, by a fifth. The links built
   * to carry trade — the cables to Norway and Sweden, the Netherlands — follow
   * it almost wholly. The meshed borders to the south and east follow it by
   * half to three quarters, because power crossing them takes every path the
   * network offers and part of it was traded between other countries: France
   * least, at a half.
   */
  private const SCHEDULE_WEIGHTS = [
    'austria'        => 0.58,
    'belgium'        => 0.71,
    'czech_republic' => 0.72,
    'denmark'        => 0.71,
    'france'         => 0.49,
    'netherlands'    => 0.87,
    'norway'         => 0.92,
    'poland'         => 0.78,
    'sweden'         => 0.90,
    'switzerland'    => 0.57
  ];

  /**
   * How far pumped storage moves, in gigawatts, for each euro per megawatt
   * hour the day-ahead price moves: it pumps when power is cheap and generates
   * when it is dear, and the price is settled the day before.
   *
   * Carried forward, as it was until September 2026, pumped storage was the
   * largest unknown among the transfers: 1.1GW out an hour on and 4.0GW six
   * hours on. Moved by the price it is 0.9GW and 2.0GW out, fitted on
   * alternate months and tested on the others.
   */
  private const PRICE_WEIGHT = 0.046;

  /**
   * How far each part's own estimate is typically out an hour on, in
   * gigawatts — the borders moved by their schedules, pumped storage by the
   * price — measured over the same year. split() shares out what the parts
   * leave by these, squared.
   */
  private const SPREAD = [
    'austria'        => 0.25,
    'belgium'        => 0.17,
    'czech_republic' => 0.21,
    'denmark'        => 0.31,
    'france'         => 0.25,
    'luxembourg'     => 0.05,
    'netherlands'    => 0.37,
    'norway'         => 0.09,
    'poland'         => 0.22,
    'sweden'         => 0.01,
    'switzerland'    => 0.28,
    'pumped'         => 0.88
  ];

  /**
   * Builds the predicted quarter hours, returning an array mapping times to
   * data.
   *
   * @param int                             $time      The time of the newest
   *                                                    confirmed quarter hour
   * @param array<string,mixed>             $map       The newest confirmed row
   * @param array<int,array<string,?float>> $forecasts The forecasts, mapping
   *                                                    times to columns
   * @param int                             $now       The current time
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
      self::split($row, $map, $target, $forecasts[$time], $forecasts[$t]);

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
   * Divides what the transfers have to carry between the borders and pumped
   * storage, leaving the equation the panel prints — demand = generation +
   * transfers — true of the predicted quarter hour as it is of the measured
   * ones.
   *
   * The total is settled by that equation; only its division is decided here.
   * Each border first moves by the change in its day-ahead schedule, times
   * how closely its flow follows the schedule, and pumped storage by the
   * change in the price. What their sum leaves short of the total is then
   * shared out by how far each part's own estimate is typically wrong,
   * squared: the least certain parts take the most of it, which is the
   * division that errs least over the whole if the parts err independently.
   * Pumped storage, the least certain, takes a little over half.
   *
   * Until September 2026 the borders took the whole of it in proportion to
   * what each was carrying, with pumped storage held — a split no better than
   * holding every part where it was, which is why the countries were not drawn
   * until then. Measured over October 2025 to September 2026 by building the
   * estimate as here from every quarter hour as the anchor, the parts were
   * 4.6GW out between them an hour on and 13.9GW six hours on; split like
   * this, 3.0GW and 6.6GW, every border closer at every reach. The total is
   * the same either way, and so is everything else on the page.
   *
   * @param array<string,mixed>  $row    The predicted row, modified in place
   * @param array<string,mixed>  $anchor The last confirmed row
   * @param float                $demand The estimated demand
   * @param array<string,?float> $then   The forecast row for the anchor
   * @param array<string,?float> $now    The forecast row being predicted
   */
  private static function split(
    array &$row,
    array $anchor,
    float $demand,
    array $then,
    array $now
  ): void {
    $total = $demand - Kind::Generation->get((new Datum($row))->sources);
    $parts = [];

    foreach (self::INTERCONNECTORS as $column) {
      $parts[$column] = (float)($anchor[$column] ?? 0);

      // a border without a schedule at both ends is carried forward
      if (isset(self::SCHEDULE_WEIGHTS[$column], $then[$column], $now[$column])) {
        $parts[$column] += self::SCHEDULE_WEIGHTS[$column]
          * ($now[$column] - $then[$column]);
      }
    }

    $parts['pumped'] = (float)($anchor['pumped_generation'] ?? 0)
      + (float)($anchor['pumped_consumption'] ?? 0);

    if (isset($now['price'])) {
      $parts['pumped'] += self::PRICE_WEIGHT
        * ($now['price'] - (float)($anchor['price'] ?? 0));
    }

    $gap      = $total - array_sum($parts);
    $variance = array_sum(array_map(fn ($spread) => $spread ** 2, self::SPREAD));

    foreach ($parts as $part => $value) {
      $parts[$part] = $value + $gap * self::SPREAD[$part] ** 2 / $variance;
    }

    foreach (self::INTERCONNECTORS as $column) {
      $row[$column] = round($parts[$column], 3);
    }

    self::pump($row, $anchor, $parts['pumped']);
  }

  /**
   * Sets pumped storage to a net figure, which the record keeps as two: what
   * the fleet generated and, as a negative figure, what it drew. A change is
   * taken first from whichever side it shrinks, so a fleet that was pumping
   * and is estimated to pump less draws less rather than drawing as much and
   * generating too.
   *
   * @param array<string,mixed> $row    The predicted row, modified in place
   * @param array<string,mixed> $anchor The last confirmed row
   * @param float               $net    The estimated net generation
   */
  private static function pump(array &$row, array $anchor, float $net): void {
    $generation  = (float)($anchor['pumped_generation'] ?? 0);
    $consumption = (float)($anchor['pumped_consumption'] ?? 0);
    $change      = $net - $generation - $consumption;

    if ($change >= 0) {
      $less         = min(-$consumption, $change);
      $consumption += $less;
      $generation  += $change - $less;
    } else {
      $less         = min($generation, -$change);
      $generation  -= $less;
      $consumption -= -$change - $less;
    }

    $row['pumped_generation']  = round($generation, 3);
    $row['pumped_consumption'] = round($consumption, 3);
  }
}
