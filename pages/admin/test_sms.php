<?php
// This script tests SMS functionality
header('Content-Type: application/json');

// Check if user is logged in and is admin
session_start();
if (!isset($_SESSION['user_id']) || !isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit;
}

// Load database connection
require_once '../../config/database.php';

// Load SMS utility class
require_once '../../includes/sms.php';

// Test SMS functionality
try {
    // Create SMS utility instance with database connection
    $smsUtil = new SMSUtil($db);
    
    // Check if SMS is enabled in settings
    if (!$smsUtil->isEnabled()) {
        echo json_encode([
            'success' => false,
            'message' => 'SMS functionality is not enabled or properly configured'
        ]);
        exit;
    }
    
    // Send a test message to admin number
    $result = $smsUtil->sendAdminSMS('This is a message from BARSERVE reservation system. If you received this message, it means that the SMS functionality is working correctly.');
    
    // Return result
    echo json_encode($result);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => "Error sending test SMS: " . $e->getMessage()
    ]);
}
?> 