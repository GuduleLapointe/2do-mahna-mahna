# TODO

Ideas and planned improvements, roughly in order of priority. Not all of these will happen — time is the limiting factor.

---

## Near term

### Version 3.0 release

Both projects bump to **3.x** to align with the API v3 that ships with
this release (aggregator was 0.3, lsl-board was 1.6 — versions are
misleading and out of sync with each other and the API).

- Bump aggregator to 3.0.0
- Bump lsl-board (2do-board) to 3.0.0
- Update version references in README, changelog, and API version header
  (`X-2do-Api-Version`)
- Tag both repos

The build/refresh/deploy reorganization below is the last remaining task
before cutting the release.

---

### Build / refresh / deploy separation

Today `dev/start-server.sh` and the cron entangle build, data refresh and
deployment. Untangle them so each step has one job and a single owner.

**Naming**: rename `bundles/standalone/` → `bundle/standalone/` as part of this work.
Multiple distributable bundles are planned (standalone mini-site, LSL
board script, future WordPress plugin), so `bundle/` is the shared root
and each bundle gets its own subdirectory:

```
bundle/
├── standalone/   ← current bundles/standalone/ (mini-site deployed to webroots)
├── lsl-board/    ← current 2do-board project (submodule or subtree)
└── wordpress/    ← future WordPress plugin
```

`bundle/` as a whole is gitignored. Each subdirectory has its own build
command and deploy target.

Data files (events.json, events.lsl*, events.ics) live in `data/`,
**not** inside any bundle subdirectory. The aggregator writes only to
`data/`; it never touches `bundle/standalone/` or any other bundle.
The standalone site reads data from a configured `data/` path at runtime.
Deploy rsync both `bundle/standalone/` and `data/` to their respective
target locations. The LSL board fetches from the API directly — it does
not get a local data copy.

**2do-board integration**

Currently `2do-board` lives as a sibling repo. As part of this
reorganisation, move it under `bundle/lsl-board/`:
- Option A: **git submodule** — keeps independent history and versioning,
  clean for contributors who only work on one side.
- Option B: **git subtree** — history merged, simpler for solo work,
  harder to split back out.

Either way, the LSL board's build step produces the distributable `.lsl`
scripts into `bundle/lsl-board/`, documented and downloadable from the
aggregator app.

**Golden rules**
- `src/` is the **only** place to edit. Everything that ends up on a deploy
  target has its source under `src/`.
- `bundles/standalone/` is **100% generated**, gitignored, safe to wipe at any moment. Standalone Sources are in `bundles/standalone/` (used only by the standalone app) or `app/Shared` (used by several modules, amongst main app, standalone and various scripts).
  Never edit anything inside it. Never let runtime code read from outside
  `bundles/standalone/`.
- App-internal code (aggregator engine, refresh, build scripts) lives
  outside `src/` (e.g. `app/`, `lib/`, `bin/`) — it must never need to be
  shipped to a standalone deploy.
- Test for placement: *« ce fichier doit-il exister sur un serveur de
  déploiement standalone ? »* — yes → `src/`, no → `app/`/`lib/`/`bin/`.

**Three steps, three commands**

1. **build** (manual, during development) — `src/` → `bundle/standalone/`
   - Minify `src/styles.css` → `bundle/standalone/styles.min.css`
   - Minify `src/script.js` → `bundle/standalone/script.min.js`
   - Process templates from `src/templates/` → `bundle/standalone/`
     (inject static sections, leave live-data placeholders intact)
   - Copy runtime PHP: `src/index.php`, `src/events.php` → `bundle/standalone/`
   - Copy runtime includes: `src/includes/*.php` → `bundle/standalone/includes/`
   - Copy static assets (`src/templates/events.lsl`, images, fonts, …)

2. **refresh** (cron + on demand) — fetch upstream → `data/`
   - Pull from configured event sources
   - Generate `data/events.json`, `events.lsl*`, `events.ics`
   - Never touches `bundle/standalone/` or any site code

3. **deploy** (cron + on demand) — `bundle/` → target(s)
   - rsync `bundle/standalone/` to the webroot
   - rsync `data/` to the data path the site reads from
   - No transformation, just transport

