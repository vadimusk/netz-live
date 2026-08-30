<?php

namespace KateMorley\Grid\UI;

/** Translations and locale-aware number formatting. */
class I18n {
  private const STRINGS = [
    'de' => [
      'site.title'         => 'Stromnetz: Live',
      'site.description'   => 'Zeigt den Live-Status des deutschen Stromnetzes: Erzeugungsmix, Preis und CO₂-Intensität',
      'site.tagline1'      => 'Der aktuelle Strommix',
      'site.tagline2'      => 'im deutschen Stromnetz',
      'site.switchLabel'   => 'English',
      'site.switchPath'    => '/en/',
      'dialog.help'        => 'Hilfe',

      'period.day'      => 'Vergangener Tag',
      'period.day.html' => '<span>Vergangener </span>Tag',
      'period.week'      => 'Vergangene Woche',
      'period.week.html' => '<span>Vergangene </span>Woche',
      'period.year'      => 'Vergangenes Jahr',
      'period.year.html' => '<span>Vergangenes </span>Jahr',
      'period.all'      => 'Gesamter Zeitraum',
      // narrow screens hide the span, so the split leaves "Gesamt" standing
      // on its own rather than the bare adjective ending "Gesamter"
      'period.all.html' => 'Gesamt<span>er Zeitraum</span>',

      'status.time'      => 'Zeit',
      'status.estimate'  => 'jetzt ≈ %s',
      'status.price'      => 'Preis',
      'status.emissions' => 'Emissionen',

      'frequency.heading' => 'Netzfrequenz',
      'frequency.area'    => 'Kontinentaleuropa',

      'equation.demand'     => 'Bedarf',
      'equation.generation' => 'Erzeugung',
      'equation.transfers'  => 'Austausch',

      'kind.generation'      => 'Erzeugung',
      'kind.fossils'         => 'Fossile Energien',
      'kind.renewables'      => 'Erneuerbare',
      'kind.others'          => 'Sonstige Quellen',
      'kind.transfers'       => 'Austausch',
      'kind.interconnectors' => 'Stromflüsse',
      'kind.storage'         => 'Speicher',

      'source.lignite'       => 'Braunkohle',
      'source.hardCoal'      => 'Steinkohle',
      'source.gas'           => 'Erdgas',
      'source.solar'         => 'Solar',
      'source.wind'          => 'Wind',
      'source.hydro'         => 'Wasserkraft',
      'source.nuclear'       => 'Kernenergie',
      'source.biomass'       => 'Biomasse',
      'source.other'         => 'Sonstige',
      'source.pumped'        => 'Pumpspeicher',
      'source.austria'       => 'Österreich',
      'source.belgium'       => 'Belgien',
      'source.czechRepublic' => 'Tschechien',
      'source.denmark'       => 'Dänemark',
      'source.france'        => 'Frankreich',
      'source.luxembourg'    => 'Luxemburg',
      'source.netherlands'   => 'Niederlande',
      'source.norway'        => 'Norwegen',
      'source.poland'        => 'Polen',
      'source.sweden'        => 'Schweden',
      'source.switzerland'   => 'Schweiz',

      'graph.price'      => 'Preis pro MWh',
      'graph.emissions'  => 'Emissionen pro kWh',
      'graph.demand'     => 'Bedarf',
      'graph.generation' => 'Erzeugung',
      'graph.transfers'  => 'Austausch',
      'graph.visits'     => 'Besuche pro Woche',

      'tables.byType'   => 'Erzeugung nach Art',
      'tables.bySource' => 'Erzeugung nach Quelle',

      'about.heading' => 'Über dieses Projekt',
      'about.p1'      => 'Diese Seite ist ein Open-Source-Fork von <a href="https://grid.iamkate.com/">National Grid: Live</a>, einem Projekt von <a href="https://iamkate.com/">Kate Morley</a>. Wie das Original steht auch dieser Fork unter der <a href="https://creativecommons.org/publicdomain/zero/1.0/legalcode">Creative Commons CC0 1.0 Universal Legal Code</a> — der Quellcode ist gemeinfrei und <a href="https://github.com/vadimusk/netz-live">auf GitHub verfügbar</a>, ohne dass eine Namensnennung erforderlich ist.',
      'about.p2'      => 'Diese Seite hatte im vergangenen Jahr %s Besuche.',
      'about.p2.short' => 'Diese Seite hatte seit ihrem Start %s Besuche.',
      'about.p3'      => 'Erzeugung und grenzüberschreitende Lastflüsse stammen von der <a href="https://transparency.entsoe.eu/">ENTSO-E-Transparenzplattform</a>, auf der die Übertragungsnetzbetreiber ihre Zahlen selbst veröffentlichen; sie sind etwa eine halbe Stunde alt, einzelne Grenzen folgen später. Die Day-Ahead-Preise stammen von <a href="https://www.smard.de/">SMARD</a>, der Strommarktdatenplattform der Bundesnetzagentur, und sind unter der Lizenz CC BY 4.0 freigegeben. Die CO₂-Intensität stammt von der <a href="https://www.energy-charts.info/">Energy-Charts-Plattform</a> des Fraunhofer-Instituts für Solare Energiesysteme ISE; sie erscheint mit einigen Stunden Verzug, weshalb sie für die jüngsten Viertelstunden aus dem Erzeugungsmix berechnet wird — mit einer mittleren Abweichung von etwa 7 g/kWh gegenüber dem später eintreffenden amtlichen Wert, der die Berechnung dann ersetzt.',

      'transition.heading' => 'Die Energiewende',
      'transition.p1'      => 'Am 15. April 2023 gingen die letzten drei deutschen Kernkraftwerke vom Netz und beendeten damit ein 2011 nach der Reaktorkatastrophe von Fukushima beschlossenes Ausstiegsprogramm. Kernenergie taucht in den Erzeugungsdaten dieser Seite deshalb nicht mehr auf.',
      'transition.p2'      => 'Der Ausstieg aus der Kohleverstromung ist per Kohleausstiegsgesetz von 2020 spätestens für 2038 vorgesehen, mit dem politischen Ziel eines früheren Ausstiegs. Braunkohle- und Steinkohlekraftwerke liefern weiterhin einen erheblichen Anteil der deutschen Stromerzeugung, besonders an dunklen, windarmen Tagen.',
      'transition.p3'      => 'Gleichzeitig ist der Anteil erneuerbarer Energien kontinuierlich gestiegen: Windkraft- und Solaranlagen decken inzwischen an vielen Tagen über die Hälfte des deutschen Strombedarfs, mit neuen Rekorden bei besonders sonnigen oder windreichen Wetterlagen.',
      'transition.p4'      => 'Zwischen %s und %s am %s erreichte die deutsche Windkraft (Onshore und Offshore zusammen) mit durchschnittlich %sGW einen neuen Rekord.',
      'transition.power'   => 'Leistung',
      'transition.date'    => 'Datum des ersten Erreichens',
      'transition.since'   => 'Grundlage sind die Viertelstundenwerte von SMARD, die bis zum %s zurückreichen. Niedrigere Leistungsstufen sind nicht aufgeführt, da sie bereits vor Beginn der Datenreihe erreicht worden sein dürften.'
    ],

    'en' => [
      'site.title'         => 'German Grid: Live',
      'site.description'   => "Shows the live status of Germany's electric power grid: the generation mix, price, and carbon intensity",
      'site.tagline1'      => 'The current electricity mix',
      'site.tagline2'      => "on Germany's power grid",
      'site.switchLabel'   => 'Deutsch',
      'site.switchPath'    => '/',
      'dialog.help'        => 'Help',

      'period.day'      => 'Past day',
      'period.day.html' => '<span>Past </span>day',
      'period.week'      => 'Past week',
      'period.week.html' => '<span>Past </span>week',
      'period.year'      => 'Past year',
      'period.year.html' => '<span>Past </span>year',
      'period.all'      => 'All time',
      'period.all.html' => 'All<span> time</span>',

      'status.time'      => 'Time',
      'status.estimate'  => 'now ≈ %s',
      'status.price'      => 'Price',
      'status.emissions' => 'Emissions',

      'frequency.heading' => 'Grid frequency',
      'frequency.area'    => 'Continental Europe',

      'equation.demand'     => 'Demand',
      'equation.generation' => 'Generation',
      'equation.transfers'  => 'Transfers',

      'kind.generation'      => 'Generation',
      'kind.fossils'         => 'Fossil fuels',
      'kind.renewables'      => 'Renewables',
      'kind.others'          => 'Other sources',
      'kind.transfers'       => 'Transfers',
      'kind.interconnectors' => 'Physical flows',
      'kind.storage'         => 'Storage',

      'source.lignite'       => 'Lignite',
      'source.hardCoal'      => 'Hard coal',
      'source.gas'           => 'Gas',
      'source.solar'         => 'Solar',
      'source.wind'          => 'Wind',
      'source.hydro'         => 'Hydro',
      'source.nuclear'       => 'Nuclear',
      'source.biomass'       => 'Biomass',
      'source.other'         => 'Other',
      'source.pumped'        => 'Pumped storage',
      'source.austria'       => 'Austria',
      'source.belgium'       => 'Belgium',
      'source.czechRepublic' => 'Czech Republic',
      'source.denmark'       => 'Denmark',
      'source.france'        => 'France',
      'source.luxembourg'    => 'Luxembourg',
      'source.netherlands'   => 'Netherlands',
      'source.norway'        => 'Norway',
      'source.poland'        => 'Poland',
      'source.sweden'        => 'Sweden',
      'source.switzerland'   => 'Switzerland',

      'graph.price'      => 'Price per MWh',
      'graph.emissions'  => 'Emissions per kWh',
      'graph.demand'     => 'Demand',
      'graph.generation' => 'Generation',
      'graph.transfers'  => 'Transfers',
      'graph.visits'     => 'Weekly visits',

      'tables.byType'   => 'Generation by type',
      'tables.bySource' => 'Generation by source',

      'about.heading' => 'About this site',
      'about.p1'      => 'This site is an open source fork of <a href="https://grid.iamkate.com/">National Grid: Live</a>, a project by <a href="https://iamkate.com/">Kate Morley</a>. Like the original, this fork is published under the terms of the <a href="https://creativecommons.org/publicdomain/zero/1.0/legalcode">Creative Commons CC0 1.0 Universal Legal Code</a> — the code is public domain and <a href="https://github.com/vadimusk/netz-live">available on GitHub</a>, and you can use and adapt it without attribution.',
      'about.p2'      => 'This site received %s visits over the past year.',
      'about.p2.short' => 'This site has received %s visits since it launched.',
      'about.p3'      => 'Generation and cross-border flows come from the <a href="https://transparency.entsoe.eu/">ENTSO-E Transparency Platform</a>, where the transmission system operators publish their figures themselves; they are around half an hour old, with some borders following later. The day-ahead prices come from <a href="https://www.smard.de/">SMARD</a>, the electricity market data platform of the Bundesnetzagentur, and are released under a CC BY 4.0 licence. The carbon intensity comes from the <a href="https://www.energy-charts.info/">Energy-Charts platform</a> run by the Fraunhofer Institute for Solar Energy Systems ISE; it arrives a few hours late, so for the most recent quarter hours it is calculated from the generation mix instead — to within about 7 g/kWh of the official figure that later arrives and replaces it.',

      'transition.heading' => 'The energy transition',
      'transition.p1'      => "On 15th April 2023 Germany's last three nuclear power stations shut down, completing a phase-out programme adopted in 2011 in response to the Fukushima disaster. As a result, nuclear power no longer appears in this site's generation data.",
      'transition.p2'      => "The 2020 coal phase-out law commits Germany to ending coal-fired power generation by 2038 at the latest, with a political ambition to bring the date forward. Lignite and hard coal power stations still supply a substantial share of the country's electricity, especially on dark, low-wind days.",
      'transition.p3'      => "At the same time, the share of renewable generation has risen steadily: wind and solar power now cover more than half of Germany's electricity demand on many days, with new records set during especially sunny or windy weather.",
      'transition.p4'      => 'Between %s and %s on %s, German wind power (onshore and offshore combined) averaged a record %sGW of generation.',
      'transition.power'   => 'Power',
      'transition.date'    => 'Date first achieved',
      'transition.since'   => 'Based on quarter-hourly figures from SMARD, which reach back to %s. Lower levels aren\'t listed, since they were most likely reached before the data begins.'
    ]
  ];

