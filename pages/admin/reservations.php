<?php
// Initialize variables
$reservations = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_desc';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : '';
$dateFrom = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$dateTo = isset($_GET['date_to']) ? $_GET['date_to'] : '';

// Build the SQL query based on filters
$query = "SELECT r.*, u.first_name, u.last_name, u.contact_number, 
          (SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
           FROM reservation_items ri 
           JOIN resources rs ON ri.resource_id = rs.id 
           WHERE ri.reservation_id = r.id) as resources
          FROM reservations r
          JOIN users u ON r.user_id = u.id
          WHERE 1=1";
$params = [];

// Apply status filter
if ($status !== '' && in_array($status, ['pending', 'approved', 'rejected', 'completed', 'cancelled'])) {
    $query .= " AND r.status = ?";
    $params[] = $status;
}

// Apply filter
if ($filter !== 'all') {
    switch ($filter) {
        case 'today':
            $query .= " AND DATE(r.created_at) = CURDATE()";
            break;
        case 'this_week':
            $query .= " AND YEARWEEK(r.created_at) = YEARWEEK(CURDATE())";
            break;
        case 'this_month':
            $query .= " AND MONTH(r.created_at) = MONTH(CURDATE()) AND YEAR(r.created_at) = YEAR(CURDATE())";
            break;
        case 'upcoming':
            $query .= " AND r.start_datetime >= NOW() AND r.status = 'approved'";
            break;
        case 'past':
            $query .= " AND r.end_datetime < NOW()";
            break;
    }
}

// Apply date range filter
if (!empty($dateFrom)) {
    $query .= " AND DATE(r.start_datetime) >= ?";
    $params[] = $dateFrom;
}
if (!empty($dateTo)) {
    $query .= " AND DATE(r.end_datetime) <= ?";
    $params[] = $dateTo;
}

