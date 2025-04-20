<?php
// Initialize variables for form handling
$errors = [];
$name = '';
$description = '';
$category = 'equipment';
$quantity = 1;
$availability = 'available';
$requires_payment = 0;
$payment_amount = 0.00;

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // IMPORTANT DEBUGGING
    // Log entire $_POST array to server logs
    error_log("======= RESOURCE ADDITION DEBUG START =======");
    error_log("RAW POST data: " . print_r($_POST, true));
    
    // Sanitize and validate input - be very explicit about type casting
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $category = $_POST['category'] ?? 'equipment';
    $quantity = filter_var($_POST['quantity'] ?? 1, FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
    $availability = $_POST['availability'] ?? 'available';
    $requires_payment = isset($_POST['requires_payment']) ? 1 : 0;
    $payment_amount = isset($_POST['payment_amount']) ? (float)$_POST['payment_amount'] : 0.00;
    
    // Log processed variables
    error_log("Processed quantity (after filter_var): " . var_export($quantity, true));
    
    // Validate name
    if (empty($name)) {
        $errors['name'] = 'Resource name is required';
    } elseif (strlen($name) > 100) {
        $errors['name'] = 'Resource name cannot exceed 100 characters';
    }
    
    // Validate description
    if (empty($description)) {
        $errors['description'] = 'Description is required';
    }
    
    // Validate category
    if (!in_array($category, ['facility', 'equipment'])) {
        $errors['category'] = 'Invalid category selected';
    }
    
    // Validate quantity - extra verification
    if ($quantity === false || $quantity < 1) {
        $quantity = 1; // Safety default
        $errors['quantity'] = 'Invalid quantity value, must be at least 1';
        error_log("Quantity validation failed - reset to 1");
    } elseif ($quantity > 1000) {
        $errors['quantity'] = 'Quantity cannot exceed 1000';
    }
    
    // Validate availability
    if (!in_array($availability, ['available', 'reserved', 'maintenance'])) {
        $errors['availability'] = 'Invalid availability status';
    }
    
    // Validate payment amount if payment is required
    if ($requires_payment && $payment_amount <= 0) {
        $errors['payment_amount'] = 'Payment amount must be greater than zero if payment is required';
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            // IMPORTANT: Cast quantity to int one more time for certainty
            $quantity = (int)$quantity;
            error_log("Final quantity value (before DB insert): $quantity");
            
            // Use a transaction to ensure data integrity
            $db->beginTransaction();
            
            // Verify quantity is correctly passed in the bind parameters
            $stmt = $db->prepare("
                INSERT INTO resources (name, description, category, quantity, availability, requires_payment, payment_amount, status, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
            ");
            
            // Explicitly bind each parameter
            $stmt->bindParam(1, $name, PDO::PARAM_STR);
            $stmt->bindParam(2, $description, PDO::PARAM_STR);
            $stmt->bindParam(3, $category, PDO::PARAM_STR);
            $stmt->bindParam(4, $quantity, PDO::PARAM_INT);  // Ensure quantity is bound as INT
            $stmt->bindParam(5, $availability, PDO::PARAM_STR);
            $stmt->bindParam(6, $requires_payment, PDO::PARAM_INT);
            $stmt->bindParam(7, $payment_amount, PDO::PARAM_STR);
            
            // Execute and check result
            $result = $stmt->execute();
            
            if ($result) {
                // Get the ID of the inserted resource
                $resourceId = $db->lastInsertId();
                
                // Double verify the quantity was saved correctly
                $verifyStmt = $db->prepare("SELECT quantity FROM resources WHERE id = ?");
                $verifyStmt->execute([$resourceId]);
                $savedQuantity = $verifyStmt->fetchColumn();
                
                error_log("Resource added. ID: $resourceId, Requested quantity: $quantity, Saved quantity: $savedQuantity");
                
                // If quantities don't match, try to update it again
                if ($savedQuantity != $quantity) {
                    error_log("QUANTITY MISMATCH DETECTED! Attempting fix...");
                    $fixStmt = $db->prepare("UPDATE resources SET quantity = ? WHERE id = ?");
                    $fixStmt->execute([$quantity, $resourceId]);
                    
                    // Verify the fix worked
                    $verifyStmt->execute([$resourceId]);
                    $fixedQuantity = $verifyStmt->fetchColumn();
                    error_log("After fix attempt: Quantity now $fixedQuantity");
                }
                
                // Add detailed log
                $logMsg = "Resource added successfully. Name: $name, Category: $category, Quantity: $quantity";
                error_log($logMsg);
                
                // Write to admin_logs if the table exists
                try {
                    $adminLogStmt = $db->prepare("
                        INSERT INTO admin_logs (action, details, user_id, created_at)
                        VALUES (?, ?, ?, NOW())
                    ");
                    $adminLogStmt->execute(['add_resource', $logMsg, $_SESSION['user_id']]);
                } catch (Exception $logEx) {
                    // Just log the error, don't interrupt the process
                    error_log("Could not write to admin_logs: " . $logEx->getMessage());
                }
                
                $db->commit();
                
                // Success - set flash message and redirect
                $_SESSION['flash_message'] = "Resource '{$name}' added successfully with quantity: $quantity!";
                $_SESSION['flash_type'] = 'success';
                
                // Redirect to resources page
                header('Location: index.php?page=admin&section=resources');
                exit;
            } else {
                $db->rollBack();
                $errors['db'] = 'Failed to add resource';
                error_log("ERROR: Failed to execute resource insert statement");
            }
        } catch (PDOException $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors['db'] = 'Database error: ' . $e->getMessage();
            error_log("PDO ERROR: " . $e->getMessage());
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors['db'] = 'Error: ' . $e->getMessage();
            error_log("GENERAL ERROR: " . $e->getMessage());
        }
        
        error_log("======= RESOURCE ADDITION DEBUG END =======");
    }
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex items-center justify-between mb-8">
        <h1 class="text-3xl font-bold text-blue-800">Add New Resource</h1>
        <a href="index.php?page=admin&section=resources" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 17l-5-5m0 0l5-5m-5 5h12" />
            </svg>
            Back to Resources
        </a>
    </div>

    <?php if (!empty($errors['db'])): ?>
        <div class="bg-red-50 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?php echo $errors['db']; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <div class="bg-white shadow overflow-hidden sm:rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <form action="index.php?page=admin&section=add_resource" method="POST" class="space-y-6">
                <!-- Resource Name -->
                <div>
                    <label for="name" class="block text-sm font-medium text-gray-700">Resource Name <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($name); ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md <?php echo isset($errors['name']) ? 'border-red-500' : ''; ?>" required>
                    </div>
                    <?php if (isset($errors['name'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['name']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Resource Description -->
                <div>
                    <label for="description" class="block text-sm font-medium text-gray-700">Description <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <textarea name="description" id="description" rows="4" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md <?php echo isset($errors['description']) ? 'border-red-500' : ''; ?>" required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Provide detailed information about this resource.</p>
                    <?php if (isset($errors['description'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['description']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Resource Category -->
                <div>
                    <label for="category" class="block text-sm font-medium text-gray-700">Category <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <select name="category" id="category" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md <?php echo isset($errors['category']) ? 'border-red-500' : ''; ?>">
                            <option value="facility" <?php echo $category === 'facility' ? 'selected' : ''; ?>>Facility</option>
                            <option value="equipment" <?php echo $category === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                        </select>
                    </div>
                    <?php if (isset($errors['category'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['category']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Resource Quantity -->
                <div>
                    <label for="quantity" class="block text-sm font-medium text-gray-700">Quantity <span class="text-red-500">*</span></label>
                    <div class="mt-1">
                        <input type="number" name="quantity" id="quantity" min="1" max="100" value="<?php echo $quantity; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md <?php echo isset($errors['quantity']) ? 'border-red-500' : ''; ?>">
                    </div>
                    <p class="mt-1 text-sm text-gray-500">How many of this resource are available?</p>
                    <?php if (isset($errors['quantity'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['quantity']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Resource Availability -->
                <div>
                    <label class="block text-sm font-medium text-gray-700">Availability Status <span class="text-red-500">*</span></label>
                    <div class="mt-2 space-y-4 sm:flex sm:items-center sm:space-y-0 sm:space-x-10">
                        <div class="flex items-center">
                            <input id="available" name="availability" type="radio" value="available" <?php echo $availability === 'available' ? 'checked' : ''; ?> class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                            <label for="available" class="ml-3 block text-sm font-medium text-gray-700">
                                Available
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input id="reserved" name="availability" type="radio" value="reserved" <?php echo $availability === 'reserved' ? 'checked' : ''; ?> class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                            <label for="reserved" class="ml-3 block text-sm font-medium text-gray-700">
                                Reserved
                            </label>
                        </div>
                        <div class="flex items-center">
                            <input id="maintenance" name="availability" type="radio" value="maintenance" <?php echo $availability === 'maintenance' ? 'checked' : ''; ?> class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300">
                            <label for="maintenance" class="ml-3 block text-sm font-medium text-gray-700">
                                Maintenance
                            </label>
                        </div>
                    </div>
                    <?php if (isset($errors['availability'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['availability']; ?></p>
                    <?php endif; ?>
                </div>

                <!-- Requires Payment -->
                <div>
                    <label for="requires_payment" class="block text-sm font-medium text-gray-700">Requires Payment</label>
                    <div class="mt-1 flex items-center">
                        <input type="checkbox" name="requires_payment" id="requires_payment" value="1" <?php echo $requires_payment ? 'checked' : ''; ?> class="focus:ring-blue-500 h-4 w-4 text-blue-600 border-gray-300 rounded">
                        <label for="requires_payment" class="ml-3 block text-sm text-gray-700">Check if this resource requires payment</label>
                    </div>
                </div>

                <!-- Payment Amount -->
                <div>
                    <label for="payment_amount" class="block text-sm font-medium text-gray-700">Payment Amount</label>
                    <div class="mt-1">
                        <input type="number" name="payment_amount" id="payment_amount" step="0.01" min="0" value="<?php echo $payment_amount; ?>" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md <?php echo isset($errors['payment_amount']) ? 'border-red-500' : ''; ?>">
                    </div>
                    <p class="mt-1 text-sm text-gray-500">Specify the payment amount if payment is required.</p>
                    <?php if (isset($errors['payment_amount'])): ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo $errors['payment_amount']; ?></p>
                    <?php endif; ?>
                </div>

                <div class="pt-5">
                    <div class="flex justify-end">
                        <a href="index.php?page=admin&section=resources" class="bg-white py-2 px-4 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            Cancel
                        </a>
                        <button type="submit" class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                            </svg>
                            Add Resource
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>