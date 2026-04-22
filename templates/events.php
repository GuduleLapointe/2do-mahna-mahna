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
 * Parameters use the same names and units as the LSL board Configuration notecard
 * so that builders can copy values directly between the two.
 *
 * Common parameters:
 *   not_before    Seconds before now still included (default: 7200 = 2 h)
 *   limit         Max events returned (default: 20 for lsl2, 0 = unlimited for png/clickmap)
 *
 * PNG / clickmap — output resolution (power-of-2 values recommended by the viewer):
 *   textureWidth   Output image width  in pixels (default: 512)
 *   textureHeight  Output image height in pixels (default: 512)
 *
 * PNG / clickmap — board face aspect ratio:
 *   ratio   width/height of the board face (default: 1.0 = square).
 *           e.g. ratio=0.75 for a portrait 1.5×2 board, ratio=0.5 for 1×2.
 *
 *   The script composes content on an internal canvas at this ratio, then resamples
 *   to textureWidth × textureHeight. This compensates for the stretch that occurs
 *   when a square texture is applied to a non-square board face.
 *   Example: a 1.5×2 portrait board with a 512×512 texture:
 *     textureWidth=512&textureHeight=512&ratio=0.75
 *
 * PNG / clickmap — layout (pixels in the internal canvas):
 *   bannerHeight   Height of the logo/footer strip (default: 36)
 *   lineHeight     Height of each event row         (default: 40)
 *   cellPadding    Inner padding within event rows   (default: 0)
 *
 * PNG / clickmap — typography:
 *   mainFontName   Font family name searched in system paths, or absolute .ttf path
 *                  (default: first available among Roboto, SF, Arial, DejaVu…)
 *   mainFontSize   Title font size in points (default: 11)
 *   hourFontName   Font for the time column (default: mainFontName)
 *   hourFontSize   Time font size in points  (default: 9)
 *
 * PNG / clickmap — colours (RRGGBB web hex, no '#' — same convention as LSL):
 *   Colors override the theme defaults. Theme still sets all unspecified values.
 *   backgroundColorStarted   Background for events currently in progress
 *   backgroundColorSoon      Background for events starting within ~1 h
 *   colorSection             Section header text (default: brand/logo colour)
 *   backgroundColor          Default row background  (future)
 *   fontColor                Default text colour     (future)
 *
 * PNG / clickmap — convenience shortcut (not in LSL):
 *   theme          'light' (default) or 'dark' — sets the full colour palette
 *
 * Apache alias to serve this script at /events/events.lsl2:
 *   Alias /events/events.lsl2 /path/to/output/events.php
 *
 * Requires: PHP 8.2+, Imagick extension (fonts resolved by name via fontconfig).
 */

ini_set("display_errors", 1); # DEBUG
ini_set("display_startup_errors", 1); # DEBUG
error_reporting(E_ALL); # DEBUG
define("BOARD_VER", "1.6.0");
define("EVENTS_JSON", __DIR__ . "/events.json");
define("SLT_TIMEZONE", "America/Los_Angeles");

$get = isset($_GET) ? $_GET : [];

// ── Parameters (same names and units as the LSL Configuration notecard) ───────

$format = $get["format"] ?? "lsl2";
$not_before = isset($get["not_before"]) ? (int) $get["not_before"] : 7200;
$limit = isset($get["limit"])
	? (int) $get["limit"]
	: ($format === "lsl2"
		? 20
		: 0);

// Output resolution — power-of-2 values recommended by the viewer
$textureWidth = isset($get["textureWidth"])
	? max(64, (int) $get["textureWidth"])
	: 512;
$textureHeight = isset($get["textureHeight"])
	? max(64, (int) $get["textureHeight"])
	: 512;

// Aspect ratio of the board face (width / height). Default 1.0 = square.
// e.g. ratio=0.75 for a 1.5×2 board, ratio=0.5 for 1×2, ratio=0.667 for 2×3.
$ratio = isset($get["ratio"]) ? max(0.01, (float) $get["ratio"]) : 1.0;

// Layout (canvas pixels)
$bannerHeight = isset($get["bannerHeight"])
	? max(0, (int) $get["bannerHeight"])
	: 36;
$lineHeight = isset($get["lineHeight"])
	? max(10, (int) $get["lineHeight"])
	: 40;
$cellPadding = isset($get["cellPadding"])
	? max(0, (int) $get["cellPadding"])
	: 0;

// Typography
$mainFontName = $get["mainFontName"] ?? "Roboto";
$hourFontName = $get["hourFontName"] ?? $mainFontName;

