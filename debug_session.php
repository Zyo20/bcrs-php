<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include functions for isAdmin() check
require_once 'includes/functions.php';

// Set content type to plain text for easier reading
header('Content-Type: text/plain');

echo "SESSION DEBUG INFORMATION\n";
echo "========================\n\n";

echo "Session status: " . (session_status() === PHP_SESSION_ACTIVE ? "ACTIVE" : "NOT ACTIVE") . "\n\n";

echo "Session variables:\n";
echo "----------------\n";
if (empty($_SESSION)) {
    echo "No session variables found. You might not be logged in.\n";
} else {
    foreach ($_SESSION as $key => $value) {
        echo "$key: ";
        if ($key === 'password' || $key === 'csrf_token') {
            echo "[HIDDEN FOR SECURITY]\n";
        } else if (is_bool($value)) {
            echo ($value ? "true" : "false") . "\n";
        } else if (is_array($value)) {
            echo "[ARRAY]\n";
        } else {
            echo $value . "\n";
        }
    }
}

echo "\n";
echo "Admin status check:\n";
echo "----------------\n";
echo "isLoggedIn(): " . (isLoggedIn() ? "true" : "false") . "\n";
echo "isAdmin(): " . (isAdmin() ? "true" : "false") . "\n";

echo "\n";
echo "If isLoggedIn() is true but isAdmin() is false, your account is not recognized as an admin.\n";
echo "If both are true but you still see the user dashboard, there might be a routing issue.\n";

echo "\n";
echo "To fix this:\n";
echo "- Try navigating directly to index?page=admin\n";
echo "- Check that the 'is_admin' session variable is set to true\n";
echo "- Log out and log back in as an admin\n";
?>