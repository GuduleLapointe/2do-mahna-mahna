<?php
/**
 * 2do Events – dynamic event generator
 *
 * Reads events.json and outputs filtered events. Output is selected via
 * ?api= (preferred) or legacy ?format=:
 *
 *   api=v3 (default)
 *     CSV, one line per row. Format:
 *       x0,y0,x1,y1,destination,start_time,start_stamp,end_time,end_stamp,title
 *     renderer=osdraw: positions are 0,0,0,0; up to ?limit events returned.
 *     renderer omitted: canvas layout used; positions are UV fractions from canvas.
 *   api=v2  or  format=lsl2
 *     Plain-text event list consumed by legacy LSL board scripts.
 *   format=png
 *     PNG board image for osSetDynamicTextureURL.
 *   format=json
 *     Full event list as JSON (for external consumers).
 *
 * Canvas parameters: width, height, ratio (same names as LSL Configuration notecard).
 * UV fractions: 0.0=top-left, 1.0=bottom-right.
 * destination is "host:port Region" for teleport or "href:https://…" for web links.
 *
 * @package 2do
 * @subpackage Event
 * @author Gudule Lapointe
 * @license AGPL-3.0-or-later
 *
 * Requires:
 * 	- PHP 8.2+
 * 	- Imagick
 */

namespace ToDo\Event;

use DateTime, DateTimeZone;
use Imagick, ImagickPixel, ImagickDraw;

// ini_set("display_errors", 1); # DEBUG
// ini_set("display_startup_errors", 1); # DEBUG
// error_reporting(E_ALL); # DEBUG

class Event
{
	private static string $format = "v3";
	private static array $config = [];
	private static array $styles = [];
	private static array $events = [];
	private static Canvas $canvas;

	public function __construct()
	{
		$this->init();
	}

	private function init(): void
	{
		if (self::$config) {
			return;
		}
		require_once __DIR__ . "/bootstrap.php";

		define("SLT_TIMEZONE", "America/Los_Angeles");

		self::$config = $config ?? [];
		if (empty(self::$config)) {
			todo_die("Config is empty", 500);
		}

		self::$styles = $styles ?? [];
		if (empty(self::$styles)) {
			todo_die("Styles are empty", 500);
		}
	}

	public function serve(): void
	{
		// new self()->init();
		$this->init();

		$this->loadEvents();
		$this->output();
	}

	private function loadEvents(): void
	{
		$json = @file_get_contents(EVENTS_JSON);
		$raw = $json ? (json_decode($json, true) ?: []) : [];
		$notBeforeTimestamp = time() - self::$config["not-before"];
		self::$events = array_values(
			array_filter(
				$raw,
				fn($e) => strtotime($e["start"]) >= $notBeforeTimestamp,
			),
		);
		// debug_log(count(self::$events) . " events");
	}

	private function output(): void
	{
		$format = self::$config["format"] ?? null;
		$output_api = self::$config["api"] ?? null;

		header("X-2do-api-version: " . API_VERSION);
		header("X-2do-events-version: " . EVENTS_VERSION);
		if (!empty($output_api)) {
			header("X-2do-output-api: " . $output_api);
		}

		if ($format === "png") {
			Event::outputBoardImage();
		} elseif ($format === "json") {
			Event::output_json();
		} elseif ($format === "lsl2" || $output_api === "v2") {
			Event::output_lsl2();
		} else {
			// Default: api=v3
			Event::output_v3();
		}
	}

	/**
	 * Output events in LSL2 format.
	 */
	function output_lsl2(): void
	{
		header("Content-Type: text/plain; charset=utf-8");
		$tz = new DateTimeZone(SLT_TIMEZONE);
		echo EVENTS_VERSION . "\n";
		$i = 0;
		$limit = self::$config["limit"];
		foreach (self::$events as $event) {
			if ($limit > 0 && $i >= $limit) {
				break;
			}
			$title = sanitize_title($event["title"]);
			if (!$title) {
				continue;
			}
			$eventStart = strtotime($event["start"]);
			$endTimestamp = strtotime($event["end"]);
			$startDateTime = new DateTime(
				$event["start"],
				new DateTimeZone("UTC"),
			);
			$startDateTime->setTimezone($tz);
			$endDateTime = new DateTime($event["end"], new DateTimeZone("UTC"));
			$endDateTime->setTimezone($tz);
			echo $title .
				"\n" .
				implode("~", [
					$startDateTime->format("h:iA"),
					$startDateTime->format("Y-m-d"),
					$eventStart,
					$endDateTime->format("h:iA"),
					$endDateTime->format("Y-m-d"),
					$endTimestamp,
				]) .
				"\n" .
				$event["hgurl"] .
				"\n";
			$i++;
		}
	}

