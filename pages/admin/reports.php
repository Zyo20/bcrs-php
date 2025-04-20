<?php

// Get date range filter
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');
$reportType = isset($_GET['report_type']) ? $_GET['report_type'] : 'reservation';

// Export to CSV if requested - This needs to be handled before any HTML output
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $filename = "Report_" . $reportType . "_" . date('Y-m-d') . ".csv";
    
    // Set headers to force download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Get report data based on selected type
    $reportData = [];
    
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
    exit(); // Make sure to exit after generating CSV
}

// Function to get reservation data
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
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

// Function to get resource usage data
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
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

// Function to get user registration data
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
        
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

// Function to get financial report
function getFinancialReport($db, $startDate, $endDate) {
    try {
        $stmt = $db->prepare("
            SELECT
                DATE(r.created_at) as date,
                -- Calculate revenue based on paid items
                IFNULL(SUM(CASE
                    WHEN r.payment_status = 'paid' AND res.requires_payment = 1 THEN ri.quantity * res.payment_amount
                    ELSE 0
                END), 0) as revenue,
                -- Count distinct reservations that are paid
                COUNT(DISTINCT CASE WHEN r.payment_status = 'paid' THEN r.id END) as paid_reservations,
                -- Count distinct reservations that are pending AND require payment (have at least one item requiring payment)
                COUNT(DISTINCT CASE WHEN r.status = 'pending' AND r_requires_payment.requires_payment_flag = 1 THEN r.id END) as pending_payments
            FROM
                reservations r
            LEFT JOIN
                reservation_items ri ON r.id = ri.reservation_id
            LEFT JOIN
                resources res ON ri.resource_id = res.id -- Join for revenue calculation
            LEFT JOIN (
                -- Subquery to determine if a reservation requires payment
                SELECT DISTINCT ri_sub.reservation_id, 1 as requires_payment_flag
                FROM reservation_items ri_sub
                JOIN resources res_sub ON ri_sub.resource_id = res_sub.id
                WHERE res_sub.requires_payment = 1
            ) r_requires_payment ON r.id = r_requires_payment.reservation_id
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

        return $stmt->fetchAll();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
        return [];
    }
}

// Get report data based on selected type
$reportData = [];
$reportTitle = "";

switch ($reportType) {
    case 'reservation':
        $reportData = getReservationReport($db, $startDate, $endDate);
        $reportTitle = "Reservation Report";
        break;
    case 'resource':
        $reportData = getResourceUsageReport($db, $startDate, $endDate);
        $reportTitle = "Resource Usage Report";
        break;
    case 'user':
        $reportData = getUserRegistrationReport($db, $startDate, $endDate);
        $reportTitle = "User Registration Report";
        break;
    case 'financial':
        $reportData = getFinancialReport($db, $startDate, $endDate);
        $reportTitle = "Financial Report";
        break;
    default:
        $reportData = getReservationReport($db, $startDate, $endDate);
        $reportTitle = "Reservation Report";
}

// Calculate summary statistics
$summaryStats = [
    'total' => 0,
    'approved' => 0,
    'pending' => 0,
    'rejected' => 0,
    'completed' => 0,
    'revenue' => 0
];

if ($reportType === 'reservation') {
    foreach ($reportData as $row) {
        $summaryStats['total'] += $row['total'];
        $summaryStats['approved'] += $row['approved'];
        $summaryStats['pending'] += $row['pending'];
        $summaryStats['rejected'] += $row['rejected'];
        $summaryStats['completed'] += $row['completed'];
        $summaryStats['revenue'] += $row['revenue'];
    }
} elseif ($reportType === 'financial') {
    foreach ($reportData as $row) {
        $summaryStats['total'] += $row['paid_reservations'] + $row['pending_payments'];
        $summaryStats['approved'] = 'N/A';
        $summaryStats['pending'] = array_sum(array_column($reportData, 'pending_payments'));
        $summaryStats['revenue'] += $row['revenue'];
    }
} elseif ($reportType === 'user') {
    foreach ($reportData as $row) {
        $summaryStats['total'] += $row['total_registrations'];
        $summaryStats['approved'] += $row['approved'];
        $summaryStats['pending'] += $row['pending'];
        $summaryStats['rejected'] += $row['rejected'];
    }
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800"><?php echo $reportTitle; ?></h1>
        <div class="flex space-x-2">
            <a href="index.php?page=admin&section=default_admin_dashboard" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
            <a href="pages/admin/export_csv.php?report_type=<?php echo $reportType; ?>&start_date=<?php echo $startDate; ?>&end_date=<?php echo $endDate; ?>" class="px-4 py-2 bg-green-600 hover:bg-green-700 text-white rounded">
                <i class="fas fa-download"></i> Export to CSV
            </a>
        </div>
    </div>

    <!-- Report Controls -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <form method="GET" class="md:flex items-center space-y-4 md:space-y-0 md:space-x-4">
            <input type="hidden" name="page" value="admin">
            <input type="hidden" name="section" value="reports">
            
            <div class="w-full md:w-1/4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Report Type</label>
                <select name="report_type" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
                    <option value="reservation" <?php echo $reportType === 'reservation' ? 'selected' : ''; ?>>Reservation Report</option>
                    <option value="resource" <?php echo $reportType === 'resource' ? 'selected' : ''; ?>>Resource Usage Report</option>
                    <option value="user" <?php echo $reportType === 'user' ? 'selected' : ''; ?>>User Registration Report</option>
                    <option value="financial" <?php echo $reportType === 'financial' ? 'selected' : ''; ?>>Financial Report</option>
                </select>
            </div>
            
            <div class="w-full md:w-1/4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                <input type="date" name="start_date" value="<?php echo $startDate; ?>" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="w-full md:w-1/4">
                <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                <input type="date" name="end_date" value="<?php echo $endDate; ?>" class="block w-full border-gray-300 rounded-md shadow-sm focus:ring-blue-500 focus:border-blue-500">
            </div>
            
            <div class="w-full md:w-1/4 flex items-end">
                <button type="submit" class="w-full px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded">
                    <i class="fas fa-filter"></i> Apply Filter
                </button>
            </div>
        </form>
    </div>

    <!-- Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
        <div class="bg-white shadow rounded-lg p-4 border-l-4 border-blue-500">
            <h3 class="text-sm font-medium text-gray-500">Total <?php echo $reportType === 'resource' ? 'Resources' : ($reportType === 'financial' ? 'Transactions' : ($reportType === 'user' ? 'Registrations' : 'Reservations')); ?></h3>
            <p class="text-2xl font-semibold text-gray-800"><?php echo $reportType === 'resource' ? count($reportData) : $summaryStats['total']; ?></p>
        </div>
        
        <?php if ($reportType !== 'resource'): ?>
        <div class="bg-white shadow rounded-lg p-4 border-l-4 border-green-500">
            <h3 class="text-sm font-medium text-gray-500"><?php echo $reportType === 'financial' ? 'Paid Transactions' : 'Approved'; ?></h3>
            <p class="text-2xl font-semibold text-gray-800"><?php echo $summaryStats['approved'] === 'N/A' ? array_sum(array_column($reportData, 'paid_reservations')) : $summaryStats['approved']; ?></p>
        </div>
        
        <div class="bg-white shadow rounded-lg p-4 border-l-4 border-yellow-500">
            <h3 class="text-sm font-medium text-gray-500">Pending</h3>
            <p class="text-2xl font-semibold text-gray-800"><?php echo $summaryStats['pending']; ?></p>
        </div>
        <?php endif; ?>
        
        <div class="bg-white shadow rounded-lg p-4 border-l-4 border-purple-500">
            <h3 class="text-sm font-medium text-gray-500"><?php echo $reportType === 'financial' || $reportType === 'resource' ? 'Total Revenue' : ($reportType === 'user' ? 'Rejected' : 'Completed'); ?></h3>
            <p class="text-2xl font-semibold text-gray-800">
                <?php 
                if ($reportType === 'financial' || $reportType === 'reservation') {
                    echo '₱' . number_format($summaryStats['revenue'], 2);
                } elseif ($reportType === 'resource') {
                    $totalRevenue = 0;
                    foreach ($reportData as $row) {
                        $totalRevenue += $row['total_reservations'] * 100; // Example calculation
                    }
                    echo '₱' . number_format($totalRevenue, 2);
                } else {
                    echo $summaryStats['rejected'];
                }
                ?>
            </p>
        </div>
    </div>
    
    <!-- Chart Section -->
    <div class="bg-white shadow rounded-lg p-6 mb-6">
        <h2 class="text-xl font-semibold text-gray-800 mb-4">Data Visualization</h2>
        <div style="height: 400px; position: relative;">
            <canvas id="reportChart"></canvas>
        </div>
    </div>
    
    <!-- Data Table -->
    <div class="bg-white shadow rounded-lg overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200">
            <h2 class="text-xl font-semibold text-gray-800">Detailed Data</h2>
        </div>
        
        <div class="overflow-x-auto">
            <?php if ($reportType === 'reservation'): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Total</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Approved</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Rejected</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Pending</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Completed</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-black uppercase tracking-wider">Revenue</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo $row['total']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo $row['approved']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo $row['rejected']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo $row['pending']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black"><?php echo $row['completed']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-black">₱<?php echo number_format($row['revenue'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="7" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No data available for the selected date range</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType === 'resource'): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource Name</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Reservations</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Quantity Reserved</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['name']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                    <?php if ($row['category'] === 'facility'): ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-blue-100 text-blue-800">
                                            Facility
                                        </span>
                                    <?php else: ?>
                                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800">
                                            Equipment
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['total_reservations']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['total_quantity_reserved']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No data available for the selected date range</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType === 'user'): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Total Registrations</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Approved</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Rejected</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['total_registrations']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['approved']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['pending']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['rejected']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No data available for the selected date range</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
                
            <?php elseif ($reportType === 'financial'): ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Revenue</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Paid Reservations</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Pending Payments</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reportData as $row): ?>
                            <tr>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($row['date'])); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">₱<?php echo number_format($row['revenue'], 2); ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['paid_reservations']; ?></td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $row['pending_payments']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if (empty($reportData)): ?>
                            <tr>
                                <td colspan="4" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">No data available for the selected date range</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Include Chart.js for data visualization -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const ctx = document.getElementById('reportChart').getContext('2d');
    
    <?php if ($reportType === 'reservation'): ?>
        // Prepare data for chart
        const dates = <?php echo json_encode(array_map(function($row) { return date('M d', strtotime($row['date'])); }, array_reverse($reportData))); ?>;
        const approved = <?php echo json_encode(array_map(function($row) { return $row['approved']; }, array_reverse($reportData))); ?>;
        const rejected = <?php echo json_encode(array_map(function($row) { return $row['rejected']; }, array_reverse($reportData))); ?>;
        const pending = <?php echo json_encode(array_map(function($row) { return $row['pending']; }, array_reverse($reportData))); ?>;
        const completed = <?php echo json_encode(array_map(function($row) { return $row['completed']; }, array_reverse($reportData))); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Approved',
                        data: approved,
                        backgroundColor: 'rgba(34, 197, 94, 0.6)',
                        borderColor: 'rgb(34, 197, 94)',
                        borderWidth: 1
                    },
                    {
                        label: 'Pending',
                        data: pending,
                        backgroundColor: 'rgba(234, 179, 8, 0.6)',
                        borderColor: 'rgb(234, 179, 8)',
                        borderWidth: 1
                    },
                    {
                        label: 'Rejected',
                        data: rejected,
                        backgroundColor: 'rgba(239, 68, 68, 0.6)',
                        borderColor: 'rgb(239, 68, 68)',
                        borderWidth: 1
                    },
                    {
                        label: 'Completed',
                        data: completed,
                        backgroundColor: 'rgba(79, 70, 229, 0.6)',
                        borderColor: 'rgb(79, 70, 229)',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: false,
                    },
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
    <?php elseif ($reportType === 'resource'): ?>
        // Prepare data for resource usage chart
        const resources = <?php echo json_encode(array_map(function($row) { return $row['name']; }, array_slice($reportData, 0, 10))); ?>;
        const reservations = <?php echo json_encode(array_map(function($row) { return $row['total_reservations']; }, array_slice($reportData, 0, 10))); ?>;
        const categories = <?php echo json_encode(array_map(function($row) { return $row['category']; }, array_slice($reportData, 0, 10))); ?>;
        
        const backgroundColors = resources.map((_, index) => {
            return categories[index] === 'facility' ? 'rgba(59, 130, 246, 0.6)' : 'rgba(16, 185, 129, 0.6)';
        });
        
        const borderColors = resources.map((_, index) => {
            return categories[index] === 'facility' ? 'rgb(59, 130, 246)' : 'rgb(16, 185, 129)';
        });
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: resources,
                datasets: [{
                    label: 'Number of Reservations',
                    data: reservations,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
    <?php elseif ($reportType === 'user'): ?>
        // Prepare data for user registration chart
        const dates = <?php echo json_encode(array_map(function($row) { return date('M d', strtotime($row['date'])); }, array_reverse($reportData))); ?>;
        const totalRegistrations = <?php echo json_encode(array_map(function($row) { return $row['total_registrations']; }, array_reverse($reportData))); ?>;
        const approved = <?php echo json_encode(array_map(function($row) { return $row['approved']; }, array_reverse($reportData))); ?>;
        const pending = <?php echo json_encode(array_map(function($row) { return $row['pending']; }, array_reverse($reportData))); ?>;
        const rejected = <?php echo json_encode(array_map(function($row) { return $row['rejected']; }, array_reverse($reportData))); ?>;
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Total Registrations',
                        data: totalRegistrations,
                        backgroundColor: 'rgba(79, 70, 229, 0.1)',
                        borderColor: 'rgb(79, 70, 229)',
                        tension: 0.1,
                        fill: true
                    },
                    {
                        label: 'Approved',
                        data: approved,
                        backgroundColor: 'transparent',
                        borderColor: 'rgb(34, 197, 94)',
                        tension: 0.1
                    },
                    {
                        label: 'Pending',
                        data: pending,
                        backgroundColor: 'transparent',
                        borderColor: 'rgb(234, 179, 8)',
                        tension: 0.1
                    },
                    {
                        label: 'Rejected',
                        data: rejected,
                        backgroundColor: 'transparent',
                        borderColor: 'rgb(239, 68, 68)',
                        tension: 0.1
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    
    <?php elseif ($reportType === 'financial'): ?>
        // Prepare data for financial chart
        const dates = <?php echo json_encode(array_map(function($row) { return date('M d', strtotime($row['date'])); }, array_reverse($reportData))); ?>;
        const revenue = <?php echo json_encode(array_map(function($row) { return $row['revenue']; }, array_reverse($reportData))); ?>;
        const paidReservations = <?php echo json_encode(array_map(function($row) { return $row['paid_reservations']; }, array_reverse($reportData))); ?>;
        
        // Create a chart with two y-axes
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: dates,
                datasets: [
                    {
                        label: 'Revenue (₱)',
                        data: revenue,
                        backgroundColor: 'rgba(59, 130, 246, 0.6)',
                        borderColor: 'rgb(59, 130, 246)',
                        borderWidth: 1,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Paid Reservations',
                        data: paidReservations,
                        type: 'line',
                        backgroundColor: 'transparent',
                        borderColor: 'rgb(16, 185, 129)',
                        borderWidth: 2,
                        tension: 0.1,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                        title: {
                            display: true,
                            text: 'Revenue (₱)'
                        },
                        beginAtZero: true
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        title: {
                            display: true,
                            text: 'Number of Reservations'
                        },
                        beginAtZero: true,
                        grid: {
                            drawOnChartArea: false
                        }
                    }
                }
            }
        });
    <?php endif; ?>
});
</script>