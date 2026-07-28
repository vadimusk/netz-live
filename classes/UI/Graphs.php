<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Functions for outputting graphs. */
class Graphs {
  /** The height of graphs. */
  private const HEIGHT = 250;

  /** The step size on the visits graph. */
  private const VISITS_STEP = 100000;

  /** The state. */
  private State $state;

  /**
   * The minimum values for each graph.
   *
   * @var array<int>
   */
  private array $minimums = [];

  /**
   * The maximum values for each graph.
   *
   * @var array<int>
   */
  private array $maximums = [];

  /**
   * The step sizes for each graph.
   *
   * @var array<int>
   */
  private array $steps = [];

  /**
   * Constructs a new instance.
   *
   * @param State $state The state
   */
  public function __construct(State $state) {
    $this->state = $state;

    foreach (Graph::cases() as $graph) {
      $minimums = [0];
      $maximums = [];

      foreach (Period::cases() as $period) {
        foreach ($period->series($state) as $datum) {
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

      $this->setAxis($graph, $minimum, $maximum, $step);
    }

    $this->setAxis(Graph::Visits, 0, max(...array_map(
      fn ($datum) => Graph::Visits->get($datum)[0],
      $state->yearSeries
    )), self::VISITS_STEP);
  }

  /**
   * Sets the axis details for a graph.
   *
   * @param Graph $graph   The graph
   * @param float $minimum The minimum
   * @param float $maximum The maximum
   * @param int   $step    The step size
   */
  private function setAxis(
    Graph $graph,
    float $minimum,
    float $maximum,
    int   $step
  ): void {
    $this->minimums[$graph->value] = $step * floor($minimum / $step);
    $this->maximums[$graph->value] = $step * ceil($maximum  / $step);
    $this->steps[$graph->value]    = $step;
  }

  /**
   * Returns the minimum for a graph.
   *
   * @param Graph $graph The graph
   */
  private function getMinimum(Graph $graph): int {
    return $this->minimums[$graph->value];
  }

  /**
   * Returns the maximum for a graph.
   *
   * @param Graph $graph The graph
   */
  private function getMaximum(Graph $graph): int {
    return $this->maximums[$graph->value];
  }

  /**
   * Returns the step size for a graph.
   *
   * @param Graph $graph The graph
   */
  private function getStep(Graph $graph): int {
    return $this->steps[$graph->value];
  }

  /**
   * Outputs a graph.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   */
  public function output(Graph $graph, Period $period): void {
    echo '<div><h3>';
    echo $graph->describe();
    echo '</h3><div class="graph" data-prefix="';
    echo $graph->prefix();
    echo '" data-suffix="';
    echo $graph->suffix();
    echo '"';

    if ($graph === Graph::Transfers) {
      echo ' data-transfers="true"';
    }

    echo '>';

    $this->outputValueAxis($graph);
    $this->outputTimeAxis($period);

    echo '<svg viewBox="-0.5 -1 ';
    echo count($period->series($this->state));
    echo ' ';
    echo self::HEIGHT + 2;
    echo '" width="';
    echo count($period->series($this->state));
    echo '" height="';
    echo self::HEIGHT + 2;
    echo '" preserveAspectRatio="none">';

    $this->outputOverlay($graph, $period);
    $this->outputLines($graph, $period);

    echo "</svg></div></div>\n";
  }

  /**
   * Outputs the value axis.
   *
   * @param Graph $graph The graph
   */
  private function outputValueAxis(Graph $graph): void {
    echo '<div>';

    for (
      $label  = $this->getMaximum($graph);
      $label >= $this->getMinimum($graph);
      $label -= $this->getStep($graph)
    ) {
      echo '<div>';

      if ($label < 0) {
        echo '−';
      }

      echo $graph->prefix();
      echo number_format(abs($label));
      echo $graph->suffix();
      echo '</div><div';

      if ($label === 0) {
        echo ' class="axis"';
      }

      echo '></div>';
    }

    echo '</div>';
  }

  /**
   * Outputs the time axis.
   *
   * @param Period $period The time period
   */
  private function outputTimeAxis(Period $period): void {
    echo '<div>';

    $index = ceil($period->tickmarkInterval() / 2);

    foreach ($period->series($this->state) as $time => $_) {
      if ($index % $period->tickmarkInterval() === 0) {
        echo '<div>';
        echo $period->format($time);
        echo '</div>';
      }

      $index ++;
    }

    echo '</div>';
  }

  /**
   * Outputs the overlay.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   */
  private function outputOverlay(Graph $graph, Period $period): void {
    echo '<g transform="translate(-0.5 0)">';

    $index = 0;

    foreach ($period->series($this->state) as $time => $datum) {
      echo '<rect x="';
      echo $index;
      echo '" y="0" width="1" height="';
      echo self::HEIGHT;
      echo '" data-time="';
      echo $period->format($time);
      echo '" data-values="';
      echo implode(' ', array_map(
        fn ($value) => number_format($value, $graph->decimalPlaces()),
        $graph->get($datum)
      ));
      echo '"/>';

      $index ++;
    }

    echo '</g>';
  }

  /**
   * Outputs the lines.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   */
  private function outputLines(Graph $graph, Period $period): void {
    $minimum = $this->getMinimum($graph);
    $range   = $this->getMaximum($graph) - $minimum;

    $lines = array_map(
      fn ($_) => new Line(self::HEIGHT, $minimum, $range),
      $graph->classes()
    );

    foreach ($period->series($this->state) as $datum) {
      foreach ($graph->get($datum) as $key => $value) {
        $lines[$key]->add($value);
      }
    }

    foreach ($graph->classes() as $index => $class) {
      $lines[$index]->output($class);
    }
  }
}
