<?php
// Helper Functions

// Sanitize input
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin';
}

// Redirect function
function redirect($location) {
    // Remove .php extension if it exists in the URL
    $location = preg_replace('/\.php(\?|$)/', '$1', $location);
    
    header("Location: " . $location);
    exit;
}

// Generate a CSRF token
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Verify a CSRF token
function verifyCSRFToken($token) {
    if (!isset($_SESSION['csrf_token']) || $token !== $_SESSION['csrf_token']) {
        return false;
    }
    return true;
}

// Format date for display
function formatDate($date, $format = 'd M Y, h:i A') {
    $dateObj = new DateTime($date);
    return $dateObj->format($format);
}

// Upload file
function uploadFile($file, $uploadDir, $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg']) {
    // Check if upload directory exists, if not create it
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }
    
    // Check if file was uploaded without errors
    if ($file['error'] == 0) {
        // Check file type
        if (in_array($file['type'], $allowedTypes)) {
            // Generate unique filename
            $newFilename = uniqid() . '_' . basename($file['name']);
            $destination = $uploadDir . '/' . $newFilename;
            
            // Move the file
            if (move_uploaded_file($file['tmp_name'], $destination)) {
                return $newFilename;
            }
        }
    }
    
    return false;
}

// Send SMS notification using Twilio API
function sendSMS($phoneNumber, $message) {
    // Load SMS configuration - using include instead of require_once to get the actual array
    $configPath = __DIR__ . '/../config/sms.php';
    
    // Check if config file exists
    if (!file_exists($configPath)) {
        error_log("SMS config file not found: $configPath");
        return false;
    }
    
    // Load config file and store the returned array
    $smsConfig = include $configPath;
    
    // Verify config is an array
    if (!is_array($smsConfig)) {
        error_log("SMS configuration is not valid. Check that config/sms.php returns an array.");
        return false;
    }
    
    // Skip if SMS is disabled or credentials are not configured
    if (!isset($smsConfig['enabled']) || 
        !$smsConfig['enabled'] || 
        empty($smsConfig['account_sid']) || 
        empty($smsConfig['auth_token']) || 
        empty($smsConfig['from_number'])) {
        error_log('SMS not sent: SMS is disabled or Twilio credentials are not configured.');
        return false;
    }
    
    // Format phone number with international format for Twilio
    // Philippines phone numbers should be in format: +63XXXXXXXXXX
    if (substr($phoneNumber, 0, 1) === '0') {
        // Remove leading 0 and add Philippines country code +63
        $phoneNumber = '+63' . substr($phoneNumber, 1);
    } elseif (substr($phoneNumber, 0, 1) !== '+') {
        // If number doesn't start with +, add +63
        $phoneNumber = '+63' . $phoneNumber;
    }
    
    // Remove any spaces or special characters
    $phoneNumber = preg_replace('/[^0-9+]/', '', $phoneNumber);
    
    // Prepare the API endpoint
    $url = $smsConfig['api_url'] . $smsConfig['account_sid'] . '/Messages.json';
    
    // Prepare the request data
    $data = [
        'To' => $phoneNumber,
        'From' => $smsConfig['from_number'],
        'Body' => $message
    ];
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Set up Basic Authentication with Account SID and Auth Token
    curl_setopt($ch, CURLOPT_USERPWD, $smsConfig['account_sid'] . ':' . $smsConfig['auth_token']);
    
    // Add SSL verification options
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // Execute request and get response
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Debug information
    error_log("Twilio API call to: $url");
    error_log("Phone number formatted as: $phoneNumber");
    error_log("HTTP Status Code: $httpCode");
    if ($err) {
        error_log("cURL Error: $err");
    }
    
    curl_close($ch);
    
    // Log results and return status
    if ($err) {
        error_log("SMS sending failed: " . $err);
        return false;
    } else {
        error_log("Raw API response: " . $response);
        $result = json_decode($response, true);
        
        // Twilio returns 201 Created on success
        if ($httpCode === 201 && isset($result['sid'])) {
            error_log("SMS sent successfully to $phoneNumber. Twilio SID: " . $result['sid']);
            return true;
        } else {
            $errorMsg = isset($result['message']) ? $result['message'] : 'Unknown error';
            error_log("SMS sending failed. Twilio error: " . $errorMsg);
            return false;
        }
    }
}

