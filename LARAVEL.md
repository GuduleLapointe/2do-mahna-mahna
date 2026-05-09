# Laravel migration

## Main objective

Merge several related project into one single project, intended to be used 
- as a web app (database, Filament WebUI, registration, calendar submission, events submission...)
- as an API for OpenSimulator services
- still serve legacy OpenSimulator helpers for backwards compatibility

Currently, those services are provided by
- [opensim-helpers](https://github.com/GuduleLapointe/opensim-helpers): legacy opensim helpers
- [w4os](https://github.com/GuduleLapointe/w4os) opensim helpers integration as a WordPress plugin, with some additional helpers
- 2do-aggregator (this project): events fetching and parsing from several sources, to build a centralized calendar suitable for both helpers search features, web search, and OpenSim LSL scripts
- Scrup: LSL automatic script updates
- [OpenSim Maps](https://github.com/GuduleLapointe/opensimmaps) Virtual World interactive map for websites

### Future additions
- [Hyperactive NPC](https://github.com/GuduleLapointe/hyperactive-npc): chatbot server for use by in-world NPC
- [OpenAI Chatbot OSSL](https://github.com/GuduleLapointe/openai-chatbot-ossl) idem
- [Gudz Teleport Board](https://github.com/GuduleLapointe/Gudz-Teleport-Board-2) Recommended destinations guide for use by in-world scripts
- [OpenSim Rest Client](https://github.com/GuduleLapointe/opensim-rest-php) command-line interface to use with console-enabled simulators, as an alternative to screen/tmux workaround

## Analysis

For now, we have something that only appears to work. The tests pass, the static files are created, the events table is populated, and the API works, but the data is inconsistent when compared to the data from the old API (globalPos contains a local position instead of the map position, the simname region is missing, the simname is URL-encoded, the simname is empty, the event name is different, etc.).

- logs/compare-legacy-and-new-results.sh.live.dump

- logs/compare-legacy-and-new-results.sh.local.dump

Perhaps it would be simpler to debug this with Artisan fixtures rather than starting to write new code to create the same result?

Until now, opensim-helpers was an ecosystem alongside which 2do-events is grafted. But most of what OpenSim-Helpers handles also relates to 2do-Events: regions and places (parcels within regions).

Regions are divided into one or more "parcels" (also called "places" or "lands"), each with a different name and potentially different owners, parameters, and a reference image. The "landing point" (pos) is relative to the region, usually with x and y values less than 256, and determines which parcel the destination belongs to. It is from this parcel that the destination's name and image are obtained. The region itself has a "globalPos" (x, y[, z]) which is not a landing point: it determines the position of the entire region on the virtual world map, usually large numbers, and should not be confused with the destination's arrival point, which is relative to the region.

Required by 2do:
- eventsparser.php: absorbed into the aggregator
- query.php: all searches (events, but also places, land for sale, classifieds)

Indirectly related:
- register.php: the endpoint called by OpenSimulator to publish parcels in the search engine
- parser.php: the cron task, the "regions/parcels" counterpart of the Events fetcher, collects details of all parcels declared by OpenSimulatro via register.php, when "Enable Search" is set for the parcel in the viewer. It uses a mechanism similar to Region->data(); by centralizing, there would only be one call for data caching for each Region.

- guide.php: a favorite destinations page, uses information from the parser
- maps.php: a map of the grid's regions (this uses globalPos values to position each region globally)

The following only concerns the grid implementing the helpers, which are necessary to provide a complete ecosystem and are typically accessible from the same helpers/ path:

- currency.php: in-world financial transactions
- landtool.php: parcels tool linked to financial transactions, an independent helper
- offline.php: management of offline instant messaging

Additional services currently provided by the w4os plugin, but which should be moved to the helpers so that the helpers become the "One source of truth" that w4os uses as a library, and so that w4os itself only manages the interface between the helpers and WordPress:

- web search: a web page providing the same functionality as the "legacy" in-world search, but with a more polished look (first (Search palette tab in the viewer), and easily integrated into a website
- web profile: profile page for an avatar, accessible from web search
- grid info: widget containing grid name and login URI
- grid status: widget containing stats (status, members, active members, regions, area, etc.)
- web assets: web bridge for in-world assets (images)
- splash: welcome page, displayed when the viewer opens
- avatar registration, including avatar name, email verification, initial outfit
- popular place: similar to guide or search results, but sorted by frequentation

Other scripts in opensim-helpers:
- textgen.php: LSL tool only, not used by the OpenSim server, certainly improvable using the methods we used for PNG output of events.
- directory_info.php: installation script only, to be integrated into an installation wizard

2do therefore depends on helpers to be fully operational, and helpers need to be managed together for best efficiency, which is why I want to integrate not only events, the fetcher, the aggregator and the renderers, but also the other helpers into a single global "2do" project. However, the traditional endpoints must still be available at customisable addresses, to accommodate recommended in Robust.HG.ini, OpenSim.ini, or the OpenSim Helpers and w4OS documentation:

- **in-world search**: customisable in OpenSim.ini `[Search] SearchURL` (${Const|BaseURL}/query.php) used by the viewer internal search system
- **web search**: customisable in Robust.HG.ini `[LoginService] SearchURL` (${Const|WebURL}/search/") web page search, used in V3 viewer specific web search tab, or directly from the website
- **search registration**: customisable in OpenSim.ini `[DataSnapshot] DATA_SRV_ServiceName`, (DATA_SRV_2do = "https://2do.directory/helpers/register.php")
- **offline **: customisable in OpenSim.ini `[Messaging] OfflineMessageURL` (${Const|BaseURL}/Offline.php)
- **economy_path**, (customisable) where the viewer will look for `currency.php` and `landtool.php` (hardcoded in the viewer) - `[GridInfoService] economy` (${Const|BaseURL}/economy). currency.php and landtool.php can be alonside other helpers, as long as they are together
- **welcome**: splash page, `[GridInfoService] welcome` (${Const|BaseURL}/welcome)

... etc.

References:
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


## Proposed folder structure:

2do-project
├── app						# Standard Laravel folder structure
├── bootstrap
├── config
├── database
├── public
├── resources
├── routes
├── storage
├── tests
├── vendor
├── dev						# Our additional folders
├── tmp
├── src		# Might not be needed anymore, as builds would be made from portions of 
│			the actual app and some content from resources/ folder
├── bundle
│   ├── helpers				# standalone helpers (current bundle/standalone)
│   │   ├── currency.php	  
│   │   ├── events.php
│   │   ├── guide.php
│   │   ├── index.php
│   │   ├── landtool.php
│   │   ├── offline.php
│   │   ├── parser.php
│   │   ├── query.php
│   │   ├── register.php
│   │   └── ...
│   ├── lsl					# LSL scripts folder, on top level to be easily findable
│   │   ├── 2do-board		# current src/bundle/lsl-board/
│   │   └── ...
│   └── ...
└── ...
