<?php

global $defaults, $styles;

$defaults = [
	"format" => "lsl2",
	"width" => 512,
	"height" => 512,
	"not-before" => 7200,
	"limit" => 100,
	"ratio" => 1.0,
	"logo" => "2do-logo.png",
	"styles" => [
		"main" => [
			"font" => "Roboto",
			"font-size" => 20,
			"color" => "#202124",
			"background" => "white",
			"line-height" => 1.2,
			"padding" => 0,
			"gap" => 10,
		],
		"section" => [
			"color" => "white",
			"background" => "#dd2a84",
			"padding" => 10,
		],
		"row" => [
			// "height" => 40,
			"padding" => 10,
		],
		"time" => [
			"font" => "DejaVuSansMono",
			"font-size" => 12,
			"color" => "#5F6468",
		],
		"location" => [
			"font-size" => 12,
			"color" => "#80868B",
		],
		"separator" => [
			"color" => "#cccccc",
		],
		"banner" => [
			"height" => 40,
			"position" => "bottom",
			"background" => "F8F9FA",
			"label" => "More events:",
			"link" => "https://2do.directory/events/",
		],
		"ongoing" => [
			"background" => "#DCF5DC", // light green tint
			"border-color" => "#34A853", // green — left border color
		],
		"soon" => [
			"background" => "#E8F0FE",
		],
	],
];

$themes = [
	"dark" => [
		"main" => [
			"color" => "cccccc",
			"background" => "121212",
		],
	],
];

require_once __DIR__ . "/functions.php";
require_once __DIR__ . "/Config.php";

define("BASE_DIR", dirname(__DIR__, 2));

Config::load(
	defaults: ['data_dir' => __DIR__],
	jsonFile: BASE_DIR . "/config/config.json",
	envFiles: [BASE_DIR . "/.env"],
	withQueryParams: false,
);

$_ENV["BASE_URL"] =
	($_SERVER["HTTPS"] === "On" ? "https" : "http") .
	"://" .
	$_SERVER["HTTP_HOST"];

if (!defined("DATA_DIR")) {
	define("DATA_DIR", rtrim(Config::get('data_dir'), "/"));
}
if (!defined("EVENTS_JSON")) {
	define("EVENTS_JSON", DATA_DIR . "/events.json");
}

$config = [
	"theme" => $_GET["theme"] ?? "default",
	"api" => $_GET["api"] ?? null,
	"format" => $_GET["format"] ?? null,
	"renderer" => $_GET["renderer"] ?? null,
	"width" => $_GET["width"] ?? $defaults["width"],
	"height" => $_GET["height"] ?? $defaults["height"],
	"not-before" => $_GET["not-before"] ?? $defaults["not-before"],
	"limit" => $_GET["limit"] ?? $defaults["limit"],
	"logo" => $_GET["logo"] ?? $defaults["logo"],
	"ratio" => max(
		0.25,
		min(4.0, (float) ($_GET["ratio"] ?? $defaults["ratio"])),
	),
];

// Canvas width determines the scale factor for all pixel dimensions.
// Mirrors Canvas::__construct() logic — both will move to a shared helper later.

$styles = $themes[$config["theme"]] ?? $defaults["styles"];

// Add missing sections from defaults
$styles = array_merge($defaults["styles"], $styles);

function scaleToWidth($value, $config)
{
	if ($value == 0) {
		return $value;
	}

	$finalWidth = $config["width"] * $config["ratio"];

	if ($config["scale"] ?? false) {
		$scale = $config["scale"];
	} elseif ($finalWidth > 512) {
		// Style dimensions are defined at the 512px reference width: fontSize=16 means 16px on a 512px canvas.
		$scale = $finalWidth / 512.0;
	} else {
		$scale = 1;
	}
	if ($scale == 1) {
		return $value;
	}

	return $value * $scale;
}

// Merge individual keys with defaults
foreach ($styles as $section_key => &$section) {
	$section = array_merge($defaults["styles"][$section_key] ?? [], $section);
	foreach ($section as $key => $value) {
		$query_arg = ($section_key != "main" ? $section_key . "-" : "") . $key;
		$value =
			$_GET[$query_arg] ??
			($value ?? ($defaults["styles"][$section_key][$key] ?? null));

		// Sanitize special types
		switch ($key) {
			case "color":
			case "background":
			case "border-color":
				$value = color($value);
				break;

			case "font":
				$value = setFont($value);
				break;

			case "ratio":
				$value = floatval($value);
				break;

			case "font-size":
			case "padding":
			case "gap":
			case "height":
				$value = scaleToWidth($value, $config);
				break;

			case "width":
			case "not-before":
			case "limit":
				$value = intval($value);
				break;
		}
		$section[$key] = $value;
	}
}
unset($section);
