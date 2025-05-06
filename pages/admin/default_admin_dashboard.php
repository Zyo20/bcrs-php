<?php
// This file contains the default admin dashboard content
// It's included by index.php when no section is specified

// Get pending user registrations
try {
    $stmt = $db->prepare("
        SELECT * FROM users 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pendingUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get pending reservations
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
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $pendingReservations = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get user status counts for the Manage Users widget
try {
    $stmt = $db->prepare("SELECT status, COUNT(*) as count FROM users GROUP BY status");
    $stmt->execute();
    $userStatusCounts = [];
    while ($row = $stmt->fetch()) {
        $userStatusCounts[$row['status']] = $row['count'];
    }
    
    // Set defaults for statuses with no users
    $statuses = ['pending', 'approved', 'blacklisted'];
    foreach ($statuses as $status) {
        if (!isset($userStatusCounts[$status])) {
            $userStatusCounts[$status] = 0;
        }
    }
    
    // Get recent users
    $stmt = $db->prepare("
        SELECT * FROM users 
        ORDER BY created_at DESC 
        LIMIT 5
    ");
    $stmt->execute();
    $recentUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $userStatusCounts = ['pending' => 0, 'approved' => 0, 'blacklisted' => 0];
    $recentUsers = [];
}

// Get system stats
try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['count'];
    
    // Total reservations (Approved or Completed)
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE status IN ('approved', 'completed')");
    $stmt->execute();
    $totalReservations = $stmt->fetch()['count'] ?? 0;
    
    // Today's reservations 
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayReservations = $stmt->fetch()['count'];
    
    // Resources in use
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM resources 
        WHERE availability = 'reserved'
    ");
    $stmt->execute();
    $resourcesInUse = $stmt->fetch()['count'];
    
    // Total resources
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM resources");
    $stmt->execute();
    $totalResources = $stmt->fetch()['count'] ?? 0;
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    // Ensure defaults in case of error
    $totalUsers = 0;
    $totalReservations = 0;
    $todayReservations = 0;
    $resourcesInUse = 0;
    $totalResources = 0;
}
?>

<div class="max-w-7xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Admin Dashboard</h1>
    
    <!-- Stats Overview -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <a href="index.php?page=admin&section=users" class="hover:shadow-lg transition duration-300">
            <div class="bg-white rounded-lg shadow-md p-5">
                <div class="flex items-center">
                    <div class="rounded-full bg-blue-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Users</p>
                        <h3 class="text-xl font-bold text-gray-800"><?php echo $totalUsers; ?></h3>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=reservations" class="hover:shadow-lg transition duration-300">
            <div class="bg-white rounded-lg shadow-md p-5">
                <div class="flex items-center">
                    <div class="rounded-full bg-green-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Reservations</p>
                        <h3 class="text-xl font-bold text-gray-800"><?php echo $totalReservations; ?></h3>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=reservations&status=&filter=today" class="hover:shadow-lg transition duration-300">
            <div class="bg-white rounded-lg shadow-md p-5">
                <div class="flex items-center">
                    <div class="rounded-full bg-yellow-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-yellow-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Today's Reservations</p>
                        <h3 class="text-xl font-bold text-gray-800"><?php echo $todayReservations; ?></h3>
                    </div>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=resources" class="hover:shadow-lg transition duration-300">
            <div class="bg-white rounded-lg shadow-md p-5">
                <div class="flex items-center">
                    <div class="rounded-full bg-purple-100 p-3 mr-4">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                        </svg>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Resources In Use</p>
                        <h3 class="text-xl font-bold text-gray-800"><?php echo $resourcesInUse; ?></h3>
                    </div>
                </div>
            </div>
        </a>
    </div>

    <!-- Quick Access -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <a href="index.php?page=admin&section=resources" class="bg-white rounded-lg shadow-md p-5 hover:shadow-lg transition duration-300">
            <div class="flex items-center">
                <div class="rounded-full bg-blue-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Manage Resources</h3>
                    <p class="text-sm text-gray-500">Add, edit, or remove facilities and equipment</p>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=calendar" class="bg-white rounded-lg shadow-md p-5 hover:shadow-lg transition duration-300">
            <div class="flex items-center">
                <div class="rounded-full bg-amber-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Reservation Calendar</h3>
                    <p class="text-sm text-gray-500">View all reservations in calendar format</p>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=feedback" class="bg-white rounded-lg shadow-md p-5 hover:shadow-lg transition duration-300">
            <div class="flex items-center">
                <div class="rounded-full bg-teal-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-teal-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 10h.01M12 10h.01M16 10h.01M9 16H5a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v8a2 2 0 01-2 2h-5l-5 5v-5z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">User Feedback</h3>
                    <p class="text-sm text-gray-500">View and respond to user feedback</p>
                </div>
            </div>
        </a>
        
        <a href="index.php?page=admin&section=reports" class="bg-white rounded-lg shadow-md p-5 hover:shadow-lg transition duration-300">
            <div class="flex items-center">
                <div class="rounded-full bg-purple-100 p-3 mr-4">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                </div>
                <div>
                    <h3 class="text-lg font-semibold text-gray-800">Generate Reports</h3>
                    <p class="text-sm text-gray-500">Create and export system reports</p>
                </div>
            </div>
        </a>
    </div>
    
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Pending User Approvals -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-blue-700">Pending User Approvals</h2>
                <a href="index.php?page=admin&section=users" class="text-blue-600 hover:underline text-sm">View All</a>
            </div>
            
            <?php if (empty($pendingUsers)): ?>
                <p class="text-gray-600">No pending user registrations.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach (array_slice($pendingUsers, 0, 5) as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-3"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                    <td class="py-2 px-3"><?php echo $user['contact_number']; ?></td>
                                    <td class="py-2 px-3"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="py-2 px-3">
                                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:underline">View</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <?php if (count($pendingUsers) > 5): ?>
                    <div class="mt-3 text-center">
                        <a href="index.php?page=admin&section=users&filter=pending" class="text-sm text-blue-600 hover:underline">View all <?php echo count($pendingUsers); ?> pending users</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Manage Users Widget -->
        <div class="bg-white rounded-lg shadow-md p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-semibold text-blue-700">Manage Users</h2>
                <a href="index.php?page=admin&section=users" class="text-blue-600 hover:underline text-sm">View All</a>
            </div>
            
            <!-- User Status Cards -->
            <div class="grid grid-cols-3 gap-3 mb-4">
                <div class="bg-gray-50 rounded p-3 text-center">
                    <div class="text-lg font-semibold"><?php echo $userStatusCounts['approved']; ?></div>
                    <div class="text-sm text-gray-600">Active</div>
                    <a href="index.php?page=admin&section=users&filter=active" class="text-xs text-blue-600 hover:underline">View</a>
                </div>
                <div class="bg-yellow-50 rounded p-3 text-center">
                    <div class="text-lg font-semibold"><?php echo $userStatusCounts['pending']; ?></div>
                    <div class="text-sm text-gray-600">Pending</div>
                    <a href="index.php?page=admin&section=users&filter=pending" class="text-xs text-blue-600 hover:underline">View</a>
                </div>
                <div class="bg-red-50 rounded p-3 text-center">
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
                    <div class="text-sm text-gray-600">Blacklisted</div>
                    <a href="index.php?page=admin&section=users&filter=blacklisted" class="text-xs text-blue-600 hover:underline">View</a>
                </div>
            </div>
            
            <!-- Recent Users -->
            <h3 class="font-medium text-gray-700 mb-2">Recent Users</h3>
            <?php if (empty($recentUsers)): ?>
                <p class="text-gray-600">No users registered yet.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200">
                            <?php foreach ($recentUsers as $user): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="py-2 px-3"><?php echo $user['first_name'] . ' ' . $user['last_name']; ?></td>
                                    <td class="py-2 px-3"><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                    <td class="py-2 px-3">
                                        <?php if ($user['status'] === 'approved'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">Approved</span>
                                        <?php elseif ($user['status'] === 'pending'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-yellow-100 text-yellow-800">Pending</span>
                                        <?php elseif ($user['status'] === 'blocked'): ?>
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">Blocked</span>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($user['blacklisted']) && $user['blacklisted'] == 1): ?>
                                            <span class="ml-1 px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800">
                                                BLACKLISTED
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="py-2 px-3">
                                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>" class="text-blue-600 hover:underline">Manage</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pending Reservations -->
    <div class="bg-white rounded-lg shadow-md p-6 mb-8">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Pending Reservations</h2>
            <a href="index.php?page=admin&section=reservations&filter=pending" class="text-blue-600 hover:underline text-sm">View All</a>
        </div>
        
        <?php if (empty($pendingReservations)): ?>
            <p class="text-gray-600">No pending reservations.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Payment</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach ($pendingReservations as $reservation): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo $reservation['id']; ?></td>
                                <td class="py-3 px-4">
                                    <div><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $reservation['contact_number']; ?></div>
                                </td>
                                <td class="py-3 px-4"><?php echo $reservation['resources']; ?></td>
                                <td class="py-3 px-4">
                                    <div><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                        <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($reservation['payment_status'] === 'pending'): ?>
                                        <span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Payment Pending</span>
                                    <?php elseif ($reservation['payment_status'] === 'paid'): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Paid</span>
                                    <?php else: ?>
                                        <span class="bg-gray-100 text-gray-600 px-2 py-1 rounded text-xs font-medium">Not Required</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:underline">View</a>
                                    
                                    <a href="index.php?page=admin&section=approve_reservation&id=<?php echo $reservation['id']; ?>" class="text-green-600 hover:underline ml-2" onclick="return confirm('Are you sure you want to approve this reservation?')">Approve</a>
                                             
                                    <a href="index.php?page=admin&section=reject_reservation&id=<?php echo $reservation['id']; ?>" class="text-red-600 hover:underline ml-2" onclick="return confirm('Are you sure you want to reject this reservation?')">Reject</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Active Reservations -->
    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="flex justify-between items-center mb-4">
            <h2 class="text-xl font-semibold text-blue-700">Active Reservations</h2>
            <a href="index.php?page=admin&section=reservations&filter=active" class="text-blue-600 hover:underline text-sm">View All</a>
        </div>
        
        <?php if (empty($activeReservations)): ?>
            <p class="text-gray-600">No active reservations.</p>
        <?php else: ?>
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">User</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-200">
                        <?php foreach (array_slice($activeReservations, 0, 5) as $reservation): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="py-3 px-4"><?php echo $reservation['id']; ?></td>
                                <td class="py-3 px-4">
                                    <div><?php echo $reservation['first_name'] . ' ' . $reservation['last_name']; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo $reservation['contact_number']; ?></div>
                                </td>
                                <td class="py-3 px-4"><?php echo $reservation['resources']; ?></td>
                                <td class="py-3 px-4">
                                    <div><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                    <div class="text-xs text-gray-500">
                                        <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                        <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                    </div>
                                </td>
                                <td class="py-3 px-4">
                                    <?php if ($reservation['status'] === 'approved'): ?>
                                        <span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Approved</span>
                                    <?php elseif ($reservation['status'] === 'for_delivery'): ?>
                                        <span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">For Delivery</span>
                                    <?php elseif ($reservation['status'] === 'for_pickup'): ?>
                                        <span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">For Pickup</span>
                                    <?php endif; ?>
                                </td>
                                <td class="py-3 px-4">
                                    <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:underline">View</a>
                                    
                                    <?php if ($reservation['status'] === 'approved'): ?>
                                        <a href="index.php?page=admin&section=update_status&id=<?php echo $reservation['id']; ?>&status=for_delivery" class="text-blue-600 hover:underline ml-2">Set for Delivery</a>
                                    <?php elseif ($reservation['status'] === 'for_delivery' || $reservation['status'] === 'for_delivery'): ?>
                                        <a href="index?page=admin&section=mark_completed&id=<?php echo $reservation['id']; ?>" class="text-green-600 hover:underline ml-2">For Pickup</a>
                                    <?php elseif ($reservation['status'] === 'for_delivery' || $reservation['status'] === 'for_pickup'): ?>
                                        <a href="index?page=admin&section=mark_completed&id=<?php echo $reservation['id']; ?>" class="text-green-600 hover:underline ml-2">Mark Complete</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if (count($activeReservations) > 5): ?>
                <div class="mt-3 text-center">
                    <a href="index.php?page=admin&section=reservations&filter=active" class="text-sm text-blue-600 hover:underline">View all <?php echo count($activeReservations); ?> active reservations</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <!-- Analytics Overview -->
    <?php
    // Get some analytics data for the dashboard
    try {
        // Current month revenue
        $currentMonth = date('Y-m-01');
        $stmt = $db->prepare("
            SELECT IFNULL(SUM(ri.quantity * res.payment_amount), 0) as monthly_revenue
            FROM reservations r
            JOIN reservation_items ri ON r.id = ri.reservation_id
            JOIN resources res ON ri.resource_id = res.id
            WHERE r.payment_status = 'paid'
            AND res.requires_payment = 1
            AND DATE(r.created_at) >= :current_month
        ");
        $stmt->bindParam(':current_month', $currentMonth);
        $stmt->execute();
        $monthlyRevenue = $stmt->fetch()['monthly_revenue'] ?? 0;

        // Resource utilization rate (percentage of resources in use)
        // Use stats already fetched: $resourcesInUse and $totalResources
        $utilizationRate = ($totalResources > 0) ? round(($resourcesInUse / $totalResources) * 100) : 0;
        
        // Top requested resources this month
        $stmt = $db->prepare("
            SELECT r.name, COUNT(ri.id) as request_count
            FROM reservation_items ri
            JOIN resources r ON ri.resource_id = r.id
            JOIN reservations res ON ri.reservation_id = res.id
            WHERE DATE(res.created_at) >= :current_month
            GROUP BY r.id
            ORDER BY request_count DESC
            LIMIT 5
        ");
        $stmt->bindParam(':current_month', $currentMonth);
        $stmt->execute();
        $topResources = $stmt->fetchAll();

        // User registration growth (comparison to last month)
        // Initialize $userGrowth with default values
        $userGrowth = ['current_month' => 0, 'last_month' => 0];
        $lastMonth = date('Y-m-d', strtotime('-1 month'));
        $stmt = $db->prepare("
            SELECT
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) >= :current_month) as current_month,
                (SELECT COUNT(*) FROM users WHERE DATE(created_at) >= :last_month AND DATE(created_at) < :current_month) as last_month
        ");
        $stmt->bindParam(':current_month', $currentMonth);
        $stmt->bindParam(':last_month', $lastMonth);
        $stmt->execute();
        $userGrowthResult = $stmt->fetch(); // Fetch into a temporary variable
        if ($userGrowthResult) { // Check if fetch was successful
            $userGrowth = $userGrowthResult;
        }
        $growthRate = $userGrowth['last_month'] > 0 ?
            round(($userGrowth['current_month'] - $userGrowth['last_month']) / $userGrowth['last_month'] * 100) :
            ($userGrowth['current_month'] > 0 ? 100 : 0);
        
    } catch (PDOException $e) {
        // Handle error quietly
        $monthlyRevenue = 0;
        $utilizationRate = 0; // Already calculated using safe defaults
        $topResources = [];
        $userGrowth = ['current_month' => 0, 'last_month' => 0]; // Also initialize here for safety
        $growthRate = 0;
    }
    ?>

    <div class="bg-white rounded-lg shadow-md p-6 my-8">
        <div class="flex justify-between items-center mb-6">
            <h2 class="text-xl font-semibold text-blue-700">Analytics Overview</h2>
            <a href="index.php?page=admin&section=reports" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded flex items-center">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-1" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17v-2m3 2v-4m3 4v-6m2 10H7a2 2 0 01-2-2V5a2 2 0 01-2-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                Detailed Reports
            </a>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-6">
            <!-- Monthly Revenue -->
            <div class="bg-gradient-to-br from-blue-50 to-blue-100 rounded-lg p-6 border border-blue-200">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-blue-600 font-medium mb-1">Monthly Revenue</p>
                        <h3 class="text-2xl font-bold text-blue-800">₱
                            <?php echo number_format($monthlyRevenue, 2); ?>
                        </h3>
                    </div>
                    <div class="rounded-full bg-blue-200 p-3 text-blue-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="index.php?page=admin&section=reports&report_type=financial" class="text-xs text-blue-600 hover:underline">View Financial Report →</a>
                </div>
            </div>
            
            <!-- Resource Utilization -->
            <div class="bg-gradient-to-br from-green-50 to-green-100 rounded-lg p-6 border border-green-200">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-green-600 font-medium mb-1">Resource Utilization</p>
                        <h3 class="text-2xl font-bold text-green-800"><?php echo $utilizationRate; ?>%</h3>
                    </div>
                    <div class="rounded-full bg-green-200 p-3 text-green-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="index.php?page=admin&section=reports&report_type=resource" class="text-xs text-green-600 hover:underline">View Resource Report →</a>
                </div>
            </div>
            
            <!-- Reservation Status -->
            <div class="bg-gradient-to-br from-purple-50 to-purple-100 rounded-lg p-6 border border-purple-200">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-purple-600 font-medium mb-1">Total Reservations</p>
                        <h3 class="text-2xl font-bold text-purple-800"><?php echo $totalReservations; ?></h3>
                    </div>
                    <div class="rounded-full bg-purple-200 p-3 text-purple-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="index.php?page=admin&section=reports&report_type=reservation" class="text-xs text-purple-600 hover:underline">View Reservation Report →</a>
                </div>
            </div>
            
            <!-- User Growth -->
            <div class="bg-gradient-to-br from-amber-50 to-amber-100 rounded-lg p-6 border border-amber-200">
                <div class="flex justify-between">
                    <div>
                        <p class="text-sm text-amber-600 font-medium mb-1">User Growth</p>
                        <div class="flex items-center">
                            <h3 class="text-2xl font-bold text-amber-800"><?php echo $userGrowth['current_month']; ?></h3>
                            <span class="ml-2 <?php echo $growthRate >= 0 ? 'text-green-600' : 'text-red-600'; ?> text-sm font-medium">
                                <?php echo $growthRate >= 0 ? '+' : ''; ?><?php echo $growthRate; ?>%
                            </span>
                        </div>
                    </div>
                    <div class="rounded-full bg-amber-200 p-3 text-amber-700">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2">
                    <a href="index.php?page=admin&section=reports&report_type=user" class="text-xs text-amber-600 hover:underline">View User Report →</a>
                </div>
            </div>
        </div>
        
        <!-- Top Resources -->
        <div class="mb-6">
            <h3 class="font-medium text-gray-700 mb-3">Top Requested Resources This Month</h3>
            <?php if (empty($topResources)): ?>
                <p class="text-gray-600">No resource reservations this month.</p>
            <?php else: ?>
                <div class="bg-gray-50 rounded-lg p-4">
                    <div class="space-y-4">
                        <?php foreach($topResources as $index => $resource): ?>
                            <?php 
                                // Calculate percentage for bar width - assuming first item is 100%
                                $maxCount = $topResources[0]['request_count'];
                                $percentage = ($resource['request_count'] / $maxCount) * 100;
                                
                                // Different colors for different ranks
                                $colors = [
                                    'bg-blue-500', 'bg-green-500', 'bg-purple-500', 
                                    'bg-amber-500', 'bg-pink-500'
                                ];
                                $color = $colors[$index % count($colors)];
                            ?>
                            <div>
                                <div class="flex justify-between text-sm mb-1">
                                    <span class="font-medium text-gray-700"><?php echo $resource['name']; ?></span>
                                    <span class="text-gray-500"><?php echo $resource['request_count']; ?> reservations</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2.5">
                                    <div class="<?php echo $color; ?> h-2.5 rounded-full" style="width: <?php echo $percentage; ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-right">
                        <a href="index.php?page=admin&section=reports&report_type=resource" class="text-sm text-blue-600 hover:underline">View All Resources →</a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Quick Links to Reports -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
            <a href="index.php?page=admin&section=reports&report_type=reservation" class="flex flex-col items-center justify-center bg-white border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-sm font-medium">Reservation Report</span>
            </a>
            <a href="index.php?page=admin&section=reports&report_type=resource" class="flex flex-col items-center justify-center bg-white border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4" />
                </svg>
                <span class="text-sm font-medium">Resource Report</span>
            </a>
            <a href="index.php?page=admin&section=reports&report_type=user" class="flex flex-col items-center justify-center bg-white border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 018 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z" />
                </svg>
                <span class="text-sm font-medium">User Report</span>
            </a>
            <a href="index.php?page=admin&section=reports&report_type=financial" class="flex flex-col items-center justify-center bg-white border border-gray-200 rounded-lg p-4 hover:bg-gray-50 transition duration-150">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span class="text-sm font-medium">Financial Report</span>
            </a>
        </div>
    </div>
</div>