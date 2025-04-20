<?php
ob_start(); // Start output buffering
session_start();
require_once 'config/database.php';
require_once 'includes/functions.php';

// Handle notification API requests
if (isset($_GET['get_notifications'])) {
    // Output directly from here instead of including the file
    header('Content-Type: application/json');
    
    if (!isLoggedIn()) {
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    
    $userId = $_SESSION['user_id'];
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
    
    try {
        // Get notifications for this user
        $stmt = $db->prepare("
            SELECT id, message, link, is_read, created_at 
            FROM notifications 
            WHERE user_id = ? 
            ORDER BY created_at DESC
            LIMIT ?
        ");
        // Use PDO::PARAM_INT for the LIMIT parameter to avoid SQL syntax errors
        $stmt->bindParam(1, $userId);
        $stmt->bindParam(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Count unread notifications
        $stmt = $db->prepare("
            SELECT COUNT(*) AS unread_count 
            FROM notifications 
            WHERE user_id = ? AND is_read = 0
        ");
        $stmt->execute([$userId]);
        $unreadCount = $stmt->fetch(PDO::FETCH_ASSOC)['unread_count'];
        
        echo json_encode([
            'success' => true, 
            'notifications' => $notifications,
            'unreadCount' => (int)$unreadCount
        ]);
    } catch (PDOException $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
    exit;
}

if (isset($_GET['notification_action'])) {
    // Handle notification actions directly in index.php
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
            
            echo json_encode(['success' => true]);
    
        } elseif ($action === 'clear') {
            if (!isset($input['id']) || !is_numeric($input['id'])) {
                echo json_encode(['success' => false, 'message' => 'Invalid notification ID.']);
                exit;
            }
            $notificationId = $input['id'];
            $stmt = $db->prepare("DELETE FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $userId]);
            
            echo json_encode(['success' => true]);
    
        } elseif ($action === 'clear_all') {
            $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
            $stmt->execute([$userId]);
            echo json_encode(['success' => true]);
    
        } else {
            echo json_encode(['success' => false, 'message' => 'Unknown action.']);
        }
    
    } catch (PDOException $e) {
        error_log("Notification action error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred.']);
    }
    exit;
}

// Route handling
$page = isset($_GET['page']) ? $_GET['page'] : 'home';

// Include header
include 'includes/header.php';

// Load the appropriate page
switch ($page) {
    case 'home':
        include 'pages/home.php';
        break;
    case 'register':
        include 'pages/register.php';
        break;
    case 'login':
        include 'pages/login.php';
        break;
    case 'resources':
        include 'pages/resources.php';
        break;
    case 'reservation':
        include 'pages/reservation.php';
        break;
    case 'view_reservation':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to view reservation details.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/view_reservation.php';
        break;
    case 'view_status_detail': // Add this case
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to view status details.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/view_status_detail.php';
        break;
    case 'cancel_reservation':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to cancel a reservation.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/cancel_reservation.php';
        break;
    case 'dashboard':
        // Check if user is logged in and has user role
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
            show404("You don't have permission to access that page.");
        }
        include 'pages/dashboard.php';
        break;
    case 'admin':
        // Check if user is admin
        if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
            show404("You don't have permission to access the admin panel.");
        }
        include 'pages/admin/index.php';
        break;
    case 'payment_history':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to view payment history.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/payment_history.php';
        break;
    case 'payment_information':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to view payment information.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/payment_information.php';
        break;
    case 'edit_profile':
        // Check if user is logged in
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash_message'] = "Please login to edit your profile.";
            $_SESSION['flash_type'] = "error";
            header("Location: index?page=login");
            exit;
        }
        include 'pages/edit_profile.php';
        break;
    default:
        // Show 404 page for invalid page requests
        show404("The page you requested was not found.");
        break;
}

// Include footer
include 'includes/footer.php';
?>