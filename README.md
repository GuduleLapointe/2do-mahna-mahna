# 2DO

![Version 3.0.0-dev](https://badgen.net/badge/Version/3.0.0/FFaa00)
![Stable 0.2.0](https://badgen.net/badge/3.0.0/Stable/00aa00)
![Requires PHP 8.2](https://badgen.net/badge/PHP/8.2/7884bf)
![License AGPLv3](https://badgen.net/badge/License/AGPLv3/552b55)

_Before download or install_ : the easiest way to use this is to get the teleport board in-world (hop://speculoos.world:8002:Lab) and ask us to include your calendar in [2do.directory](https://2do.directory/).

Aggregator is a tool to fetch events from various sources and export them 
on several format, for use by 2do Board and other applications related with
2do Events project.

Import formats:

- [x] iCal (ics, iCalendar)
- Custom web parsers:
  - [x] opensimworld
  - [x] thirdrock
  - gridtalk (to do)
  - OpenSim Fest (to do)

Export formats:

`/api/v3/events` is the main base of the different checkpoints.

- [x] `/api/v3/events/lsl` (default): csv list, including event details and click mapping coordinates for v3 in-world scripts
- [x] `/api/v3/events/png`: server-rendered board image for use with v3 scripts
- [x] `/api/v3/events/ics`: iCalendar format for use with web, mobile and desktop applications
- [x] `/api/v3/events/json`: JSON format for general use with custom scripts or apps
- [x] `/api/v2/events/lsl`: legacy "lsl2" plain-text event list for use with both v2 and v3 in-world scripts

Legacy pseudo-endpoints are supported for backwards compatibility with v2 scripts. They are served dynamically by the api with the same output as the main endpoints, and are also generated as fallback static files on each fetch job.

- [x] `events.lsl2`
- [x] `events.ics`
- [x] `events.json`
- [x] `events.lsl`: deprecation notice for even older legacy scripts
- [x] `static.html`: light standalone web calendar page for standalone use
- [x] `events.php`: light standalone app, for classic direct access without rewrite rules

### URL parameters (for api endpoints or standalone `events.php`)

| Parameter | Default | Description |
|-----------|---------|-------------|
| `format` | `lsl2` | (standalone only) Output format: `lsl2`, `png`, `clickmap` |
| `width` | `512` | Canvas width in pixels |
| `height` | `512` | Canvas height in pixels |
| `ratio` | `1.0` | Face aspect ratio (width/height); adjusts canvas dimensions |
| `not-before` | `7200` | Skip events starting within this many seconds (0 = include all upcoming) |
| `limit` | `100` | Maximum number of events to return |
| `theme` | `default` | Color theme (e.g. `dark`) |

Style overrides are accepted as `section-property` query parameters (e.g. `main-background`, `row-padding`, `hour-font-size`). Dimensions are scaled proportionally to canvas width (512 px reference).

This is a side PHP application intended to provide the same functionality as 2do-server, the original events fetcher of 2do project and the original HYPEvents code, but in a more modular and maintainable way, and with better integration with other tools of 2do Events, w4os and Flexiple Helper Scripts projects.

- 2do-board: the in-world board to display the calendar

For grid owners :
- [W4OS](https://w4os.org): a WordPress plugin to manage OpenSimulator grids and provide external helpers, including 2do Events
- [OpenSim Helpers](https://github.com/GuduleLapointe/opensim-helpers): a standalione collection of scripts providing the same helpers, without the web interface


## Getting started (recommended)

Don't install this app. Seriously. In most cases you don't need to install it, you can use ours.

- as a parcel owner, you only need the the in-world board, which is intended to be used on any grid. It is available here:

  - Speculoos grid, Lab region [speculoos.world:8002:Lab](hop://speculoos.world:8002/Lab/128/128/22)
  - or for scripters/builders: [2DO board Github repository](https://git.magiiic.com/opensimulator/2do-board),

- as grid or region owner, you can use 2do.directory (<https://2do.directory>) service to enable events search on your grid with a simple straight-forward configuration

- 2do.directory service is also included by [w4os WordPress Interface for OpenSimulator plugin](https://wordpress.org/plugins/w4os-opensimulator-web-interface/)

Jump directly to "Calendar conventions" below for the events format.

## Installation

_Installation is only relevant if you want to provide a custom-curated calendar. In most cases you don't need it — use [2do.directory](https://2do.directory) instead._

See **[INSTALLATION.md](INSTALLATION.md)** for full instructions (dependencies, web server config, cron setup).

## Calendar conventions

Events must have
- A title
- A start and end date/time
- A location composed of the region HG url. The url format is quite flexible, you can use variants, most recommended ones are hop links (`hop://yourgrid.org:8002/My+Region`) or user-friendly text (`yourgrid.org:8002/My_Region`).

They might also include
- A description (optional but recommended)
- A category (optional), following standards recognized by the viewers:
  - discussion
  - sports
  - live music
  - commercial
  - nightlife/entertainment
  - games/contests
  - pageants
  - education
  - arts and culture
  - charity/support groups
  - miscellaneous
  - These aliases are also recognized as variants of standard categories:
    - art (art and culture)
    - lecture (art and culture)
    - litterature (art and culture)
    - fair (nightlife/entertainment)
    - music (life music)
    - roleplay (games/contests)
    - social (charity/support groups)

### How to export Google Calendar as .ics

To get the url of your calendar in iCal format, move your mouse above the calendar you want to share, a three dots icon appears, select "Settings and Sharing" and scroll the page down to find Public iCal format adress. This is the value you need to copy as calendar ics url.


## Related projects

Events parsers for in-world search:
- [w4os Web interface for OpenSimulator](https://w4os.org) WordPress plugin for OpenSim grid management, providing also a collection of tools and helpers, including 2do services, It uses 2do.directory (see below) as default events source.
- [Flexible Helper Scripts](https://github.com/GuduleLapointe/flexible_helper_scripts) standalone collection of helpers included in w4os, without web management interface, including in-world search engine, currency, events, offline messaging. It uses 2do.directory as default events source.

Public calendars to use without installing this app:
- [2do.directory](https://2do.directory), the public 2do Events hypergrid directory.
- [OutWorldz OpensimEvents](https://github.com/Outworldz/OpensimEvents) another calendar based on HYPEvents/2do Events.

This project is the PHP port of python [2do-server](https://github.com/GuduleLapointe/2do-server), which is now deprecated.
