# Stromnetz: Live / German Grid: Live

This repository is a German fork of [National Grid: Live](https://grid.iamkate.com/) ([KateMorley/grid](https://github.com/KateMorley/grid)), a project by [Kate Morley](https://iamkate.com/). Same architecture and visual design, adapted to show the live status of **Germany's** electric power grid instead of Great Britain's: generation mix, price, and carbon intensity, updated every fifteen minutes. The site is bilingual, with German at `/` and English at `/en/`.

This fork is not currently deployed anywhere — it's code only. See "Production" below for what's needed to run it live.

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

### Cron

Set up a cron job to execute the `update.php` script (using the [PHP CLI SAPI](https://www.php.net/manual/en/features.commandline.usage.php)) every five minutes. The cron job must run as a user with write access to `public/favicon.svg`, `public/index.html`, and `public/en/index.html`.

The script outputs details of the update process to standard output, and details of errors to standard error. An error with an individual data source does not abort the rest of the update process.

### Cloudflare

Visit counts will be retrieved from Cloudflare if the `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID` environment variables are set to non-empty strings. The Cloudflare API token must be configured to provide Analytics Read access for the zone.

### Domain and banner

`classes/UI/UI.php` currently points canonical/`hreflang` URLs at a placeholder `netz-live.example` domain — update these once the site has a real home. The original repository's `public/banner.png` (an Open Graph preview image reading "National Grid: Live") was removed rather than left with the wrong branding; a replacement for this fork is a nice-to-have follow-up, as is a favicon refresh.

## Codebase structure

PHP classes can be found in the `classes` directory. The [Database](classes/Database.php) class directly within this directory is responsible for all database access. The other classes are divided into three namespaces:

The [Data](classes/Data) namespace contains classes for reading data from the various data sources, as documented further below.

The [State](classes/State) namespace contains classes representing the data needed to output the user interface. The [State](classes/State/State.php) class is the overall container; an instance of this class is returned by the `getState()` method of a `Database` instance.

The [UI](classes/UI) namespace contains classes that output the user interface, including the [I18n](classes/UI/I18n.php) class, which holds the German/English translation strings and locale-aware number/date formatting. The [UI](classes/UI/UI.php) class has overall responsibility for outputting the HTML for a given locale, while the [Favicon](classes/UI/Favicon.php) class outputs the dynamically-updated favicon (shared between locales).

Unlike the original, which ingests data at mismatched 5-minute and 30-minute resolutions and so needs a raw tier that gets aggregated up to a half-hour tier, every German data source below is natively quarter-hourly, so this fork ingests directly into a single `past_quarter_hours` base table, which is then rolled up into `past_days`, `past_weeks`, and `past_years` exactly as in the original.

## Data sources

### [Energy-Charts](https://www.energy-charts.info/)

This API, run by the [Fraunhofer Institute for Solar Energy Systems ISE](https://www.ise.fraunhofer.de/), publishes German electricity data sourced from ENTSO-E and the Bundesnetzagentur/SMARD.de, at 15-minute resolution.

- `/public_power` — generation by source (lignite, hard coal, gas, oil, biomass, waste, geothermal, solar, wind onshore/offshore, hydro run-of-river/reservoir/pumped storage, others)
- `/cbpf` — physical cross-border flows with Germany's eleven interconnected neighbours (Austria, Belgium, Czech Republic, Denmark, France, Luxembourg, Netherlands, Norway, Poland, Sweden, Switzerland)
- `/co2eq` — estimated carbon intensity of German electricity generation
- `/price` — day-ahead auction price for the DE-LU bidding zone (this specific dataset is licensed CC BY 4.0 from the Bundesnetzagentur/SMARD.de; see the `license_info` field returned by the API)

PHP classes: [Generation](classes/Data/Generation.php), [Emissions](classes/Data/Emissions.php), [Pricing](classes/Data/Pricing.php)

Unlike the UK original, Germany doesn't need a separate "embedded generation" data source: `/public_power` already reports full national generation including distributed solar and wind, so there's no `Demand.php` equivalent.

## Future plans

Nuclear power isn't shown, since Germany's last three reactors shut down on 15th April 2023 and the data source no longer reports it. Battery storage isn't shown, for the same double-counting reason described in the original project (and because Energy-Charts doesn't report a distinct battery series for Germany).

Following the original project's philosophy: the aim is a limited scope and a concise interface for the general public, not specialised analysis for energy industry experts.
