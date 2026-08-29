<?php

namespace KateMorley\Grid;

use KateMorley\Grid\Data\Emissions;
use KateMorley\Grid\Data\Generation;
use KateMorley\Grid\Data\Pricing;
use KateMorley\Grid\Data\Visits;
use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Record;
use KateMorley\Grid\State\State;

/** Database functions. */
class Database {
  private const PAST_DAY  = '(SELECT * FROM past_quarter_hours ORDER BY time DESC LIMIT 96)';
  private const PAST_WEEK = '(SELECT * FROM past_days ORDER BY time DESC LIMIT 1,7)';
  private const PAST_YEAR = '(SELECT * FROM past_weeks ORDER BY time DESC LIMIT 1,52)';

  private \mysqli $connection;

  /** Constructs a new instance. */
  public function __construct() {
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

    $this->connection = new \mysqli(
      getenv('DATABASE_HOSTNAME'),
      getenv('DATABASE_USERNAME'),
      getenv('DATABASE_PASSWORD'),
      getenv('DATABASE_DATABASE')
    );

    $this->connection->set_charset('utf8mb4');
  }

  /** Returns the latest state. */
  public function getState(): State {
    list($time, $latest) = $this->getLatest();

    return new State(
      $time,
      $latest,
      $this->getPastPeriod(self::PAST_DAY),
      $this->getPastPeriod(self::PAST_WEEK),
      $this->getPastPeriod(self::PAST_YEAR),
      $this->getPastPeriod('past_days'),
      $this->getSeries(self::PAST_DAY),
      $this->getSeries(self::PAST_WEEK),
      $this->getSeries(self::PAST_YEAR),
      $this->getSeries('past_years'),
      $this->getWindRecord(),
      $this->getWindMilestones(),
      $this->getRecordsStart(),
      $this->getYearlyVisits(),
      $this->getVisitsCoverYear()
    );
  }

  /**
   * Returns the earliest quarter hour, as a YYYY-MM-DD HH:MM:SS string. The
   * return value represents the latest midnight more than four weeks ago;
   * this ensures that the quarter-hourly data represents complete days for
   * aggregation.
   */
  public function getEarliestQuarterHour(): string {
    return gmdate(
      'Y-m-d H:i:s',
      gmmktime(0, 0, 0, gmdate('n'), gmdate('j') - 28)
    );
  }

  /** Returns the latest quarter hour, as a YYYY-MM-DD HH:MM:SS string. */
  public function getLatestQuarterHour(): string {
    return $this->connection->query(
      'SELECT MAX(time) FROM past_quarter_hours'
    )->fetch_row()[0];
  }

  /** Returns the latest quarter hour, as a Unix timestamp. */
  public function getLatestQuarterHourTimestamp(): int {
    return strtotime($this->getLatestQuarterHour() . ' UTC');
  }

  /** Returns the latest time and datum. */
  private function getLatest(): array {
    $map = $this->getLatestMap('past_quarter_hours');

    return [
      strtotime($map['time'] . ' UTC'),
      new Datum($map)
    ];
  }

  /**
   * Returns a past period's datum.
   *
   * @param string $table The table
   */
  private function getPastPeriod(string $table): Datum {
    $row = $this->connection->query(
      'SELECT '
      . self::getAveragesExpression(self::getColumns())
      . ' FROM '
      . $table
      . ' AS t'
    )->fetch_assoc();

    return new Datum($row);
  }

  /**
   * Returns a past period's series.
   *
   * @param string $table The table
   */
  private function getSeries(string $table): array {
    $series = [];

    $rows = $this->connection->query(
      'SELECT time,'
      . implode(',', self::getColumns())
      . ' FROM '
      . $table
      . ' AS t ORDER BY time ASC'
    );

    while ($row = $rows->fetch_assoc()) {
      $series[strtotime($row['time'] . ' UTC')] = new Datum($row);
    }

    return $series;
  }

  /** Returns the wind power generation record. */
  private function getWindRecord(): Record {
    $record = $this->getLatestMap('wind_records');

    return new Record(
      strtotime($record['time'] . ' UTC'),
      $record['value']
    );
  }

