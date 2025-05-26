<?php
session_start();

// Save flash message for later
$flashMessage = 'You have been successfully logged out.';
$flashType = 'success';

// Destroy all session data
$_SESSION = array();

// If session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Start a new session for the flash message
session_start();
$_SESSION['flash_message'] = $flashMessage;
$_SESSION['flash_type'] = $flashType;

// Redirect to home page without .php extension
header("Location: ../index?page=login");
exit;
?>