$mainFontSize = isset($get["mainFontSize"])
	? max(6, (int) $get["mainFontSize"])
	: 11;
$hourFontName = $get["hourFontName"] ?? null; // defaults to mainFontName when null
$hourFontSize = isset($get["hourFontSize"])
	? max(6, (int) $get["hourFontSize"])
	: 9;

// Theme — sets default palette; individual colour params below override it
$theme = in_array($get["theme"] ?? "", ["dark", "light"])
	? $get["theme"]
	: "light";

// Colours (RRGGBB web hex, no '#' prefix — same convention as LSL)
// These override the theme defaults when provided.
$backgroundColorStarted = $get["backgroundColorStarted"] ?? null; // bg for ongoing events
$backgroundColorSoon = $get["backgroundColorSoon"] ?? null; // bg for events starting within 1 h
$colorSection = $get["colorSection"] ?? null; // section header text

$allFonts = \Imagick::queryFonts();
error_log("queryFonts(): found " . count($allFonts) . " fonts");
function setFont(?string $fontName)
{
	error_log(__FUNCTION__ . ": looking for $fontName");
	$patterns = ["$fontName", "$fontName*", "*$fontName*", "DejaVu-Sans"];
	foreach ($patterns as $pattern) {
		$fonts = \Imagick::queryFonts($pattern);
		if (!empty($fonts)) {
			error_log(__FUNCTION__ . ": found {$fonts[0]}");
			return $fonts[0];
		}
	}
	error_log(__FUNCTION__ . ": no match for $fontName");
	// return $allFonts[0] ?? "DejaVuSans";
}

$mainFontName = setFont($mainFontName) ?? "DejaVuSans";
error_log("mainFontName=$mainFontName");
$hourFontName = $hourFontName ?? $mainFontName;
$hourFontName = setFont($hourFontName) ?? $mainFontName;
error_log("mainFontName=$mainFontName");

// ── Load and filter events ────────────────────────────────────────────────────

$json = @file_get_contents(EVENTS_JSON);
$raw = $json ? (json_decode($json, true) ?: []) : [];
$notBefore = time() - $not_before;
$events = array_values(
	array_filter($raw, fn($e) => strtotime($e["start"]) >= $notBefore),
);

header("Cache-Control: no-cache, must-revalidate");

if ($format === "png") {
	$color_overrides = array_filter([
		"backgroundColorStarted" => $backgroundColorStarted,
		"backgroundColorSoon" => $backgroundColorSoon,
		"colorSection" => $colorSection,
	]);
	output_board_image(
		$events,
		$limit,
		$textureWidth,
		$textureHeight,
		$ratio,
		$theme,
		$mainFontName,
		$mainFontSize,
		$hourFontName,
		$hourFontSize,
		$bannerHeight,
		$lineHeight,
		$cellPadding,
		$color_overrides,
	);
} elseif ($format === "clickmap") {
	output_click_map(
		$events,
		$limit,
		$textureWidth,
		$textureHeight,
		$ratio,
		$get,
		$bannerHeight,
		$lineHeight,
		$cellPadding,
	);
} else {
	output_lsl2($events, $limit);
}

// ── Format: lsl2 ─────────────────────────────────────────────────────────────

function output_lsl2(array $events, int $limit): void
{
	header("Content-Type: text/plain; charset=utf-8");
	$tz = new DateTimeZone(SLT_TIMEZONE);
	echo BOARD_VER . "\n";
	$n = 0;
	foreach ($events as $ev) {
		if ($limit > 0 && $n >= $limit) {
			break;
		}
		$title = sanitise_title($ev["title"]);
		if (!$title) {
			continue;
		}
		$s = strtotime($ev["start"]);
		$e = strtotime($ev["end"]);
		$bdt = new DateTime($ev["start"], new DateTimeZone("UTC"));
		$bdt->setTimezone($tz);
		$edt = new DateTime($ev["end"], new DateTimeZone("UTC"));
		$edt->setTimezone($tz);
		echo $title .
			"\n" .
			implode("~", [
				$bdt->format("h:iA"),
				$bdt->format("Y-m-d"),
				$s,
				$edt->format("h:iA"),
				$edt->format("Y-m-d"),
				$e,
			]) .
			"\n" .
			$ev["hgurl"] .
			"\n";
		$n++;
	}
}

