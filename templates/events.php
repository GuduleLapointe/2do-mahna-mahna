<?php
/**
 * Dynamic LSL events generator
 *
 * Reads events.json (expected in the same directory as this script) and outputs
 * filtered events for use by the 2do Board LSL script or other consumers.
 *
 * This script is designed to be placed in the web-accessible output directory
 * (copied there by the aggregator). It serves as a real-time alternative to
 * the static events.lsl2 file: it filters out past events at request time,
 * so the output stays current even if the cron job hasn't run recently.
 *
 * The primary reason this exists is that the LSL board script has a hard
 * HTTP_BODY_MAXLENGTH of 4096 bytes. The static events.lsl2 grows without
 * bound (it contains all upcoming events), and once it exceeds ~4 KB, the
 * LSL script silently receives a truncated response containing only old events,
 * causing the board to appear empty. This script avoids that by filtering
 * server-side before sending.
 *
 * URL parameters (all optional):
 *   format      Output format. Currently only "lsl2" is supported (default: lsl2)
 *   limit       Maximum number of events to return (default: 20, 0 = unlimited)
 *   not_before  How far back in seconds to include already-started events
 *               (default: 7200 = 2 hours, matching the LSL board's notBefore logic)
 *
 * Apache configuration — add to the vhost serving your events directory:
 *
 *   # Serve events.php dynamically as events.lsl2 (and keep events.lsl2 as fallback)
 *   RewriteEngine On
 *   RewriteRule ^events/events\.lsl2$ events/events.php [L,QSA]
 *
 * Or, if events/ is its own DocumentRoot or Alias target:
 *
 *   RewriteEngine On
 *   RewriteRule ^events\.lsl2$ events.php [L,QSA]
 */

define('BOARD_VER', '1.5.5');
define('EVENTS_JSON', __DIR__ . '/events.json');
define('SLT_TIMEZONE', 'America/Los_Angeles');

$format     = isset($_GET['format'])     ? $_GET['format']     : 'lsl2';
$limit      = isset($_GET['limit'])      ? (int)$_GET['limit'] : 20;
$not_before = isset($_GET['not_before']) ? (int)$_GET['not_before'] : 2 * 3600;

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-cache, must-revalidate');

$json = @file_get_contents(EVENTS_JSON);
if ($json === false) {
    echo BOARD_VER . "\n";
    exit;
}

$events = json_decode($json, true);
if (!is_array($events)) {
    echo BOARD_VER . "\n";
    exit;
}

$notBefore = time() - $not_before;
$tz = new DateTimeZone(SLT_TIMEZONE);
$output = BOARD_VER . "\n";
$count = 0;

foreach ($events as $event) {
    if ($limit > 0 && $count >= $limit) {
        break;
    }

    $start_stamp = strtotime($event['start']);
    if ($start_stamp < $notBefore) {
        continue;
    }

    $title = $event['title'];
    $title = preg_replace('/[\x{1F000}-\x{1FFFF}]/u', '', $title);
    $title = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $title);
    $title = trim($title);
    if (empty($title)) {
        continue;
    }

    $end_stamp = strtotime($event['end']);

    $begin_dt = new DateTime($event['start'], new DateTimeZone('UTC'));
    $begin_dt->setTimezone($tz);
    $end_dt = new DateTime($event['end'], new DateTimeZone('UTC'));
    $end_dt->setTimezone($tz);

    $time_specifier = implode('~', [
        $begin_dt->format('h:iA'),
        $begin_dt->format('Y-m-d'),
        $start_stamp,
        $end_dt->format('h:iA'),
        $end_dt->format('Y-m-d'),
        $end_stamp,
    ]);

    $output .= "$title\n$time_specifier\n{$event['hgurl']}\n";
    $count++;
}

echo $output;
