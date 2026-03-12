<?php

if (!function_exists('save_json_pretty')) {
    function save_json_pretty($filename, $arr)
    {
        if (defined("JSON_PRETTY_PRINT")) {    
            file_put_contents($filename, json_encode($arr, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT), LOCK_EX);
        } else {
            file_put_contents($filename, json_encode($arr, JSON_UNESCAPED_UNICODE), LOCK_EX);
        }
    }
}


function get_htmly_root() {
    $url_path = trim(parse_url(config('site.url'), PHP_URL_PATH), '/');
    $doc_root = realpath($_SERVER['DOCUMENT_ROOT']);
    if ($url_path === '' || $url_path === null) {
        return $doc_root;
    }
    return $doc_root . '/' . $url_path;
}



function matomo_set_session($data) {
    foreach ($data as $key => $value) {
        $_SESSION['matomo'][$key] = $value;
    }    
}



function matomo_pagetitle($locals) {
    $metadata = generate_meta_info($locals);
    
    $pagePrefix = $metadata['prefix'] ?? '';
    $pageTitle = trim($pagePrefix . $metadata['title']);

    if (substr($pageTitle, -strlen(' - ' . blog_title())) === ' - ' . blog_title()) {
        $pageTitle = substr($pageTitle, 0, -strlen(' - ' . blog_title()));
    }
    return $pageTitle;
}



function matomo_track($matomo, $async = false) {
    // need to determine if it is bot or not before initializing the tracker
    if ($matomo['isbot'] == 1 || ($async && $matomo['isbot'] == -1)) {
        $matomo['isbot'] = 1;
        $matomo['botname'] = $matomo['browser'] . '  ' . $matomo['asn'];
    }

    // Initialize tracker
    if (config('matomo.bots.siteid') != '' && $matomo['isbot'] == 1) {
        $t = new MatomoTracker((int)config('matomo.bots.siteid'), config('matomo.url'));  
    }
    else {
        $t = new MatomoTracker((int)config('matomo.site.id'), config('matomo.url'));    
    }

    // Set auth token (required for some features like custom IP or user ID)
    $t -> setTokenAuth(config('matomo.authtoken'));

    if (config('matomo.cookies') == 'false') {
        $t -> disableCookieSupport();
    }

    if ($async) {
        $localDate = new DateTime($matomo['datetime'], new DateTimeZone('Europe/Rome'));
        $localDate -> setTimezone(new DateTimeZone('UTC'));
        $utcDate = $localDate->format('Y-m-d H:i:s');

        $t -> setUrl($matomo['url']);
        $t -> setUrlReferrer($matomo['referer']);
        $t -> setIp($matomo['ip']);
        $t -> setUserAgent($matomo['useragent']);
        $t -> setForceVisitDateTime($utcDate);
    }

    if ($matomo['screen_width'] != '' && $matomo['screen_height'] != '') {
        $t -> setResolution($matomo['screen_width'], $matomo['screen_height']);
    }

    if ($matomo['screen_colordepth'] != '') {
        $t -> setCustomTrackingParameter('color', $matomo['screen_colordepth']);
    }

    if ($matomo['screen_pixelratio'] != '') {
        $t -> setCustomTrackingParameter('pixel_ratio', $matomo['screen_pixelratio']);
    }

    if (config('matomo.provider.dimension') != '') {
        $t -> setCustomDimension(config('matomo.provider.dimension'), $matomo['asn']);
    }

    if (config('matomo.visitortype.dimension') != '') {
        if ($matomo['isbot'] == 1) {
            $visitor_type = 'crawler/bot';
        }
        else {
            $visitor_type = 'real';
        }
        $t -> setCustomDimension(config('matomo.visitortype.dimension'), $visitor_type);
    }

    if ($matomo['pagetype'] == 'search' && isset($matomo['search']) && isset($matomo['searchresults'])) {
        // search specific tracking with search terms and number of results
        $t -> doTrackSiteSearch($matomo['search'], '', $matomo['searchresults']);
        matomo_debug('TRKSRC', $t);
    }
    else {
        // Track page view
        matomo_add_view($matomo);
        $t -> doTrackPageView($matomo['pagetitle']);
        matomo_debug('TRKMTM', $t);
    }
}


function matomo_add_view($matomo) {
    // not incrementing counters for logged in users
    if (isset($_SESSION[config('site-url')]['user']) && $_SESSION[config('site-url')]['user'] != '') {
        return;
    }

    if (($matomo['pagetype'] == 'post' || $matomo['pagetype'] == 'page') && $matomo['isbot'] == 0) {
        $url = parse_url($matomo['url']);
        $paths = explode('/', trim($url['path'], '/'));

        if ($matomo['pagetype'] == 'post') {
            $post = end($paths);
            $page = 'post_' . $post;
        }
        else {
            if (count($paths) > 1) {
                $post = 'subpage_' . $paths[0] . '.' . $paths[1];
            }
            else {
                $post = 'page_' . $paths[0];
            }
        }

        $dir = get_htmly_root() . '/content/data/' . session_id() . '.stats.json';

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $filename = $dir . "/views.json";
        $views = array();
        if (file_exists($filename)) {
            $views = json_decode(file_get_data($filename), true);
        }

        if (isset($views['flock_fail'])) {
            return;
        } else {
            if (isset($views[$page])) {
                $views[$page]++;
                save_json_pretty($filename, $views);
            } else {
                $views[$page] = 1;
                save_json_pretty($filename, $views);
            }
        }
    }
}



