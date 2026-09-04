<?php

namespace KateMorley\Grid\State;

/** Represents the UI state. */
class State {
  /**
   * Constructs a new instance.
   *
   * @param int           $time           The time of the latest data
   * @param Datum         $latest         The latest datum
   * @param array<Datum>  $predicted      The quarter hours between the latest
   *                                       confirmed data and now, estimated
   *                                       rather than measured
   * @param bool          $banded         Whether the estimate is drawn with a
   *                                       band around it, which it is only
   *                                       once the source has fallen far
   *                                       enough behind for its width to be
   *                                       worth reading
   * @param Datum         $day            The past day's datum
   * @param Datum         $week           The past week's datum
   * @param Datum         $year           The past year's datum
   * @param Datum         $all            The all-time datum
   * @param array<Datum>  $daySeries      The past day series
   * @param array<Datum>  $weekSeries     The past week series
   * @param array<Datum>  $yearSeries     The past year series
   * @param array<Datum>  $allSeries      The all-time series
   * @param Record        $windRecord     The wind power generation record
   * @param array<string> $windMilestones The wind power generation milestones
   * @param int           $windMilestonesSince The start of the record keeping
   * @param int           $visits         The number of visits in the past year
   * @param bool           $visitsCoverYear Whether visits have been counted
   *                                        for a full year
   */
  public function __construct(
    public readonly int    $time,
    public readonly Datum  $latest,
    public readonly array  $predicted,
    public readonly bool   $banded,
    public readonly Datum  $day,
    public readonly Datum  $week,
    public readonly Datum  $year,
    public readonly Datum  $all,
    public readonly array  $daySeries,
    public readonly array  $weekSeries,
    public readonly array  $yearSeries,
    public readonly array  $allSeries,
    public readonly Record $windRecord,
    public readonly array  $windMilestones,
    public readonly int    $windMilestonesSince,
    public readonly int    $visits,
    public readonly bool   $visitsCoverYear
  ) {
  }
}
