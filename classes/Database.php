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
      $this->getYearlyVisits()
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

    $rows = $this->connection->query(
      'SELECT * FROM wind_records ORDER BY value DESC'
    );

    while ($row = $rows->fetch_assoc()) {
      $milestones[floor($row['value'])] = strtotime($row['time'] . ' UTC');
    }

    return $milestones;
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
   * Updates the generation data.
   *
   * @param array $data The generation data
   */
  public function updateGeneration(array $data): void {
    $this->updatePastTimeSeries('past_quarter_hours', Generation::KEYS, $data);
  }

  /**
   * Updates data, ignoring data prior to the earliest quarter hour or past
   * the latest quarter hour.
   *
   * @param array $columns The columns to update
   * @param array $data    The data
   */
  public function update(array $columns, array $data): void {
    $earliest = '"' . $this->getEarliestQuarterHour() . '"';
    $latest   = '"' . $this->getLatestQuarterHour() . '"';

    $this->updatePastTimeSeries('past_quarter_hours', $columns, array_filter(
      $data,
      fn ($datum) => $datum[0] >= $earliest && $datum[0] <= $latest
    ));
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
    $this->deleteOldQuarterHours();
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