// Show 404 page with custom error message
function show404($message = null) {
    global $db; // Make the database connection available
    
    // Set the HTTP status code to 404
    http_response_code(404);
    
    // Store the error message in session if provided
    if ($message) {
        $_SESSION['error_message'] = $message;
    }
    
    // Include header
    include 'includes/header.php';
    
    // Include the 404 page
    include 'pages/404.php';
    
    // Include footer
    include 'includes/footer.php';
    
    // Stop execution
    exit;
}

// Set a flash message to be displayed on the next page load
function setFlashMessage($message, $type = 'success') {
    $_SESSION['flash_message'] = $message;
    $_SESSION['flash_type'] = $type;
}

// Create a notification that's stored as a flash message
// This function replaces the database notifications with flash messages
function createNotification($userId, $message, $link = null) {
    // Since we're using flash messages instead of DB notifications,
    // we'll store the message in the session if this is for the current user
    if (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $userId) {
        $_SESSION['flash_message'] = $message;
        $_SESSION['flash_type'] = 'info';
        
        // If there's a link, we could potentially store it as well
        if ($link) {
            $_SESSION['flash_link'] = $link;
        }
    }
    
    // Return true to indicate success (for compatibility with any code that expects a return value)
    return true;
}

// Replace usage of the old notifications table with flash messages
function markAllNotificationsRead() {
    // For flash messages, this is a no-op since they're automatically cleared after being shown
    return true;
}

