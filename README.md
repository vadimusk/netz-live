# Stromnetz: Live / German Grid: Live

This repository is a German fork of [National Grid: Live](https://grid.iamkate.com/) ([KateMorley/grid](https://github.com/KateMorley/grid)), a project by [Kate Morley](https://iamkate.com/). Same architecture and visual design, adapted to show the live status of **Germany's** electric power grid instead of Great Britain's: generation mix, price, and carbon intensity, updated every fifteen minutes. The site is bilingual, with German at `/` and English at `/en/`.

Live at [netz.vterskov.de](https://netz.vterskov.de/), rebuilt from the API every five minutes.

## Development

The development environment uses [Docker](https://www.docker.com/).

Copy `.env.example` to `.env` and edit the values as appropriate. At a minimum, `DATABASE_PASSWORD` must be given a value.

To start the containers:

```
docker compose up --detach
```

Once the containers are running, you can view the site at [http://localhost:9714/](http://localhost:9714/). Port 9714 was chosen by the original author due to its slight resemblance to the word 'grid'.

To run the update script:

```
docker compose exec php php /var/grid/update.php
```

To stop the containers:

```
docker compose down
```

## Production

The production environment does not use Docker, instead running directly on the server. PHP 8.3 and a recent version of MariaDB or MySQL are required.

### Files

Copy `.env.example` to `.env` and edit the values as appropriate. At a minimum, `DATABASE_PASSWORD` must be given a value.`DATABASE_HOSTNAME` should be changed to `localhost` if the database is running on the same server.

Upload `.env`, `update.php`, and the `classes` and `public` directories to the server.

### Database

Create a database and a user with `SELECT`, `INSERT`, `UPDATE`, and `DELETE` privileges, and import `grid.sql` into the database.

### Web server

Configure the server to serve the contents of the `public` directory, with directory-index resolution enabled so that `/en/` serves `public/en/index.html`. This directory contains only static files, so the web server does not need to support PHP.

The live deployment runs nginx from [nginx.org's own repository](https://nginx.org/en/linux_packages.html#Ubuntu), with its configuration in `/etc/nginx/conf.d/netz-live.conf`. Two details there are worth keeping if you rewrite it: cache lifetimes are chosen with a `map` rather than an `add_header` inside each `location`, because an `add_header` in a location replaces the ones set on the server and would silently drop the security headers from those responses; and the HTML is served `no-cache`, since the update script rewrites it every five minutes and the page polls for a newer copy.

### Cron

Set up a cron job to execute the `update.php` script (using the [PHP CLI SAPI](https://www.php.net/manual/en/features.commandline.usage.php)) every five minutes. The cron job must run as a user with write access to `public/favicon.svg`, `public/index.html`, and `public/en/index.html`.

The script outputs details of the update process to standard output, and details of errors to standard error. An error with an individual data source does not abort the rest of the update process.

### Fonts

The site is set in a system font stack (`-apple-system`, `Segoe UI`, `Roboto`, `Helvetica Neue`, `Arial`) rather than the commercial Proza the upstream project uses. Proza Libre, the free version of Proza, and Source Sans 3 were both tried in turn — the former reads heavier than upstream since it starts at weight 400 where upstream's body text is 300, and the latter draws a visibly different question mark glyph on the help icons, which is subtle enough to be hard to place but stood out once several were on screen together. The system stack sidesteps both: no face to license, no glyph shapes to compare against Proza's, and it renders in whatever the visitor's own OS already uses for its interface.

### Cloudflare

Visit counts will be retrieved from Cloudflare if the `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID` environment variables are set to non-empty strings. The Cloudflare API token must be configured to provide Analytics Read access for the zone.

### Domain and banner

The canonical and `hreflang` URLs come from the `BASE_URL` constant at the top of [UI](classes/UI/UI.php); change it there if the site moves.

The original repository's `public/banner.png` (an Open Graph preview image reading "National Grid: Live") was replaced with `banner-de.png` and `banner-en.png`, drawn in the site's own palette, along with a matching `favicon.png` and `apple-touch-icon.png`.

## Codebase structure

PHP classes can be found in the `classes` directory. The [Database](classes/Database.php) class directly within this directory is responsible for all database access. The other classes are divided into three namespaces:

The [Data](classes/Data) namespace contains classes for reading data from the various data sources, as documented further below.

The [State](classes/State) namespace contains classes representing the data needed to output the user interface. The [State](classes/State/State.php) class is the overall container; an instance of this class is returned by the `getState()` method of a `Database` instance.

The [UI](classes/UI) namespace contains classes that output the user interface, including the [I18n](classes/UI/I18n.php) class, which holds the German/English translation strings and locale-aware number/date formatting. The [UI](classes/UI/UI.php) class has overall responsibility for outputting the HTML for a given locale, while the [Favicon](classes/UI/Favicon.php) class outputs the dynamically-updated favicon (shared between locales).

Unlike the original, which ingests data at mismatched 5-minute and 30-minute resolutions and so needs a raw tier that gets aggregated up to a half-hour tier, every German data source below is natively quarter-hourly, so this fork ingests directly into a single `past_quarter_hours` base table, which is then rolled up into `past_days`, `past_weeks`, and `past_years` exactly as in the original.

## Data sources

### [SMARD](https://www.smard.de/)

The electricity market data platform of the [Bundesnetzagentur](https://www.bundesnetzagentur.de/), the German federal network regulator. It publishes at 15-minute resolution, and its own documentation puts its target at one hour after the fact.

This fork originally read the same figures from the Energy-Charts API, which republishes them but does so around three hours later. Switching to the first-hand source cut the site's lag from over three and a half hours to around forty minutes; the values themselves are identical, having been checked series by series against Energy-Charts over a fortnight and agreeing to the last stored decimal.

Each series lives in its own file, one per calendar week, at `chart_data/{id}/DE/{id}_DE_quarterhour_{monday}.json`, where `{monday}` is the millisecond timestamp of midnight German local time on the Monday starting the week. Requests need a browser user agent, and the files carry an `ETag` that conditional requests ignore, so caching gains nothing; the [Smard](classes/Data/Smard.php) class instead fetches the thirty-odd files it needs in parallel, which takes a second or two.

Values are reported as megawatt hours produced within the quarter hour, so multiplying by four gives megawatts.

- **Generation** — lignite, hard coal, gas, biomass, solar, wind onshore/offshore, hydro, pumped storage, and the remainders SMARD groups as other renewable and other conventional. Pumped storage consumption is reported under consumption rather than generation, as a positive figure, and is stored negated.
- **Physical cross-border flows** with Germany's eleven interconnected neighbours (Austria, Belgium, Czech Republic, Denmark, France, Luxembourg, Netherlands, Norway, Poland, Sweden, Switzerland), each as a separate export and import series. Exports are positive and imports negative, both from Germany's point of view, so negating their sum gives the net import.

  Flows are used in preference to the scheduled commercial exchanges. Germany sits inside the Continental European synchronous grid, where power reaches a buyer along whichever lines carry it, so a sale to one neighbour can flow through another: measured over a day the two series agree on the country's overall balance to within a few hundred megawatts, but disagree per neighbour by a gigawatt or more, at times even in direction. Flows are what actually happened.

  They still trail the generation slightly, so they are written separately and the last known figures are carried forward over the quarter hours they don't reach, rather than holding the generation back.
- **Day-ahead auction price** for the DE-LU bidding zone, licensed CC BY 4.0. Settled the day before, so it runs ahead of the generation rather than behind it.

### The historic archive

SMARD's archive reaches back to the start of 2015, and [backfill.php](backfill.php) imports it in one pass — around twenty minutes for eleven and a half years. Without it the year and all-time views hold only as much as the site has been running for, and the wind records are whatever the past few weeks happened to produce.

Three things about the archive are worth knowing, since each one is a fact about the grid rather than a gap in the data:

- **Nuclear** ran until 15th April 2023, when Emsland, Isar 2 and Neckarwestheim 2 were disconnected. The column is imported for the years it ran and sits at zero afterwards; the regular update doesn't read the series at all, since SMARD stopped publishing weekly files for it in 2024. Leaving it out would understate the pre-2023 mix by around a tenth.
- **Norway and Belgium** only appear from late 2020, when NordLink and ALEGrO went into service. Earlier quarter hours are zero because there was no interconnector.
- **The price** comes from the joint Germany/Austria/Luxembourg bidding zone until it was split on 1st October 2018, and from DE-LU afterwards. Both series are read for every week and the German one wins where it exists, because neither ends on a Monday and a week straddling the split would otherwise come back empty.

Carbon intensity is imported from Energy-Charts year by year, which reaches back to 2015 as well, so the history carries official figures rather than calculated ones.

### [Energy-Charts](https://www.energy-charts.info/)

Run by the [Fraunhofer Institute for Solar Energy Systems ISE](https://www.ise.fraunhofer.de/). Only one figure is still read from here, because SMARD doesn't publish it:

- `/co2eq` — carbon intensity of German electricity generation

It arrives around three hours after the fact, where the generation it describes is barely an hour old. Rather than show a stale figure beside a current mix, [Emissions](classes/Data/Emissions.php) fills the remaining quarter hours in from the generation mix itself, and the official figure overwrites the calculation as soon as it arrives.

The emission factors were calibrated by fitting the official series against SMARD's mix over a fortnight, holding the renewables at zero and keeping each factor within the range its technology can plausibly take. The fitted values turn out to be direct combustion emissions rather than lifecycle ones, which is what the official series tracks: lignite at 1074 g/kWh and hard coal at 720 g/kWh sit where the literature puts them. Checked against 105 hours the fit had not seen, the calculation reproduces the official figure to a mean error of 7 g/kWh, with 99% of quarter hours within 20 g/kWh, against values ranging from 111 to 718.

PHP classes: [Smard](classes/Data/Smard.php), [Generation](classes/Data/Generation.php), [Emissions](classes/Data/Emissions.php), [Pricing](classes/Data/Pricing.php)

Unlike the UK original, Germany doesn't need a separate "embedded generation" data source: the generation figures already cover the whole country including distributed solar and wind, so there's no `Demand.php` equivalent.

## Future plans

Nuclear power isn't shown, since Germany's last three reactors shut down on 15th April 2023 and the series has reported nothing since. Battery storage isn't shown, for the same double-counting reason described in the original project (and because neither source reports a distinct battery series for Germany).

Following the original project's philosophy: the aim is a limited scope and a concise interface for the general public, not specialised analysis for energy industry experts.
