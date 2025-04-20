<?php
// Check if user is already logged in
if (isLoggedIn()) {
    header("Location: index.php");
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $contactNumber = sanitize($_POST['contact_number']);
    $password = $_POST['password'];
    
    // Validate form
    $errors = [];
    
    if (empty($contactNumber)) {
        $errors[] = "Contact number is required";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    }
    
    // If no errors, process login
    if (empty($errors)) {
        try {
            // Check if user exists
            $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$contactNumber]);
            $user = $stmt->fetch();
            
            if ($user && password_verify($password, $user['password'])) {
                // Check user status
                if ($user['status'] === 'approved') {
                    // Check if user is blacklisted
                    if ($user['blacklisted'] == 1) {
                        $_SESSION['flash_message'] = "Your account has been blacklisted. Please visit the barangay office for assistance.";
                        $_SESSION['flash_type'] = "error";
                    } else {
                        // Set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                        $_SESSION['role'] = $user['role'];
                        
                        // Set flash message
                        $_SESSION['flash_message'] = "Login successful!";
                        $_SESSION['flash_type'] = "success";
                        
                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: index.php?page=admin");
                        } else {
                            header("Location: index.php?page=dashboard");
                        }
                        exit;
                    }
                } elseif ($user['status'] === 'rejected') {
                    // Handle blocked users (status = rejected)
                    $_SESSION['flash_message'] = "Your account has been blocked. Please contact the barangay office for assistance.";
                    $_SESSION['flash_type'] = "error";
                } else {
                    // Handle pending users
                    $_SESSION['flash_message'] = "Your account is still pending approval. Please wait for admin confirmation.";
                    $_SESSION['flash_type'] = "error";
                }
            } else {
                $_SESSION['flash_message'] = "Invalid contact number or password.";
                $_SESSION['flash_type'] = "error";
            }
        } catch (PDOException $e) {
            $errors[] = "Login failed: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-md mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Login to Your Account</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['flash_message']) && isset($_SESSION['flash_type'])): ?>
        <div class="<?php echo $_SESSION['flash_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> p-4 rounded mb-6">
            <p><?php echo $_SESSION['flash_message']; ?></p>
            <?php 
            // Unset the flash message after displaying it
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            ?>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="mb-4">
                <label for="contact_number" class="block text-gray-700 mb-1">Email Address</label>
                <input type="email" id="contact_number" name="contact_number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="youremail@example.com" pattern="[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$" value="<?php echo isset($contactNumber) ? $contactNumber : ''; ?>" required>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 mb-1">Password</label>
                <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
            </div>
            
            <div class="text-center">
                <button type="submit" class="w-full px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Login</button>
            </div>
        </form>
    </div>
    
    <div class="mt-4 text-center">
        <p class="text-gray-600">Don't have an account? <a href="index.php?page=register" class="text-blue-600 hover:underline">Register here</a></p>
    </div>
</div>