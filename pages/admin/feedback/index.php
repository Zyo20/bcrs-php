<?php
// Check if user is admin
if (!isAdmin()) {
    redirect('login');
}

// Get all feedback with user information
try {
    // Get filter parameter with default as 'all'
    $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
    
    // Base query
    $query = "
        SELECT f.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email
        FROM feedback f
        JOIN users u ON f.user_id = u.id
    ";
    
    // Add filters
    if ($filter === 'pending') {
        $query .= " WHERE f.status = 'pending'";
    } elseif ($filter === 'read') {
        $query .= " WHERE f.status = 'read'";
    } elseif ($filter === 'responded') {
        $query .= " WHERE f.status = 'responded'";
    }
    
    // Add order by
    $query .= " ORDER BY f.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $feedbacks = $stmt->fetchAll();
} catch (PDOException $e) {
    $feedbacks = [];
    setFlashMessage('Error fetching feedback: ' . $e->getMessage(), 'error');
}

// Count feedback by status using direct count queries for more reliability
try {
    // Total count
    $stmt = $db->query("SELECT COUNT(*) FROM feedback");
    $total = $stmt->fetchColumn() ?: 0;
    
    // Pending count
    $stmt = $db->query("SELECT COUNT(*) FROM feedback WHERE status = 'pending'");
    $pending = $stmt->fetchColumn() ?: 0;
    
    // Read count
    $stmt = $db->query("SELECT COUNT(*) FROM feedback WHERE status = 'read'");
    $read = $stmt->fetchColumn() ?: 0;
    
    // Responded count
    $stmt = $db->query("SELECT COUNT(*) FROM feedback WHERE status = 'responded'");
    $responded = $stmt->fetchColumn() ?: 0;
    
    // Combine all counts
    $counts = [
        'total' => (int)$total,
        'pending' => (int)$pending,
        'read' => (int)$read,
        'responded' => (int)$responded
    ];
    
    // Debug information - remove in production
    echo "<!-- Debug: Total: $total, Pending: $pending, Read: $read, Responded: $responded -->";
    
} catch (PDOException $e) {
    $counts = ['total' => 0, 'pending' => 0, 'read' => 0, 'responded' => 0];
    echo "<!-- Debug: Error counting feedback: " . $e->getMessage() . " -->";
}

// CSRF token for actions
$csrf_token = generateCSRFToken();
?>

<div class="bg-white p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-gray-800">User Feedback</h1>
    </div>
    
    <!-- Feedback Stats -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-blue-50 p-4 rounded-lg border border-blue-200">
            <h3 class="text-lg font-semibold text-blue-700">Total</h3>
            <p class="text-2xl font-bold text-blue-800"><?php echo $counts['total']; ?></p>
        </div>
        <div class="bg-yellow-50 p-4 rounded-lg border border-yellow-200">
            <h3 class="text-lg font-semibold text-yellow-700">Pending</h3>
            <p class="text-2xl font-bold text-yellow-800"><?php echo $counts['pending']; ?></p>
        </div>
        <div class="bg-purple-50 p-4 rounded-lg border border-purple-200">
            <h3 class="text-lg font-semibold text-purple-700">Read</h3>
            <p class="text-2xl font-bold text-purple-800"><?php echo $counts['read']; ?></p>
        </div>
        <div class="bg-green-50 p-4 rounded-lg border border-green-200">
            <h3 class="text-lg font-semibold text-green-700">Responded</h3>
            <p class="text-2xl font-bold text-green-800"><?php echo $counts['responded']; ?></p>
        </div>
    </div>
    
    <!-- Filter options -->
    <div class="flex gap-2 mb-4">
        <a href="?page=admin&section=feedback" class="px-3 py-1 rounded-full <?php echo $filter === 'all' ? 'bg-blue-600 text-white' : 'bg-gray-200 text-gray-800'; ?>">
            All (<?php echo $counts['total']; ?>)
        </a>
        <a href="?page=admin&section=feedback&filter=pending" class="px-3 py-1 rounded-full <?php echo $filter === 'pending' ? 'bg-yellow-500 text-white' : 'bg-gray-200 text-gray-800'; ?>">
            Pending (<?php echo $counts['pending']; ?>)
        </a>
        <a href="?page=admin&section=feedback&filter=read" class="px-3 py-1 rounded-full <?php echo $filter === 'read' ? 'bg-purple-600 text-white' : 'bg-gray-200 text-gray-800'; ?>">
            Read (<?php echo $counts['read']; ?>)
        </a>
        <a href="?page=admin&section=feedback&filter=responded" class="px-3 py-1 rounded-full <?php echo $filter === 'responded' ? 'bg-green-600 text-white' : 'bg-gray-200 text-gray-800'; ?>">
            Responded (<?php echo $counts['responded']; ?>)
        </a>
    </div>
    
    <?php if (count($feedbacks) > 0): ?>
        <div class="overflow-auto">
            <table class="min-w-full bg-white">
                <thead class="bg-gray-100">
                    <tr>
                        <th class="py-3 px-4 text-left">ID</th>
                        <th class="py-3 px-4 text-left">User</th>
                        <th class="py-3 px-4 text-left">Subject</th>
                        <th class="py-3 px-4 text-left">Status</th>
                        <th class="py-3 px-4 text-left">Date</th>
                        <th class="py-3 px-4 text-left">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($feedbacks as $feedback): ?>
                        <tr class="border-b hover:bg-gray-50">
                            <td class="py-3 px-4"><?php echo $feedback['id']; ?></td>
                            <td class="py-3 px-4">
                                <?php echo htmlspecialchars($feedback['user_name']); ?><br>
                                <span class="text-sm text-gray-500"><?php echo htmlspecialchars($feedback['email']); ?></span>
                            </td>
                            <td class="py-3 px-4"><?php echo htmlspecialchars($feedback['subject']); ?></td>
                            <td class="py-3 px-4">
                                <?php if ($feedback['status'] === 'pending'): ?>
                                    <span class="px-2 py-1 bg-yellow-100 text-yellow-800 rounded-full text-xs">Pending</span>
                                <?php elseif ($feedback['status'] === 'read'): ?>
                                    <span class="px-2 py-1 bg-purple-100 text-purple-800 rounded-full text-xs">Read</span>
                                <?php elseif ($feedback['status'] === 'responded'): ?>
                                    <span class="px-2 py-1 bg-green-100 text-green-800 rounded-full text-xs">Responded</span>
                                <?php endif; ?>
                            </td>
                            <td class="py-3 px-4"><?php echo formatDate($feedback['created_at']); ?></td>
                            <td class="py-3 px-4">
                                <div class="flex space-x-2">
                                    <a href="?page=admin&section=feedback&action=view&id=<?php echo $feedback['id']; ?>" 
                                       class="text-blue-600 hover:text-blue-800" 
                                       title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    
                                    <?php if ($feedback['status'] === 'pending'): ?>
                                        <a href="?page=admin&section=feedback&action=mark_read&id=<?php echo $feedback['id']; ?>&csrf_token=<?php echo $csrf_token; ?>" 
                                           class="text-purple-600 hover:text-purple-800" 
                                           title="Mark as Read">
                                            <i class="fas fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="?page=admin&section=feedback&action=respond&id=<?php echo $feedback['id']; ?>" 
                                       class="text-green-600 hover:text-green-800" 
                                       title="Respond">
                                        <i class="fas fa-reply"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php else: ?>
        <div class="bg-gray-100 p-6 rounded-lg text-center">
            <p class="text-gray-600">No feedback found.</p>
        </div>
    <?php endif; ?>
</div>