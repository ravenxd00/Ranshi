<?php
/* Integrated mpd.php - Fixed Token Extraction & URL Logic */

ini_set('display_errors', 0);
error_reporting(0);

/* ===== 1. DEFINE PATHS & HELPERS ===== */
define('DATA_FOLDER', __DIR__ . '/assets/data');

function decrypt_data($e_data, $key) {
    $encrypted = base64_decode($e_data);
    $decrypted = array_map(fn($char) => chr(ord($char) - (int)$key), str_split($encrypted));
    return implode('', $decrypted);
}

function getCRED() {
    $filePath = DATA_FOLDER . '/creds.jtv';
    $keyPath = DATA_FOLDER . '/credskey.jtv';
    if(!file_exists($filePath) || !file_exists($keyPath)) return false;
    return decrypt_data(file_get_contents($filePath), file_get_contents($keyPath));
}

/* ===== 2. SECURITY CHECK ===== */
if (!getCRED()) {
    header("HTTP/1.1 401 Unauthorized");
    exit("Login required: Credentials missing or invalid.");
}

/* ===== 3. GET & VALIDATE ID ===== */
$id = $_GET['id'] ?? null;
if (!$id) {
    header("HTTP/1.1 400 Bad Request");
    exit("Missing id");
}

/* ===== 4. LOAD CHANNEL DATA (REMOTE) ===== */
$jsonUrl = "https://api.npoint.io/1ac6a202753a353b7bb5";
$ch = curl_init($jsonUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true
]);
$jsonData = curl_exec($ch);
curl_close($ch);

if (!$jsonData) {
    header("HTTP/1.1 500 Internal Server Error");
    exit("Remote database unreachable");
}

$channels = json_decode($jsonData, true);
$channel = null;
if (is_array($channels)) {
    foreach ($channels as $c) {
        if ((string)($c['channel_id'] ?? '') === (string)$id) {
            $channel = $c;
            break;
        }
    }
}

if (!$channel) {
    header("HTTP/1.1 404 Not Found");
    exit("Channel not found");
}

$channelName = $channel['channel_name'] ?? '';
$baseUrl = $channel['channel_url'] ?? '';

/* ===== 5. DEFINE SPECIAL CHANNELS (STAR SPORTS) ===== */
$starSportsChannels = [
    "Star Sports Select 1 HD", "Star Sports Select 1", "Star Sports Select 2 HD", "Star Sports Select 2",
    "Star Sports 1 HD", "Star Sports 1", "Star Sports 1 Hindi HD", "Star Sports 1 Hindi",
    "Star Sports 2 HD", "Star Sports 2", "Star Sports 2 Hindi HD", "Star Sports 2 Hindi",
    "Star Sports 3", "Star Sports 1 Tamil HD", "Star Sports 1 Tamil", "Star Sports 1 Telugu HD",
    "Star Sports 1 Telugu", "Star Sports 1 Kannada", "Star Sports 2 Kannada", "Star Sports 2 Tamil HD",
    "Star Sports 2 Tamil", "Star Sports 2 Telugu HD", "Star Sports 2 Telugu"
];

$token = '';

/* ===== 6. TOKEN GENERATION LOGIC ===== */

// --- A. PRIMARY FETCH (Star Sports Specific) ---
if (in_array($channelName, $starSportsChannels)) {
    // FIX: Removed base64_decode from a non-base64 string
    $starApiUrl = "https://servertvhub.site/superlive/api.php?id=sports";
    
    $ch = curl_init($starApiUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        $results = $data['failed_results'] ?? [];
        
        foreach ($results as $item) {
            if ($item['channel_name'] === $channelName) {
                $finalUrlInJson = $item['error_details']['final_url'] ?? '';
                // Regex to extract token from the URL provided in JSON
                if (preg_match('/__hdnea__=([^;|\s&]+)/', $finalUrlInJson, $m)) {
                    $token = $m[1];
                    break;
                }
            }
        }
    }
}

// --- B. SECONDARY FETCH (Fallback Handshake) ---
if (!$token) {
    // FIX: Added missing quote and variable fix
    $localTokenApi = "https://servertvhub.site/superlive/api.php";

    $ch = curl_init($localTokenApi);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36"
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);

    if ($response) {
        $data = json_decode($response, true);
        // Accessing index [1] for the cookie as per your JSON format
        $cookieString = $data[1]['cookie'] ?? '';
        if (preg_match('/__hdnea__=([^;|\s&]+)/', $cookieString, $m)) {
            $token = $m[1];
        }
    }
}

/* ===== 7. FINAL VALIDATION & REDIRECT ===== */
if (!$token) {
    header("HTTP/1.1 503 Service Unavailable");
    exit("Token generation failed: All methods exhausted.");
}

// Clean any old tokens and append fresh one
$baseUrl = preg_replace('/[?&]__hdnea__=[^&]+/', '', $baseUrl);
$sep = (strpos($baseUrl, '?') !== false) ? '&' : '?';
$finalUrl = $baseUrl . $sep . "__hdnea__=" . $token;

header("Access-Control-Allow-Origin: *");
header("Location: " . $finalUrl, true, 302);
exit;
