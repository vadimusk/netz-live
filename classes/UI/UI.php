<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Functions for outputting the user interface. */
class UI {
  /**
   * The site's base URL, used for the canonical and alternate language
   * links. Change this if the site moves to a different subdomain.
   */
  private const BASE_URL = 'https://netz.vterskov.de/';

  /**
   * Returns a sitemap entry for a page, cross-referencing the other language.
   *
   * @param string $path     The path below the base URL
   * @param string $modified The last modification time, in ISO 8601 format
   */
  public static function sitemapEntry(string $path, string $modified): string {
    return (
      '<url><loc>' . self::BASE_URL . $path . '</loc>'
      . '<lastmod>' . $modified . '</lastmod>'
      . '<changefreq>hourly</changefreq>'
      . '<xhtml:link rel="alternate" hreflang="de" href="' . self::BASE_URL . '"/>'
      . '<xhtml:link rel="alternate" hreflang="en" href="' . self::BASE_URL . 'en/"/>'
      . '<xhtml:link rel="alternate" hreflang="x-default" href="' . self::BASE_URL . '"/>'
      . '</url>'
    );
  }

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
    $canonicalUrl = self::BASE_URL . ($locale === 'en' ? 'en/' : '');

    // the English page lives a directory down, so its shared assets, which
    // are all at the site root, are one level up
    $root = ($locale === 'en' ? '../' : '');

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
    <meta property="og:description" content="<?= I18n::t('site.description', $locale) ?>">
    <meta property="og:locale" content="<?= $locale === 'de' ? 'de_DE' : 'en_GB' ?>">
    <meta property="og:image" content="<?= self::BASE_URL ?>banner-<?= $locale ?>.png">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
    <link rel="canonical" href="<?= $canonicalUrl ?>">
    <link rel="alternate" hreflang="de" href="<?= self::BASE_URL ?>">
    <link rel="alternate" hreflang="en" href="<?= self::BASE_URL ?>en/">
    <link rel="alternate" hreflang="x-default" href="<?= self::BASE_URL ?>">
    <link rel="stylesheet" href="<?= $root ?>grid.css?<?= $stylesheetModified ?>" type="text/css">
    <link rel="icon" href="<?= $root ?>favicon.png" type="image/png">
    <link rel="icon" href="<?= $root ?>favicon.svg?<?= floor(time() / 300) ?>" type="image/svg+xml">
    <link rel="apple-touch-icon" href="<?= $root ?>apple-touch-icon.png">
    <script src="<?= $root ?>grid.js?<?= $javascriptModified ?>" defer></script>
    <script type="application/ld+json"><?= json_encode([
      '@context'    => 'https://schema.org',
      '@type'       => 'Dataset',
      'name'        => I18n::t('site.title', $locale),
      'description' => I18n::t('site.description', $locale),
      'url'         => $canonicalUrl,
      'inLanguage'  => $locale,
      'license'     => 'https://creativecommons.org/publicdomain/zero/1.0/',
      'isAccessibleForFree' => true,
      'creativeWorkStatus'  => 'Published',
      'dateModified'        => gmdate('c', $this->state->time),
      'temporalCoverage'    => gmdate('Y-m-d', $this->state->windMilestonesSince) . '/..',
      // Place rather than the more precise Country: Country is a subtype of
      // Place, but Google's validator only accepts the base type here
      'spatialCoverage'     => [
        '@type' => 'Place',
        'name'  => $locale === 'de' ? 'Deutschland' : 'Germany'
      ],
      'variableMeasured' => array_map(
        fn ($key) => I18n::t($key, $locale),
        ['graph.generation', 'graph.price', 'graph.emissions', 'graph.transfers']
      ),
      'creator' => [
        '@type' => 'Organization',
        'name'  => 'ENTSO-E Transparency Platform',
        'url'   => 'https://transparency.entsoe.eu/'
      ]
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
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
      <p class="lag">
        <?= I18n::t('site.lag', $locale) ?>
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
