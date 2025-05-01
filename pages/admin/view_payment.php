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
    // Show 404 if we have the function, otherwise redirect with error
    if (function_exists('show404')) {
        show404("Invalid reservation ID.");
    } else {
        $_SESSION['flash_message'] = "Invalid reservation ID.";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php?page=admin");
        exit;
    }
}

// Process payment verification actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';
    $notes = isset($_POST['notes']) ? sanitize($_POST['notes']) : '';
    
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get reservation details to notify user
        $stmt = $db->prepare("
            SELECT r.*, u.id as user_id, u.contact_number 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            throw new Exception("Reservation not found.");
        }
        
        if ($reservation['payment_status'] !== 'pending') {
            throw new Exception("This payment has already been processed.");
        }
        
        if ($action === 'approve') {
            // Update payment status to paid
            $stmt = $db->prepare("UPDATE reservations SET payment_status = 'paid' WHERE id = ?");
            $stmt->execute([$reservationId]);
            
            // Add status history entry - Using created_by_admin_id column to match your database structure
            $stmt = $db->prepare("
                INSERT INTO reservation_status_history 
                (reservation_id, status, notes, created_by_admin_id)
                VALUES (?, 'paid', ?, ?)
            ");
            
            // Make sure user_id is valid before using it
            if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                throw new Exception("Admin session is invalid. Please log in again.");
            }
            
            $stmt->execute([$reservationId, $notes ?: 'Payment approved', $_SESSION['user_id']]);
            
            // Add notification for user
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, message, link)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$reservation['user_id'], "Your payment for reservation #$reservationId has been approved.", "index.php?page=view_reservation&id=$reservationId"]);
            
            // Send SMS notification (if configured)
            if (!empty($reservation['contact_number'])) {
                $message = "Your payment for reservation #$reservationId has been approved. You can view details on your dashboard.";
                sendSMS($reservation['contact_number'], $message);
            }
            
            $db->commit();
            $_SESSION['flash_message'] = "Payment has been successfully approved.";
            $_SESSION['flash_type'] = "success";
            
        } elseif ($action === 'reject') {
            // Require rejection reason
            if (empty($notes)) {
                $_SESSION['flash_message'] = "Please provide a reason for rejecting the payment.";
                $_SESSION['flash_type'] = "error";
                header("Location: index.php?page=admin&section=view_payment&id=$reservationId");
                exit;
            }
            
            // Update payment status to rejected
            $stmt = $db->prepare("UPDATE reservations SET payment_status = 'reject', status = 'cancelled' WHERE id = ?");
            $stmt->execute([$reservationId]);
            
            // Add status history entry for payment rejection - Using created_by_admin_id column
            $stmt = $db->prepare("
                INSERT INTO reservation_status_history 
                (reservation_id, status, notes, created_by_admin_id)
                VALUES (?, 'reject', ?, ?)
            ");
            $stmt->execute([$reservationId, 'Payment rejected: ' . $notes, $_SESSION['user_id']]);
            
            // Add status history entry for reservation cancellation - Using created_by_admin_id column
            $stmt = $db->prepare("
                INSERT INTO reservation_status_history 
                (reservation_id, status, notes, created_by_admin_id)
                VALUES (?, 'cancelled', ?, ?)
            ");
            $stmt->execute([$reservationId, 'Reservation cancelled due to payment rejection: ' . $notes, $_SESSION['user_id']]);
            
            // Add notification for user
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, message, link)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$reservation['user_id'], "Your payment for reservation #$reservationId was rejected. Reason: $notes", "index.php?page=view_reservation&id=$reservationId"]);
            
            // Send SMS notification (if configured)
            if (!empty($reservation['contact_number'])) {
                $message = "Your payment for reservation #$reservationId was rejected. Please check your dashboard for details.";
                sendSMS($reservation['contact_number'], $message);
            }
            
            $db->commit();
            $_SESSION['flash_message'] = "Payment has been rejected. The user has been notified.";
            $_SESSION['flash_type'] = "success";
            
        } else {
            throw new Exception("Invalid action.");
        }
        
        // Redirect back to admin dashboard
        header("Location: index.php?page=admin");
        exit;
        
    } catch (Exception $e) {
        $db->rollBack();
        $_SESSION['flash_message'] = "Error: " . $e->getMessage();
        $_SESSION['flash_type'] = "error";
        header("Location: index.php?page=admin");
        exit;
    }
}

// If not POST, load reservation details for verification
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.contact_number, (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.id = ? AND r.payment_status = 'pending' AND r.payment_proof IS NOT NULL
    ");
    $stmt->execute([$reservationId]);
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        // Show 404 if we have the function, otherwise redirect with error
        if (function_exists('show404')) {
            show404("Payment verification request not found or already processed.");
        } else {
            $_SESSION['flash_message'] = "Payment verification request not found or already processed.";
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=admin");
            exit;
        }
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Database error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=admin");
    exit;
}