  /** Returns the wind power generation milestones. */
  private function getWindMilestones(): array {
    $milestones = [];

    // compared whole gigawatts, since that's the granularity the table shows:
    // a warm-up peak of 30.04GW makes the whole 30GW row unreliable, not just
    // the readings below it
    $rows = $this->connection->query(
      'SELECT * FROM wind_records WHERE FLOOR(value)>FLOOR('
      . $this->getWindWarmUpMaximum()
      . ') ORDER BY value DESC'
    );

    while ($row = $rows->fetch_assoc()) {
      $milestones[floor($row['value'])] = strtotime($row['time'] . ' UTC');
    }

    return $milestones;
  }

  /** Returns the time the data begins, as a Unix timestamp. */
  private function getRecordsStart(): int {
    $start = $this->connection->query(
      'SELECT MIN(time) FROM past_days'
    )->fetch_row()[0];

    return $start === null ? time() : strtotime($start . ' UTC');
  }

  /**
   * Returns the highest wind power generation of the first month of records.
   *
   * Records are kept as "the first time this level was reached", which only
   * means anything once there is a stretch of history to be first within. On
   * the day observations start, wind climbs through every level below wherever
   * it happens to be, and each one is written down as a milestone — the
   * archive opens with a dozen of them sharing one date, saying nothing except
   * that the data starts there. Levels at or below that opening month's peak
   * are dropped, since they were very likely reached before the archive began.
   */
  private function getWindWarmUpMaximum(): float {
    $period = $this->connection->query(
      'SELECT MIN(time) AS first,MAX(time) AS last FROM wind_records'
    )->fetch_assoc();

    if ($period === null || $period['first'] === null) {
      return 0;
    }

    $warmUpEnd = strtotime($period['first'] . ' UTC') + 31 * 24 * 60 * 60;

    // a database younger than the warm-up period has nothing to compare
    // against yet, so every record it holds is shown
    if (strtotime($period['last'] . ' UTC') < $warmUpEnd) {
      return 0;
    }

    return (float)$this->connection->query(
      'SELECT MAX(value) FROM wind_records WHERE time<"'
      . gmdate('Y-m-d H:i:s', $warmUpEnd)
      . '"'
    )->fetch_row()[0];
  }

  /** Returns the number of visits in the past year. */
  private function getYearlyVisits(): int {
    // using 365 days rather than a calendar year (which would have 366 days in
    // a leap year) is more appropriate for a rolling total
    return (int)$this->connection->query(
      'SELECT SUM(visits) FROM past_days WHERE time>="'
      . date('Y-m-d', time() - 365 * 24 * 60 * 60)
      . '" AND time<"'
      . date('Y-m-d')
      . '"'
    )->fetch_row()[0];
  }

  /**
   * Returns whether visits have been counted for a full year.
   *
   * A new site has counted them for days rather than a year, and saying its
   * handful of visits arrived "over the past year" would read as a site nobody
   * goes to rather than one that just went up.
   */
  private function getVisitsCoverYear(): bool {
    $earliest = $this->connection->query(
      'SELECT MIN(time) FROM past_days WHERE visits>0'
    )->fetch_row()[0];

    return $earliest !== null
      && strtotime($earliest . ' UTC') <= time() - 365 * 24 * 60 * 60;
  }

  /**
   * Updates the generation and cross-border flow data.
   *
   * @param array         $generation        The generation data
   * @param array<string> $generationColumns The generation columns
   * @param array         $transfers         The flow data
   * @param array<string> $transferColumns   The flow columns
   * @param ?string       $latestTransfer    The latest quarter hour the flows
   *                                          cover, quoted for SQL, or null
   */
  public function updateGeneration(
    array   $generation,
    array   $generationColumns,
    array   $transfers,
    array   $transferColumns,
    ?string $latestTransfer
  ): void {
    $this->updatePastTimeSeries(
      'past_quarter_hours',
      $generationColumns,
      $generation
    );

    $this->updatePastTimeSeries(
      'past_quarter_hours',
      $transferColumns,
      $transfers
    );

    if ($latestTransfer !== null) {
      $this->propagateTransfers($transferColumns, $latestTransfer);
    }
  }

