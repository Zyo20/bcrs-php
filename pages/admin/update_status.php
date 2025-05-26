<?php
// Check if user has admin access
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Get reservation ID and status
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$newStatus = isset($_GET['status']) ? $_GET['status'] : '';

// Validate parameters
if ($reservationId <= 0 || !in_array($newStatus, ['for_delivery', 'delivered', 'returned'])) {
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
    } elseif ($newStatus === 'delivered') {
        $notes = 'Items have been delivered to the requester.';
    } elseif ($newStatus === 'for_pickup') {
        $notes = 'Items ready for pickup by user.';
    } elseif ($newStatus === 'picked_up') {
        $notes = 'Items have been picked up by the requester.';
    } elseif ($newStatus === 'returned') {
        $notes = 'Items have been returned by the requester.';
    }
    
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_admin_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$reservationId, $newStatus, $notes, $_SESSION['admin_id']]);
    
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
        } elseif ($newStatus === 'delivered') {
            $message = "Your reservation #$reservationId has been delivered. Please take care of the borrowed items.";
        } elseif ($newStatus === 'returned') {
            $message = "Your reservation #$reservationId has been marked as returned. Thank you for using our barangay resources.";
        }
        
        // Create notification
        $notifLink = "index.php?page=view_reservation&id=" . $reservationId;
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
header("Location: index.php?page=admin&section=view_reservation&id=" . $reservationId);
exit;
?>