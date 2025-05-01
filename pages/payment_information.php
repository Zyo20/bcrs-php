<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = "Please login to make a reservation.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Check if reservation data exists in session
if (!isset($_SESSION['reservation_data'])) {
    $_SESSION['flash_message'] = "No reservation data found. Please start a new reservation.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=reservation");
    exit;
}

// Get reservation data from session
$reservationData = $_SESSION['reservation_data'];
$requiresPayment = $reservationData['requires_payment'] ?? false;
$gymReservation = $reservationData['gym_reservation'] ?? false;
$totalAmount = $reservationData['payment_amount'] ?? 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if this is a skip action (for non-payment reservations)
    if (isset($_POST['skip_payment']) && !$requiresPayment) {
        // Process the reservation directly
        try {
            $db->beginTransaction();
            
            // Extract reservation data
            $userId = $_SESSION['user_id'];
            $landmark = $reservationData['landmark'];
            $address = $reservationData['address'];
            $purok = $reservationData['purok'];
            $startDateTime = $reservationData['start_datetime'];
            $endDateTime = $reservationData['end_datetime'];
            $notes = $reservationData['notes'];
            $resourceItems = $reservationData['resource_items'];
            $resourceQuantities = $reservationData['resource_quantities'];
            
            // Create reservation with 'not_required' payment status
            $stmt = $db->prepare("
                INSERT INTO reservations (
                    user_id, landmark, address, purok, 
                    start_datetime, end_datetime, status, 
                    payment_status, payment_proof, notes
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', 'not_required', NULL, ?)
            ");
            $stmt->execute([
                $userId, $landmark, $address, $purok,
                $startDateTime, $endDateTime, $notes
            ]);
            
            $reservationId = $db->lastInsertId();
            
            // Add reservation items
            foreach ($resourceItems as $resourceId) {
                $quantity = $resourceQuantities[$resourceId] ?? 1;
                
                $stmt = $db->prepare("
                    INSERT INTO reservation_items (reservation_id, resource_id, quantity)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$reservationId, $resourceId, $quantity]);
            }
            
            // Add status history
            $stmt = $db->prepare("
                INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_user_id)
                VALUES (?, 'pending', 'Reservation submitted', ?)
            ");
            
            // Make sure user_id is valid before using it
            if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                throw new Exception("User session is invalid. Please log in again.");
            }
            
            $stmt->execute([$reservationId, $_SESSION['user_id']]);
            
            // Add notification for admin
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, message, link)
                SELECT id, ?, ? FROM users
            ");
            $stmt->execute([
                "New reservation request from {$_SESSION['user_name']}. Reservation ID: $reservationId",
                "index.php?page=admin&section=view_reservation&id=$reservationId"
            ]);
            
            $db->commit();
            
            // Clear reservation data
            unset($_SESSION['reservation_data']);
            
            // Set flash message
            $_SESSION['flash_message'] = "Reservation submitted successfully! Please wait for admin approval.";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to dashboard
            header("Location: index.php?page=dashboard");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_message'] = "Reservation failed: " . $e->getMessage();
            $_SESSION['flash_type'] = "error";
            header("Location: index.php?page=payment_information");
            exit;
        }
    }
    
    // Process payment submission
    $errors = [];
    
    // Check payment proof if required
    if ($requiresPayment) {
        if (empty($_FILES['payment_proof']['name'])) {
            $errors[] = "Payment proof is required";
        } else {
            $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
            if (!in_array($_FILES['payment_proof']['type'], $allowedTypes)) {
                $errors[] = "Payment proof must be JPEG, JPG or PNG format";
            }
        }
    }
    
    // If no errors, process reservation with payment
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Upload payment proof
            $paymentProof = null;
            if ($requiresPayment && !empty($_FILES['payment_proof']['name'])) {
                $paymentProof = uploadFile($_FILES['payment_proof'], 'uploads/payments');
                if (!$paymentProof) {
                    throw new Exception("Failed to upload payment proof");
                }
            }
            
            // Extract reservation data
            $userId = $_SESSION['user_id'];
            $landmark = $reservationData['landmark'];
            $address = $reservationData['address'];
            $purok = $reservationData['purok'];
            $startDateTime = $reservationData['start_datetime'];
            $endDateTime = $reservationData['end_datetime'];
            $notes = $reservationData['notes'];
            $resourceItems = $reservationData['resource_items'];
            $resourceQuantities = $reservationData['resource_quantities'];
            
            // Create reservation
            $paymentStatus = $requiresPayment ? 'pending' : 'not_required';
            
            $stmt = $db->prepare("
                INSERT INTO reservations (
                    user_id, landmark, address, purok, 
                    start_datetime, end_datetime, status, 
                    payment_status, payment_proof, notes
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?)
            ");
            $stmt->execute([
                $userId, $landmark, $address, $purok,
                $startDateTime, $endDateTime, 
                $paymentStatus, $paymentProof, $notes
            ]);
            
            $reservationId = $db->lastInsertId();
            
            // Add reservation items
            foreach ($resourceItems as $resourceId) {
                $quantity = $resourceQuantities[$resourceId] ?? 1;
                
                $stmt = $db->prepare("
                    INSERT INTO reservation_items (reservation_id, resource_id, quantity)
                    VALUES (?, ?, ?)
                ");
                $stmt->execute([$reservationId, $resourceId, $quantity]);
            }
            
            // Add status history
            $stmt = $db->prepare("
                INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_user_id)
                VALUES (?, 'pending', 'Reservation submitted', ?)
            ");
            
            // Make sure user_id is valid before using it
            if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
                throw new Exception("User session is invalid. Please log in again.");
            }
            
            $stmt->execute([$reservationId, $_SESSION['user_id']]);
            
            // Add notification for admin
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, message, link)
                SELECT id, ?, ? FROM users
            ");
            $stmt->execute([
                "New reservation request from {$_SESSION['user_name']}. Reservation ID: $reservationId",
                "index.php?page=admin&section=view_reservation&id=$reservationId"
            ]);
            
            $db->commit();
            
            // Clear reservation data
            unset($_SESSION['reservation_data']);
            
            // Set flash message
            $_SESSION['flash_message'] = "Reservation submitted successfully! Please wait for admin approval.";
            $_SESSION['flash_type'] = "success";
            
            // Redirect to dashboard
            header("Location: index.php?page=dashboard");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            $errors[] = "Reservation failed: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Payment Information</h1>
    
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
        <!-- Reservation Summary -->
        <div class="mb-6">
            <h2 class="text-lg font-semibold text-blue-700 mb-4">Reservation Summary</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <h3 class="font-medium text-gray-700">Location:</h3>
                    <p><?php echo $reservationData['address']; ?>, Purok <?php echo $reservationData['purok']; ?></p>
                    <?php if (!empty($reservationData['landmark'])): ?>
                        <p class="text-sm text-gray-600">Landmark: <?php echo $reservationData['landmark']; ?></p>
                    <?php endif; ?>
                </div>
                <div>
                    <h3 class="font-medium text-gray-700">Schedule:</h3>
                    <p>From: <?php echo formatDate($reservationData['start_datetime']); ?></p>
                    <p>To: <?php echo formatDate($reservationData['end_datetime']); ?></p>
                </div>
            </div>
            
            <div class="mb-4">
                <h3 class="font-medium text-gray-700">Reserved Resources:</h3>
                <ul class="list-disc pl-5">
                    <?php foreach ($reservationData['resource_names'] as $resourceId => $resourceName): ?>
                        <li>
                            <?php echo $resourceName; ?>
                            <?php if (isset($reservationData['resource_quantities'][$resourceId]) && $reservationData['resource_quantities'][$resourceId] > 1): ?>
                                <span class="text-sm text-gray-600">(Quantity: <?php echo $reservationData['resource_quantities'][$resourceId]; ?>)</span>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
            
            <?php if (!empty($reservationData['notes'])): ?>
                <div class="mb-4">
                    <h3 class="font-medium text-gray-700">Additional Notes:</h3>
                    <p class="text-sm text-gray-600"><?php echo $reservationData['notes']; ?></p>
                </div>
            <?php endif; ?>
        </div>
        
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <?php if ($requiresPayment): ?>
                <!-- Payment Section -->
                <div class="mb-6">
                    <h2 class="text-lg font-semibold text-blue-700 mb-4">Payment Information</h2>
                    
                    <div class="bg-blue-50 p-4 rounded mb-4">
                        <div class="flex flex-col md:flex-row items-center">
                            <div class="md:w-1/2 mb-4 md:mb-0 md:mr-4">
                                <h3 class="font-medium text-gray-800 mb-2">Pay via GCash:</h3>
                                <p class="text-sm text-gray-600 mb-2">Please send the payment to the following GCash account:</p>
                                <p class="font-medium">Name: Barangay Resource Management</p>
                                <p class="font-medium">Number: 09XX-XXX-XXXX</p>
                                <p class="text-sm text-gray-600 mt-2">Amount: ₱<?php echo number_format($totalAmount, 2); ?></p>
                            </div>
                            
                            <div class="md:w-1/2 text-center">
                                <div class="bg-white p-2 inline-block rounded">
                                    <img src="includes/qr.jpg" alt="GCash QR Code" class="w-40 h-40 object-cover">
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-6">
                        <label for="payment_proof" class="block text-gray-700 mb-1">Upload Payment Proof *</label>
                        <input type="file" id="payment_proof" name="payment_proof" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" accept="image/jpeg,image/jpg,image/png" required>
                        <p class="text-xs text-gray-500 mt-1">Upload a screenshot of your payment receipt (JPEG, JPG, or PNG format)</p>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Complete Reservation</button>
                </div>
            <?php else: ?>
                <!-- No payment required message -->
                <div class="mb-6">
                    <div class="bg-green-50 p-4 rounded text-center">
                        <p class="text-green-700">No payment is required for this reservation. Click the button below to complete your reservation.</p>
                    </div>
                </div>
                
                <div class="text-center">
                    <button type="submit" name="skip_payment" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Complete Reservation</button>
                </div>
            <?php endif; ?>
        </form>
    </div>
</div>