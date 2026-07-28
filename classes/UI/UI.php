<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Functions for outputting the user interface. */
class UI {
  /** The state. */
  private State $state;

  /** The graphs. */
  private Graphs $graphs;

  /**
   * Constructs a new instance.
   *
   * @param State $state The state
   */
  public function __construct(State $state) {
    $this->state = $state;
    $this->graphs = new Graphs($state);
  }

  /** Outputs the user interface. */
  public function output(): void {
    $stylesheetModified = filemtime(__DIR__ . '/../../public/grid.css');
    $javascriptModified = filemtime(__DIR__ . '/../../public/grid.js');

?>
<!DOCTYPE html>
<html lang="en-gb" data-version="<?= max($stylesheetModified, $javascriptModified) ?>">
  <head>
    <title>
      National Grid: Live
    </title>
    <meta name="description" content="Shows the live status of Great Britain’s electric power transmission network">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="fediverse:creator" content="@katemorley@hachyderm.io">
    <meta property="og:url" content="https://grid.iamkate.com/">
    <meta property="og:type" content="website">
    <meta property="og:title" content="National Grid: Live">
    <meta property="og:image" content="https://grid.iamkate.com/banner.png">
    <link rel="canonical" href="https://grid.iamkate.com/">
    <link rel="preload" href="proza-regular.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="proza-light.woff2" as="font" type="font/woff2" crossorigin>
    <link rel="stylesheet" href="grid.css?<?= $stylesheetModified ?>" type="text/css">
    <link rel="icon" href="favicon.png" type="image/png">
    <link rel="icon" href="favicon.svg?<?= floor(time() / 300) ?>" type="image/svg+xml">
    <script src="grid.js?<?= $javascriptModified ?>" defer></script>
  </head>
  <body>
    <header>
      <p>
        A project by <a href="https://iamkate.com/">Kate Morley</a> <a href="https://ko-fi.com/katemorley">Donate</a>
      </p>
      <h1>
        National Grid: Live
      </h1>
      <p>
        <span>The National Grid is the electric power</span> <span>transmission network for Great Britain</span>
      </p>
    </header>
    <main>
      <div id="live">
        <div>
<?php Status::output($this->state->latest, Status::time($this->state->time), true); ?>
<?php Equation::output($this->state->latest->sources, true); ?>
        </div>
        <div class="sources">
<?php Latest::output($this->state->latest->sources, new Sparklines($this->state->daySeries)); ?>
        </div>
        <?php PieChart::output($this->state->latest->sources); ?>
      </div>
<?php $this->historical(); ?>
    </main>
    <footer>
<?php Transition::output($this->state); ?>
<?php About::output($this->state); ?>
    </footer>
    <dialog>
      <h2>Help</h2>
      <form method="dialog"><button><svg viewBox="0 0 30 30"><path d="M6,6 24,24"/><path d="M6,24 24,6"/></svg></button></form>
      <div></div>
    </dialog>
  </body>
</html>
<?php
  }

  /** Outputs the historical data section. */
  public function historical(): void {
?>
      <section>
        <div role="tablist">
          <h2 id="tab-day" role="tab" aria-controls="tab-panel-day" aria-selected="true"><span>Past </span>day</h2>
          <h2 id="tab-week" role="tab" aria-controls="tab-panel-week" aria-selected="false"><span>Past </span>week</h2>
          <h2 id="tab-year" role="tab" aria-controls="tab-panel-year" aria-selected="false"><span>Past </span>year</h2>
          <h2 id="tab-all" role="tab" aria-controls="tab-panel-all" aria-selected="false">All<span> time</span></h2>
        </div>
<?php

    foreach (Period::cases() as $period) {
      $this->panel($period);
    }

?>
      </section>
<?php
  }

  /**
   * Outputs a historical data panel.
   *
   * @param Period $period The time period
   */
  public function panel(Period $period): void {
    $datum = $period->datum($this->state);

?>
        <div id="tab-panel-<?= $period->id() ?>" role="tabpanel" aria-labelledby="tab-<?= $period->id() ?>" tabindex="0">
<?php Status::output($datum, $period->describe()); ?>
<?php Equation::output($datum->sources); ?>
          <div>
            <?php PieChart::output($datum->sources); ?>
          </div>
          <div>
            <div class="sources">
<?php Tables::output($datum->sources); ?>
            </div>
          </div>
<?php

    foreach (Graph::cases() as $graph) {
      if ($graph !== Graph::Visits) {
        echo '          ';
        $this->graphs->output($graph, $period);
      }
    }

?>
        </div>
<?php
  }
}
