# TODO

Ideas and planned improvements, roughly in order of priority. Not all of these will happen — time is the limiting factor.

---

## Near term

### Clickmap implementation

The clickmap format (`events.php?format=clickmap`) exists but does not work yet.
It is not a separate renderer option — it is required alongside the PNG renderer for touch-to-teleport to work the same way it does in the current LSL/osDraw renderer.

The flow should be:
1. Board fetches the PNG via `format=png` and applies it to the face
2. Board also fetches `format=clickmap` (or both in a single call?) to get the y-coordinate map
3. On touch, the board reads `llDetectedTouchST()` to get the UV coordinates, maps them to an event row, and teleports

The clickmap output is: PNG URL on line 1, then one `hgurl~y_start~y_end` per event row (matching the rows drawn on the PNG).

The current LSL board already has a clickmap handler for the osDraw renderer — that logic needs to be adapted for the PNG renderer mode.

### Event deduplication

Some events appear multiple times:
- The source calendar lists the same event separately for each recurrence hour
- The same event appears in multiple source calendars

Merge rule: two or more events with the same teleport URL that start within 1 hour of each other are treated as one event. Keep the one with the earliest start time and the latest end time.

This should happen in the aggregation/parsing step, before any output format is generated.

---

## Medium term

### Additional themes

A few radically different themes beyond color variations. Implemented the same way as `dark` (overrides in `$themes[]` in `bootstrap.php`), but with more personality.

Ideas:
- **Retro** — warm amber/green phosphor palette, monospace font, terminal aesthetic
- **Playful** — bright saturated colors, rounded feel, event-poster vibe
- **Sci-fi** — dark background, cyan/magenta accents, futuristic typography

Aim for 3–4 themes that look immediately distinct from the default and from each other.

### Configurable teleport method

Currently touching an event always does a direct teleport (`osTP...`).
Add an option to show the map instead (`llMapDestination()`), so the user can decide before jumping.

Configuration notecard key: `teleportMode = direct | map`

Default: `direct` (current behavior).

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
