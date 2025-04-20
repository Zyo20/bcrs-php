<?php
// Check if the user is logged in
if (!isLoggedIn()) {
    redirect('login');
}

// Process feedback submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    // Validate CSRF token
    if (!verifyCSRFToken($_POST['csrf_token'])) {
        setFlashMessage('Invalid request. Please try again.', 'error');
        redirect('feedback');
    }
    
    // Validate inputs
    $subject = sanitize($_POST['subject']);
    $message = sanitize($_POST['message']);
    $errors = [];
    
    if (empty($subject)) {
        $errors[] = 'Subject is required';
    } elseif (strlen($subject) > 100) {
        $errors[] = 'Subject must be less than 100 characters';
    }
    
    if (empty($message)) {
        $errors[] = 'Message is required';
    }
    
    // If no validation errors, save the feedback
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("
                INSERT INTO feedback (user_id, subject, message) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$_SESSION['user_id'], $subject, $message]);
            
            // Set success message and redirect
            setFlashMessage('Your feedback has been successfully submitted. Thank you!', 'success');
            redirect('index?page=dashboard');
            
        } catch (PDOException $e) {
            setFlashMessage('An error occurred while submitting your feedback. Please try again later.', 'error');
        }
    }
}
?>

<div class="bg-white p-6 rounded-lg shadow-md max-w-2xl mx-auto">
    <h1 class="text-2xl font-bold text-gray-800 mb-6">Send Feedback</h1>
    
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
    
    <form method="POST" action="index.php?page=feedback">
        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
        
        <div class="mb-4">
            <label for="subject" class="block text-gray-700 font-medium mb-2">Subject</label>
            <input 
                type="text" 
                name="subject" 
                id="subject" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                value="<?php echo isset($subject) ? htmlspecialchars($subject) : ''; ?>"
                placeholder="Brief subject of your feedback"
                required
            >
        </div>
        
        <div class="mb-6">
            <label for="message" class="block text-gray-700 font-medium mb-2">Message</label>
            <textarea 
                name="message" 
                id="message" 
                rows="6" 
                class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500" 
                placeholder="Please provide details of your feedback, suggestions, or concerns..."
                required
            ><?php echo isset($message) ? htmlspecialchars($message) : ''; ?></textarea>
        </div>
        
        <div class="flex justify-between items-center">
            <a href="index?page=dashboard" class="text-gray-600 hover:text-gray-800">
                <i class="fas fa-arrow-left mr-2"></i>Back to Dashboard
            </a>
            <button 
                type="submit" 
                name="submit_feedback" 
                class="bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 px-4 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500"
            >
                <i class="fas fa-paper-plane mr-2"></i>Submit Feedback
            </button>
        </div>
    </form>
</div>