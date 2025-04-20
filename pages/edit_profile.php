<?php
// Check if user is logged in
if (!isLoggedIn()) {
    header("Location: index.php?page=login");
    exit;
}

// Get user data
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $_SESSION['flash_message'] = "User not found.";
        $_SESSION['flash_type'] = "error";
        header('Location: index.php');
        exit;
    }
} catch (PDOException $e) {
    $_SESSION['flash_message'] = "Error retrieving user data: " . $e->getMessage();
    $_SESSION['flash_type'] = "error";
    header('Location: index.php');
    exit;
}

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $firstName = sanitize($_POST['first_name']);
    $middleInitial = sanitize($_POST['middle_initial']);
    $lastName = sanitize($_POST['last_name']);
    $address = sanitize($_POST['address']);
    $contactNumber = sanitize($_POST['contact_number']);
    $purok = sanitize($_POST['purok']);
    $email = sanitize($_POST['email']);
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
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
    
    if (empty($email)) {
        $errors[] = "Email address is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    } else {
        // Check if email is already registered to another user
        try {
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id != ?");
            $stmt->execute([$email, $_SESSION['user_id']]);
            if ($stmt->fetchColumn() > 0) {
                $errors[] = "Email address is already registered to another user";
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking email: " . $e->getMessage();
        }
    }
    
    // Handle password change if requested
    if (!empty($currentPassword)) {
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = "Current password is incorrect";
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required when changing password";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        }
    }
    
    // Handle ID image update if provided
    $idImage = $user['id_image']; // Default to current ID image
    if (!empty($_FILES['id_image']['name'])) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
        if (!in_array($_FILES['id_image']['type'], $allowedTypes)) {
            $errors[] = "ID image must be JPEG, JPG or PNG format";
        } else {
            // Upload new ID image
            $newIdImage = uploadFile($_FILES['id_image'], 'uploads/ids');
            if ($newIdImage) {
                $idImage = $newIdImage;
            } else {
                $errors[] = "Failed to upload new ID image";
            }
        }
    }
    
    // If no errors, update profile
    if (empty($errors)) {
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Prepare query base
            $query = "UPDATE users SET 
                first_name = ?, 
                middle_initial = ?, 
                last_name = ?, 
                address = ?, 
                contact_number = ?, 
                purok = ?, 
                email = ?,
                id_image = ?";
            
            $params = [
                $firstName,
                $middleInitial,
                $lastName,
                $address,
                $contactNumber,
                $purok,
                $email,
                $idImage
            ];
            
            // Add password update if needed
            if (!empty($currentPassword) && !empty($newPassword)) {
                $query .= ", password = ?";
                $params[] = password_hash($newPassword, PASSWORD_DEFAULT);
            }
            
            // Complete the query
            $query .= " WHERE id = ?";
            $params[] = $_SESSION['user_id'];
            
            // Execute the update
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            
            // Commit transaction
            $db->commit();
            
            // Update session data
            $_SESSION['user_name'] = $firstName . ' ' . $lastName;
            
            // Set success message
            $_SESSION['flash_message'] = "Profile updated successfully!";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to refresh the page
            header("Location: index.php?page=edit_profile");
            exit;
        } catch (PDOException $e) {
            // Rollback transaction on error
            $db->rollBack();
            $errors[] = "Profile update failed: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-3xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Edit Your Profile</h1>
    
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
                        <input type="text" id="first_name" name="first_name" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['first_name']); ?>" required>
                    </div>
                    
                    <div>
                        <label for="middle_initial" class="block text-gray-700 mb-1">Middle Name *</label>
                        <input type="text" id="middle_initial" name="middle_initial" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['middle_initial']); ?>">
                    </div>
                    
                    <div>
                        <label for="last_name" class="block text-gray-700 mb-1">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['last_name']); ?>" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="address" class="block text-gray-700 mb-1">Complete Address *</label>
                        <input type="text" id="address" name="address" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['address']); ?>" required>
                    </div>
                    
                    <div>
                        <label for="purok" class="block text-gray-700 mb-1">Purok *</label>
                        <input type="text" id="purok" name="purok" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['purok']); ?>" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="id_type" class="block text-gray-700 mb-1">ID Type</label>
                        <input type="text" id="id_type" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none bg-gray-100" value="<?php echo htmlspecialchars($user['id_type']); ?>" disabled>
                        <p class="text-xs text-gray-500 mt-1">ID type cannot be changed</p>
                    </div>
                    
                    <div>
                        <label for="id_image" class="block text-gray-700 mb-1">Update Valid ID Image (Optional)</label>
                        <input type="file" id="id_image" name="id_image" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" accept="image/jpeg,image/jpg,image/png">
                        <p class="text-xs text-gray-500 mt-1">Leave empty to keep current ID image</p>
                    </div>
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
                    <p class="text-sm font-medium text-gray-700">Current ID Image:</p>
                    <div class="border rounded-lg p-2 bg-gray-50">
                    <img src="<?php echo htmlspecialchars($idImagePath); ?>" alt="ID Image" class="max-w-full h-auto max-h-64 mx-auto">
                    </div>
                </div>
            <?php endif; ?>
                </div>
            
            <!-- Account Information -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Account Information</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="email" class="block text-gray-700 mb-1">Email Address *</label>
                        <input type="email" id="email" name="email" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                    </div>
                    
                    <div>
                        <label for="contact_number" class="block text-gray-700 mb-1">Contact Number *</label>
                        <input type="text" id="contact_number" name="contact_number" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" pattern="[0-9]{11}" placeholder="09xxxxxxxxx" value="<?php echo htmlspecialchars($user['contact_number']); ?>" required>
                        <p class="text-xs text-gray-500 mt-1">11-digit mobile number</p>
                    </div>
                </div>
            </div>
            
            <!-- Change Password (Optional) -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Change Password (Optional)</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label for="current_password" class="block text-gray-700 mb-1">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                        <p class="text-xs text-gray-500 mt-1">Enter current password to verify</p>
                    </div>
                    
                    <div>
                        <label for="new_password" class="block text-gray-700 mb-1">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" minlength="6">
                        <p class="text-xs text-gray-500 mt-1">Minimum 6 characters</p>
                    </div>
                    
                    <div>
                        <label for="confirm_password" class="block text-gray-700 mb-1">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500">
                    </div>
                </div>
            </div>
            
            <div class="flex justify-between items-center">
                <a href="index.php?page=dashboard" class="px-6 py-2 bg-gray-500 text-white rounded-md hover:bg-gray-600 transition duration-300">Cancel</a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Update Profile</button>
            </div>
        </form>
    </div>
</div>