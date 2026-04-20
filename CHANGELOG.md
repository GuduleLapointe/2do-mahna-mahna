## Changelog

0.3.0
* events.php: dynamic LSL output filtered server-side to stay within the LSL
  HTTP_BODY_MAXLENGTH limit (4096 bytes), fixing the blank-board bug caused by
  the static events.lsl2 growing beyond that limit
* events.php: PNG board image renderer (format=png) for osSetDynamicTextureURL,
  modern mobile calendar look (white background, Google-Calendar-style palette)
* events.php: clickmap format for LSL touch-to-teleport with exact Y coordinates
* events.php: ratio parameter for aspect-ratio correction on non-square board
  faces — internal canvas composed at natural ratio, resampled to output size
* events.php: URL parameters aligned with LSL Configuration notecard names:
  textureWidth, textureHeight, bannerHeight, lineHeight, cellPadding,
  mainFontName, mainFontSize, hourFontName, hourFontSize
* events.php: anticipates 2do-board 1.6.0 (BOARD_VER updated)
* Font detection: Roboto, SF (macOS), Arial (Windows/macOS), DejaVu (Linux)
* Widow fix: day headers never appear without at least one event below them
* dev/start-server.sh: Symfony CLI dev server with fswatch auto-sync
* templates/examples.php: aspect-ratio preview page (4 ratios × 2 themes)
* Requires PHP 8.2+ (was 7.3)

0.2.0
* new cron script to schedule the calendar synchronization

0.1.6
* added fetcher cache to ease the developer's life
* fix mobile flex style
* fix mobile flex style
* removed the barely working disclaimer, transition is finished and it works if not perfectly, at least better than before
* set cache timeout to 55 minutes, to accomodate for 1 hour cron job
* updated fluid calendar style
* removed disclaimer, scraping engine is globally back to normal
* set stable to 0.1.6

0.1.5
* new exclusion list
* added opensimworld crawler
* added color differentiation for events happening today on the user time zone
* added dark theme
* fix undefined $source_tz
* fix date and week display according to selected timezone
* fix wrong date in html day block title
* fix section menu disabling timezone selector
* changed menu, set calendar first
* removed deprecated fabpot/goutte package, use Symfony BrowserKit and DomCrawler instead
* ignore composer.lock
* Merge branch 'master' of github.com:GuduleLapointe/2do-aggregator
* removed obsolete fetcher.cfg.example
* revert bug introduced in 9c0771cc93b74c2d54188d052db2b68428852bbf
* balance the layout by adjusting day columns width based on the number of events
* remove rss link until it is implemented
* removed debug output
* detect event hgurl from description if location is not set
* updated styles header padding
* updated FAQ

0.1.4
* added sections About (readme), FAQ and Changelog to web page
* added menu
* fix responsive display and text wrapping for small devices
* better checkbox display in lists
* use minified json, css and js

0.1.3
* pretty functional version
* import iCal (.ics) calendars
* export json (for events parser helper and web page)
* export hypevents format (extra light for 2do boards and lsl scripts)
* export iCal (.ics) for use with any calendar software
* export HTML, a basic web page with the calendar, with adjustable timezone
* responsive layout

0.1.3-dev-3
* added disclaimer to the web page
* added teleport links on the web page
* added real time SLTime clock
* added timezone selector
* added disclaimer to the web page
* added teleport links on the web page
* added real time SLTime clock
* added basic HTML export
* added timezone selector
* fix new lines in description
* renamed events.js as script.js
* sticky header
* updated disclaimer
* responive events block display grouped by week and day

0.1.3-dev-2
* added json format export
* added matthiasmullie/minify library
* fix wrong type for empty tags
* fix iconv(): Detected an illegal character in input string
* fix wrong end date in json export
* fix $array typo
* fix version
* include saved files in output
* don't include source in event tags
* disambiguation, renamed  categories as tags, to differientiate from OS/SL events category
* mv src/2do-server src/helpers dev/
* don't encode  to utf8 if it is already the good format
* minified json

0.1.2-dev
* added iCal export format

0.1.1-dev
* added json format export

0.1.0-dev-2
* more a proof of concept than a producion-ready app
* functional ics import
* functional hypevents export
* base classes for general import/export tasks
