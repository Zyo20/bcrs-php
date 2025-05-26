<?php
// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Enable error reporting for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load the SMS utility
require_once 'includes/sms.php';
$smsUtil = new SMSUtil($db);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Start transaction
        $db->beginTransaction();
        
        // Collect all SMS settings into an array
        $smsSettings = [];
        
        // Set SMS enabled status
        $smsSettings['sms_enabled'] = isset($_POST['sms_enabled']) ? '1' : '0';
        
        // Set API key if provided (will be encrypted by SMSUtil)
        if (isset($_POST['sms_api_key']) && !empty($_POST['sms_api_key'])) {
            $smsSettings['sms_api_key'] = $_POST['sms_api_key'];
        }
        
        // Set API URL if provided
        if (isset($_POST['sms_api_url']) && !empty($_POST['sms_api_url'])) {
            $smsSettings['sms_api_url'] = $_POST['sms_api_url'];
        }
        
        // Set sender ID if provided
        if (isset($_POST['sms_sender_id']) && !empty($_POST['sms_sender_id'])) {
            $smsSettings['sms_sender_id'] = $_POST['sms_sender_id'];
        }
        
        // Set admin number if provided
        if (isset($_POST['sms_admin_number']) && !empty($_POST['sms_admin_number'])) {
            // Ensure the phone number is formatted correctly (remove any spaces, dashes, etc.)
            $adminNumber = preg_replace('/[^0-9]/', '', $_POST['sms_admin_number']);
            // Ensure it starts with 09 for Philippines format
            if (!preg_match('/^09\d{9}$/', $adminNumber)) {
                throw new Exception("Admin phone number must be in Philippines format (09XXXXXXXXX)");
            }
            $smsSettings['sms_admin_number'] = $adminNumber;
        }
        
        // Validate API key if SMS is enabled
        if ($smsSettings['sms_enabled'] === '1') {
            if (empty($smsSettings['sms_api_key'] ?? '')) {
                $existingApiKey = $smsUtil->getApiKey();
                if (empty($existingApiKey)) {
                    throw new Exception("API key is required when SMS is enabled");
                }
            }
            
            if (empty($smsSettings['sms_admin_number'] ?? '')) {
                throw new Exception("Admin phone number is required when SMS is enabled");
            }
        }
        
        // Use the SMSUtil to save settings with encryption
        $success = $smsUtil->saveSettings($smsSettings);
        
        if ($success) {
            $db->commit();
            setFlashMessage("Settings updated successfully", "success");
            
            // If SMS is enabled, check if it's working
            if ($smsSettings['sms_enabled'] === '1') {
                $testResult = $smsUtil->isEnabled();
                if (!$testResult) {
                    setFlashMessage("Settings saved, but SMS still appears to be not properly configured. Please check your settings.", "warning");
                }
            }
        } else {
            throw new Exception("Failed to save settings");
        }
        
        // Redirect to prevent form resubmission
        header("Location: index.php?page=admin&section=settings");
        exit;
    } catch (Exception $e) {
        // Rollback transaction on error
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        setFlashMessage("Error: " . $e->getMessage(), "error");
        
        // Redirect to prevent form resubmission
        header("Location: index.php?page=admin&section=settings");
        exit;
    }
}

// Get current settings
try {
    $stmt = $db->query("SELECT setting_key, setting_value FROM settings");
    $settingsData = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    // Set default values if not set
    $smsEnabled = isset($settingsData['sms_enabled']) ? (bool)$settingsData['sms_enabled'] : false;
    $smsApiKey = $settingsData['sms_api_key'] ?? '';
    $smsApiUrl = $settingsData['sms_api_url'] ?? '';
    $smsSenderId = $settingsData['sms_sender_id'] ?? 'BCRS';
    $smsAdminNumber = $settingsData['sms_admin_number'] ?? '';
    
} catch (Exception $e) {
    // Log the error
    error_log("Settings load error: " . $e->getMessage());
    $errorMessage = "Error loading settings: " . $e->getMessage();
}

// Process flash messages
$flashMessage = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flashType = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';

