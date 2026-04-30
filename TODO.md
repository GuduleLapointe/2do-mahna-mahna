# TODO

Ideas and planned improvements, roughly in order of priority. Not all of these will happen — time is the limiting factor.

---

## Near term

### LSL board: invalid bannerImageURL crashes the script

Setting an invalid or unreachable URL for `bannerImageURL` causes the script to crash.
- Validate the URL format before applying it (basic check, not a full HTTP request)
- Fall back to the default image on failure
- Check whether a texture UUID is also accepted as an alternative to a URL
  (`osSetDynamicTextureURLBlendFace` vs `llSetTexture`)

---

### Version 3.0 release

Both projects bump to **3.x** to align with the API v3 that ships with
this release (aggregator was 0.3, lsl-board was 1.6 — versions are
misleading and out of sync with each other and the API).

- Bump aggregator to 3.0.0
- Bump lsl-board (2do-board) to 3.0.0
- Update version references in README, changelog, and API version header
  (`X-2do-Api-Version`)
- Tag both repos

---

### Fetcher source-agnostic refactor + aggregator PHAR

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

**Aggregator PHAR**

Once parsers are includes (no subprocess calls), the aggregator can be compiled to a
standalone PHAR:

- Logic: `app/Services/Aggregator.php` (used by both CLI and future web UI — no duplication)
- CLI entry point: thin wrapper in `src/bin/aggregator.php` (or standard Laravel CLI path), compiled into the PHAR
- Output: `bin/aggregator` (self-contained executable PHAR — installable standalone, same pattern as `bundle/standalone/index.php`)
- Config files (`sources.csv`, `exclude.txt`, etc.) remain external, read from CWD or a
  configurable path — standard PHAR behaviour.
- Goal: distributable single-file tool for anyone who wants just the aggregator, without
  the full app. Also the right foundation for future Laravel integration.

---

### Verify legacy config parameters

The LSL board notecard supports a set of configuration keys. Unknown keys now log a
debug message (already implemented), but the full list of supported keys has never
been audited against the old format documentation. Before the v3.0 release:

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

**Forward-looking (Laravel migration)**

Mapping when migrating:
- Current `app/` (internal aggregator engine) → Laravel `app/`
- Current `lib/` (external libraries imported as is from other projects) →   `lib/` or  `contrib/` even if used only by Laravel
- Current `bundle/standalone/` (standalone bundle output) → kept as-is, generated by
  an artisan command (e.g. `php artisan aggregator:build`).
- `bundle/` is tracked in git (already done — removed from .gitignore), same wipe-and-regen contract: no direct edit.
- Refresh → scheduled jobs (`app/Console/Commands/`)
- Deploy → unchanged (rsync `bundle/standalone/` to targets)

**Composer autoload**

`composer.json` already declares `"autoload": { "classmap": ["app/", "lib/"] }` — `src/`
is not autoloaded (bundle PHP loads via its own front controller). At migration time:
- Laravel `app/` autoloads via its own PSR-4 mapping — independent.
- Shared code between bundle and app stays in `app/Shared/`. Never `src/`.

This is a significant undertaking and depends on having more time than the current series of fixes.
