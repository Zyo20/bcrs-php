<?php
// Check if user is admin
if (!isAdmin()) {
    redirect('login');
}

// Ensure ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('Feedback ID is required', 'error');
    redirect('admin&section=feedback');
}

$id = (int)$_GET['id'];

// Get feedback details
try {
    $stmt = $db->prepare("
        SELECT f.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email, u.contact_number
        FROM feedback f
        JOIN users u ON f.user_id = u.id
        WHERE f.id = ?
    ");
    $stmt->execute([$id]);
    $feedback = $stmt->fetch();
    
    if (!$feedback) {
        setFlashMessage('Feedback not found', 'error');
        redirect('admin&section=feedback');
    }
    
    // If this is the first time viewing and it's pending, mark as read
    if ($feedback['status'] === 'pending') {
        $stmt = $db->prepare("UPDATE feedback SET status = 'read' WHERE id = ?");
        $stmt->execute([$id]);
        $feedback['status'] = 'read';
    }
    
} catch (PDOException $e) {
    setFlashMessage('Error retrieving feedback: ' . $e->getMessage(), 'error');
    redirect('admin&section=feedback');
}

// Generate CSRF token for actions
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto">
    <div class="mb-4">
        <a href="?page=admin&section=feedback" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Feedback List
        </a>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <div class="flex justify-between items-start mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Feedback Details</h1>
            <div>
                <?php if ($feedback['status'] === 'pending'): ?>
                    <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full">Pending</span>
                <?php elseif ($feedback['status'] === 'read'): ?>
                    <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full">Read</span>
                <?php elseif ($feedback['status'] === 'responded'): ?>
                    <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full">Responded</span>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Feedback Information -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
            <div>
                <h3 class="text-lg font-semibold mb-2">Feedback Information</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="mb-2"><span class="font-medium">Subject:</span> <?php echo htmlspecialchars($feedback['subject']); ?></p>
                    <p class="mb-2"><span class="font-medium">Date Submitted:</span> <?php echo formatDate($feedback['created_at']); ?></p>
                    <p class="mb-2"><span class="font-medium">Status:</span> <?php echo ucfirst($feedback['status']); ?></p>
                </div>
            </div>
            
            <div>
                <h3 class="text-lg font-semibold mb-2">User Information</h3>
                <div class="bg-gray-50 p-4 rounded-lg">
                    <p class="mb-2"><span class="font-medium">Name:</span> <?php echo htmlspecialchars($feedback['user_name']); ?></p>
                    <p class="mb-2"><span class="font-medium">Email:</span> <?php echo htmlspecialchars($feedback['email']); ?></p>
                    <p class="mb-2"><span class="font-medium">Contact:</span> <?php echo htmlspecialchars($feedback['contact_number']); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Feedback Message -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">Feedback Message</h3>
            <div class="bg-gray-50 p-4 rounded-lg">
                <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></p>
            </div>
        </div>
        
        <!-- Admin Response Section -->
        <?php if ($feedback['status'] === 'responded'): ?>
            <div>
                <h3 class="text-lg font-semibold mb-2">Admin Response</h3>
                <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                    <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                </div>
            </div>
        <?php else: ?>
            <div class="text-center">
                <a href="?page=admin&section=feedback&action=respond&id=<?php echo $id; ?>" class="bg-green-600 hover:bg-green-700 text-white font-medium px-4 py-2 rounded-md">
                    <i class="fas fa-reply mr-2"></i>Respond to Feedback
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>