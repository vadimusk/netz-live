<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Functions for outputting graphs. */
class Graphs {
  /** The height of graphs. */
  private const HEIGHT = 250;

  /** The fewest points a graph needs before it is worth drawing. */
  private const MINIMUM_POINTS = 4;

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

    // the minimums and maximums start out holding a zero because a series
    // can cover no data at all: until a new database has collected a couple
    // of days, the past week and past year series are empty
    foreach (Graph::cases() as $graph) {
      $minimums = [0];
      $maximums = [0];

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

    $visits = array_map(
      fn ($datum) => Graph::Visits->get($datum)[0],
      $state->yearSeries
    );

    $maximum = count($visits) === 0 ? 0 : max($visits);

    $this->setAxis(Graph::Visits, 0, $maximum, self::visitsStep($maximum));
  }

  /**
   * Returns the step size for the visits graph.
   *
   * Visits are the one series whose scale isn't set by the grid: a site can
   * see hundreds a week or hundreds of thousands, so the step is chosen to
   * leave four or five gridlines at whatever size the figures happen to be.
   * Upstream can hardcode a step because its traffic is known.
   *
   * @param float $maximum The largest value on the graph
   */
  private static function visitsStep(float $maximum): int {
    if ($maximum <= 0) {
      return 1;
    }

    $magnitude = 10 ** floor(log10($maximum / 4));

    foreach ([1, 2, 5] as $multiple) {
      if ($maximum / ($multiple * $magnitude) <= 5) {
        return (int)max(1, $multiple * $magnitude);
      }
    }

    return (int)max(1, 10 * $magnitude);
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

    // A series that never leaves zero gives the axis no range at all, and
    // plotting a point on it would divide by that range. The visits stay at
    // zero unless Cloudflare analytics are configured, so the graph is left
    // with a single step of headroom to draw a flat line along the bottom.
    if ($this->maximums[$graph->value] == $this->minimums[$graph->value]) {
      $this->maximums[$graph->value] += $step;
    }
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
   * Returns the series a graph is plotted from.
   *
   * Visits are the one series that starts empty. A new site has a year of zero
   * weeks behind its first visit, and plotting all of them draws a flat line
   * along the bottom with a single spike at the end, which reads as a broken
   * graph rather than a young one. Dropping the leading zeroes plots only the
   * weeks that were actually counted, and the graph grows into the full year
   * by itself.
   *
   * Only leading zeroes are dropped. A zero week between two counted ones is a
   * real week with no visitors and stays where it is.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   *
   * @return array<int, mixed>
   */
  private function series(Graph $graph, Period $period): array {
    $series = $period->series($this->state);

    if ($graph !== Graph::Visits) {
      return $series;
    }

    $offset = 0;

    foreach ($series as $datum) {
      if ($graph->get($datum)[0] > 0) {
        break;
      }

      $offset ++;
    }

    // the keys are timestamps, which the time axis and the background both
    // read, so they have to survive the slice
    return array_slice($series, $offset, null, true);
  }

  /**
   * Returns the predicted data drawn after a series.
   *
   * Only the past day carries a prediction, and only for the graphs the
   * forecast actually informs: generation, the demand it is balanced against,
   * and the carbon intensity computed from it. The price is settled a day ahead
   * and needs no estimate, and the transfers graph is left out because its
   * lines are per country: the prediction moves the borders only in total, so
   * drawing them one by one would show a split nobody forecast.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   *
   * @return array<Datum>
   */
  private function predicted(Graph $graph, Period $period): array {
    if (
      $period !== Period::Day
      || !in_array($graph, [Graph::Generation, Graph::Demand, Graph::Emissions], true)
    ) {
      return [];
    }

    return $this->state->predicted;
  }

  /**
   * Returns every point a graph is drawn across, measured and predicted alike.
   *
   * The axis, the background and the width of the drawing area all follow this
   * rather than the measured series, so that the dashed tail has somewhere to
   * be drawn and can be read off like the rest of the line.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   *
   * @return array<Datum>
   */
  private function points(Graph $graph, Period $period): array {
    return $this->series($graph, $period) + $this->predicted($graph, $period);
  }

  /**
   * Returns whether a graph has enough points to be worth drawing.
   *
   * Below a few points there is no shape to see, and for visits the sentence
   * above the graph already gives the figure, so an almost empty graph is left
   * out rather than drawn. This is the same reasoning that picks the wording of
   * that sentence: a site that just went up should read as new, not as one
   * nobody goes to.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   */
  public function hasShape(Graph $graph, Period $period): bool {
    return count($this->series($graph, $period)) >= self::MINIMUM_POINTS;
  }

  /**
   * Outputs a graph.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   * @param string $locale The locale ('de' or 'en')
   */
  public function output(Graph $graph, Period $period, string $locale): void {
    echo '<div><h3>';
    echo $graph->describe($locale);
    echo '</h3><div class="graph" data-prefix="';
    echo $graph->prefix();
    echo '" data-suffix="';
    echo $graph->suffix();
    echo '"';

    if ($graph === Graph::Transfers) {
      echo ' data-transfers="true"';
    }

    echo '>';

    $this->outputValueAxis($graph, $locale);
    $this->outputTimeAxis($graph, $period, $locale);

    echo '<svg viewBox="-0.5 -1 ';
    echo count($this->points($graph, $period));
    echo ' ';
    echo self::HEIGHT + 2;
    echo '" width="';
    echo count($this->points($graph, $period));
    echo '" height="';
    echo self::HEIGHT + 2;
    echo '" preserveAspectRatio="none">';

    $this->outputBackground($graph, $period, $locale);
    $this->outputLines($graph, $period);

    echo "</svg></div></div>\n";
  }

  /**
   * Outputs the value axis.
   *
   * @param Graph  $graph  The graph
   * @param string $locale The locale ('de' or 'en')
   */
  private function outputValueAxis(Graph $graph, string $locale): void {
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
      echo I18n::number(abs($label), 0, $locale);
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
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   * @param string $locale The locale ('de' or 'en')
   */
  private function outputTimeAxis(Graph $graph, Period $period, string $locale): void {
    echo '<div>';

    $index = ceil($period->tickmarkInterval() / 2);

    foreach ($this->points($graph, $period) as $time => $_) {
      if ($index % $period->tickmarkInterval() === 0) {
        echo '<div>';
        echo $period->format($time, $locale);
        echo '</div>';
      }

      $index ++;
    }

    echo '</div>';
  }

  /**
   * Outputs the background.
   *
   * @param Graph  $graph  The graph
   * @param Period $period The time period
   * @param string $locale The locale ('de' or 'en')
   */
  private function outputBackground(Graph $graph, Period $period, string $locale): void {
    echo '<g transform="translate(-0.5 -1) scale(1 ';
    echo self::HEIGHT + 2;
    echo ')">';

    $index = 0;

    foreach ($this->points($graph, $period) as $time => $datum) {
      echo '<rect x="';
      echo $index;
      echo '" y="0" width="1" height="1" data-time="';
      echo $period->format($time, $locale);
      echo '" data-values="';
      echo implode(' ', array_map(
        fn ($value) => I18n::number($value, $graph->decimalPlaces(), $locale),
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
    $range   = max(1, $this->getMaximum($graph) - $minimum);

    $levels = $graph->levels();

    // Where a graph has levels, the line takes its colour from the band it
    // passes through. The bands are drawn as stacked full-width rectangles
    // and the line itself becomes a mask over them, so a single line changes
    // colour partway along rather than being one flat colour.
    if ($levels !== null) {
      echo '<g mask="url(#';
      echo $period->id();
      echo ')">';

      foreach ($levels as $class => $minimumLevel) {
        echo '<rect class="';
        echo $class;
        echo '" x="0" y="-1" width="';
        echo count($this->points($graph, $period));
        echo '" height="';

        if ($minimumLevel === $minimum) {
          echo self::HEIGHT + 2;
        } else {
          echo round((1 - $minimumLevel / $range) * self::HEIGHT) + 1;
        }

        echo '"/>';
      }

      echo '</g><mask id="';
      echo $period->id();
      echo '" mask-type="alpha">';
    }

    $lines = array_map(
      fn ($_) => new Line(self::HEIGHT, $minimum, $range),
      $graph->classes()
    );

    $series = $this->series($graph, $period);

    foreach ($series as $datum) {
      foreach ($graph->get($datum) as $key => $value) {
        $lines[$key]->add($value);
      }
    }

    foreach ($graph->classes() as $index => $class) {
      $lines[$index]->output($class);
    }

    // The predicted quarter hours are drawn as their own dashed paths, laid
    // end to end with the measured ones. Each starts from the last measured
    // point rather than the first predicted one, so the two meet without a
    // gap, and none of the measured line is redrawn or replaced.
    $predicted = $this->predicted($graph, $period);

    if (count($predicted) !== 0 && count($series) !== 0) {
      $tails = array_map(
        fn ($_) => new Line(self::HEIGHT, $minimum, $range),
        $graph->classes()
      );

      foreach ($tails as $tail) {
        $tail->startAt(count($series) - 1);
      }

      foreach (array_merge([end($series)], array_values($predicted)) as $datum) {
        foreach ($graph->get($datum) as $key => $value) {
          $tails[$key]->add($value);
        }
      }

      foreach ($graph->classes() as $index => $class) {
        $tails[$index]->output($class . ' predicted');
      }
    }

    if ($levels !== null) {
      echo '</mask>';
    }
  }
}