// ── Format: clickmap ─────────────────────────────────────────────────────────
//
// Line 1 : URL of the matching PNG (same parameters, format=png).
// Lines 2+: hgurl~y_start~y_end  — one per visible event, in display order.
//           y_start / y_end are pixel coordinates in textureWidth × textureHeight space.

function output_click_map(
	array $events,
	int $limit,
	int $texW,
	int $texH,
	float $ratio,
	array $get,
	int $bannerHeight,
	int $lineHeight,
	int $cellPadding,
): void {
	header("Content-Type: text/plain; charset=utf-8");
	[$cw, $ch] = natural_canvas($texW, $texH, $ratio);
	$rows = plan_board_rows(
		$events,
		$limit,
		$cw,
		$ch,
		$bannerHeight,
		$lineHeight,
		$cellPadding,
	);

	$params = array_merge($get, ["format" => "png"]);
	$host = $_SERVER["HTTP_HOST"] ?? "localhost";
	$uri = strtok($_SERVER["REQUEST_URI"] ?? "/events/events.php", "?");
	echo "https://" . $host . $uri . "?" . http_build_query($params) . "\n";

	// Scale Y from canvas space to texture output space
	foreach ($rows as $row) {
		if ($row["type"] === "event") {
			$y0 = (int) round(($row["y_start"] * $texH) / $ch);
			$y1 = (int) round(($row["y_end"] * $texH) / $ch);
			echo $row["hgurl"] . "~" . $y0 . "~" . $y1 . "\n";
		}
	}
}

// ── Format: png ──────────────────────────────────────────────────────────────

function output_board_image(
	array $events,
	int $limit,
	int $texW,
	int $texH,
	float $ratio,
	string $theme,
	?string $mainFontName,
	int $mainFontSize,
	?string $hourFontName,
	int $hourFontSize,
	int $bannerHeight,
	int $lineHeight,
	int $cellPadding,
	array $color_overrides = [],
): void {
	[$cw, $ch] = natural_canvas($texW, $texH, $ratio);
	$rows = plan_board_rows(
		$events,
		$limit,
		$cw,
		$ch,
		$bannerHeight,
		$lineHeight,
		$cellPadding,
	);
	$canvas = new Imagick();
	$canvas->newImage($cw, $ch, new ImagickPixel("white"));
	$canvas->setImageFormat("png");

	render_board_image(
		$rows,
		$canvas,
		$cw,
		$ch,
		$theme,
		$mainFontName,
		$mainFontSize,
		$hourFontName ?? $mainFontName,
		$hourFontSize,
		$color_overrides,
	);

	// Resample to requested texture resolution
	if ($cw !== $texW || $ch !== $texH) {
		$canvas->resizeImage($texW, $texH, Imagick::FILTER_LANCZOS, 1);
	}

	header("Content-Type: image/png");
	header("Cache-Control: public, max-age=300");
	echo $canvas->getImageBlob();
	$canvas->destroy();
}

// ── Row planner ───────────────────────────────────────────────────────────────
//
// Computes the canvas Y position of every visible row (section headers, event
// rows, bottom banner) without touching Imagick. Both output_board_image() and
// output_click_map() call this so their Y coordinates are always in sync.
//
// Events are first sorted by start time (the JSON source is not guaranteed to
// be ordered), then split into two sections:
//   - started  : start <= now  (top-level filter already enforces the time window)
//   - upcoming : start >  now  (grouped by day)
//
// Returns an array of row descriptors. Relevant keys per type:
//   type = 'section_header' : section ('started'|'day'), label,
//                             is_today (day only), y_start, y_end
//   type = 'event'          : event (raw array), hgurl, section, is_soon,
//                             is_today, time_str, title, y_start, y_end,
//                             y_time, y_title, y_location (text baselines, px)
//   type = 'banner'         : y_start, y_end

