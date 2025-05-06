<?php

// Handle user status changes if requested
if (isset($_GET['action']) && isset($_GET['user_id'])) {
    $action = $_GET['action'];
    $userId = (int)$_GET['user_id'];
    
    switch ($action) {
        case 'approve':
            try {
                $stmt = $db->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$userId]);
                
                // Send approval notification to user (in a real system)
                // sendApprovalNotification($userId);
                
                $_SESSION['success_message'] = "User has been approved.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error approving user: " . $e->getMessage();
            }
            break;
            
        case 'block':
            try {
                // Check for active reservations (pending or approved)
                $stmtCheck = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ? AND status IN ('pending', 'approved')");
                $stmtCheck->execute([$userId]);
                $activeReservations = $stmtCheck->fetch()['count'];

                if ($activeReservations > 0) {
                    $_SESSION['error_message'] = "Cannot block user with active reservations (pending or approved).";
                } else {
                    $stmt = $db->prepare("UPDATE users SET status = 'rejected', blacklisted = 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                    $_SESSION['success_message'] = "User has been blocked.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error blocking user: " . $e->getMessage();
            }
            break;
            
        case 'activate':
            try {
                $stmt = $db->prepare("UPDATE users SET status = 'approved', blacklisted = 0 WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success_message'] = "User has been activated.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error activating user: " . $e->getMessage();
            }
            break;
            
        case 'delete':
            try {
                // First check if user has any reservations
                $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ?");
                $stmt->execute([$userId]);
                $result = $stmt->fetch();
                
                if ($result['count'] > 0) {
                    $_SESSION['error_message'] = "Cannot delete user with existing reservations. Block the user instead.";
                } else {
                    $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
                    $stmt->execute([$userId]);
                    $_SESSION['success_message'] = "User has been deleted.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
            }
            break;
    }
    
    // Redirect to remove action from URL
    header('Location: index.php?page=admin&section=users' . (isset($_GET['filter']) ? '&filter=' . $_GET['filter'] : ''));
    exit;
}

// Get filter, sort and search parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'id';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchField = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';

// Validate sort parameters to prevent SQL injection
$allowedSortFields = ['id', 'first_name', 'email', 'contact_number', 'created_at'];
$allowedSortOrders = ['asc', 'desc'];
$sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id';
$sortOrder = in_array($sortOrder, $allowedSortOrders) ? $sortOrder : 'desc';

// Helper function to generate sort URL
function getSortUrl($field, $currentSortBy, $currentSortOrder, $filter, $search, $searchField) {
    $newOrder = ($field === $currentSortBy && $currentSortOrder === 'asc') ? 'desc' : 'asc';
    $url = "index.php?page=admin&section=users&sort={$field}&order={$newOrder}";
    if ($filter !== 'all') {
        $url .= "&filter={$filter}";
    }
    if (!empty($search)) {
        $url .= "&search=" . urlencode($search) . "&search_field=" . urlencode($searchField);
    }
    return $url;
}

// Helper function to get sort icon
function getSortIcon($field, $currentSortBy, $currentSortOrder) {
    if ($field !== $currentSortBy) {
        return '<svg class="w-3 h-3 ml-1 opacity-30" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M12 20l-8-8h16l-8 8zm0-16l8 8H4l8-8z"/></svg>';
    } else {
        return ($currentSortOrder === 'asc') 
            ? '<svg class="w-3 h-3 ml-1" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M7 14l5-5 5 5H7z"/></svg>' 
            : '<svg class="w-3 h-3 ml-1" aria-hidden="true" fill="currentColor" viewBox="0 0 24 24"><path d="M7 10l5 5 5-5H7z"/></svg>';
    }
}

// Function to check if user is in masterlist
function isInMasterlist($db, $firstName, $lastName) {
    try {
        $stmt = $db->prepare("SELECT COUNT(*) as count FROM masterlist WHERE first_name = ? AND last_name = ?");
        $stmt->execute([$firstName, $lastName]);
        $result = $stmt->fetch();
        return $result['count'] > 0;
    } catch (PDOException $e) {
        return false;
    }
}

// Prepare the query based on filter, search and sort
$query = "SELECT * FROM users WHERE 1=1";
$params = [];

// Apply status filter
switch ($filter) {
    case 'pending':
        $query .= " AND status = 'pending'";
        break;
    case 'active':
        $query .= " AND status = 'approved'";
        break;
    case 'blocked':
        $query .= " AND status = 'rejected'";
        break;
    case 'blacklisted':
        $query .= " AND blacklisted = 1";
        break;
    // 'all' doesn't need additional conditions
}

// Apply search if provided
if (!empty($search)) {
    switch ($searchField) {
        case 'name':
            $query .= " AND (first_name LIKE ? OR last_name LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            break;
        case 'email':
            $query .= " AND email LIKE ?";
            $params[] = "%{$search}%";
            break;
        case 'contact':
            $query .= " AND contact_number LIKE ?";
            $params[] = "%{$search}%";
            break;
        default: // 'all' - search all fields
            $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
            $params[] = "%{$search}%";
    }
}

// Add sorting
$query .= " ORDER BY {$sortBy} {$sortOrder}";

// Get users list
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $users = [];
}

// Count users by status
try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM users GROUP BY status");
    $stmt->execute();
    $statusCounts = [];
    while ($row = $stmt->fetch()) {
        $statusCounts[$row['status']] = $row['count'];
    }
    
    // Set defaults for statuses with no users
    $statuses = ['pending', 'approved', 'rejected'];
    foreach ($statuses as $status) {
        if (!isset($statusCounts[$status])) {
            $statusCounts[$status] = 0;
        }
    }
    
    // Total count
    $totalUsers = array_sum($statusCounts);
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $statusCounts = ['pending' => 0, 'approved' => 0, 'rejected' => 0];
    $totalUsers = 0;
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Manage Users</h1>
        <div class="flex items-center space-x-2">
            <a href="index.php?page=admin" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                ← Back to Dashboard
            </a>
            <!-- Masterlist Button -->
            <a href="index.php?page=admin&section=masterlist" 
               class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                <span class="flex items-center">
                    <i class="fas fa-list-check mr-2"></i>
                    Masterlist
                </span>
            </a>
            <a href="index.php?page=admin&section=export_csv&report_type=users_list&filter=<?php echo $filter; ?><?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_field=' . urlencode($searchField) : ''; ?><?php echo $sortBy !== 'id' || $sortOrder !== 'desc' ? '&sort=' . $sortBy . '&order=' . $sortOrder : ''; ?>" 
               class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                <span class="flex items-center">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                    </svg>
                    Export to CSV
                </span>
            </a>
        </div>
    </div>
    
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['success_message']; ?></p>
            <?php unset($_SESSION['success_message']); ?>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
            <p><?php echo $_SESSION['error_message']; ?></p>
            <?php unset($_SESSION['error_message']); ?>
        </div>
    <?php endif; ?>
    
    <!-- Status filters -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-6">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <a href="index.php?page=admin&section=users<?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_field=' . urlencode($searchField) : ''; ?><?php echo $sortBy !== 'id' || $sortOrder !== 'desc' ? '&sort=' . $sortBy . '&order=' . $sortOrder : ''; ?>" 
               class="<?php echo $filter === 'all' ? 'bg-blue-100 border-blue-500' : 'bg-gray-50 hover:bg-gray-100'; ?> border-l-4 p-4 rounded transition-colors duration-150">
                <div class="text-lg font-semibold"><?php echo $totalUsers; ?></div>
                <div class="text-gray-600">All Users</div>
            </a>
            
            <a href="index.php?page=admin&section=users&filter=pending<?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_field=' . urlencode($searchField) : ''; ?><?php echo $sortBy !== 'id' || $sortOrder !== 'desc' ? '&sort=' . $sortBy . '&order=' . $sortOrder : ''; ?>" 
               class="<?php echo $filter === 'pending' ? 'bg-yellow-100 border-yellow-500' : 'bg-gray-50 hover:bg-gray-100'; ?> border-l-4 p-4 rounded transition-colors duration-150">
                <div class="text-lg font-semibold"><?php echo $statusCounts['pending']; ?></div>
                <div class="text-gray-600">Pending</div>
            </a>
            
            <a href="index.php?page=admin&section=users&filter=active<?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_field=' . urlencode($searchField) : ''; ?><?php echo $sortBy !== 'id' || $sortOrder !== 'desc' ? '&sort=' . $sortBy . '&order=' . $sortOrder : ''; ?>" 
               class="<?php echo $filter === 'active' ? 'bg-green-100 border-green-500' : 'bg-gray-50 hover:bg-gray-100'; ?> border-l-4 p-4 rounded transition-colors duration-150">
                <div class="text-lg font-semibold"><?php echo $statusCounts['approved']; ?></div>
                <div class="text-gray-600">Active</div>
            </a>
            
            <a href="index.php?page=admin&section=users&filter=blacklisted<?php echo !empty($search) ? '&search=' . urlencode($search) . '&search_field=' . urlencode($searchField) : ''; ?><?php echo $sortBy !== 'id' || $sortOrder !== 'desc' ? '&sort=' . $sortBy . '&order=' . $sortOrder : ''; ?>" 
               class="<?php echo $filter === 'blacklisted' ? 'bg-red-100 border-red-500' : 'bg-gray-50 hover:bg-gray-100'; ?> border-l-4 p-4 rounded transition-colors duration-150">
                <div class="text-lg font-semibold"><?php 
                    // Count blacklisted users
                    try {
                        $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE blacklisted = 1");
                        $stmt->execute();
                        echo $stmt->fetch()['count'];
                    } catch (PDOException $e) {
                        echo "0";
                    }
                ?></div>
                <div class="text-gray-600">Blacklisted</div>
            </a>
        </div>
    </div>
    
    <!-- Users Table -->
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 md:mb-0">
                    <?php 
                    switch ($filter) {
                        case 'pending': echo 'Pending Users'; break;
                        case 'active': echo 'Active Users'; break;
                        case 'blocked': echo 'Blocked Users'; break;
                        case 'blacklisted': echo 'Blacklisted Users'; break;
                        default: echo 'All Users'; break;
                    }
                    ?>
                    <span class="text-gray-500 text-sm ml-2">(<?php echo count($users); ?>)</span>
                </h2>
                
                <!-- Search Form -->
                <form method="GET" action="index.php" class="flex flex-wrap md:flex-nowrap">
                    <input type="hidden" name="page" value="admin">
                    <input type="hidden" name="section" value="users">
                    <?php if ($filter !== 'all'): ?>
                        <input type="hidden" name="filter" value="<?php echo htmlspecialchars($filter); ?>">
                    <?php endif; ?>
                    <?php if ($sortBy !== 'id' || $sortOrder !== 'desc'): ?>
                        <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortBy); ?>">
                        <input type="hidden" name="order" value="<?php echo htmlspecialchars($sortOrder); ?>">
                    <?php endif; ?>
                    
                    <div class="flex mr-2 md:w-64 w-full mb-2 md:mb-0">
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" 
                               class="border-gray-300 rounded-l focus:ring-blue-500 focus:border-blue-500 block w-full">
                        <select name="search_field" class="border-l-0 border-gray-300 rounded-r focus:ring-blue-500 focus:border-blue-500">
                            <option value="all" <?php echo $searchField === 'all' ? 'selected' : ''; ?>>All Fields</option>
                            <option value="name" <?php echo $searchField === 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="email" <?php echo $searchField === 'email' ? 'selected' : ''; ?>>Email</option>
                            <option value="contact" <?php echo $searchField === 'contact' ? 'selected' : ''; ?>>Contact</option>
                        </select>
                    </div>
                    
                    <div class="flex">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md mr-2">Search</button>
                        <a href="index.php?page=admin&section=users<?php echo $filter !== 'all' ? '&filter=' . $filter : ''; ?>" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Clear</a>
                    </div>
                </form>
            </div>
            
            <?php if (empty($users)): ?>
                <p class="text-gray-600">No users found matching your criteria.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left">
                                    <a href="<?php echo getSortUrl('id', $sortBy, $sortOrder, $filter, $search, $searchField); ?>" class="group flex items-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        ID <?php echo getSortIcon('id', $sortBy, $sortOrder); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="<?php echo getSortUrl('first_name', $sortBy, $sortOrder, $filter, $search, $searchField); ?>" class="group flex items-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Name <?php echo getSortIcon('first_name', $sortBy, $sortOrder); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="<?php echo getSortUrl('email', $sortBy, $sortOrder, $filter, $search, $searchField); ?>" class="group flex items-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Email <?php echo getSortIcon('email', $sortBy, $sortOrder); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="<?php echo getSortUrl('contact_number', $sortBy, $sortOrder, $filter, $search, $searchField); ?>" class="group flex items-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Contact <?php echo getSortIcon('contact_number', $sortBy, $sortOrder); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left">
                                    <a href="<?php echo getSortUrl('created_at', $sortBy, $sortOrder, $filter, $search, $searchField); ?>" class="group flex items-center text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        Registered <?php echo getSortIcon('created_at', $sortBy, $sortOrder); ?>
                                    </a>
                                </th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($users as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $user['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="flex items-center">
                                            <?php if (isInMasterlist($db, $user['first_name'], $user['last_name'])): ?>
                                                <span class="inline-block w-3 h-3 bg-green-500 rounded-full mr-2" title="Found in Masterlist"></span>
                                            <?php endif; ?>
                                            <?php echo $user['first_name'] . ' ' . $user['last_name']; ?>
                                        </div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $user['email']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo $user['contact_number']; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <?php if ($user['status'] === 'approved'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                                Active
                                            </span>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">
                                                Pending
                                            </span>
                                        <?php elseif ($user['status'] === 'rejected'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                Blocked
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($user['blacklisted']) && $user['blacklisted'] == 1): ?>
                                            <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                BLACKLISTED
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <?php if ($user['status'] === 'pending'): ?>
                                            <a href="index.php?page=admin&section=users&action=approve&user_id=<?php echo $user['id']; ?>&filter=<?php echo $filter; ?>" class="text-blue-600 hover:text-blue-900 mr-3" onclick="return confirm('Are you sure you want to approve this user?')">
                                                Approve
                                            </a>
                                        <?php elseif ($user['status'] === 'approved'): ?>
                                            <a href="index.php?page=admin&section=users&action=block&user_id=<?php echo $user['id']; ?>&filter=<?php echo $filter; ?>" class="text-red-600 hover:text-red-900 mr-3" onclick="return confirm('Are you sure you want to block this user?')">
                                                Reject
                                            </a>
                                        <?php elseif ($user['status'] === 'rejected'): ?>
                                            <a href="index.php?page=admin&section=users&action=activate&user_id=<?php echo $user['id']; ?>&filter=<?php echo $filter; ?>" class="text-green-600 hover:text-green-900 mr-3" onclick="return confirm('Are you sure you want to activate this user?')">
                                                Activate
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            View
                                        </a>
                                        
                                        <a href="index.php?page=admin&section=users&action=delete&user_id=<?php echo $user['id']; ?>&filter=<?php echo $filter; ?>" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                                            Delete
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>