	/**
	 * Output events in clickmap format.
	 *
	 * One "hgurl~y_start~y_end" line per visible event, in display order.
	 * y_start / y_end are UV fractions from top (0.0 = top, 1.0 = bottom).
	 * Banner row is emitted last with an empty hgurl as a sentinel.
	 */
	function output_click_map(): void
	{
		header("Content-Type: text/plain; charset=utf-8");

		Event::setCanvas();
		Event::planBoardRows();
		$rows = self::$canvas->rows();
		$canvasHeight = self::$canvas->height();

		foreach ($rows as $row) {
			if ($row["type"] === "event") {
				$y0 = $row["y_start"] / $canvasHeight;
				$y1 = $row["y_end"] / $canvasHeight;
				echo "0," . $y0 . ",1," . $y1 . "," . $row["hgurl"] . "\n";
			} elseif ($row["type"] === "banner") {
				$y0 = $row["y_start"] / $canvasHeight;
				$link =
					self::$styles["banner"]["link"] ??
					"https://2do.directory/events/";
				echo "0," . $y0 . ",1,1.0,href:" . $link . "\n";
			}
		}
	}

	/**
	 * Helper: encode one CSV row as a string (no trailing newline, RFC 4180).
	 */
	private static function csv_line(array $fields): string
	{
		$cols = [];
		foreach ($fields as $field) {
			$field = (string) $field;
			if (
				str_contains($field, ",") ||
				str_contains($field, '"') ||
				str_contains($field, "\n")
			) {
				$field = '"' . str_replace('"', '""', $field) . '"';
			}
			$cols[] = $field;
		}
		return implode(",", $cols);
	}

	/**
	 * API v3: unified event data and clickmap.
	 *
	 * Format: x0,y0,x1,y1,destination,start_time,start_stamp,end_time,end_stamp,title
	 *   - x0/y0/x1/y1 : UV fractions (0.0=top-left, 1.0=bottom-right),
	 *                    or 0,0,0,0 when renderer=osdraw
	 *   - destination  : "host:port Region" for teleport, "href:url" for web links
	 *   - start_time   : "h:iA" formatted (e.g. "10:30AM") for display
	 *   - start_stamp  : unix timestamp
	 *   - end_time     : "h:iA" formatted
	 *   - end_stamp    : unix timestamp
	 *   - title        : last — free-form text, safe to truncate on partial parse
	 * Canvas mode: positions are UV fractions; banner row appended at end.
	 * osDraw mode (?renderer=osdraw): positions are 0,0,0,0; no banner row; up to ?limit events.
	 */
	function output_v3(): void
	{
		header("Content-Type: text/plain; charset=utf-8");

		$tz = new DateTimeZone(SLT_TIMEZONE);

		if ((self::$config["renderer"] ?? null) === "osdraw") {
			// List mode: no canvas layout, up to $limit events with empty positions
			$limit = (int) self::$config["limit"];
			$i = 0;
			usort(
				self::$events,
				fn($a, $b) => strtotime($a["start"]) <=> strtotime($b["start"]),
			);
			foreach (self::$events as $event) {
				if ($limit > 0 && $i >= $limit) {
					break;
				}
				$title = sanitize_title($event["title"]);
				if (!$title) {
					continue;
				}
				$startDT = new DateTime(
					$event["start"],
					new DateTimeZone("UTC"),
				);
				$startDT->setTimezone($tz);
				$endDT = new DateTime($event["end"], new DateTimeZone("UTC"));
				$endDT->setTimezone($tz);
				echo self::csv_line([
					0,
					0,
					0,
					0,
					$event["hgurl"],
					$startDT->format("h:iA"),
					strtotime($event["start"]),
					$endDT->format("h:iA"),
					strtotime($event["end"]),
					$title,
				]) . "\n";
				$i++;
			}
			return;
		}

		// Canvas mode: compute layout, output positioned rows
		Event::setCanvas();
		Event::planBoardRows();
		$rows = self::$canvas->rows();
		$canvasHeight = self::$canvas->height();

		foreach ($rows as $row) {
			if ($row["type"] === "event") {
				$y0 = $row["y_start"] / $canvasHeight;
				$y1 = $row["y_end"] / $canvasHeight;
				$event = $row["event"];
				$startDT = new DateTime(
					$event["start"],
					new DateTimeZone("UTC"),
				);
				$startDT->setTimezone($tz);
				$endDT = new DateTime($event["end"], new DateTimeZone("UTC"));
				$endDT->setTimezone($tz);
				echo self::csv_line([
					0,
					$y0,
					1,
					$y1,
					$row["hgurl"],
					$startDT->format("h:iA"),
					strtotime($event["start"]),
					$endDT->format("h:iA"),
					strtotime($event["end"]),
					$row["title"] ?? "",
				]) . "\n";
			} elseif ($row["type"] === "banner") {
				$y0 = $row["y_start"] / $canvasHeight;
				$link =
					self::$styles["banner"]["link"] ??
					"https://2do.directory/events/";
				echo self::csv_line([
					0,
					$y0,
					1,
					1.0,
					"href:" . $link,
					"",
					"",
					"",
					"",
					"",
				]) . "\n";
			}
		}
	}

