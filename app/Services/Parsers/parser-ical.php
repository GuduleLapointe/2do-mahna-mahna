<?php
// Independant process to fetch ical data from url given as argument and output it
// to stdout if a format that the parent script can use to fill an array of events

if( ! defined('APP_DIR') ) {
    define('APP_DIR', dirname(__DIR__));
}

require_once APP_DIR . '/vendor/autoload.php';
require_once APP_DIR . '/includes/functions.php';

use Kigkonsult\Icalcreator\Vcalendar;
use Kigkonsult\Icalcreator\Vevent;

$timeout = 5;
$process_from = '- 1 day';
$process_to = '+ 3 months';
$scriptName = $argv[0];

if( !isset($argv[1]) ) {
    error_log("Usage: $scriptName <ical_url>");
    die(1);
}

$url = $argv[1];

// error_reporting(0);
// ini_set('display_errors', '0');

try {
    $ics_data = file_get_contents($url, false, stream_context_create(array(
        'http' => array(
            'timeout' => 5,
        ),
    )));
} catch (Exception $e) {
    error_log( $e.get_message() );
    die(2);
}
if($ics_data === false) {
    error_log("$scriptName $url data fetch failed");
    die(3);
}

$ics_data = preg_replace('/:MAILTO:(?![^:]*@[^:]*\.[^:]*\b)([^:\n]*)(?=\n|$)/i', "$1", $ics_data);
$ics_data = preg_replace('/:$/m', '', $ics_data);

// Check if $ics_data is a valid ics formatted file or google calendar file
if (strpos($ics_data, 'BEGIN:VCALENDAR') === false && strpos($ics_data, 'BEGIN:VEVENT') === false) {
    error_log("$scripName $url ERROR, not a valid ics file");
    die(4);
}

// Use Kigkonsult\Icalcreator to parse $ics_data and create an array of events
$vcalendar = Vcalendar::factory();

try {
    $vcalendar->parse($ics_data);
} catch (Exception $e) {
    
    die(5);
}
$vcalendar->sort();

$startDate = new DateTime();
$startDate->modify($process_from);
$endDate = new DateTime();
$endDate->modify($process_to);

try {
    $vevents = $vcalendar->selectComponents(
        $startDate->format('Y'), $startDate->format('m'), $startDate->format('d'),
        $endDate->format('Y'), $endDate->format('m'), $endDate->format('d'),
        Vcalendar::VEVENT
    );
} catch (Exception $e) {
    // Log the error
    error_log("$scriptName $url error " . $e->get_code() . ': ' . $e->get_message());
    die(6);
}
if($vevents === false) {
    // error_log("$scriptName $url no events found");
    // Silently fail if no events are found
    die();
}

$events = array();
$events_count = 0;
foreach ($vevents as $yearlyEvents) {
    foreach ($yearlyEvents as $monthlyEvents) {
        foreach ($monthlyEvents as $dailyEvents) {
            foreach ($dailyEvents as $vevent) {
                $uid = $vevent->getUid();

                $dtstart = $vevent->getXprop('X-CURRENT-DTSTART');
                $dtend = $vevent->getXprop('X-CURRENT-DTEND');
                $recurrence = $vevent->getXprop('X-RECURRENCE');
                if( ! empty($recurrence) ) {
                    $recurrence = $recurrence[1];
                }
                
                if ($dtstart !== null) {
                    $dtstart = DateTime::createFromFormat('Y-m-d H:i:s e', $dtstart[1]);
                } else {
                    $dtstart = $vevent->getDtstart();
                }
                
                if ($dtend !== null) {
                    $dtend = DateTime::createFromFormat('Y-m-d H:i:s e', $dtend[1]);
                } else {
                    $dtend = $vevent->getDtend();
                }
                
                if(empty($dtstart) || empty($dtend)) {
                    $dtstart = $vevent->getDtstart();
                    $dtend = $vevent->getDtend();
                }

                $duration = $vevent->getDuration();

                if ($duration) {
                    $interval = new DateInterval($duration);
                    $durationInMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                } else {
                    // error_log("dtstart: " . print_r($dtstart, true) . " dtend: " . print_r($dtend, true) . "\n");
                    $interval = $dtend->diff($dtstart);
                    $durationInMinutes = $interval->days * 24 * 60 + $interval->h * 60 + $interval->i;
                }
                
                $dateUTC = $dtstart->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');

                $tags = $vevent->getCategories();

                $event = array(
                    'source_url' => $url,
                    'uid' => $vevent->getUid() . (empty($recurrence) ? '' : '-' . $recurrence),
                    // 'dtstart' => $vevent->getDtstart(),
                    // 'dtend' => $vevent->getDtend(),
                    'dateUTC' => $dateUTC,
                    'duration' => $durationInMinutes,
                    // 'owneruuid' => null, // Not implemented
                    // 'creatoruuid' => null, // Not implemented
                    'name' => $vevent->getSummary(),
                    'category' => $tags,
                    'tags' => $tags,
                    'description' => $vevent->getDescription(),
                    // 'covercharge' => 0, // Not implemented
                    // 'coveramount' => 0, // Not implemented
                    'simname' => $vevent->getLocation(),
                    // 'parcelUUID' => null, // Not implemented
                    // 'globalPos' => null, // Will be processed. by the main script
                    // 'eventflags' => 0, // Not implemented
                    // 'gatekeeperURL' => null, // Will be processed. by the main script
                    // 'hash' => null, // Will be processed. by the main script
                );
                $events_count++;
                $events[] = $event;
            }
        }
    }
}
// error_log("\nDEBUG: " . count($events) . "/$events_count events\n");

echo json_encode($events);