  /**
   * Returns a translated string.
   *
   * @param string        $key    The translation key
   * @param string        $locale The locale ('de' or 'en')
   * @param array<string> $params Values to substitute for %s placeholders
   */
  public static function t(string $key, string $locale, array $params = []): string {
    $string = self::STRINGS[$locale][$key] ?? $key;

    return count($params) === 0 ? $string : vsprintf($string, $params);
  }

  private const MONTHS_DE = [
    'Januar', 'Februar', 'März', 'April', 'Mai', 'Juni',
    'Juli', 'August', 'September', 'Oktober', 'November', 'Dezember'
  ];

  /**
   * Formats a Unix timestamp as a long date (e.g. "15 April 2023" /
   * "15. April 2023"), in the Europe/Berlin timezone. PHP's date() can't
   * localise month names without the intl extension, so German month names
   * are mapped by hand.
   *
   * @param int    $timestamp The Unix timestamp
   * @param string $locale    The locale ('de' or 'en')
   */
  public static function longDate(int $timestamp, string $locale): string {
    $local = (new \DateTime('@' . $timestamp))->setTimezone(
      new \DateTimeZone('Europe/Berlin')
    );

    if ($locale === 'de') {
      return $local->format('j.') . ' ' . self::MONTHS_DE[(int)$local->format('n') - 1] . ' ' . $local->format('Y');
    }

    return $local->format('jS F Y');
  }

