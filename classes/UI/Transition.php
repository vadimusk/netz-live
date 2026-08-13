<?php

namespace KateMorley\Grid\UI;

use KateMorley\Grid\State\State;

/** Outputs the energy transition section. */
class Transition {
  /**
   * Outputs the energy transition section.
   *
   * @param State  $state  The state
   * @param string $locale The locale ('de' or 'en')
   */
  public static function output(State $state, string $locale): void {
    $recordStart = Status::time($state->windRecord->time, $locale);
    $recordEnd   = Status::time($state->windRecord->time + 900, $locale);
    $recordDate  = I18n::longDate($state->windRecord->time, $locale);

?>
      <section>
        <h2>
          <?= I18n::t('transition.heading', $locale) ?>
        </h2>
        <p>
          <?= I18n::t('transition.p1', $locale) ?>
        </p>
        <p>
          <?= I18n::t('transition.p2', $locale) ?>
        </p>
        <p>
          <?= I18n::t('transition.p3', $locale) ?>
        </p>
        <p>
          <?= I18n::t('transition.p4', $locale, [
            strip_tags($recordStart),
            strip_tags($recordEnd),
            $recordDate,
            Value::formatPower($state->windRecord->power, $locale)
          ]) ?>
        </p>
        <table class="wind-milestones">
          <tr><th><?= I18n::t('transition.power', $locale) ?></th><th><?= I18n::t('transition.date', $locale) ?></th></tr>
<?php

    foreach ($state->windMilestones as $power => $time) {
?>
          <tr><td><?= $power ?><abbr>GW</abbr></td><td><?= I18n::longDate($time, $locale) ?></td></tr>
<?php
    }

?>
        </table>
        <p>
          <?= I18n::t('transition.since', $locale, [
            I18n::longDate($state->windMilestonesSince, $locale)
          ]) ?>
        </p>
      </section>
<?php
  }
}