  /**
   * Carries the most recent flow figures forward over the quarter hours the
   * flow data doesn't reach yet.
   *
   * Flows are published a couple of hours after the fact, while generation
   * is current, so without this the newest quarter hours would show a full
   * generation mix against no trade at all. The carried-forward figures are
   * overwritten with the real ones as soon as they arrive, and the same
   * approach is what upstream uses for its own slower series.
   *
   * @param array<string> $columns The flow columns
   * @param string        $time    The latest quarter hour the flows cover,
   *                                quoted for SQL
   */
  private function propagateTransfers(array $columns, string $time): void {
    $row = $this->connection->query(
      'SELECT ' . implode(',', $columns)
      . ' FROM past_quarter_hours WHERE time=' . $time
    )->fetch_assoc();

    if ($row === null) {
      return;
    }

    $this->connection->query(
      'UPDATE past_quarter_hours SET '
      . implode(',', array_map(
        fn ($column) => $column . '=' . (float)$row[$column],
        $columns
      ))
      . ' WHERE time>' . $time
    );
  }

  /**
   * Calculates emissions from the generation mix for the quarter hours the
   * official figures don't reach yet.
   *
   * The official carbon intensity arrives around three hours after the fact,
   * where the generation it describes is barely an hour old. Rather than show
   * a stale figure beside a current mix, the remaining quarter hours are
   * filled in from the mix itself, and overwritten with the official figure
   * as soon as it arrives.
   *
   * @param array<string,int> $factors     A map from column to emission factor
   * @param array<string>     $denominator The columns the intensity is
   *                                        measured against
   * @param float             $offset      A constant added to the total
   * @param ?string           $after       The latest quarter hour the official
   *                                        figures cover, quoted for SQL, or
   *                                        null if none were read at all
   * @param int               $threshold   The largest gap allowed between an
   *                                        official figure and the calculated
   *                                        one before the official one is
   *                                        discarded as broken; 0 disables the
   *                                        check
   *
   * @return int The number of official figures discarded as implausible
   */
  public function updateComputedEmissions(
    array   $factors,
    array   $denominator,
    float   $offset,
    ?string $after,
    int     $threshold = 0
  ): int {
    $total = implode('+', $denominator);

    $emissions = implode('+', array_map(
      fn ($column, $factor) => $factor . '*' . $column,
      array_keys($factors),
      $factors
    ));

    $calculated = 'ROUND((' . $emissions . '+' . $offset . ')/(' . $total . '))';

    // an official figure that disagrees with the mix by more than the
    // threshold is not a late figure but a broken one, so it is recalculated
    // alongside the quarter hours no official figure has reached. The check
    // only runs where official figures were read (a null $after means the
    // source was unreachable, and its earlier figures are the good ones), and
    // is stateless: the moment the source serves a sane figure it is written
    // by updateExisting and passes this, so the calculated stand-in gives way.
    $guard = $after !== null && $threshold > 0
      ? 'time<=' . $after . ' AND ABS(emissions-' . $calculated . ')>' . $threshold
      : null;

    $discarded = 0;

    if ($guard !== null) {
      $discarded = (int)$this->connection->query(
        'SELECT COUNT(*) FROM past_quarter_hours'
        . ' WHERE (' . $total . ')>0 AND (' . $guard . ')'
      )->fetch_row()[0];
    }

    $this->connection->query(
      'UPDATE past_quarter_hours SET emissions=' . $calculated
      . ' WHERE (' . $total . ')>0 AND ('
      // quarter hours the official figures already cover are left alone, but
      // any they skipped are filled in: a new database starts with a few of
      // them, since the generation reaches back slightly further.
      //
      // Reading no official figures at all means the source was unreachable,
      // not that every quarter hour needs recalculating — overwriting the
      // official figures already held with calculated ones would throw away
      // the better number for as long as the outage lasted.
      . ($after === null
        ? 'emissions=0'
        : '(time>' . $after . ' OR emissions=0)')
      // …except an official figure that cannot be reconciled with the mix
      . ($guard !== null ? ' OR (' . $guard . ')' : '')
      . ')'
    );

    return $discarded;
  }

