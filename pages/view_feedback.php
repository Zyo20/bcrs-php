<?php
// Check if the user is logged in
if (!isLoggedIn()) {
    redirect('login');
}

// Ensure ID is provided
if (!isset($_GET['id'])) {
    setFlashMessage('Feedback ID is required', 'error');
    redirect('dashboard');
}

$id = (int)$_GET['id'];
$userId = $_SESSION['user_id'];

// Get feedback details
try {
    $stmt = $db->prepare("
        SELECT * FROM feedback
        WHERE id = ? AND user_id = ?
    ");
    $stmt->execute([$id, $userId]);
    $feedback = $stmt->fetch();
    
    if (!$feedback) {
        setFlashMessage('Feedback not found', 'error');
        redirect('dashboard');
    }
    
} catch (PDOException $e) {
    setFlashMessage('Error retrieving feedback: ' . $e->getMessage(), 'error');
    redirect('dashboard');
}
?>

<div class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
    <div class="mb-4">
        <a href="index?page=dashboard" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
        </a>
    </div>

    <h1 class="text-2xl font-bold text-gray-800 mb-6">Feedback Details</h1>
    
    <!-- Feedback Status Badge -->
    <div class="mb-6 flex justify-end">
        <?php if ($feedback['status'] === 'pending'): ?>
            <span class="px-3 py-1 bg-yellow-100 text-yellow-800 rounded-full text-sm">Pending</span>
        <?php elseif ($feedback['status'] === 'read'): ?>
            <span class="px-3 py-1 bg-purple-100 text-purple-800 rounded-full text-sm">Read by Admin</span>
        <?php elseif ($feedback['status'] === 'responded'): ?>
            <span class="px-3 py-1 bg-green-100 text-green-800 rounded-full text-sm">Responded</span>
        <?php endif; ?>
    </div>

    <!-- Feedback Information -->
    <div class="mb-6">
        <h3 class="text-lg font-medium text-gray-700 mb-3">Your Feedback</h3>
        <div class="bg-gray-50 p-4 rounded-lg">
            <p class="mb-2"><span class="font-medium">Subject:</span> <?php echo htmlspecialchars($feedback['subject']); ?></p>
            <p class="mb-2"><span class="font-medium">Date Submitted:</span> <?php echo formatDate($feedback['created_at']); ?></p>
            <div class="mt-4 pt-4 border-t border-gray-200">
                <p class="font-medium mb-2">Message:</p>
                <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></p>
            </div>
        </div>
    </div>
    
    <!-- Admin Response Section -->
    <?php if ($feedback['status'] === 'responded' && !empty($feedback['admin_response'])): ?>
        <div class="mb-6">
            <h3 class="text-lg font-medium text-gray-700 mb-3">Admin Response</h3>
            <div class="bg-green-50 p-4 rounded-lg border border-green-200">
                <p class="mb-2"><span class="font-medium">Response Date:</span> <?php echo formatDate($feedback['updated_at']); ?></p>
                <div class="mt-4 pt-4 border-t border-green-200">
                    <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></p>
                </div>
            </div>
        </div>
    <?php elseif ($feedback['status'] !== 'responded'): ?>
        <div class="bg-gray-100 p-4 rounded-lg text-center text-gray-600">
            <p>Your feedback is being reviewed. You will be notified when there is a response.</p>
        </div>
    <?php endif; ?>

    <!-- Submit New Feedback Button -->
    <div class="mt-6 text-center">
        <a href="index?page=feedback" class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md">
            <i class="fas fa-comment-dots mr-2"></i>Submit Another Feedback
        </a>
    </div>
</div>