<?php
session_start(); // Required to detect the Proxy Toggle state
ini_set('display_errors', 0);
error_reporting(0);

/* ===== 1. DYNAMIC BASE URL & PROXY STATUS ===== */
$protocol = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://");
$host     = $_SERVER['HTTP_HOST'];
$folder   = rtrim(dirname($_SERVER['PHP_SELF']), '/\\');
$base     = $protocol . $host . $folder;

// Detect if Proxy is ON from index.php toggle
$useProxy = isset($_SESSION['proxy_active']) && $_SESSION['proxy_active'] === true;

/* ===== ASSETS ===== */
$mainLogo = "https://ik.imagekit.io/yjtx9nh9y/IMG_20250207_083415_447.jpg";
$jioLogo  = "https://ik.imagekit.io/yjtx9nh9y/Jio-TV-Logo.png";
$tgGroup  = "https://t.me/your_telegram_link"; 

/* ===== LOAD REMOTE JSON ===== */
$jsonUrl = "https://api.npoint.io/1ac6a202753a353b7bb5";
$jsonRaw = @file_get_contents($jsonUrl);

if (!$jsonRaw) {
    header("Content-Type: text/plain");
    exit("Error: Unable to fetch channels.");
}

$data = json_decode($jsonRaw, true);

/* ===== HEADER ===== */
header("Content-Type: application/vnd.apple.mpegurl"); 
header("Content-Disposition: inline; filename=\"playlist.m3u\"");

echo "#EXTM3U x-tvg-url=\"\" logo=\"$mainLogo\"\n";
echo "#EXTREM: Proxy Mode: " . ($useProxy ? "ON (Worldwide Routing)" : "OFF (Local/Direct)") . "\n";
echo "#EXTREM: Join: $tgGroup\n\n";

/* ===== LOOP ===== */
foreach ($data as $c) {
    $id    = $c['channel_id'] ?? '';
    $name  = $c['channel_name'] ?? 'Unknown';
    $rawGroup = $c['channel_genre'] ?? 'Live';
    $group = "JioTv+ " . strtoupper($rawGroup);
    $url   = $c['channel_url'] ?? '';

    if (!$id || !$url) continue;

    // Smart Logo Logic
    $logo = !empty($c['channel_logo']) ? $c['channel_logo'] : $jioLogo;

    /* ===== DYNAMIC URL GENERATION ===== */
    // Generate internal paths for manifest and keys
    $mpdPath = $base . "/mpd.php?id=" . $id;
    $keyPath = $base . "/keys.php?id=" . $id;

    // If Proxy is ON, wrap the internal logic through proxy.php for worldwide support
    if ($useProxy) {
        $finalStreamUrl = $base . "/proxy.php?url=" . urlencode($mpdPath);
        $finalKeyUrl    = $base . "/proxy.php?url=" . urlencode($keyPath);
    } else {
        $finalStreamUrl = $mpdPath;
        $finalKeyUrl    = $keyPath;
    }

    /* ===== M3U OUTPUT ===== */
    echo '#EXTINF:-1 tvg-id="'.$id.'" tvg-name="'.$name.'" tvg-logo="'.$logo.'" group-title="'.$group.'",'.$name."\n";

    if (stripos($url, '.mpd') !== false) {
        // DASH/MPD Logic with ClearKey support for Android TV/ExoPlayer
        echo '#KODIPROP:inputstream.adaptive.manifest_type=mpd' . "\n";
        echo '#KODIPROP:inputstream.adaptive.license_type=clearkey' . "\n";
        echo '#KODIPROP:inputstream.adaptive.license_key=' . $finalKeyUrl . "\n";
        echo '#EXT-X-DRM-ID: ' . $id . "\n";
        echo '#EXT-X-LICENSE-URL: ' . $finalKeyUrl . "\n";
        echo $finalStreamUrl . "\n\n";
    } else {
        // Standard HLS Logic: Wrap original URL in proxy if active
        if ($useProxy) {
            echo $base . "/proxy.php?url=" . urlencode($url) . "\n\n";
        } else {
            echo $url . "\n\n";
        }
    }
}
?>
