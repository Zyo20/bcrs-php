<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Set content type to JSON
header('Content-Type: application/json');

// Session timeout in seconds (5 minutes)
$sessionTimeout = 300;

// Action parameter
$action = $_GET['action'] ?? '';

// Handle session status requests
if ($action === 'check') {
    // If user is not logged in, return session expired
    if (!isset($_SESSION['user_id'])) {
        echo json_encode([
            'status' => 'expired',
            'message' => 'Your session has expired. Please log in again.'
        ]);
        exit;
    }

    // Get current time
    $currentTime = time();
    
    // If last activity timestamp doesn't exist, create it
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $currentTime;
    }
    
    // Calculate time remaining before session expires
    $elapsedTime = $currentTime - $_SESSION['last_activity'];
    $remainingTime = $sessionTimeout - $elapsedTime;
    
    // If session has expired
    if ($remainingTime <= 0) {
        echo json_encode([
            'status' => 'expired',
            'message' => 'Your session has expired. Please log in again.',
            'remainingTime' => 0
        ]);
        exit;
    }
    
    // Return session status and remaining time
    echo json_encode([
        'status' => 'active',
        'remainingTime' => $remainingTime,
        'timeout' => $sessionTimeout
    ]);
    exit;
}
// Handle session renewal requests
else if ($action === 'renew') {
    // If user is logged in, update last activity time
    if (isset($_SESSION['user_id'])) {
        $_SESSION['last_activity'] = time();
        
        echo json_encode([
            'status' => 'renewed',
            'message' => 'Session has been renewed successfully.',
            'remainingTime' => $sessionTimeout
        ]);
    } else {
        echo json_encode([
            'status' => 'expired',
            'message' => 'Cannot renew an expired session. Please log in again.'
        ]);
    }
    exit;
} 
// Invalid or missing action
else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid action parameter'
    ]);
    exit;
}
?>