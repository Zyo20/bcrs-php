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
    $validStatuses = ['approved', 'for_delivery', 'for_pickup', 'picked_up', 'completed'];
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
    
    $db->beginTransaction();
    
    // Update reservation status to completed
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'completed' 
        WHERE id = ?
    ");
    $stmt->execute([$reservationId]);
    
    // Use the admin ID from session instead of user_id
    if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
        throw new Exception("Admin session is invalid. Please log in again.");
    }
    
    // Add status history
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history 
        (reservation_id, status, notes, created_by_admin_id)
        VALUES (?, 'completed', 'Items marked as returned', ?)
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    
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
            // For facilities, just update availability
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
    $message = "Your reservation #" . $reservationId . " has been marked as returned. Thank you for using our services!";
    $stmt->execute([$reservation['user_id'], $message, "index.php?page=view_reservation&id=" . $reservationId]);
    
    $db->commit();
    
    // Set success message
    $_SESSION['flash_message'] = "Items marked as returned successfully! Resources are now available for other reservations.";
    $_SESSION['flash_type'] = "success";
    
    // Send email notification to user
    $subject = "Your Resource Return Confirmed";
    $emailBody = "Dear " . $reservation['first_name'] . ",\n\n";
    $emailBody .= "This is to confirm that your reserved items from reservation #" . $reservationId . " have been returned successfully.\n\n";
    $emailBody .= "Thank you for using our barangay resource management services!\n\n";
    $emailBody .= "Regards,\nBarangay Resource Management Team";
    
    // Send email (uncomment once SMTP is configured)
    // sendEmail($reservation['email'], $subject, $emailBody);
    
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