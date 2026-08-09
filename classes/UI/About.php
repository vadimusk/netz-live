<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Outputs the about section. */
class About {
  /**
   * Outputs the about section.
   *
   * @param State  $state  The state
   * @param string $locale The locale ('de' or 'en')
   */
  public static function output(State $state, string $locale): void {
?>
      <section>
        <h2>
          <?= I18n::t('about.heading', $locale) ?>
        </h2>
        <p>
          <?= I18n::t('about.p1', $locale) ?>
        </p>
        <p>
          <?= I18n::t('about.p2', $locale, [I18n::number($state->visits, 0, $locale)]) ?>
        </p>
        <?php (new Graphs($state))->output(Graph::Visits, Period::Year, $locale); ?>
        <p>
          <?= I18n::t('about.p3', $locale) ?>
        </p>
      </section>
<?php
  }
}
