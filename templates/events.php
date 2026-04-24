<?php
/**
 * 2do Events – dynamic event generator
 *
 * Reads events.json and outputs filtered events in one of
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
define("BOARD_VER", "1.6.0");
define("EVENTS_JSON", __DIR__ . "/events.json");
define("SLT_TIMEZONE", "America/Los_Angeles");

class Event
{
	private static string $format = "lsl2";
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
		require_once __DIR__ . "/includes/bootstrap.php";

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
		switch (self::$config["format"] ?? "lsl2") {
			case "png":
				// debug_log("format=png");
				Event::outputBoardImage();
				break;
			case "clickmap":
				// debug_log("format=clickmap");
				Event::output_click_map();
				break;
			default:
				// debug_log("format=lsl2");
				Event::output_lsl2();
				break;
		}
	}

	/**
	 * Output events in LSL2 format.
	 */
	function output_lsl2(): void
	{
		header("Content-Type: text/plain; charset=utf-8");
		$tz = new DateTimeZone(SLT_TIMEZONE);
		echo BOARD_VER . "\n";
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
	 * Lines 2+:
	 * 	hgurl~y_start~y_end  — one per visible event, in display order.
	 * 	y_start / y_end are pixel coordinates in textureWidth × textureHeight space.
	 */
	function output_click_map(): void
	{
		header("Content-Type: text/plain; charset=utf-8");

		Event::setCanvas();
		Event::planBoardRows();
		$rows = self::$canvas->rows();

		$params = array_merge($_GET, ["format" => "png"]);
		$host = $_SERVER["HTTP_HOST"] ?? "localhost";
		$uri = strtok($_SERVER["REQUEST_URI"] ?? "/events/events.php", "?");
		echo "https://" . $host . $uri . "?" . http_build_query($params) . "\n";

		$canvasHeight = self::$canvas->height();
		$textureHeight = self::$config["height"];

		foreach ($rows as $row) {
			if ($row["type"] === "event") {
				$y0 = (int) round(
					($row["y_start"] * $textureHeight) / $canvasHeight,
				);
				$y1 = (int) round(
					($row["y_end"] * $textureHeight) / $canvasHeight,
				);
				echo $row["hgurl"] . "~" . $y0 . "~" . $y1 . "\n";
			}
		}
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

		$dayHeightCalc = (int) round(self::$styles["row"]["height"] * 0.55); // section header height
		$dayGap = (int) round(self::$styles["row"]["padding"] ?? 0); // gap between sections
		$canvasHeight = self::$canvas->height();
		$contentHeight = $canvasHeight - self::$styles["banner"]["height"];
		// debug_log("Available content height: $contentHeight");
		$rowHeight = self::$styles["row"]["height"];
		$bannerHeight = self::$styles["banner"]["height"] ?? 0;

		$rows = [];
		$y = self::$styles["main"]["padding"] ?? 0;
		$prev_section = null;
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

			// Section / day header
			if ($section === "started" && $prev_section !== "started") {
				// "CURRENTLY" header before the first started event
				if ($y > 6) {
					$y += $dayGap;
				}
				$heightNeeded = $y + $dayHeightCalc + $rowHeight;
				if ($heightNeeded > $contentHeight) {
					// debug_log(
					// 	"Section $section requested height = $y + $dayHeightCalc + $rowHeight = $heightNeeded > $contentHeight), stopping",
					// );
					break;
					// } else {
					// 	debug_log(
					// 		"Section $section requested height = $heightNeeded = $y + $dayHeightCalc + $rowHeight <= $contentHeight), proceeding",
					// 	);
				}
				self::$canvas->addRow([
					"type" => "section_header",
					"section" => "started",
					"label" => "CURRENTLY",
					"y_start" => $y,
					"y_end" => $y + $dayHeightCalc,
				]);
				$y += $dayHeightCalc; // TODO: implement top/bottom padding in section header style
			} elseif ($section === "upcoming" && $day !== $prev_day) {
				// Date header on day change (upcoming events only)
				if ($prev_section !== null) {
					$y += $dayGap;
				}
				$heightNeeded = $y + $dayHeightCalc + $rowHeight;
				if ($heightNeeded > $contentHeight) {
					// debug_log(
					// 	"Section $section requested height = $heightNeeded = $y + $dayHeightCalc + $rowHeight > $contentHeight), stopping",
					// );
					break;
					// } else {
					// 	debug_log(
					// 		"Section $section requested height = $heightNeeded = $y + $dayHeightCalc + $rowHeight <= $contentHeight), proceeding",
					// 	);
				}
				self::$canvas->addRow([
					"type" => "section_header",
					"section" => "day",
					"label" => strtoupper($startDateTime->format("D j M")),
					"is_today" => $day === $today,
					"y_start" => $y,
					"y_end" => $y + $dayHeightCalc,
				]);
				$y += $dayHeightCalc;
				$prev_day = $day;
			}

			$prev_section = $section;

			// Event row
			$title = sanitize_title($event["title"]);
			$heightNeeded = $y + $rowHeight;
			if ($heightNeeded > $contentHeight) {
				// debug_log(
				// 	"Row $i requested height = $heightNeeded = $y + $rowHeight > $contentHeight), stopping",
				// );
				break;
				// } else {
				// 	debug_log(
				// 		"Row $i requested height = $heightNeeded = $y + $rowHeight <= $contentHeight), adding $title",
				// 	);
			}

			$padding = self::$styles["row"]["padding"] ?? 0;
			$y_text =
				$y + $padding + (int) round(($rowHeight - $padding) * 0.6);
			$y_loc =
				$y + $padding + (int) round(($rowHeight - $padding) * 0.88);
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
				"y_end" => $y + $rowHeight,
				"y_time" => $y_text,
				"y_title" => $y_text,
				"y_location" => $y_loc,
			]);
			$y += $rowHeight;
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
				self::$styles["time"]["font-size"] * 5;

			foreach ($rows as $row) {
				if ($row["type"] === "section_header") {
					self::$canvas->drawText(
						"section",
						$row["label"],
						8,
						$row["y_end"] - 2,
					);
				} elseif ($row["type"] === "event") {
					$y0 = $row["y_start"];
					$y1 = $row["y_end"];
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

					// Left accent bar for started events
					if ($is_started) {
						self::$canvas->fillRectangle(
							0,
							$y0,
							3,
							$y1 - 1,
							self::$styles["ongoing"]["accent"],
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
						$row["y_title"],
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
						$row["y_location"],
					);

					// Row separator
					self::$canvas->drawLine(
						$title_x,
						$y1 - 1,
						$width - 1,
						$y1 - 1,
						self::$styles["separator"]["color"],
					);
				} elseif ($row["type"] === "banner") {
					$y0 = $row["y_start"];
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
					$imagePath = __DIR__ . "/2do-logo-trim.png";
					self::$canvas->addImageFromPath(
						$imagePath,
						$y0,
						$width,
						$y0 + $row["banner_h"],
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
		int $x1,
		int $y1,
		int $x2,
		int $y2,
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
		int $x1,
		int $y1,
		int $x2,
		int $y2,
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
		?int $posX,
		int $posY,
	): void {
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
		int $max_px,
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
	 * Load the board logo and centre it in the footer strip.
	 * Falls back silently if the file is missing or Imagick fails.
	 */
	function addImageFromPath(
		string $imagePath,
		int $banner_y,
		int $canvasWidth,
		int $banner_y_end,
	): void {
		if (!file_exists($imagePath)) {
			return;
		}
		try {
			$localImage = new Imagick($imagePath);
			$lw = $localImage->getImageWidth();
			$lh = $localImage->getImageHeight();
			$banner_h = $banner_y_end - $banner_y;
			$scale = min(($banner_h - 6) / $lh, ($canvasWidth * 0.6) / $lw);
			$dw = (int) round($lw * $scale);
			$dh = (int) round($lh * $scale);
			$localImage->resizeImage($dw, $dh, Imagick::FILTER_LANCZOS, 1);
			$dx = (int) (($canvasWidth - $dw) / 2);
			$dy = $banner_y + (int) (($banner_h - $dh) / 2);
			$this->compositeImage(
				$localImage,
				Imagick::COMPOSITE_OVER,
				$dx,
				$dy,
			);
			$localImage->destroy();
		} catch (ImagickException $e) {
			// Logo not critical — continue silently
		}
	}
}

$event = new Event();
$event->serve();
