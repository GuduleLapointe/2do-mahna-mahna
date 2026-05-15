# 2do Mahna Mahna

![Version 3.0.0-dev](https://badgen.net/badge/Version/3.0.0/FFaa00)
![Stable 0.2.0](https://badgen.net/badge/3.0.0/Stable/00aa00)
![Requires PHP 8.2](https://badgen.net/badge/PHP/8.2/7884bf)
![License AGPLv3](https://badgen.net/badge/License/AGPLv3/552b55)

This project is the collection of several projects around OpenSimulator Helpers and tools, to allow better centralized management.

## Installation

**Important**: after downloading, cd to the application directory and execute:

```bash
composer run setup
```

## Main objective

Merge several related project into one single project, intended to be used 
- as a web app (database, Filament WebUI, registration, calendar submission, events submission...)
- as an API for OpenSimulator services
- still serve legacy OpenSimulator helpers for backwards compatibility

Currently, those services are provided by
- [opensim-helpers](https://github.com/GuduleLapointe/opensim-helpers): legacy opensim helpers
- [w4os](https://github.com/GuduleLapointe/w4os) opensim helpers integration as a WordPress plugin, with some additional helpers
- [2do-aggregator](http://github.com/GuduleLapointe/2do-aggregator): events fetching and parsing from several sources, to build a centralized calendar suitable for both helpers search features, web search, and OpenSim LSL scripts
- [Scrup](http://github.com/GuduleLapointe/scrup): LSL automatic script updates
- [OpenSim Maps](https://github.com/GuduleLapointe/opensimmaps) Virtual World interactive map for websites

### Future additions
- [Hyperactive NPC](https://github.com/GuduleLapointe/hyperactive-npc): chatbot server for use by in-world NPC
- [OpenAI Chatbot OSSL](https://github.com/GuduleLapointe/openai-chatbot-ossl) idem
- [Gudz Teleport Board](https://github.com/GuduleLapointe/Gudz-Teleport-Board-2) Recommended destinations guide for use by in-world scripts
- [OpenSim Rest Client](https://github.com/GuduleLapointe/opensim-rest-php) command-line interface to use with console-enabled simulators, as an alternative to screen/tmux workaround.

## Standalone helpers

Helpers are small web applications required either by Robust/OpenSim servers, the viewer, or both. They are traditionally available on the grid website/frontend, e.g:
    - example.org/helpers/
    - example.org/helper/
    - example.org/economy/

They must strictly follow the communication and storage standards they are built on, established by Robust/OpenSim, by the viewer (Firestorm,...) or by legacy alternatives, to allow complete and fluid compatibility.

**xmlrpc** (queried by Robust/OpenSim or the viewer)
- {helpers|economy}/currency.php // must always be in same path as landtool.php
- {helpers|economy}/landtool.php // must always be in same path as currency.php
- {helpers}/query.php // In-world search for land, 

**others** (queried by OpenSim)
- {helpers}/offline.php // Handles offline message forwarding (xml input/output)
- register.php: the endpoint called by OpenSimulator to publish parcels in the search engine ($_GET, no output)
- guide.php (html): a favorite destinations page, uses information from the parser
- web assets: web bridge for in-world assets (images)

**cron tasks**
- parser.php // crawls the registered region to collect search data
- eventsparser.php // crawls the events calendars (Deprecated merged in 2do aggregator)
- maps.php: a map of the grid's regions (this uses globalPos values to position each region globally)

**widgets** (used by v3 viewers or standalone pages)
- web search: a web page providing the same functionality as the "legacy" in-world search, but with a more polished look (first (Search palette tab in the viewer), and easily integrated into a website
- web profile: profile page for an avatar, accessible from web search
- grid info: widget containing grid name and login URI
- grid status: widget containing stats (status, members, active members, regions, area, etc.)
- splash: welcome page, displayed when the viewer opens
- avatar registration, including avatar name, email verification, initial outfit
- popular place: similar to guide or search results, but sorted by frequentation
- textgen.php: LSL tool only, not used by the OpenSim server, certainly improvable using the methods we used for PNG output of events.

## References

- lib/opensim-helpers/lib/0.9.3/dist-ini/OpenSim.ini.example
- lib/opensim-helpers/lib/0.9.3/dist-ini/Robust.ini.example
- lib/opensim-helpers/lib/0.9.2/Gloebit.ini.example
- lib/opensim-helpers/lib/MoneyServer/MoneyServer.ini.example
- lib/opensim-helpers/lib/MoneyServer/MoneyServer.OpenSim.ini.example
- [OpenSimSearch](https://github.com/kcozens/OpenSimSearch)
- [DTL/NSL Money Server for OpenSim](http://www.nsl.tuis.ac.jp/xoops/modules/xpwiki/?OpenSim%2FMoneyServer) (for Podex and generic currencies)
- [Gloebit](http://dev.gloebit.com/opensim/downloads/)
- [w4os](https://github.com/GuduleLapointe/w4os)
- [opensim-helpers 3.x-dev](https://github.com/GuduleLapointe/opensim-helpers/tree/3.x) unfinished, work in progress or to abandon
