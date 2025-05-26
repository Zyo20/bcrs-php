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

// Initialize reservation variable
$reservation = null;

// Process form submission with approval notes OR fetch reservation details for display
try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
        
        // Call the approveReservation function
        list($success, $message) = approveReservation($db, $reservationId, $_SESSION['user_id'], $notes);
        
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = $success ? "success" : "error";
        
        // Redirect to view reservation instead of dashboard
        header("Location: index.php?page=admin&section=view_reservation&id=$reservationId");
        exit;
    } else {
        // Fetch reservation details for display
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
            $_SESSION['flash_message'] = "Only pending reservations can be approved.";
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=admin");
            exit;
        }
        
        // Check if payment is required but not yet confirmed
        if ($reservation['payment_status'] === 'pending') {
            $_SESSION['flash_message'] = "Payment must be confirmed before approving this reservation.";
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=admin&section=view_reservation&id=" . $reservationId);
            exit;
        }
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=admin&section=view_reservation&id=$reservationId");
    exit;
}

// If we reach here, it means it's a GET request and the reservation is valid and pending
// The HTML form will be displayed below.
?>

<div class="max-w-2xl mx-auto mt-8">
    <div class="bg-white shadow-md rounded-lg p-6">
        <h2 class="text-xl font-bold text-blue-800 mb-6">Approve Reservation #<?php echo $reservationId; ?></h2>
        
        <?php if ($reservation): // Ensure reservation data is available before trying to display it ?>
            <div class="mb-6">
                <p class="text-gray-700">You are about to approve the reservation for <strong><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></strong>.</p>
                <p class="text-gray-600 text-sm mt-2">Date: <?php echo formatDate($reservation['start_datetime']); ?> to <?php echo formatDate($reservation['end_datetime']); ?></p>
            </div>
            
            <form action="index.php?page=admin&section=approve_reservation&id=<?php echo $reservationId; ?>" method="post">
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Approval Notes (Optional):</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500"></textarea>
                    <p class="text-xs text-gray-500 mt-1">These notes will be visible in the reservation history.</p>
                </div>
                
                <div class="flex justify-end space-x-4">
                    <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservationId; ?>" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-md hover:bg-gray-300 transition duration-200">Cancel</a>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition duration-200">Approve Reservation</button>
                </div>
            </form>
        <?php else: ?>
            <p class="text-red-500">Could not load reservation details.</p> 
        <?php endif; ?>
    </div>
</div>