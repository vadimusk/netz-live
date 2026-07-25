<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;

/** Outputs a panel. */
class Panel {
  /**
   * Outputs a panel.
   *
   * @param string $time       The time
   * @param Datum  $average    The average
   * @param array  $series     The series
   * @param Axes   $axes       The axes information
   * @param int    $timeStep   The time step
   * @param string $timeFormat The time format
   */
  public static function output(
    string $time,
    Datum  $average,
    array  $series,
    Axes   $axes,
    int    $timeStep,
    string $timeFormat
  ): void {
?>
          <div>
<?php Status::output($average, $time); ?>
          </div>
          <div>
<?php Equation::output($average->sources); ?>
          </div>
          <div>
            <?php PieChart::output($average->sources); ?>
          </div>
          <div>
            <div class="sources">
<?php Tables::output($average->sources); ?>
            </div>
          </div>
          <div>
            <h3>Price per MWh</h3>
            <?php Graph::Price->output($series, $axes, '£', '', $timeStep, $timeFormat, 2); ?>
          </div>
          <div>
            <h3>Emissions per kWh</h3>
            <?php Graph::Emissions->output($series, $axes, '', 'g', $timeStep, $timeFormat, 0); ?>
          </div>
          <div>
            <h3>Demand</h3>
            <?php Graph::Demand->output($series, $axes, '', 'GW', $timeStep, $timeFormat, 1); ?>
          </div>
          <div>
            <h3>Generation</h3>
            <?php Graph::Generation->output($series, $axes, '', 'GW', $timeStep, $timeFormat, 2); ?>
          </div>
          <div>
            <h3>Transfers</h3>
            <?php Graph::Transfers->output($series, $axes, '', 'GW', $timeStep, $timeFormat, 2); ?>
          </div>
<?php
  }
}
