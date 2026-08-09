<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Functions for outputting the user interface. */
class UI {
  /** The state. */
  private State $state;

  /** The locale ('de' or 'en'). */
  private string $locale;

  /** The graphs. */
  private Graphs $graphs;

  /**
   * Constructs a new instance.
   *
   * @param State  $state  The state
   * @param string $locale The locale ('de' or 'en')
   */
  public function __construct(State $state, string $locale) {
    $this->state  = $state;
    $this->locale = $locale;
    $this->graphs = new Graphs($state);
  }

  /** Outputs the user interface. */
  public function output(): void {
    $stylesheetModified = filemtime(__DIR__ . '/../../public/grid.css');
    $javascriptModified = filemtime(__DIR__ . '/../../public/grid.js');

    $locale       = $this->locale;
    $canonicalUrl = 'https://netz-live.example/' . ($locale === 'en' ? 'en/' : '');

?>
<!DOCTYPE html>
<html lang="<?= $locale ?>" data-version="<?= max($stylesheetModified, $javascriptModified) ?>">
  <head>
    <title>
      <?= I18n::t('site.title', $locale) ?>
    </title>
    <meta name="description" content="<?= I18n::t('site.description', $locale) ?>">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta property="og:url" content="<?= $canonicalUrl ?>">
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= I18n::t('site.title', $locale) ?>">
    <meta property="og:locale" content="<?= $locale === 'de' ? 'de_DE' : 'en_GB' ?>">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="alternate" hreflang="de" href="https://netz-live.example/">
    <link rel="alternate" hreflang="en" href="https://netz-live.example/en/">
    <link rel="stylesheet" href="<?= $locale === 'de' ? '' : '../' ?>grid.css?<?= $stylesheetModified ?>" type="text/css">
    <link rel="icon" href="<?= $locale === 'de' ? '' : '../' ?>favicon.png" type="image/png">
    <link rel="icon" href="<?= $locale === 'de' ? '' : '../' ?>favicon.svg?<?= floor(time() / 300) ?>" type="image/svg+xml">
    <script src="<?= $locale === 'de' ? '' : '../' ?>grid.js?<?= $javascriptModified ?>" defer></script>
  </head>
  <body>
    <header>
      <p>
        <?= I18n::t('site.credit', $locale) ?> <a href="<?= I18n::t('site.switchPath', $locale) ?>" hreflang="<?= $locale === 'de' ? 'en' : 'de' ?>"><?= I18n::t('site.switchLabel', $locale) ?></a>
      </p>
      <h1>
        <?= I18n::t('site.title', $locale) ?>
      </h1>
      <p>
        <span><?= I18n::t('site.tagline1', $locale) ?></span> <span><?= I18n::t('site.tagline2', $locale) ?></span>
      </p>
    </header>
    <main>
      <div id="live">
        <div>
<?php Status::output($this->state->latest, Status::time($this->state->time, $locale), $locale, true); ?>
<?php Equation::output($this->state->latest->sources, $locale, true); ?>
        </div>
        <div class="sources">
<?php Latest::output($this->state->latest->sources, new Sparklines($this->state->daySeries), $locale); ?>
        </div>
        <?php PieChart::output($this->state->latest->sources, $locale); ?>
      </div>
<?php $this->historical(); ?>
    </main>
    <footer>
<?php Transition::output($this->state, $locale); ?>
<?php About::output($this->state, $locale); ?>
    </footer>
    <dialog>
      <h2><?= I18n::t('dialog.help', $locale) ?></h2>
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
          <h2 id="tab-day" role="tab" aria-controls="tab-panel-day" aria-selected="true"><?= Period::Day->describeHtml($this->locale) ?></h2>
          <h2 id="tab-week" role="tab" aria-controls="tab-panel-week" aria-selected="false"><?= Period::Week->describeHtml($this->locale) ?></h2>
          <h2 id="tab-year" role="tab" aria-controls="tab-panel-year" aria-selected="false"><?= Period::Year->describeHtml($this->locale) ?></h2>
          <h2 id="tab-all" role="tab" aria-controls="tab-panel-all" aria-selected="false"><?= Period::All->describeHtml($this->locale) ?></h2>
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
<?php Status::output($datum, $period->describe($this->locale), $this->locale); ?>
<?php Equation::output($datum->sources, $this->locale); ?>
          <div>
            <?php PieChart::output($datum->sources, $this->locale); ?>
          </div>
          <div>
            <div class="sources">
<?php Tables::output($datum->sources, $this->locale); ?>
            </div>
          </div>
<?php

    foreach (Graph::cases() as $graph) {
      if ($graph !== Graph::Visits) {
        echo '          ';
        $this->graphs->output($graph, $period, $this->locale);
      }
    }

?>
        </div>
<?php
  }
}
