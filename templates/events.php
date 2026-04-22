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
 *   notBefore    Seconds before now still included (default: 7200 = 2 h)
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
 *   rowHeight     Height of each event row         (default: 40)
 *   padding    Inner padding within event rows   (default: 0)
 *
 * PNG / clickmap — typography:
 *   font   Font family name searched in system paths, or absolute .ttf path
 *                  (default: first available among Roboto, SF, Arial, DejaVu…)
 *   fontSize   Title font size in points (default: 11)
 *   timeFont   Font for the time column (default: font)
 *   timeFontSize   Time font size in points  (default: 9)
 *
 * PNG / clickmap — colours (RRGGBB web hex, no '#' — same convention as LSL):
 *   Colors override the theme defaults. Theme still sets all unspecified values.
 *   ongoingBackground   Background for events currently in progress
 *   soonBackground      Background for events starting within ~1 h
 *   sectionColor             Section header text (default: brand/logo colour)
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

namespace ToDo\Event;

use DateTime, DateTimeZone;
use Imagick, ImagickPixel, ImagickDraw;

ini_set("display_errors", 1); # DEBUG
ini_set("display_startup_errors", 1); # DEBUG
error_reporting(E_ALL); # DEBUG
define("BOARD_VER", "1.6.0");
define("EVENTS_JSON", __DIR__ . "/events.json");
define("SLT_TIMEZONE", "America/Los_Angeles");

require_once __DIR__ . "/includes/config.php";

// Use parameters from $config and $styles
$format = $config["format"];
$notBefore = $config["not-before"];
$limit = $config["limit"];

// Output resolution
$textureWidth = $config["width"];
$textureHeight = $config["height"];

// Aspect ratio
$ratio = $config["ratio"];

// Layout
$bannerHeight = $styles["banner"]["height"];
$lineHeight = $styles["main"]["line-height"];
$rowHeight = $styles["main"]["row-height"];
$padding = $styles["main"]["padding"] ?? 0;

// Typography
$font = $styles["main"]["font"];
$fontSize = $styles["main"]["font-size"];

$timeFont = $styles["time"]["font"];
$timeFontSize = $styles["time"]["font-size"];
$timeColor = $styles["time"]["color"];

$locationFont = $styles["location"]["font"] ?? $font;
$locationFontSize = $styles["location"]["font-size"];
$locationColor = $styles["location"]["color"];

$ongoingBackground = $styles["ongoing"]["background"];
$soonBackground = $styles["soon"]["background"];
$sectionColor = $styles["section"]["color"];

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

$font = setFont($font ?? null) ?? "DejaVuSans";
error_log("font=$font");
$timeFont = $timeFont ?? $font;
$timeFont = setFont($timeFont) ?? $font;
error_log("font=$font");

// ── Load and filter events ────────────────────────────────────────────────────

$json = @file_get_contents(EVENTS_JSON);
$raw = $json ? (json_decode($json, true) ?: []) : [];
$notBeforeTimestamp = time() - $notBefore;
$events = array_values(
	array_filter($raw, fn($e) => strtotime($e["start"]) >= $notBeforeTimestamp),
);

header("Cache-Control: no-cache, must-revalidate");

