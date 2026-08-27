const LABELS = {
  de: {
    price: 'Preis pro MWh',
    emissions: 'Emissionen pro kWh',
    demand: 'Bedarf',
    generation: 'Erzeugung',
    fossils: 'Fossile Energien',
    renewables: 'Erneuerbare',
    others: 'Sonstige Quellen',
    transfers: 'Austausch',
    lignite: 'Braunkohle',
    hardCoal: 'Steinkohle',
    gas: 'Erdgas',
    solar: 'Solar',
    wind: 'Wind',
    hydro: 'Wasserkraft',
    nuclear: 'Kernenergie',
    biomass: 'Biomasse',
    other: 'Sonstige',
    pumped: 'Pumpspeicher',
    austria: 'Österreich',
    belgium: 'Belgien',
    czechRepublic: 'Tschechien',
    denmark: 'Dänemark',
    france: 'Frankreich',
    luxembourg: 'Luxemburg',
    netherlands: 'Niederlande',
    norway: 'Norwegen',
    poland: 'Polen',
    sweden: 'Schweden',
    switzerland: 'Schweiz'
  },

  en: {
    price: 'Price per MWh',
    emissions: 'Emissions per kWh',
    demand: 'Demand',
    generation: 'Generation',
    fossils: 'Fossil fuels',
    renewables: 'Renewables',
    others: 'Other sources',
    transfers: 'Transfers',
    lignite: 'Lignite',
    hardCoal: 'Hard coal',
    gas: 'Gas',
    solar: 'Solar',
    wind: 'Wind',
    hydro: 'Hydro',
    nuclear: 'Nuclear',
    biomass: 'Biomass',
    other: 'Other',
    pumped: 'Pumped storage',
    austria: 'Austria',
    belgium: 'Belgium',
    czechRepublic: 'Czech Republic',
    denmark: 'Denmark',
    france: 'France',
    luxembourg: 'Luxembourg',
    netherlands: 'Netherlands',
    norway: 'Norway',
    poland: 'Poland',
    sweden: 'Sweden',
    switzerland: 'Switzerland'
  }
}

