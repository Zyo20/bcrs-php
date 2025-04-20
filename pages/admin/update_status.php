<?php
// Check if user has admin access
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page.";
    $_SESSION['flash_type'] = "error";
    header("Location: index?page=login");
    exit;
}

// Get reservation ID and status
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Validate parameters
if ($reservationId <= 0 || !in_array($newStatus, ['for_delivery', 'for_pickup'])) {
    $_SESSION['flash_message'] = "Invalid request parameters.";
    $_SESSION['flash_type'] = "error";
    header("Location: index?page=admin");
    exit;
}

// Process the status update
try {
    // Begin transaction
    $db->beginTransaction();
    
    // Get reservation details to verify current status
    $stmt = $db->prepare("SELECT status FROM reservations WHERE id = ?");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception("Reservation not found.");
    }
    

    
    // Update the reservation status
    $stmt = $db->prepare("UPDATE reservations SET status = ? WHERE id = ?");
    $stmt->execute([$newStatus, $reservationId]);
    
    // Add entry to status history
    $notes = '';
    if ($newStatus === 'for_delivery') {
        $notes = 'Items set for delivery by administrator.';
    } elseif ($newStatus === 'for_pickup') {
        $notes = 'Items ready for pickup by user.';
    }
    
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history (reservation_id, status, notes, created_by)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$reservationId, $newStatus, $notes, $_SESSION['user_id']]);
    
    // Get user contact information to send notification
    $stmt = $db->prepare("
        SELECT u.contact_number, r.user_id 
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $userInfo = $stmt->fetch();
    
    // Create notification for user
    if ($userInfo) {
        $message = '';
        if ($newStatus === 'for_delivery') {
            $message = "Your reservation #$reservationId is set for delivery. Please prepare to receive your items.";
        } elseif ($newStatus === 'for_pickup') {
            $message = "Your reservation #$reservationId is ready for pickup. Please visit the barangay office to collect your items.";
        }
        
        // Create notification
        $notifLink = "index?page=view_reservation&id=" . $reservationId;
        createNotification($userInfo['user_id'], $message, $notifLink);
        
        // Send SMS if configured and phone number is available
        if (!empty($userInfo['contact_number'])) {
            sendSMS($userInfo['contact_number'], $message);
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Success message
    $_SESSION['flash_message'] = "Reservation status updated to " . ucfirst(str_replace('_', ' ', $newStatus)) . " successfully.";
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    // Rollback on error
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Redirect back to view reservation page
header("Location: index?page=admin&section=view_reservation&id=" . $reservationId);
exit;
?>