// Apply search
if (!empty($search)) {
    $query .= " AND (u.first_name LIKE ? OR u.last_name LIKE ? OR u.contact_number LIKE ? OR r.id LIKE ?)";
    $searchParam = "%{$search}%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

// Apply sorting
switch ($sort) {
    case 'created_asc':
        $query .= " ORDER BY r.created_at ASC";
        break;
    case 'start_asc':
        $query .= " ORDER BY r.start_datetime ASC";
        break;
    case 'start_desc':
        $query .= " ORDER BY r.start_datetime DESC";
        break;
    case 'user_asc':
        $query .= " ORDER BY u.last_name ASC, u.first_name ASC";
        break;
    case 'status_asc':
        $query .= " ORDER BY r.status ASC";
        break;
    default: // created_desc
        $query .= " ORDER BY r.created_at DESC";
        break;
}

// Get reservations from database
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Get statistics
try {
    // Total reservations count
    $totalCount = count($reservations);
    
    // Count by status
    $pendingCount = 0;
    $approvedCount = 0;
    $rejectedCount = 0;
    $completedCount = 0;
    $cancelledCount = 0;
    
    foreach ($reservations as $reservation) {
        switch ($reservation['status']) {
            case 'pending':
                $pendingCount++;
                break;
            case 'approved':
                $approvedCount++;
                break;
            case 'rejected':
                $rejectedCount++;
                break;
            case 'completed':
                $completedCount++;
                break;
            case 'cancelled':
                $cancelledCount++;
                break;
        }
    }
} catch (Exception $e) {
    $errorMessage = "Error calculating statistics: " . $e->getMessage();
}

// Process flash messages
$flashMessage = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flashType = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';

// Clear flash message after retrieving it
if (isset($_SESSION['flash_message'])) {
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-blue-800">Reservation Management</h1>
            <p class="text-gray-600 mt-1">Manage and track all reservations in the system</p>
        </div>
        <div class="mt-4 md:mt-0 flex space-x-3">
            <a href="index.php?page=admin&section=calendar" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-amber-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                Calendar View
            </a>
            <a href="index.php?page=admin&section=resources" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Manage Resources
            </a>
        </div>
    </div>

    <?php if (!empty($flashMessage)): ?>
        <div class="bg-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-50 border-l-4 border-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <?php if ($flashType === 'error'): ?>
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    <?php else: ?>
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-700"><?php echo $flashMessage; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Reservation Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Reservations</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $totalCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Pending</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $pendingCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Approved</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $approvedCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Rejected/Cancelled</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $rejectedCount + $cancelledCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-purple-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Completed</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $completedCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Reservation Filters and Search -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <form action="" method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:flex-wrap sm:items-end sm:gap-4">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="reservations">
                
                <div class="w-full sm:w-[calc(20%-16px)]">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Name, ID, Phone..." class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="w-full sm:w-[calc(20%-16px)]">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select name="status" id="status" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">All Status</option>
                        <option value="pending" <?php echo $status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="approved" <?php echo $status === 'approved' ? 'selected' : ''; ?>>Approved</option>
                        <option value="rejected" <?php echo $status === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                        <option value="completed" <?php echo $status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                        <option value="cancelled" <?php echo $status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    </select>
                </div>

                <div class="w-full sm:w-[calc(20%-16px)]">
                    <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Time Filter</label>
                    <select name="filter" id="filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Time</option>
                        <option value="today" <?php echo $filter === 'today' ? 'selected' : ''; ?>>Today</option>
                        <option value="this_week" <?php echo $filter === 'this_week' ? 'selected' : ''; ?>>This Week</option>
                        <option value="this_month" <?php echo $filter === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                        <option value="upcoming" <?php echo $filter === 'upcoming' ? 'selected' : ''; ?>>Upcoming</option>
                        <option value="past" <?php echo $filter === 'past' ? 'selected' : ''; ?>>Past</option>
                    </select>
                </div>

                <div class="w-full sm:w-[calc(20%-16px)]">
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select name="sort" id="sort" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="start_asc" <?php echo $sort === 'start_asc' ? 'selected' : ''; ?>>Start Date (Asc)</option>
                        <option value="start_desc" <?php echo $sort === 'start_desc' ? 'selected' : ''; ?>>Start Date (Desc)</option>
                        <option value="user_asc" <?php echo $sort === 'user_asc' ? 'selected' : ''; ?>>User Name</option>
                        <option value="status_asc" <?php echo $sort === 'status_asc' ? 'selected' : ''; ?>>Status</option>
                    </select>
                </div>

                <div class="w-full sm:w-[calc(20%-16px)]">
                    <button type="submit" class="w-full inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </button>
                </div>
                
                <div class="w-full sm:w-[calc(33%-16px)]">
                    <label for="date_from" class="block text-sm font-medium text-gray-700 mb-1">From Date</label>
                    <input type="date" name="date_from" id="date_from" value="<?php echo htmlspecialchars($dateFrom); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="w-full sm:w-[calc(33%-16px)]">
                    <label for="date_to" class="block text-sm font-medium text-gray-700 mb-1">To Date</label>
                    <input type="date" name="date_to" id="date_to" value="<?php echo htmlspecialchars($dateTo); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="w-full sm:w-[calc(33%-16px)]">
                    <button type="button" onclick="clearFilters()" class="w-full inline-flex justify-center items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                        </svg>
                        Clear Filters
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reservation List -->
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo $errorMessage; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($reservations)): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No reservations found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php if (!empty($search) || $filter !== 'all' || !empty($status) || !empty($dateFrom) || !empty($dateTo)): ?>
                        No reservations match the current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        There are no reservations in the system yet.
                    <?php endif; ?>
                </p>
            </div>
        <?php else: ?>
            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($reservations as $reservation): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">#<?php echo $reservation['id']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($reservation['first_name'] . ' ' . $reservation['last_name']); ?></div>
                                                        <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['contact_number']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900 max-w-xs truncate"><?php echo htmlspecialchars($reservation['resources']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                                    <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $statusColor = '';
                                                switch ($reservation['status']) {
                                                    case 'pending':
                                                        $statusColor = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'approved':
                                                        $statusColor = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'rejected':
                                                        $statusColor = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'cancelled':
                                                        $statusColor = 'bg-red-100 text-red-800';
                                                        break;
                                                    case 'completed':
                                                        $statusColor = 'bg-blue-100 text-blue-800';
                                                        break;
                                                    case 'for_delivery':
                                                        $statusColor = 'bg-indigo-100 text-indigo-800';
                                                        break;
                                                    case 'for_pickup':
                                                        $statusColor = 'bg-purple-100 text-purple-800';
                                                        break;
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColor; ?>">
                                                    <?php echo ucfirst(str_replace('_', ' ', $reservation['status'])); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if ($reservation['payment_status'] === 'pending'): ?>
                                                    <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Pending</span>
                                                <?php elseif ($reservation['payment_status'] === 'paid'): ?>
                                                    <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Paid</span>
                                                <?php else: ?>
                                                    <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-medium">Not Required</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($reservation['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                                                
                                                <?php if ($reservation['status'] === 'pending'): ?>
                                                    <a href="index.php?page=admin&section=approve_reservation&id=<?php echo $reservation['id']; ?>" class="text-green-600 hover:text-green-900 ml-2" onclick="return confirm('Are you sure you want to approve this reservation?')">Approve</a>
                                                    <a href="index.php?page=admin&section=reject_reservation&id=<?php echo $reservation['id']; ?>" class="text-red-600 hover:text-red-900 ml-2" onclick="return confirm('Are you sure you want to reject this reservation?')">Reject</a>
                                                <?php elseif ($reservation['status'] === 'approved'): ?>
                                                    <a href="index.php?page=admin&section=mark_returned&id=<?php echo $reservation['id']; ?>" class="text-orange-600 hover:text-orange-700 ml-2" onclick="return confirm('Are you sure these items have been returned?')">Mark as Returned</a>
                                                <?php elseif ($reservation['status'] === 'for_delivery' || $reservation['status'] === 'for_pickup'): ?>
                                                    <a href="index.php?page=admin&section=update_status&id=<?php echo $reservation['id']; ?>&status=completed" class="text-green-600 hover:text-green-900 ml-2">Complete</a>
                                                    <a href="index.php?page=admin&section=mark_returned&id=<?php echo $reservation['id']; ?>" class="text-orange-600 hover:text-orange-700 ml-2" onclick="return confirm('Are you sure these items have been returned?')">Mark as Returned</a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function clearFilters() {
    window.location = 'index.php?page=admin&section=reservations';
}
</script>