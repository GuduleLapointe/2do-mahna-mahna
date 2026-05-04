#!/usr/bin/env php
<?php
/**
 * dump-events-for-diff.php
 *
 * Dumps the events table to stdout as JSON for cross-server comparison.
 * Run on each server, redirect output to a file, then diff locally.
 *
 * Usage:
 *   php dev/dump-events-for-diff.php > /tmp/events-new.json
 *   # on live server:
 *   php dev/dump-events-for-diff.php > /tmp/events-old.json
 *   # compare:
 *   diff <(jq -S . /tmp/events-old.json) <(jq -S . /tmp/events-new.json)
 *
 * Fields included: name, dateUTC, duration, simname, category, globalPos,
 * gatekeeperURL, description_len (length only — avoids encoding noise).
 * Sorted by dateUTC + name for deterministic diff output.
 */

$env = parse_ini_file(dirname(__DIR__) . '/.env') ?: [];
$host = $env['SEARCH_DB_HOST'] ?? getenv('SEARCH_DB_HOST') ?: 'localhost';
$name = $env['SEARCH_DB_NAME'] ?? getenv('SEARCH_DB_NAME') ?: '';
$user = $env['SEARCH_DB_USER'] ?? getenv('SEARCH_DB_USER') ?: '';
$pass = $env['SEARCH_DB_PASS'] ?? getenv('SEARCH_DB_PASS') ?: '';
$table = $env['SEARCH_TABLE_EVENTS'] ?? getenv('SEARCH_TABLE_EVENTS') ?: 'events';

if (empty($host) || empty($name)) {
    fwrite(STDERR, "SEARCH_DB_HOST / SEARCH_DB_NAME not set in .env\n");
    exit(1);
}

try {
    $pdo = new PDO("mysql:host=$host;dbname=$name;charset=utf8", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    fwrite(STDERR, "Connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

$stmt = $pdo->query(
    "SELECT
        name,
        dateUTC,
        duration,
        simname,
        category,
        globalPos,
        gatekeeperURL,
        LENGTH(description)  AS description_len,
        MD5(CONCAT(dateUTC, simname)) AS hash
     FROM `$table`
     ORDER BY dateUTC ASC, name ASC"
);

$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cast numeric fields for clean JSON output
foreach ($events as &$e) {
    $e['dateUTC']         = (int) $e['dateUTC'];
    $e['duration']        = (int) $e['duration'];
    $e['category']        = (int) $e['category'];
    $e['description_len'] = (int) $e['description_len'];
}

echo json_encode([
    'server'  => $host,
    'db'      => $name,
    'table'   => $table,
    'count'   => count($events),
    'dumped'  => date('c'),
    'events'  => $events,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
