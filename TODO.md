# TODO

Ideas and planned improvements, roughly in order of priority. Not all of these will happen — time is the limiting factor.

---

## Near term

### Clickmap implementation

The clickmap format (`events.php?format=clickmap`) exists but does not work yet.
It is not a separate renderer option — it complements the PNG renderer so that touch-to-teleport works the same way it does in the classic osDraw renderer.

Because `llHTTPRequest` is asynchronous, the PNG and clickmap fetches can run in parallel with no coordination overhead. The PNG URL does not need to be embedded in the clickmap response.

**Deduplication by ratio** — each face has its own `ratio`, which determines canvas size, font/padding scaling, and — critically — how many events fit. Two faces with the same ratio will produce identical PNGs and identical clickmaps. Making one request per unique ratio instead of one per face avoids redundant HTTP calls.

- Group active faces by ratio before looping
- For each unique ratio: send one PNG request and one clickmap request
- Apply results to all faces sharing that ratio (see caching below)

**`refreshEvents()` (renamed from `doRequest`)**

- If `renderer=png`:
  1. Call `refreshTexturePNG()`, which groups faces by ratio and loops over unique ratios
  2. For each unique ratio: send the PNG request AND a clickmap request with the same parameters
  3. Both are async — no coordination needed
- Otherwise (classic renderer):
  1. Send `format=lsl2` request as now

**Texture UUID cache** — `osSetDynamicTextureURLBlendFace` returns a UUID for the generated texture. Store a `ratio => UUID` mapping. When applying a texture to faces that share the same ratio, use `llSetTexture(uuid, face)` for all faces after the first — no additional HTTP request or dynamic texture call needed. On refresh, pass the stored UUID as `dynamicID` so the texture slot is reused in place.

**Clickmap cache** — store clickmaps indexed by ratio (string key). Before requesting a clickmap for a face, check if one for that ratio already exists. If yes, reuse it directly.

**`http_response`**

- If PNG renderer: identify the ratio from the request key (pass `ratio` as a query param), store the returned UUID in the ratio→UUID cache, apply the texture to all faces with that ratio, store the clickmap in the ratio→clickmap cache
- If classic renderer: generate textures as now, then build a clickmap in the same format from the fixed row heights of the osDraw layout

**Unified clickmap format** — one `hgurl~y_start~y_end` per event row (coordinates are UV fractions 0.0–1.0, top to bottom). Indexed by ratio, looked up by face at touch time.

**`touch_end`**

Single handler for both renderers: read `llDetectedTouchFace()`, get its ratio, look up the clickmap for that ratio, read `llDetectedTouchST()` for the V coordinate, find the matching row, teleport.

In debug mode: print the resolved teleport URL to the owner instead of executing the teleport — allows precise validation of the coordinate mapping for both renderers and all faces.

### Event deduplication

Some events appear multiple times:
- The source calendar lists the same event separately for each recurrence hour
- The same event appears in multiple source calendars

Merge rule: two or more events with the same teleport URL that start within 1 hour of each other are treated as one event. Keep the one with the earliest start time and the latest end time.

This should happen in the aggregation/parsing step, before any output format is generated.

---

## Medium term

### Update server

For automatic LSL script update. 
- This is currently implemented through our own external "scrup" service. We can use the same architecture or make a simpler one if applicable. 
- events.php should provide additional methods to advertise and serve script updates.
- When the full app is implemented, this would happen through API endpoint(s)

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
