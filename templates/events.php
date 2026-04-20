<?php
/**
 * 2do Events – dynamic event generator
 *
 * Reads events.json (same directory) and outputs filtered events in one of
 * three formats selected via ?format=:
 *
 *   lsl2      (default) Plain-text event list consumed by the LSL board script.
 *   png       PNG board image, intended for osSetDynamicTextureURL.
 *   clickmap  Plain text: PNG URL on line 1, then one "hgurl~y_start~y_end"
 *             line per visible event — used by the LSL script to map a touch
 *             Y coordinate to the correct event for teleport.
 *
 * Common parameters:
 *   not_before  Seconds before now still included (default: 7200 = 2 h,
 *               matching the LSL board's own notBefore window)
 *   limit       Max events returned (default: 20 for lsl2, 0 = unlimited for png/clickmap)
 *
 * PNG / clickmap parameters:
 *   width       Canvas width  in pixels (default: 512)
 *   height      Canvas height in pixels (default: 512)
 *   display_w   In-world object width  — used for aspect-ratio correction (default: width)
 *   display_h   In-world object height — used for aspect-ratio correction (default: height)
 *               When a square texture is applied to a non-square in-world surface the image
 *               gets stretched. Providing display_w / display_h pre-compresses content
 *               vertically so it appears undistorted. Example: a 1.5 × 2 m board with a
 *               512 × 512 texture needs display_w=1.5&display_h=2 (or any equivalent ratio).
 *   theme       Colour theme: dark (default) or light
 *   font        Absolute path to a TrueType font file (optional server-side override)
 *
 * Apache alias to serve this script at /events/events.lsl2:
 *   Alias /events/events.lsl2 /path/to/output/events.php
 *
 * LSL board configuration (boardURL option, enables PNG rendering mode):
 *   boardURL = https://2do.directory/events/events.php
 *   The LSL script appends &format=clickmap and &format=png as needed.
 *
 * Requires: PHP 8.2+, GD extension with FreeType support.
 */

define('BOARD_VER',    '1.5.5');
define('EVENTS_JSON',  __DIR__ . '/events.json');
define('SLT_TIMEZONE', 'America/Los_Angeles');

$get = isset($_GET) ? $_GET : [];

$format     = $get['format']     ?? 'lsl2';
$not_before = isset($get['not_before']) ? (int)$get['not_before'] : 7200;
$limit      = isset($get['limit'])      ? (int)$get['limit']
            : ($format === 'lsl2' ? 20 : 0);
$width      = isset($get['width'])     ? max(64, (int)$get['width'])   : 512;
$height     = isset($get['height'])    ? max(64, (int)$get['height'])  : 512;
$display_w  = isset($get['display_w']) ? (float)$get['display_w']      : $width;
$display_h  = isset($get['display_h']) ? (float)$get['display_h']      : $height;
$theme      = in_array($get['theme'] ?? '', ['dark', 'light']) ? $get['theme'] : 'light';

// Vertical scale factor for aspect-ratio correction.
// yscale = (display_w / display_h) × (canvas_h / canvas_w)
// For a 1.5 × 2 m board with a 512 × 512 texture: yscale = 0.75.
// All vertical canvas positions = logical_y × yscale.
// Font sizes follow the same scale so rendered glyphs appear correct in-world.
$yscale = ($display_w > 0 && $display_h > 0)
    ? ($display_w / $display_h) * ($height / $width)
    : 1.0;

// ── Load and filter events ────────────────────────────────────────────────────

$json      = @file_get_contents(EVENTS_JSON);
$raw       = $json ? (json_decode($json, true) ?: []) : [];
$notBefore = time() - $not_before;
$events    = array_values(array_filter($raw, fn($e) => strtotime($e['start']) >= $notBefore));

header('Cache-Control: no-cache, must-revalidate');

if ($format === 'png') {
    output_board_image($events, $limit, $width, $height, $yscale, $theme,
                       $get['font'] ?? null);
} elseif ($format === 'clickmap') {
    output_click_map($events, $limit, $width, $height, $yscale, $get);
} else {
    output_lsl2($events, $limit);
}

