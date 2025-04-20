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
$errors = [];

// Get feedback details
try {
    $stmt = $db->prepare("
        SELECT f.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.email, u.contact_number, u.id as user_id
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
    
    // If feedback is already responded, redirect to view
    if ($feedback['status'] === 'responded') {
        setFlashMessage('This feedback has already been responded to.', 'info');
        redirect('admin&section=feedback&action=view&id=' . $id);
    }
    
} catch (PDOException $e) {
    setFlashMessage('Error retrieving feedback: ' . $e->getMessage(), 'error');
    redirect('admin&section=feedback');
}

// Process response form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['respond_feedback'])) {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'error');
        redirect('admin&section=feedback&action=respond&id=' . $id);
    }
    
    // Validate response
    $response = sanitize($_POST['response']);
    
    if (empty($response)) {
        $errors[] = 'Response message is required';
    }
    
    // If no validation errors, save the response
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Update feedback with response and change status to responded
            $stmt = $db->prepare("
                UPDATE feedback 
                SET admin_response = ?, status = 'responded', updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$response, $id]);
            
            // Create notification for the user
            $notificationLink = "index.php?page=view_feedback&id=" . $id;
            $notificationMessage = "Admin has responded to your feedback: " . substr($feedback['subject'], 0, 30) . (strlen($feedback['subject']) > 30 ? '...' : '');
            createNotification($feedback['user_id'], $notificationMessage, $notificationLink);
            
            // Send SMS notification (if configured)
            if (!empty($feedback['contact_number'])) {
                $message = "Admin has responded to your feedback. Please check your account for details.";
                sendSMS($feedback['contact_number'], $message);
            }
            
            $db->commit();
            
            // Set success message and redirect
            setFlashMessage('Your response has been sent successfully.', 'success');
            redirect('index.php?page=admin&section=feedback&action=view&id=' . $id);
            
        } catch (PDOException $e) {
            $db->rollBack();
            setFlashMessage('An error occurred while sending your response: ' . $e->getMessage(), 'error');
        }
    }
}

// Generate CSRF token
$csrf_token = generateCSRFToken();
?>

<div class="container mx-auto">
    <div class="mb-4">
        <a href="?page=admin&section=feedback&action=view&id=<?php echo $id; ?>" class="text-blue-600 hover:text-blue-800">
            <i class="fas fa-arrow-left mr-2"></i>Back to Feedback Details
        </a>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow-md mb-6">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Respond to Feedback</h1>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p class="font-bold">Please correct the following errors:</p>
                <ul class="list-disc pl-5">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <!-- Original Feedback Details -->
        <div class="mb-6">
            <h3 class="text-lg font-semibold mb-2">User Feedback</h3>
            <div class="bg-gray-50 p-4 rounded-lg mb-4">
                <p class="mb-2"><span class="font-medium">From:</span> <?php echo htmlspecialchars($feedback['user_name']); ?></p>
                <p class="mb-2"><span class="font-medium">Subject:</span> <?php echo htmlspecialchars($feedback['subject']); ?></p>
                <p class="mb-2"><span class="font-medium">Date:</span> <?php echo formatDate($feedback['created_at']); ?></p>
                <div class="mt-4 pt-4 border-t border-gray-200">
                    <p class="whitespace-pre-wrap"><?php echo nl2br(htmlspecialchars($feedback['message'])); ?></p>
                </div>
            </div>
        </div>
        
        <!-- Response Form -->
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
            
            <div class="mb-6">
                <label for="response" class="block text-gray-700 font-medium mb-2">Your Response</label>
                <textarea 
                    name="response" 
                    id="response" 
                    rows="8" 
                    class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                    placeholder="Type your response here..."
                    required
                ><?php echo isset($response) ? htmlspecialchars($response) : ''; ?></textarea>
            </div>
            
            <div class="flex justify-between items-center">
                <a href="?page=admin&section=feedback&action=view&id=<?php echo $id; ?>" class="text-gray-600 hover:text-gray-800">
                    Cancel
                </a>
                <button 
                    type="submit" 
                    name="respond_feedback" 
                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500"
                >
                    <i class="fas fa-paper-plane mr-2"></i>Send Response
                </button>
            </div>
        </form>
    </div>
</div>