const HELP = {
  de: {
    time: '<p>Alle Daten liegen im Viertelstundenraster vor, entsprechend der Auflösung der zugrundeliegenden Marktdaten.</p><p>Die grenzüberschreitenden Lastflüsse werden erst mit einigen Stunden Verzug veröffentlicht. Angezeigt wird deshalb die jüngste Viertelstunde, für die alle Werte — Erzeugung, Flüsse, Preis und Emissionen — vollständig vorliegen.</p>',
    price: '<p>Der angezeigte Preis ist der deutsch-luxemburgische Day-Ahead-Auktionspreis, wie er von der Bundesnetzagentur über SMARD.de veröffentlicht wird. Er spiegelt den Großhandelspreis wider, zu dem Strom für den jeweiligen Zeitraum am Vortag gehandelt wurde.</p><p>Bei hoher Einspeisung aus Wind- und Solaranlagen und gleichzeitig niedriger Nachfrage kann der Preis auf null oder sogar negative Werte fallen.</p>',
    emissions: '<p>Die Verbrennung von Braunkohle, Steinkohle, Erdgas und Biomasse setzt Kohlendioxid frei. Dieser Wert schätzt die CO₂-Intensität der deutschen Stromerzeugung auf Basis des aktuellen Erzeugungsmixes.</p><p>Die Farbe richtet sich nach der Bandbreite des deutschen Netzes selbst: im vergangenen Jahr lag die Intensität zwischen 94 und 766g/kWh bei einem Median von 382. Je ein Drittel der Zeit lag sie unter 300 beziehungsweise über 450g/kWh — daran orientieren sich die Stufen.</p><ul><li class="low"><span>Niedrig (≤300g/kWh)</span></li><li class="medium"><span>Mittel (≤450g/kWh)</span></li><li class="high"><span>Hoch (>450g/kWh)</span></li></ul>',
    frequency: '<p>Die Netzfrequenz ist der einzige Wert auf dieser Seite, der das Jetzt beschreibt: Er ist rund drei Minuten alt, während Erzeugung, Preis und Emissionen eine halbe Stunde zurückliegen.</p><p>Im europäischen Verbundnetz drehen sich alle Generatoren im Gleichtakt. Liegt die Frequenz unter 50 Hz, wird in diesem Augenblick mehr Strom entnommen als erzeugt, und die Schwungmassen der Turbinen geben die Differenz ab, wobei sie langsamer werden. Über 50 Hz besteht ein Überschuss.</p><p>Der Wert gilt nicht für Deutschland allein, sondern für das gesamte kontinentaleuropäische Synchrongebiet von Portugal bis Polen — dort wird überall dieselbe Frequenz gemessen.</p><ul><li class="low"><span>Normal (±20 mHz)</span></li><li class="medium"><span>Erhöht (±50 mHz)</span></li><li class="high"><span>Angespannt (>50 mHz)</span></li></ul>',
    demand: '<p>Der Bedarf ist die Summe aus inländischer Erzeugung und dem Nettoimport aus dem Ausland. Da das Netz ausgeglichen sein muss, entspricht der Bedarf stets der erzeugten Leistung zuzüglich der importierten (oder abzüglich der exportierten) Leistung.</p>',
    generation: '<p>Der überwiegende Teil des in Deutschland verbrauchten Stroms wird von Kraftwerken im Inland erzeugt. Diese nutzen drei Arten von Energiequellen:</p><p>Fossile Energien sind die Überreste urzeitlicher Pflanzen und Tiere. Ihre Verbrennung setzt Kohlendioxid und andere Schadstoffe frei und verschärft die Klimakrise.</p><p>Erneuerbare Energien werden auf natürliche Weise rasch wieder aufgefüllt. Der Ersatz fossiler Energien durch Erneuerbare senkt die Kohlendioxidemissionen erheblich.</p><p>Sonstige Quellen können gegenüber fossilen Energien vorzuziehen sein, haben aber eigene unerwünschte Effekte, etwa im Fall von Abfall- oder Biomasseverbrennung.</p>',
    transfers: '<p>Die Erzeugung muss dem Bedarf nicht exakt entsprechen, da Strom mit den Nachbarländern gehandelt und in Speichersystemen zwischen- oder eingelagert werden kann.</p><p>Gezeigt werden die physikalischen Lastflüsse: der Strom, der tatsächlich über die Grenzen geflossen ist. Weil Deutschland Teil des kontinentaleuropäischen Verbundnetzes ist, nimmt der Strom den Weg über alle verfügbaren Leitungen — eine Lieferung nach Frankreich kann daher physikalisch durch die Schweiz fließen. Die Flüsse weichen deshalb je Nachbarland deutlich vom rein kommerziellen Außenhandel ab, obwohl beide über den Tag dieselbe Gesamtbilanz ergeben.</p><p>Diese Daten werden erst einige Stunden nach dem jeweiligen Zeitraum veröffentlicht. Die Seite zeigt daher den letzten Zeitpunkt, für den alle Werte vollständig vorliegen, statt neuere, aber physikalisch unzutreffende Zahlen.</p><p>Positive Werte bedeuten einen Import nach Deutschland, negative Werte einen Export.</p>',
    lignite: '<p>Braunkohlekraftwerke verbrennen Braunkohle, die im Tagebau in den rheinischen, mitteldeutschen und lausitzer Revieren gefördert wird. Braunkohle hat einen geringeren Energiegehalt als Steinkohle und verursacht bei der Verbrennung besonders hohe CO₂-Emissionen.</p><p>Das Kohleausstiegsgesetz von 2020 sieht ein Ende der Braunkohleverstromung spätestens 2038 vor.</p>',
    hardCoal: '<p>Steinkohlekraftwerke verbrennen Steinkohle, die heute nahezu vollständig importiert wird, da der deutsche Steinkohlebergbau 2018 endete.</p><p>Wie bei Braunkohle sieht das Kohleausstiegsgesetz von 2020 ein Ende der Steinkohleverstromung spätestens 2038 vor.</p>',
    gas: '<p>Gaskraftwerke verbrennen Erdgas, um eine Turbine anzutreiben. Sie lassen sich schneller regeln als Kohlekraftwerke und übernehmen daher häufig die Ausgleichslast, wenn die Einspeisung aus Wind und Solar schwankt.</p>',
    solar: '<p>Solaranlagen erzeugen Strom durch den photovoltaischen Effekt. Die installierte Solarleistung in Deutschland ist in den letzten Jahren stark gewachsen, sowohl durch große Freiflächenanlagen als auch durch Anlagen auf Wohn- und Gewerbedächern.</p><p>Die Erzeugung folgt naturgemäß dem Tagesverlauf und schwankt stark mit der Jahreszeit und der Bewölkung.</p>',
    wind: '<p>Windkraftanlagen erzeugen Strom aus der Bewegung der Luft. Onshore-Anlagen stehen an Land, vor allem in Nord- und Ostdeutschland; Offshore-Anlagen stehen in Nord- und Ostsee, wo der Wind stärker und gleichmäßiger weht.</p>',
    hydro: '<p>Wasserkraftanlagen erzeugen Strom aus der Bewegung von Wasser. Laufwasserkraftwerke nutzen den natürlichen Flusslauf, während Speicherkraftwerke Wasser in einem höher gelegenen Reservoir zurückhalten. Aufgrund der Topographie befindet sich der Großteil der deutschen Wasserkraft in Bayern und Baden-Württemberg.</p>',
    nuclear: '<p>Kernkraftwerke gewinnen Wärme aus der Spaltung von Uran und treiben damit eine Turbine an. Der Betrieb setzt kein Kohlendioxid frei, hinterlässt aber radioaktiven Abfall.</p><p>Am 15. April 2023 gingen mit Emsland, Isar 2 und Neckarwestheim 2 die letzten drei deutschen Reaktoren vom Netz. In den Langzeitreihen dieser Seite ist der Ausstieg als Kurve zu sehen, die an diesem Tag auf null fällt; seither erzeugt Deutschland keinen Atomstrom mehr.</p>',
    biomass: '<p>Biomassekraftwerke verbrennen Pflanzenmaterial, Holzpellets oder Biogas, um eine Turbine anzutreiben. Biomasse gilt als erneuerbar, da nachwachsende Pflanzen das bei der Verbrennung freigesetzte Kohlendioxid wieder binden können, wobei dieser Prozess Jahre bis Jahrzehnte dauert.</p>',
    other: '<p>Diese Kategorie fasst kleinere Erzeugungsquellen zusammen: Erdöl, Abfallverbrennung, Geothermie und sonstige, nicht gesondert ausgewiesene Erzeugung.</p>',
    pumped: '<p>Pumpspeicherkraftwerke nutzen Strom, wenn er vergleichsweise günstig ist, um Wasser aus einem tiefer gelegenen in ein höher gelegenes Reservoir zu pumpen. Ist Strom vergleichsweise teuer, wird das Wasser abgelassen und treibt Turbinen an.</p><p>Negative Werte bedeuten, dass Wasser hochgepumpt wird, positive Werte, dass Strom erzeugt wird.</p>',
    austria: '<p>Deutschland und Österreich sind Teil des kontinentaleuropäischen Verbundnetzes und über mehrere Höchstspannungsleitungen direkt miteinander verbunden — bis 2018 bildeten beide Länder sogar eine gemeinsame Strompreiszone.</p>',
    belgium: '<p>Deutschland und Belgien sind über Höchstspannungsleitungen des kontinentaleuropäischen Verbundnetzes verbunden.</p>',
    czechRepublic: '<p>Deutschland und Tschechien sind über Höchstspannungsleitungen des kontinentaleuropäischen Verbundnetzes verbunden, vor allem zwischen Sachsen und Bayern auf deutscher Seite.</p>',
    denmark: '<p>Deutschland und Dänemark sind über mehrere grenzüberschreitende Leitungen nach Jütland verbunden. Dänemarks Stromnetz ist zweigeteilt: Der westliche Teil (DK1) ist eng mit dem deutschen Netz verzahnt.</p>',
    france: '<p>Deutschland und Frankreich sind Teil des kontinentaleuropäischen Verbundnetzes und über mehrere Höchstspannungsleitungen entlang des Rheins verbunden.</p>',
    luxembourg: '<p>Deutschland und Luxemburg sind über Höchstspannungsleitungen des kontinentaleuropäischen Verbundnetzes verbunden. Luxemburg bildet gemeinsam mit Deutschland die Strompreiszone DE-LU.</p>',
    netherlands: '<p>Deutschland und die Niederlande sind über Höchstspannungsleitungen des kontinentaleuropäischen Verbundnetzes verbunden, vor allem im Grenzgebiet zu Nordrhein-Westfalen und Niedersachsen.</p>',
    norway: '<p>NordLink ist eine 1,4<abbr>GW</abbr>-Gleichstromverbindung zwischen Wilster in Schleswig-Holstein und Tonstad in Norwegen. Sie ging 2021 in Betrieb und wird auch als „grünes Kabel" bezeichnet, da sie deutsche Wind- und Solarenergie mit norwegischer Wasserkraft verknüpft.</p>',
    poland: '<p>Deutschland und Polen sind über Höchstspannungsleitungen des kontinentaleuropäischen Verbundnetzes verbunden, hauptsächlich zwischen Brandenburg, Sachsen und den westpolnischen Netzgebieten.</p>',
    sweden: '<p>Das Baltic Cable ist eine Gleichstromverbindung mit rund 0,6<abbr>GW</abbr> Kapazität zwischen Lübeck und der schwedischen Küste bei Kruseberg. Es ging bereits 1994 in Betrieb.</p>',
    switzerland: '<p>Deutschland und die Schweiz sind Teil des kontinentaleuropäischen Verbundnetzes und über mehrere Höchstspannungsleitungen verbunden, vor allem im süddeutschen Grenzgebiet.</p>'
  },

  en: {
    time: '<p>All the data comes at quarter-hourly resolution, matching the underlying market data.</p><p>Cross-border flows are published a few hours after the fact, so what is shown is the most recent quarter hour for which every figure — generation, flows, price and emissions — is complete.</p>',
    price: '<p>The price shown is the German-Luxembourg (DE-LU) day-ahead auction price, as published by the Bundesnetzagentur via SMARD.de. It reflects the wholesale price at which electricity for each period was traded the day before.</p><p>When wind and solar output is high and demand is low, the price can fall to zero or even go negative.</p>',
    emissions: '<p>Burning lignite, hard coal, gas, and biomass produces carbon dioxide. This figure estimates the carbon intensity of German electricity generation from the current generation mix.</p><p>The colour follows the German grid\'s own range rather than a fixed target: over the past year the intensity ran from 94 to 766g/kWh with a median of 382, spending about a third of the time below 300 and a third above 450, which is where the levels sit.</p><ul><li class="low"><span>Low (≤300g/kWh)</span></li><li class="medium"><span>Medium (≤450g/kWh)</span></li><li class="high"><span>High (>450g/kWh)</span></li></ul>',
    frequency: '<p>Grid frequency is the only figure on this page that describes now: it is around three minutes old, where generation, price and emissions are half an hour behind.</p><p>Across the European interconnected grid every generator turns in step. Below 50 Hz more power is being drawn than generated at this instant, and the spinning mass of the turbines makes up the difference as it slows. Above 50 Hz there is a surplus.</p><p>The figure is not for Germany alone but for the whole Continental European synchronous area, from Portugal to Poland, where the same frequency is measured everywhere.</p><ul><li class="low"><span>Normal (±20 mHz)</span></li><li class="medium"><span>Elevated (±50 mHz)</span></li><li class="high"><span>Strained (>50 mHz)</span></li></ul>',
    demand: "<p>Demand is the sum of domestic generation and net imports from abroad. As the grid is balanced, demand always equals the power being generated plus power being imported (or minus power being exported).</p>",
    generation: '<p>Most of the electricity used in Germany is generated by power stations within the country. These use three types of source:</p><p>Fossil fuels are the remains of ancient plants and animals. Burning them releases carbon dioxide and other pollutants, worsening the climate crisis.</p><p>Renewables are resources that are rapidly replenished naturally. Replacing fossil fuels with renewables dramatically reduces carbon dioxide emissions.</p><p>Other sources may be preferable to fossil fuels but have their own unwanted effects, such as those from waste incineration or biomass combustion.</p>',
    transfers: "<p>Generation doesn't need to match demand exactly, as electricity can be traded with neighbouring countries and moved into or out of storage systems.</p><p>What's shown is the physical flows: the electricity that actually crossed the borders. Because Germany is part of the Continental European synchronous grid, power takes whichever lines carry it, so a sale to France can physically flow through Switzerland. Per neighbour the flows therefore differ markedly from purely commercial trade, even though the two agree on the overall balance across a day.</p><p>This data is published a few hours after each period. The page shows the most recent time for which every figure is complete, rather than newer numbers that would not reflect what physically happened.</p><p>Positive values mean an import into Germany, negative values an export.</p>",
    lignite: '<p>Lignite (brown coal) power stations burn coal strip-mined in the Rhineland, central Germany, and Lusatia regions. Lignite has a lower energy content than hard coal and produces particularly high carbon dioxide emissions when burned.</p><p>The 2020 coal phase-out law requires an end to lignite-fired generation by 2038 at the latest.</p>',
    hardCoal: '<p>Hard coal power stations burn coal that is now almost entirely imported, since German hard coal mining ended in 2018.</p><p>As with lignite, the 2020 coal phase-out law requires an end to hard coal generation by 2038 at the latest.</p>',
    gas: '<p>Gas-fired power stations burn natural gas to drive a turbine. They can be ramped up and down faster than coal stations, so they often provide balancing power when wind and solar output fluctuates.</p>',
    solar: "<p>Solar panels generate power from the photovoltaic effect. Germany's installed solar capacity has grown rapidly in recent years, from both large ground-mounted arrays and panels on residential and commercial roofs.</p><p>Generation naturally follows the daily cycle and varies strongly with season and cloud cover.</p>",
    wind: '<p>Wind turbines generate power from the movement of air. Onshore turbines are mostly located in northern and eastern Germany; offshore turbines stand in the North Sea and Baltic Sea, where winds are stronger and more consistent.</p>',
    hydro: "<p>Hydroelectric turbines generate power from the movement of water. Run-of-river stations use a river's natural flow, while reservoir stations hold back water at height. Because of the terrain involved, most of Germany's hydroelectric capacity is in Bavaria and Baden-Württemberg.</p>",
    nuclear: '<p>Nuclear power stations raise heat by splitting uranium, and use it to drive a turbine. Running them releases no carbon dioxide, but leaves radioactive waste behind.</p><p>On 15th April 2023 the last three German reactors — Emsland, Isar 2, and Neckarwestheim 2 — were disconnected from the grid. On this site\'s longer views the phase-out shows as a line falling to zero on that day; Germany has generated no nuclear power since.</p>',
    biomass: '<p>Biomass power stations burn plant material, wood pellets, or biogas to drive a turbine. Biomass is classed as renewable because newly planted crops can reabsorb the carbon dioxide released by burning, though this process takes years to decades.</p>',
    other: '<p>This category groups together smaller generation sources: oil, waste incineration, geothermal power, and other generation not separately reported.</p>',
    pumped: '<p>Pumped hydroelectric storage systems use electricity when it is comparatively cheap to pump water from a lower reservoir into a higher one. When electricity is comparatively expensive, the water is released, driving turbines to produce power.</p><p>Negative values mean water is being pumped, positive values mean power is being generated.</p>',
    austria: "<p>Germany and Austria are both part of the Continental European synchronous grid and are directly linked by several high-voltage lines — until 2018 the two countries even shared a single electricity price zone.</p>",
    belgium: '<p>Germany and Belgium are linked by high-voltage lines as part of the Continental European synchronous grid.</p>',
    czechRepublic: '<p>Germany and the Czech Republic are linked by high-voltage lines as part of the Continental European synchronous grid, mainly between Saxony and Bavaria on the German side.</p>',
    denmark: "<p>Germany and Denmark are linked by several cross-border lines into Jutland. Denmark's grid is split in two: the western half (DK1) is closely integrated with the German network.</p>",
    france: '<p>Germany and France are both part of the Continental European synchronous grid and are linked by several high-voltage lines along the Rhine.</p>',
    luxembourg: '<p>Germany and Luxembourg are linked by high-voltage lines as part of the Continental European synchronous grid. Luxembourg forms a single electricity price zone with Germany (DE-LU).</p>',
    netherlands: '<p>Germany and the Netherlands are linked by high-voltage lines as part of the Continental European synchronous grid, mainly near the border with North Rhine-Westphalia and Lower Saxony.</p>',
    norway: '<p>NordLink is a 1.4<abbr>GW</abbr> HVDC link between Wilster in Schleswig-Holstein and Tonstad in Norway. It entered service in 2021 and is sometimes called the "green cable", as it pairs German wind and solar power with Norwegian hydroelectric power.</p>',
    poland: '<p>Germany and Poland are linked by high-voltage lines as part of the Continental European synchronous grid, mainly connecting Brandenburg and Saxony to western Polish grid areas.</p>',
    sweden: '<p>The Baltic Cable is an HVDC link with a capacity of around 0.6<abbr>GW</abbr> between Lübeck and the Swedish coast near Kruseberg. It has been in service since 1994.</p>',
    switzerland: '<p>Germany and Switzerland are both part of the Continental European synchronous grid and are linked by several high-voltage lines, mainly in the southern German border area.</p>'
  }
}

