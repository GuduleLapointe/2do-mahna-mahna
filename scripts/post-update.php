<?php
// scripts/setup.php
// Centralized setup script for app-specific install/update tasks (portable)

$app_dir = realpath(dirname(__DIR__));

$src = "legacy/lib/opensim-helpers/includes/2do-polyfill.php";
$dest = "legacy/lib/opensim-helpers/includes/config.php";

if (!file_exists("$app_dir/$src")) {
    fwrite(STDERR, "[setup] Source polyfill not found: $src" . PHP_EOL);
    exit(1);
}

$dest_exists = file_exists("$app_dir/$dest");
if (!copy("$app_dir/$src", "$app_dir/$dest")) {
    fwrite(STDERR, "[setup] Failed to copy $src to $dest" . PHP_EOL);
    exit(1);
}

echo "[setup] $dest " . ($dest_exists ? "updated" : "created") . PHP_EOL;
