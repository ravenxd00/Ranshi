<?php

// * Copyright 2021-2025 SnehTV, Inc.
// * Licensed under MIT (https://github.com/mitthu786/TS-JioTV/blob/main/LICENSE)
// * Created By : TechieSneh

// --- Configuration & Error Handling ---
error_reporting(0);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// Global configuration
$DATA_FOLDER = "../assets/data";

// ------------------------------------------------------------------
// --- CORE UTILITY FUNCTIONS ---
// ------------------------------------------------------------------

/**
 * Retrieves and decrypts the saved JioTV credentials.
 * @return string The decrypted JSON string of credentials.
 */
function getCRED()
{
    global $DATA_FOLDER;
    $filePath = $DATA_FOLDER . "/creds.jtv";
    
    if (!file_exists($filePath) || !file_exists($DATA_FOLDER . "/credskey.jtv")) {
        return "{}";
    }
    
    $key_data = file_get_contents($DATA_FOLDER . "/credskey.jtv");
    $cred_data = decrypt_data(file_get_contents($filePath), $key_data);
    
    return $cred_data;
}

// ENCRYPTION && DECRYPTION (Simple XOR-like cipher)
function encrypt_data($data, $key)
{
    $key = intval($key);
    $encrypted = '';
    for ($i = 0; $i < strlen($data); $i++) {
        $encrypted .= chr(ord($data[$i]) + $key);
    }
    return base64_encode($encrypted);
}

function decrypt_data($e_data, $key)
{
    $key = intval($key);
    $encrypted = base64_decode($e_data);
    $decrypted = '';
    for ($i = 0; $i < strlen($encrypted); $i++) {
        $decrypted .= chr(ord($encrypted[$i]) - $key);
    }
    return $decrypted;
}

// ------------------------------------------------------------------
// --- JIO TV AUTHENTICATION API FUNCTIONS ---
// ------------------------------------------------------------------

/**
 * Sends an OTP to the given mobile number for JioTV login.
 */
function send_jio_otp($mobile)
{
    $j_otp_api = 'https://jiotvapi.media.jio.com/userservice/apis/v1/loginotp/send';
    $j_otp_headers = array('appname: RJIL_JioTV', 'os: android', 'devicetype: phone', 'content-type: application/json', 'user-agent: okhttp/3.14.9');
    $j_otp_payload = array('number' => base64_encode('+91' . $mobile));
    $process = curl_init($j_otp_api);
    curl_setopt($process, CURLOPT_POST, 1);
    curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($j_otp_payload));
    curl_setopt($process, CURLOPT_HTTPHEADER, $j_otp_headers);
    curl_setopt($process, CURLOPT_HEADER, 0);
    curl_setopt($process, CURLOPT_TIMEOUT, 10);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    $j_otp_resp = curl_exec($process);
    $j_otp_info = curl_getinfo($process);
    curl_close($process);
    
    $j_otp_data = @json_decode($j_otp_resp, true);
    $resp = ['status' => 'error', 'user' => $mobile, 'message' => ''];

    if ($j_otp_info['http_code'] == 204) {
        $resp['status'] = "success";
        $resp['message'] = "OTP Sent Successfully";
    } else {
        $resp['message'] = "Jio Error - " . ($j_otp_data['message'] ?? "Unknown Error Occured : Code " . $j_otp_info['http_code']);
    }
    return $resp;
}

/**
 * Verifies the OTP and logs the user into JioTV, saving the authentication data.
 */
