<?php
/**
 * 2do front controller
 *
 * Routes clean API URLs to events.php with the appropriate parameters.
 *
 * GET /api/v2/events              → legacy lsl2 plain-text format (frozen)
 * GET /events.lsl2                → alias for /api/v2/events (backward compat)
 * GET /events.lsl3                → alias for /api/v2/events (backward compat)
 * GET /events.lsl                 → 410 Gone (obsolete format)
 *
 * GET /api/v3/events              → 501 Not Implemented (reserved for REST)
 * GET /api/v3/events/lsl          → v3 CSV event list for LSL scripts
 * GET /api/v3/events/json         → full JSON event list
 * GET /api/v3/events/ics          → 501 Not Implemented (iCal, planned)
 * GET /api/v3/events/board.png    → PNG board image
 */

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($scriptDir && str_starts_with($requestPath, $scriptDir)) {
    $requestPath = substr($requestPath, strlen($scriptDir));
}
$path = '/' . trim($requestPath, '/');

switch ($path) {
    case '/':
        include __DIR__ . '/templates/calendar.html';
        break;

    case '/api/v3/events':
        http_response_code(501);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Implemented', 'message' => 'Use /api/v3/events/lsl, /api/v3/events/json or /api/v3/events/board.png']);
        break;

    case '/api/v3/events/lsl':
        unset($_GET['format']);
        $_GET['api'] = 'v3';
        require __DIR__ . '/events.php';
        break;

    case '/api/v3/events/json':
        unset($_GET['api']);
        $_GET['format'] = 'json';
        require __DIR__ . '/events.php';
        break;

    case '/api/v3/events/ics':
        http_response_code(501);
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Not Implemented', 'message' => 'iCal export via API is planned but not yet available']);
        break;

    case '/api/v3/events/board.png':
        unset($_GET['api']);
        $_GET['format'] = 'png';
        require __DIR__ . '/events.php';
        break;

    case '/api/v2/events':
    case '/events.lsl2':
    case '/events.lsl3':
        unset($_GET['format']);
        $_GET['api'] = 'v2';
        require __DIR__ . '/events.php';
        break;

    case '/events.lsl':
        http_response_code(410);
        header('Content-Type: text/plain; charset=utf-8');
        echo "This endpoint is no longer supported. Please update your board script.\n";
        break;

    default:
        http_response_code(404);
        break;
}
