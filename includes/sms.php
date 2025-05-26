<?php
/**
 * SMS Utility Class
 * 
 * This class handles SMS sending functionality.
 * It uses settings from the database to determine if SMS sending is enabled
 * and includes methods to send SMS messages.
 */
class SMSUtil {
    private $db;
    private $enabled = false;
    private $apiKey = '';
    private $senderId = '';
    private $adminNumber = '';
    private $apiUrl = '';
    private $encryption;
    
    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        
        // Initialize encryption utility
        require_once __DIR__ . '/encryption.php';
        $this->encryption = new EncryptionUtil();
        
        // Default API URL (can be overridden in settings)
        $this->apiUrl = 'https://api.semaphore.co/api/v4/messages';
        
        // Load settings from database
        $this->loadSettings();
    }
    
    /**
     * Load SMS settings from database
     */
    private function loadSettings() {
        try {
            $stmt = $this->db->query("SELECT setting_key, setting_value FROM settings WHERE setting_key IN ('sms_enabled', 'sms_api_key', 'sms_sender_id', 'sms_admin_number', 'sms_api_url')");
            if (!$stmt) {
                error_log("Failed to query settings table");
                return;
            }
            
            $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
            
            // Check if SMS is enabled
            $this->enabled = isset($settings['sms_enabled']) && $settings['sms_enabled'] == '1';
            error_log("SMS Enabled setting: " . ($this->enabled ? 'Yes' : 'No'));
            
            // Handle API key - for Semaphore, we'll use the raw key from the database
            // Skip encryption for this service as Semaphore uses a simple API key
            if (isset($settings['sms_api_key']) && !empty($settings['sms_api_key'])) {
                $this->apiKey = $settings['sms_api_key'];
                error_log("API key loaded: " . substr($this->apiKey, 0, 5) . "...");
            } else {
                error_log("API key not found or empty");
                $this->apiKey = '';
            }
            
            // Set sender ID
            $this->senderId = $settings['sms_sender_id'] ?? '';
            error_log("Sender ID: " . (!empty($this->senderId) ? $this->senderId : 'Not set'));
            
            // Set admin number
            $this->adminNumber = $settings['sms_admin_number'] ?? '';
            error_log("Admin Number: " . (!empty($this->adminNumber) ? $this->adminNumber : 'Not set'));
            
            // Load API URL if set in database
            if (isset($settings['sms_api_url']) && !empty($settings['sms_api_url'])) {
                $this->apiUrl = $settings['sms_api_url'];
                error_log("API URL: " . $this->apiUrl);
            } else {
                error_log("Using default API URL: " . $this->apiUrl);
            }
        } catch (Exception $e) {
            error_log("Error loading SMS settings: " . $e->getMessage());
            $this->enabled = false;
        }
    }
    
    /**
     * Save SMS settings to database
     * 
     * @param array $settings Settings to save
     * @return bool True if settings were saved successfully
     */
    public function saveSettings($settings) {
        try {
            // Start transaction if not already in one
            if (!$this->db->inTransaction()) {
                $this->db->beginTransaction();
            }
            
            $settingsUpdated = false;
            
            foreach ($settings as $key => $value) {
                // For Semaphore API, we don't need to encrypt the API key
                // It's a simple string that can be stored directly
                
                // Check if setting exists
                $checkStmt = $this->db->prepare("SELECT COUNT(*) FROM settings WHERE setting_key = ?");
                $checkStmt->execute([$key]);
                $exists = (int)$checkStmt->fetchColumn() > 0;
                
                if ($exists) {
                    // Update existing setting
                    $stmt = $this->db->prepare("
                        UPDATE settings 
                        SET setting_value = ? 
                        WHERE setting_key = ?
                    ");
                } else {
                    // Insert new setting
                    $stmt = $this->db->prepare("
                        INSERT INTO settings (setting_key, setting_value) 
                        VALUES (?, ?)
                    ");
                }
                
                $stmt->execute([$value, $key]);
                $settingsUpdated = true;
            }
            
            // Only commit if we started the transaction
            if ($settingsUpdated && !$this->db->inTransaction()) {
                $this->db->commit();
            }
            
            // Reload settings
            $this->loadSettings();
            
            return true;
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            
            error_log("Error saving SMS settings: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Check if SMS functionality is enabled
     * 
     * @return bool True if SMS is enabled
     */
    public function isEnabled() {
        if (!$this->enabled) {
            error_log("SMS not enabled in settings");
            return false;
        }
        
        if (empty($this->apiKey)) {
            error_log("SMS API key not configured");
            return false;
        }
        
        if (empty($this->adminNumber)) {
            error_log("SMS admin number not configured");
            return false;
        }
        
        return true;
    }
    
    /**
     * Get the API key (used for validation in settings page)
     * 
     * @return string The API key (decrypted if needed)
     */
    public function getApiKey() {
        return $this->apiKey;
    }
    
    /**
     * Send an SMS message
     * 
     * @param string $to Recipient phone number
     * @param string $message Message content
     * @return array Result of the operation
     */
    public function sendSMS($to, $message) {
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'SMS functionality is disabled or not configured'
            ];
        }
        
        try {
            // Log the SMS attempt
            $this->logSMS($to, $message);
            
            // Use Semaphore API to send the SMS
            $data = [
                'apikey' => $this->apiKey,
                'number' => $to,
                'message' => $message,
            ];
            
            // Add sender ID if available
            if (!empty($this->senderId)) {
                $data['sendername'] = $this->senderId;
            }
            
            // For debugging
            error_log("Sending SMS to: $to with API key: " . substr($this->apiKey, 0, 5) . "...");
            
            // Send the request to Semaphore API
            $ch = curl_init($this->apiUrl);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            // Log raw response for debugging
            error_log("SMS API Response: " . $response);
            
            // Check for curl errors
            if ($error) {
                throw new Exception("cURL Error: " . $error);
            }
            
            // Parse response
            $responseData = json_decode($response, true);
            
            // Check HTTP status code
            if ($httpCode >= 200 && $httpCode < 300) {
                if (isset($responseData['error'])) {
                    throw new Exception("API Error: " . $responseData['error']);
                }
                
                return [
                    'success' => true,
                    'message' => 'SMS sent successfully',
                    'to' => $to,
                    'from' => $this->senderId,
                    'content' => $message,
                    'response' => $responseData
                ];
            } else {
                // API returned an error
                $errorMessage = isset($responseData['message']) ? $responseData['message'] : "API Error (HTTP $httpCode)";
                throw new Exception($errorMessage);
            }
            
        } catch (Exception $e) {
            // Log the error
            error_log("Error sending SMS: " . $e->getMessage());
            
            return [
                'success' => false,
                'message' => 'Failed to send SMS: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Send an SMS to the admin number
     * 
     * @param string $message Message content
     * @return array Result of the operation
     */
    public function sendAdminSMS($message) {
        return $this->sendSMS($this->adminNumber, $message);
    }
    
    /**
     * Send a reservation notification SMS to a user
     * 
     * @param int $reservationId Reservation ID
     * @param string $userPhone User's phone number
     * @param string $status Reservation status
     * @return array Result of the operation
     */
    public function sendReservationNotification($reservationId, $userPhone, $status) {
        // Skip if SMS is disabled
        if (!$this->isEnabled()) {
            return [
                'success' => false,
                'message' => 'SMS functionality is disabled or not configured'
            ];
        }
        
        // Skip if no valid phone number
        if (empty($userPhone)) {
            return [
                'success' => false,
                'message' => 'No valid phone number provided'
            ];
        }
        
        // Create appropriate message based on status
        $message = '';
        switch ($status) {
            case 'pending':
                $message = "BARSERVE: Your reservation #{$reservationId} has been submitted and is pending approval. We'll notify you when it's approved.";
                break;
            case 'approved':
                $message = "BARSERVE: Great news! Your reservation #{$reservationId} has been approved. Please check your account for details.";
                break;
            case 'rejected':
                $message = "BARSERVE: We're sorry, but your reservation #{$reservationId} has been rejected. Please check your account for details.";
                break;
            case 'completed':
                $message = "BARSERVE: Your reservation #{$reservationId} has been marked as completed. Thank you for using our services!";
                break;
            case 'cancelled':
                $message = "BARSERVE: Your reservation #{$reservationId} has been cancelled as requested.";
                break;
            default:
                $message = "BARSERVE: Your reservation #{$reservationId} status has been updated to {$status}.";
        }
        
        // Send the SMS
        return $this->sendSMS($userPhone, $message);
    }
    
    /**
     * Log SMS message to database
     * 
     * @param string $to Recipient phone number
     * @param string $message Message content
     */
    private function logSMS($to, $message) {
        try {
            // Check if sms_logs table exists
            $stmt = $this->db->query("SHOW TABLES LIKE 'sms_logs'");
            if ($stmt->rowCount() == 0) {
                // Create sms_logs table if it doesn't exist
                $this->db->exec("
                    CREATE TABLE IF NOT EXISTS `sms_logs` (
                      `id` int(11) NOT NULL AUTO_INCREMENT,
                      `recipient` varchar(20) NOT NULL,
                      `message` text NOT NULL,
                      `status` varchar(20) NOT NULL DEFAULT 'pending',
                      `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
                      PRIMARY KEY (`id`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                ");
            }
            
            // Insert log entry
            $stmt = $this->db->prepare("
                INSERT INTO sms_logs (recipient, message, status)
                VALUES (?, ?, 'sent')
            ");
            $stmt->execute([$to, $message]);
        } catch (Exception $e) {
            error_log("Error logging SMS: " . $e->getMessage());
        }
    }
}
?> 