  /**
   * Updates existing quarter hours only, leaving data for quarter hours that
   * don't exist yet unwritten.
   *
   * @param array $columns The columns to update
   * @param array $data    The data
   */
  public function updateExisting(array $columns, array $data): void {
    foreach (array_chunk($data, 500) as $chunk) {
      $assignments = [];

      foreach ($columns as $index => $column) {
        $cases = '';

        foreach ($chunk as $datum) {
          $cases .= ' WHEN ' . $datum[0] . ' THEN ' . (float)$datum[$index + 1];
        }

        // rows the batch doesn't mention keep the value they hold, so one
        // statement can carry a few hundred quarter hours
        $assignments[] = $column . '=CASE time' . $cases . ' ELSE ' . $column . ' END';
      }

      $this->connection->query(
        'UPDATE past_quarter_hours SET '
        . implode(',', $assignments)
        . ' WHERE time IN ('
        . implode(',', array_column($chunk, 0))
        . ')'
      );
    }
  }

  /**
   * Writes quarter hours directly, without the windowing the regular update
   * applies. Used by the historic import, which writes years at a time.
   *
   * @param array<string> $columns The columns
   * @param array         $rows    The rows
   */
  public function insertQuarterHours(array $columns, array $rows): void {
    foreach (array_chunk($rows, 200) as $chunk) {
      $this->updatePastTimeSeries('past_quarter_hours', $columns, $chunk);
    }
  }

  /**
   * Updates a past time series.
   *
   * @param string $table   The table
   * @param array  $columns The columns to update
   * @param array  $data    The data
   */
  private function updatePastTimeSeries(
    string $table,
    array  $columns,
    array  $data
  ): void {
    if (count($data) === 0) {
      return;
    }

    $rows = array_map(
      fn ($datum) => '(' . implode(',', $datum) . ')',
      $data
    );

    $this->connection->query(
      'INSERT INTO '
      . $table
      . ' (`time`,'
      . implode(',', $columns)
      . ') VALUES '
      . implode(',', $rows)
      . self::getOnDuplicateKeyUpdateClause($columns)
    );
  }

  /** Finishes a database update. */
  public function finishUpdate(): void {
    $this->updateWindRecords();

    $this->aggregateTimeSeries(
      'past_quarter_hours',
      'past_days',
      'DATE_SUB(DATE_SUB(time,INTERVAL MINUTE(time) MINUTE),INTERVAL HOUR(time) HOUR)'
    );

    $this->aggregateTimeSeries(
      'past_days',
      'past_weeks',
      'DATE_SUB(time,INTERVAL WEEKDAY(time) DAY)'
    );

    $this->aggregateTimeSeries(
      'past_days',
      'past_years',
      'DATE_SUB(DATE_SUB(time,INTERVAL (DAYOFMONTH(time) - 1) DAY),INTERVAL (MONTH(time) - 1) MONTH)'
    );

    // deleted last, so that the aggregates are built from everything the
    // quarter hours hold. The historic import writes years of them in one go,
    // and deleting first would throw that away before it had been rolled up.
    $this->deleteOldQuarterHours();
  }

  /** Deletes old quarter-hourly data to reduce the size of the database. */
  private function deleteOldQuarterHours(): void {
    $this->connection->query(
      'DELETE FROM past_quarter_hours WHERE time<"'
      . $this->getEarliestQuarterHour()
      . '"'
    );
  }

  /** Updates the wind records. */
  private function updateWindRecords(): void {
    // delete records for which wind estimates may have been revised
    $this->connection->query(
      'DELETE wind_records FROM wind_records INNER JOIN past_quarter_hours USING (time)'
    );

    $record = (float)$this->connection->query(
      'SELECT MAX(value) FROM wind_records'
    )->fetch_row()[0];

    $rows = $this->connection->query(
      'SELECT time,wind_onshore+wind_offshore AS value FROM past_quarter_hours ORDER BY time'
    );

    while ($row = $rows->fetch_assoc()) {
      if ((float)$row['value'] > $record) {
        $record = (float)$row['value'];

        $this->connection->query(
          'INSERT INTO wind_records (value,time) VALUES ('
          . $row['value']
          . ',"'
          . $row['time']
          . '")'
        );
      }
    }
  }