function plan_board_rows(
	array $events,
	int $limit,
	int $canvas_w,
	int $canvas_h,
	int $bannerHeight,
	int $lineHeight,
	int $cellPadding,
): array {
	$tz = new DateTimeZone(SLT_TIMEZONE);
	$now = time();
	$today = new DateTime("now", $tz);
	$today->format("Y-m-d");
	$soon_window = 3600; // flag upcoming events starting within 1 h as "soon"

	// Sort by start time — JSON source may be unordered or stale
	usort(
		$events,
		fn($a, $b) => strtotime($a["start"]) <=> strtotime($b["start"]),
	);

	$day_h = (int) round($lineHeight * 0.55); // section header height
	$day_gap = (int) round($lineHeight * 0.1); // gap between sections

	$max_y = $canvas_h - $bannerHeight; // bottom of usable content area

	$rows = [];
	$y = 6;
	$prev_section = null;
	$prev_day = null;
	$n = 0;

	foreach ($events as $ev) {
		if ($limit > 0 && $n >= $limit) {
			break;
		}

		$start = strtotime($ev["start"]);
		$sdt = new DateTime($ev["start"], new DateTimeZone("UTC"));
		$sdt->setTimezone($tz);
		$day = $sdt->format("Y-m-d");

		// Section: events with start in the past are "started"; the top-level
		// not_before filter already ensures they are within the display window.
		$section = $start <= $now ? "started" : "upcoming";
		$is_soon = $section === "upcoming" && $start - $now < $soon_window;

		// ── Section / day header ─────────────────────────────────────────────

		if ($section === "started" && $prev_section !== "started") {
			// "CURRENTLY" header before the first started event
			if ($y > 6) {
				$y += $day_gap;
			}
			if ($y + $day_h + $lineHeight > $max_y) {
				break;
			}
			$rows[] = [
				"type" => "section_header",
				"section" => "started",
				"label" => "CURRENTLY",
				"y_start" => $y,
				"y_end" => $y + $day_h,
			];
			$y += $day_h;
		} elseif ($section === "upcoming" && $day !== $prev_day) {
			// Date header on day change (upcoming events only)
			if ($prev_section !== null) {
				$y += $day_gap;
			}
			if ($y + $day_h + $lineHeight > $max_y) {
				break;
			}
			$rows[] = [
				"type" => "section_header",
				"section" => "day",
				"label" => strtoupper($sdt->format("D j M")),
				"is_today" => $day === $today,
				"y_start" => $y,
				"y_end" => $y + $day_h,
			];
			$y += $day_h;
			$prev_day = $day;
		}

		$prev_section = $section;

		// ── Event row ────────────────────────────────────────────────────────

		if ($y + $lineHeight > $max_y) {
			break;
		}
		$title = sanitise_title($ev["title"]);
		$pad = $cellPadding;
		$y_text = $y + $pad + (int) round(($lineHeight - $pad) * 0.6);
		$y_loc = $y + $pad + (int) round(($lineHeight - $pad) * 0.88);
		$rows[] = [
			"type" => "event",
			"event" => $ev,
			"hgurl" => $ev["hgurl"],
			"section" => $section,
			"is_soon" => $is_soon,
			"is_today" => $day === $today,
			"time_str" => $sdt->format("g:ia"),
			"title" => $title,
			"y_start" => $y,
			"y_end" => $y + $lineHeight,
			"y_time" => $y_text,
			"y_title" => $y_text,
			"y_location" => $y_loc,
		];
		$y += $lineHeight;
		$n++;
	}

	// Banner pinned to canvas bottom
	$rows[] = [
		"type" => "banner",
		"y_start" => $canvas_h - $bannerHeight,
		"y_end" => $canvas_h - 1,
		"banner_h" => $bannerHeight,
	];

	return $rows;
}

// ── Board renderer ────────────────────────────────────────────────────────────
//
// Takes the row plan from plan_board_rows() and draws everything onto $img.
// No layout logic here — only Imagick drawing calls.
// Fonts are resolved by name via fontconfig; no filesystem paths needed.

