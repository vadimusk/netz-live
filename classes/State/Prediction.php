<?php

namespace KateMorley\Grid\State;

use KateMorley\Grid\Data\Emissions;

/**
 * Builds the quarter hours between the newest confirmed data and now.
 *
 * The measured mix is always an hour or so behind, because a quarter hour must
 * end before the operators can report it. These are the quarter hours that have
 * happened but haven't been published: they are estimated rather than measured,
 * shown as a dashed line, and never written to the database.
 *
 * Two methods, chosen by how far behind the confirmed data is:
 *
 * - **Anchored** while the gap is under ANCHOR_LIMIT. The last confirmed values
 *   set the level and the forecast supplies only the change since then, so a
 *   forecast that is reading high or low keeps its shape without carrying its
 *   offset in. Measured over a week this beats the raw forecast by 30% for
 *   solar and 37% for offshore wind at 45 minutes.
 * - **The raw forecast** beyond it, because anchoring is only worth it while
 *   the forecast's error resembles the error it had at the anchor. That
 *   correlation runs 0.53 to 0.79 at an hour and falls through 0.5 at around
 *   ninety minutes, which is where anchoring stops paying and starts dragging
 *   a stale offset forward.
 *
 * Only the weather-driven sources are predicted. Coal, gas, biomass, hydro and
 * the price are carried forward from the last confirmed quarter hour, which for
 * slowly dispatched sources costs little.
 *
 * **The change in generation is shared between demand and the borders.** The
 * panel prints demand = generation + transfers, so of those three only two can
 * be modelled and the third has to follow. Both extremes are worse than a split:
 * letting demand absorb everything makes it 72% worse than carrying it forward,
 * because when solar climbs the surplus leaves the country rather than being
 * consumed; letting the borders absorb everything — which this did at first —
 * turned out to be the worst setting for *both* figures at once. Measured across
 * 170 predictions per horizon, `DEMAND_SHARE = 0.3` beats it by 44% on the
 * transfers and 20% on demand at an hour ahead, and wins at every horizon. That
 * matches how the grid answers a surge: mostly exports and pumping, but demand
 * drifts a little too.
 *
 * The carbon intensity is recalculated from the predicted mix rather than
 * carried, since that is the number the prediction most changes.
 */
class Prediction {
  /**
   * The gap beyond which the forecast is used as it comes rather than anchored
   * to the last confirmed values.
   */
  public const ANCHOR_LIMIT = 75 * 60;

  /**
   * The share of the predicted change in generation that moves demand rather
   * than the borders. Swept over the recorded predictions: nought — holding
   * demand rigid — is the worst setting for the transfers and for demand alike,
   * and three tenths is the best compromise at every horizon.
   */
  private const DEMAND_SHARE = 0.3;

  /** The columns the forecast covers. */
  private const COLUMNS = [
    'solar',
    'wind_onshore',
    'wind_offshore'
  ];

  /**
   * The columns that take up the difference between predicted generation and
   * held demand. Pumped storage is left out even though it counts among the
   * transfers, because it answers to the price rather than to a surplus.
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

    if ($time <= 0 || $latest <= $time || count($forecasts) === 0) {
      return [];
    }

    $anchored = ($now - $time) < self::ANCHOR_LIMIT;

    // anchoring measures the forecast against the confirmed quarter hour, so
    // without a forecast for that quarter hour there is nothing to measure
    if ($anchored && !isset($forecasts[$time])) {
      $anchored = false;
    }

    $predicted = [];

    for ($t = $time + 900; $t <= $latest; $t += 900) {
      if (!isset($forecasts[$t])) {
        // the forecast has run out, and a gap in the middle of a line is worse
        // than a line that stops early
        break;
      }

      $row = $map;
      unset($row['time']);

      foreach (self::COLUMNS as $column) {
        $value = $anchored
          ? (float)($map[$column] ?? 0)
            + $forecasts[$t][$column]
            - $forecasts[$time][$column]
          : $forecasts[$t][$column];

        // generation cannot be negative, and anchoring an overnight solar
        // forecast can otherwise push it slightly below zero
        $row[$column] = max(0.0, round($value, 3));
      }

      $row['emissions'] = Emissions::calculate($row);

      self::balance($row, $map);

      $predicted[$t] = new Datum($row);
    }

    return $predicted;
  }

  /**
   * Moves the interconnectors so that demand stays where it was, leaving the
   * equation the panel prints — demand = generation + transfers — true of the
   * predicted quarter hour as it is of the measured ones.
   *
   * The difference is spread across the borders in proportion to what each is
   * already carrying, since a surplus leaves along the lines already in use.
   * The per-country figures this produces are not shown anywhere: the country
   * table and the transfers graph are drawn from confirmed data only, and this
   * shows up solely in the total.
   *
   * @param array<string,mixed> $row    The predicted row, modified in place
   * @param array<string,mixed> $anchor The last confirmed row
   */
  private static function balance(array &$row, array $anchor): void {
    $anchorSources = (new Datum($anchor))->sources;
    $sources       = (new Datum($row))->sources;

    // demand as it was at the anchor, which is what is being held
    $held = Kind::Generation->get($anchorSources)
      + Kind::Transfers->get($anchorSources);

    // the share of the predicted change in generation that demand is allowed to
    // take up; the borders carry the rest
    $change = Kind::Generation->get($sources) - Kind::Generation->get($anchorSources);

    // what the borders have to carry for that demand to come out again, with
    // pumped storage counted separately since it is carried rather than moved
    $target = $held
      + self::DEMAND_SHARE * $change
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
