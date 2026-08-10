<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Kind;
use KateMorley\Grid\State\Source;
use KateMorley\Grid\State\Sources;

/** Outputs a pie chart. */
class PieChart {
  private const OUTER_RADIUS = 0.75;
  private const INNER_RADIUS = 0.50;

  /**
   * Outputs a pie chart.
   *
   * @param Sources $sources The sources
   * @param string  $locale  The locale ('de' or 'en')
   */
  public static function output(Sources $sources, string $locale): void {
    $generation = Kind::Generation->get($sources);
    $demand     = $generation + Kind::Transfers->get($sources);

    $generationPower      = Value::formatTotalPower($generation, $locale);
    $generationPercentage = Value::formatShare($generation, $demand, $locale);

    echo '<div class="pie-chart"><div><div>';
    echo I18n::t('kind.generation', $locale);
    echo '</div><div class="generation"></div><div><span>';
    echo $generationPower;
    echo '</span>GW</div><div><span>';
    echo $generationPercentage;
    echo '</span>%</div></div><svg viewBox="-1 -1 2 2" data-power="';
    echo $generationPower;
    echo '" data-percentage="';
    echo $generationPercentage;
    echo '">';

    self::outputRing(
      $sources,
      Kind::Generation->sources(),
      self::OUTER_RADIUS,
      1,
      $locale
    );

    self::outputRing(
      $sources,
      [Kind::Fossils, Kind::Renewables, Kind::Others],
      self::INNER_RADIUS,
      self::OUTER_RADIUS,
      $locale
    );

    echo "</svg></div>\n";
  }

  /**
   * Outputs a ring.
   *
   * @param Sources                   $sources        The sources
   * @param array<Kind>|array<Source> $kindsOrSources The kinds or sources
   * @param float                     $innerRadius    The inner radius
   * @param float                     $outerRadius    The outer radius
   * @param string                    $locale         The locale ('de' or 'en')
   */
  private static function outputRing(
    Sources $sources,
    array   $kindsOrSources,
    float   $innerRadius,
    float   $outerRadius,
    string  $locale
  ): void {
    $offset = 0;

    foreach ($kindsOrSources as $kindOrSource) {
      $power = $kindOrSource->get($sources);

      if ($power > 0) {
        $fraction = Value::share($power, Kind::Generation->get($sources));

        self::outputArc(
          $kindOrSource->value,
          Value::formatPower($power, $locale),
          Value::formatShare($power, $sources->sum(), $locale),
          $fraction,
          $offset,
          $innerRadius,
          $outerRadius
        );

        $offset += $fraction;
      }
    }
  }

  /**
   * Outputs an arc.
   *
   * @param string $source      The source
   * @param string $power       The power
   * @param string $percentage  The percentage
   * @param float  $faction     The fraction of the circle
   * @param float  $offset      The faction offset
   * @param float  $innerRadius The inner radius
   * @param float  $outerRadius The outer radius
   */
  private static function outputArc(
    string $source,
    string $power,
    string $percentage,
    float  $faction,
    float  $offset,
    float  $innerRadius,
    float  $outerRadius
  ): void {
    echo '<path class="';
    echo $source;
    echo '" d="M';
    self::outputArcPoint($offset, $outerRadius);
    echo 'A';
    echo $outerRadius;
    echo ',';
    echo $outerRadius;
    echo ' 0 ';
    echo ($faction < 0.5 ? 0 : 1);
    echo ' 1 ';
    self::outputArcPoint($offset + $faction, $outerRadius);
    echo 'L';
    self::outputArcPoint($offset + $faction, $innerRadius);
    echo 'A';
    echo $innerRadius;
    echo ',';
    echo $innerRadius;
    echo ' 0 ';
    echo ($faction < 0.5 ? 0 : 1);
    echo ' 0 ';
    self::outputArcPoint($offset, $innerRadius);
    echo 'z" data-power="';
    echo $power;
    echo '" data-percentage="';
    echo $percentage;
    echo '"/>';
  }

  /**
   * Outputs the co-ordinates of a point on an arc.
   *
   * @param float $faction The fraction of the circle
   * @param float $radius  The radius
   */
  private static function outputArcPoint(float $faction, float $radius): void {
    printf('%0.4f', $radius * sin($faction * 2 * M_PI));
    echo ',';
    printf('%0.4f', $radius * -cos($faction * 2 * M_PI));
  }
}