function render_board_image(
	array $rows,
	Imagick $img,
	int $w,
	int $h,
	string $theme,
	?string $mainFont,
	int $mainFontSize,
	?string $hourFontName,
	int $hourFontSize,
	array $color_overrides = [],
): void {
	// ── Colour palette ───────────────────────────────────────────────────────
	//
	// Theme sets defaults; $color_overrides (keyed by LSL param name, RRGGBB
	// hex without '#') override individual entries.

	$dark = $theme === "dark";

	$defaults = $dark
		? [
			"backgroundColor" => "121212", // true black (OLED)
			"backgroundColorStarted" => "163E26", // dark green tint
			"backgroundColorSoon" => "16263E", // dark blue tint
			"colorStartedAccent" => "57BB76", // green — left accent bar
			"colorText" => "E8EAED",
			"colorTime" => "9AA0A6", // medium grey
			"colorLocation" => "666D73",
			"colorSection" => "C06090", // brand colour, lightened for dark bg
			"colorSeparator" => "303030",
			"backgroundColorBanner" => "000000",
		]
		: [
			"backgroundColor" => "FFFFFF",
			"backgroundColorStarted" => "DCF5DC", // light green tint
			"backgroundColorSoon" => "E8F0FE", // light blue tint
			"colorStartedAccent" => "34A853", // green — left accent bar
			"colorText" => "202124", // near-black
			"colorTime" => "5F6468", // medium grey
			"colorLocation" => "80868B",
			"colorSection" => "804060", // brand/logo colour
			"colorSeparator" => "E8EAED",
			"backgroundColorBanner" => "F8F9FA",
		];

	// Merge overrides (strip leading '#' if present), build ImagickPixel map
	$resolved = array_map(
		fn($v) => ltrim($v, "#"),
		array_merge($defaults, array_filter($color_overrides)),
	);
	$c = array_map(fn($hex) => new ImagickPixel("#" . $hex), $resolved);

	// Background
	fill_rect($img, 0, 0, $w - 1, $h - 1, $c["backgroundColor"]);

	// ── Draw rows ────────────────────────────────────────────────────────────

	$time_col_w = 60; // width of time column (canvas px)

	foreach ($rows as $row) {
		if ($row["type"] === "section_header") {
			$day_fsz = max(6, (int) round($hourFontSize * 0.9));
			draw_text(
				$img,
				$hourFontName,
				$day_fsz,
				$c["colorSection"],
				8,
				$row["y_end"] - 2,
				$row["label"],
				$w,
			);
		} elseif ($row["type"] === "event") {
			$y0 = $row["y_start"];
			$y1 = $row["y_end"];
			$is_started = $row["section"] === "started";

			// Card background — green for started, blue for soon, default otherwise
			$bg = $is_started
				? $c["backgroundColorStarted"]
				: ($row["is_soon"]
					? $c["backgroundColorSoon"]
					: $c["backgroundColor"]);
			fill_rect($img, 0, $y0, $w - 1, $y1 - 1, $bg);

			// Left accent bar for started events
			if ($is_started) {
				fill_rect($img, 0, $y0, 3, $y1 - 1, $c["colorStartedAccent"]);
			}

			// Time
			draw_text(
				$img,
				$hourFontName,
				$hourFontSize,
				$c["colorTime"],
				7,
				$row["y_time"],
				$row["time_str"],
				$w,
			);

			// Title
			$title_x = $time_col_w;
			$title_w = $w - $title_x - 6;
			$title = fit_text(
				$row["title"],
				$mainFontSize,
				$mainFont,
				$title_w,
				$img,
			);
			draw_text(
				$img,
				$mainFont,
				$mainFontSize,
				$c["colorText"],
				$title_x,
				$row["y_title"],
				$title,
				$w,
			);

			// Location
			$loc_fsz = max(6, (int) round($hourFontSize * 0.85));
			$loc = fit_text(
				$row["hgurl"],
				$loc_fsz,
				$hourFontName,
				$title_w,
				$img,
			);
			draw_text(
				$img,
				$hourFontName,
				$loc_fsz,
				$c["colorLocation"],
				$title_x,
				$row["y_location"],
				$loc,
				$w,
			);

			// Row separator
			draw_line(
				$img,
				$title_x,
				$y1 - 1,
				$w - 1,
				$y1 - 1,
				$c["colorSeparator"],
			);
		} elseif ($row["type"] === "banner") {
			$y0 = $row["y_start"];
			fill_rect(
				$img,
				0,
				$y0,
				$w - 1,
				$h - 1,
				$c["backgroundColorBanner"],
			);
			draw_line($img, 0, $y0, $w - 1, $y0, $c["colorSeparator"]);
			draw_logo($img, $y0, $w, $y0 + $row["banner_h"]);
		}
	}
}

// ── Helpers ───────────────────────────────────────────────────────────────────

/**
 * Convert a RRGGBB hex string (with or without leading '#') to [r, g, b].
 *
 * @return array{int,int,int}
 */
function hex_to_rgb(string $hex): array
{
	$hex = ltrim($hex, "#");
	return [
		hexdec(substr($hex, 0, 2)),
		hexdec(substr($hex, 2, 2)),
		hexdec(substr($hex, 4, 2)),
	];
}

/**
 * Compute the internal canvas size for a given output texture and board ratio.
 *
 * The canvas is composed at the natural aspect ratio of the board
 * (at the given ratio), then resampled to texW × texH for output.
 * The longer canvas dimension always equals the corresponding texture dimension
 * so neither axis loses resolution in the resample step.
 *
 * @return array{int,int}  [canvas_w, canvas_h]
 */
