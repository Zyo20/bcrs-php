<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = "Please login to view details.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Get IDs from query parameters
$reservationId = isset($_GET['reservation_id']) ? (int)$_GET['reservation_id'] : 0;
$historyId = isset($_GET['history_id']) ? (int)$_GET['history_id'] : 0;

if ($reservationId <= 0 || $historyId <= 0) {
    show404("Invalid reservation or history ID.");
}

// Load reservation details to check permissions
try {
    // Check if user has permission to view this reservation
    if (isAdmin()) {
        $stmt = $db->prepare("SELECT id FROM reservations WHERE id = ?");
        $stmt->execute([$reservationId]);
    } else {
        $stmt = $db->prepare("SELECT id FROM reservations WHERE id = ? AND user_id = ?");
        $stmt->execute([$reservationId, $_SESSION['user_id']]);
    }
    $reservation = $stmt->fetch();

    if (!$reservation) {
        show404("Reservation not found or you don't have permission to view this status history.");
    }

    // Get the specific status history entry
    $stmt = $db->prepare("
        SELECT 
            rsh.*,
            COALESCE(u.first_name, a.first_name) as first_name,
            COALESCE(u.last_name, a.last_name) as last_name,
            CASE 
                WHEN rsh.created_by_user_id IS NOT NULL THEN 'user'
                WHEN rsh.created_by_admin_id IS NOT NULL THEN 'admin'
                ELSE 'system'
            END as creator_type
        FROM reservation_status_history rsh
        LEFT JOIN users u ON rsh.created_by_user_id = u.id
        LEFT JOIN admins a ON rsh.created_by_admin_id = a.id
        WHERE rsh.id = ? AND rsh.reservation_id = ?
    ");
    $stmt->execute([$historyId, $reservationId]);
    $historyEntry = $stmt->fetch();

    if (!$historyEntry) {
        show404("Status history entry not found.");
    }

} catch (PDOException $e) {
    show404("Error: " . $e->getMessage());
}

// Function to get status badge (copied from view_reservation.php for consistency, consider moving to functions.php)
function getStatusBadge($status) {
     switch ($status) {
        case 'pending':
            return '<span class="bg-yellow-100 text-yellow-800 px-2 py-1 rounded text-xs font-medium">Pending Approval</span>';
        case 'approved':
            return '<span class="bg-green-100 text-green-800 px-2 py-1 rounded text-xs font-medium">Approved</span>';
        case 'for_delivery':
            return '<span class="bg-blue-100 text-blue-800 px-2 py-1 rounded text-xs font-medium">For Delivery</span>';
        case 'for_pickup':
            return '<span class="bg-purple-100 text-purple-800 px-2 py-1 rounded text-xs font-medium">For Pickup</span>';
        case 'completed':
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">Completed</span>';
        case 'cancelled':
            return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Cancelled</span>';
        case 'reject':
            return '<span class="bg-red-100 text-red-800 px-2 py-1 rounded text-xs font-medium">Payment Rejected</span>';
        default:
            return '<span class="bg-gray-100 text-gray-800 px-2 py-1 rounded text-xs font-medium">' . ucfirst($status) . '</span>';
    }
}

?>

<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Status History Detail</h1>
        <?php 
        $backLink = isAdmin() 
            ? "index.php?page=admin&section=view_reservation&id=" . $reservationId 
            : "index.php?page=view_reservation&id=" . $reservationId;
        ?>
        <a href="<?php echo $backLink; ?>" class="text-blue-600 hover:underline">← Back to Reservation</a>
    </div>

    <div class="bg-white rounded-lg shadow-md p-6">
        <div class="mb-4 pb-4 border-b border-gray-200">
            <p class="text-sm text-gray-500">Reservation #<?php echo $reservationId; ?></p>
            <h2 class="text-xl font-semibold mt-1">
                <?php 
                // Display more descriptive status title
                switch($historyEntry['status']) {
                    case 'pending':
                        echo 'Reservation Submitted';
                        break;
                    case 'approved':
                        echo 'Reservation Approved';
                        break;
                    case 'cancelled':
                        echo 'Reservation Cancelled';
                        break;
                    case 'completed':
                        echo 'Reservation Completed';
                        break;
                    case 'for_delivery':
                        echo 'Items Set for Delivery';
                        break;
                    case 'for_pickup':
                        echo 'Items Ready for Pickup';
                        break;
                    case 'reject':
                        echo 'Payment Rejected';
                        break;
                    case 'equipment_update':
                        echo 'Equipment Quantities Updated';
                        break;
                    default:
                        echo 'Status: ' . ucfirst($historyEntry['status']);
                }
                ?>
                <?php echo getStatusBadge($historyEntry['status']); ?>
            </h2>
        </div>

        <div class="mb-4">
            <p class="font-semibold text-gray-700">Timestamp:</p>
            <p class="text-gray-600"><?php echo formatDate($historyEntry['created_at']); ?></p>
        </div>

        <div class="mb-4">
            <p class="font-semibold text-gray-700">Updated By:</p>
            <p class="text-gray-600"><?php echo $historyEntry['first_name'] . ' ' . $historyEntry['last_name']; ?></p>
        </div>

        <?php if (!empty($historyEntry['notes'])): ?>
            <div>
                <p class="font-semibold text-gray-700">Notes:</p>
                <p class="text-gray-600 bg-gray-50 p-3 rounded mt-1"><?php echo nl2br(htmlspecialchars($historyEntry['notes'])); ?></p>
            </div>
        <?php else: ?>
             <div>
                <p class="font-semibold text-gray-700">Notes:</p>
                <p class="text-gray-600 bg-gray-50 p-3 rounded mt-1">
                    <?php
                    // Display default descriptive notes based on status
                    switch($historyEntry['status']) {
                        case 'pending':
                            echo "The reservation was submitted and is now waiting for administrator approval.";
                            break;
                        case 'approved':
                            echo "This reservation has been reviewed and approved by an administrator.";
                            break;
                        case 'cancelled':
                            echo "This reservation has been cancelled and is no longer active.";
                            break;
                        case 'completed':
                            echo "All items for this reservation have been returned. The reservation is now complete.";
                            break;
                        case 'for_delivery':
                            echo "The reserved items are scheduled for delivery to the provided address.";
                            break;
                        case 'for_pickup':
                            echo "The reserved items are ready and available for pickup.";
                            break;
                        case 'reject':
                            echo "The submitted payment proof was reviewed and rejected.";
                            break;
                        case 'equipment_update':
                            echo "The quantities of reserved equipment have been updated in the system.";
                            break;
                        default:
                            echo "The status of this reservation was updated.";
                    }
                    ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>