Cron runs **refresh + deploy** only — never `build` (build is a developer
action).

**Source layout changes**
- Move runtime includes into `src/`: `includes/bootstrap.php`,
  `includes/helpers.php` → `src/includes/`
- Audit remaining files at repo root: anything used only by aggregator/
  refresh/build belongs in `app/` (or `lib/`/`bin/`), not `src/`
- Create `src/templates/` and move into it: `src/index.html` → 
  `src/templates/calendar.html` (rename avoids confusion with the
  `index.php` front controller), `src/boards.html`, `src/events.lsl`

**`/` route in `index.php`**
- Use `include` of `calendar.html` rather than a redirect — user stays on
  the root URL instead of being bounced to `/calendar.html`

**Forward-looking (Laravel migration)**

Note: Laravel's own `bundles/standalone/` is the webroot of the app itself (depends on
`app/`, `config/`, `vendor/`, `storage/`) — it is **not** a standalone
bundle. The current `bundles/standalone/` of this project plays a different role: a
self-contained artifact meant to be deployed to third-party servers.

Mapping when migrating:
- Current `src/` (deployable runtime sources) → kept as-is at the repo
  root, or moved under `resources/bundle/` if going full-Laravel
  layout. Decision deferred to migration time. **Not** merged into
  Laravel's `app/`.
- Current `app/`/`lib/` (internal aggregator engine) → Laravel `app/`
- Current `bundles/standalone/` (standalone bundle output) → `bundle/standalone/`,
  generated by an artisan command (e.g. `php artisan aggregator:build`).
  Entire `bundle/` is gitignored, same wipe-and-regen contract.
- Refresh → scheduled jobs (`app/Console/Commands/`)
- Deploy → unchanged (rsync `bundle/standalone/` to targets)

**Composer autoload — keep the two universes strictly separate**

Today `composer.json` declares `"autoload": { "classmap": ["src/"] }`.
Acceptable while the project is single-universe, but a trap once Laravel
is added: the app's autoloader would silently see every bundle class,
allowing `new SomeBundleClass()` from `app/` and breaking the standalone
contract by accident.

At migration time:
- Remove `src/` from the Composer autoload (the bundle PHP is loaded by
  its own front controller from `bundle/`, not via Composer).
- Laravel `app/` autoloads via its own PSR-4 mapping — independent.
- If something genuinely needs to be shared between the bundle and the
  app (rare, deliberate), put it in a dedicated `lib/` or `shared/`
  directory with its own PSR-4 namespace, and autoload **that** from
  Composer. Never `src/`.

Rule of thumb: a class accessible from both `app/` and any bundle must
live in neither — it lives in `lib/`/`shared/` or it doesn't exist.

---

### Fetcher source-agnostic refactor + PHAR build

These two tasks are linked: converting parsers from `shell_exec` subprocesses to PHP
includes is a prerequisite for PHAR compilation.

**Fetcher refactor**

Today `Fetcher` has two code paths: `fetch_ical()` and `fetch_opensimworld()`, with
source-specific logic baked in. The fetcher should be agnostic:

- Parsers become classes in `app/Services/Parsers/`, loaded via autoloader, not spawned subprocesses.
- Each source type maps to a parser class; `ical` is the default.
- The parser type is declared in `config/sources.csv` (new column, optional).
- Fetcher always calls `fetch_source($slug, $calendar)` → instantiates the right parser
  → gets back the same normalised array → creates `Event` objects the same way.
- No source-specific branches in the fetcher; new source types only need a new parser class.

`opensimworld` becomes a named parser that can be listed in `sources.csv` like any other
source. Future parsers (including ports of legacy Python parsers) follow the same interface.

**PHAR build**

Once parsers are includes (no subprocess calls), the aggregator can be compiled to a
standalone PHAR:

- Source: `src/bin/aggregator.php` (entry point) + all `app/` classes + `vendor/`
- Output: `bin/aggregator` (executable PHAR, produced by the build script)
- Config files (`sources.csv`, `exclude.txt`, etc.) remain external, read from CWD or a
  configurable path — standard PHAR behaviour.
