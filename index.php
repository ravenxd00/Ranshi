<?php
/**
 * Main Dashboard - index.php
 * Handles Proxy Toggling, File Integrity, and Navigation
 */
session_start();

// 1. FILE INTEGRITY CHECK
// Check for the credentials files required for your IPTV system.
$required_files = [
    'assets/data/creds.jtv',
    'assets/data/credskey.jtv'
];

$all_files_present = true;
foreach ($required_files as $file) {
    if (!file_exists($file)) {
        $all_files_present = false;
        break;
    }
}

// If files are missing, redirect to login to regenerate them.
if (!$all_files_present) {
    header("Location: login/index.php");
    exit();
}

// 2. PROXY TOGGLE STATE
// Used by playlist.php to decide whether to wrap stream URLs in proxy.php.
$isProxyEnabled = isset($_SESSION['proxy_active']) && $_SESSION['proxy_active'] === true;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stream Control Panel</title>
    <style>
        :root {
            --bg: #0d1117;
            --card: #161b22;
            --text: #c9d1d9;
            --primary: #238636;
            --secondary: #1f6feb;
            --danger: #da3633;
            --border: #30363d;
            --orange: #f0883e;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Helvetica, Arial, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
        }

        .container {
            background: var(--card);
            padding: 2.5rem;
            border-radius: 12px;
            border: 1px solid var(--border);
            width: 100%;
            max-width: 400px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            text-align: center;
        }

        h2 { margin: 0 0 0.5rem 0; color: #ffffff; font-weight: 600; }
        p { color: #8b949e; font-size: 0.9rem; margin-bottom: 2rem; }

        .status-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 2rem;
            background: rgba(35, 134, 54, 0.15);
            color: #3fb950;
            border: 1px solid rgba(63, 185, 80, 0.4);
        }

        .btn {
            display: block;
            width: 100%;
            padding: 14px;
            margin: 12px 0;
            border-radius: 8px;
            border: none;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            box-sizing: border-box;
            transition: all 0.2s ease;
        }

        .btn:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn:active { transform: translateY(0); }

        /* Proxy Toggling Colors */
        .btn-proxy-on { background-color: var(--primary); color: white; border: 1px solid var(--primary); }
        .btn-proxy-off { background: transparent; color: #fff; border: 1px solid var(--border); }
        
        .btn-refresh { background-color: var(--secondary); color: white; }
        .btn-m3u { background-color: var(--orange); color: white; }
        .btn-logout { background-color: transparent; color: var(--danger); border: 1px solid var(--danger); margin-top: 1.5rem; }

        .footer-info {
            margin-top: 2rem;
            font-size: 0.75rem;
            color: #484f58;
            border-top: 1px solid var(--border);
            padding-top: 1rem;
        }
    </style>
</head>
<body>

<div class="container">
    <h2>Control Panel</h2>
    <div class="status-badge">● System Online</div>
    <p>Manage worldwide proxy routing and streaming data.</p>

    <form action="proxy.php" method="POST">
        <button type="submit" class="btn <?php echo $isProxyEnabled ? 'btn-proxy-on' : 'btn-proxy-off'; ?>">
            <?php echo $isProxyEnabled ? 'Proxy: ENABLED (Worldwide)' : 'Proxy: DISABLED (Local)'; ?>
        </button>
    </form>

    <a href="login/refreshLogin.php" class="btn btn-refresh">Refresh Session/Tokens</a>

    <a href="playlist.php" target="_blank" class="btn btn-m3u">Get M3U Playlist</a>

    <a href="logout.php" class="btn btn-logout">Logout</a>

    <div class="footer-info">
        SERVER TV HUB
    </div>
</div>

</body>
</html>