// ── Format: lsl2 ─────────────────────────────────────────────────────────────

function output_lsl2(array $events, int $limit): void {
    header('Content-Type: text/plain; charset=utf-8');
    $tz = new DateTimeZone(SLT_TIMEZONE);
    echo BOARD_VER . "\n";
    $n = 0;
    foreach ($events as $ev) {
        if ($limit > 0 && $n >= $limit) break;
        $title = sanitise_title($ev['title']);
        if (!$title) continue;
        $s   = strtotime($ev['start']);
        $e   = strtotime($ev['end']);
        $bdt = (new DateTime($ev['start'], new DateTimeZone('UTC')))->setTimezone($tz);
        $edt = (new DateTime($ev['end'],   new DateTimeZone('UTC')))->setTimezone($tz);
        echo $title . "\n"
           . implode('~', [$bdt->format('h:iA'), $bdt->format('Y-m-d'), $s,
                           $edt->format('h:iA'), $edt->format('Y-m-d'), $e])
           . "\n" . $ev['hgurl'] . "\n";
        $n++;
    }
}

// ── Format: clickmap ─────────────────────────────────────────────────────────
//
// Line 1 : URL of the matching PNG (same parameters, format=png).
// Lines 2+: hgurl~y_start~y_end  — one per visible event, in display order.
//           y_start / y_end are canvas pixel coordinates matching the PNG.

function output_click_map(array $events, int $limit, int $w, int $h,
                          float $yscale, array $get): void {
    header('Content-Type: text/plain; charset=utf-8');
    $rows = plan_board_rows($events, $limit, $w, $h, $yscale);

    $params = array_merge($get, ['format' => 'png']);
    $host   = $_SERVER['HTTP_HOST']   ?? 'localhost';
    $uri    = strtok($_SERVER['REQUEST_URI'] ?? '/events/events.php', '?');
    echo 'https://' . $host . $uri . '?' . http_build_query($params) . "\n";

    foreach ($rows as $row) {
        if ($row['type'] === 'event') {
            echo $row['hgurl'] . '~' . $row['y_start'] . '~' . $row['y_end'] . "\n";
        }
    }
}

// ── Format: png ──────────────────────────────────────────────────────────────

function output_board_image(array $events, int $limit, int $w, int $h,
                            float $yscale, string $theme, ?string $font_path): void {
    $rows = plan_board_rows($events, $limit, $w, $h, $yscale);
    $img  = imagecreatetruecolor($w, $h);
    render_board_image($rows, $img, $w, $h, $yscale, $theme, $font_path);
    header('Content-Type: image/png');
    header('Cache-Control: public, max-age=300');
    imagepng($img);
    imagedestroy($img);
}

// ── Row planner ───────────────────────────────────────────────────────────────
//
// Computes the canvas Y position of every visible row (day headers, event rows,
// bottom banner) without touching GD. Both output_board_image() and
// output_click_map() call this so their Y coordinates are always in sync.
//
// Returns an array of row descriptors. Relevant keys per type:
//   type = 'day_header' : label, is_today, y_start, y_end
//   type = 'event'      : event (raw array), hgurl, is_live, is_today,
//                         time_str, title, y_start, y_end,
//                         y_time, y_title, y_location  (text baselines, canvas px)
//   type = 'banner'     : y_start, y_end

