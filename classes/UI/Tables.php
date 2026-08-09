<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Sources;

/** Outputs tables of sources. */
class Tables {
  /**
   * Outputs tables of sources.
   *
   * @param Sources $sources The sources
   * @param string  $locale  The locale ('de' or 'en')
   */
  public static function output(Sources $sources, string $locale): void {
?>
              <h3><?= I18n::t('tables.byType', $locale) ?></h3>
              <table>
<?php

    self::rows($sources, [Kind::Fossils, Kind::Renewables, Kind::Others], $locale);

?>
              </table>
              <h3><?= I18n::t('tables.bySource', $locale) ?></h3>
              <table>
<?php

    self::rows($sources, [
      ...Kind::Fossils->sources(),
      ...Kind::Renewables->sources(),
      ...Kind::Others->sources()
    ], $locale);

?>
              </table>
              <h3><?= Kind::Interconnectors->describe($locale) ?></h3>
              <table class="transfers">
<?php

    self::rows($sources, Kind::Interconnectors->sources(), $locale);

?>
              </table>
              <h3><?= Kind::Storage->describe($locale) ?></h3>
              <table class="transfers">
<?php

    self::rows($sources, Kind::Storage->sources(), $locale);

?>
              </table>
<?php
  }

  /**
   * Outputs the rows of a table.
   *
   * @param Sources                   $sources        The sources
   * @param array<Kind>|array<Source> $kindsOrSources The kinds or sources
   * @param string                    $locale         The locale ('de' or 'en')
   */
  private static function rows(
    Sources $sources,
    array   $kindsOrSources,
    string  $locale
  ): void {
    foreach ($kindsOrSources as $kindOrSource) {
      echo '                <tr><td class="';
      echo $kindOrSource->value;
      echo '"></td><td>';
      echo $kindOrSource->describe($locale);
      echo '</td><td>';
      echo Value::formatPower($kindOrSource->get($sources), $locale);
      echo '</td><td>';
      echo Value::formatPercentage($kindOrSource->get($sources) / $sources->sum(), $locale);
      echo "</td></tr>\n";
    }
  }
}