if ($format === "png") {
	$color_overrides = array_filter([
		"ongoingBackground" => $ongoingBackground,
		"soonBackground" => $soonBackground,
		"sectionColor" => $sectionColor,
	]);
	output_board_image(
		$events,
		// $limit,
		// $textureWidth,
		// $textureHeight,
		// $ratio,
		// $config["theme"],
		// $font,
		// $fontSize,
		// $timeFont,
		// $timeFontSize,
		// $bannerHeight,
		// $rowHeight,
		// $padding,
		// $color_overrides,
	);
} elseif ($format === "clickmap") {
	output_click_map(
		$events,
		// $limit,
		// $textureWidth,
		// $textureHeight,
		// $ratio,
		// $get,
		// $bannerHeight,
		// $rowHeight,
		// $padding,
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
		$title = sanitize_title($ev["title"]);
		if (!$title) {
			continue;
		}
		$startTimestamp = strtotime($ev["start"]);
		$endTimestamp = strtotime($ev["end"]);
		$startDateTime = new DateTime($ev["start"], new DateTimeZone("UTC"));
		$startDateTime->setTimezone($tz);
		$endDateTime = new DateTime($ev["end"], new DateTimeZone("UTC"));
		$endDateTime->setTimezone($tz);
		echo $title .
			"\n" .
			implode("~", [
				$startDateTime->format("h:iA"),
				$startDateTime->format("Y-m-d"),
				$startTimestamp,
				$endDateTime->format("h:iA"),
				$endDateTime->format("Y-m-d"),
				$endTimestamp,
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

function output_click_map(array $events): // int $limit,
// int $width,
// int $height,
// float $ratio,
// array $get,
// int $bannerHeight,
// int $rowHeight,
// int $padding, void {
	header("Content-Type: text/plain; charset=utf-8");
	[$canvasWidth, $canvasHeigth] = natural_canvas($width, $height, $ratio);
	$rows = plan_board_rows(
		$events,
		// $limit,
		// $canvasWidth,
		// $canvasHeigth,
		// $bannerHeight,
		// $rowHeight,
		// $padding,
	);

	$params = array_merge($_GET, ["format" => "png"]);
	$host = $_SERVER["HTTP_HOST"] ?? "localhost";
	$uri = strtok($_SERVER["REQUEST_URI"] ?? "/events/events.php", "?");
	echo "https://" . $host . $uri . "?" . http_build_query($params) . "\n";

	// Scale Y from canvas space to texture output space
	foreach ($rows as $row) {
		if ($row["type"] === "event") {
			$y0 = (int) round(($row["y_start"] * $height) / $canvasHeigth);
			$y1 = (int) round(($row["y_end"] * $height) / $canvasHeigth);
			echo $row["hgurl"] . "~" . $y0 . "~" . $y1 . "\n";
		}
	}
}

// ── Format: png ──────────────────────────────────────────────────────────────

function output_board_image(array $events): // int $limit,
// int $width,
// int $height,
// float $ratio,
// string $themeName,
// ?string $font,
// int $fontSize,
// ?string $timeFont,
// int $timeFontSize,
// int $bannerHeight,
// int $rowHeight,
// int $padding,
// array $color_overrides = [], void {
	global $config, $styles;

	[$canvasWidth, $canvasHeigth] = natural_canvas($width, $height, $ratio);
	$rows = plan_board_rows(
		$events,
		// $limit,
		// $canvasWidth,
		// $canvasHeigth,
		// $bannerHeight,
		// $rowHeight,
		// $padding,
	);
	try {
		$canvas = new Imagick();
		$canvas->newImage(
			$canvasWidth,
			$canvasHeigth,
			new ImagickPixel("white"),
		);
		$canvas->setImageFormat("png");

		render_board_image(
			$rows,
			// $canvas,
			// $canvasWidth,
			// $canvasHeigth,
			// $themeName,
			// $font,
			// $fontSize,
			// $timeFont ?? $font,
			// $timeFontSize,
			// $color_overrides,
		);

		// Resample to requested texture resolution
		if ($canvasWidth !== $width || $canvasHeigth !== $height) {
			$canvas->resizeImage($width, $height, Imagick::FILTER_LANCZOS, 1);
		}

		header("Content-Type: image/png");
		header("Cache-Control: public, max-age=300");
		echo $canvas->getImageBlob();
		$canvas->destroy();
	} catch (ImagickException $e) {
		header("Content-Type: text/plain");
		http_response_code(500);
		echo "Error generating image: " . $e->getMessage() . "\n";
		echo "Stack trace: " . $e->getTraceAsString();
	}
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

function plan_board_rows(array $events): // int $limit,
// int $canvasWidth,
// int $canvasHeigth,
// int $bannerHeight,
// int $rowHeight,
// int $padding, array {
	$tz = new DateTimeZone(SLT_TIMEZONE);
	$now = time();
	$today = new DateTime("now", $tz)->format("Y-m-d");
	$soon_window = 3600; // flag upcoming events starting within 1 h as "soon"

	// Sort by start time — JSON source may be unordered or stale
	usort(
		$events,
		fn($a, $b) => strtotime($a["start"]) <=> strtotime($b["start"]),
	);

	$day_h = (int) round($rowHeight * 0.55); // section header height
	$day_gap = (int) round($rowHeight * 0.1); // gap between sections

	$max_y = $canvasHeigth - $bannerHeight; // bottom of usable content area

	$rows = [];
	$y = 6;
	$prev_section = null;
	$prev_day = null;
	$n = 0;

	foreach ($events as $ev) {
		if ($limit > 0 && $n >= $limit) {
			break;
		}

		$startTimestamp = strtotime($ev["start"]);
		$startDateTime = new DateTime($ev["start"], new DateTimeZone("UTC"));
		$startDateTime->setTimezone($tz);
		$day = $startDateTime->format("Y-m-d");

		// Section: events with start in the past are "started"; the top-level
		// notBefore filter already ensures they are within the display window.
		$section = $start <= $now ? "started" : "upcoming";
		$is_soon = $section === "upcoming" && $start - $now < $soon_window;

		// ── Section / day header ─────────────────────────────────────────────

		if ($section === "started" && $prev_section !== "started") {
			// "CURRENTLY" header before the first started event
			if ($y > 6) {
				$y += $day_gap;
			}
			if ($y + $day_h + $rowHeight > $max_y) {
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
			if ($y + $day_h + $rowHeight > $max_y) {
				break;
			}
			$rows[] = [
				"type" => "section_header",
				"section" => "day",
				"label" => strtoupper($startDateTime->format("D j M")),
				"is_today" => $day === $today,
				"y_start" => $y,
				"y_end" => $y + $day_h,
			];
			$y += $day_h;
			$prev_day = $day;
		}

		$prev_section = $section;

		// ── Event row ────────────────────────────────────────────────────────

		if ($y + $rowHeight > $max_y) {
			break;
		}
		$title = sanitize_title($ev["title"]);
		$pad = $padding;
		$y_text = $y + $pad + (int) round(($rowHeight - $pad) * 0.6);
		$y_loc = $y + $pad + (int) round(($rowHeight - $pad) * 0.88);
		$rows[] = [
			"type" => "event",
			"event" => $ev,
			"hgurl" => $ev["hgurl"],
			"section" => $section,
			"is_soon" => $is_soon,
			"is_today" => $day === $today,
			"time_str" => $startDateTime->format("g:ia"),
			"title" => $title,
			"y_start" => $y,
			"y_end" => $y + $rowHeight,
			"y_time" => $y_text,
			"y_title" => $y_text,
			"y_location" => $y_loc,
		];
		$y += $rowHeight;
		$n++;
	}

	// Banner pinned to canvas bottom
	$rows[] = [
		"type" => "banner",
		"y_start" => $canvasHeigth - $bannerHeight,
		"y_end" => $canvasHeigth - 1,
		"banner_h" => $bannerHeight,
	];

	return $rows;
}

// ── Board renderer ────────────────────────────────────────────────────────────
//
// Takes the row plan from plan_board_rows() and draws everything onto $img.
// No layout logic here — only Imagick drawing calls.
// Fonts are resolved by name via fontconfig; no filesystem paths needed.

function render_board_image(array $rows, Imagick $img): void
{
	// int $width,
	// int $height,
	// string $themeName,
	// ?string $font,
	// int $fontSize,
	// ?string $timeFont,
	// int $timeFontSize,
	// array $color_overrides = [],
	global $config, $styles;

	try {
		// ── Colour palette ───────────────────────────────────────────────────────
		//
		// Theme sets defaults; $color_overrides (keyed by LSL param name, RRGGBB
		// hex without '#') override individual entries.

		// $dark = $themeName === "dark";

		// $defaults = $dark
		// 	? [
		// 		"backgroundColor" => "121212", // true black (OLED)
		// 		"ongoingBackground" => "163E26", // dark green tint
		// 		"soonBackground" => "16263E", // dark blue tint
		// 		"colorOngoingAccent" => "57BB76", // green — left accent bar
		// 		"colorText" => "E8EAED",
		// 		"colorTime" => "9AA0A6", // medium grey
		// 		"colorLocation" => "666D73",
		// 		"sectionColor" => "C06090", // brand colour, lightened for dark bg
		// 		"colorSeparator" => "303030",
		// 		"backgroundColorBanner" => "000000",
		// 	]
		// 	: [
		// 		"backgroundColor" => "FFFFFF",
		// 		"ongoingBackground" => "DCF5DC", // light green tint
		// 		"soonBackground" => "E8F0FE", // light blue tint
		// 		"colorOngoingAccent" => "34A853", // green — left accent bar
		// 		"colorText" => "202124", // near-black
		// 		"colorTime" => "5F6468", // medium grey
		// 		"colorLocation" => "80868B",
		// 		"sectionColor" => "804060", // brand/logo colour
		// 		"colorSeparator" => "E8EAED",
		// 		"backgroundColorBanner" => "F8F9FA",
		// 	];

		// // Merge overrides (strip leading '#' if present), build ImagickPixel map
		// $resolved = array_map(
		// 	fn($v) => ltrim($v, "#"),
		// 	array_merge($defaults, array_filter($color_overrides)),
		// );
		// $c = array_map(fn($hex) => new ImagickPixel("#" . $hex), $resolved);

		// Background
		fill_rect($img, 0, 0, $width - 1, $height - 1, $c["backgroundColor"]);

		// ── Draw rows ────────────────────────────────────────────────────────────

		$time_col_w = 60; // width of time column (canvas px)

		foreach ($rows as $row) {
			if ($row["type"] === "section_header") {
				$day_fsz = max(6, (int) round($timeFontSize * 0.9));
				draw_text(
					$img,
					$timeFont,
					$day_fsz,
					$c["sectionColor"],
					8,
					$row["y_end"] - 2,
					$row["label"],
					$width,
				);
			} elseif ($row["type"] === "event") {
				$y0 = $row["y_start"];
				$y1 = $row["y_end"];
				$is_started = $row["section"] === "started";

				// Card background — green for started, blue for soon, default otherwise
				$bg = $is_started
					? $c["ongoingBackground"]
					: ($row["is_soon"]
						? $c["soonBackground"]
						: $c["backgroundColor"]);
				fill_rect($img, 0, $y0, $width - 1, $y1 - 1, $bg);

				// Left accent bar for started events
				if ($is_started) {
					fill_rect(
						$img,
						0,
						$y0,
						3,
						$y1 - 1,
						$c["colorOngoingAccent"],
					);
				}

				// Time
				draw_text(
					$img,
					$timeFont,
					$timeFontSize,
					$c["colorTime"],
					7,
					$row["y_time"],
					$row["time_str"],
					$width,
				);

				// Title
				$title_x = $time_col_w;
				$title_w = $width - $title_x - 6;
				$title = fit_text(
					$row["title"],
					$fontSize,
					$font,
					$title_w,
					$img,
				);
				draw_text(
					$img,
					$font,
					$fontSize,
					$c["colorText"],
					$title_x,
					$row["y_title"],
					$title,
					$width,
				);

				// Location
				$loc_fsz = max(6, (int) round($timeFontSize * 0.85));
				$loc = fit_text(
					$row["hgurl"],
					$loc_fsz,
					$timeFont,
					$title_w,
					$img,
				);
				draw_text(
					$img,
					$timeFont,
					$loc_fsz,
					$c["colorLocation"],
					$title_x,
					$row["y_location"],
					$loc,
					$width,
				);

				// Row separator
				draw_line(
					$img,
					$title_x,
					$y1 - 1,
					$width - 1,
					$y1 - 1,
					$c["colorSeparator"],
				);
			} elseif ($row["type"] === "banner") {
				$y0 = $row["y_start"];
				fill_rect(
					$img,
					0,
					$y0,
					$width - 1,
					$height - 1,
					$c["backgroundColorBanner"],
				);
				draw_line($img, 0, $y0, $width - 1, $y0, $c["colorSeparator"]);
				draw_image($img, $y0, $width, $y0 + $row["banner_h"]);
			}
		}
	} catch (ImagickException $e) {
		throw $e;
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
function natural_canvas(int $width, int $height, float $ratio): array
{
	if ($ratio <= 0) {
		return [$width, $height];
	}
	// Portrait or square (ratio ≤ texW/texH): fix canvas width = texW, scale height up
	$canvasHeigth = (int) round($width / $ratio);
	if ($canvasHeigth >= $height) {
		return [$width, $canvasHeigth];
	}
	// Landscape (ratio > texW/texH): fix canvas height = texH, scale width up
	$canvasWidth = (int) round($height * $ratio);
	return [$canvasWidth, $height];
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
	int $canvasWidth,
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
		$x = (int) (($canvasWidth - $metrics["textWidth"]) / 2);
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
function draw_image(
	Imagick $img,
	int $banner_y,
	int $canvasWidth,
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
		$scale = min(($banner_h - 6) / $lh, ($canvasWidth * 0.6) / $lw);
		$dw = (int) round($lw * $scale);
		$dh = (int) round($lh * $scale);
		$logo->resizeImage($dw, $dh, Imagick::FILTER_LANCZOS, 1);
		$dx = (int) (($canvasWidth - $dw) / 2);
		$dy = $banner_y + (int) (($banner_h - $dh) / 2);
		$img->compositeImage($logo, Imagick::COMPOSITE_OVER, $dx, $dy);
		$logo->destroy();
	} catch (ImagickException $e) {
		// Logo not critical — continue silently
	}
}

/** Strip emoji and force ASCII, matching the aggregator's own title sanitisation. */
function sanitize_title(string $title): string
{
	$title = preg_replace("/[\x{1F000}-\x{1FFFF}]/u", "", $title);
	$title = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $title);
	return trim($title);
}
