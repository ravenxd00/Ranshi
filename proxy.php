<?php
/* proxy.php - Worldwide Support, Auto-Detection & Toggle Controller */
session_start();

ini_set('display_errors', 0);
error_reporting(0);

/**
 * 1. TOGGLE LOGIC
 * If called from index.php (POST), flip the proxy state and redirect.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_SESSION['proxy_active'] = !(isset($_SESSION['proxy_active']) && $_SESSION['proxy_active'] === true);
    header("Location: index.php");
    exit;
}

/**
 * 2. CORS & HEADERS FOR WORLDWIDE PLAYERS
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit;
}

/**
 * 3. PROXY LOGIC
 */
$targetUrl = $_GET['url'] ?? '';

if (empty($targetUrl)) {
    // If accessed directly without a URL or POST, just go back home
    header("Location: index.php");
    exit;
}

// Identify file type for specific header tuning
$isKey = (stripos($targetUrl, 'keys.php') !== false);
$isMpd = (stripos($targetUrl, 'mpd.php') !== false || stripos($targetUrl, '.mpd') !== false);
$isSegment = preg_match('/\.(ts|m4s|m4a|m4v)$/i', $targetUrl);

$ch = curl_init($targetUrl);

// Headers to bypass geo-restrictions and match your streaming projects
$outHeaders = [
    "User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7",
    "X-Forwarded-For: " . ($_SERVER['REMOTE_ADDR'] ?? '49.36.0.1'), 
    "Accept-Encoding: gzip, deflate",
    "Connection: keep-alive"
];

if ($isMpd) {
    $outHeaders[] = "Referer: https://www.jiotv.com/";
}

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HTTPHEADER     => $outHeaders,
    CURLOPT_TIMEOUT        => ($isSegment ? 10 : 25), 
    CURLOPT_ENCODING       => "", 
]);

$response = curl_exec($ch);
$contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Set proper Content-Type for the worldwide player
if ($isKey) {
    header('Content-Type: application/json; charset=UTF-8');
} elseif ($contentType) {
    header("Content-Type: $contentType");
}

http_response_code($httpCode);
echo $response;
exit;