  /**
   * Aggregates a time series.
   *
   * @param string $sourceTable      The source table
   * @param string $destinationTable The destination table
   * @param string $timeExpression   The expression to group times
   */
  private function aggregateTimeSeries(
    string $sourceTable,
    string $destinationTable,
    string $timeExpression
  ): void {
    $columns = self::getColumns();

    $this->connection->query(
      'INSERT INTO '
      . $destinationTable
      . ' (`time`,'
      . implode(',', $columns)
      . ') SELECT '
      . $timeExpression
      . ' AS aggregated_time,'
      . self::getAveragesExpression($columns)
      . ' FROM '
      . $sourceTable
      . ' GROUP BY aggregated_time'
      . self::getOnDuplicateKeyUpdateClause($columns)
    );

    $this->connection->query(
      'INSERT INTO '
      . $destinationTable
      . ' (`time`,visits) SELECT '
      . $timeExpression
      . ' AS aggregated_time,SUM(visits) FROM '
      . $sourceTable
      . ' GROUP BY aggregated_time'
      . self::getOnDuplicateKeyUpdateClause(['visits'])
    );
  }

  /**
   * Returns a map from keys to values for the most recent row in a table.
   *
   * @param string $table The table
   */
  private function getLatestMap(string $table): array {
    $map = $this->connection->query(
      'SELECT * FROM ' . $table . ' ORDER BY time DESC LIMIT 1'
    )->fetch_assoc();

    // create a map of all zeroes for new instances with an empty database
    if ($map === null) {
      $map = array_fill_keys(self::getColumns(), '0');
      $map['time'] = '0000-00-00 00:00:00';
    }

    return $map;
  }

  /** Returns the list of database columns. */
  private static function getColumns(): array {
    return array_merge(
      Generation::KEYS,
      Pricing::KEYS,
      Emissions::KEYS,
      Visits::KEYS
    );
  }

  /**
   * Returns the expression for the averages for each of a set of columns.
   *
   * The averages are coalesced to zero because a period can legitimately
   * cover no rows at all: for the first couple of days after a new database
   * is set up, the past week and past year queries skip the only row there
   * is, and averaging an empty set yields null, which the typed properties
   * of a Datum reject.
   *
   * @param array $columns The columns
   */
  private static function getAveragesExpression(array $columns): string {
    return implode(
      ',',
      array_map(
        fn ($column) => 'COALESCE(AVG(' . $column . '),0) AS ' . $column,
        $columns
      )
    );
  }

  /**
   * Returns an ON DUPLICATE KEY UPDATE clause.
   *
   * @param array $columns The columns
   */
  private static function getOnDuplicateKeyUpdateClause(
    array $columns
  ): string {
    return (
      ' ON DUPLICATE KEY UPDATE '
      . implode(
        ',',
        array_map(
          fn ($column) => $column . '=VALUES(' . $column . ')',
          $columns
        )
      )
    );
  }

  /**
   * Clears recorded errors for an action that completed successfully.
   *
   * @param string $action The action
   */
  public function clearErrors(string $action): void {
    $this->connection->query(
      'DELETE FROM errors WHERE action="'
      . $this->connection->real_escape_string($action)
      . '"'
    );
  }

  /**
   * Returns the count of occurrences of an error.
   *
   * @param string $action The action
   * @param string $error  The error
   */
  public function getErrorCount(string $action, string $error): int {
    $this->connection->query(
      'INSERT INTO errors (action,error,count) VALUES ("'
      . $this->connection->real_escape_string($action)
      . '","'
      . $this->connection->real_escape_string($error)
      . '",1) ON DUPLICATE KEY UPDATE count=count+1'
    );

    return (int)$this->connection->query(
      'SELECT count FROM errors WHERE action="'
      . $this->connection->real_escape_string($action)
      . '" AND error="'
      . $this->connection->real_escape_string($error)
      . '"'
    )->fetch_row()[0];
  }
}
