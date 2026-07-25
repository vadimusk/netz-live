<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Source;
use KateMorley\Grid\State\Sources;

/** Outputs the latest data. */
class Latest {
  /**
   * Outputs the latest data.
   *
   * @param Sources    $sources    The latest sources
   * @param Sparklines $sparklines The Sparklines instance
   */
  public static function output(Sources $sources, Sparklines $sparklines): void {
    self::table($sources, $sparklines, Kind::Fossils);
    self::table($sources, $sparklines, Kind::Renewables);
    self::table($sources, $sparklines, Kind::Others);
    self::table($sources, $sparklines, Kind::Interconnectors);
    self::table($sources, $sparklines, Kind::Storage);
  }

  /**
   * Outputs a table.
   *
   * @param Sources    $sources    The latest sources
   * @param Sparklines $sparklines The Sparklines instance
   * @param Kind       $kind       The kind of power source
   */
  private static function table(
    Sources    $sources,
    Sparklines $sparklines,
    Kind       $kind,
  ): void {
?>
          <table class="<?= $kind->value ?>">
            <thead>
<?php

    echo '              <tr><th></th><th>';
    echo $kind->describe();
    echo '</th><th>';
    echo Value::formatPower($kind->get($sources));
    echo '</th><th>';
    echo Value::formatPercentage($kind->get($sources) / $sources->sum());
    echo "</th></tr>\n";

?>
            </thead>
            <tbody>
<?php

    foreach ($kind->sources() as $source) {
      if ($source === Source::Coal) {
        continue;
      }

      echo '              <tr><td class="';
      echo $source->value;
      echo '">';

      if ($source === Source::Battery) {
        echo '<svg></svg></td><td>Battery <span data-help="battery"></span></td><td>—</td><td>—';
      } else {
        $sparklines->output($source);
        echo '</td><td>';
        echo $source->describe();
        echo ' <span data-help="';
        echo $source->value;
        echo '"></span></td><td>';
        echo Value::formatPower($source->get($sources));
        echo '</td><td>';
        echo Value::formatPercentage($source->get($sources) / $sources->sum());
      }

      echo "</td></tr>\n";
    }

?>
            </tbody>
          </table>
<?php
  }
}