	/**
	 * JSON output — full event list for external consumers.
	 */
	function output_json(): void
	{
		header("Content-Type: application/json; charset=utf-8");
		echo json_encode(
			self::$events,
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
		);
	}

	/**
	 * Output events in PNG format.
	 */
	function outputBoardImage(): void
	{
		Event::setCanvas();
		Event::planBoardRows();
		$rows = self::$canvas->rows();

		try {
			// $canvas = new Imagick();
			$canvas = self::$canvas;
			$canvas->newImage(
				self::$canvas->width(),
				self::$canvas->height(),
				new ImagickPixel("white"),
			);
			$canvas->setImageFormat("png");

			self::render_board_image($rows);
			// Resample to requested texture resolution
			// Resample to requested texture resolution if needed
			if (
				self::$canvas->width() !== self::$config["width"] ||
				self::$canvas->height() !== self::$config["height"]
			) {
				// debug_log(
				// 	"Resizing canvas from " .
				// 		self::$canvas->width() .
				// 		"x" .
				// 		self::$canvas->height() .
				// 		" to " .
				// 		self::$config["width"] .
				// 		"x" .
				// 		self::$config["height"],
				// );
				$canvas->resizeImage(
					self::$config["width"],
					self::$config["height"],
					Imagick::FILTER_LANCZOS,
					1,
				);
				// } else {
				// 	debug_log("Canvas already at correct dimensions");
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

	/**
	 * Computes the canvas Y position of every visible row (section headers, event
	 * rows, bottom banner) without touching Imagick. Both outputBoardImage() and
	 * output_click_map() call this so their Y coordinates are always in sync.
	 *
	 * Events are first sorted by start time (the JSON source is not guaranteed to
	 * be ordered), then split into two sections:
	 *   - started  : start <= now  (top-level filter already enforces the time window)
	 *   - upcoming : start >  now  (grouped by day)
	 *
	 * Returns an array of row descriptors. Relevant keys per type:
	 *   type = 'section_header' : section ('started'|'day'), label,
	 *                             is_today (day only), y_start, y_end
	 *   type = 'event'          : event (raw array), hgurl, section, is_soon,
	 *                             is_today, time_str, title, y_start, y_end,
	 *                             y_time, y_title, y_location (text baselines, px)
	 *   type = 'banner'         : y_start, y_end
	 */
	static function planBoardRows(): void
	{
		$tz = new DateTimeZone(SLT_TIMEZONE);
		$now = time();
		$limit = self::$config["limit"];
		// Do not merge DateTime formatting with creation, the IDE may
		// reformat it improperly, breaking the script in some environments.
		// Keep them separate.
		$today = new DateTime("now", $tz);
		$today->format("Y-m-d");
		$soon_window = self::$config["soon"]["delay"] ?? 3600; // flag upcoming events starting within 1 h as "soon"

		// Sort by start time — JSON source may be unordered or stale
		usort(
			self::$events,
			fn($a, $b) => strtotime($a["start"]) <=> strtotime($b["start"]),
		);

		$fontSize = self::$styles["main"]["font-size"] ?? 16;
		$locationFontSize = self::$styles["location"]["font-size"] ?? $fontSize;
		$padding = self::$styles["main"]["padding"] ?? 0;
		$dayGap = self::$styles["main"]["gap"] ?? 0;
		$rowPadding = self::$styles["row"]["padding"] ?? 0;
		$eventHeight =
			self::$styles["row"]["height"] ??
			(int) round(
				$rowPadding +
					max($locationFontSize * 0.25, $rowPadding) +
					$fontSize +
					$locationFontSize,
			);
		$sectionFontSize = self::$styles["section"]["font-size"] ?? $fontSize;
		$sectionPadding = self::$styles["section"]["padding"] ?? 0;
		$sectionHeight = $sectionPadding * 2 + $sectionFontSize;
		$canvasHeight = self::$canvas->height();
		$contentHeight = $canvasHeight - self::$styles["banner"]["height"];
		// debug_log("Available content height: $contentHeight");
		$bannerHeight = self::$styles["banner"]["height"] ?? 0;

		$rows = [];
		$y = $padding;
		$prev_section = null;
		$first_in_section = true;
		$prev_day = null;

		$i = 0;
		foreach (self::$events as $event) {
			if ($limit > 0 && $i >= $limit) {
				break;
			}

			$startTimestamp = strtotime($event["start"]);
			$startDateTime = new DateTime(
				$event["start"],
				new DateTimeZone("UTC"),
			);
			$startDateTime->setTimezone($tz);
			$day = $startDateTime->format("Y-m-d");

			// Section: events with start in the past are "started"; the top-level
			// notBefore filter already ensures they are within the display window.
			$section = $startTimestamp <= $now ? "started" : "upcoming";
			$is_soon =
				$section === "upcoming" &&
				$startTimestamp - $now < $soon_window;

			// Section header — emitted on first started event or on each new day
			$sectionLabel = null;
			$sectionName = null;
			$sectionIsToday = false;
			if ($section === "started" && $prev_section !== "started") {
				$sectionName = "ongoing";
				$sectionLabel = "CURRENTLY";
			} elseif ($section === "upcoming" && $day !== $prev_day) {
				$sectionName = "day";
				$sectionLabel = strtoupper($startDateTime->format("D j M"));
				$sectionIsToday = $day === $today;
				$prev_day = $day;
			}
			if ($sectionLabel !== null) {
				if ($prev_section !== null) {
					$y += $dayGap;
				}
				$heightNeeded = $y + $sectionHeight + $eventHeight;
				if ($heightNeeded > $contentHeight) {
					break;
				}
				self::$canvas->addRow([
					"type" => "section_header",
					"section" => $sectionName,
					"label" => $sectionLabel,
					"is_today" => $sectionIsToday,
					"y_start" => $y,
					"y_end" => $y + $sectionHeight,
					"y_label" =>
						$y +
						$sectionPadding +
						(int) round($sectionFontSize * 0.85),
				]);
				$y += $sectionHeight;
				$first_in_section = true;
			}

			$prev_section = $section;

			// Event row
			$title = sanitize_title($event["title"]);
			$heightNeeded = $y + $eventHeight;
			if ($heightNeeded > $contentHeight) {
				break;
			}

			$y_title = $y + $rowPadding + $fontSize;
			$y_loc = $y_title + $locationFontSize;
			$y_time = $y_title;
			self::$canvas->addRow([
				"type" => "event",
				"event" => $event,
				"hgurl" => $event["hgurl"],
				"section" => $section,
				"is_soon" => $is_soon,
				"is_today" => $day === $today,
				"time_str" => $startDateTime->format("g:ia"),
				"title" => $title,
				"y_start" => $y,
				"y_end" => $y + $eventHeight,
				"y_time" => $y_time,
				"y_title" => $y_title,
				"y_location" => $y_loc,
				"first_in_section" => $first_in_section,
			]);
			$first_in_section = false;
			$y += $eventHeight;
			$i++;
		}

		if ($bannerHeight > 0) {
			// Banner pinned to canvas bottom
			self::$canvas->addRow([
				"type" => "banner",
				"y_start" => $canvasHeight - $bannerHeight,
				"y_end" => $canvasHeight - 1,
				"banner_h" => $bannerHeight,
			]);
		}
	}

	/**
	 * Takes the row plan from planBoardRows() and draws everything onto $img.
	 * No layout logic here — only Imagick drawing calls.
	 * Fonts are resolved by name via fontconfig; no filesystem paths needed.
	 */
	function render_board_image(): void
	{
		// Resolve colors from $styles
		function normalize_color($color)
		{
			if (empty($color)) {
				return "#FFFFFF";
			}
			if (strpos($color, "#") === false) {
				return "#" . $color;
			}
			return $color;
		}

		// Use canvas working dimensions (not output texture size — they differ when ratio != 1)
		$width = self::$canvas->width();
		$height = self::$canvas->height();
		$rows = self::$canvas->rows();

		try {
			// Background
			self::$canvas->fillRectangle(
				0,
				0,
				$width - 1,
				$height - 1,
				self::$styles["main"]["background"],
			);

			// Draw rows

			$time_col_w =
				self::$styles["time"]["width"] ??
				self::$styles["time"]["font-size"] * 5.5;

			foreach ($rows as $row) {
				if ($row["type"] === "section_header") {
					$sectionBg = self::$styles["section"]["background"] ?? null;
					if ($sectionBg) {
						self::$canvas->fillRectangle(
							0,
							$row["y_start"],
							$width - 1,
							$row["y_end"] - 1,
							$sectionBg,
						);
					}
					self::$canvas->drawText(
						"section",
						$row["label"],
						8,
						$row["y_label"],
					);
				} elseif ($row["type"] === "event") {
					$y0 = (int) $row["y_start"];
					$y1 = (int) $row["y_end"];
					$is_started = $row["section"] === "started";

					// Card background — green for started, blue for soon, default otherwise
					$bg = $is_started
						? self::$styles["ongoing"]["background"]
						: ($row["is_soon"]
							? self::$styles["soon"]["background"]
							: self::$styles["main"]["background"]);
					self::$canvas->fillRectangle(
						0,
						$y0,
						$width - 1,
						$y1 - 1,
						$bg,
					);

					// Left border color for started events
					if ($is_started) {
						self::$canvas->fillRectangle(
							0,
							$y0,
							3,
							$y1 - 1,
							self::$styles["ongoing"]["border-color"],
						);
					}

					// Time
					self::$canvas->drawText(
						"time",
						$row["time_str"],
						7,
						$row["y_time"],
					);

					// Title
					$title_x = $time_col_w;
					$title_w = $width - $title_x - 6;
					$title = self::$canvas->fitText(
						$row["title"],
						self::$styles["main"]["font-size"],
						self::$styles["main"]["font"],
						$title_w,
					);
					self::$canvas->drawText(
						"main",
						$title,
						$title_x,
						(int) $row["y_title"],
					);

					// Location
					$loc = self::$canvas->fitText(
						$row["hgurl"],
						self::$styles["location"]["font-size"],
						self::$styles["location"]["font"] ??
							self::$styles["main"]["font"],
						$title_w,
					);
					self::$canvas->drawText(
						"location",
						$loc,
						$title_x,
						(int) $row["y_location"],
					);

					// Row separator — top of row, skipped for the first event of each section
					if (!$row["first_in_section"]) {
						self::$canvas->drawLine(
							0,
							$y0,
							$width - 1,
							$y0,
							self::$styles["separator"]["color"],
						);
					}
				} elseif ($row["type"] === "banner") {
					$y0 = (int) $row["y_start"];
					self::$canvas->fillRectangle(
						0,
						$y0,
						$width - 1,
						$height - 1,
						self::$styles["banner"]["background"],
					);
					self::$canvas->drawLine(
						0,
						$y0,
						$width - 1,
						$y0,
						self::$styles["separator"]["color"],
					);
					$imagePath =
						(defined("BUNDLE_DIR") ? BUNDLE_DIR : __DIR__) .
						"/" .
						self::$config["logo"];
					self::$canvas->addImageFromPath(
						$imagePath,
						$y0,
						$width,
						$y0 + $row["banner_h"],
						self::$styles["banner"]["label"] ?? "",
					);
				}
			}
		} catch (ImagickException $e) {
			throw $e;
		}
	}

	/**
	 * Create the canvas for drawing events.
	 *
	 * @return array{int,int}  [canvas_w, canvas_h]
	 */
	static function setCanvas(): Canvas
	{
		self::$canvas = new Canvas(self::$config, self::$styles);
		return self::$canvas;
	}
}

/**
 * Canvas
 *
 * Represents the internal canvas used for drawing events.
 *
 * Compute the internal canvas size for a given output texture and board ratio.
 *
 * The canvas is composed at the natural aspect ratio of the board
 * (at the given ratio), then resampled to texW × texH for output.
 * The longer canvas dimension always equals the corresponding texture dimension
 * so neither axis loses resolution in the resample step.
 *
 * TODO: adjust calculation ratio according to target width/height
 * instead of assuming a square image target.
 *
 * @property float $ratio	Canvas aspect ratio
 * @property int $width		Calculated canvas width
 * @property int $height	Calculated canvas height
 * @property array $config	Configuration options
 * @property array $styles	Drawing styles
 * @property array $rows	Event rows
 */
class Canvas extends Imagick
{
	private float $ratio;
	private int $width;
	private int $height;
	private array $config;
	private array $styles;
	private array $rows = [];

	/**
	 * Construct a new canvas with the given configuration and drawing styles.
	 *
	 * @param array $config	Configuration options
	 * @param array $styles	Drawing styles
	 */
	public function __construct(array $config, array $styles)
	{
		parent::__construct();

		$this->config = $config;
		$this->styles = $styles;
		$ratio = $config["ratio"];
		$width = $config["width"];
		$height = $config["height"];

		// Calculate canvas dimensions based on ratio
		if ($ratio > 1) {
			// Landscape: fix canvas height = texture height, scale width up
			$width = (int) round($width * $ratio);
		} elseif ($ratio > 0) {
			// Portrait or square: fix canvas width = texture width, scale height up
			$height = (int) round($height / $ratio);
		}

		// debug_log("Canvas: width=$width height=$height ratio=$ratio");

		$this->width = $width;
		$this->height = $height;
		$this->ratio = $ratio;
	}

	/**
	 * Get the canvas width.
	 *
	 * @return int
	 */
	public function width(): int
	{
		return $this->width;
	}

	/**
	 * Get the canvas height.
	 *
	 * @return int
	 */
	public function height(): int
	{
		return $this->height;
	}

	/**
	 * Get the canvas aspect ratio.
	 *
	 * @return float
	 */
	public function ratio(): float
	{
		return $this->ratio;
	}

	/**
	 * Add a row of events to the canvas.
	 *
	 * @param array $row	Row of events
	 */
	public function addRow(array $row): void
	{
		$this->rows[] = $row;
	}

	/**
	 * Get the rows of events on the canvas.
	 *
	 * @return array
	 */
	public function rows(): array
	{
		return $this->rows;
	}

	/**
	 * Fill a rectangle on an Imagick canvas.
	 */
	public function fillRectangle(
		float $x1,
		float $y1,
		float $x2,
		float $y2,
		string $color,
	): void {
		$draw = new ImagickDraw();
		$draw->setFillColor($color);
		$draw->setStrokeOpacity(0);
		$draw->rectangle($x1, $y1, $x2, $y2);
		$this->drawImage($draw);
	}

	/**
	 * Draw a line on an Imagick canvas.
	 */
	public function drawLine(
		float $x1,
		float $y1,
		float $x2,
		float $y2,
		string $color,
	): void {
		$draw = new ImagickDraw();
		$draw->setStrokeColor($color);
		$draw->setFillOpacity(0);
		$draw->setStrokeWidth(1);
		$draw->line($x1, $y1, $x2, $y2);
		$this->drawImage($draw);
	}

	/**
	 * Draw text on an Imagick canvas. Pass $x = null to centre horizontally.
	 */
	function drawText(
		string $section,
		string $text,
		?float $posX,
		float $posY,
	): void {
		// $posX = (int) $posX;
		// $posY = (int) $posY;
		if ($text === "") {
			return;
		}
		$draw = new ImagickDraw();
		$font =
			$this->styles[$section]["font"] ?? $this->styles["main"]["font"];
		if ($font) {
			$draw->setFont($font);
		}
		$fontSize =
			$this->styles[$section]["font-size"] ??
			$this->styles["main"]["font-size"];
		$draw->setFontSize($fontSize);
		$color =
			$this->styles[$section]["color"] ?? $this->styles["main"]["color"];
		$draw->setFillColor($color);
		$draw->setTextAntialias(true);
		if ($posX === null) {
			$metrics = $this->queryFontMetrics($draw, $text);
			$posX = (int) (($canvasWidth - $metrics["textWidth"]) / 2);
		}
		$this->annotateImage($draw, $posX, $posY, 0, $text);
	}

	/**
	 * Truncate $text so it fits within $max_px canvas pixels wide.
	 * Appends '…' when truncation occurs. Uses Imagick font metrics.
	 */
	public function fitText(
		string $text,
		float $size,
		?string $font,
		float $max_px,
	): string {
		$ellipsis = "…";
		$draw = new ImagickDraw();
		if ($font) {
			$draw->setFont($font);
		}
		$draw->setFontSize($size);
		$metrics = $this->queryFontMetrics($draw, $text);
		if ($metrics["textWidth"] <= $max_px) {
			return $text;
		}
		while (mb_strlen($text) > 1) {
			$text = mb_substr($text, 0, -1);
			$metrics = $this->queryFontMetrics($draw, $text . $ellipsis);
			if ($metrics["textWidth"] <= $max_px) {
				return $text . $ellipsis;
			}
		}
		return $ellipsis;
	}

	/**
	 * Draw an optional text label followed by a logo, centred in the footer strip.
	 * Either part is omitted gracefully if missing or unavailable.
	 */
	function addImageFromPath(
		string $imagePath,
		float $banner_y,
		float $canvasWidth,
		float $banner_y_end,
		string $label = "",
	): void {
		$banner_h = $banner_y_end - $banner_y;

		// Load and scale logo
		$localImage = null;
		$dw = 0;
		$dh = 0;
		if (file_exists($imagePath)) {
			try {
				$localImage = new Imagick($imagePath);
				$lw = $localImage->getImageWidth();
				$lh = $localImage->getImageHeight();
				$scale = min(($banner_h - 6) / $lh, ($canvasWidth * 0.6) / $lw);
				$dw = (int) round($lw * $scale);
				$dh = (int) round($lh * $scale);
				$localImage->resizeImage($dw, $dh, Imagick::FILTER_LANCZOS, 1);
			} catch (ImagickException $e) {
				$localImage = null;
			}
		}

		// Measure label text
		$labelWidth = 0;
		$draw = null;
		$metrics = [];
		if ($label !== "") {
			$draw = new ImagickDraw();
			$font = $this->styles["main"]["font"] ?? null;
			if ($font) {
				$draw->setFont($font);
			}
			$draw->setFontSize($this->styles["main"]["font-size"]);
			$draw->setFillColor($this->styles["main"]["color"]);
			$draw->setTextAntialias(true);
			$metrics = $this->queryFontMetrics($draw, $label);
			$labelWidth = (int) ceil($metrics["textWidth"]);
		}

		// Centre text + gap + logo as a unit
		$gap =
			$localImage !== null && $labelWidth > 0
				? max(4, (int) ($this->styles["main"]["font-size"] * 0.4))
				: 0;
		$startX = (int) (($canvasWidth - $labelWidth - $gap - $dw) / 2);

		// Draw label
		if ($draw !== null && $labelWidth > 0) {
			$textY = $banner_y + (int) (($banner_h + $metrics["ascender"]) / 2);
			$this->annotateImage($draw, $startX, $textY, 0, $label);
		}

		// Draw logo
		if ($localImage !== null) {
			$dx = $startX + $labelWidth + $gap;
			$dy = $banner_y + (int) (($banner_h - $dh) / 2);
			$this->compositeImage(
				$localImage,
				Imagick::COMPOSITE_OVER,
				$dx,
				$dy,
			);
			$localImage->destroy();
		}
	}
}

$event = new Event();
$event->serve();
