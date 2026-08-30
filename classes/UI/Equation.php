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
   * @param ?Sources $predicted The estimate for the quarter hour running now,
   *                             shown under each confirmed figure rather than
   *                             in place of it; null where there is none
   */
  public static function output(
    Sources  $sources,
    string   $locale,
    bool     $help = false,
    ?Sources $predicted = null
  ): void {
    $generation = round(Kind::Generation->get($sources), 1);
    $transfers = round(Kind::Transfers->get($sources), 1);

?>
          <dl data-operator="<?= ($transfers < 0 ? '−' : '+') ?>">
            <dt><?= I18n::t('equation.demand', $locale) ?><?php if ($help) { ?> <span data-help="demand"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($generation + $transfers, $locale) ?><abbr>GW</abbr><?= self::estimate($predicted, fn ($p) => Kind::Generation->get($p) + Kind::Transfers->get($p), $locale) ?></dd>
            <dt><?= I18n::t('equation.generation', $locale) ?><?php if ($help) { ?> <span data-help="generation"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower($generation, $locale) ?><abbr>GW</abbr><?= self::estimate($predicted, fn ($p) => Kind::Generation->get($p), $locale) ?></dd>
            <dt><?= I18n::t('equation.transfers', $locale) ?><?php if ($help) { ?> <span data-help="transfers"></span><?php } ?></dt>
            <dd><?= Value::formatTotalPower(abs($transfers), $locale) ?><abbr>GW</abbr><?= self::estimate($predicted, fn ($p) => abs(Kind::Transfers->get($p)), $locale) ?></dd>
          </dl>
<?php
  }

  /**
   * Returns the estimate shown under a figure, or an empty string where there
   * is nothing to estimate.
   *
   * @param ?Sources $predicted The predicted sources
   * @param callable $get       Returns the figure from a set of sources
   * @param string   $locale    The locale ('de' or 'en')
   */
  private static function estimate(
    ?Sources $predicted,
    callable $get,
    string   $locale
  ): string {
    if ($predicted === null) {
      return '';
    }

    return (
      '<span class="estimate">'
      . I18n::t(
        'status.estimate',
        $locale,
        [Value::formatTotalPower(round($get($predicted), 1), $locale) . 'GW']
      )
      . '</span>'
    );
  }
}
