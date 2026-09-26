<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Sources;

/** Outputs the demand equation. */
class Equation {
  /**
   * Outputs the demand equation.
   *
   * @param Sources  $sources   The sources
   * @param string   $locale    The locale ('de' or 'en')
   * @param bool     $help      Whether to show the help
   * @param bool     $estimate  Whether the figures are estimated, marked so
   */
  public static function output(
    Sources  $sources,
    string   $locale,
    bool     $help = false,
    bool     $estimate = false
  ): void {
    $generation = round(Kind::Generation->get($sources), 1);
    $transfers = round(Kind::Transfers->get($sources), 1);
    // estimated figures are marked as the estimate is in the graphs, dashed
    $mark = fn (string $value) => $estimate ? '<span class="est">≈' . $value . '</span>' : $value;

?>
          <dl data-operator="<?= ($transfers < 0 ? '−' : '+') ?>">
            <dt><?= I18n::t('equation.demand', $locale) ?><?php if ($help) { ?> <span data-help="demand"></span><?php } ?></dt>
            <dd><?= $mark(Value::formatTotalPower($generation + $transfers, $locale)) ?><abbr>GW</abbr></dd>
            <dt><?= I18n::t('equation.generation', $locale) ?><?php if ($help) { ?> <span data-help="generation"></span><?php } ?></dt>
            <dd><?= $mark(Value::formatTotalPower($generation, $locale)) ?><abbr>GW</abbr></dd>
            <dt><?= I18n::t('equation.transfers', $locale) ?><?php if ($help) { ?> <span data-help="transfers"></span><?php } ?></dt>
            <dd><?= $mark(Value::formatTotalPower(abs($transfers), $locale)) ?><abbr>GW</abbr></dd>
          </dl>
<?php
  }
}
