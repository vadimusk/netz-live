<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;
use KateMorley\Grid\State\Generation;
use KateMorley\Grid\State\Interconnectors;
use KateMorley\Grid\State\Map;
use KateMorley\Grid\State\Storage;
use KateMorley\Grid\State\Types;
use KateMorley\Grid\UI\PieChart;

/** Outputs the latest data. */
class Latest {
  /**
   * Outputs the latest data.
   *
   * @param Datum        $latest The latest datum
   * @param array<Datum> $series The series to show in sparklines
   */
  public static function output(Datum $latest, array $series): void {
    $sparklines = new Sparklines($series);

    $demand = $latest->getTotal();

    self::outputTable(
      'fossils',
      'Fossil fuels',
      $latest->types->get(Types::FOSSILS),
      $latest,
      $sparklines,
      Datum::GENERATION,
      [Generation::GAS],
      $demand
    );

    self::outputTable(
      'renewables',
      'Renewables',
      $latest->types->get(Types::RENEWABLES),
      $latest,
      $sparklines,
      Datum::GENERATION,
      [Generation::SOLAR, Generation::WIND, Generation::HYDRO],
      $demand
    );

    self::outputTable(
      'others',
      'Other sources',
      $latest->types->get(Types::OTHERS),
      $latest,
      $sparklines,
      Datum::GENERATION,
      [Generation::NUCLEAR, Generation::BIOMASS],
      $demand
    );

    self::outputTable(
      'interconnectors transfers',
      'Interconnectors',
      $latest->interconnectors->getTotal(),
      $latest,
      $sparklines,
      Datum::TRANSFERS,
      array_keys(Interconnectors::KEYS),
      $demand
    );

    self::outputTable(
      'storage transfers',
      'Storage',
      $latest->storage->getTotal(),
      $latest,
      $sparklines,
      Datum::TRANSFERS,
      [Storage::PUMPED, 'battery'],
      $demand
    );
  }

  /**
   * Outputs a table.
   *
   * @param string        $class       The type class
   * @param string        $description The type description
   * @param float         $total       The type total
   * @param Datum         $latest      The latest datum
   * @param Sparklines    $sparklines  The Sparklines instance
   * @param int           $map         The map key
   * @param array<string> $keys        The source keys
   * @param float         $demand      The total demand
   */
  private static function outputTable(
    string     $class,
    string     $description,
    float      $total,
    Datum      $latest,
    Sparklines $sparklines,
    int        $map,
    array      $keys,
    float      $demand
  ): void {
?>
          <table class="<?= $class ?>">
            <thead>
<?php

    echo '              <tr><th></th><th>';
    echo $description;
    echo '</th><th>';
    echo Value::formatPower($total);
    echo '</th><th>';
    echo Value::formatPercentage($total / $demand);
    echo "</th></tr>\n";

?>
            </thead>
            <tbody>
<?php

    foreach ($keys as $key) {
      echo '              <tr><td class="';
      echo $key;
      echo '">';

      if ($key === 'battery') {
        echo '<svg></svg>';
      } else {
        $sparklines->output($key);
      }

      echo '<td>';

      if ($key === 'battery') {
        echo 'Batteries';
      } else {
        echo $latest->get($map)::KEYS[$key];
      }

      echo ' <span data-help="';
      echo $key;
      echo '"></span></td><td>';

      if ($key === 'battery') {
        echo '—';
      } else {
        echo Value::formatPower($latest->get($map)->get($key));
      }

      echo '</td><td>';

      if ($key === 'battery') {
        echo '—';
      } else {
        echo Value::formatPercentage($latest->get($map)->get($key) / $demand);
      }

      echo "</td></tr>\n";
    }

?>
            </tbody>
          </table>
<?php
  }
}
