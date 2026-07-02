<?php
// Start session to destroy it
session_start();

// List of files to be deleted
$files_to_delete = [
    'assets/data/cookie.jtv',
    'assets/data/creds.jtv',
    'assets/data/credskey.jtv'
];

// Delete the files if they exist
foreach ($files_to_delete as $file) {
    if (file_exists($file)) {
        unlink($file);
    }
}

// Clear all session variables
$_SESSION = array();

// Destroy the session cookie if it exists
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Logged Out</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">

    <div class="bg-white p-8 rounded-lg shadow-lg max-w-sm w-full text-center">
        <div class="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
            <svg class="h-10 w-10 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-800 mb-2">Successfully Logged Out</h2>
        <p class="text-gray-600 mb-6">Your session has ended and temporary credentials have been cleared.</p>

        <a href="index.php" class="inline-block bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-6 rounded-md transition duration-200">
            Return to Login
        </a>
    </div>

</body>
</html>


