<?php
// Get all active resources
try {
    $stmt = $db->prepare("
        SELECT r.*, 
            CASE 
                WHEN r.category = 'equipment' THEN 
                    r.quantity - IFNULL((
                        SELECT SUM(ri.quantity) 
                        FROM reservation_items ri 
                        JOIN reservations res ON ri.reservation_id = res.id 
                        WHERE ri.resource_id = r.id AND res.status IN ('pending', 'approved', 'for_delivery', 'for_pickup')
                    ), 0) 
                ELSE r.quantity 
            END as available_quantity,
            CASE
                WHEN r.category = 'facility' THEN
                    (SELECT COUNT(*) 
                    FROM reservation_items ri 
                    JOIN reservations res ON ri.reservation_id = res.id 
                    WHERE ri.resource_id = r.id AND res.status = 'pending')
                ELSE 0
            END as has_pending_reservations,
            CASE
                WHEN r.category = 'facility' THEN
                    (SELECT COUNT(*) 
                    FROM reservation_items ri 
                    JOIN reservations res ON ri.reservation_id = res.id 
                    WHERE ri.resource_id = r.id AND res.status = 'approved')
                ELSE 0
            END as has_approved_reservations
        FROM resources r
        WHERE r.status = 'active' 
        ORDER BY r.category, r.name
    ");
    $stmt->execute();
    $resources = $stmt->fetchAll();
    
    // Group resources by category
    $facilities = [];
    $equipment = [];
    
    foreach ($resources as $resource) {
        if ($resource['category'] === 'facility') {
            $facilities[] = $resource;
        } else {
            $equipment[] = $resource;
        }
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>

<div class="max-w-6xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Available Resources</h1>
    
    <div class="mb-10">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">Facilities</h2>
        
        <?php if (empty($facilities)): ?>
            <p class="text-gray-600">No facilities available at this time.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($facilities as $facility): ?>
                    <?php 
                    // Determine facility status text and color
                    $statusText = ucfirst($facility['availability']);
                    $statusColor = 'text-green-500';
                    $facilityAvailable = true;
                    
                    if ($facility['availability'] !== 'available') {
                        $statusColor = 'text-red-500';
                        $facilityAvailable = false;
                    } elseif ($facility['has_approved_reservations'] > 0) {
                        $statusText = 'Reserved';
                        $statusColor = 'text-red-500';
                        $facilityAvailable = false;
                    } elseif ($facility['has_pending_reservations'] > 0) {
                        $statusText = 'Has Pending Reservation';
                        $statusColor = 'text-orange-500';
                        $facilityAvailable = false;
                    }
                    
                    // Check if facility has image
                    $hasImage = !empty($facility['image']) && file_exists($facility['image']);
                    ?>
                    
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                        <?php if ($hasImage): ?>
                            <div class="h-48 bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($facility['image']); ?>')"></div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-2"><?php echo $facility['name']; ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo $facility['description']; ?></p>
                            
                            <div class="flex justify-between text-sm mb-3">
                                <span class="text-gray-500">Status:</span>
                                <span class="<?php echo $statusColor; ?> font-medium">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            
                            <?php if ($facility['requires_payment']): ?>
                                <div class="flex justify-between text-sm mb-3">
                                    <span class="text-gray-500">Fee:</span>
                                    <span class="font-medium">₱<?php echo number_format($facility['payment_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <?php if ($facilityAvailable): ?>
                                    <a href="index.php?page=reservation&resource_id=<?php echo $facility['id']; ?>" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-300">Book Now</a>
                                <?php else: ?>
                                    <button disabled class="inline-block px-4 py-2 bg-gray-300 text-gray-600 rounded cursor-not-allowed">Currently Unavailable</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <div class="mb-10">
        <h2 class="text-xl font-semibold text-blue-700 mb-4">Equipment</h2>
        
        <?php if (empty($equipment)): ?>
            <p class="text-gray-600">No equipment available at this time.</p>
        <?php else: ?>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach ($equipment as $item): ?>
                    <?php
                    // Check if equipment has image
                    $hasImage = !empty($item['image']) && file_exists($item['image']);
                    ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden hover:shadow-lg transition duration-300">
                        <?php if ($hasImage): ?>
                            <div class="h-48 bg-cover bg-center" style="background-image: url('<?php echo htmlspecialchars($item['image']); ?>')"></div>
                        <?php endif; ?>
                        
                        <div class="p-6">
                            <h3 class="text-lg font-semibold mb-2"><?php echo $item['name']; ?></h3>
                            <p class="text-gray-600 mb-4"><?php echo $item['description']; ?></p>
                            
                            <div class="flex justify-between text-sm mb-2">
                                <span class="text-gray-500">Available Quantity:</span>
                                <span class="font-medium"><?php echo $item['available_quantity']; ?></span>
                            </div>
                            
                            <div class="flex justify-between text-sm mb-3">
                                <span class="text-gray-500">Status:</span>
                                <span class="inline-block px-2 py-1 rounded text-xs font-medium
                                    <?php echo $item['availability'] === 'available' ? 'bg-green-100 text-green-800' : ($item['availability'] === 'reserved' ? 'bg-orange-100 text-orange-800' : 'bg-red-100 text-red-800'); ?>">
                                    <?php echo ucfirst($item['availability']); ?>
                                </span>
                            </div>
                            
                            <?php if ($item['requires_payment']): ?>
                                <div class="flex justify-between text-sm mb-3">
                                    <span class="text-gray-500">Fee:</span>
                                    <span class="font-medium">₱<?php echo number_format($item['payment_amount'], 2); ?></span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mt-4">
                                <?php if ($item['availability'] === 'available' && $item['available_quantity'] > 0): ?>
                                    <a href="index.php?page=reservation&resource_id=<?php echo $item['id']; ?>" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition duration-300">Reserve</a>
                                <?php else: ?>
                                    <button disabled class="inline-block px-4 py-2 bg-gray-300 text-gray-600 rounded cursor-not-allowed">Unavailable</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>