const KEY_MARGIN = 8

const ELEMENTS_TO_UPDATE = [
  '#live',
  '#frequency',
  '#tab-panel-day',
  '#tab-panel-week',
  '#tab-panel-year',
  '#tab-panel-all',
  'footer'
]

let locale = document.documentElement.lang === 'de' ? 'de' : 'en'
let labels = LABELS[locale]
let help = HELP[locale]

let key = document.createElement('div')
let dialog = document.querySelector('dialog')
let updated = document.querySelector('time').dateTime
let delay = Math.random() * 60000
let parser = new DOMParser()

document.addEventListener('visibilitychange', update)
document.body.addEventListener('click', handleClick)

let tabList = document.querySelector('[role="tablist"]')
selectTab(tabList, tabList.firstElementChild)
tabList.addEventListener('click', handleTabClick)
tabList.addEventListener('keydown', handleTabKeyDown)

addGraphListeners()
scheduleUpdate()

/** Adds the listeners to the graphs. */
function addGraphListeners() {
  document.querySelectorAll('.pie-chart').forEach(pieChart => {
    pieChart.addEventListener('mouseover', e => updatePieChartKey(e, true))
    pieChart.addEventListener('mouseout',  e => updatePieChartKey(e, false))
  })

  document.querySelectorAll('.graph svg').forEach(graph => {
    graph.addEventListener('mouseover', showGraphKey)
    graph.addEventListener('mouseleave', hideGraphKey)
  })
}

