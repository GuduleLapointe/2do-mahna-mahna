<?php
/**
 * 2do front controller
 *
 * Routes clean API URLs to events.php with the appropriate parameters.
 *
 * GET /api/v3/events              → 501 Not Implemented (reserved for REST)
 * GET /api/v3/events/lsl          → v3 CSV event list for LSL scripts
 * GET /api/v3/events/json         → full JSON event list
 * GET /api/v3/events/ics          → 501 Not Implemented (iCal, planned)
 * GET /api/v3/events/board.png    → PNG board image
 *
 * GET  /api/v3/scrup/get-version          → latest registered version for a script
 * POST /api/v3/scrup/register/server   → register a scrup server
 * POST /api/v3/scrup/register/script   → register a script version
 * POST /api/v3/scrup/register/client   → register a client for update delivery
 *
 * GET /api/v2/events              → legacy lsl2 plain-text format (frozen)
 * GET /events.lsl2                → alias for /api/v2/events (backward compat)
 * GET /events.lsl3                → alias for /api/v2/events (backward compat)
 * GET /events.lsl                 → 410 Gone (obsolete format)
 *
 * Fallback routes (no URL rewriting — direct script access):
 * GET /?api=v3                    → alias for /api/v3/events/lsl
 * GET /?api=v3&format=png         → alias for /api/v3/events/board.png
 * GET /events.php                 → alias for /api/v3/events/lsl
 * GET /events.php?format=png      → alias for /api/v3/events/board.png
 */

$scriptDir = rtrim(dirname($_SERVER["SCRIPT_NAME"] ?? "/"), "/");
$requestPath = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);
if ($scriptDir && str_starts_with($requestPath, $scriptDir)) {
	$strippedPath = substr($requestPath, strlen($scriptDir));
	// Set $requestPath to stripped only if stripped does not start with /api/
	if (!preg_match("~^/api/~", $strippedPath)) {
		$requestPath = $strippedPath;
	}
}
$path = "/" . trim($requestPath, "/");

switch ($path) {
	case "/":
	case "/index.php":
		if (($_GET["format"] ?? null) === "png") {
			unset($_GET["api"]);
			$_GET["format"] = "png";
			require __DIR__ . "/events.php";
		} elseif (($_GET["api"] ?? null) === "v3") {
			unset($_GET["format"]);
			$_GET["api"] = "v3";
			require __DIR__ . "/events.php";
		} else {
			readfile(dirname($_SERVER["SCRIPT_FILENAME"]) . "/static.html");
		}
		break;

	case "/api/v3/events":
		http_response_code(501);
		header("Content-Type: application/json");
		echo json_encode([
			"error" => "Not Implemented",
			"message" =>
				"Use /api/v3/events/lsl, /api/v3/events/json or /api/v3/events/board.png",
		]);
		break;

	case "/api/v3/events/lsl":
	case "/events.php":
		if (($_GET["format"] ?? null) === "png") {
			unset($_GET["api"]);
			$_GET["format"] = "png";
		} else {
			unset($_GET["format"]);
			$_GET["api"] = "v3";
		}
		require __DIR__ . "/events.php";
		break;

	case "/api/v3/events/json":
		unset($_GET["api"]);
		$_GET["format"] = "json";
		require __DIR__ . "/events.php";
		break;

	case "/api/v3/events/ics":
		http_response_code(501);
		header("Content-Type: application/json");
		echo json_encode([
			"error" => "Not Implemented",
			"message" => "iCal export via API is planned but not yet available",
		]);
		break;

	case "/api/v3/events/board.png":
		unset($_GET["api"]);
		$_GET["format"] = "png";
		require __DIR__ . "/events.php";
		break;

	case "/api/v3/scrup/get-version":
		$_REQUEST["action"] = "get-version";
		require __DIR__ . "/scrup/scrup.php";
		break;

	case "/api/v3/scrup/register/server":
		$_POST["action"] = $_REQUEST["action"] = "register";
		$_POST["type"] = $_REQUEST["type"] = "server";
		require __DIR__ . "/scrup/scrup.php";
		break;

	case "/api/v3/scrup/register/script":
		$_POST["action"] = $_REQUEST["action"] = "register";
		$_POST["type"] = $_REQUEST["type"] = "script";
		require __DIR__ . "/scrup/scrup.php";
		break;

	case "/api/v3/scrup/register/client":
		$_POST["action"] = $_REQUEST["action"] = "register";
		$_POST["type"] = $_REQUEST["type"] = "client";
		require __DIR__ . "/scrup/scrup.php";
		break;

	// Support for legacy v2 API endpoints
	case "/api/v2/events":
	case "/api/v2/events/lsl":
	case "/events.lsl2":
	case "/events.lsl3":
		unset($_GET["format"]);
		$_GET["api"] = "v2";
		require __DIR__ . "/events.php";
		break;

	// EOL v1 API endpoints
	// Output error message formatted as a v1 events list
	case "/events.lsl":
		http_response_code(410);
		header("Content-Type: text/plain; charset=utf-8");
		include __DIR__ . "/templates/events.lsl";
		break;

	default:
		http_response_code(404);
		include __DIR__ . "/templates/404.html";
		break;
}
