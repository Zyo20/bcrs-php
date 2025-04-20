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

// Get report data based on selected type
$reportData = [];

// Export the data to CSV based on report type
switch ($reportType) {
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