function verify_jio_otp($mobile, $otp)
{
    global $DATA_FOLDER;
    $u_name = encrypt_data($mobile, "TS-JIOTV");
    $j_otp_api = 'https://jiotvapi.media.jio.com/userservice/apis/v1/loginotp/verify';
    $j_otp_headers = [
        'appname: RJIL_JioTV', 'os: android', 'devicetype: phone', 'content-type: application/json', 'user-agent: okhttp/3.14.9'
    ];
    $j_otp_payload = [
        'number' => base64_encode('+91' . $mobile),
        'otp' => $otp,
        'deviceInfo' => [
            'consumptionDeviceName' => 'RMX1945',
            'info' => [
                'type' => 'android',
                'platform' => ['name' => 'RMX1945'],
                'androidId' => substr(sha1(time() . rand(00, 99)), 0, 16)
            ]
        ]
    ];

    $process = curl_init($j_otp_api);
    curl_setopt($process, CURLOPT_POST, 1);
    curl_setopt($process, CURLOPT_POSTFIELDS, json_encode($j_otp_payload));
    curl_setopt($process, CURLOPT_HTTPHEADER, $j_otp_headers);
    curl_setopt($process, CURLOPT_HEADER, 0);
    curl_setopt($process, CURLOPT_TIMEOUT, 10);
    curl_setopt($process, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($process, CURLOPT_FOLLOWLOCATION, 1);
    $j_otp_resp = curl_exec($process);
    $j_otp_info = curl_getinfo($process);
    curl_close($process);
    $j_otp_data = @json_decode($j_otp_resp, true);

    $resp = ['status' => 'error', 'user' => $mobile, 'message' => ''];

    if (isset($j_otp_data['ssoToken']) && !empty($j_otp_data['ssoToken'])) {
        if (
            file_put_contents($DATA_FOLDER . "/creds.jtv", encrypt_data(json_encode($j_otp_data), $u_name)) &&
            file_put_contents($DATA_FOLDER . "/credskey.jtv", $u_name)
        ) {
            $resp['status'] = 'success';
            $resp['message'] = 'Jio LoggedIn Successfully';
        } else {
            $resp['message'] = 'Logged In Successfully But Failed To Save Data';
        }
    } else {
        $msg = $j_otp_data['message'] ?? $j_otp_data['errors'][1]['message'] ?? $j_otp_data['errors'][0]['message'] ?? 'Unknown Error Occurred: Code ' . $j_otp_info['http_code'];
        $resp['message'] = 'Jio Error - ' . $msg;
    }
    return $resp;
}

// ------------------------------------------------------------------
// --- LOGIN FORM HANDLER (Screen 1: Send OTP) ---
// ------------------------------------------------------------------

/**
 * Handles the login form submission to send the OTP.
 */
function handleLogin()
{
    // The resend OTP link uses a POST request with the 'resend' flag to bypass the form
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $user = $_POST['username'] ?? $_POST['resend_user']; // Get user from form or resend hidden field
        
        $otp_data = send_jio_otp($user);

        if ($otp_data["status"] == "error") {
            $msg = $otp_data["message"];
            header("Location: " . $_SERVER['PHP_SELF'] . "?screen=login&OtpError&msg=" . urlencode($msg));
            exit();
        } else {
            // Redirect to the OTP verification screen
            header("Location: " . $_SERVER['PHP_SELF'] . "?screen=otpVerify&user=" . $user);
            exit();
        }
    }
    
    $msg = $_GET['msg'] ?? '';
    renderLoginForm($msg);
}

// ------------------------------------------------------------------
// --- OTP VERIFICATION HANDLER (Screen 2: Verify OTP) ---
// ------------------------------------------------------------------

/**
 * Handles the OTP verification form submission.
 */
function handleOTPVerification()
{
    $user = $_GET['user'] ?? '';
    $msg = '';

    if (empty($user)) {
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $otp = implode('', $_POST['otp']);
        $verification_data = verify_jio_otp($user, $otp);

        if ($verification_data["status"] == "success") {
            header("Location: ../index.php?success&msg=Login+Successful");
            exit();
        } else {
            $msg = $verification_data["message"];
            header("Location: " . $_SERVER['PHP_SELF'] . "?screen=otpVerify&user=" . $user . "&error&msg=" . urlencode($msg));
            exit();
        }
    }
    
    $msg = $_GET['msg'] ?? '';
    renderOTPForm($msg, $user);
}

// ------------------------------------------------------------------
// --- HTML RENDER FUNCTIONS ---
// ------------------------------------------------------------------