  /**
   * Formats an age, in seconds, as a short relative phrase (e.g. "vor 40 Min."
   * / "40 min ago", or "vor 2 Std. 20 Min." / "2 hr 20 min ago"), for showing
   * beside the time how old the newest data is.
   *
   * @param int    $seconds The age in seconds
   * @param string $locale  The locale ('de' or 'en')
   */
  public static function age(int $seconds, string $locale): string {
    $minutes = max(1, (int)round($seconds / 60));
    $hours   = intdiv($minutes, 60);
    $rest    = $minutes % 60;

    if ($locale === 'de') {
      return $hours === 0
        ? 'vor ' . $minutes . ' Min.'
        : 'vor ' . $hours . ' Std.' . ($rest !== 0 ? ' ' . $rest . ' Min.' : '');
    }

    return $hours === 0
      ? $minutes . ' min ago'
      : $hours . ' hr' . ($rest !== 0 ? ' ' . $rest . ' min' : '') . ' ago';
  }

  /**
   * Formats a number using locale-appropriate separators.
   *
   * @param float  $value         The value
   * @param int    $decimalPlaces The number of decimal places to show
   * @param string $locale        The locale ('de' or 'en')
   */
  public static function number(
    float  $value,
    int    $decimalPlaces,
    string $locale
  ): string {
    return $locale === 'de'
      ? number_format($value, $decimalPlaces, ',', '.')
      : number_format($value, $decimalPlaces, '.', ',');
  }
}
