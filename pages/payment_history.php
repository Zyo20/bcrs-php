<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = "Please login to view your payment history.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Get all payments for the current user
try {
    $stmt = $db->prepare("
        SELECT r.*, 
               (SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
                FROM reservation_items ri 
                JOIN resources rs ON ri.resource_id = rs.id 
                WHERE ri.reservation_id = r.id) as resources,
               (SELECT rsh.created_at
                FROM reservation_status_history rsh
                WHERE rsh.reservation_id = r.id AND rsh.notes LIKE '%Payment approved%'
                ORDER BY rsh.created_at DESC
                LIMIT 1) as payment_date
        FROM reservations r
        WHERE r.user_id = ? 
        AND r.payment_status IN ('pending', 'paid')
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$_SESSION['user_id']]);
    $payments = $stmt->fetchAll();
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=dashboard");
    exit;
}

// Format payment status badge
function getPaymentStatusBadge($status) {
    switch ($status) {
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

<div class="max-w-6xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">My Payment History</h1>
        <a href="index.php?page=dashboard" class="text-blue-600 hover:underline">← Back to Dashboard</a>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <?php if (empty($payments)): ?>
            <p class="text-gray-600">You don't have any payment records yet.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation ID</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation Date</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment Date</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($payments as $payment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4">#<?php echo $payment['id']; ?></td>
                                <td class="py-3 px-4"><?php echo $payment['resources']; ?></td>
                                <td class="py-3 px-4"><?php echo date('M d, Y', strtotime($payment['start_datetime'])); ?></td>
                                <td class="py-3 px-4">
                                    <?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-'; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php echo getPaymentStatusBadge($payment['payment_status']); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=view_reservation&id=<?php echo $payment['id']; ?>" class="text-blue-600 hover:underline">View Details</a>
                                    
                                    <?php if ($payment['payment_status'] === 'pending' && $payment['status'] === 'pending'): ?>
                                    <form method="post" action="index.php?page=upload_payment&id=<?php echo $payment['id']; ?>" class="mt-2">
                                        <button type="submit" class="text-green-600 hover:underline">Update Payment Proof</button>
                                    </form>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-6 bg-blue-50 border border-blue-200 rounded p-4">
                <h3 class="font-semibold text-blue-800 mb-2">Payment Information</h3>
                <p class="text-gray-700 mb-2">For gym bookings, please upload your payment proof within 24 hours of making your reservation.</p>
                <p class="text-gray-700">Payment can be made via:</p>
                <ul class="list-disc pl-5 mt-2 text-gray-700">
                    <li>GCash: 09123456789 (Barangay Treasurer)</li>
                    <li>Bank Transfer: BPI Account #1234-5678-90</li>
                    <li>In-person payment at the Barangay Hall</li>
                </ul>
            </div>
        <?php endif; ?>
    </div>
</div>