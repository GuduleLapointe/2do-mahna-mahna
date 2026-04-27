<?php
// Independant process to fetch ical data from url given as argument and output it
// to stdout if a format that the parent script can use to fill an array of events

if( ! defined('APP_DIR') ) {
    define('APP_DIR', dirname(__DIR__));
}

require_once APP_DIR . '/vendor/autoload.php';
require_once APP_DIR . '/includes/functions.php';

use Symfony\Component\BrowserKit\HttpBrowser;
use Symfony\Component\DomCrawler\Crawler;

$source_url = "https://opensimworld.com/events/";
$source_tz = 'America/Los_Angeles';

$timeout = 5;
$process_from = '- 1 day'; // Not needed, source contain only ongoing and future events
$process_to = '+ 3 months'; // Not needed, source contain only a few days

$startDate = new DateTime();
$startDate->modify($process_from);
$endDate = new DateTime();
$endDate->modify($process_to);

$scriptName = $argv[0];

function fetchHGUrl($event_page_url) {
    static $url_cache;
    if(isset($url_cache[$event_page_url])) {
        // error_log("DEBUG: Cache hit for $event_page_url");
        return $url_cache[$event_page_url];
    }

    $browser = new HttpBrowser();
    $crawler = $browser->request('GET', $event_page_url);

    $hgurl_input = $crawler->filter('input#hgAddr')->attr('value');
    $hgurl = $hgurl_input ? $hgurl_input : "-";

    $url_cache[$event_page_url] = $hgurl;
    return $hgurl;
}

$browser = new HttpBrowser();
$crawler = $browser->request('GET', $source_url);

$i=0;
$events = $crawler->filter('div.container.wcont table.table.table-striped.table-bordered tr')->each(function (Crawler $node) {
    global $source_url;
    global $source_tz;

    $event = [];

    $title = $node->filter('h4 b a')->text();
    
    $parentHtml = $node->filter('td > b')->html();
    preg_match('/\b\d{4}-\d{2}-\d{2} \d{2}:\d{2}\b/', $parentHtml, $matches);
    $datetime_slt = $matches[0] ?? '';
    $date = DateTime::createFromFormat('Y-m-d H:i', $datetime_slt, new DateTimeZone($source_tz));
    $date->setTimezone(new DateTimeZone('UTC'));
    $datetime_utc = $date->format('Y-m-d H:i');

    $event_id = $node->filter('td b a[href^="/hop/"]')->attr('href');
    $event_page_url = "https://opensimworld.com" . $event_id;
    $tpurl = fetchHGUrl($event_page_url);
    $description = $node->filter('div small')->text();

    $event['source_url'] = $source_url;
    $event['uid'] = $event_page_url;; // a unique id and the domain name of the source
    $event['dateUTC'] = $datetime_utc; // event start date in UTC
    $event['duration'] = 120; // duration in minutes, not provided, 2 hours by default
    $event['name'] = $title; // title + " @HIE " + year of the event
    $event['category'] = 'Education';
    $event['tags'] = []; // an array of tags, including 'HIE ' . year
    $event['description'] = $description;
    $event['simname'] = $tpurl; // Destination HG teleport url

    return $event;
});

echo json_encode($events);
exit;
