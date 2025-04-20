<?php
session_start();
require_once '../config/database.php';
require_once 'functions.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable error logging
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_log('get_notifications.php called');

if (!isLoggedIn()) {
    error_log('User not logged in');
    echo json_encode(['success' => false, 'message' => 'User not logged in']);
    exit;
}

$userId = $_SESSION['user_id'];
error_log('User ID: ' . $userId);
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default to 10 notifications

try {
    // Get notifications for this user
    $stmt = $db->prepare("
        SELECT id, message, link, is_read, created_at 
        FROM notifications 
        WHERE user_id = ? 
        ORDER BY created_at DESC
        LIMIT ?
    ");
    $stmt->execute([$userId, $limit]);
    $notifications = $stmt->fetchAll();
    error_log('Found ' . count($notifications) . ' notifications');
    
    // Count unread notifications
    $stmt = $db->prepare("
        SELECT COUNT(*) AS unread_count 
        FROM notifications 
        WHERE user_id = ? AND is_read = 0
    ");
    $stmt->execute([$userId]);
    $unreadCount = $stmt->fetch()['unread_count'];
    error_log('Unread count: ' . $unreadCount);
    
    echo json_encode([
        'success' => true, 
        'notifications' => $notifications,
        'unreadCount' => (int)$unreadCount
    ]);
    
} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>