// Approve a reservation
function approveReservation($db, $reservationId, $adminId, $notes = '') {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get reservation details
        $stmt = $db->prepare("
            SELECT r.*, u.contact_number 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            return [false, "Reservation not found."];
        }
        
        // Check if reservation is in pending status
        if ($reservation['status'] !== 'pending') {
            return [false, "Only pending reservations can be approved."];
        }
        
        // Check if payment is required but not yet confirmed
        if ($reservation['payment_status'] === 'pending') {
            return [false, "Payment must be confirmed before approving this reservation."];
        }
        
        // Get reservation items to update quantities
        $stmt = $db->prepare("
            SELECT ri.*, r.category, r.quantity as available_quantity, r.name as resource_name
            FROM reservation_items ri
            JOIN resources r ON ri.resource_id = r.id
            WHERE ri.reservation_id = ?
        ");
        $stmt->execute([$reservationId]);
        $items = $stmt->fetchAll();
        
        // Log quantity deductions for debugging
        $deductionLog = [];
        
        // Validate that all equipment items have sufficient quantity
        foreach ($items as $item) {
            if ($item['category'] === 'equipment' && $item['quantity'] > $item['available_quantity']) {
                $db->rollBack();
                return [false, "Insufficient quantity available for {$item['resource_name']} (requested: {$item['quantity']}, available: {$item['available_quantity']})"];
            }
            $deductionLog[] = "Item: {$item['resource_name']}, Category: {$item['category']}, Quantity to deduct: {$item['quantity']}";
        }
        
        // Update reservation status
        $stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'approved' 
            WHERE id = ?
        ");
        $stmt->execute([$reservationId]);
        
        // Add status history entry with quantity debug info
        $debugNotes = $notes ?: 'Reservation approved';
        if (!empty($deductionLog)) {
            $debugNotes .= "\nQuantity changes: " . implode("; ", $deductionLog);
        }
        
        $stmt = $db->prepare("
            INSERT INTO reservation_status_history (reservation_id, status, notes, created_by)
            VALUES (?, 'approved', ?, ?)
        ");
        $stmt->execute([$reservationId, $debugNotes, $adminId]);
        
        // Update resource availability and quantities
        foreach ($items as $item) {
            if ($item['category'] === 'facility') {
                // Mark facilities as reserved
                $stmt = $db->prepare("
                    UPDATE resources 
                    SET availability = 'reserved'
                    WHERE id = ?
                ");
                $stmt->execute([$item['resource_id']]);
            } else if ($item['category'] === 'equipment') {
                // Get current quantity first for verification
                $stmt = $db->prepare("SELECT quantity FROM resources WHERE id = ?");
                $stmt->execute([$item['resource_id']]);
                $currentQty = $stmt->fetchColumn();
                
                // Calculate new quantity
                $newQty = max(0, $currentQty - (int)$item['quantity']);
                
                // Directly set the new quantity instead of decrementing
                $stmt = $db->prepare("
                    UPDATE resources 
                    SET quantity = ?,
                        availability = CASE WHEN ? <= 0 THEN 'reserved' ELSE availability END
                    WHERE id = ?
                ");
                $stmt->execute([$newQty, $newQty, $item['resource_id']]);
                
                // Log this change to history
                $stmt = $db->prepare("
                    INSERT INTO reservation_status_history (reservation_id, status, notes, created_by)
                    VALUES (?, 'equipment_update', ?, ?)
                ");
                $stmt->execute([
                    $reservationId, 
                    "Updated {$item['resource_name']} quantity from {$currentQty} to {$newQty} (deducted {$item['quantity']})", 
                    $adminId
                ]);
            }
        }
        
        // Add notification for user
        $userNotificationLink = "index.php?page=view_reservation&id=" . $reservationId; // Link for the user
        createNotification($reservation['user_id'], "Your reservation #$reservationId has been approved.", $userNotificationLink);
        
        // Send SMS notification (if configured)
        if (!empty($reservation['contact_number'])) {
            $message = "Your reservation #$reservationId has been approved. You can view details on your dashboard.";
            sendSMS($reservation['contact_number'], $message);
        }
        
        $db->commit();
        return [true, "Reservation has been successfully approved."];
        
    } catch (PDOException $e) {
        $db->rollBack();
        return [false, "Error: " . $e->getMessage()];
    }
}

// Reject a reservation
function rejectReservation($db, $reservationId, $adminId, $notes = '') {
    try {
        // Begin transaction
        $db->beginTransaction();
        
        // Get reservation details
        $stmt = $db->prepare("
            SELECT r.*, u.contact_number 
            FROM reservations r
            JOIN users u ON r.user_id = u.id
            WHERE r.id = ?
        ");
        $stmt->execute([$reservationId]);
        $reservation = $stmt->fetch();
        
        if (!$reservation) {
            return [false, "Reservation not found."];
        }
        
        // Check if reservation is in pending status
        if ($reservation['status'] !== 'pending') {
            return [false, "Only pending reservations can be rejected."];
        }
        
        // Update reservation status
        $stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'cancelled' 
            WHERE id = ?
        ");
        $stmt->execute([$reservationId]);
        
        // Add status history entry
        $stmt = $db->prepare("
            INSERT INTO reservation_status_history (reservation_id, status, notes, created_by)
            VALUES (?, 'cancelled', ?, ?)
        ");
        $stmt->execute([$reservationId, $notes ?: 'Reservation rejected by admin', $adminId]);
        
        // Add notification for user
        $userNotificationLink = "index.php?page=view_reservation&id=" . $reservationId; // Link for the user
        // Include the rejection reason in the message for clarity
        $rejectionMessage = "Your reservation #$reservationId has been rejected.";
        if (!empty($notes)) {
            $rejectionMessage .= " Reason: " . htmlspecialchars($notes);
        }
        createNotification($reservation['user_id'], $rejectionMessage, $userNotificationLink);
        
        // Send SMS notification (if configured)
        if (!empty($reservation['contact_number'])) {
            $message = "Your reservation #$reservationId has been rejected. Please check your dashboard for details.";
            sendSMS($reservation['contact_number'], $message);
        }
        
        $db->commit();
        return [true, "Reservation has been successfully rejected."];
        
    } catch (PDOException $e) {
        $db->rollBack();
        return [false, "Error: " . $e->getMessage()];
    }
}
?>