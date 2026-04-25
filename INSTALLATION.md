# Installation

## Requirements

- **PHP 8.2 or later.** Only PHP versions under active support are supported
  (see <https://www.php.net/supported-versions.php>).
- **dependencies** see below for your architecture

## Setup

1. Install system dependencies (see below for your OS)
2. `composer install --no-dev`
3. Copy and edit config files:
   ```bash
   cp config/sources.csv.example config/sources.csv   # calendar sources (required)
   cp config/targets.example     config/targets        # rsync targets   (optional)
   cp config/exclude.txt.example config/exclude.txt    # event exclusions (optional)
   ```
4. Configure your web server (see [Web server](#web-server) below)
5. Run `./aggregator.php public/` and check the output
6. Run `./cron.sh` and verify it syncs correctly
7. Schedule the cron job (see [Scheduling](#scheduling-cron) below)

## TL;DR — install everything at once

### Debian / Ubuntu

```bash
PHP=php8.2   # adjust to your PHP version (php8.2, php8.3…)

sudo apt-get install -y \
    $PHP $PHP-cli \
    $PHP-mbstring \
    $PHP-xml \
    $PHP-curl \
    $PHP-imagick \
    composer

```

> `php-json` is built into PHP 8.x and needs no separate package.  
> `php-iconv` is included in the base package on most distributions.

### macOS (Homebrew)

Homebrew PHP ships with most extensions built-in — including mbstring, xml, dom, json, curl, iconv.
`imagick` is the only one that requires a separate install:

```bash
brew install php imagemagick pkg-config
sudo pecl install imagick
```

After each `pecl install`, add the extension to your `php.ini` if it was not done automatically:

```bash
# Find the right php.ini
php --ini | grep "Loaded Configuration"

# Add any missing lines
echo "extension=imagick.so" | sudo tee -a /path/to/php.ini
```

Restart your web server or PHP-FPM after editing `php.ini`.

---

## Composer dependencies

Install PHP library dependencies (required by all scripts):

```bash
composer install --no-dev
```

---

## Why each extension is needed

| Extension | Required by | Notes |
|-----------|-------------|-------|
| **php-mbstring** | `export-html.php`, `export-hypevents.php`, `functions.php`, `events.php` | UTF-8 string handling |
| **php-xml** / **php-dom** | `export-html.php` | DOM manipulation to build `index.html` |
| **php-curl** | Symfony HTTP client (Composer) | Fetching remote calendars and web sources |
| **php-imagick** | `events.php` (PNG board image) | Font rendering via system fontconfig; no TTF path needed |
| **php-iconv** | `export-hypevents.php`, `events.php` | Character set conversion to ASCII |
| **phpxmlrpc/phpxmlrpc** | `includes/functions.php` | XML-RPC calls to OpenSim grid helpers. The native `xmlrpc_*` functions were removed in PHP 8.0; this Composer package provides a drop-in polyfill (`includes/library-xmlrpc.php`, shared with w4os). Installed automatically by `composer install`. |
| **php-json** | everywhere | Built into PHP 8.x — no package needed |

---

## Runtime dependencies (non-PHP)

| Tool | Used by | Required? | Install |
|------|---------|-----------|---------|
| `rsync` | `cron.sh` | Only if syncing to remote targets | pre-installed on most systems |
| `imagemagick` | `php-imagick` extension | Yes, pulled automatically by apt; must be explicit on macOS | `brew install imagemagick` |

## Development dependencies

Only needed to run the local dev server (`dev/start-server.sh`).

| Tool | Role | Install |
|------|------|---------|
| Symfony CLI | Local HTTPS dev server | <https://symfony.com/download> |
| `fswatch` | Auto-sync `src/` → `public/` on save | `brew install fswatch` (macOS) / `apt-get install fswatch` |

Both are optional: without Symfony CLI the script falls back to `php -S` (plain HTTP), and without `fswatch` you copy files manually.

---

## Web server

The aggregator writes its output to a directory (default `public/`). That directory
must be served by a web server. Assuming `/var/www/html` is your document root and
the aggregator runs in `/opt/2do-aggregator`, this would produce:

- `http://yourgrid.org/events/` — web calendar page
- `http://yourgrid.org/events/events.lsl2` — source URL for the 2do Board
- `http://yourgrid.org/events/events.json` — source URL for events parsers (w4os, Flexible Helper Scripts)

### events.php vs events.lsl2

The aggregator also deploys `events.php` alongside the static files. It reads
`events.json` at request time and returns only current and upcoming events, filtered
server-side. It is the recommended URL for the 2do Board because:

- The static `events.lsl2` grows without bound. The LSL board script has a hard
  `HTTP_BODY_MAXLENGTH` of 4096 bytes; once the file exceeds that limit, the board
  silently receives a truncated response and appears empty.
- If the cron job hasn't run recently, the static file may be stale. `events.php`
  always reflects what is current.

### Apache configuration

Alias `events.lsl2` to `events.php` so the board always gets the dynamic version:

```apache
Alias /events/events.lsl2 /opt/2do-aggregator/public/events.php
```

### events.php URL parameters

All parameters are optional.

| Parameter       | Default | Description |
|-----------------|---------|-------------|
| `format`        | `lsl2`  | Output format: `lsl2`, `png`, `clickmap` |
| `limit`         | `20`    | Maximum events returned (0 = unlimited) |
| `not_before`    | `7200`  | Seconds before now still included (matches the board's 2-hour window) |
| `textureWidth`  | `512`   | PNG output width in pixels |
| `textureHeight` | `512`   | PNG output height in pixels |
| `ratio`         | `1.0`   | Board face aspect ratio width/height (e.g. `0.75` for a 1.5×2 board) |
| `theme`         | `light` | Colour palette: `light` or `dark` |
| `bannerHeight`  | `36`    | Footer strip height in canvas pixels |
| `lineHeight`    | `40`    | Event row height in canvas pixels |
| `mainFontName`  | `Roboto`| Title font name (resolved by system fontconfig) |
| `mainFontSize`  | `11`    | Title font size in points |
| `hourFontName`  | `mainFontName` | Time column font name |
| `hourFontSize`  | `9`     | Time column font size in points |

---

## Verify your installation

```bash
php -m | grep -E "mbstring|xml|dom|curl|imagick|iconv|json"
```

Expected output (order may vary):

```
curl
dom
iconv
imagick
json
mbstring
SimpleXML
xml
xmlrpc
```

Run the aggregator manually once to confirm everything works:

```bash
php aggregator.php public/
```

---

## Scheduling (cron)

The aggregator should run periodically to keep the output fresh.
Running it more than once per hour is rarely useful.

### Linux — crontab

```bash
crontab -e
```

Add one of these lines:

```cron
# Every hour at minute 0
0 * * * * /opt/2do-aggregator/cron.sh

# Every 4 hours
0 */4 * * * /opt/2do-aggregator/cron.sh
```

### Linux — drop-in cron directory (requires root)

```bash
# Hourly
sudo ln -sf "$PWD/cron.sh" /etc/cron.hourly/2do-aggregator

# Daily
sudo ln -sf "$PWD/cron.sh" /etc/cron.daily/2do-aggregator
```

### macOS — crontab

`crontab` works on macOS the same way as on Linux:

```bash
crontab -e
```

```cron
0 * * * * /opt/2do-aggregator/cron.sh
```

macOS may prompt for Full Disk Access the first time a cron job runs.
If the job is silently skipped, grant access to `/usr/sbin/cron` in
**System Settings → Privacy & Security → Full Disk Access**.

### macOS — launchd (recommended for macOS)

Create a plist in `~/Library/LaunchAgents/` (runs as your user) or
`/Library/LaunchAgents/` (runs for all users):

```bash
cat > ~/Library/LaunchAgents/world.2do.aggregator.plist << 'EOF'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN"
  "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
    <key>Label</key>             <string>world.2do.aggregator</string>
    <key>ProgramArguments</key>  <array><string>/opt/2do-aggregator/cron.sh</string></array>
    <key>StartInterval</key>     <integer>3600</integer>  <!-- seconds: 3600 = hourly -->
    <key>RunAtLoad</key>         <false/>
    <key>StandardOutPath</key>   <string>/tmp/2do-aggregator.log</string>
    <key>StandardErrorPath</key> <string>/tmp/2do-aggregator.log</string>
</dict>
</plist>
EOF

launchctl load ~/Library/LaunchAgents/world.2do.aggregator.plist
```

To stop and remove the scheduled job:

```bash
launchctl unload ~/Library/LaunchAgents/world.2do.aggregator.plist
```
