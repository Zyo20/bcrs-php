<?php
// Get reservation ID
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reservationId <= 0) {
    $_SESSION['flash_message'] = "Invalid reservation ID.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=dashboard");
    exit;
}

try {
    // Check if user has permission to cancel this reservation
    // Only the owner can cancel their reservation
    $stmt = $db->prepare("
        SELECT * FROM reservations 
        WHERE id = ? AND user_id = ? AND status = 'pending'
    ");
    $stmt->execute([$reservationId, $_SESSION['user_id']]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        $_SESSION['flash_message'] = "Reservation not found, already processed, or you don't have permission to cancel it.";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php?page=dashboard");
        exit;
    }
    
    // Begin transaction to cancel reservation
    $db->beginTransaction();
    
    // Update reservation status
    $stmt = $db->prepare("
        UPDATE reservations 
        SET status = 'cancelled' 
        WHERE id = ?
    ");
    $stmt->execute([$reservationId]);
    
    // Validate that the user ID exists in the users table before inserting into history
    $userId = $_SESSION['user_id'];
    $stmt = $db->prepare("SELECT id FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $userExists = $stmt->fetch();
    
    if (!$userExists) {
        // If the user doesn't exist, use the reservation owner as fallback
        $userId = $reservation['user_id'];
    }
    
    // Add status history entry
    $stmt = $db->prepare("
        INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_user_id)
        VALUES (?, 'cancelled', 'Cancelled by user', ?)
    ");
    
    $stmt->execute([$reservationId, $userId]);
    
    // Add notification for admin
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, message, link)
        SELECT id, ?, ? FROM admins
    ");
    $stmt->execute(["Reservation #$reservationId has been cancelled by the user.", "index.php?page=admin&section=view_reservation&id=$reservationId"]);
    
    $db->commit();
    
    $_SESSION['flash_message'] = "Reservation has been successfully cancelled.";
    $_SESSION['flash_type'] = "success";
    
} catch (PDOException $e) {
    $db->rollBack();
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
}

// Redirect to dashboard
header("Location: index.php?page=dashboard");
exit;
?>