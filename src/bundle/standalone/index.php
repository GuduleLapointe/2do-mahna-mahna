<?php
/**
 * 2do front controller
 *
 * Routes clean API URLs to events.php with the appropriate parameters.
 *
 * GET /api/v3/events         → v3 CSV event list (canvas params forwarded)
 * GET /api/v3/events.png     → PNG board image
 * GET /api/v3/events.json    → full JSON event list
 * GET /api/v2/events         → legacy lsl2 plain-text format
 * GET /events.lsl2           → alias for /api/v2/events (backward compat)
 * GET /events.lsl3           → alias for /api/v2/events (backward compat)
 * GET /events.lsl            → 410 Gone (obsolete format)
 */

$scriptDir = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
if ($scriptDir && str_starts_with($requestPath, $scriptDir)) {
    $requestPath = substr($requestPath, strlen($scriptDir));
}
$path = '/' . trim($requestPath, '/');

switch ($path) {
    case '/':
        header('Location: index.html', true, 301);
        exit;

    case '/api/v3/events':
        unset($_GET['format']);
        $_GET['api'] = 'v3';
        require __DIR__ . '/events.php';
        break;

    case '/api/v3/events.png':
        unset($_GET['api']);
        $_GET['format'] = 'png';
        require __DIR__ . '/events.php';
        break;

    case '/api/v3/events.json':
        unset($_GET['api']);
        $_GET['format'] = 'json';
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