// Get reservation status history
try {
    $stmt = $db->prepare("
        SELECT rsh.*, 
            COALESCE(u.first_name, a.first_name) as first_name,
            COALESCE(u.last_name, a.last_name) as last_name
        FROM reservation_status_history rsh
        LEFT JOIN users u ON rsh.created_by_user_id = u.id
        LEFT JOIN admins a ON rsh.created_by_admin_id = a.id
        WHERE rsh.reservation_id = ?
        ORDER BY rsh.created_at DESC
    ");
    $stmt->execute([$reservationId]);
    $statusHistory = $stmt->fetchAll();
} catch (PDOException $e) {
    $statusHistory = [];
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Verify Payment</h1>
        <a href="index.php?page=admin" class="text-blue-600 hover:underline">← Back to Dashboard</a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold mb-2">Reservation #<?php echo $reservation['id']; ?></h2>
                <p class="text-gray-600">Created: <?php echo formatDate($reservation['created_at']); ?></p>
            </div>
            <div class="mt-4 md:mt-0">
                <span class="bg-yellow-100 text-yellow-800 px-3 py-1 rounded-full text-sm font-medium">
                    Payment Verification Pending
                </span>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">User Information</h3>
                <p><span class="text-gray-600">Name:</span> <?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></p>
                <p><span class="text-gray-600">Contact:</span> <?php echo $reservation['contact_number']; ?></p>
            </div>
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Reservation Details</h3>
                <p><span class="text-gray-600">Resources:</span> <?php echo $reservation['resources']; ?></p>
                <p><span class="text-gray-600">Date:</span> <?php echo formatDate($reservation['start_datetime']); ?> to <?php echo formatDate($reservation['end_datetime']); ?></p>
            </div>
        </div>
        
        <div class="mb-6">
            <h3 class="font-semibold text-gray-700 mb-4">Payment Proof</h3>
            <div class="border border-gray-200 rounded-lg p-4 flex flex-col items-center">
                <?php if ($reservation['payment_proof']): ?>
                    <img src="uploads/payments/<?php echo $reservation['payment_proof']; ?>" alt="Payment Proof" class="max-w-full h-auto max-h-96 mb-4">
                    <div class="text-center">
                        <a href="uploads/payments/<?php echo $reservation['payment_proof']; ?>" target="_blank" class="text-blue-600 hover:underline">View full size</a>
                    </div>
                <?php else: ?>
                    <p class="text-gray-600">No payment proof image available.</p>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="border-t border-gray-200 pt-6">
            <h3 class="font-semibold text-gray-700 mb-4">Verify Payment</h3>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Approve Payment Form -->
                <form action="index.php?page=admin&section=view_payment&id=<?php echo $reservationId; ?>" method="post" class="border border-green-200 rounded-lg p-4 bg-green-50">
                    <h4 class="font-medium text-green-800 mb-3">Approve Payment</h4>
                    <div class="mb-4">
                        <label for="approve-notes" class="block text-sm text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea id="approve-notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                    </div>
                    <input type="hidden" name="action" value="approve">
                    <button type="submit" class="w-full bg-green-600 text-white py-2 px-4 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        Approve Payment
                    </button>
                </form>
                
                <!-- Reject Payment Form -->
                <form action="index.php?page=admin&section=view_payment&id=<?php echo $reservationId; ?>" method="post" class="border border-red-200 rounded-lg p-4 bg-red-50">
                    <h4 class="font-medium text-red-800 mb-3">Reject Payment</h4>
                    <div class="mb-4">
                        <label for="reject-notes" class="block text-sm text-gray-700 mb-1">Reason for Rejection (Required)</label>
                        <textarea id="reject-notes" name="notes" rows="3" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-red-500 focus:border-red-500" required></textarea>
                    </div>
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="w-full bg-red-600 text-white py-2 px-4 rounded-md hover:bg-red-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500">
                        Reject Payment
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Status History -->
    <?php if (!empty($statusHistory)): ?>
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Status History</h2>
        <div class="space-y-4">
            <?php foreach ($statusHistory as $history): ?>
                <div class="p-3 border-l-4 
                    <?php 
                        if (strpos($history['notes'], 'Payment rejected') !== false) {
                            echo 'border-red-400';
                        } elseif (strpos($history['notes'], 'Payment approved') !== false) {
                            echo 'border-green-400';
                        } else {
                            echo 'border-blue-400';
                        }
                    ?> rounded">
                    <div class="flex justify-between">
                        <div>
                            <p class="font-medium"><?php echo ucfirst($history['status']); ?></p>
                            <?php if (!empty($history['notes'])): ?>
                                <p class="text-gray-600 mt-1"><?php echo $history['notes']; ?></p>
                            <?php endif; ?>
                        </div>
                        <div class="text-right">
                            <p class="text-sm text-gray-500"><?php echo formatDate($history['created_at']); ?></p>
                            <p class="text-xs text-gray-500 mt-1">by <?php echo $history['first_name'] . ' ' . $history['last_name']; ?></p>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>