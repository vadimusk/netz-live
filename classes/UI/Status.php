<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\Datum;

/** Outputs the status. */
class Status {
  /**
   * Outputs the status.
   *
   * @param Datum  $datum The datum
   * @param string $time  The time
   * @param bool   $help  Whether to show the help
   */
  public static function output(
    Datum $datum,
    string $time,
    bool $help = false
  ): void {
?>
          <dl>
            <dt>Time<?php if ($help) { ?> <span data-help="time"></span><?php } ?></dt>
            <dd><?= $time ?></dd>
            <dt>Price<?php if ($help) { ?>  <span data-help="price"></span><?php } ?></dt>
            <dd><?= Value::formatPrice($datum->price) ?><abbr>/MWh</abbr></dd>
            <dt>Emissions<?php if ($help) { ?> <span data-help="emissions"></span><?php } ?></dt>
            <dd><?= (int)$datum->emissions ?><abbr>g/kWh</abbr></dd>
          </dl>
<?php
  }

  /**
   * Formats a time as HTML and returns it.
   *
   * @param int $time The time
   */
  public static function time(int $time): string {
    return (
      '<time datetime="'
      . gmdate('Y-m-d\TH:i:s\Z', $time)
      . '">'
      . date('g:i', $time)
      . '<abbr>'
      . date('a', $time)
      . '</abbr></time>'
    );
  }
}
