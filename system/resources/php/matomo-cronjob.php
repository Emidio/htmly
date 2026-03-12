<?php

function config($key) {
    $base = strstr(__FILE__, 'system/resources/php/matomo-cronjob.php', true);
    $config_file = $base . 'config/config.ini';
    $config = parse_ini_file($config_file);
    if (isset($config[$key])) {
        return $config[$key];
    }
    return null;
}

$minutes = 5;
$base = strstr(__FILE__, 'system/resources/php/matomo-cronjob.php', true);
include_once($base . 'system/vendor/matomo/matomo-php-tracker/MatomoTracker.php');
include_once($base . 'system/includes/matomo-functions.php');

$threshold = time() - ($minutes * 60);
$stats_files = glob($base . 'cache/sessions/*.stats.json');

foreach ($stats_files as $stats_file) {
    // check last modified date
    if (filemtime($stats_file) >= $threshold) {
        continue; // skip processing, too early
    }

    // deleting residual files already sent by JS tracker (race situations patch)
    if (file_exists($stats_file . '.delete')) {
        unlink($stats_file);
        unlink($stats_file  . '.delete');
        continue;
    }

    $data_json = file_get_contents($stats_file);
    $data = json_decode($data_json, true);
    matomo_track($data, true);
    // finally remove the file
    unlink($stats_file);
}


// cleaning old residual delete files, just in case
$delete_files = glob($base . 'cache/sessions/*.stats.json.deleted');
foreach ($delete_files as $delete_file) {
    print_r($delete_file);
    // check last modified date
    if (filemtime($delete_file) >= $threshold) {
        continue; // skip processing, too early
    }

    if (!file_exists(preg_replace('/\.deleted$/', '', $delete_file))) {
        unlink($delete_file);
    }
}


?>