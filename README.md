# Stromnetz: Live / German Grid: Live

This repository is a German fork of [National Grid: Live](https://grid.iamkate.com/) ([KateMorley/grid](https://github.com/KateMorley/grid)), a project by [Kate Morley](https://iamkate.com/). Same architecture and visual design, adapted to show the live status of **Germany's** electric power grid instead of Great Britain's: generation mix, price, carbon intensity and grid frequency, updated every fifteen minutes. The site is bilingual, with German at `/` and English at `/en/`.

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

The production environment does not use Docker, instead running directly on the server. PHP 8.3 or later and a recent version of MariaDB or MySQL are required. The live deployment runs PHP 8.5, and the development image is pinned to the same version so that a deprecation in a newer PHP surfaces locally rather than in production, which is how `curl_close()` being removed in 8.5 was found.

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

### Monitoring

`update.php` reports the failures it can see, but its worst one is invisible to it. SMARD once left a single generation series unpublished for eighteen hours; every run completed cleanly and logged nothing but `OK`, because a run that has nothing valid to write is not an error. The site simply stopped moving. Counting errors cannot catch that — only the age of the newest quarter hour can.

[watchdog.php](watchdog.php) is run from its own cron job, every thirty minutes, and reports when the data stops advancing for more than two hours, when the database is unreachable, and when there is no data at all. It remembers what it last reported, so a long outage alerts once rather than every half hour, and forgets once the problem clears, so a recurrence is reported immediately. It also touches `/var/lib/netz-live/watchdog.ok` on every clean check — a watchdog that speaks only when something is wrong otherwise looks identical whether all is well or it stopped running months ago.

Alerts go to Telegram if `ALERT_TELEGRAM_TOKEN` and `ALERT_TELEGRAM_CHAT` are set, and to a webhook if `ALERT_WEBHOOK` is, as a JSON `POST` with a `text` field. They are printed regardless, so cron captures them either way. With no channel configured the watchdog still works, but nobody is told — the deployment needs at least one.

### Backups

The eleven years of history can be rebuilt from SMARD in about eight minutes with `backfill.php`, so the dumps exist for what cannot: the accumulated visit counts and the wind records, both of which are only ever built up locally.

The live deployment dumps the database nightly to `/root/backups` via `/usr/local/bin/netz-live-backup`, keeping a week. Note that this protects against the data being corrupted, not against losing the machine; copying the dumps somewhere else is left to whoever runs it.

### Fonts

The site is set in [IBM Plex Sans](https://github.com/IBM/plex), subset from the official variable font to the characters these pages actually print and served from `public/ibm-plex-sans.woff2` (26 KB). It is licensed under the SIL Open Font License, a copy of which is in `public/IBM-PLEX-LICENCE.txt`.

Two earlier attempts are worth recording, because both failed for reasons that only show up on the page. Proza Libre, the free version of the commercial Proza the upstream project uses, has no weight below 400, so every page read heavier than upstream, whose body text is 300. Source Sans 3 fixed the weight but draws a visibly different question mark, which is hard to place on its own and unmistakable once several help icons sit on screen together.

IBM Plex Sans avoids both: it is variable, so one file covers 300 for the body and 400 for the headings, and its figures are tabular by default — every digit the same width — which is what keeps the columns of numbers from shuffling as the data updates. The width axis is pinned and the weight axis clipped to the range in use, and the subset carries the German diacritics, the euro sign, the true minus and the subscript two of CO₂, the last of which Google's own Latin subset leaves out.

### Cloudflare

Visit counts will be retrieved from Cloudflare if the `CLOUDFLARE_API_TOKEN` and `CLOUDFLARE_ZONE_ID` environment variables are set to non-empty strings. The Cloudflare API token must be configured to provide Analytics Read access for the zone.

Cloudflare only protects what cannot be reached around it. The site's own certificate is published in Certificate Transparency logs and internet-wide scanners record which address answers for it, so the origin's address should be assumed public rather than treated as a secret: an audit found the origin serving the full site to a direct request, which bypassed the CDN's caching, rate limiting and DDoS protection entirely.

Two things close that, both regenerated by `/usr/local/bin/netz-live-cloudflare` from the ranges Cloudflare publishes at [cloudflare.com/ips-v4](https://www.cloudflare.com/ips-v4) and `ips-v6`:

- `ufw` admits ports 80 and 443 only from those ranges. SSH stays open to everywhere, since locking it to the CDN would lock everyone out.
- `/etc/nginx/conf.d/zz-cloudflare.conf` restores the visitor's address from `CF-Connecting-IP`, trusting the header only from those same ranges so it cannot be forged, and turns off `server_tokens`.

The same file adds a `default_server` returning 444. Without one the site's own block answers for every name on those ports including the bare address, so anyone could point a domain of theirs at the machine and serve a copy of the site.

Keeping the firewall and the nginx list in one script is deliberate: two hand-maintained copies drift, and drift means either visitors logged as the CDN or real traffic refused outright.

Certificate renewal continues to work through this, which is worth knowing because it looks as though it should not: Let's Encrypt's validators are not Cloudflare addresses, but the record is proxied, so the challenge reaches the edge and is forwarded to the origin like any other request. Verified with `certbot renew --dry-run` after the firewall was narrowed. It does leave renewal depending on the CDN, which a DNS-01 challenge would not.

### Domain and banner

The canonical and `hreflang` URLs come from the `BASE_URL` constant at the top of [UI](classes/UI/UI.php); change it there if the site moves.

The original repository's `public/banner.png` (an Open Graph preview image reading "National Grid: Live") was replaced with `banner-de.png` and `banner-en.png`, drawn in the site's own palette, along with a matching `favicon.png` and `apple-touch-icon.png`.

## Codebase structure

PHP classes can be found in the `classes` directory. The [Database](classes/Database.php) class directly within this directory is responsible for all database access. The other classes are divided into three namespaces:

The [Data](classes/Data) namespace contains classes for reading data from the various data sources, as documented further below.

The [State](classes/State) namespace contains classes representing the data needed to output the user interface. The [State](classes/State/State.php) class is the overall container; an instance of this class is returned by the `getState()` method of a `Database` instance. [Prediction](classes/State/Prediction.php) also lives here, estimating the quarter hours that have happened but have not yet been published.

The [UI](classes/UI) namespace contains classes that output the user interface, including the [I18n](classes/UI/I18n.php) class, which holds the German/English translation strings and locale-aware number/date formatting. The [UI](classes/UI/UI.php) class has overall responsibility for outputting the HTML for a given locale, while the [Favicon](classes/UI/Favicon.php) class outputs the dynamically-updated favicon (shared between locales).

Unlike the original, which ingests data at mismatched 5-minute and 30-minute resolutions and so needs a raw tier that gets aggregated up to a half-hour tier, every German data source below is natively quarter-hourly, so this fork ingests directly into a single `past_quarter_hours` base table, which is then rolled up into `past_days`, `past_weeks`, and `past_years` exactly as in the original.

## Data sources

### [ENTSO-E Transparency Platform](https://transparency.entsoe.eu/)

The platform the European transmission system operators publish to, and where Germany's four — 50Hertz, Amprion, TenneT and TransnetBW — file their figures first. Generation and cross-border flows are read from here by the [Entsoe](classes/Data/Entsoe.php) class, which needs an API token in `ENTSOE_API_TOKEN`.

Reading the first-hand source rather than a republisher takes about half an hour off the age of the newest figure. Two details of the platform decide most of how that class is written:

- **The German control area is `10Y1001A1001A83F`, not the DE-LU bidding zone `10Y1001A1001A82H`**, which includes Luxembourg and so overstates the country by a couple of hundred megawatts. The bidding zone is the right domain for the price and the wrong one for generation.
- **Points are curve type A03**, a variable-length block: a point appears only where the value changes and holds until the next one or until the period ends. That is right for a border whose flow has not moved all day, and wrong for wind and solar, where a period declaring one point across hours means the operators have not published yet. Weather-driven types are therefore held for at most four quarter hours before the reading is treated as absent.

Read from here:

- **Generation** — lignite, hard coal, gas, biomass, solar, wind onshore/offshore, hydro, pumped storage, and the remainders grouped as other renewable and other conventional. Pumped storage consumption is reported separately and stored negated.

  A quarter hour is written only once every fast-moving type has reported. The ones that matter are decided by how quickly a source can move rather than how large it is — solar shifts around 928MW between quarter hours where hydro shifts 37 — so a mix missing solar is refused while one missing hydro is not. This is why gaps appear during an upstream outage instead of rows showing a collapse that never happened.
- **Physical cross-border flows** with Germany's eleven interconnected neighbours (Austria, Belgium, Czech Republic, Denmark, France, Luxembourg, Netherlands, Norway, Poland, Sweden, Switzerland).

  Flows are used in preference to the scheduled commercial exchanges. Germany sits inside the Continental European synchronous grid, where power reaches a buyer along whichever lines carry it, so a sale to one neighbour can flow through another: measured over a day the two series agree on the country's overall balance to within a few hundred megawatts, but disagree per neighbour by a gigawatt or more, at times even in direction. Flows are what actually happened.

  They still trail the generation slightly, so they are written separately and the last known figures are carried forward over the quarter hours they don't reach, rather than holding the generation back.

### [SMARD](https://www.smard.de/)

The electricity market data platform of the [Bundesnetzagentur](https://www.bundesnetzagentur.de/), the German federal network regulator, and the **fallback** for generation and flows: when ENTSO-E cannot be reached, [Generation](classes/Data/Generation.php) reads the same figures from here and prints `(SMARD fallback: …)` into the update log.

Being a republisher of ENTSO-E, it can never be fresher — measured over a calm night the two ran neck and neck, ENTSO-E ahead by a quarter hour at most — and during a platform outage both stall together, since they trace to the same submissions. The fallback earns its keep in the extremes rather than the average: on 27 August 2026 SMARD stalled five hours in the morning while ENTSO-E's API timed out through the afternoon, each covering the other.

Each series lives in its own file, one per calendar week, at `chart_data/{id}/DE/{id}_DE_quarterhour_{monday}.json`, where `{monday}` is the millisecond timestamp of midnight German local time on the Monday starting the week. Requests need a browser user agent, and the files carry an `ETag` that conditional requests ignore, so caching gains nothing; the [Smard](classes/Data/Smard.php) class instead fetches the thirty-odd files it needs in parallel. Values are megawatt hours produced within the quarter hour, so multiplying by four gives megawatts.

SMARD is also the source of the **day-ahead auction price** for the DE-LU bidding zone, licensed CC BY 4.0. Settled the day before, so it runs ahead of the generation rather than behind it.

### The historic archive

SMARD's archive reaches back to the start of 2015, and [backfill.php](backfill.php) imports it in one pass — about eight minutes for eleven and a half years. Without it the year and all-time views hold only as much as the site has been running for, and the wind records are whatever the past few weeks happened to produce.

Three things about the archive are worth knowing, since each one is a fact about the grid rather than a gap in the data:

- **Nuclear** ran until 15th April 2023, when Emsland, Isar 2 and Neckarwestheim 2 were disconnected. The column is imported for the years it ran and sits at zero afterwards; the regular update doesn't read the series at all, since SMARD stopped publishing weekly files for it in 2024. Leaving it out would understate the pre-2023 mix by around a tenth.
- **Norway and Belgium** only appear from late 2020, when NordLink and ALEGrO went into service. Earlier quarter hours are zero because there was no interconnector.
- **The price** comes from the joint Germany/Austria/Luxembourg bidding zone until it was split on 1st October 2018, and from DE-LU afterwards. Both series are read for every week and the German one wins where it exists, because neither ends on a Monday and a week straddling the split would otherwise come back empty.

Carbon intensity is imported from Energy-Charts year by year, which reaches back to 2015 as well, so the history carries official figures rather than calculated ones.

### [Energy-Charts](https://www.energy-charts.info/)

Run by the [Fraunhofer Institute for Solar Energy Systems ISE](https://www.ise.fraunhofer.de/). Three things are read from here, none of which ENTSO-E or SMARD publishes in a usable form:

- `/co2eq` — carbon intensity of German electricity generation
- `/frequency` — grid frequency, at one-second resolution
- `/public_power_forecast` — the wind and solar forecast

It arrives around three hours after the fact, where the generation it describes is barely an hour old. Rather than show a stale figure beside a current mix, [Emissions](classes/Data/Emissions.php) fills the remaining quarter hours in from the generation mix itself, and the official figure overwrites the calculation as soon as it arrives.

An official figure that disagrees with the one calculated from the same quarter hour's mix by more than 100 g/kWh is discarded as broken and the calculated one kept: the source occasionally emits a value with no relation to the generation it describes — on 29 August 2026 it jumped from 125 to 622 g/kWh while the mix stayed almost pure solar and wind. The check compares against the mix rather than an absolute ceiling, because a legitimate figure does reach 718 on a still, dark evening, and it is stateless, so a corrected figure is taken up on the next run.

The emission factors were calibrated by fitting the official series against the mix over a fortnight, holding the renewables at zero and keeping each factor within the range its technology can plausibly take. The fitted values turn out to be direct combustion emissions rather than lifecycle ones, which is what the official series tracks: lignite at 1074 g/kWh and hard coal at 720 g/kWh sit where the literature puts them. Checked against 105 hours the fit had not seen, the calculation reproduces the official figure to a mean error of 7 g/kWh, with 99% of quarter hours within 20 g/kWh, against values ranging from 111 to 718.

**Grid frequency** is the only figure on the page describing now rather than a finished quarter hour, and the only one never stored: averaging it into the quarter-hourly table would destroy the second-by-second movement that makes it worth showing. It is read fresh each run and kept just long enough to render, with a short-lived on-disk cache standing in for up to fifteen minutes when the endpoint rate-limits. It is also **not German** — the whole Continental European synchronous area turns in step, which is why the page says "Kontinentaleuropa".

**The forecast** covers solar and both winds, and exists because the measured mix is always an hour or so behind: a quarter hour must end before the operators can report it. When the delay passes an hour, [Prediction](classes/State/Prediction.php) estimates the quarter hours that have happened but have not been published, drawn as a dashed line inside a band whose width is the error measured for that line at that reach. Two things about it are deliberate: the forecast supplies only the *change* since the last confirmed reading, which the measured values then anchor, since revisions mostly move a forecast's level rather than its shape; and the estimates are never written to the database, so no confirmed figure can be displaced by one.

PHP classes: [Entsoe](classes/Data/Entsoe.php), [Smard](classes/Data/Smard.php), [Generation](classes/Data/Generation.php), [Emissions](classes/Data/Emissions.php), [Pricing](classes/Data/Pricing.php), [Forecast](classes/Data/Forecast.php), [Frequency](classes/Data/Frequency.php)

Unlike the UK original, Germany doesn't need a separate "embedded generation" data source: the generation figures already cover the whole country including distributed solar and wind, so there's no `Demand.php` equivalent.

## Future plans

Nuclear power isn't shown, since Germany's last three reactors shut down on 15th April 2023 and the series has reported nothing since. Battery storage isn't shown, for the same double-counting reason described in the original project (and because neither source reports a distinct battery series for Germany).

Following the original project's philosophy: the aim is a limited scope and a concise interface for the general public, not specialised analysis for energy industry experts.