// Clear flash message after retrieving it
if (isset($_SESSION['flash_message'])) {
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">System Settings</h1>
        <a href="index.php?page=admin&section=dashboard" class="bg-gray-500 hover:bg-gray-600 text-white py-2 px-4 rounded">
            Back to Dashboard
        </a>
    </div>
    
    <?php if (!empty($flashMessage)): ?>
        <div class="bg-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-100 border-l-4 border-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <?php if ($flashType === 'error'): ?>
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    <?php else: ?>
                        <svg class="h-5 w-5 text-green-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                        </svg>
                    <?php endif; ?>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-700"><?php echo $flashMessage; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if (isset($errorMessage)): ?>
        <div class="bg-red-100 border-l-4 border-red-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <p class="text-sm text-red-700"><?php echo $errorMessage; ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="bg-white shadow-md rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold mb-4">SMS Notification Settings</h2>
        
        <form method="POST" action="">
            <div class="mb-6">
                <div class="flex items-center justify-between p-4 bg-blue-50 rounded-lg mb-4">
                    <div>
                        <h3 class="font-medium text-blue-800">SMS Notifications</h3>
                        <p class="text-sm text-gray-600">Enable or disable SMS notifications for reservations and important updates</p>
                    </div>
                    
                    <label class="relative inline-flex items-center cursor-pointer">
                        <input type="checkbox" name="sms_enabled" value="1" class="sr-only peer" <?php echo $smsEnabled ? 'checked' : ''; ?>>
                        <div class="w-11 h-6 bg-gray-200 peer-focus:outline-none peer-focus:ring-4 peer-focus:ring-blue-300 rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-gray-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-blue-600"></div>
                    </label>
                </div>
                
                <div class="border border-gray-200 rounded-lg p-4">
                    <div class="mb-4">
                        <label for="sms_api_key" class="block text-sm font-medium text-gray-700 mb-1">SMS API Key <span class="text-xs text-green-600">(Will be encrypted)</span></label>
                        <input type="password" id="sms_api_key" name="sms_api_key" placeholder="Enter new API key" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Your SMS gateway API key (e.g., Twilio, Nexmo, etc.) - Leave blank to keep current value</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="sms_api_url" class="block text-sm font-medium text-gray-700 mb-1">API URL</label>
                        <input type="text" id="sms_api_url" name="sms_api_url" value="<?php echo htmlspecialchars($smsApiUrl); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">The base URL for the SMS API service</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="sms_sender_id" class="block text-sm font-medium text-gray-700 mb-1">Sender ID</label>
                        <input type="text" id="sms_sender_id" name="sms_sender_id" value="<?php echo htmlspecialchars($smsSenderId); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">The sender name that appears on SMS messages (e.g., BCRS)</p>
                    </div>
                    
                    <div class="mb-4">
                        <label for="sms_admin_number" class="block text-sm font-medium text-gray-700 mb-1">Admin Phone Number</label>
                        <input type="text" id="sms_admin_number" name="sms_admin_number" value="<?php echo htmlspecialchars($smsAdminNumber); ?>" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                        <p class="mt-1 text-xs text-gray-500">Phone number to receive admin notifications (with country code, e.g., +639171234567)</p>
                    </div>
                    
                    <div class="mt-2">
                        <button type="button" id="test_sms" class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500" <?php echo !$smsEnabled ? 'disabled' : ''; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8" />
                            </svg>
                            Test SMS
                        </button>
                        <span id="test_result" class="ml-2 text-sm"></span>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end">
                <button type="submit" class="inline-flex justify-center py-2 px-4 border border-transparent shadow-sm text-sm font-medium rounded-md text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                    Save Settings
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Toggle SMS settings fields based on checkbox
    document.addEventListener('DOMContentLoaded', function() {
        const smsEnabledCheckbox = document.querySelector('input[name="sms_enabled"]');
        const testSmsButton = document.getElementById('test_sms');
        
        function updateFieldsState() {
            const enabled = smsEnabledCheckbox.checked;
            testSmsButton.disabled = !enabled;
        }
        
        // Initial state
        updateFieldsState();
        
        // Update on change
        smsEnabledCheckbox.addEventListener('change', updateFieldsState);
        
        // Handle test SMS button
        testSmsButton.addEventListener('click', function() {
            const testResult = document.getElementById('test_result');
            testResult.textContent = 'Sending test message...';
            testResult.classList.remove('text-red-600', 'text-green-600');
            
            fetch('pages/admin/test_sms.php')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        testResult.textContent = 'Test message sent successfully!';
                        testResult.classList.add('text-green-600');
                    } else {
                        testResult.textContent = 'Error: ' + data.message;
                        testResult.classList.add('text-red-600');
                    }
                })
                .catch(error => {
                    testResult.textContent = 'Error: ' + error.message;
                    testResult.classList.add('text-red-600');
                });
        });
    });
</script> 