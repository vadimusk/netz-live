<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Sources;

/** Outputs the latest data. */
class Latest {
  /**
   * Outputs the latest data.
   *
   * @param Sources    $sources    The latest sources
   * @param Sparklines $sparklines The Sparklines instance
   * @param string     $locale     The locale ('de' or 'en')
   */
  public static function output(
    Sources    $sources,
    Sparklines $sparklines,
    string     $locale
  ): void {
    self::table($sources, $sparklines, Kind::Fossils, $locale);
    self::table($sources, $sparklines, Kind::Renewables, $locale);
    self::table($sources, $sparklines, Kind::Others, $locale);
    self::table($sources, $sparklines, Kind::Interconnectors, $locale);
    self::table($sources, $sparklines, Kind::Storage, $locale);
  }

  /**
   * Outputs a table.
   *
   * @param Sources    $sources    The latest sources
   * @param Sparklines $sparklines The Sparklines instance
   * @param Kind       $kind       The kind of power source
   * @param string     $locale     The locale ('de' or 'en')
   */
  private static function table(
    Sources    $sources,
    Sparklines $sparklines,
    Kind       $kind,
    string     $locale
  ): void {
?>
          <table class="<?= $kind->value ?>">
            <thead>
<?php

    echo '              <tr><th></th><th>';
    echo $kind->describe($locale);
    echo '</th><th>';
    echo Value::formatPower($kind->get($sources), $locale);
    echo '</th><th>';
    echo Value::formatPercentage($kind->get($sources) / $sources->sum(), $locale);
    echo "</th></tr>\n";

?>
            </thead>
            <tbody>
<?php

    foreach ($kind->sources() as $source) {
      echo '              <tr><td class="';
      echo $source->value;
      echo '">';

      $sparklines->output($source);
      echo '</td><td>';
      echo $source->describe($locale);
      echo ' <span data-help="';
      echo $source->value;
      echo '"></span></td><td>';
      echo Value::formatPower($source->get($sources), $locale);
      echo '</td><td>';
      echo Value::formatPercentage($source->get($sources) / $sources->sum(), $locale);

      echo "</td></tr>\n";
    }

?>
            </tbody>
          </table>
<?php
  }
}
