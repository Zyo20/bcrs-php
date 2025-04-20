<?php

// Check if user ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error_message'] = "Invalid user ID.";
    header('Location: index.php?page=admin&section=users');
    exit;
}

$userId = (int)$_GET['id'];

// Process actions if submitted
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    switch ($action) {
        case 'approve':
            try {
                $stmt = $db->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success_message'] = "User has been approved successfully.";
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
                    $stmt = $db->prepare("UPDATE users SET status = 'rejected' WHERE id = ?");
                    $stmt->execute([$userId]);
                    $_SESSION['success_message'] = "User has been blocked successfully.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error blocking user: " . $e->getMessage();
            }
            break;
            
        case 'activate':
            try {
                $stmt = $db->prepare("UPDATE users SET status = 'approved' WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success_message'] = "User has been activated successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error activating user: " . $e->getMessage();
            }
            break;
            
        case 'blacklist':
            try {
                $stmt = $db->prepare("UPDATE users SET blacklisted = 1 WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success_message'] = "User has been blacklisted successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error blacklisting user: " . $e->getMessage();
            }
            break;
            
        case 'unblacklist':
            try {
                $stmt = $db->prepare("UPDATE users SET blacklisted = 0 WHERE id = ?");
                $stmt->execute([$userId]);
                $_SESSION['success_message'] = "User has been removed from blacklist successfully.";
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error removing user from blacklist: " . $e->getMessage();
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
                    $_SESSION['success_message'] = "User has been deleted successfully.";
                    
                    // Redirect back to user list after deletion
                    header('Location: index.php?page=admin&section=users');
                    exit;
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Error deleting user: " . $e->getMessage();
            }
            break;
    }
    
    // Redirect back to this page to remove action from URL
    header('Location: index.php?page=admin&section=view_user&id=' . $userId);
    exit;
}

// Get user details
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['error_message'] = "User not found.";
        header('Location: index.php?page=admin&section=users');
        exit;
    }
    
    // Get user's reservation history
    $stmt = $db->prepare("
        SELECT r.*, 
            (SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
             FROM reservation_items ri 
             JOIN resources rs ON ri.resource_id = rs.id 
             WHERE ri.reservation_id = r.id) as resources
        FROM reservations r 
        WHERE r.user_id = ? 
        ORDER BY r.created_at DESC
        LIMIT 5
    ");
    $stmt->execute([$userId]);
    $reservations = $stmt->fetchAll();
    
    // Count total reservations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE user_id = ?");
    $stmt->execute([$userId]);
    $totalReservations = $stmt->fetch()['count'];
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Error retrieving user details: " . $e->getMessage();
    header('Location: index.php?page=admin&section=users');
    exit;
}
?>

