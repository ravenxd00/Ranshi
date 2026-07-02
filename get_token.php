<?php
// Copyright 2021-2026 SnehTV, Inc.
error_reporting(0);
include "functions.php";

header('Content-Type: application/json');

try {
    // 1. Get channel data to trigger fresh cookie handshake
    $sample_id = "144"; 
    $data = getJioTvData($sample_id);

    if (isset($data->result)) {
        $headers = ["User-Agent: plaYtv/7.1.3 (Linux;Android 14) ExoPlayerLib/2.11.7"];
        
        // 2. Refresh and get the latest HEX cookie from assets/data/cookie.jtv
        $hexCookie = get_and_refresh_cookie($data->result, $headers);

        if ($hexCookie) {
            $rawCookie = hex2bin($hexCookie);

            // 3. FORCE UNIVERSAL ACL (Fixes the specific path issue)
            // Replaces specific paths like /bpk-tv/.../* with the universal /*
            $universalCookie = preg_replace('/acl=[^~]*/', 'acl=/*', $rawCookie);

            // 4. GENERATE TIMESTAMP (IST - Kolkata)
            date_default_timezone_set('Asia/Kolkata');
            $lastUpdated = date('h:i d-m-Y');

            // 5. OUTPUT IN REQUESTED FORMAT
            echo json_encode([
                [
                    "last_updated" => $lastUpdated
                ],
                [
                    "cookie" => $universalCookie
                ]
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

        } else {
            throw new Exception("Failed to retrieve cookie.");
        }
    } else {
        throw new Exception("Handshake failed. Check credentials.");
    }
} catch (Exception $e) {
    echo json_encode(["error" => $e->getMessage()]);
}
