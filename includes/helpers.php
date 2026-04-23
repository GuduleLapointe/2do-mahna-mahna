<?php
/**
 * Global helpers
 */

/**
 * Set the font for Imagick, searching for a match in the available fonts.
 *
 * @param string|null $fontName The font name to search for.
 * @param array $fonts An array of font names to search through. Defaults to all available fonts.
 * @return string|null The font name that matches the search pattern, or null if no match is found.
 */
function setFont(?string $fontName, $fonts = [])
{
	if (empty($fonts)) {
		$fonts = \Imagick::queryFonts();
	}
	error_log("queryFonts(): found " . count($fonts) . " fonts");

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
	// return $fonts[0] ?? "DejaVuSans";
}

/**
 * Sanitize a title by removing emoji and forcing ASCII, matching the aggregator's own title sanitisation.
 *
 * @param string $title The title to sanitize.
 * @return string The sanitized title.
 */
function sanitize_title(string $title): string
{
	$title = preg_replace("/[\x{1F000}-\x{1FFFF}]/u", "", $title);
	$title = iconv("UTF-8", "ASCII//TRANSLIT//IGNORE", $title);
	return trim($title);
}

/**
 * Get a color in the specified format.
 *
 * @param string|null $color The color to get.
 * @param string $format The format to return the color in (hex or rgb).
 * @return string|null The color in the specified format, or null if the color is empty.
 */
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

function cssArray($css)
{
	preg_match_all(
		"/(?ims)([a-z0-9\s\,\.\:#_\-@]+)\{([^\}]*)\}/",
		$css,
		$css_ARR,
	);
	$styles = [];

	foreach ($css_ARR[0] as $i => $x) {
		$selector = trim($css_ARR[1][$i]);
		$rules = explode(";", trim($css_ARR[2][$i]));
		$styles[$selector] = [];

		foreach ($rules as $strRule) {
			if (!empty($strRule)) {
				$rule = explode(":", $strRule);
				$styles[$selector][][trim($rule[0])] = trim($rule[1]);
			}
		}
	}

	return $styles;
}

function admin_notice($message, $error_code = 0, $die = false)
{
	// get calling function and file
	$trace = debug_backtrace();

	$depth = 1;
	if (isset($trace[1])) {
		$caller = $trace[1];
		$function = $caller["function"] ?? "";
		while (in_array($function, ["debug_log", "require_once"])) {
			$caller = $trace[$depth++];
			$function = $caller["function"] ?? "";
		}
	} else {
		$caller = $trace[0];
	}
	$file = empty($caller["file"]) ? "" : $caller["file"];
	$line = $caller["line"] ?? 0;
	$function =
		($caller["function"] === "require_once"
			? basename($file)
			: $caller["function"] . "()") ?? "main";
	$class = $caller["class"] ?? "main";
	$type = $caller["type"] ?? "::";
	if ($class != "main") {
		$function = $class . $type . $function;
	}
	$file = $file . ":" . $line;
	$message = sprintf(
		"%s%s: %s in %s",
		$function,
		empty($error_code) ? "" : " Error $error_code",
		$message,
		$file,
	);
	error_log($message);
	if ($die == true) {
		die($error_code);
	}
	return $message;
}

function debug_log($message, $error_code = 0, $die = false)
{
	admin_notice($message, $error_code, $die);
}

/**
 * Die silently with HTTP error_code and log the error message
 */
function todo_die($response = null, $error_code = null)
{
	if (is_array($response)) {
		$success = $response["success"] ?? false;
		$error_code = $response["error_code"] ?? ($success ? 200 : 500);
		$error_message =
			$response["message"] ??
			($success ? "" : "Unexpected error occurred " . $error_code);
	} elseif (is_string($response)) {
		// success if no error_code or error code between 200 and 299
		$success =
			empty($error_code) || ($error_code >= 200 && $error_code < 300);
		$error_code = $error_code ?? ($success ? 200 : 500);
		$error_message = $response;
	} elseif (is_numeric($response)) {
		$success =
			empty($error_code) || ($error_code >= 200 && $error_code < 300);
		$error_code = $response ?? ($success ? 200 : 500);
		$error_message = $success
			? ""
			: "Unexpected error occurred " . $error_code;
	}

	if (php_sapi_name() !== "cli" && !headers_sent()) {
		// $locale = set_helpers_locale();
		header("HTTP/1.1 " . $error_code . " " . $error_message);
		header("Content-Type: text/plain; charset=utf-8");
		// header("Content-Language: " . $locale);
	}
	if (!empty($error_message)) {
		$message = $success
			? $error_message
			: "[ERROR] $error_code $error_message";
		error_log($message);
	}
	die();
}
