<?php
// This file is dedicated just for CSV exports - no HTML output
require_once '../../config/database.php';

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'reservation';

// Set CSV headers
$filename = "Report_" . $reportType . "_" . date('Y-m-d') . ".csv";
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
                $query .= " AND (first_name LIKE ? OR last_name LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                break;
            case 'email':
                $query .= " AND email LIKE ?";
                $params[] = "%{$search}%";
                break;
            case 'contact':
                $query .= " AND contact_number LIKE ?";
                $params[] = "%{$search}%";
                break;
            default: // 'all' - search all fields
                $query .= " AND (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR contact_number LIKE ?)";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
                $params[] = "%{$search}%";
        }
    }

    // Add sorting - ensure we only sort by allowed fields
    $allowedSortFields = ['id', 'first_name', 'email', 'contact_number', 'created_at', 'status'];
    $allowedSortOrders = ['asc', 'desc'];
    $sortBy = in_array($sortBy, $allowedSortFields) ? $sortBy : 'id';
    $sortOrder = in_array($sortOrder, $allowedSortOrders) ? $sortOrder : 'desc';
    
    $query .= " ORDER BY {$sortBy} {$sortOrder}";

    try {
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error exporting users list: " . $e->getMessage());
        return [];
    }
}

function getResourcesListReport($db, $filter = 'all', $sort = 'name_asc', $category = '', $search = '') {
    // Build the SQL query based on filters
    $query = "SELECT id, name, description, category, quantity, availability, created_at FROM resources WHERE 1=1";
    $params = [];

    // Apply category filter
    if (!empty($category) && in_array($category, ['equipment', 'facility'])) {
        $query .= " AND category = ?";
        $params[] = $category;
    }

    // Apply availability filter
    if ($filter !== 'all' && in_array($filter, ['available', 'reserved', 'maintenance'])) {
        $query .= " AND availability = ?";
        $params[] = $filter;
    }

    // Apply search
    if (!empty($search)) {
        $query .= " AND (name LIKE ? OR description LIKE ?)";
        $searchParam = "%{$search}%";
        $params[] = $searchParam;
        $params[] = $searchParam;
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

// Get report data based on selected type
$reportData = [];
$exportType = isset($_GET['export_type']) ? $_GET['export_type'] : $reportType; // Use export_type if present

// Export the data to CSV based on report type
switch ($exportType) { // Changed from $reportType to $exportType
    case 'users_list':
        // Get filter parameters
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';
        $searchField = isset($_GET['search_field']) ? $_GET['search_field'] : 'all';
        $sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'id';
        $sortOrder = isset($_GET['order']) ? $_GET['order'] : 'desc';
        
        $reportData = getUsersListReport($db, $filter, $search, $searchField, $sortBy, $sortOrder);
        $filename = "Users_List_" . date('Y-m-d') . ".csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        fputcsv($output, ['ID', 'First Name', 'Last Name', 'Email', 'Contact Number', 'Address', 'Status', 'Blacklisted', 'Registration Date']);
        foreach ($reportData as $row) {
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
        break;
    case 'resources': // New case for filtered resources export
        $filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
        $sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
        $category = isset($_GET['category']) ? $_GET['category'] : '';
        $search = isset($_GET['search']) ? trim($_GET['search']) : '';

        $reportData = getResourcesListReport($db, $filter, $sort, $category, $search);
        $filename = "Resources_List_" . date('Y-m-d') . ".csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');

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
}

fclose($output);
exit();
?>