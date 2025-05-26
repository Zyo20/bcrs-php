<?php
// Make sure no previous output was generated
ob_clean();

// Check if admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message']= "You don't have permission to access this page";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Get parameters
$start = isset($_GET['start']) ? $_GET['start'] : date('Y-m-d', strtotime('-1 month'));
$end = isset($_GET['end']) ? $_GET['end'] : date('Y-m-d', strtotime('+1 month'));
$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$resourceType = isset($_GET['resource_type']) ? $_GET['resource_type'] : 'all';

// Build query
$query = "
    SELECT 
        r.id,
        r.user_id,
        CONCAT(u.first_name, ' ', u.last_name) as user_name,
        r.start_datetime,
        r.end_datetime,
        r.status,
        r.payment_status,
        r.address,
        u.contact_number,
        (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
    FROM 
        reservations r
    JOIN 
        users u ON r.user_id = u.id
    WHERE 1=1
";

// Modified date filter - corrected to ensure it matches events within the range
$query .= " AND ((r.start_datetime BETWEEN ? AND ?) 
            OR (r.end_datetime BETWEEN ? AND ?) 
            OR (r.start_datetime <= ? AND r.end_datetime >= ?))";

$params = [$start, $end, $start, $end, $start, $end];

// Apply status filter
if ($status !== 'all') {
    $query .= " AND r.status = ?";
    $params[] = $status;
}

// Apply resource type filter
if ($resourceType !== 'all') {
    $query .= " AND EXISTS (
        SELECT 1 
        FROM reservation_items ri2 
        JOIN resources res2 ON ri2.resource_id = res2.id 
        WHERE ri2.reservation_id = r.id 
        AND res2.category = ?
    )";
    $params[] = $resourceType;
}

// Add group by to avoid duplicates
$query .= " GROUP BY r.id ORDER BY r.start_datetime ASC";

try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for the calendar
    foreach ($reservations as &$reservation) {
        // Create a title that includes user name and resources
        $resources = isset($reservation['resources']) ? $reservation['resources'] : '';
        $reservation['title'] = "#" . $reservation['id'] . " - " . $reservation['user_name'] . 
                              " (" . substr($resources, 0, 30) . 
                              (strlen($resources) > 30 ? "..." : "") . ")";
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode(['events' => $reservations]);
    
} catch (PDOException $e) {
    // Return error
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
}
exit;
?>