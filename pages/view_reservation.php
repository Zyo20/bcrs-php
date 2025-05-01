<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = "Please login to view reservation details.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Get reservation ID
$reservationId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($reservationId <= 0) {
    show404("Invalid reservation ID.");
}

// Load reservation details
try {
    // Check if user has permission to view this reservation
    // Admin can view all reservations, users can only view their own
    if (isAdmin()) {
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name, u.contact_number
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
    } else {
        $stmt = $db->prepare("
            SELECT r.*, u.first_name, u.last_name, u.contact_number
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ? AND r.user_id = ?
        ");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
    }
    
    $reservation = $stmt->fetch();
    
    if (!$reservation) {
        show404("Reservation not found or you don't have permission to view it.");
    }
    
    // Get reservation items
    $stmt = $db->prepare("
        SELECT ri.*, r.name, r.category
        FROM reservation_items ri
        JOIN resources r ON ri.resource_id = r.id
        WHERE ri.reservation_id = ?
    ");
    $stmt->execute([$reservationId]);
    $reservationItems = $stmt->fetchAll();
    
    // Get reservation status history
    $stmt = $db->prepare("
        SELECT 
            rsh.*,
            COALESCE(u.first_name, a.first_name) as first_name,
            COALESCE(u.last_name, a.last_name) as last_name,
            CASE 
                WHEN rsh.created_by_user_id IS NOT NULL THEN 'user'
                WHEN rsh.created_by_admin_id IS NOT NULL THEN 'admin'
                ELSE 'system'
            END as creator_type
        FROM reservation_status_history rsh
        LEFT JOIN users u ON rsh.created_by_user_id = u.id
        LEFT JOIN admins a ON rsh.created_by_admin_id = a.id
        WHERE rsh.reservation_id = ?
        ORDER BY rsh.created_at DESC
    ");
    $stmt->execute([$reservationId]);
    $statusHistory = $stmt->fetchAll();
    
} catch (PDOException $e) {
    show404("Error: " . $e->getMessage());
}

// Format status badges
function getStatusBadge($status) {
    switch ($status) {
        case 'pending':
            return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Pending Approval</span>';
        case 'approved':
            return '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Approved</span>';
        case 'for_delivery':
            return '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">For Delivery</span>';
        case 'for_pickup':
            return '<span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">For Pickup</span>';
        case 'completed':
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">Completed</span>';
        case 'cancelled':
            return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Cancelled</span>';
        default:
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">' . ucfirst($status) . '</span>';
    }
}

function getPaymentStatusBadge($status) {
    switch ($status) {
        case 'not_required':
            return '<span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-medium">Not Required</span>';
        case 'pending':
            return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Payment Pending</span>';
        case 'paid':
            return '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Paid</span>';
        case 'reject':
            return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Payment Rejected</span>';
        default:
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">' . ucfirst($status) . '</span>';
    }
}
?>

<div class="max-w-4xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Reservation Details</h1>
        
        <div>
            <?php if (isAdmin()): ?>
                <a href="index.php?page=admin" class="text-blue-600 hover:underline">← Back to Admin</a>
            <?php else: ?>
                <a href="index.php?page=dashboard" class="text-blue-600 hover:underline">← Back to Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="flex flex-col md:flex-row justify-between mb-6">
            <div>
                <h2 class="text-xl font-semibold mb-2">Reservation #<?php echo $reservation['id']; ?></h2>
                <p class="text-gray-600">Created: <?php echo formatDate($reservation['created_at']); ?></p>
            </div>
            
            <div class="mt-4 md:mt-0 flex flex-col items-start md:items-end">
                <div class="mb-2">Status: <?php echo getStatusBadge($reservation['status']); ?></div>
                <div>Payment: <?php echo getPaymentStatusBadge($reservation['payment_status']); ?></div>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">User Information</h3>
                <p><span class="text-gray-600">Name:</span> <?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></p>
                <p><span class="text-gray-600">Contact:</span> <?php echo $reservation['contact_number']; ?></p>
            </div>
            
            <div>
                <h3 class="font-semibold text-gray-700 mb-2">Location Information</h3>
                <?php if (!empty($reservation['landmark'])): ?>
                    <p><span class="text-gray-600">Landmark:</span> <?php echo $reservation['landmark']; ?></p>
                <?php endif; ?>
                <p><span class="text-gray-600">Address:</span> <?php echo $reservation['address']; ?></p>
                <p><span class="text-gray-600">Purok:</span> <?php echo $reservation['purok']; ?></p>
            </div>
        </div>
        
        <div class="mb-6">
            <h3 class="font-semibold text-gray-700 mb-2">Date and Time</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p><span class="text-gray-600">Start:</span> <?php echo formatDate($reservation['start_datetime']); ?></p>
                </div>
                <div>
                    <p><span class="text-gray-600">End:</span> <?php echo formatDate($reservation['end_datetime']); ?></p>
                </div>
            </div>
        </div>
        
        <div class="mb-6">
            <h3 class="font-semibold text-gray-700 mb-2">Reserved Resources</h3>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource</th>
                            <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                            <th class="py-2 px-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($reservationItems as $item): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3"><?php echo $item['name']; ?></td>
                                <td class="py-2 px-3"><?php echo ucfirst($item['category']); ?></td>
                                <td class="py-2 px-3 text-center"><?php echo $item['quantity']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <?php if ($reservation['payment_status'] === 'pending' || $reservation['payment_status'] === 'paid'): ?>
            <div class="mb-6">
                <h3 class="font-semibold text-gray-700 mb-2">Payment Information</h3>
                <?php if ($reservation['payment_proof']): ?>
                    <div class="mb-2">
                        <p class="text-gray-600 mb-1">Payment Proof:</p>
                        <img src="uploads/payments/<?php echo $reservation['payment_proof']; ?>" alt="Payment Proof" class="max-w-full h-auto max-h-64 border border-gray-300 rounded">
                    </div>
                <?php elseif ($reservation['payment_status'] === 'pending'): ?>
                    <p class="text-gray-600 italic">Payment proof has not been uploaded yet.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($reservation['notes'])): ?>
            <div class="mb-6">
                <h3 class="font-semibold text-gray-700 mb-2">Additional Notes</h3>
                <p class="text-gray-600"><?php echo nl2br($reservation['notes']); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (isAdmin() && $reservation['status'] === 'pending'): ?>
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-200">
                <a href="index.php?page=admin&section=approve_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-300" onclick="return confirm('Are you sure you want to approve this reservation?')">Approve</a>
                
                <a href="index.php?page=admin&section=reject_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-300" onclick="return confirm('Are you sure you want to reject this reservation?')">Reject</a>
            </div>
        <?php elseif (!isAdmin() && $reservation['status'] === 'pending'): ?>
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-200">
                <a href="index.php?page=cancel_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-300" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel Reservation</a>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Status History</h2>
        
        <?php if (empty($statusHistory) && $reservation['payment_status'] !== 'reject'): ?>
            <p class="text-gray-600">No status history available.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php 
                // Check if there's a payment rejection in the reservation but not in history
                $hasRejectInHistory = false;
                foreach ($statusHistory as $history) {
                    if ($history['status'] === 'reject') {
                        $hasRejectInHistory = true;
                        break;
                    }
                }
                
                // If payment is rejected but no reject entry in history, add a virtual entry
                if ($reservation['payment_status'] === 'reject' && !$hasRejectInHistory): 
                ?>
                    <div class="block p-3 border-l-4 border-red-400 rounded">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium">Payment Rejected</p>
                                <p class="text-sm text-gray-700 mt-1 line-clamp-2">Payment was rejected by administrator</p>
                                <p class="text-xs text-gray-500 mt-1">This caused the reservation to be cancelled</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500"><?php echo formatDate($reservation['updated_at']); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <?php foreach ($statusHistory as $history): ?>
                    <a href="index.php?page=view_status_detail&reservation_id=<?php echo $reservationId; ?>&history_id=<?php echo $history['id']; ?>" 
                       class="block p-3 border-l-4 <?php echo ($history['status'] === 'cancelled' || $history['status'] === 'reject') ? 'border-red-400 hover:bg-red-50' : ($history['status'] === 'approved' ? 'border-green-400 hover:bg-green-50' : 'border-blue-400 hover:bg-blue-50'); ?> rounded transition duration-150 ease-in-out">
                        <div class="flex justify-between items-center">
                            <div>
                                <p class="font-medium">
                                    <?php
                                    // Display status with descriptive text
                                    switch($history['status']) {
                                        case 'pending':
                                            echo 'Reservation Submitted';
                                            break;
                                        case 'approved':
                                            echo 'Reservation Approved';
                                            break;
                                        case 'cancelled':
                                            echo 'Reservation Cancelled';
                                            break;
                                        case 'completed':
                                            echo 'Reservation Completed';
                                            break;
                                        case 'for_delivery':
                                            echo 'Items Set for Delivery';
                                            break;
                                        case 'for_pickup':
                                            echo 'Items Ready for Pickup';
                                            break;
                                        case 'reject':
                                            echo 'Payment Rejected';
                                            break;
                                        case 'equipment_update':
                                            echo 'Equipment Quantities Updated';
                                            break;
                                        case 'paid':
                                            echo 'Payment Approved';
                                            break;
                                        default:
                                            echo ucfirst($history['status']);
                                    }
                                    ?>
                                </p>
                                <?php if (!empty($history['notes'])): ?>
                                    <p class="text-sm text-gray-700 mt-1 line-clamp-2"><?php echo htmlspecialchars($history['notes']); ?></p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500 mt-1 italic">
                                        <?php
                                        // Display default notes based on status if none provided
                                        switch($history['status']) {
                                            case 'pending':
                                                echo 'Reservation was submitted';
                                                break;
                                            case 'approved':
                                                echo 'Reservation was approved by administrator';
                                                break;
                                            case 'cancelled':
                                                echo 'Reservation was cancelled';
                                                break;
                                            case 'completed':
                                                echo 'All items have been returned';
                                                break;
                                            case 'for_delivery':
                                                echo 'Items scheduled for delivery';
                                                break;
                                            case 'for_pickup':
                                                echo 'Items ready to be picked up';
                                                break;
                                            case 'reject':
                                                echo 'Payment was rejected by administrator';
                                                break;
                                            case 'equipment_update':
                                                echo 'Resource quantities were updated';
                                                break;
                                            default:
                                                echo 'Status was updated';
                                        }
                                        ?>
                                    </p>
                                <?php endif; ?>
                                <p class="text-xs text-gray-500 mt-1">
                                    <?php if (!empty($history['first_name']) && !empty($history['last_name'])): ?>
                                        Updated by: <?php echo $history['first_name'] . ' ' . $history['last_name']; ?>
                                    <?php elseif ($history['created_by_admin_id']): ?>
                                        Updated by: Admin User
                                    <?php elseif ($history['created_by_user_id']): ?>
                                        Updated by: User
                                    <?php else: ?>
                                        Updated by: System
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500"><?php echo formatDate($history['created_at']); ?></p>
                                <div class="text-xs text-blue-600 mt-2">View Details →</div>
                            </div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>