/** Handles a click by showing a help dialog if appropriate. */
function handleClick(e) {
  let helpKey = e.target.dataset.help

  if (helpKey) {
    dialog.children[0].innerHTML = e.target.parentNode.textContent
    dialog.children[2].innerHTML = help[helpKey]
    dialog.showModal()
  }
}

/** Updates a pie chart key. */
function updatePieChartKey(e, showDetails) {
  if (e.target.nodeName === 'path') {
    let sourceNode = e.target.parentNode
    let source     = 'generation'
    let pieChart   = sourceNode.parentNode

    if (showDetails) {
      sourceNode = e.target
      source     = e.target.getAttribute('class')
    }

    let nodes = pieChart.querySelectorAll('div,span')

    nodes[1].textContent = labels[source]
    nodes[2].className   = source
    nodes[4].textContent = sourceNode.dataset.power
    nodes[6].textContent = sourceNode.dataset.percentage
  }
}

/** Selects a tab. */
function selectTab(tabList, tab) {
  let panels = Array.from(
    tabList.parentNode.querySelectorAll('[role="tabpanel"]')
  )

  for (let node of tabList.children) {
    let selected = (node === tab)

    node.setAttribute('aria-selected', (selected ? 'true' : 'false'))
    node.tabIndex = (selected ? 0 : -1)

    panels.shift().style.display = (selected ? 'grid' : 'none')
  }
}