function plan_board_rows(array $events, int $limit, int $canvas_w, int $canvas_h,
                         float $yscale): array {
    $tz  = new DateTimeZone(SLT_TIMEZONE);
    $now = time();
    $today = (new DateTime('now', $tz))->format('Y-m-d');

    $cy = fn(float $ly): int => (int)round($ly * $yscale);

    // Logical-pixel heights
    $banner_lh    = 36.0;
    $day_lh       = 22.0;
    $day_gap_lh   =  4.0; // extra gap before a new day section
    $event_lh     = 40.0;

    // Bottom of usable content area (leave room for banner)
    $max_canvas_y = $canvas_h - $cy($banner_lh);

    $rows     = [];
    $ly       = 6.0;
    $prev_day = null;
    $n        = 0;

    foreach ($events as $ev) {
        if ($limit > 0 && $n >= $limit) break;

        $start   = strtotime($ev['start']);
        $end     = strtotime($ev['end']);
        $is_live = ($start <= $now && $end > $now);

        $sdt = (new DateTime($ev['start'], new DateTimeZone('UTC')))->setTimezone($tz);
        $day = $sdt->format('Y-m-d');

        // Day header on day change — require room for header + at least one event
        if ($day !== $prev_day) {
            if ($prev_day !== null) $ly += $day_gap_lh;
            if ($cy($ly + $day_lh + $event_lh) > $max_canvas_y) break;
            $rows[] = [
                'type'     => 'day_header',
                'label'    => strtoupper($sdt->format('D j M')),
                'is_today' => ($day === $today),
                'y_start'  => $cy($ly),
                'y_end'    => $cy($ly + $day_lh),
            ];
            $ly      += $day_lh;
            $prev_day = $day;
        }

        // Event row
        if ($cy($ly + $event_lh) > $max_canvas_y) break;
        $title = sanitise_title($ev['title']);
        $rows[] = [
            'type'       => 'event',
            'event'      => $ev,
            'hgurl'      => $ev['hgurl'],
            'is_live'    => $is_live,
            'is_today'   => ($day === $today),
            'time_str'   => $sdt->format('g:ia'),
            'title'      => $title,
            'y_start'    => $cy($ly),
            'y_end'      => $cy($ly + $event_lh),
            'y_time'     => $cy($ly + 15),   // text baseline: time
            'y_title'    => $cy($ly + 15),   // text baseline: title
            'y_location' => $cy($ly + 29),   // text baseline: location
        ];
        $ly += $event_lh;
        $n++;
    }

    // Banner pinned to canvas bottom.
    $banner_h_px = $cy($banner_lh);
    $banner_y    = $canvas_h - $banner_h_px;
    $rows[] = [
        'type'     => 'banner',
        'y_start'  => $banner_y,
        'y_end'    => $canvas_h - 1,
        'banner_h' => $banner_h_px,
    ];

    return $rows;
}

// ── Board renderer ────────────────────────────────────────────────────────────
//
// Takes the row plan from plan_board_rows() and draws everything onto $img.
// No layout logic here — only GD drawing calls.