function natural_canvas(int $texW, int $texH, float $ratio): array
{
	if ($ratio <= 0) {
		return [$texW, $texH];
	}
	// Portrait or square (ratio ≤ texW/texH): fix canvas width = texW, scale height up
	$ch = (int) round($texW / $ratio);
	if ($ch >= $texH) {
		return [$texW, $ch];
	}
	// Landscape (ratio > texW/texH): fix canvas height = texH, scale width up
	$cw = (int) round($texH * $ratio);
	return [$cw, $texH];
}

/**
 * Fill a rectangle on an Imagick canvas.
 */
function fill_rect(
	Imagick $img,
	int $x1,
	int $y1,
	int $x2,
	int $y2,
	ImagickPixel $color,
): void {
	$draw = new ImagickDraw();
	$draw->setFillColor($color);
	$draw->setStrokeOpacity(0);
	$draw->rectangle($x1, $y1, $x2, $y2);
	$img->drawImage($draw);
}

/**
 * Draw a line on an Imagick canvas.
 */
function draw_line(
	Imagick $img,
	int $x1,
	int $y1,
	int $x2,
	int $y2,
	ImagickPixel $color,
): void {
	$draw = new ImagickDraw();
	$draw->setStrokeColor($color);
	$draw->setFillOpacity(0);
	$draw->setStrokeWidth(1);
	$draw->line($x1, $y1, $x2, $y2);
	$img->drawImage($draw);
}

/**
 * Draw text on an Imagick canvas. Pass $x = null to centre horizontally.
 * Fonts are resolved by name via fontconfig — no filesystem paths needed.
 */
function draw_text(
	Imagick $img,
	?string $font,
	float $size,
	ImagickPixel $color,
	?int $x,
	int $y,
	string $text,
	int $canvas_w,
): void {
	if ($text === "") {
		return;
	}
	$draw = new ImagickDraw();
	if ($font) {
		$draw->setFont($font);
	}
	$draw->setFontSize($size);
	$draw->setFillColor($color);
	$draw->setTextAntialias(true);
	if ($x === null) {
		$metrics = $img->queryFontMetrics($draw, $text);
		$x = (int) (($canvas_w - $metrics["textWidth"]) / 2);
	}
	$img->annotateImage($draw, $x, $y, 0, $text);
}

/**
 * Truncate $text so it fits within $max_px canvas pixels wide.
 * Appends '…' when truncation occurs. Uses Imagick font metrics.
 */
function fit_text(
	string $text,
	float $size,
	?string $font,
	int $max_px,
	Imagick $img,
): string {
	$ellipsis = "…";
	$draw = new ImagickDraw();
	if ($font) {
		$draw->setFont($font);
	}
	$draw->setFontSize($size);
	$metrics = $img->queryFontMetrics($draw, $text);
	if ($metrics["textWidth"] <= $max_px) {
		return $text;
	}
	while (mb_strlen($text) > 1) {
		$text = mb_substr($text, 0, -1);
		$metrics = $img->queryFontMetrics($draw, $text . $ellipsis);
		if ($metrics["textWidth"] <= $max_px) {
			return $text . $ellipsis;
		}
	}
	return $ellipsis;
}

/**
 * Load the board logo and centre it in the footer strip.
 * Falls back silently if the file is missing or Imagick fails.
 */
function draw_logo(
	Imagick $img,
	int $banner_y,
	int $canvas_w,
	int $banner_y_end,
): void {
	$logo_file = __DIR__ . "/2do-logo-trim.png";
	if (!file_exists($logo_file)) {
		return;
	}
	try {
		$logo = new Imagick($logo_file);
		$lw = $logo->getImageWidth();
		$lh = $logo->getImageHeight();
		$banner_h = $banner_y_end - $banner_y;
		$scale = min(($banner_h - 6) / $lh, ($canvas_w * 0.6) / $lw);
		$dw = (int) round($lw * $scale);
		$dh = (int) round($lh * $scale);
		$logo->resizeImage($dw, $dh, Imagick::FILTER_LANCZOS, 1);
		$dx = (int) (($canvas_w - $dw) / 2);
		$dy = $banner_y + (int) (($banner_h - $dh) / 2);
		$img->compositeImage($logo, Imagick::COMPOSITE_OVER, $dx, $dy);
		$logo->destroy();
	} catch (ImagickException $e) {
		// Logo not critical — continue silently
	}
}

/** Strip emoji and force ASCII, matching the aggregator's own title sanitisation. */
function sanitise_title(string $title): string
{
	$title = preg_replace("/[\x{1F000}-\x{1FFFF}]/u", "", $title);
	$title = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $title);
	return trim($title);
}
