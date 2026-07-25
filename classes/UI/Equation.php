<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Demand;

/** Outputs the demand equation. */
class Equation {
  /**
   * Outputs the demand equation.
   *
   * @param Datum $datum The datum
   * @param bool  $help  Whether to show the help
   */
  public static function output(Datum $datum, bool $help = false): void {
    $transfers = $datum->demand->get(Demand::TRANSFERS);

?>
          <dl data-operator="<?= ($transfers < 0 ? '−' : '+') ?>">
            <dt>Demand<?php if ($help) { ?> <span data-help="demand"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($datum->demand->get(Demand::DEMAND)) ?><abbr>GW</abbr></dd>
            <dt>Generation<?php if ($help) { ?> <span data-help="generation"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($datum->demand->getGeneration()) ?><abbr>GW</abbr></dd>
            <dt>Transfers<?php if ($help) { ?> <span data-help="transfers"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower(abs($transfers)) ?><abbr>GW</abbr></dd>
          </dl>
<?php
  }
}