function matomo_set_values($data = array()) {
    // set to null all empty strings/values and false value
    foreach ($data as $key => $value) {
        if (trim($value) == '') {
            $data[$key] = null;
        }
    }

    // list of _SESSION['matomo'] variables - first initialization
    $items['url'] = $data['url'] ?? (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
    $items['referer'] = $data['referer'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $items['pagetitle'] = $data['pagetitle'] ?? '';
    $items['pagetype'] = $data['pagetype'] ?? '';
    $items['search'] = $data['search'] ?? '';
    $items['searchresults'] = $data['searchresults'] ?? 0;
    $items['screen_width'] = $data['screen_width'] ?? '';;  // set by js in js script
    $items['screen_height'] = $data['screen_height'] ?? '';; // set by js in js script
    $items['screen_colordepth'] = $data['screen_colordepth'] ?? '';;    // set by js in js script
    $items['screen_pixelratio'] = $data['screen_pixelratio'] ?? '';;    // set by js in js script
    $items['useragent'] = $data['useragent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? '';
    $items['asn'] = $data['asn'] ?? '';
    $items['isbot'] = $data['isbot'] ?? -1;
    $items['browser'] = $data['browser'] ?? '';
    $items['botname'] = $data['botname'] ?? '';
    $items['ip'] = $data['ip'] ?? client_ip();
    $items['js'] = $data['js'] ?? 0;                                  // initial value, always take $data one if available
    $items['datetime'] = $data['datetime'] ?? date("Y-m-d H:i:s");    // initial value, always take $data one if available
    $items['updater'] = $data['updater'] ?? 'php';                    // initial value, always take $data one if available

    // set bot from browscap
    $br = getBrowser($items['useragent']);
    
    $items['browser'] = $br['browser'];
    if ($br['isbot']) {
        $items['isbot'] = 1;
        $items['botname'] = $br['browser'];
    }

    // set ASN and bot from ASN
    $asn = getASN($items['ip']);

    if ($asn['asn'] != '') {
        $items['asn'] = $asn['asn'];
        if ($items['isbot'] < 1 && ($asn['bot'] || $asn['bad'])) {
            $items['isbot'] = 1;
            $items['botname'] = $items['browser'] . ' - ' . $asn['asn'];
        }
    }

    return $items;
}




function matomo_set_sessionfile($sessionid = null, $arr = array()) {
    if ($sessionid === null) $sessionid = session_id();
    
    $matomo_json_folder = matomo_get_sessionsfolder();
    $matomo_json_file = $matomo_json_folder . '/' . session_id() . '.stats.json';

    if (file_exists($matomo_json_file)) {
        // get missing data from existing file
        $data = matomo_get_sessionfile($sessionid, $arr);
    }
    else {
        // get missing default data
        $data = matomo_set_values($arr);
    }

    $data_json = json_encode($data);

    file_put_contents($matomo_json_file, $data_json, LOCK_EX);
    clearstatcache(true, $matomo_json_file);
    
    return $data;
}

function matomo_get_sessionfile($sessionid = null, $arr = array()) {
    if ($sessionid === null) $sessionid = session_id();
    
    $matomo_json_folder = matomo_get_sessionsfolder();
    $matomo_json_file = $matomo_json_folder . '/' . session_id() . '.stats.json';

    if (!file_exists($matomo_json_file)) {
        return false;
    }
    
    $data_json = file_get_contents($matomo_json_file);
    $data = json_decode($data_json, true);
    
    // setting new values from $arr
    foreach ($data as $key => $value) {
        $data[$key] = $arr[$key] ?? $value;    
    }
    return $data;
}


function matomo_get_sessionsfolder() {
    $matomo_json_folder = $_SERVER['DOCUMENT_ROOT'] . '/cache/sessions';
    if (!is_dir($matomo_json_folder)) {
        mkdir($matomo_json_folder);
    }
    return $matomo_json_folder;
}


function matomo_isdatetime(string $value): bool {
    $d = DateTime::createFromFormat('Y-m-d H:i:s', $value);
    return $d && $d->format('Y-m-d H:i:s') === $value;
}





function matomo_sendsessionfiles($minutes) {
    $threshold = time() - ($minutes * 60); // to get all files older than $minutes
    $stats_files = glob(matomo_get_sessionsfolder() . '/*.stats.json');

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
        
        matomo_debug('SND001', $stats_file);

        $data = matomo_get_sessionfile(preg_replace('/\.stats\.json$/', '', $stats_file));

        matomo_track($data, true); // TODO: Emidio
        

        // finally remove the file
        unlink($stats_file);
    }
    
    
    // cleaning old residual delete files, just in case
    $delete_files = glob(matomo_get_sessionsfolder() . '/*.stats.json.deleted');
    foreach ($delete_files as $delete_file) {
        // check last modified date
        if (filemtime($delete_file) >= $threshold) {
            continue; // skip processing, too early
        }
        
        if (!file_exists(preg_replace('/\.deleted$/', '', $delete_file))) {
            unlink($delete_file);
        }
    }
}





function getBrowser($useragent = null) {
    $browser_stats['browser'] = '';
    $browser_stats['isbot'] = false;
    if (function_exists('get_browser')) {
        $browser = get_browser($useragent, true);

        $browser_stats['browser'] = $browser['browser'];
        if ($browser['crawler'] == 1 || $browser['browser_type'] == 'Bot' || $browser['isfake'] == 1) {
            $browser_stats['isbot'] = true;
        }
        return $browser_stats;
    }
    return $browser_stats;
}



function isLocalIp($ip) {
    if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return false;
    }

    $long = ip2long($ip);

    // pre-calcultaed hex values (best performances)
    return ($long & 0xFF000000) === 0x0A000000        // 10.0.0.0/8
        || ($long & 0xFFF00000) === 0xAC100000        // 172.16.0.0/12
        || ($long & 0xFFFF0000) === 0xC0A80000        // 192.168.0.0/16
        || ($long & 0xFF000000) === 0x7F000000        // 127.0.0.0/8
        || ($long & 0xFFFF0000) === 0xA9FE0000;       // 169.254.0.0/16
}



function getASN($ip) {
    if (isLocalIp($ip)) {
        $arr['asn'] = 'Local IP address range';
        $arr['bad'] = false;
        $arr['bot'] = false;
        return $arr;
    }

    $bot_asn = array_map('trim', explode(',', config('matomo.asnbot')));
    $bad_asn = array_map('trim', explode(',', config('matomo.asnblock')));

    $arr['asn'] = '';
    $arr['bad'] = false;
    $arr['bot'] = false;

    // check if it's a malicious/bot ASN in malicious ASN definition- check only if GeoIP is configured in Apache
    if (isset($_SERVER['MM_ASN'])) {
        $arr['asn'] = trim($_SERVER['MM_ASORG'] . ' (' . $_SERVER['MM_ASN'] . ')');
        if (in_array($_SERVER['MM_ASN'], $bot_asn)) {
            $arr['bot'] = true;
        }
        if (in_array($_SERVER['MM_ASN'], $bad_asn)) {
            $arr['bad'] = true;
        }
    }

    // if ASN still undetected, third try using the datacenter.csv file in config/data folder
    if ($arr['asn'] == '') {
        $asn_csv = asnLookup($ip);
        if ($asn_csv) {
            $arr['asn'] = $asn_csv[3] . ' (datacenter)';
            $arr['bot'] = true;
        }
    }

    return $arr;
}

function asnLookup($ip) {
    static $fh       = null;
    static $fileSize = null;

    // datacenters.csv comes from https://github.com/growlfm/ipcat
    $csvFile = 'config/data/datacenters.csv';

    if (file_exists($csvFile)) {
        if ($fh === null) {
            $fh       = fopen($csvFile, 'rb');
            $fileSize = filesize($csvFile);
            if (!$fh) return false;
        }

        $target = ip2long($ip);
        if ($target === false) return false;

        $lo   = 0;
        $hi   = $fileSize - 1;
        $best = null;

        while ($lo <= $hi) {
            $mid = (int)(($lo + $hi) / 2);

            fseek($fh, $mid);
            if ($mid > 0) fgets($fh); // allinea alla riga successiva

            $pos = ftell($fh);
            if ($pos >= $fileSize) { $hi = $mid - 1; continue; }

            $line = fgets($fh);
            if ($line === false || trim($line) === '') { $hi = $mid - 1; continue; }

            $row      = str_getcsv(trim($line));
            $rowStart = ip2long($row[0] ?? '');
            if ($rowStart === false) { $hi = $mid - 1; continue; }

            if ($rowStart <= $target) {
                $best = $row;
                $lo   = $pos + strlen($line);
            } else {
                $hi = $mid - 1;
            }
        }

        if ($best === null) return false;
        if (ip2long($best[1]) < $target) return false;

        return $best; // [ip_start, ip_end, name, url]
    }
    return false;
}



function matomo_debug($code, $var) {
    if (PHP_SAPI != 'cli') {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/cache/matomo.log';
        
        $datetime = date('Y-m-d H:i:s');
        
        $ip = $_SERVER['REMOTE_ADDR'];
        
        if (is_array($var) || is_object($var)) {
            $var_output = print_r($var, true);
            // Indenta ogni riga di 4 spazi
            $var_output = implode("\n", array_map(function($line) { return '    ' . $line; }, explode("\n", $var_output)));
            $line = "[{$datetime}] {$code} | {$ip} | {$_SERVER['REQUEST_URI']}\n{$var_output}\n";
        } else {
            $line = "[{$datetime}] {$code} | {$ip} | {$_SERVER['REQUEST_URI']} | {$var}\n";
        }
        
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
    }
}





function display($var) {
    print "<pre>";
    print_r($var);
    print "</pre>";
}



?>