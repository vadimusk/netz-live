<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Sources;

/** Outputs the demand equation. */
class Equation {
  /**
   * Outputs the demand equation.
   *
   * @param Sources $sources The sources
   * @param bool    $help    Whether to show the help
   */
  public static function output(Sources $sources, bool $help = false): void {
    $generation = round(Kind::Generation->get($sources), 1);
    $transfers = round(Kind::Transfers->get($sources), 1);

?>
          <dl data-operator="<?= ($transfers < 0 ? '−' : '+') ?>">
            <dt>Demand<?php if ($help) { ?> <span data-help="demand"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($generation + $transfers) ?><abbr>GW</abbr></dd>
            <dt>Generation<?php if ($help) { ?> <span data-help="generation"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($generation) ?><abbr>GW</abbr></dd>
            <dt>Transfers<?php if ($help) { ?> <span data-help="transfers"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower(abs($transfers)) ?><abbr>GW</abbr></dd>
          </dl>
<?php
  }
}
