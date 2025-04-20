<?php
// Check if admin is logged in
if (!isAdmin()) {
    if (function_exists('show404')) {
        show404("You don't have permission to access this page.");
    } else {
        $_SESSION['flash_message'] = "You don't have permission to access this page.";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php");
        exit;
    }
}

// Filter settings
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : '';
$searchTerm = isset($_GET['search']) ? $_GET['search'] : '';

// Build query based on filters
$params = [];
$whereConditions = [];

// Always exclude not_required from payment history
$whereConditions[] = "r.payment_status != 'not_required'";

if ($status !== 'all') {
    $whereConditions[] = "r.payment_status = ?";
    $params[] = $status;
}

if (!empty($startDate)) {
    $whereConditions[] = "r.created_at >= ?";
    $params[] = $startDate . ' 00:00:00';
}

if (!empty($endDate)) {
    $whereConditions[] = "r.created_at <= ?";
    $params[] = $endDate . ' 23:59:59';
}

if (!empty($searchTerm)) {
    $whereConditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.contact_number LIKE ?)";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
    $params[] = "%$searchTerm%";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Get all payments with filters
try {
    $query = "
        SELECT r.*, 
            u.first_name, u.last_name, u.contact_number,
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
        JOIN users u ON r.user_id = u.id
        $whereClause
        ORDER BY 
            CASE 
                WHEN r.payment_status = 'pending' THEN 0
                WHEN r.payment_status = 'paid' THEN 1
                ELSE 2
            END,
            r.created_at DESC
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();
    
    // Get statistics
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE payment_status = 'pending'");
    $stmt->execute();
    $pendingCount = $stmt->fetch()['count'];
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE payment_status = 'paid'");
    $stmt->execute();
    $paidCount = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=admin");
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

<div class="max-w-7xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Payment Management</h1>
        <a href="index.php?page=admin" class="text-blue-600 hover:underline">← Back to Dashboard</a>
    </div>
    
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-md p-5">
            <div class="flex items-center">
                <div class="rounded-full bg-yellow-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Pending Payments</p>
                    <h3 class="text-xl font-bold text-gray-800"><?php echo $pendingCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-5">
            <div class="flex items-center">
                <div class="rounded-full bg-green-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Completed Payments</p>
                    <h3 class="text-xl font-bold text-gray-800"><?php echo $paidCount; ?></h3>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-md p-5">
            <div class="flex items-center">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <p class="text-sm text-gray-500">Total Payment Records</p>
                    <h3 class="text-xl font-bold text-gray-800"><?php echo $pendingCount + $paidCount; ?></h3>
                </div>
            </div>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <!-- Filter form -->
        <form action="index.php" method="get" class="mb-6 grid grid-cols-1 md:grid-cols-5 gap-4 items-end">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="payments">
            
            <div>
                <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Payment Status</label>
                <select id="status" name="status" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
                    <option value="all" <?php echo $status === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="paid" <?php echo $status === 'paid' ? 'selected' : ''; ?>>Paid</option>
                </select>
            </div>
            
            <div>
                <label for="start_date" class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?php echo $startDate; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label for="end_date" class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?php echo $endDate; ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                <input type="text" id="search" name="search" placeholder="Search by name or contact" value="<?php echo htmlspecialchars($searchTerm); ?>" class="w-full border border-gray-300 rounded-md px-3 py-2 focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div>
                <button type="submit" class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">Apply Filters</button>
            </div>
        </form>

        <!-- Results table -->
        <?php if (empty($payments)): ?>
            <div class="py-4 text-center text-gray-600">No payment records found matching your criteria.</div>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Reservation ID</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
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
                                <td class="py-3 px-4">
                                    <div><?php echo $payment['first_name'] . ' ' . $payment['last_name']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $payment['contact_number']; ?></div>
                                </td>
                                <td class="py-3 px-4"><?php echo $payment['resources']; ?></td>
                                <td class="py-3 px-4"><?php echo date('M d, Y', strtotime($payment['start_datetime'])); ?></td>
                                <td class="py-3 px-4">
                                    <?php echo $payment['payment_date'] ? date('M d, Y', strtotime($payment['payment_date'])) : '-'; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <?php echo getPaymentStatusBadge($payment['payment_status']); ?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=admin&section=view_reservation&id=<?php echo $payment['id']; ?>" class="text-blue-600 hover:underline">View Details</a>
                                    
                                    <?php if ($payment['payment_status'] === 'pending' && $payment['payment_proof']): ?>
                                        <a href="index.php?page=admin&section=view_payment&id=<?php echo $payment['id']; ?>" class="ml-3 text-green-600 hover:underline">Verify</a>
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