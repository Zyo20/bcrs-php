<?php
session_start();
require_once '../config/database.php'; // Adjust path as needed
require_once 'functions.php'; // Adjust path as needed, if functions like isLoggedIn are used

header('Content-Type: application/json');

if (!isLoggedIn()) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit;
}

$userId = $_SESSION['user_id'];
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['action'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request.']);
    exit;
}

$action = $input['action'];

try {
    if ($action === 'mark_all_read') {
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);

    } elseif ($action === 'mark_read') {
        if (!isset($input['id']) || !is_numeric($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
            exit;
        }
        $notificationId = $input['id'];
        $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        
        // Check if any row was actually updated to confirm ownership
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            // Either ID didn't exist or didn't belong to the user
            echo json_encode(['success' => false, 'message' => 'Notification not found or permission denied.']);
        }

    } elseif ($action === 'clear') {
        if (!isset($input['id']) || !is_numeric($input['id'])) {
            echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
            exit;
        }
        $notificationId = $input['id'];
        $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
        $stmt->execute([$notificationId, $userId]);
        // Check if any row was actually deleted to confirm ownership
        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true]);
        } else {
            // Either ID didn't exist or didn't belong to the user
            echo json_encode(['success' => false, 'message' => 'Notification not found or permission denied.']);
        }

    } elseif ($action === 'clear_all') {
        $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
        $stmt->execute([$userId]);
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    }

} catch (PDOException $e) {
    error_log("Notification action error: " . $e->getMessage()); // Log the error
    echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
}

?>