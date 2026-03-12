<?php

session_start();

header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');
header('Content-Type: application/json');

include_once('../../vendor/matomo/matomo-php-tracker/MatomoTracker.php');
include_once('../../includes/matomo-functions.php');


if (isset($_SESSION['matomo']['updater']) && $_SESSION['matomo']['updater'] != 'js') {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $screen_stats  = $data['screen']  ?? [];
    $browser_stats = $data['browser'] ?? [];

    $_SESSION['matomo']['screen_width'] = $screen_stats['width'] ?? null;
    $_SESSION['matomo']['screen_height'] = $screen_stats['height'] ?? null;
    $_SESSION['matomo']['screen_colordepth'] = $screen_stats['colorDepth'] ?? null;
    $_SESSION['matomo']['screen_pixelratio'] = $screen_stats['pixelRatio'] ?? null;

    // has js - if not set as bot by useragent or ASN/datacenter, we can set it as NON bot as it supports JS
    if ($_SESSION['matomo']['isbot'] == -1) {
        $_SESSION['matomo']['isbot'] = 0;
    }

    // setting JS support and updater script
    $_SESSION['matomo']['js'] = 1;
    $_SESSION['matomo']['updater'] = 'js';

    matomo_debug('JST001', 'Sending data.');
    matomo_track($_SESSION['matomo'], true);
    matomo_debug('JST002', $_SESSION);


    // sessions/e423efcaabbf0cf1b897d8e876754483.stats.json
    
    $root = dirname(__FILE__, 4); // risale di 4 livelli da questo file
    $matomo_json_file = $root . '/cache/sessions/' . session_id() . '.stats.json';
    
    // $matomo_json_file = '../../../cache/sessions/' . session_id() . '.stats.json';

    if (file_exists($matomo_json_file)) {
        unlink($matomo_json_file);
        matomo_debug('JST003', 'Session file deleted.');
    }
    else {
        // setting the delete file, so file is delete later
        file_put_contents($matomo_json_file . '.delete', '@', LOCK_EX);
        matomo_debug('JST004', 'Session file not present, putting delete placeholder.');
    }
}


session_write_close();

echo json_encode([
    'status'  => 'ok'
]);


?>