<?php
// logout.php
// Include config to access session (if needed)
include('includes/config.php');

// Destroy all session data
session_unset();
session_destroy();

// Clear any session cookies
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to index.php
header('Location: index.php');
exit();
?>