/** Handles a click on a tab. */
function handleTabClick(e) {
  if (e.target.parentNode === this) {
    selectTab(this, e.target)
  } else if (e.target.parentNode.parentNode === this) {
    selectTab(this, e.target.parentNode)
  }
}

/** Handles a key down on a tab. */
function handleTabKeyDown(e) {
  let tabs  = Array.from(this.children)
  let count = tabs.length
  let index = tabs.indexOf(this.querySelector('[aria-selected="true"'))

  let preventDefault = true

  switch (e.key) {
    case 'ArrowLeft':  index = (index + count - 1) % count; break
    case 'ArrowRight': index = (index + 1) % count;         break
    case 'Home':       index = 0;                           break
    case 'End':        index = count - 1;                   break
    default:           preventDefault = false
  }

  if (preventDefault) {
    e.preventDefault()
  }

  selectTab(this, tabs[index])
  tabs[index].focus()
}

/** Shows the graph key. */
function showGraphKey(e) {
  if (e.target.nodeName !== 'rect') {
    return
  }

  let graph     = e.target.closest('.graph')
  let transfers = graph.dataset.transfers === 'true'
  let prefix    = graph.dataset.prefix
  let suffix    = graph.dataset.suffix
  let classes   = Array.from(graph.querySelectorAll('path')).map(
    series => series.className.baseVal
  )
  let values    = e.target.dataset.values.split(' ')

  let time = document.createElement('div')
  time.append(e.target.dataset.time)

  let table = document.createElement('table')
  table.className = 'sources' + (transfers ? ' transfers' : '')

  let body  = table.createTBody()

  for (let i = 0; i < values.length; i ++) {
    let isNegative = values[i].substring(0,1) === '-'

    let row = body.insertRow()
    row.insertCell().className = classes[i]
    row.insertCell().textContent = labels[classes[i]]
    row.insertCell().textContent = (
      (isNegative ? '−' : '')
      + prefix
      + values[i].substring(isNegative ? 1 : 0)
      + suffix
    )
  }

  key.innerHTML = ''
  key.append(time, table)
  graph.append(key)

  let keyWidth        = key.offsetWidth
  let overlayPosition = e.target.getBoundingClientRect()

  let left = overlayPosition.left - graph.getBoundingClientRect().left

  if (overlayPosition.left > keyWidth + 2 * KEY_MARGIN) {
    left -= keyWidth + KEY_MARGIN
  } else {
    left += overlayPosition.width + KEY_MARGIN
  }

  key.style.left = left + 'px'
}

