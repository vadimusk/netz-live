<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Represents graph axes. */
class Axes {
  private const VISITS_STEP = 100000;

  private array $minimums = [];
  private array $maximums = [];
  private array $steps    = [];

  /**
   * Constructs a new instance.
   *
   * @param State $state The state
   */
  public function __construct(State $state) {
    foreach (Graph::cases() as $graph) {
      $minimums = [0];
      $maximums = [];

      foreach ([
        $state->pastDaySeries,
        $state->pastWeekSeries,
        $state->pastYearSeries,
        $state->allTimeSeries
      ] as $series) {
        foreach ($series as $datum) {
          $minimums[] = min($graph->get($datum));
          $maximums[] = max($graph->get($datum));
        }
      }

      $minimum = min($minimums);
      $maximum = max($maximums);
      $range   = $maximum - $minimum;

      if ($range > 2000) {
        $step = 500;
      } elseif ($range > 1000) {
        $step = 200;
      } elseif ($range > 500) {
        $step = 100;
      } elseif ($range > 200) {
        $step = 50;
      } elseif ($range > 100) {
        $step = 20;
      } elseif ($range > 50) {
        $step = 10;
      } elseif ($range > 20) {
        $step = 5;
      } elseif ($range > 10) {
        $step = 2;
      } else {
        $step = 1;
      }

      $this->setGraphAxis($graph, $minimum, $maximum, $step);
    }

    $this->setGraphAxis(
      Graph::Visits,
      0,
      max(...array_map(
        fn ($datum) => Graph::Visits->get($datum)[0],
        $state->pastYearSeries
      )),
      self::VISITS_STEP
    );
  }

  /**
   * Sets the axis details for a graph.
   *
   * @param Graph $graph   The graph
   * @param float $minimum The minimum
   * @param float $maximum The maximum
   * @param int   $step    The step size
   */
  private function setGraphAxis(
    Graph $graph,
    float $minimum,
    float $maximum,
    int   $step
  ): void {
    $this->minimums[$graph->value] = $step * floor($minimum / $step);
    $this->maximums[$graph->value] = $step * ceil($maximum  / $step);
    $this->steps[$graph->value] = $step;
  }

  /**
   * Returns the minimum for a graph.
   *
   * @param Graph $graph The graph
   */
  public function getMinimum(Graph $graph): int {
    return $this->minimums[$graph->value];
  }

  /**
   * Returns the maximum for a graph.
   *
   * @param Graph $graph The graph
   */
  public function getMaximum(Graph $graph): int {
    return $this->maximums[$graph->value];
  }

  /**
   * Returns the step size for a graph.
   *
   * @param Graph $graph The graph
   */
  public function getStep(Graph $graph): int {
    return $this->steps[$graph->value];
  }
}
