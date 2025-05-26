<?php
// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Check if reservation ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['flash_message'] = "Reservation ID not provided";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=admin&section=reservations");
    exit;
}

$reservationId = (int)$_GET['id'];

try {
    // Get reservation details
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.email
        FROM reservations r 
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        throw new Exception("Reservation not found");
    }
    
    // Check if reservation can be marked as returned
    $validStatuses = ['approved', 'for_delivery', 'completed', 'delivered'];
    if (!in_array($reservation['status'], $validStatuses)) {
        throw new Exception("This reservation cannot be marked as returned in its current status");
    }

    // Get reservation items
    $stmt = $db->prepare("
        SELECT ri.*, r.name as resource_name, r.category
        FROM reservation_items ri
        JOIN resources r ON ri.resource_id = r.id
        WHERE ri.reservation_id = ?
    ");
    $stmt->execute([$reservationId]);
    $items = $stmt->fetchAll();
    
    // Determine if this is a facility-only reservation
    $hasFacilitiesOnly = true;
    foreach ($items as $item) {
        if ($item['category'] !== 'facility') {
            $hasFacilitiesOnly = false;
            break;
        }
    }
    
    $db->beginTransaction();
    
    // Update reservation status based on items type
    // For facility-only reservations, set status back to 'approved' so they can be reserved again
    // For equipment or mixed reservations, set to 'completed'
    $newStatus = $hasFacilitiesOnly ? 'approved' : 'completed';
    $statusNote = $hasFacilitiesOnly ? 'Items returned and marked as reserved' : 'Items marked as returned';
    
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = ? 
        WHERE id = ?
    ");
    $stmt->execute([$newStatus, $reservationId]);
    
    // Use the admin ID from session instead of user_id
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        throw new Exception("Admin session is invalid. Please log in again.");
    }
    
    // Add status history
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history 
        (reservation_id, status, notes, created_by_admin_id)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute([$reservationId, $newStatus, $statusNote, $_SESSION['user_id']]);
    
    // Update resource availability and quantities
    foreach ($items as $item) {
        if ($item['category'] === 'equipment') {
            // For equipment, update availability and increase available quantity
            $stmt = $db->prepare("
                UPDATE resources
                SET availability = 'available',
                    quantity = quantity + ?
                WHERE id = ?
            ");
            $stmt->execute([$item['quantity'], $item['resource_id']]);
        } else {
            // For facilities, just update availability to available
            $stmt = $db->prepare("
                UPDATE resources
                SET availability = 'available'
                WHERE id = ?
            ");
            $stmt->execute([$item['resource_id']]);
        }
    }
    
    // Add notification to user about returned items
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, message, link)
        VALUES (?, ?, ?)
    ");
    $message = "Your reservation #" . $reservationId . " has been marked as returned and completed. Thank you for using our services!";
    $stmt->execute([$reservation['user_id'], $message, "index.php?page=view_reservation&id=" . $reservationId]);
    
    // If there's a contact number, send SMS notification
    if (!empty($reservation['contact_number'])) {
        $smsMessage = "Your reservation #$reservationId has been marked as returned and completed. Thank you for using our services!";
        sendSMS($reservation['contact_number'], $smsMessage);
    }
    
    $db->commit();
    
    // Set success message
    $_SESSION['flash_message'] = $hasFacilitiesOnly ? 
        "Items returned successfully! Facility is now marked as reserved." :
        "Items marked as returned successfully! Resources are now available for other reservations.";
    $_SESSION['flash_type'] = "success";
    
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    $_SESSION['flash_message'] = "Error marking items as returned: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Redirect back to reservations page
header("Location: index.php?page=admin&section=reservations");
exit;
?>