<?php
/* keys.php - Final Fixed Version for 403 Errors */

ini_set('display_errors', 0);
error_reporting(0);

header('Content-Type: application/json; charset=UTF-8');
header("Access-Control-Allow-Origin: *");

// --- DATA PATHS ---
define('DATA_FOLDER', __DIR__ . '/assets/data');

/* ===== HELPER FUNCTIONS ===== */
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

function base64UrlEncode($data) {
    // Standard Base64 to Base64Url conversion
    // CRITICAL: rtrim removes '=' padding which players reject as "incorrect key"
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

/* ===== 1. SECURITY & VALIDATION ===== */
$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$blockedAgents = ['curl', 'postman', 'insomnia', 'httpie', 'wget'];
foreach ($blockedAgents as $b) {
    if (stripos($ua, $b) !== false) {
        http_response_code(403);
        exit(json_encode(["error" => "Access Denied"]));
    }
}

if (!isset($_GET['id']) || empty($_GET['id'])) {
    http_response_code(400);
    exit(json_encode(["error" => "Missing channel id"]));
}

/* ===== 2. CHECK LOCAL CREDENTIALS ===== */
if (!getCRED()) {
    http_response_code(401);
    exit(json_encode(["error" => "Unauthorized: Login required"]));
}

/* ===== 3. LOAD REMOTE CHANNEL DATA ===== */
$id = $_GET['id'];
$jsonUrl = "https://api.npoint.io/1ac6a202753a353b7bb5";
$ch = curl_init($jsonUrl);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false 
]);
$jsonRaw = curl_exec($ch);
curl_close($ch);

if (!$jsonRaw) {
    http_response_code(500);
    exit(json_encode(["error" => "Database unreachable"]));
}

$data = json_decode($jsonRaw, true);

/* ===== 4. KEY SEARCH & RESPONSE ===== */
if (is_array($data)) {
    foreach ($data as $c) {
        if ((string)($c['channel_id'] ?? '') === (string)$id) {
            
            $rawKid = $c['keyId'] ?? '';
            $rawKey = $c['key'] ?? '';

            if (empty($rawKid) || empty($rawKey)) {
                http_response_code(404);
                exit(json_encode(["error" => "Keys not found for this channel"]));
            }

            try {
                // 1. Clean Hex: Strip ALL non-hex characters (spaces, dashes, etc.)
                $cleanKid = preg_replace('/[^a-fA-F0-9]/', '', $rawKid);
                $cleanKey = preg_replace('/[^a-fA-F0-9]/', '', $rawKey);

                // 2. ClearKey MUST be exactly 16 bytes (32 hex characters)
                if (strlen($cleanKid) !== 32 || strlen($cleanKey) !== 32) {
                    // Force pad to 32 characters if there is a minor typo in the JSON database
                    $cleanKid = str_pad(substr($cleanKid, 0, 32), 32, "0", STR_PAD_LEFT);
                    $cleanKey = str_pad(substr($cleanKey, 0, 32), 32, "0", STR_PAD_LEFT);
                }

                // 3. Convert to Binary and then to strict Base64Url
                // We use @ to suppress warnings if hex2bin fails
                $binKid = @hex2bin($cleanKid);
                $binKey = @hex2bin($cleanKey);

                if (!$binKid || !$binKey) {
                    throw new Exception("Invalid Hex format in database");
                }

                $kid_b64 = base64UrlEncode($binKid);
                $key_b64 = base64UrlEncode($binKey);

                // Output JSON in the format Android players expect for ClearKey
                echo json_encode([
                    "keys" => [[
                        "kty" => "oct",
                        "kid" => $kid_b64,
                        "k"   => $key_b64
                    ]],
                    "type" => "temporary"
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                
                exit;
            } catch (Exception $e) {
                http_response_code(500);
                exit(json_encode(["error" => "Key processing error"]));
            }
        }
    }
}

http_response_code(404);
echo json_encode(["error" => "Channel ID not found"]);
