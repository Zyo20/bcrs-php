<?php
// Get pending user registrations
try {
    $stmt = $db->prepare("
        SELECT * FROM users 
        WHERE status = 'pending'
        ORDER BY created_at DESC
    ");
    $stmt->execute();
    $pendingUsers = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get pending reservations
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.contact_number, (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.status = 'pending'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $pendingReservations = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get reservations requiring payment verification
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.contact_number
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.payment_status = 'pending' AND r.payment_proof IS NOT NULL
        ORDER BY r.created_at DESC
    ");
    $stmt->execute();
    $pendingPayments = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get active reservations
try {
    $stmt = $db->prepare("
        SELECT r.*, u.first_name, u.last_name, u.contact_number, (
            SELECT GROUP_CONCAT(rs.name SEPARATOR ', ') 
            FROM reservation_items ri 
            JOIN resources rs ON ri.resource_id = rs.id 
            WHERE ri.reservation_id = r.id
        ) as resources
        FROM reservations r
        JOIN users u ON r.user_id = u.id
        WHERE r.status IN ('approved', 'for_delivery', 'for_pickup')
        ORDER BY r.start_datetime ASC
    ");
    $stmt->execute();
    $activeReservations = $stmt->fetchAll();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get system stats
try {
    // Total users
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $stmt->execute();
    $totalUsers = $stmt->fetch()['count'];
    
    // Total reservations
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations");
    $stmt->execute();
    $totalReservations = $stmt->fetch()['count'];
    
    // Today's reservations 
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM reservations WHERE DATE(created_at) = CURDATE()");
    $stmt->execute();
    $todayReservations = $stmt->fetch()['count'];
    
    // Resources in use
    $stmt = $db->prepare("
        SELECT COUNT(*) as count 
        FROM resources 
        WHERE availability = 'reserved'
    ");
    $stmt->execute();
    $resourcesInUse = $stmt->fetch()['count'];
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Check if a section is specified
$section = isset($_GET['section']) ? $_GET['section'] : '';

// Handle the different admin sections
switch ($section) {
    case 'view_payment':
        include 'pages/admin/view_payment.php';
        break;
    case 'view_reservation':
        include 'pages/admin/view_reservation.php';
        break;
    case 'resources':
        include 'pages/admin/resources.php';
        break;
    case 'add_resource':
        include 'pages/admin/add_resource.php';
        break;
    case 'edit_resource':
        include 'pages/admin/edit_resource.php';
        break;
    case 'delete_resource':
        include 'pages/admin/delete_resource.php';
        break;
    case 'approve_reservation':
        include 'pages/admin/approve_reservation.php';
        break;
    case 'reject_reservation':
        include 'pages/admin/reject_reservation.php';
        break;
    case 'payments':
        include 'pages/admin/payments.php';
        break;
    case 'users':
        include 'pages/admin/users.php';
        break;
    case 'view_user':
        include 'pages/admin/view_user.php';
        break;
    case 'reservations':
        include 'pages/admin/reservations.php';
        break;
    case 'reports':
        include 'pages/admin/reports.php';
        break;
    case 'export_csv':
        include 'pages/admin/export_csv.php';
        break;
    case 'mark_returned':
        include 'pages/admin/mark_returned.php';
        break;
    case 'update_status':
        include 'pages/admin/update_status.php';
        break;
    case 'mark_completed':
        include 'pages/admin/mark_completed.php';
        break;
    case 'calendar':
        if (isset($_GET['action']) && $_GET['action'] === 'get_reservations') {
            include 'pages/admin/calendar/get_reservations.php';
        } else {
            include 'pages/admin/calendar/index.php';
        }
        break;
    case 'feedback':
        if (isset($_GET['action'])) {
            switch ($_GET['action']) {
                case 'view':
                    include 'pages/admin/feedback/view.php';
                    break;
                case 'respond':
                    include 'pages/admin/feedback/respond.php';
                    break;
                case 'mark_read':
                    // Handle mark as read action directly
                    if (isset($_GET['id']) && isset($_GET['csrf_token']) && verifyCSRFToken($_GET['csrf_token'])) {
                        $id = (int)$_GET['id'];
                        try {
                            $stmt = $db->prepare("UPDATE feedback SET status = 'read' WHERE id = ?");
                            $stmt->execute([$id]);
                            setFlashMessage('Feedback marked as read', 'success');
                        } catch (PDOException $e) {
                            setFlashMessage('Error updating feedback status', 'error');
                        }
                    }
                    redirect('index.php?page=admin&section=feedback');
                    break;
                default:
                    include 'pages/admin/feedback/index.php';
                    break;
            }
        } else {
            include 'pages/admin/feedback/index.php';
        }
        break;
    default:
        include 'pages/admin/default_admin_dashboard.php';
        break;
}
?>
