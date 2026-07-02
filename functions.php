<?php
// * Copyright 2021-2026 SnehTV, Inc.
// * Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// * Created By : TechieSneh

// Load configuration
$config = @parse_ini_file(__DIR__ . '/../config.ini', true);
$PROXY = $config['settings']['proxy'] ?? null;

// --- ABSOLUTE DATA PATHS (FIXED FOR LOCAL/PROD) ---
define('DATA_FOLDER', __DIR__ . '/assets/data');
define('TOKEN_EXPIRY_TIME', 7000);
define('COOKIE_EXPIRY_TIME', 40000);

// Determine protocol and host
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https://' : 'http://';
$host_jio = $_SERVER['HTTP_HOST'] ?? getHostByName(php_uname('n'));

// Build Jio path dynamically
$jio_path = $protocol . $host_jio . str_replace(' ', '%20', str_replace(basename($_SERVER['PHP_SELF']), '', $_SERVER['PHP_SELF']));

// --- SERVER COMPATIBILITY CHECK ---
function isApache(): bool {
    $software = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    $compatibleServers = ['apache', 'litespeed', 'openlitespeed', 'win32', 'win64'];
    foreach ($compatibleServers as $server) {
        if (strpos($software, $server) !== false) return true;
    }
    return false;
}

// --- UPDATED CRAWLER & API HEADERS (VERSION 452) ---
function jio_headers($cookies, $access_token, $crm, $device_id, $ssoToken, $uniqueId) {
    return [
        "Cookie: $cookies",
        "accesstoken: $access_token",
        "appkey: NzNiMDhlYcQyNjJm",
        "crmid: $crm",
        "deviceId: $device_id",
        "devicetype: phone",
        "os: android",
        "osVersion: 14",
        "srno: 250918144000", // Updated Serial
        "ssotoken: $ssoToken",
        "subscriberId: $crm",
        "uniqueId: $uniqueId",
        "User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7",
        "versionCode: 452", // Updated Version
        "Origin: https://www.jiocinema.com",
        "Referer: https://www.jiocinema.com/",
    ];
}

// --- NEW: SONY SPECIFIC HEADERS ---
function jio_sony_headers($ck, $id, $crm, $device_id, $access_token, $uniqueId, $ssoToken) {
    return [
        "Cookie: " . hex2bin($ck),
        "appkey: NzNiMDhlYcQyNjJm",
        "accesstoken: $access_token",
        "channel_id: $id",
        "crmid: $crm",
        "deviceId: $device_id",
        "devicetype: phone",
        "x-platform: android",
        "srno: 250918144000",
        "ssotoken: $ssoToken",
        "subscriberId: $crm",
        "uniqueId: $uniqueId",
        "User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7",
        "versionCode: 452",
        "appname: RJIL_JioTV"
    ];
}

// --- ROBUST CURL DATA FETCH (SSL BYPASS INCLUDED) ---
function cUrlGetData($url, $headers = null, $post_fields = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    
    // CRITICAL FOR LOCALHOST/XAMPP
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

    if (!empty($post_fields)) {
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }
    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $data = curl_exec($ch);
    if (curl_errno($ch)) error_log('JioTV cURL Error: ' . curl_error($ch));
    curl_close($ch);
    return $data;
}

// --- REFRESH LOGIC ---
function refresh_token() {
    $filePath = DATA_FOLDER . '/creds.jtv';
    if (!file_exists($filePath) || (time() - filemtime($filePath) > TOKEN_EXPIRY_TIME)) {
        return cUrlGetData($GLOBALS['jio_path'] . '/login/refreshLogin.php');
    }
    return null;
}

function get_and_refresh_cookie($url, $headers) {
    $filePath = DATA_FOLDER . '/cookie.jtv';
    if (!file_exists($filePath) || (time() - filemtime($filePath) > COOKIE_EXPIRY_TIME)) {
        $cookies = getCookiesFromUrl($url, $headers);
        if (isset($cookies['__hdnea__'])) {
            $cooKee = bin2hex('__hdnea__=' . $cookies['__hdnea__']);
            file_put_contents($filePath, $cooKee);
            return $cooKee;
        }
    }
    return @file_get_contents($filePath);
}

// --- DATA SECURITY (ENCRYPT/DECRYPT) ---
function getCRED() {
    $filePath = DATA_FOLDER . '/creds.jtv';
    $keyPath = DATA_FOLDER . '/credskey.jtv';
    if(!file_exists($filePath) || !file_exists($keyPath)) return false;
    return decrypt_data(file_get_contents($filePath), file_get_contents($keyPath));
}

function encrypt_data($data, $key) {
    $encrypted = array_map(fn($char) => chr(ord($char) + (int)$key), str_split($data));
    return base64_encode(implode('', $encrypted));
}

function decrypt_data($e_data, $key) {
    $encrypted = base64_decode($e_data);
    $decrypted = array_map(fn($char) => chr(ord($char) - (int)$key), str_split($encrypted));
    return implode('', $decrypted);
}

// --- HELPER FUNCTIONS ---
function getCookiesFromUrl($url, $headers = [], $post_fields = null) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    if ($post_fields !== null) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
    }
    $response = curl_exec($ch);
    $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $header = substr($response, 0, $header_size);
    curl_close($ch);
    return extractCookies($header);
}

function extractCookies($header) {
    $cookies = [];
    foreach (explode("\r\n", $header) as $line) {
        if (preg_match('/^Set-Cookie:\s*([^;]*)/mi', $line, $matches)) {
            parse_str($matches[1], $cookie);
            $cookies = array_merge($cookies, $cookie);
        }
    }
    return $cookies;
}

function getJioTvData($id) {
    $cred = getCRED();
    if(!$cred) return null;
    
    $jio_cred = json_decode($cred, true) ?? [];
    extract($jio_cred['sessionAttributes']['user'] ?? []);

    $access_token = $jio_cred['authToken'] ?? '';
    $crm = $subscriberId ?? '';
    $uniqueId = $unique ?? '';
    $device_id = $jio_cred['deviceId'] ?? '';

    $post_data = http_build_query(['stream_type' => 'Seek', 'channel_id' => $id]);

    $headers = [
        "Host: jiotvapi.media.jio.com",
        "Content-Type: application/x-www-form-urlencoded",
        "appkey: NzNiMDhlYzQyNjJm",
        "channel_id: $id",
        "userid: $crm",
        "crmid: $crm",
        "deviceId: $device_id",
        "devicetype: phone",
        "os: android",
        "dm: Xiaomi 22101316UP",
        "osversion: 14",
        "srno: 250918144000",
        "accesstoken: $access_token",
        "subscriberid: $crm",
        "uniqueId: $uniqueId",
        "User-Agent: okhttp/4.12.13", // Updated UA
        "versionCode: 452",
    ];

    $response = cUrlGetData("https://jiotvapi.media.jio.com/playback/apis/v1/geturl?langId=6", $headers, $post_data);
    return json_decode($response);
}