<div class="max-w-4xl mx-auto px-4 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">User Profile: <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?></h1>
        <a href="index.php?page=admin&section=users" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
            Back to Users
        </a>
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
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
        <!-- User Status Banner -->
        <div class="w-full p-4 <?php 
            if ($user['status'] === 'approved') echo 'bg-green-100';
            elseif ($user['status'] === 'pending') echo 'bg-yellow-100';
            elseif ($user['status'] === 'rejected') echo 'bg-red-100';
        ?>">
            <div class="flex justify-between items-center">
                <div>
                    <span class="font-medium <?php 
                        if ($user['status'] === 'approved') echo 'text-green-700';
                        elseif ($user['status'] === 'pending') echo 'text-yellow-700';
                        elseif ($user['status'] === 'rejected') echo 'text-red-700';
                    ?>">
                        Status: <?php echo $user['status'] === 'approved' ? 'Active' : ucfirst($user['status']); ?>
                    </span>
                    
                    <?php if (!empty($user['blacklisted']) && $user['blacklisted'] == 1): ?>
                        <span class="ml-2 px-2 py-1 bg-red-100 text-red-800 text-xs font-semibold rounded-full">
                            BLACKLISTED
                        </span>
                    <?php endif; ?>
                    
                    <span class="ml-4 text-gray-600 text-sm">
                        Registered: <?php echo date('F j, Y', strtotime($user['created_at'])); ?>
                    </span>
                </div>
                
                <div>
                    <?php if ($user['status'] === 'pending'): ?>
                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=approve" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 mr-2" onclick="return confirm('Are you sure you want to approve this user?')">
                            Approve User
                        </a>
                    <?php elseif ($user['status'] === 'approved'): ?>
                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=block" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 mr-2" onclick="return confirm('Are you sure you want to block this user?')">
                            Reject User
                        </a>
                    <?php elseif ($user['status'] === 'rejected'): ?>
                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=activate" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 mr-2" onclick="return confirm('Are you sure you want to activate this user?')">
                            Activate User
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($user['blacklisted'] == 0): ?>
                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=blacklist" class="bg-red-600 hover:bg-red-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 mr-2" onclick="return confirm('Are you sure you want to blacklist this user?')">
                            Blacklist User
                        </a>
                    <?php else: ?>
                        <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=unblacklist" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150 mr-2" onclick="return confirm('Are you sure you want to remove this user from blacklist?')">
                            Remove from Blacklist
                        </a>
                    <?php endif; ?>
                    
                    <a href="index.php?page=admin&section=view_user&id=<?php echo $user['id']; ?>&action=delete" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150" onclick="return confirm('Are you sure you want to delete this user? This action cannot be undone.')">
                        Delete User
                    </a>
                </div>
            </div>
        </div>
        
        <div class="p-6">
            <!-- Personal Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4 pb-2 border-b">Personal Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">Full Name</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['first_name'] . ' ' . $user['middle_initial'] . ' ' . $user['last_name']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Email Address</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Contact Number</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['contact_number']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">Purok</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['purok']); ?></p>
                    </div>
                    
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600">Complete Address</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['address']); ?></p>
                    </div>
                </div>
            </div>
            
            <!-- ID Verification -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4 pb-2 border-b">ID Verification</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <p class="text-sm text-gray-600">ID Type</p>
                        <p class="font-medium"><?php echo htmlspecialchars($user['id_type']); ?></p>
                    </div>
                    
                    <div>
                        <p class="text-sm text-gray-600">ID Verification Status</p>
                        <p class="font-medium">
                            <span class="px-2 py-1 rounded-full text-xs font-semibold 
                            <?php 
                                if ($user['status'] === 'approved') echo 'bg-green-100 text-green-800';
                                elseif ($user['status'] === 'pending') echo 'bg-yellow-100 text-yellow-800';
                                elseif ($user['status'] === 'rejected') echo 'bg-red-100 text-red-800';
                            ?>">
                                <?php echo $user['status'] === 'approved' ? 'Active' : ucfirst($user['status']); ?>
                            </span>
                        </p>
                    </div>
                    
                    <?php
                    // Check both possible field names for ID image
                    $idImagePath = null;
                    if (!empty($user['id_image'])) {
                        // Add the directory path prefix if it's not already there
                        $idImagePath = $user['id_image'];
                        if (strpos($idImagePath, 'uploads/') !== 0) {
                            $idImagePath = 'uploads/ids/' . $idImagePath;
                        }
                    } elseif (!empty($user['id_proof'])) {
                        // Add the directory path prefix if it's not already there
                        $idImagePath = $user['id_proof'];
                        if (strpos($idImagePath, 'uploads/') !== 0) {
                            $idImagePath = 'uploads/ids/' . $idImagePath;
                        }
                    }
                    
                    if ($idImagePath): 
                    ?>
                    <div class="md:col-span-2">
                        <p class="text-sm text-gray-600 mb-2">ID Image</p>
                        <div class="border rounded-lg p-2 bg-gray-50">
                            <img src="<?php echo htmlspecialchars($idImagePath); ?>" alt="ID Image" class="max-w-full h-auto max-h-64 mx-auto">
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Reservation History -->
            <div>
                <h2 class="text-lg font-semibold text-blue-700 mb-4 pb-2 border-b">Reservation History (<?php echo $totalReservations; ?>)</h2>
                
                <?php if (empty($reservations)): ?>
                    <p class="text-gray-600">This user has no reservation history.</p>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resources</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                    <th class="py-2 px-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-2 px-3"><?php echo $reservation['id']; ?></td>
                                        <td class="py-2 px-3"><?php echo $reservation['resources']; ?></td>
                                        <td class="py-2 px-3">
                                            <div><?php echo date('M d, Y', strtotime($reservation['start_datetime'])); ?></div>
                                            <div class="text-xs text-gray-500">
                                                <?php echo date('h:i A', strtotime($reservation['start_datetime'])); ?> - 
                                                <?php echo date('h:i A', strtotime($reservation['end_datetime'])); ?>
                                            </div>
                                        </td>
                                        <td class="py-2 px-3">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                            <?php 
                                                switch ($reservation['status']) {
                                                    case 'pending': echo 'bg-yellow-100 text-yellow-800'; break;
                                                    case 'approved': echo 'bg-green-100 text-green-800'; break;
                                                    case 'rejected': echo 'bg-red-100 text-red-800'; break;
                                                    case 'cancelled': echo 'bg-gray-100 text-gray-800'; break;
                                                    case 'completed': echo 'bg-blue-100 text-blue-800'; break;
                                                    default: echo 'bg-gray-100 text-gray-800';
                                                }
                                            ?>">
                                                <?php echo ucfirst($reservation['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-2 px-3">
                                            <a href="index.php?page=admin&section=view_reservation&id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:underline">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if ($totalReservations > 5): ?>
                        <div class="mt-3 text-center">
                            <a href="index.php?page=admin&section=reservations&user_id=<?php echo $user['id']; ?>" class="text-sm text-blue-600 hover:underline">View all <?php echo $totalReservations; ?> reservations</a>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>