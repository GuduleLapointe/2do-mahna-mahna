<?php

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
			"background" => "#cccccc",
			"line-height" => 1.2,
			"row-height" => 40,
			"padding" => 0,
		],
		"time" => [
			"font" => "DejaVuSansMono",
			"font-size" => 12,
			"color" => "pink", // "#5F6468",
		],
		"location" => [
			"font-size" => 9,
			"color" => "#80868B",
		],
		"section" => [
			"color" => "#999999",
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

function color($color, $format = "hex")
{
	if (empty($color)) {
		return null;
	}
	$color = preg_replace("/^([0-9a-fA-F]+)$/", "#$1", $color);
	try {
		$pixel = new ImagickPixel($color);
		$rgb = $pixel->getColor();
		if ($format == "hex") {
			$hex = strtolower(
				sprintf("#%02X%02X%02X", $rgb["r"], $rgb["g"], $rgb["b"]),
			);
			return $hex;
		}
		return $rgb;
	} catch (ImagickException $e) {
		error_log("Invalid color: " . $color);
		return null;
	}
}

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
