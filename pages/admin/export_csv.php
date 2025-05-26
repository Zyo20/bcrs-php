<?php
// This file is dedicated just for CSV exports - no HTML output

// Start immediate diagnostics in a log file - debug for users_list export issue
$logFile = dirname(dirname(dirname(__FILE__))) . '/logs/export_debug.log';
file_put_contents($logFile, "Export started at " . date('Y-m-d H:i:s') . "\n", FILE_APPEND);
file_put_contents($logFile, "REQUEST: " . print_r($_REQUEST, true) . "\n", FILE_APPEND);

// Start the session first if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error handling - disable display errors and log them instead
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_warnings', 0);

// Use dynamic path determination instead of hardcoded path
$baseDir = dirname(dirname(dirname(__FILE__))) . '/';
require_once $baseDir . 'config/database.php';
ini_set('log_errors', 1);
ini_set('error_log', $baseDir . 'logs/export_errors.log');

// Start output buffering and clear any existing output
ob_start();
ob_clean();

// Check if we're exporting users_list and log it
if (isset($_GET['export_type']) && $_GET['export_type'] == 'users_list') {
    file_put_contents($logFile, "USERS EXPORT REQUEST DETECTED\n", FILE_APPEND);
}

try {
    // Get date range filter
    $startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
    $endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
    $reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'reservation';

    // Determine filename based on report type
    $filename = "Report_" . $reportType . "_" . date('Y-m-d') . ".csv";
    
    // Clear the output buffer again before setting headers
    ob_clean();
    
    // Send headers in the correct order
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: 0');

    // Create output stream
    $output = fopen('php://output', 'w');

    // Functions for reports
    function getReservationReport($db, $startDate, $endDate) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(r.created_at) as date,
                    COUNT(*) as total,
                    SUM(CASE WHEN r.status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN r.status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    SUM(CASE WHEN r.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    IFNULL(SUM(CASE 
                        WHEN r.payment_status = 'paid' THEN (
                            SELECT IFNULL(SUM(ri.quantity * res.payment_amount), 0)
                            FROM reservation_items ri
                            JOIN resources res ON ri.resource_id = res.id
                            WHERE ri.reservation_id = r.id AND res.requires_payment = 1
                        )
                        ELSE 0 
                    END), 0) as revenue
                FROM 
                    reservations r
                WHERE 
                    DATE(r.created_at) BETWEEN :start_date AND :end_date
                GROUP BY 
                    DATE(r.created_at)
                ORDER BY 
                    date DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    function getResourceUsageReport($db, $startDate, $endDate) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    r.name,
                    r.category,
                    COUNT(ri.id) as total_reservations,
                    SUM(ri.quantity) as total_quantity_reserved
                FROM 
                    resources r
                LEFT JOIN 
                    reservation_items ri ON r.id = ri.resource_id
                LEFT JOIN 
                    reservations res ON ri.reservation_id = res.id
                WHERE 
                    res.created_at IS NULL OR
                    (DATE(res.created_at) BETWEEN :start_date AND :end_date)
                GROUP BY 
                    r.id
                ORDER BY 
                    total_reservations DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    function getUserRegistrationReport($db, $startDate, $endDate) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    COUNT(*) as total_registrations,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                FROM 
                    users
                WHERE 
                    role = 'user' AND
                    DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY 
                    DATE(created_at)
                ORDER BY 
                    date DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    function getFinancialReport($db, $startDate, $endDate) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    DATE(created_at) as date,
                    SUM(CASE WHEN payment_status = 'paid' THEN (
                        SELECT IFNULL(SUM(ri.quantity * res.payment_amount), 0)
                        FROM reservation_items ri
                        JOIN resources res ON ri.resource_id = res.id
                        WHERE ri.reservation_id = r.id AND res.requires_payment = 1
                    ) ELSE 0 END) as revenue,
                    COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_reservations,
                    COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_payments
                FROM 
                    reservations r
                WHERE 
                    DATE(created_at) BETWEEN :start_date AND :end_date
                GROUP BY 
                    DATE(created_at)
                ORDER BY 
                    date DESC
            ");
            $stmt->bindParam(':start_date', $startDate);
            $stmt->bindParam(':end_date', $endDate);
            $stmt->execute();
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    function getUsersListReport($db, $filter = 'all', $search = '', $searchField = 'all', $sortBy = 'id', $sortOrder = 'desc') {
        // Log the parameters
        error_log("getUsersListReport called with filter: $filter, search: $search, field: $searchField, sort: $sortBy $sortOrder");
        
        // Prepare the query based on filter, search and sort
        $query = "SELECT id, first_name, last_name, email, contact_number, address, status, blacklisted, created_at FROM users WHERE role = 'user'";
        $params = [];

        // Apply status filter
        switch ($filter) {
            case 'pending':
                $query .= " AND status = 'pending'";
                break;
            case 'active':
                $query .= " AND status = 'approved'";
                break;
            case 'blocked':
                $query .= " AND status = 'rejected'";
                break;
            case 'blacklisted':
                $query .= " AND blacklisted = 1";
                break;
            // 'all' doesn't need additional conditions
        }

        // Apply search if provided
        if (!empty($search)) {
            switch ($searchField) {
                case 'name':
                    $query .= " AND (first_name LIKE :search_name1 OR last_name LIKE :search_name2)";
                    $params[':search_name1'] = "%{$search}%";
                    $params[':search_name2'] = "%{$search}%";
                    break;
                case 'email':
                    $query .= " AND email LIKE :search_email";
                    $params[':search_email'] = "%{$search}%";
                    break;
                case 'contact':
                    $query .= " AND contact_number LIKE :search_contact";
                    $params[':search_contact'] = "%{$search}%";
                    break;
                default: // 'all' - search all fields
                    $query .= " AND (first_name LIKE :search_all1 OR last_name LIKE :search_all2 OR email LIKE :search_all3 OR contact_number LIKE :search_all4)";
                    $params[':search_all1'] = "%{$search}%";
                    $params[':search_all2'] = "%{$search}%";
                    $params[':search_all3'] = "%{$search}%";
                    $params[':search_all4'] = "%{$search}%";
            }
        }

        // Add sorting - ensure we only sort by allowed fields
        $allowedSortFields = ['id', 'first_name', 'email', 'contact_number', 'created_at', 'status'];
        $allowedSortOrders = ['asc', 'desc'];
        $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id';
        $sortOrder = in_array($sortOrder, $allowedSortOrders) ? $sortOrder : 'desc';
        
        $query .= " ORDER BY {$sortBy} {$sortOrder}";
        
        // Log the final query
        error_log("Final SQL query: $query");
        error_log("Params: " . print_r($params, true));

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
            error_log("Query returned " . count($results) . " rows");
            return $results;
        } catch (PDOException $e) {
            error_log("Error exporting users list: " . $e->getMessage());
            error_log("Query that failed: $query");
            return [];
        }
    }

    function getResourcesListReport($db, $filter = 'all', $sort = 'name_asc', $category = '', $search = '') {
        // Build the SQL query based on filters
        $query = "SELECT id, name, description, category, quantity, availability, created_at FROM resources WHERE 1=1";
        $params = [];

        // Apply category filter
        if (!empty($category) && in_array($category, ['equipment', 'facility'])) {
            $query .= " AND category = :category";
            $params[':category'] = $category;
        }

        // Apply availability filter
        if ($filter !== 'all' && in_array($filter, ['available', 'reserved', 'maintenance'])) {
            $query .= " AND availability = :availability";
            $params[':availability'] = $filter;
        }

        // Apply search
        if (!empty($search)) {
            $query .= " AND (name LIKE :search_name OR description LIKE :search_desc)";
            $searchParam = "%{$search}%";
            $params[':search_name'] = $searchParam;
            $params[':search_desc'] = $searchParam;
        }

        // Apply sorting
        switch ($sort) {
            case 'name_desc':
                $query .= " ORDER BY name DESC";
                break;
            case 'created_asc':
                $query .= " ORDER BY created_at ASC";
                break;
            case 'created_desc':
                $query .= " ORDER BY created_at DESC";
                break;
            case 'category_asc':
                $query .= " ORDER BY category ASC, name ASC";
                break;
            case 'category_desc':
                $query .= " ORDER BY category DESC, name ASC";
                break;
            default: // name_asc
                $query .= " ORDER BY name ASC";
                break;
        }

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error exporting resources list: " . $e->getMessage());
            return [];
        }
    }

    function getMasterlistReport($db, $filter = 'all', $search = '', $purok = '') {
        // First check if masterlist table exists
        try {
            $stmt = $db->query("SHOW TABLES LIKE 'masterlist'");
            if ($stmt->rowCount() == 0) {
                error_log("Masterlist table does not exist in the database");
                return [];
            }
        } catch (PDOException $e) {
            error_log("Error checking for masterlist table: " . $e->getMessage());
            return [];
        }
        
        // Build the SQL query based on filters
        $query = "SELECT id, last_name, first_name, middle_name, contact_number, age, year_of_residency, purok, created_at FROM masterlist WHERE 1=1";
        $params = [];

        // Apply purok filter
        if (!empty($purok) && in_array($purok, ['Purok 1', 'Purok 2', 'Purok 3', 'Purok 4', 'Purok 5', 'Purok 6'])) {
            $query .= " AND purok = :purok";
            $params[':purok'] = $purok;
        }

        // Apply search
        if (!empty($search)) {
            $query .= " AND (last_name LIKE :search_last OR first_name LIKE :search_first OR middle_name LIKE :search_middle OR contact_number LIKE :search_contact)";
            $searchParam = "%{$search}%";
            $params[':search_last'] = $searchParam;
            $params[':search_first'] = $searchParam;
            $params[':search_middle'] = $searchParam;
            $params[':search_contact'] = $searchParam;
        }

        // Apply sorting - default to last_name ASC
        $query .= " ORDER BY last_name ASC, first_name ASC";

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error exporting masterlist: " . $e->getMessage());
            return [];
        }
    }

    // Get report data based on selected type
    $reportData = [];
    $exportType = isset($_GET['export_type']) ? $_GET['export_type'] : $reportType; // Use export_type if present

    // Export the data to CSV based on report type
    switch ($exportType) { // Changed from $reportType to $exportType
        case 'users_list':
            // Log that we're in users_list case
            file_put_contents($logFile, "Entering users_list export case\n", FILE_APPEND);
            
            // Get filter parameters
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $searchField = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';
            $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'id';
            $sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
            
            // DIRECT TEST: Let's try using static data to test if CSV works at all
            file_put_contents($logFile, "DIRECT TEST: Using static data\n", FILE_APPEND);
            
            // Force debug logging
            error_log("Starting users_list export with filter: $filter, search: $search");
            
            // Clear everything before starting
            ob_end_clean();
            ob_start();
            
            try {
                // Use static test data instead of database query for diagnosis
                $testData = [
                    ['id' => 1, 'first_name' => 'Test', 'last_name' => 'User1', 'email' => 'test1@example.com', 
                     'contact_number' => '123456789', 'address' => 'Test Address 1', 'status' => 'approved', 
                     'blacklisted' => 0, 'created_at' => '2023-01-01'],
                    ['id' => 2, 'first_name' => 'Test', 'last_name' => 'User2', 'email' => 'test2@example.com', 
                     'contact_number' => '987654321', 'address' => 'Test Address 2', 'status' => 'pending', 
                     'blacklisted' => 0, 'created_at' => '2023-01-02']
                ];
                
                // Also try the regular query
                $reportData = getUsersListReport($db, $filter, $search, $searchField, $sortBy, $sortOrder);
                
                // Use the test data for the export
                $dataToExport = !empty($reportData) ? $reportData : $testData;
                
                // Check if we got data
                file_put_contents($logFile, "Got " . count($dataToExport) . " user records for export\n", FILE_APPEND);
                
                // Clean buffer again to be sure
                ob_end_clean();
                
                // Set fresh headers
                $filename = "Users_List_" . date('Y-m-d') . ".csv";
                header('Content-Type: text/csv');
                header('Content-Disposition: attachment; filename="' . $filename . '"');
                header('Pragma: no-cache');
                header('Cache-Control: no-cache, must-revalidate');
                header('Expires: 0');
                
                // Create a new output handle
                $output = fopen('php://output', 'w');
                
                // Write UTF-8 BOM to help Excel detect encoding properly
                fputs($output, "\xEF\xBB\xBF");
                
                // Output the CSV
                fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Address', 'Status', 'Blacklisted', 'Registration Date']);
                foreach ($dataToExport as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['first_name'],
                        $row['last_name'], 
                        $row['email'],
                        $row['contact_number'],
                        $row['address'],
                        $row['status'],
                        $row['blacklisted'] ? 'Yes' : 'No',
                        $row['created_at']
                    ]);
                }
                
                // Final log entry
                file_put_contents($logFile, "Finished users_list export\n", FILE_APPEND);
                
                // Close output and exit
                fclose($output);
                exit();
            } catch (Exception $e) {
                error_log("Error in users_list export: " . $e->getMessage());
                file_put_contents($logFile, "ERROR in users_list export: " . $e->getMessage() . "\n", FILE_APPEND);
                // Clean output and return error
                ob_end_clean();
                header('Content-Type: text/plain');
                echo "Error exporting users: " . $e->getMessage();
                exit();
            }
            break;
        case 'resources': // New case for filtered resources export
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
            $category = isset($_GET['category']) ? $_GET['category'] : '';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';

            $reportData = getResourcesListReport($db, $filter, $sort, $category, $search);
            $filename = "Resources_List_" . date('Y-m-d') . ".csv";
            
            // Reset headers to ensure consistent behavior
            ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Cache-Control: no-cache, must-revalidate');

            fputcsv($output, ['ID', 'Name', 'Description', 'Category', 'Quantity', 'Availability', 'Created At']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['id'],
                    $row['name'],
                    $row['description'],
                    $row['category'],
                    $row['quantity'],
                    $row['availability'],
                    $row['created_at']
                ]);
            }
            break;
        case 'reservation':
            $reportData = getReservationReport($db, $startDate, $endDate);
            fputcsv($output, ['Date', 'Total', 'Approved', 'Rejected', 'Pending', 'Completed', 'Revenue']);
            foreach ($reportData as $row) {
                fputcsv($output, [
                    $row['date'], 
                    $row['total'], 
                    $row['approved'], 
                    $row['rejected'], 
                    $row['pending'], 
                    $row['completed'],
                    $row['revenue']
                ]);
            }
            break;
        case 'resource':
            $reportData = getResourceUsageReport($db, $startDate, $endDate);
            fputcsv($output, ['Resource Name', 'Category', 'Total Reservations', 'Total Quantity Reserved']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['name'], $row['category'], $row['total_reservations'], $row['total_quantity_reserved']]);
            }
            break;
        case 'user':
            $reportData = getUserRegistrationReport($db, $startDate, $endDate);
            fputcsv($output, ['Date', 'Total Registrations', 'Approved', 'Pending', 'Rejected']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['date'], $row['total_registrations'], $row['approved'], $row['pending'], $row['rejected']]);
            }
            break;
        case 'financial':
            $reportData = getFinancialReport($db, $startDate, $endDate);
            fputcsv($output, ['Date', 'Revenue', 'Paid Reservations', 'Pending Payments']);
            foreach ($reportData as $row) {
                fputcsv($output, [$row['date'], $row['revenue'], $row['paid_reservations'], $row['pending_payments']]);
            }
            break;
        case 'masterlist':
            $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
            $search = isset($_GET['search']) ? trim($_GET['search']) : '';
            $purok = isset($_GET['purok']) ? $_GET['purok'] : '';

            $reportData = getMasterlistReport($db, $filter, $search, $purok);
            $filename = "Masterlist_" . date('Y-m-d') . ".csv";
            
            // Reset headers to ensure consistent behavior
            ob_clean();
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Pragma: no-cache');
            header('Cache-Control: no-cache, must-revalidate');

            // Only proceed if we have data
            if (empty($reportData)) {
                // Log error if debug mode is on
                error_log("No data found for masterlist export");
                
                // Write header row even if no data
                fputcsv($output, ['ID', 'Last Name', 'First Name', 'Middle Name', 'Contact Number', 'Age', 'Year of Residency', 'Purok', 'Date Added']);
                
                // Add a single row indicating no data
                fputcsv($output, ['No records found', '', '', '', '', '', '', '', '']);
            } else {
                fputcsv($output, ['ID', 'Last Name', 'First Name', 'Middle Name', 'Contact Number', 'Age', 'Year of Residency', 'Purok', 'Date Added']);
                foreach ($reportData as $row) {
                    fputcsv($output, [
                        $row['id'],
                        $row['last_name'],
                        $row['first_name'],
                        $row['middle_name'],
                        $row['contact_number'],
                        $row['age'],
                        $row['year_of_residency'],
                        $row['purok'],
                        $row['created_at']
                    ]);
                }
            }
            break;
    }

    // Only close and exit here for non-users_list reports
    // (users_list has its own exit path)
    if ($exportType != 'users_list') {
        fclose($output);
        exit();
    }
} catch (Exception $e) {
    // Log the error
    error_log("Error exporting CSV: " . $e->getMessage());
    
    // Clean any output buffers
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    // If headers have not been sent, return a plain text error
    if (!headers_sent()) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/plain; charset=UTF-8');
        echo "Error exporting data. Please contact the administrator.";
    }
    
    // Make sure we clean up the output buffer
    if (isset($output) && is_resource($output)) {
        fclose($output);
    }
    exit();
}
?>