// Shared styles for both forms
$common_styles = '
    <style>
        .glass-effect {
            background: rgba(17, 24, 39, 0.8);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        .gradient-text {
            background: linear-gradient(45deg, #8B5CF6, #EC4899);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .otp-input {
            width: 3.5rem;
            height: 3.5rem;
            font-size: 1.5rem;
            text-align: center;
        }
    </style>';

/**
 * Renders the HTML login form.
 */
function renderLoginForm($msg)
{
    global $common_styles;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JioTV Login</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://code.iconify.design/2/2.1.2/iconify.min.js"></script>
    <?php echo $common_styles; ?>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="glass-effect rounded-2xl shadow-2xl w-full max-w-md p-8" data-aos="zoom-in">
        <div class="text-center mb-8">
            <img src="https://i.ibb.co/BcjC6R8/jiotv.png"
                alt="JioTV Logo"
                class="w-24 h-24 mx-auto mb-4 filter brightness-125"
                data-aos="fade-down">
            <h1 class="text-3xl font-bold gradient-text mb-2">JioTV Login</h1>
            <p class="text-gray-400">Secure access to premium content</p>
        </div>

        <div id="alert" class="hidden mb-6 p-4 rounded-lg text-sm"></div>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?screen=login" method="POST" class="space-y-6">
            <div>
                <label class="block text-gray-300 mb-2">Mobile Number</label>
                <div class="relative">
                    <span class="absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400">
                        <span class="iconify" data-icon="mdi:cellphone"></span>
                    </span>
                    <input id="username"
                        name="username"
                        type="text"
                        minlength="10"
                        maxlength="10"
                        required
                        class="w-full pl-10 pr-4 py-3 bg-gray-800 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-600 focus:border-transparent text-gray-100 placeholder-gray-500"
                        placeholder="Enter 10-digit mobile number">
                </div>
            </div>

            <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 rounded-lg font-medium transition-all transform hover:scale-[1.02] flex items-center justify-center">
                <span class="iconify mr-2" data-icon="mdi:login"></span>
                Send OTP
            </button>
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-400 text-sm">
                Powered by <span class="gradient-text font-medium">SNEH-TV</span>
            </p>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: false, easing: 'ease-in-out-quad' });

        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            const alertEl = document.getElementById('alert');
            const url_path = '<?php echo $_SERVER['PHP_SELF']; ?>';

            const alertConfig = {
                success: { message: "Login successful! Redirecting...", color: "bg-green-900 text-green-300" },
                error: { message: params.get("msg"), color: "bg-red-900 text-red-300" },
                OtpError: { message: params.get("msg"), color: "bg-red-900 text-red-300" }
            };

            for (const [key, config] of Object.entries(alertConfig)) {
                if (params.has(key)) {
                    alertEl.innerHTML = `
                        <div class="${config.color} p-3 rounded-lg flex justify-between items-center">
                            <span>${config.message}</span>
                            <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-white">
                                <span class="iconify" data-icon="mdi:close"></span>
                            </button>
                        </div>`;
                    alertEl.classList.remove('hidden');

                    if (key === 'success') {
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 1500);
                    }
                    
                    const cleanUrl = url_path + (params.has('screen') ? '?screen=' + params.get('screen') : '');
                    history.replaceState(null, null, cleanUrl);
                    break;
                }
            }
        });
    </script>
</body>
</html>
<?php
}

/**
 * Renders the HTML OTP verification form.
 */