- Goal: distributable single-file tool for anyone who wants just the aggregator, without
  the full app. Also the right foundation for future Laravel integration.

---

### Verify legacy config parameters

The LSL board notecard supports a set of configuration keys. Unknown keys now log a
debug message (already implemented), but the full list of supported keys has never
been audited against the old format documentation. Before the v2.0 release:

- Review all notecard keys from the legacy script (v1.x) and confirm each is handled
  or intentionally dropped in the new getConfig() parser.
- Document the final supported key list in the README / Configuration notecard template.

### Deduplication by ratio

Faces sharing the same ratio still send duplicate PNG and v3 requests.
Cache `ratio → UUID` and `ratio → clickmap` to avoid redundant HTTP calls
on multi-face prims.

---

## Medium term

### Update server

For automatic LSL script update. 
- This is currently implemented through our own external "scrup" service. We can use the same architecture or make a simpler one if applicable. 
- events.php should provide additional methods to advertise and serve script updates.
- When the full app is implemented, this would happen through API endpoint(s)

### Analytics / observability

Long-deferred but increasingly useful. Two distinct angles, often
confused:

**Usage analytics — who consumes the server**
- Number of distinct boards (UUID from board self-identification)
- Number of distinct regions
- Number of distinct grids
- Request volume per API version (v2/v3) and per format (lsl2/png/json)
- Error count and breakdown on the serving side (HTTP 4xx/5xx)

  Where to read from: the `sendSimInfo` hook already pushes
  board/region/grid info on each request — log it instead of discarding.
  Webserver access logs for raw volume.

  Privacy note: in OpenSim nothing is really anonymous (grid URIs,
  region names, board UUIDs are all public on the wire), but
  anonymising or aggregating before storage is cheap and worth doing —
  no reason to keep more than we need.

**Calendar source health — what we ingest**

The aggregator pulls from the sources listed in `config/sources.csv`.
We currently throw away everything we learn about them. Worth tracking:
- Fetch success / failure per source, with last-seen-good timestamp
- Number of events returned per fetch, and how it drifts over time
- Frequency of changes (sources that never update vs. constantly churn)
- Parse errors and format anomalies

This is what tells us when a feed silently dies or a grid stops
publishing — currently invisible.

**Open questions (apply to both)**
- Storage: flat log + periodic aggregation, SQLite, or defer to a real
  DB once we move to Laravel?
- Surface: admin-only dashboard, public stats page, or both? Some grid
  admins may want their own slice (see `Grid filtering` below).

### Additional themes

A few radically different themes beyond color variations. Implemented the same way as `dark` (overrides in `$themes[]` in `bootstrap.php`), but with more personality.

Ideas:
- **Retro** — warm amber/green phosphor palette, monospace font, terminal aesthetic
- **Playful** — bright saturated colors, rounded feel, event-poster vibe
- **Sci-fi** — dark background, cyan/magenta accents, futuristic typography

Aim for 3–4 themes that look immediately distinct from the default and from each other.

---

## Longer term

### Grid filtering

Allow a grid admin to pass a grid URL parameter that filters events to only those hosted on their grid:
- URL parameter: `?grid=yourgrid.org:8002`
- Returns only events whose location is on that grid
- Useful for grids that want to embed a local-only board

Bonus: a grid admin token (in `.env`) could allow publishing grid-specific URLs with no public exposure of the filter.

### Local grid highlighting

In the board script, detect the current simulator's grid and mark events on the same grid with a distinct color (similar to how `ongoing` and `soon` events are already highlighted).

This requires the board to know its own grid address — probably via `osGetGridGatekeeperURI()` or similar — and pass it to the server (via `sendSimInfo` already exists as a hook) or compare locally after fetching the event list.

---

## Future / full app

A proper web application (possibly Laravel) to replace the current aggregator + static templates, enabling:

- **User-submitted events**: parcel owners can register events, with verification (only parcel owners may submit events for their location, similar to Second Life's event system)
- **Rich calendar views**: month / week / day, with filtering by category, grid, date range
- **Event moderation**: review queue before publication
- The existing aggregator feeds (iCal, JSON, LSL2, PNG) would remain as API outputs

This is a significant undertaking and depends on having more time than the current series of fixes.
