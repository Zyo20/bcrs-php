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
        case 'delivered':
            return '<span class="bg-indigo-100 text-indigo-800 px-2 py-1 rounded text-xs font-medium">Delivered</span>';
        case 'completed':
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">Completed</span>';
        case 'cancelled':
            return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Cancelled</span>';
        case 'returned':
            return '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Returned</span>';
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
        <a href="index.php?page=admin&section=reservations" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
            Back to Reservations
        </a>
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
            <?php 
            // Calculate days difference
            $startDate = new DateTime($reservation['start_datetime']);
            $endDate = new DateTime($reservation['end_datetime']);
            $interval = $startDate->diff($endDate);
            $totalHours = ($interval->days * 24) + $interval->h;
            $displayPeriod = $interval->days > 0 ? "{$interval->days} day" . ($interval->days > 1 ? "s" : "") : "";
            if ($interval->h > 0) {
                $displayPeriod .= $interval->days > 0 ? ", " : "";
                $displayPeriod .= "{$interval->h} hour" . ($interval->h > 1 ? "s" : "");
            }
            if ($interval->i > 0 && $totalHours < 10) { // Only show minutes if less than 10 hours total
                $displayPeriod .= !empty($displayPeriod) ? ", " : "";
                $displayPeriod .= "{$interval->i} minute" . ($interval->i > 1 ? "s" : "");
            }
            ?>
            <?php if (!empty($displayPeriod)): ?>
                <div class="mt-2">
                    <p><span class="text-gray-600">Duration:</span> <?php echo $displayPeriod; ?></p>
                    <?php if (!empty($reservation['is_multi_day']) && $reservation['is_multi_day'] == 1): ?>
                        <p class="mt-2">
                            <span class="bg-purple-100 text-purple-800 text-xs font-semibold px-2.5 py-0.5 rounded">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 inline mr-1" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                </svg>
                                Multi-Day Booking
                            </span>
                        </p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
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
                            <th class="py-2 px-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Available</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php 
                        $hasFacilitiesOnly = true;
                        foreach ($reservationItems as $item): 
                            // Check if this is not a facility-only reservation
                            if ($item['category'] !== 'facility') {
                                $hasFacilitiesOnly = false;
                            }
                            
                            // Get the total available quantity of this resource
                            $resourceStmt = $db->prepare("SELECT quantity FROM resources WHERE id = ?");
                            $resourceStmt->execute([$item['resource_id']]);
                            $resourceInfo = $resourceStmt->fetch();
                            $availableQuantity = isset($resourceInfo['quantity']) ? $resourceInfo['quantity'] : 'N/A';
                        ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-2 px-3"><?php echo $item['name']; ?></td>
                                <td class="py-2 px-3"><?php echo ucfirst($item['category']); ?></td>
                                <td class="py-2 px-3 text-center font-medium"><?php echo $item['quantity']; ?></td>
                                <td class="py-2 px-3 text-center text-gray-600"><?php echo $availableQuantity; ?></td>
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
            <div class="flex flex-col gap-3 mt-6 pt-4 border-t border-gray-200">
                <?php if ($reservation['payment_status'] === 'pending'): ?>
                    <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-3">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-sm text-yellow-700">
                                    <strong>Payment confirmation required.</strong> This reservation cannot be approved until payment is confirmed.
                                </p>
                                <?php if ($reservation['payment_proof']): ?>
                                <p class="mt-1 text-sm text-yellow-700">
                                    <a href="index.php?page=admin&section=view_payment&id=<?php echo $reservation['id']; ?>" class="font-medium underline text-yellow-700 hover:text-yellow-600">Verify payment proof</a> before approving this reservation.
                                </p>
                                <?php else: ?>
                                <p class="mt-1 text-sm text-yellow-700">
                                    The user has not uploaded payment proof yet.
                                </p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($reservation['payment_proof']): ?>
                    <a href="index.php?page=admin&section=view_payment&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-yellow-600 text-white rounded hover:bg-yellow-700 transition duration-300 text-center">
                        Verify Payment
                    </a>
                    <?php endif; ?>
                    
                    <button disabled class="px-4 py-2 bg-gray-400 text-white rounded cursor-not-allowed">
                        Approve (Payment Pending)
                    </button>
                <?php else: ?>
                    <a href="index.php?page=admin&section=approve_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-300 text-center" onclick="return confirm('Are you sure you want to approve this reservation?')">
                        Approve
                    </a>
                <?php endif; ?>
                
                <a href="index.php?page=admin&section=reject_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-300 text-center" onclick="return confirm('Are you sure you want to reject this reservation?')">
                    Reject
                </a>
            </div>
        <?php elseif (!isAdmin() && $reservation['status'] === 'pending'): ?>
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-200">
                <a href="index.php?page=cancel_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-300" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel Reservation</a>
            </div>
        <?php elseif (!isAdmin() && $reservation['status'] === 'pending'): ?>
            <div class="flex flex-wrap gap-3 mt-6 pt-4 border-t border-gray-200">
                <a href="index.php?page=cancel_reservation&id=<?php echo $reservation['id']; ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition duration-300" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel Reservation</a>
            </div>
        <?php elseif ($reservation['status'] === 'approved'): ?>
            <div class="flex flex-wrap gap-2 mt-6">
                <?php
                // Check if there are any equipment items in this reservation
                $hasEquipment = false;
                foreach ($reservationItems as $item) {
                    if ($item['category'] === 'equipment') {
                        $hasEquipment = true;
                        break;
                    }
                }
                ?>
                <?php if ($hasEquipment): ?>
                <a href="index.php?page=admin&section=update_status&id=<?php echo $reservation['id']; ?>&status=for_delivery" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V9a2 2 0 00-2-2h-3m-1 4l-3 3m0 0l-3-3m3 3V4" />
                    </svg>
                    Mark For Delivery
                </a>
                <?php else: ?>
                <!-- If reservation contains only facilities, show Mark as Completed button -->
                <a href="index.php?page=admin&section=mark_completed&id=<?php echo $reservation['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500" onclick="return confirm('Are you sure you want to mark this reservation as completed?')">
                    <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Mark as Completed
                </a>
                <?php endif; ?>
            </div>
        <?php elseif ($reservation['status'] === 'for_delivery'): ?>
            <div class="flex flex-wrap gap-2 mt-6">
                <a href="index.php?page=admin&section=update_status&id=<?php echo $reservation['id']; ?>&status=delivered" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                    <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                    Mark as Delivered
                </a>
            </div>
        <?php elseif ($reservation['status'] === 'delivered'): ?>
            <div class="flex flex-wrap gap-2 mt-6">
                <a href="index.php?page=admin&section=mark_returned&id=<?php echo $reservation['id']; ?>" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-orange-600 hover:bg-orange-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-orange-500" onclick="return confirm('Are you sure these items have been returned?')">
                    <svg class="h-4 w-4 mr-1.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    Mark as Returned and Completed
                </a>
            </div>
        <?php endif; ?>
        </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold mb-4">Status History</h2>
        
        <?php if (empty($statusHistory)): ?>
            <p class="text-gray-600">No status history available.</p>
        <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($statusHistory as $history): ?>
                    <div class="p-3 border-l-4 <?php echo $history['status'] === 'cancelled' ? 'border-red-400' : ($history['status'] === 'approved' ? 'border-green-400' : 'border-blue-400'); ?> rounded">
                        <div class="flex justify-between">
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
                                        case 'delivered':
                                            echo 'Items Delivered';
                                            break;
                                        case 'reject':
                                            echo 'Payment Rejected';
                                            break;
                                        case 'equipment_update':
                                            echo 'Equipment Quantities Updated';
                                            break;
                                        case 'returned':
                                            echo 'Items Returned and Completed';
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
                                    <p class="text-gray-600 mt-1"><?php echo $history['notes']; ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="text-right">
                                <p class="text-sm text-gray-500"><?php echo formatDate($history['created_at']); ?></p>
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
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>