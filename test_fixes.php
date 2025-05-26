<?php
/**
 * Test File for BCRS-PHP Fixes
 * 
 * This file documents the fixes applied to the BCRS-PHP system.
 * Two main issues were addressed:
 * 
 * 1. Resource Deduction Issue - Fixed to only deduct resources after approval
 * 2. SMS Registration Approval - Added SMS notifications for user registration approval
 */

// Include database configuration and functions
require_once 'config/database.php';
require_once 'includes/functions.php';

/**
 * Test 1: Resource Availability Calculation
 * 
 * This test verifies that pending reservations don't affect resource availability
 */
function testResourceAvailability($db) {
    echo "<h2>Test 1: Resource Availability Calculation</h2>\n";
    
    try {
        // Query from pages/reservation.php (modified version)
        $stmt = $db->prepare("
            SELECT r.name, r.quantity,
                CASE 
                    WHEN r.category = 'equipment' THEN 
                        r.quantity - IFNULL((
                            SELECT SUM(ri.quantity) 
                            FROM reservation_items ri 
                            JOIN reservations res ON ri.reservation_id = res.id 
                            WHERE ri.resource_id = r.id AND res.status IN ('approved', 'for_delivery', 'for_pickup')
                        ), 0) 
                    ELSE r.quantity 
                END as available_quantity
            FROM resources r
            WHERE r.status = 'active' AND r.availability = 'available' AND r.category = 'equipment'
            ORDER BY r.name
        ");
        $stmt->execute();
        $resources = $stmt->fetchAll();
        
        echo "<table border='1' style='border-collapse: collapse; margin: 10px;'>\n";
        echo "<tr><th>Resource Name</th><th>Total Quantity</th><th>Available Quantity</th><th>Status</th></tr>\n";
        
        foreach ($resources as $resource) {
            $status = ($resource['available_quantity'] == $resource['quantity']) ? 
                "✅ No approved reservations" : 
                "⚠️ Has approved reservations";
                
            echo "<tr>";
            echo "<td>{$resource['name']}</td>";
            echo "<td>{$resource['quantity']}</td>";
            echo "<td>{$resource['available_quantity']}</td>";
            echo "<td>$status</td>";
            echo "</tr>\n";
        }
        echo "</table>\n";
        
        echo "<p><strong>Expected:</strong> Available quantity should only be affected by 'approved', 'for_delivery', or 'for_pickup' reservations, NOT 'pending' ones.</p>\n";
        
    } catch (Exception $e) {
        echo "<p style='color: red;'>Error: " . $e->getMessage() . "</p>\n";
    }
}

/**
 * Test 2: SMS Function Availability
 * 
 * This test verifies that SMS functionality is available and configured
 */
function testSMSFunctionality() {
    echo "<h2>Test 2: SMS Functionality</h2>\n";
    
    // Check if SMS functions exist
    $smsAvailable = function_exists('sendSMS');
    $smsUtilExists = class_exists('SMSUtil');
    
    echo "<ul>\n";
    echo "<li>sendSMS() function available: " . ($smsAvailable ? "✅ Yes" : "❌ No") . "</li>\n";
    echo "<li>SMSUtil class available: " . ($smsUtilExists ? "✅ Yes" : "❌ No") . "</li>\n";
    echo "</ul>\n";
    
    if ($smsAvailable) {
        echo "<p><strong>SMS Function Usage for Registration Approval:</strong></p>\n";
        echo "<code>\n";
        echo "// Example usage in admin approval:\n";
        echo "\$smsMessage = \"Your registration has been approved. You may now log in to the Barserve website.\";\n";
        echo "\$smsResult = sendSMS(\$user['contact_number'], \$smsMessage);\n";
        echo "</code>\n";
    }
}

/**
 * Display summary of applied fixes
 */
function displayFixesSummary() {
    echo "<h1>BCRS-PHP System Fixes Applied</h1>\n";
    
    echo "<h2>Fix 1: Resource Deduction Logic</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Issue:</strong> Resources were being deducted immediately when reservations were made (including 'pending' status)</li>\n";
    echo "<li><strong>Fix:</strong> Modified pages/reservation.php lines 37-45 to exclude 'pending' reservations from availability calculation</li>\n";
    echo "<li><strong>Result:</strong> Resources are now only deducted after admin approval</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Fix 2: SMS Registration Approval</h2>\n";
    echo "<ul>\n";
    echo "<li><strong>Issue:</strong> No SMS notification when user registration is approved</li>\n";
    echo "<li><strong>Fix:</strong> Added SMS notifications in pages/admin/users.php and pages/admin/view_user.php</li>\n";
    echo "<li><strong>Message:</strong> \"Your registration has been approved. You may now log in to the Barserve website.\"</li>\n";
    echo "<li><strong>Result:</strong> Users now receive SMS when their registration is approved</li>\n";
    echo "</ul>\n";
    
    echo "<h2>Files Modified:</h2>\n";
    echo "<ol>\n";
    echo "<li><strong>pages/reservation.php</strong> - Updated resource availability calculation</li>\n";
    echo "<li><strong>pages/admin/users.php</strong> - Added SMS notification for user approval</li>\n";
    echo "<li><strong>pages/admin/view_user.php</strong> - Added SMS notification for user approval</li>\n";
    echo "</ol>\n";
}

// Only run tests if accessed directly and database is available
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "<html><head><title>BCRS-PHP Fixes Test</title></head><body>\n";
    
    displayFixesSummary();
    
    if (isset($db) && $db instanceof PDO) {
        testResourceAvailability($db);
        testSMSFunctionality();
    } else {
        echo "<h2>Database Connection</h2>\n";
        echo "<p style='color: red;'>❌ Database connection not available. Cannot run database tests.</p>\n";
        echo "<p>Please ensure the database is properly configured in config/database.php</p>\n";
        testSMSFunctionality();
    }
    
    echo "<p><em>Test completed at " . date('Y-m-d H:i:s') . "</em></p>\n";
    echo "</body></html>\n";
}
?> 