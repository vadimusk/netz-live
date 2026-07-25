<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Source;
use KateMorley\Grid\State\Sources;

/** Outputs tables of sources. */
class Tables {
  /**
   * Outputs tables of sources.
   *
   * @param Sources $sources The sources
   */
  public static function output(Sources $sources): void {
?>
              <h3>Generation by type</h3>
              <table>
<?php

    self::rows($sources, [Kind::Fossils, Kind::Renewables, Kind::Others]);

?>
              </table>
              <h3>Generation by source</h3>
              <table>
<?php

    self::rows($sources, [
      ...Kind::Fossils->sources(),
      ...Kind::Renewables->sources(),
      ...Kind::Others->sources()
    ]);

?>
              </table>
              <h3>Interconnectors</h3>
              <table class="transfers">
<?php

    self::rows($sources, Kind::Interconnectors->sources());

?>
              </table>
              <h3>Storage</h3>
              <table class="transfers">
<?php

    self::rows($sources, Kind::Storage->sources());

?>
              </table>
<?php
  }

  /**
   * Outputs the rows of a table.
   *
   * @param Sources                   $sources        The sources
   * @param array<Kind>|array<Source> $kindsOrSources The kinds or sources
   */
  private static function rows(Sources $sources, array $kindsOrSources): void {
    foreach ($kindsOrSources as $kindOrSource) {
      if ($kindOrSource === Source::Battery) {
        continue;
      }

      echo '                <tr><td class="';
      echo $kindOrSource->value;
      echo '"></td><td>';
      echo $kindOrSource->describe();
      echo '</td><td>';
      echo Value::formatPower($kindOrSource->get($sources));
      echo '</td><td>';
      echo Value::formatPercentage($kindOrSource->get($sources) / $sources->sum());
      echo "</td></tr>\n";
    }
  }
}
