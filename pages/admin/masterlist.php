<?php
// We don't need to include session manager directly as it's included in index.php
// require_once '../../includes/session_manager.php';
// Just need the database connection
require_once 'config/database.php';

// Check if user is logged in and is an admin - already done in index.php

// Handle masterlist entry deletion
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $db->prepare("DELETE FROM masterlist WHERE id = ?");
    $stmt->execute([$id]);
    $_SESSION['success_message'] = "Masterlist entry deleted successfully!";
    header("Location: index.php?page=admin&section=masterlist");
    exit;
}

// Check for success or error messages
$success_message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$error_message = isset($_SESSION['error_message']) ? $_SESSION['error_message'] : '';
unset($_SESSION['success_message'], $_SESSION['error_message']);

// Fetch masterlist entries with pagination
$page = isset($_GET['page_num']) ? (int)$_GET['page_num'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

$stmt = $db->prepare("SELECT COUNT(*) FROM masterlist");
$stmt->execute();
$total_records = $stmt->fetchColumn();
$total_pages = ceil($total_records / $limit);

// Fix: Use directly in the query instead of parameters for LIMIT/OFFSET
$stmt = $db->prepare("SELECT * FROM masterlist ORDER BY last_name, first_name LIMIT $limit OFFSET $offset");
$stmt->execute();
$masterlist = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Add search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
if ($search) {
    // Fix: Use directly in the query instead of parameters for LIMIT/OFFSET
    $stmt = $db->prepare("SELECT * FROM masterlist WHERE 
        last_name LIKE ? OR 
        first_name LIKE ? OR 
        middle_name LIKE ? OR
        contact_number LIKE ? OR
        purok LIKE ?
        ORDER BY last_name, first_name LIMIT $limit OFFSET $offset");
    $search_param = "%$search%";
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $search_param]);
    $masterlist = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Count total filtered results for pagination
    $stmt = $db->prepare("SELECT COUNT(*) FROM masterlist WHERE 
        last_name LIKE ? OR 
        first_name LIKE ? OR 
        middle_name LIKE ? OR
        contact_number LIKE ? OR
        purok LIKE ?");
    $stmt->execute([$search_param, $search_param, $search_param, $search_param, $search_param]);
    $total_records = $stmt->fetchColumn();
    $total_pages = ceil($total_records / $limit);
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Masterlist Management</h1>
        <div class="flex items-center space-x-2">
            <a href="index.php?page=admin" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                ← Back to Dashboard
            </a>
            <a href="index.php?page=admin&section=users" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                <span class="flex items-center">
                    <i class="fas fa-users mr-2"></i>
                    Manage Users
                </span>
            </a>
        </div>
    </div>
    
    <?php if ($success_message): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
        <p><?php echo $success_message; ?></p>
    </div>
    <?php endif; ?>
    
    <?php if ($error_message): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
        <p><?php echo $error_message; ?></p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="flex flex-col md:flex-row md:items-center justify-between mb-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4 md:mb-0">
                    Masterlist Entries
                    <span class="text-gray-500 text-sm ml-2">(<?php echo $total_records; ?>)</span>
                </h2>
                <div class="flex items-center space-x-2">
                    <a href="index.php?page=admin&section=add_masterlist_entry" class="bg-blue-500 hover:bg-blue-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                        <i class="fas fa-plus mr-2"></i> Add New Entry
                    </a>
                    <a href="index.php?page=admin&section=import_masterlist" class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
                        <i class="fas fa-file-import mr-2"></i> Import from Excel
                    </a>
                    <a href="pages/admin/export_csv.php?export_type=masterlist<?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="bg-purple-500 hover:bg-purple-600 text-white px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150" target="_blank">
                        <i class="fas fa-file-export mr-2"></i> Export to CSV
                    </a>
                </div>
            </div>
            
            <!-- Search Form -->
            <form method="GET" action="index.php" class="mb-6">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="masterlist">
                
                <div class="flex flex-wrap md:flex-nowrap">
                    <div class="flex mr-2 md:w-64 w-full mb-2 md:mb-0">
                        <input type="text" name="search" placeholder="Search..." value="<?php echo htmlspecialchars($search); ?>" 
                            class="border-gray-300 rounded-l focus:ring-blue-500 focus:border-blue-500 block w-full">
                        <button type="submit" class="bg-blue-500 hover:bg-blue-600 text-white px-4 rounded-r">Search</button>
                    </div>
                    
                    <?php if ($search): ?>
                    <a href="index.php?page=admin&section=masterlist" class="bg-gray-300 hover:bg-gray-400 text-gray-800 px-4 py-2 rounded-md">Clear</a>
                    <?php endif; ?>
                </div>
            </form>
            
            <!-- Masterlist Table -->
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Last Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">First Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Middle Name</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact No.</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Age</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Year of Residency</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Purok</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php if (count($masterlist) > 0): ?>
                            <?php foreach ($masterlist as $entry): ?>
                                <tr class="hover:bg-gray-50">
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $entry['id']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($entry['last_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($entry['first_name']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($entry['middle_name'] ?? '-'); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($entry['contact_number']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $entry['age']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo $entry['year_of_residency']; ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap"><?php echo htmlspecialchars($entry['purok']); ?></td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="index.php?page=admin&section=edit_masterlist_entry&id=<?php echo $entry['id']; ?>" class="text-indigo-600 hover:text-indigo-900 mr-3">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="#" class="text-red-600 hover:text-red-900" onclick="confirmDelete(<?php echo $entry['id']; ?>)">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="px-6 py-4 text-center text-gray-500">No masterlist entries found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
            <div class="mt-6">
                <nav class="flex justify-center">
                    <ul class="flex">
                        <li class="<?php echo ($page <= 1) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            <a class="px-3 py-2 ml-0 leading-tight text-gray-500 bg-white border border-gray-300 rounded-l-lg hover:bg-gray-100 hover:text-gray-700 <?php echo ($page <= 1) ? 'pointer-events-none' : ''; ?>"
                               href="<?php echo ($page <= 1) ? '#' : 'index.php?page=admin&section=masterlist&page_num='.($page-1).(($search) ? '&search='.urlencode($search) : ''); ?>">
                                Previous
                            </a>
                        </li>
                        
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li>
                                <a class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 <?php echo ($page == $i) ? 'text-blue-600 bg-blue-50' : 'hover:bg-gray-100 hover:text-gray-700'; ?>"
                                   href="index.php?page=admin&section=masterlist&page_num=<?php echo $i; ?><?php echo ($search) ? '&search='.urlencode($search) : ''; ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="<?php echo ($page >= $total_pages) ? 'opacity-50 cursor-not-allowed' : ''; ?>">
                            <a class="px-3 py-2 leading-tight text-gray-500 bg-white border border-gray-300 rounded-r-lg hover:bg-gray-100 hover:text-gray-700 <?php echo ($page >= $total_pages) ? 'pointer-events-none' : ''; ?>"
                               href="<?php echo ($page >= $total_pages) ? '#' : 'index.php?page=admin&section=masterlist&page_num='.($page+1).(($search) ? '&search='.urlencode($search) : ''); ?>">
                                Next
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
function confirmDelete(id) {
    if (confirm("Are you sure you want to delete this masterlist entry? This action cannot be undone.")) {
        window.location.href = `index.php?page=admin&section=masterlist&delete=${id}`;
    }
}
</script>