function render_board_image(array $rows, $img, int $w, int $h,
                            float $yscale, string $theme, ?string $font_path): void {
    $font      = ($font_path && file_exists($font_path)) ? $font_path : find_font();
    $font_bold = find_font(true);

    $fs = fn(float $lf): float => max(6.0, $lf * $yscale);

    // ── Colour palette ───────────────────────────────────────────────────────

    // Light palette: neutral white, Google-Calendar-style typography
    // Dark palette: true OLED dark, same accent blue
    $pal = ($theme === 'light') ? [
        'bg'         => [255, 255, 255],  // white
        'row_bg'     => [255, 255, 255],
        'live_bg'    => [232, 240, 254],  // very light blue tint for live
        'live_accent'=> [ 26, 115, 232],  // Google blue
        'title'      => [ 32,  33,  36],  // near-black #202124
        'time'       => [ 95, 100, 104],  // medium grey #5F6468
        'live_time'  => [ 26, 115, 232],  // Google blue
        'location'   => [128, 134, 139],  // light grey #80868B
        'day_text'   => [ 95, 100, 104],  // same as time
        'today_text' => [ 26, 115, 232],  // Google blue
        'separator'  => [232, 234, 237],  // #E8EAED
        'banner_bg'  => [248, 249, 250],  // #F8F9FA
    ] : [
        'bg'         => [ 18,  18,  18],  // true black (OLED)
        'row_bg'     => [ 30,  30,  30],
        'live_bg'    => [ 22,  38,  62],  // dark blue tint
        'live_accent'=> [ 66, 133, 244],  // Google blue on dark
        'title'      => [232, 234, 237],  // #E8EAED
        'time'       => [154, 160, 166],  // medium grey
        'live_time'  => [ 66, 133, 244],
        'location'   => [102, 109, 115],
        'day_text'   => [154, 160, 166],
        'today_text' => [ 66, 133, 244],
        'separator'  => [ 48,  48,  48],
        'banner_bg'  => [  0,   0,   0],
    ];

    $c = [];
    foreach ($pal as $k => [$r, $g, $b]) {
        $c[$k] = imagecolorallocate($img, $r, $g, $b);
    }

    // Background
    imagefilledrectangle($img, 0, 0, $w - 1, $h - 1, $c['bg']);

    // ── Draw rows ────────────────────────────────────────────────────────────

    $time_col_w = 60;  // width of time column (canvas px)

    foreach ($rows as $row) {

        if ($row['type'] === 'day_header') {
            $col = $row['is_today'] ? $c['today_text'] : $c['day_text'];
            draw_text($img, $font, $fs(8.5), $col,
                      8, $row['y_end'] - 3, $row['label'], $w);

        } elseif ($row['type'] === 'event') {
            $y0 = $row['y_start'];
            $y1 = $row['y_end'];

            // Card background
            $bg = $row['is_live'] ? $c['live_bg'] : $c['row_bg'];
            imagefilledrectangle($img, 0, $y0, $w - 1, $y1 - 1, $bg);

            // Live accent bar (left edge)
            if ($row['is_live']) {
                imagefilledrectangle($img, 0, $y0, 3, $y1 - 1, $c['live_accent']);
            }

            // Time
            $time_col = $row['is_live'] ? $c['live_time'] : $c['time'];
            draw_text($img, $font, $fs(9), $time_col,
                      7, $row['y_time'], $row['time_str'], $w);

            // Title
            $title_col = $c['title'];
            $title_x   = $time_col_w;
            $title_w   = $w - $title_x - 6;
            $title     = fit_text($row['title'], $fs(10.5), $font, $title_w);
            draw_text($img, $font, $fs(10.5), $title_col,
                      $title_x, $row['y_title'], $title, $w);

            // Location
            $loc = fit_text($row['hgurl'], $fs(7.5), $font, $title_w);
            draw_text($img, $font, $fs(7.5), $c['location'],
                      $title_x, $row['y_location'], $loc, $w);

            // Row separator
            imageline($img, $title_x, $y1 - 1, $w - 1, $y1 - 1, $c['separator']);

        } elseif ($row['type'] === 'banner') {
            $y0 = $row['y_start'];
            imagefilledrectangle($img, 0, $y0, $w - 1, $h - 1, $c['banner_bg']);
            imageline($img, 0, $y0, $w - 1, $y0, $c['separator']);
            draw_logo($img, $y0, $w, $y0 + $row['banner_h']);
        }
    }
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Load the board logo (2do-logo-trim.png) and centre it in the footer strip.
 * Falls back silently if the file is missing.
 */
function draw_logo($img, int $banner_y, int $canvas_w, int $banner_y_end): void {
    $logo_file = __DIR__ . '/2do-logo-trim.png';
    if (!file_exists($logo_file)) return;
    $logo = @imagecreatefrompng($logo_file);
    if (!$logo) return;
    $lw = imagesx($logo);
    $lh = imagesy($logo);
    $banner_h = $banner_y_end - $banner_y;
    $scale    = min(($banner_h - 6) / $lh, ($canvas_w * 0.6) / $lw);
    $dw = (int)round($lw * $scale);
    $dh = (int)round($lh * $scale);
    $dx = (int)(($canvas_w - $dw) / 2);
    $dy = $banner_y + (int)(($banner_h - $dh) / 2);
    imagecopyresampled($img, $logo, $dx, $dy, 0, 0, $dw, $dh, $lw, $lh);
    imagedestroy($logo);
}

/**
 * Draw text on $img. Pass $x = null to centre horizontally.
 * Falls back to GD built-in bitmap fonts when no TTF font is available.
 */
function draw_text($img, ?string $font, float $size, int $colour,
                   ?int $x, int $y, string $text, int $canvas_w): void {
    if ($text === '') return;
    if ($font) {
        $bbox = imagettfbbox($size, 0, $font, $text);
        $tw   = $bbox[2] - $bbox[0];
        $tx   = ($x === null) ? (int)(($canvas_w - $tw) / 2) : $x;
        imagettftext($img, $size, 0, $tx, $y, $colour, $font, $text);
    } else {
        $gf = max(1, min(5, (int)round($size / 3)));
        $cw = imagefontwidth($gf);
        $tx = ($x === null) ? (int)(($canvas_w - mb_strlen($text) * $cw) / 2) : $x;
        imagestring($img, $gf, $tx, $y - imagefontheight($gf), $text, $colour);
    }
}

/**
 * Truncate $text so it fits within $max_px canvas pixels wide.
 * Appends an ellipsis character (UTF-8) when truncation occurs.
 */
function fit_text(string $text, float $size, ?string $font, int $max_px): string {
    $ellipsis = "\xE2\x80\xA6"; // …
    if (!$font) {
        $gf  = max(1, min(5, (int)round($size / 3)));
        $max = max(1, (int)($max_px / imagefontwidth($gf)));
        return mb_strlen($text) > $max ? mb_substr($text, 0, $max - 1) . $ellipsis : $text;
    }
    $bbox = imagettfbbox($size, 0, $font, $text);
    if (($bbox[2] - $bbox[0]) <= $max_px) return $text;
    while (mb_strlen($text) > 1) {
        $text = mb_substr($text, 0, -1);
        $bbox = imagettfbbox($size, 0, $font, $text . $ellipsis);
        if (($bbox[2] - $bbox[0]) <= $max_px) return $text . $ellipsis;
    }
    return $ellipsis;
}

/** Strip emoji and force ASCII, matching the aggregator's own title sanitisation. */
function sanitise_title(string $title): string {
    $title = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $title);
    $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    return trim($title);
}

