<?php
// Get user's reservations
try {
    $stmt = $db->prepare("
        SELECT r.*, (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
        FROM reservations r
        WHERE r.user_id = ?
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $reservations = $stmt->fetchAll();
} catch (PDOException $e) {
    // Consider logging the error instead of echoing
    error_log("Error fetching reservations: " . $e->getMessage());
    $reservations = []; // Ensure $reservations is an array
}

// Get upcoming reservations
try {
    $stmt = $db->prepare("
        SELECT r.*, (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
        FROM reservations r
        WHERE r.user_id = ? AND r.status IN ('approved', 'for_delivery', 'for_pickup') AND r.start_datetime > NOW()
        ORDER BY r.start_datetime ASC
        LIMIT 3
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $upcomingReservations = $stmt->fetchAll();
} catch (PDOException $e) {
    // Consider logging the error instead of echoing
    error_log("Error fetching upcoming reservations: " . $e->getMessage());
    $upcomingReservations = []; // Ensure $upcomingReservations is an array
}

// Get status labels and colors (Consider moving these functions to functions.php if not already there)
if (!function_exists('getStatusBadge')) {
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
                return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">' . ucfirst(htmlspecialchars($status)) . '</span>';
        }
    }
}

if (!function_exists('getPaymentStatusBadge')) {
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
                return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">' . ucfirst(htmlspecialchars($status)) . '</span>';
        }
    }
}
?>

<div class="max-w-6xl mx-auto">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">My Dashboard</h1>
        <div>
            <a href="index.php?page=payment_history" class="mt-2 md:mt-0 px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition duration-300 mr-2">Payment History</a>
            <a href="index.php?page=reservation" class="mt-2 md:mt-0 px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-300">New Reservation</a>
        </div>
    </div>
    
    <!-- Quick Stats -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
        <div class="bg-white rounded-lg shadow-md p-5">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Total Reservations</h3>
            <p class="text-3xl font-bold text-blue-600"><?php echo count($reservations); ?></p>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-5">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Pending Approvals</h3>
            <p class="text-3xl font-bold text-yellow-600">
                <?php 
                $pendingCount = 0;
                if (is_array($reservations)) { // Check if $reservations is an array
                    foreach ($reservations as $res) {
                        if ($res['status'] === 'pending') $pendingCount++;
                    }
                }
                echo $pendingCount;
                ?>
            </p>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-5">
            <h3 class="text-lg font-semibold text-gray-700 mb-2">Upcoming Bookings</h3>
            <p class="text-3xl font-bold text-green-600"><?php echo count($upcomingReservations); ?></p>
        </div>
    </div>
    
    <!-- Upcoming Reservations -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">Upcoming Reservations</h2>
        
        <?php if (empty($upcomingReservations)): ?>
            <p class="text-gray-600">You have no upcoming reservations.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($upcomingReservations as $reservation): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4">
                                    <div class="font-medium"><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                    <div class="text-sm text-gray-500">
                                        <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                        <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['resources'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4"><?php echo getStatusBadge($reservation['status']); ?></td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:underline">View Details</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- User Feedback Section -->
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-bold text-gray-800">My Feedback</h2>
            <a href="index?page=feedback" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium py-2 px-4 rounded">
                <i class="fas fa-plus mr-2"></i>New Feedback
            </a>
        </div>

        <?php
        // Get user's feedback
        $stmt = $db->prepare("SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC");
        $stmt->execute([$_SESSION['user_id']]);
        $feedbacks = $stmt->fetchAll();
        ?>

        <?php if (count($feedbacks) > 0): ?>
            <div class="overflow-auto">
                <table class="min-w-full bg-white">
                    <thead class="bg-gray-100">
                        <tr>
                            <th class="py-2 px-4 text-left">Subject</th>
                            <th class="py-2 px-4 text-left">Status</th>
                            <th class="py-2 px-4 text-left">Date</th>
                            <th class="py-2 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feedbacks as $feedback): ?>
                            <tr class="border-b hover:bg-gray-50">
                                <td class="py-2 px-4"><?php echo htmlspecialchars($feedback['subject']); ?></td>
                                <td class="py-2 px-4">
                                    <?php if ($feedback['status'] === 'pending'): ?>
                                        <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>
                                    <?php elseif ($feedback['status'] === 'read'): ?>
                                        <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Read</span>
                                    <?php elseif ($feedback['status'] === 'responded'): ?>
                                        <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Responded</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-2 px-4"><?php echo formatDate($feedback['created_at']); ?></td>
                                <td class="py-2 px-4">
                                    <a href="index?page=view_feedback&id=<?php echo $feedback['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        <i class="fas fa-eye" title="View Details"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php else: ?>
            <div class="bg-gray-100 p-4 rounded-lg text-center">
                <p class="text-gray-600">You haven't submitted any feedback yet.</p>
                <a href="index?page=feedback" class="text-blue-600 hover:underline mt-2 inline-block">
                    <i class="fas fa-comment-dots mr-2"></i>Send your first feedback
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- All Reservations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">All Reservations</h2>
        
        <?php if (empty($reservations)): ?>
            <p class="text-gray-600">You haven't made any reservations yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full bg-white">
                    <thead>
                        <tr class="bg-gray-50 text-gray-600 uppercase text-xs leading-normal">
                            <th class="py-3 px-4 text-left">ID</th>
                            <th class="py-3 px-4 text-left">Date & Time</th>
                            <th class="py-3 px-4 text-left">Resources</th>
                            <th class="py-3 px-4 text-left">Status</th>
                            <th class="py-3 px-4 text-left">Payment</th>
                            <th class="py-3 px-4 text-left">Created</th>
                            <th class="py-3 px-4 text-left">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="text-gray-600 text-sm">
                        <?php foreach ($reservations as $reservation): ?>
                            <tr class="border-b border-gray-200 hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo $reservation['id']; ?></td>
                                <td class="py-3 px-4">
                                    <div><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                        <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4"><?php echo htmlspecialchars($reservation['resources'] ?? 'N/A'); ?></td>
                                <td class="py-3 px-4"><?php echo getStatusBadge($reservation['status']); ?></td>
                                <td class="py-3 px-4"><?php echo getPaymentStatusBadge($reservation['payment_status']); ?></td>
                                <td class="py-3 px-4"><?php echo formatDate($reservation['created_at']); ?></td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:underline">View</a>
                                    
                                    <?php if ($reservation['status'] === 'pending'): ?>
                                        <a href="index.php?page=cancel_reservation&id=<?php echo $reservation['id']; ?>" class="text-red-600 hover:underline ml-2" onclick="return confirm('Are you sure you want to cancel this reservation?')">Cancel</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>