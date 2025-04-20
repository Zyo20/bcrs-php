<?php
// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $firstName = sanitize($_POST['first_name']);
    $middleInitial = sanitize($_POST['middle_initial']);
    $lastName = sanitize($_POST['last_name']);
    $address = sanitize($_POST['address']);
    $contactNumber = sanitize($_POST['contact_number']);
    $purok = sanitize($_POST['purok']);
    $idType = sanitize($_POST['id_type']);
    $email = sanitize($_POST['email']);
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validate form
    $errors = [];
    
    if (empty($firstName)) {
        $errors[] = "First name is required";
    }
    
    if (empty($lastName)) {
        $errors[] = "Last name is required";
    }
    
    if (empty($address)) {
        $errors[] = "Complete address is required";
    }
    
    if (empty($contactNumber)) {
        $errors[] = "Contact number is required";
    } elseif (!preg_match('/^[0-9]{11}$/', $contactNumber)) {
        $errors[] = "Contact number must be 11 digits";
    }
    
    if (empty($purok)) {
        $errors[] = "Purok is required";
    }
    
    if (empty($idType)) {
        $errors[] = "ID type is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email is already registered
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email address is already registered";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking email: " . $e->getMessage();
        }
    }
    
    if (empty($_FILES['id_image']['name'])) {
        $errors[] = "Valid ID image is required";
    } else {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($_FILES['id_image']['type'], $allowedTypes)) {
            $errors[] = "ID image must be JPEG, JPG or PNG format";
        }
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, process registration
    if (empty($errors)) {
        // Upload ID image
        $idImage = uploadFile($_FILES['id_image'], 'uploads/ids');
        
        if ($idImage) {
            // Hash the password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            
            // Save to database
            try {
                $stmt = $db->prepare("INSERT INTO users (first_name, middle_initial, last_name, address, contact_number, purok, id_type, id_image, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'user', 'pending')");
                $stmt->execute([$firstName, $middleInitial, $lastName, $address, $contactNumber, $purok, $idType, $idImage, $email, $hashedPassword]);
                
                // Set flash message
                $_SESSION['flash_message'] = "Registration successful! Please wait for admin approval.";
                $_SESSION['flash_type'] = "success";
                
                // Redirect to login page
                header("Location: index.php?page=login");
                exit;
            } catch (PDOException $e) {
                $errors[] = "Registration failed: " . $e->getMessage();
            }
        } else {
            $errors[] = "Failed to upload ID image";
        }
    }
}
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Register as a Resident</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Personal Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Personal Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                    <div>
                        <label for="first_name" class="block text-gray-700 mb-1">First Name *</label>
                        <input type="text" id="first_name" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="Juan" value="<?php echo isset($firstName) ? $firstName : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="middle_initial" class="block text-gray-700 mb-1">Middle Name *</label>
                        <input type="text" id="middle_initial" name="middle_initial" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="Type NA if none" value="<?php echo isset($middleInitial) ? $middleInitial : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-gray-700 mb-1">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="Dela Cruz" value="<?php echo isset($lastName) ? $lastName : ''; ?>" required>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="address" class="block text-gray-700 mb-1">Complete Address *</label>
                    <textarea id="address" name="address" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" rows="2" required><?php echo isset($address) ? $address : ''; ?></textarea>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="contact_number" class="block text-gray-700 mb-1">Contact Number *</label>
                        <input type="text" id="contact_number" name="contact_number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="09XXXXXXXXX" pattern="[0-9]{11}" value="<?php echo isset($contactNumber) ? $contactNumber : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="purok" class="block text-gray-700 mb-1">Purok *</label>
                        <select id="purok" name="purok" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                            <option value="">Select Purok</option>
                            <option value="Purok 1" <?php echo (isset($purok) && $purok === 'Purok 1') ? 'selected' : ''; ?>>Purok 1</option>
                            <option value="Purok 2" <?php echo (isset($purok) && $purok === 'Purok 2') ? 'selected' : ''; ?>>Purok 2</option>
                            <option value="Purok 3" <?php echo (isset($purok) && $purok === 'Purok 3') ? 'selected' : ''; ?>>Purok 3</option>
                            <option value="Purok 4" <?php echo (isset($purok) && $purok === 'Purok 4') ? 'selected' : ''; ?>>Purok 4</option>
                            <option value="Purok 5" <?php echo (isset($purok) && $purok === 'Purok 5') ? 'selected' : ''; ?>>Purok 5</option>
                            <option value="Purok 6" <?php echo (isset($purok) && $purok === 'Purok 6') ? 'selected' : ''; ?>>Purok 6</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- ID Verification -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">ID Verification</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="id_type" class="block text-gray-700 mb-1">ID Type *</label>
                        <select id="id_type" name="id_type" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                            <option value="">Select ID Type</option>
                            <option value="National ID" <?php echo (isset($idType) && $idType === 'National ID') ? 'selected' : ''; ?>>National ID</option>
                            <option value="Driver's License" <?php echo (isset($idType) && $idType === "Driver's License") ? 'selected' : ''; ?>>Driver's License</option>
                            <option value="Voter's ID" <?php echo (isset($idType) && $idType === "Voter's ID") ? 'selected' : ''; ?>>Voter's ID</option>
                            <option value="PhilHealth ID" <?php echo (isset($idType) && $idType === 'PhilHealth ID') ? 'selected' : ''; ?>>PhilHealth ID</option>
                            <option value="Postal ID" <?php echo (isset($idType) && $idType === 'Postal ID') ? 'selected' : ''; ?>>Postal ID</option>
                            <option value="Barangay ID" <?php echo (isset($idType) && $idType === 'Barangay ID') ? 'selected' : ''; ?>>Barangay ID</option>
                        </select>
                    </div>
                    
                    <div>
                        <label for="id_image" class="block text-gray-700 mb-1">Upload Valid ID Image *</label>
                        <input type="file" id="id_image" name="id_image" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" accept="image/jpeg,image/jpg,image/png" required>
                        <p class="text-xs text-gray-500 mt-1">Upload a clear image of your valid ID (JPEG, JPG, or PNG format)</p>
                    </div>
                </div>
            </div>
            
            <!-- Account Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Account Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-gray-700 mb-1">Email Address *</label>
                        <input type="email" id="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" placeholder="youremail@example.com" value="<?php echo isset($email) ? $email : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="password" class="block text-gray-700 mb-1">Password *</label>
                        <input type="password" id="password" name="password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" minlength="6" required>
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-gray-700 mb-1">Confirm Password *</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" minlength="6" required>
                    </div>
                </div>
            </div>
            
            <div class="text-center md:text-right">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Register</button>
            </div>
        </form>
    </div>
    
    <div class="mt-4 text-center">
        <p class="text-gray-600">Already have an account? <a href="index.php?page=login" class="text-blue-600 hover:underline">Login here</a></p>
    </div>
</div>