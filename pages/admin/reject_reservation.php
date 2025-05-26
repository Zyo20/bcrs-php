<?php
// Check if admin is logged in
if (!isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Get reservation ID
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reservationId <= 0) {
    $_SESSION['flash_message'] = "Invalid reservation ID.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=admin");
    exit;
}

// Process form submission with rejection notes
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    if (empty($notes)) {
        $_SESSION['flash_message'] = "Please provide a reason for rejection.";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php?page=admin&section=reject_reservation&id=$reservationId");
        exit;
    }
    
    // Call the rejectReservation function
    list($success, $message) = rejectReservation($db, $reservationId, $_SESSION['user_id'], $notes);
    
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $success ? "success" : "error";
    
    // Redirect to view reservation instead of dashboard
    header("Location: index.php?page=admin&section=view_reservation&id=$reservationId");
    exit;
} else {
    // Display rejection confirmation form
    try {
        // Get reservation details
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            $_SESSION['flash_message'] = "Reservation not found.";
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=admin");
            exit;
        }
        
        if ($reservation['status'] !== 'pending') {
            $_SESSION['flash_message'] = "Only pending reservations can be rejected.";
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=admin&section=view_reservation&id=$reservationId");
            exit;
        }
    } catch (PDOException $e) {
        $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
        header("Location: index.php?page=admin&section=view_reservation&id=$reservationId");
        exit;
    }
}
?>

<div class="max-w-2xl mx-auto mt-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-bold text-red-800 mb-6">Reject Reservation #<?php echo $reservationId; ?></h2>
        
        <div class="mb-6">
            <p class="text-gray-700">You are about to reject the reservation for <strong><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></strong>.</p>
            <p class="text-gray-600 text-sm mt-2">Date: <?php echo formatDate($reservation['start_datetime']); ?> to <?php echo formatDate($reservation['end_datetime']); ?></p>
            <div class="mt-4 p-3 bg-red-50 border border-red-200 rounded text-red-700">
                <div class="flex">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                    </svg>
                    <p>This action cannot be undone. The user will be notified about the rejection.</p>
                </div>
            </div>
        </div>
        
        <form action="index.php?page=admin&section=reject_reservation&id=<?php echo $reservationId; ?>" method="post">
            <div class="mb-6">
                <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Reason for Rejection (Required):</label>
                <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500" required></textarea>
                <p class="text-xs text-gray-500 mt-1">This reason will be shared with the user and visible in the reservation history.</p>
            </div>
            
            <div class="flex justify-end space-x-4">
                <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservationId; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">Cancel</a>
                <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md hover:bg-red-700 transition duration-200">Reject Reservation</button>
            </div>
        </form>
    </div>
</div>