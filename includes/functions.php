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
    // Check for the new session variable
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
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
    $dateObj->setTimezone(new DateTimeZone('Asia/Manila')); // Explicitly set to Manila timezone
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

// Send SMS notification using the new settings-based system
function sendSMS($phoneNumber, $message) {
    global $db;
    
    // Include SMS utility if not already included
    if (!class_exists('SMSUtil')) {
        require_once __DIR__ . '/sms.php';
    }
    
    // Create SMS utility instance
    $sms = new SMSUtil($db);
    
    // Check if SMS is enabled
    if (!$sms->isEnabled()) {
        error_log('SMS not sent: SMS is disabled or not configured.');
        return [
            'success' => false,
            'message' => 'SMS functionality is disabled or not configured'
        ];
    }
    
    // Send the SMS
    return $sms->sendSMS($phoneNumber, $message);
}

// Send a reservation notification SMS
function sendReservationSMS($reservationId, $phoneNumber, $status) {
    global $db;
    
    // Include SMS utility if not already included
    if (!class_exists('SMSUtil')) {
        require_once __DIR__ . '/sms.php';
    }
    
    // Create SMS utility instance
    $sms = new SMSUtil($db);
    
    // Send notification
    return $sms->sendReservationNotification($reservationId, $phoneNumber, $status);
}

// Show 404 page with custom error message
function show404($message = null) {
    // Set the response code to 404
    http_response_code(404);
    
    // Store the error message in session if provided
    if ($message) {
        $_SESSION['error_message'] = $message;
    }
    
    // Include the 404 page
    include 'pages/404.php';
    
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
        
        // Check if the admin ID exists in the admins table
        $stmt = $db->prepare("SELECT id FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminExists = $stmt->fetch();
        
        if (!$adminExists) {
            // If the adminId doesn't exist in admins table, we have a problem
            // Let's use the ID of the first admin user instead
            $stmt = $db->prepare("SELECT id FROM admins LIMIT 1");
            $stmt->execute();
            $adminUser = $stmt->fetch();
            
            if ($adminUser) {
                $adminId = $adminUser['id'];
            } else {
                // This is a critical error - no admins in the system
                $db->rollBack();
                return [false, "System error: No administrator account found."];
            }
        }
        
        // Insert into reservation_status_history with the admin ID in the correct column
        $notes = $notes ?: 'Reservation approved';
        $stmt = $db->prepare("
            INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_admin_id)
            VALUES (?, 'approved', ?, ?)
        ");
        $stmt->execute([$reservationId, $notes, $adminId]);
        
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
                
                // Log this change to history - also using the correct admin_id column
                $stmt = $db->prepare("
                    INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_admin_id)
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
function rejectReservation($db, $reservationId, $adminId, $reason = '') {
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
        
        // Check if reservation can be rejected
        if (!in_array($reservation['status'], ['pending', 'approved'])) {
            return [false, "Only pending or approved reservations can be rejected."];
        }
        
        // Update reservation status
        $stmt = $db->prepare("
            UPDATE reservations 
            SET status = 'rejected' 
            WHERE id = ?
        ");
        $stmt->execute([$reservationId]);
        
        // Check if the admin ID exists in admins table
        $stmt = $db->prepare("SELECT id FROM admins WHERE id = ?");
        $stmt->execute([$adminId]);
        $adminExists = $stmt->fetch();
        
        if (!$adminExists) {
            // If the adminId doesn't exist in admins table, use the first admin instead
            $stmt = $db->prepare("SELECT id FROM admins LIMIT 1");
            $stmt->execute();
            $adminUser = $stmt->fetch();
            
            if ($adminUser) {
                $adminId = $adminUser['id'];
            } else {
                // This is a critical error - no admins in the system
                $db->rollBack();
                return [false, "System error: No administrator account found."];
            }
        }
        
        // Add status history entry - using the correct admin_id column
        $notes = $reason ?: 'Reservation rejected';
        $stmt = $db->prepare("
            INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_admin_id)
            VALUES (?, 'rejected', ?, ?)
        ");
        $stmt->execute([$reservationId, $notes, $adminId]);
        
        // Return any allocated resources to inventory if reservation was previously approved
        if ($reservation['status'] === 'approved') {
            // Get reservation items to update quantities
            $stmt = $db->prepare("
                SELECT ri.*, r.category, r.quantity as current_quantity, r.name as resource_name
                FROM reservation_items ri
                JOIN resources r ON ri.resource_id = r.id
                WHERE ri.reservation_id = ?
            ");
            $stmt->execute([$reservationId]);
            $items = $stmt->fetchAll();
            
            foreach ($items as $item) {
                if ($item['category'] === 'facility') {
                    // Mark facilities as available again
                    $stmt = $db->prepare("
                        UPDATE resources 
                        SET availability = 'available'
                        WHERE id = ?
                    ");
                    $stmt->execute([$item['resource_id']]);
                } else if ($item['category'] === 'equipment') {
                    // Increment quantities
                    $newQuantity = $item['current_quantity'] + (int)$item['quantity'];
                    $stmt = $db->prepare("
                        UPDATE resources 
                        SET quantity = ?,
                            availability = 'available'
                        WHERE id = ?
                    ");
                    $stmt->execute([$newQuantity, $item['resource_id']]);
                    
                    // Log this change to history - also using the correct admin_id column
                    $stmt = $db->prepare("
                        INSERT INTO reservation_status_history (reservation_id, status, notes, created_by_admin_id)
                        VALUES (?, 'equipment_update', ?, ?)
                    ");
                    $stmt->execute([
                        $reservationId, 
                        "Updated {$item['resource_name']} quantity from {$item['current_quantity']} to {$newQuantity} (returned {$item['quantity']})", 
                        $adminId
                    ]);
                }
            }
        }
        
        // Add notification for user
        $userNotificationLink = "index.php?page=view_reservation&id=" . $reservationId; // Link for the user
        $notificationMessage = "Your reservation #$reservationId has been rejected.";
        if (!empty($reason)) {
            $notificationMessage .= " Reason: " . substr($reason, 0, 100);
            if (strlen($reason) > 100) $notificationMessage .= "...";
        }
        createNotification($reservation['user_id'], $notificationMessage, $userNotificationLink);
        
        // Send SMS notification (if configured)
        if (!empty($reservation['contact_number'])) {
            $message = "Your reservation #$reservationId has been rejected.";
            if (!empty($reason)) {
                $message .= " Reason: " . substr($reason, 0, 50);
                if (strlen($reason) > 50) $message .= "...";
            }
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