function renderOTPForm($msg, $user)
{
    global $common_styles;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify OTP</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <script src="https://code.iconify.design/2/2.1.2/iconify.min.js"></script>
    <?php echo $common_styles; ?>
</head>
<body class="bg-gray-900 min-h-screen flex items-center justify-center p-4">
    <div class="glass-effect rounded-2xl shadow-2xl w-full max-w-md p-8" data-aos="zoom-in">
        <div class="text-center mb-8">
            <img src="https://i.ibb.co/BcjC6R8/jiotv.png"
                alt="JioTV Logo"
                class="w-24 h-24 mx-auto mb-4 filter brightness-125"
                data-aos="fade-down">
            <h1 class="text-3xl font-bold gradient-text mb-2">Verify OTP</h1>
            <p class="text-gray-400">Enter code sent to **<?php echo htmlspecialchars($user); ?>**</p>
        </div>

        <div id="alert" class="hidden mb-6 p-4 rounded-lg text-sm"></div>

        <form action="<?php echo $_SERVER['PHP_SELF']; ?>?screen=otpVerify&user=<?php echo $user; ?>" method="POST" class="space-y-6">
            <div class="flex justify-center gap-4">
                <?php for ($i = 0; $i < 6; $i++): ?>
                    <input type="number"
                        name="otp[]"
                        class="otp-input bg-gray-800 border border-gray-700 rounded-lg focus:ring-2 focus:ring-purple-600 text-gray-100"
                        maxlength="1"
                        min="0"
                        max="9"
                        required
                        oninput="this.value=this.value.slice(0,1); focusNext(this)">
                <?php endfor; ?>
            </div>

            <div class="text-center text-gray-400 text-sm">
                Didn't receive code?
                <span id="resend" class="text-gray-600 cursor-pointer" onclick="resendOTP()">
                    Resend OTP (<span id="countdown">30</span>s)
                </span>
            </div>

            <button type="submit"
                class="w-full py-3 bg-gradient-to-r from-purple-600 to-pink-600 hover:from-purple-700 hover:to-pink-700 rounded-lg font-medium transition-all transform hover:scale-[1.02] flex items-center justify-center">
                <span class="iconify mr-2" data-icon="mdi:shield-check"></span>
                Verify OTP
            </button>
        </form>
        
        <form id="resendForm" action="<?php echo $_SERVER['PHP_SELF']; ?>?screen=login" method="POST" class="hidden">
            <input type="hidden" name="resend_user" value="<?php echo htmlspecialchars($user); ?>">
        </form>

        <div class="mt-6 text-center">
            <p class="text-gray-400 text-sm">
                Powered by <span class="gradient-text font-medium">SNEH-TV</span>
            </p>
        </div>
    </div>

    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({ duration: 800, once: false, easing: 'ease-in-out-quad' });

        // OTP Input Handling
        function focusNext(input) {
            if (input.value.length === 1) {
                const next = input.nextElementSibling;
                if (next) next.focus();
            }
        }

        // Resend OTP Countdown
        let timer = 30;
        const countdownEl = document.getElementById('countdown');
        const resendBtn = document.getElementById('resend');

        function updateCountdown() {
            countdownEl.textContent = timer;
            if (timer-- <= 0) {
                resendBtn.classList.add('gradient-text', 'hover:opacity-80');
                resendBtn.classList.remove('text-gray-600');
                resendBtn.innerHTML = 'Resend OTP';
                resendBtn.onclick = () => {
                    // Submit the hidden form to trigger a new OTP request via the handleLogin function
                    document.getElementById('resendForm').submit(); 
                };
                clearInterval(countdownInterval);
            }
        }

        let countdownInterval = setInterval(updateCountdown, 1000);

        // Alert Handling
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            const alertEl = document.getElementById('alert');
            const current_user = '<?php echo $user; ?>';
            const url_path = '<?php echo $_SERVER['PHP_SELF']; ?>';

            const alertConfig = {
                success: { message: "Verification successful! Redirecting...", color: "bg-green-900 text-green-300" },
                error: { message: params.get("msg"), color: "bg-red-900 text-red-300" }
            };

            for (const [key, config] of Object.entries(alertConfig)) {
                if (params.has(key)) {
                    alertEl.innerHTML = `
                        <div class="${config.color} p-3 rounded-lg flex justify-between items-center">
                            <span>${config.message}</span>
                            <button onclick="this.parentElement.remove()" class="text-gray-400 hover:text-white">
                                <span class="iconify" data-icon="mdi:close"></span>
                            </button>
                        </div>`;
                    alertEl.classList.remove('hidden');

                    if (key === 'success') {
                        setTimeout(() => {
                            window.location.href = '../index.php';
                        }, 1500);
                    }
                    
                    const cleanUrl = url_path + '?screen=otpVerify&user=' + current_user;
                    history.replaceState(null, null, cleanUrl);
                    break;
                }
            }
        });
    </script>
</body>
</html>
<?php
}

// ------------------------------------------------------------------
// --- APPLICATION ENTRY POINT ---
// ------------------------------------------------------------------

// Determine which screen/handler to run based on the 'screen' URL parameter
$screen = $_GET['screen'] ?? 'login';

if ($screen === 'otpVerify') {
    handleOTPVerification();
} else {
    // Default is the login form (and handles the resend action)
    handleLogin();
}

?>