/** Hides the graph key. */
function hideGraphKey() {
  key.remove()
}

/**
 * Schedules an update. Updates occur every five minutes, with an offset of two
 * minutes plus a visitor-specific random delay of up to a minute to reduce
 * server load.
 */
function scheduleUpdate() {
  setTimeout(update, (420000 - (Date.now() % 300000)  + delay) % 300000)
}

/**
 * Updates the user interface.
 *
 * @param {boolean} unscheduled `true` for updates triggered when the page
 *                              becomes visible again after updates were
 *                              suspended while the page was not visible
 */
function update(unscheduled) {
  let time = Math.floor(Date.now() / 300000)

  if (unscheduled && (Date.now() % 300000) < (120000 + delay)) {
    time --
  }

  let faviconLink = document.querySelector('link[type*="svg"]')
  faviconLink.href = faviconLink.href.split('?')[0] + '?' + time

  if (document.visibilityState === 'visible') {
    fetch('?v=' + time).then(response => response.text()).then(html => {
      let update = parser.parseFromString(html, 'text/html')

      if (update.documentElement.dataset.version > document.documentElement.dataset.version) {
        location.reload()
      }

      let timestamp = update.querySelector('time').dateTime
      if (timestamp > updated) {
        updated = timestamp

        ELEMENTS_TO_UPDATE.forEach(
          selector => document.querySelector(selector).replaceChildren(
            ...update.querySelector(selector).children
          )
        )

        hideGraphKey()

        addGraphListeners()
      }
    })
  }

  if (!unscheduled) {
    scheduleUpdate()
  }
}
