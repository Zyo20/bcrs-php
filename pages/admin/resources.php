<?php
// Initialize variables
$resources = [];
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name_asc';
$category = isset($_GET['category']) ? $_GET['category'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build the SQL query based on filters
$query = "SELECT * FROM resources WHERE 1=1";
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

// Get resources from database
try {
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $resources = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $errorMessage = "Database error: " . $e->getMessage();
}

// Get statistics
try {
    // Total resources count
    $totalCount = count($resources);
    
    // Count by category
    $facilityCount = 0;
    $equipmentCount = 0;
    
    // Count by availability
    $availableCount = 0;
    $reservedCount = 0;
    $maintenanceCount = 0;
    
    foreach ($resources as $resource) {
        if ($resource['category'] === 'facility') {
            $facilityCount++;
        } else {
            $equipmentCount++;
        }
        
        if ($resource['availability'] === 'available') {
            $availableCount++;
        } elseif ($resource['availability'] === 'reserved') {
            $reservedCount++;
        } elseif ($resource['availability'] === 'maintenance') {
            $maintenanceCount++;
        }
    }
} catch (Exception $e) {
    $errorMessage = "Error calculating statistics: " . $e->getMessage();
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
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-blue-800">Resource Management</h1>
            <p class="text-gray-600 mt-1">Manage resources for the booking system</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="index.php?page=admin&section=add_resource" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Add New Resource
            </a>
        </div>
    </div>

    <?php if (!empty($flashMessage)): ?>
        <div class="bg-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-50 border-l-4 border-<?php echo $flashType === 'error' ? 'red' : 'green'; ?>-500 p-4 mb-6">
            <div class="flex">
                <div class="flex-shrink-0">
                    <?php if ($flashType === 'error'): ?>
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
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

    <!-- Resource Dashboard -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-8">
        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-blue-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Total Resources</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $totalCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-green-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Available Resources</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $availableCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-yellow-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Reserved Resources</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $reservedCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white overflow-hidden shadow rounded-lg">
            <div class="px-4 py-5 sm:p-6">
                <div class="flex items-center">
                    <div class="flex-shrink-0 bg-red-500 rounded-md p-3">
                        <svg class="h-6 w-6 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                    </div>
                    <div class="ml-5 w-0 flex-1">
                        <dl>
                            <dt class="text-sm font-medium text-gray-500 truncate">Maintenance Resources</dt>
                            <dd class="flex items-baseline">
                                <div class="text-2xl font-semibold text-gray-900"><?php echo $maintenanceCount; ?></div>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Resource Filters and Search -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <form action="" method="GET" class="space-y-4 sm:space-y-0 sm:flex sm:items-end sm:space-x-4">
                <input type="hidden" name="page" value="admin">
                <input type="hidden" name="section" value="resources">
                
                <div class="w-full sm:w-1/5">
                    <label for="search" class="block text-sm font-medium text-gray-700 mb-1">Search</label>
                    <input type="text" name="search" id="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search resources..." class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                </div>

                <div class="w-full sm:w-1/5">
                    <label for="category" class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                    <select name="category" id="category" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="">All Categories</option>
                        <option value="facility" <?php echo $category === 'facility' ? 'selected' : ''; ?>>Facilities</option>
                        <option value="equipment" <?php echo $category === 'equipment' ? 'selected' : ''; ?>>Equipment</option>
                    </select>
                </div>

                <div class="w-full sm:w-1/5">
                    <label for="filter" class="block text-sm font-medium text-gray-700 mb-1">Availability</label>
                    <select name="filter" id="filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="all" <?php echo $filter === 'all' ? 'selected' : ''; ?>>All Status</option>
                        <option value="available" <?php echo $filter === 'available' ? 'selected' : ''; ?>>Available</option>
                        <option value="reserved" <?php echo $filter === 'reserved' ? 'selected' : ''; ?>>Reserved</option>
                        <option value="maintenance" <?php echo $filter === 'maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                    </select>
                </div>

                <div class="w-full sm:w-1/5">
                    <label for="sort" class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                    <select name="sort" id="sort" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="name_asc" <?php echo $sort === 'name_asc' ? 'selected' : ''; ?>>Name (A-Z)</option>
                        <option value="name_desc" <?php echo $sort === 'name_desc' ? 'selected' : ''; ?>>Name (Z-A)</option>
                        <option value="created_asc" <?php echo $sort === 'created_asc' ? 'selected' : ''; ?>>Oldest First</option>
                        <option value="created_desc" <?php echo $sort === 'created_desc' ? 'selected' : ''; ?>>Newest First</option>
                        <option value="category_asc" <?php echo $sort === 'category_asc' ? 'selected' : ''; ?>>Category (A-Z)</option>
                        <option value="category_desc" <?php echo $sort === 'category_desc' ? 'selected' : ''; ?>>Category (Z-A)</option>
                    </select>
                </div>

                <div class="w-full sm:w-auto flex space-x-2">
                    <button type="submit" class="w-full inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z" />
                        </svg>
                        Filter
                    </button>
                    <a href="pages/admin/export_csv.php?export_type=resources&filter=<?php echo urlencode($filter); ?>&sort=<?php echo urlencode($sort); ?>&category=<?php echo urlencode($category); ?>&search=<?php echo urlencode($search); ?>" 
                       class="w-full inline-flex items-center justify-center px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500"
                       target="_blank"> <!-- Open in new tab to avoid disrupting the current view -->
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                        </svg>
                        Export
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Resource List -->
    <div class="bg-white shadow overflow-hidden rounded-lg">
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-50 border-l-4 border-red-400 p-4 mb-6">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-red-700"><?php echo $errorMessage; ?></p>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($resources)): ?>
            <div class="text-center py-12">
                <svg class="mx-auto h-12 w-12 text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900">No resources found</h3>
                <p class="mt-1 text-sm text-gray-500">
                    <?php if (!empty($search) || $filter !== 'all' || !empty($category)): ?>
                        No resources match the current filters. Try adjusting your search criteria.
                    <?php else: ?>
                        There are no resources in the system yet.
                    <?php endif; ?>
                </p>
                <div class="mt-6">
                    <a href="index.php?page=admin&section=add_resource" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                        </svg>
                        Add New Resource
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="flex flex-col">
                <div class="-my-2 overflow-x-auto sm:-mx-6 lg:-mx-8">
                    <div class="py-2 align-middle inline-block min-w-full sm:px-6 lg:px-8">
                        <div class="overflow-hidden">
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Resource Name</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Category</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
                                        <th scope="col" class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($resources as $resource): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div>
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($resource['name']); ?></div>
                                                        <div class="text-sm text-gray-500 truncate max-w-xs"><?php echo htmlspecialchars(substr($resource['description'], 0, 80) . (strlen($resource['description']) > 80 ? '...' : '')); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php if (!empty($resource['image']) && file_exists($resource['image'])): ?>
                                                    <img src="<?php echo htmlspecialchars($resource['image']); ?>" alt="<?php echo htmlspecialchars($resource['name']); ?>" class="h-12 w-12 object-cover rounded-md">
                                                <?php else: ?>
                                                    <div class="h-12 w-12 rounded-md bg-gray-200 flex items-center justify-center">
                                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                                        </svg>
                                                    </div>
                                                <?php endif; ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $resource['category'] === 'facility' ? 'bg-purple-100 text-purple-800' : 'bg-blue-100 text-blue-800'; ?>">
                                                    <?php echo ucfirst($resource['category']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $resource['quantity']; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <?php 
                                                $statusColor = '';
                                                switch ($resource['availability']) {
                                                    case 'available':
                                                        $statusColor = 'bg-green-100 text-green-800';
                                                        break;
                                                    case 'reserved':
                                                        $statusColor = 'bg-yellow-100 text-yellow-800';
                                                        break;
                                                    case 'maintenance':
                                                        $statusColor = 'bg-red-100 text-red-800';
                                                        break;
                                                }
                                                ?>
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $statusColor; ?>">
                                                    <?php echo ucfirst($resource['availability']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                                <?php echo date('M j, Y', strtotime($resource['created_at'])); ?>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <a href="index.php?page=admin&section=edit_resource&id=<?php echo $resource['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    document.getElementById('resourceId').value = id;
    document.getElementById('resourceName').textContent = name;
    document.getElementById('deleteModal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('deleteModal');
    if (event.target === modal) {
        closeModal();
    }
}
</script>