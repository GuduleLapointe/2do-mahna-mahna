<?php

global $defaults, $styles;

$defaults = [
	"format" => "lsl2",
	"width" => 512,
	"height" => 512,
	"not-before" => 7200,
	"limit" => 100,
	"ratio" => 1.0,
	"styles" => [
		"main" => [
			"font" => "Roboto",
			"font-size" => 16,
			"color" => "#202124",
			"background" => "white",
			"line-height" => 1.2,
			"padding" => 0,
		],
		"row" => [
			"height" => 40,
			"padding" => 0,
		],
		"time" => [
			"font" => "DejaVuSansMono",
			"font-size" => 12,
			"color" => "#5F6468",
		],
		"location" => [
			"font-size" => 9,
			"color" => "#80868B",
		],
		"section" => [
			"color" => "#999999",
			"background" => "magenta",
		],
		"separator" => [
			"color" => "#cccccc",
		],
		"banner" => [
			"filename" => "2do-logo-trim.png",
			"height" => 32,
			"position" => "bottom",
			"background" => "F8F9FA",
		],
		"ongoing" => [
			"background" => "#DCF5DC", // light green tint
			"accent" => "#34A853", // green — left accent bar
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

require_once __DIR__ . "/helpers.php";

define("BASE_DIR", dirname(__DIR__, 2));
debug_log("BASE_DIR: " . BASE_DIR . PHP_EOL);

// Load environment variables
if (file_exists(BASE_DIR . "/.env")) {
	debug_log("Loading .env file " . BASE_DIR . "/.env");
	try {
		$env = parse_ini_file(BASE_DIR . "/.env");
		$_ENV = array_merge_recursive($_ENV, $env);
	} catch (Exception $e) {
		error_log("Failed to load .env file: " . $e->getMessage());
	}
}

$_ENV["BASE_URL"] =
	($_SERVER["HTTPS"] === "On" ? "https" : "http") .
	"://" .
	$_SERVER["HTTP_HOST"];
debug_log("BASE_URL: " . $_ENV["BASE_URL"] . PHP_EOL);

$config = [
	"theme" => $_GET["theme"] ?? "default",
	"format" => $_GET["format"] ?? $defaults["format"],
	"width" => $_GET["width"] ?? $defaults["width"],
	"height" => $_GET["height"] ?? $defaults["height"],
	"not-before" => $_GET["not-before"] ?? $defaults["not-before"],
	"limit" => $_GET["limit"] ?? $defaults["limit"],
	"ratio" => $_GET["ratio"] ?? $defaults["ratio"],
];

$styles = $themes[$config["theme"]] ?? $defaults["styles"];

// Add missing sections from defaults
$styles = array_merge($defaults["styles"], $styles);

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
			case "accent":
				$value = color($value);
				break;

			case "ratio":
				$value = floatval($value);
				break;
			case "width":
			case "height":
			case "not-before":
			case "limit":
				$value = intval($value);
				break;
		}
		$section[$key] = $value;
	}
}