/**
 * Find a usable TrueType font file on the server (macOS, Linux, Windows).
 * Drop font.ttf / font-bold.ttf next to this script to override.
 */
function find_font(bool $bold = false): ?string {
    $b = $bold;
    $rb = $b ? 'Bold' : 'Regular';
    $candidates = [
        // Local override (place font.ttf / font-bold.ttf next to this script)
        __DIR__ . '/font' . ($b ? '-bold' : '') . '.ttf',
        // Linux — Roboto (most Android/web servers)
        "/usr/share/fonts/truetype/roboto/hinted/Roboto-{$rb}.ttf",
        "/usr/share/fonts/truetype/roboto/Roboto-{$rb}.ttf",
        "/usr/share/fonts/roboto/Roboto-{$rb}.ttf",
        // macOS — San Francisco (system UI font)
        '/System/Library/Fonts/SFNS.ttf',
        // macOS — Arial
        $b ? '/System/Library/Fonts/Supplemental/Arial Bold.ttf'
           : '/System/Library/Fonts/Supplemental/Arial.ttf',
        '/Library/Fonts/Arial Unicode.ttf',
        // Windows — Arial
        $b ? 'C:\\Windows\\Fonts\\arialbd.ttf'
           : 'C:\\Windows\\Fonts\\arial.ttf',
        // Linux — DejaVu Sans
        '/usr/share/fonts/truetype/dejavu/DejaVuSans' . ($b ? '-Bold' : '') . '.ttf',
        '/usr/share/fonts/dejavu/DejaVuSans'          . ($b ? '-Bold' : '') . '.ttf',
        // Linux — Liberation Sans / Ubuntu / FreeSans
        '/usr/share/fonts/truetype/liberation/LiberationSans' . ($b ? '-Bold' : '') . '-Regular.ttf',
        '/usr/share/fonts/truetype/ubuntu/Ubuntu-R.ttf',
        '/usr/share/fonts/truetype/freefont/FreeSans.ttf',
    ];
    foreach ($candidates as $p) {
        if (file_exists($p)) return $p;
    }
    return null;
}
