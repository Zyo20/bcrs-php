<?php
// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page";
    $_SESSION['flash_type'] = "error";
    header("Location: index");
    exit;
}

// Check if reservation ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "Reservation ID not provided";
    $_SESSION['flash_type'] = "error";
    header("Location: index?page=admin&section=reservations");
    exit;
}

$reservationId = (int)$_GET['id'];

try {
    // Get reservation details
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email, u.contact_number
        FROM reservations r 
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception("Reservation not found");
    }
    
    // Check if reservation can be marked as completed
    $validStatuses = ['approved', 'for_delivery', 'for_pickup'];
    if (!in_array($reservation['status'], $validStatuses)) {
        throw new Exception("This reservation cannot be marked as completed in its current status");
    }

    // Start transaction
    $db->beginTransaction();
    
    // Update reservation status to completed
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'completed' 
        WHERE id = ?
    ");
    $stmt->execute([$reservationId]);
    
    // Add status history entry
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history 
        (reservation_id, status, notes, created_by)
        VALUES (?, 'completed', 'Reservation marked as completed', ?)
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    
    // Create notification for the user
    $message = "Your reservation #" . $reservationId . " has been marked as completed. Thank you for using our services!";
    $notifLink = "index?page=view_reservation&id=" . $reservationId;
    createNotification($reservation['user_id'], $message, $notifLink);
    
    // If there's a contact number, send SMS notification
    if (!empty($reservation['contact_number'])) {
        $smsMessage = "Your reservation #$reservationId has been marked as completed. Thank you for using our barangay resource services!";
        sendSMS($reservation['contact_number'], $smsMessage);
    }
    
    $db->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Reservation #" . $reservationId . " has been marked as completed successfully!";
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['flash_message'] = "Error marking reservation as completed: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Redirect back to view_reservation page
header("Location: index?page=admin&section=view_reservation&id=" . $reservationId);
exit;
?>