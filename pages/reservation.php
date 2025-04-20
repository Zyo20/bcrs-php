<?php
// Check if user is logged in
if (!isLoggedIn()) {
    $_SESSION['flash_message'] = "Please login to make a reservation.";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php?page=login");
    exit;
}

// Check if user is blacklisted
try {
    $stmt = $db->prepare("SELECT blacklisted FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    
    if ($user && $user['blacklisted'] == 1) {
        $_SESSION['flash_message'] = "Your account has been blacklisted. Please visit the barangay office for assistance.";
        $_SESSION['flash_type'] = "error";
        header("Location: index.php");
        exit;
    }
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get user info
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $userInfo = $stmt->fetch();
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Get all active resources
try {
    // Modified query to account for pending reservations
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
                    WHERE ri.resource_id = r.id AND res.status IN ('pending', 'approved'))
                ELSE 0
            END as has_pending_reservations
        FROM resources r
        WHERE r.status = 'active' AND r.availability = 'available'
        HAVING CASE WHEN r.category = 'equipment' THEN available_quantity > 0 ELSE 1 = 1 END
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

// Pre-select resource if specified in URL
$preSelectedResource = null;
if (isset($_GET['resource_id'])) {
    try {
        $stmt = $db->prepare("SELECT * FROM resources WHERE id = ? AND status = 'active' AND availability = 'available'");
        $stmt->execute([$_GET['resource_id']]);
        $preSelectedResource = $stmt->fetch();
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize input
    $landmark = sanitize($_POST['landmark']);
    $address = sanitize($_POST['address'] ?? $userInfo['address']);
    $purok = sanitize($_POST['purok'] ?? $userInfo['purok']);
    $startDate = sanitize($_POST['start_date']);
    $startTime = sanitize($_POST['start_time']);
    $endDate = sanitize($_POST['end_date']);
    $endTime = sanitize($_POST['end_time']);
    $resourceItems = isset($_POST['resources']) ? $_POST['resources'] : [];
    $resourceQuantities = isset($_POST['quantities']) ? $_POST['quantities'] : [];
    $notes = sanitize($_POST['notes']);
    
    // Validate form
    $errors = [];
    
    if (empty($address)) {
        $errors[] = "Address is required";
    }
    
    if (empty($purok)) {
        $errors[] = "Purok is required";
    }
    
    if (empty($startDate) || empty($startTime) || empty($endDate) || empty($endTime)) {
        $errors[] = "Start and end date/time are required";
    }
    
    if (empty($resourceItems)) {
        $errors[] = "Please select at least one resource to reserve";
    }
    
    // Check for valid dates
    $startDateTime = $startDate . ' ' . $startTime;
    $endDateTime = $endDate . ' ' . $endTime;
    
    $currentDateTime = date('Y-m-d H:i:s');
    $minDateTime = date('Y-m-d H:i:s', strtotime('+3 days'));
    
    if ($startDateTime <= $currentDateTime) {
        $errors[] = "Start date must be in the future";
    }
    
    if ($startDateTime < $minDateTime) {
        $errors[] = "Reservations must be made at least 3 days in advance";
    }
    
    if ($endDateTime <= $startDateTime) {
        $errors[] = "End date must be after start date";
    }
    
    // Check resource availability
    $requiresPayment = false;
    $gymReservation = false;
    $totalPaymentAmount = 0;
    $resourceNames = [];
    
    foreach ($resourceItems as $resourceId) {
        $quantity = (int)($resourceQuantities[$resourceId] ?? 1);
        
        try {
            $stmt = $db->prepare("SELECT * FROM resources WHERE id = ?");
            $stmt->execute([$resourceId]);
            $resource = $stmt->fetch();
            
            if (!$resource || $resource['status'] !== 'active' || $resource['availability'] !== 'available') {
                $errors[] = "Resource '{$resource['name']}' is not available";
                continue;
            }
            
            // Store resource name for payment page display
            $resourceNames[$resourceId] = $resource['name'];
            
            // Check quantity limits
            if ($resource['category'] === 'equipment') {
                if ($resource['name'] === 'Tent' && $quantity > 2) {
                    $errors[] = "Maximum 1 tent per reservation";
                } elseif ($resource['name'] === 'Chairs' && $quantity > 20) {
                    $errors[] = "Maximum 20 chairs per reservation";
                } elseif ($quantity > $resource['quantity']) {
                    $errors[] = "Not enough {$resource['name']} available (requested: $quantity, available: {$resource['quantity']})";
                }
            }
            
            // Check for payment requirement
            if ($resource['requires_payment']) {
                $requiresPayment = true;
                $totalPaymentAmount += $resource['payment_amount'];
                if ($resource['name'] === 'Gymnasium') {
                    $gymReservation = true;
                }
            }
            
            // Check for double booking (facilities)
            if ($resource['category'] === 'facility') {
                $stmt = $db->prepare("
                    SELECT r.* FROM reservations r
                    JOIN reservation_items ri ON r.id = ri.reservation_id
                    WHERE ri.resource_id = ? 
                    AND r.status NOT IN ('cancelled')
                    AND (
                        (r.start_datetime <= ? AND r.end_datetime >= ?) OR
                        (r.start_datetime <= ? AND r.end_datetime >= ?) OR
                        (r.start_datetime >= ? AND r.end_datetime <= ?)
                    )
                ");
                $stmt->execute([
                    $resourceId, 
                    $startDateTime, $startDateTime,
                    $endDateTime, $endDateTime,
                    $startDateTime, $endDateTime
                ]);
                $existingReservation = $stmt->fetch();
                
                if ($existingReservation) {
                    $errors[] = "The {$resource['name']} is already reserved during the selected time";
                }
            }
        } catch (PDOException $e) {
            $errors[] = "Error checking resource: " . $e->getMessage();
        }
    }
    
    // If no errors, store reservation data in session and redirect to payment page
    if (empty($errors)) {
        try {
            // Store reservation data in session
            $_SESSION['reservation_data'] = [
                'landmark' => $landmark,
                'address' => $address,
                'purok' => $purok,
                'start_datetime' => $startDateTime,
                'end_datetime' => $endDateTime,
                'resource_items' => $resourceItems,
                'resource_quantities' => $resourceQuantities,
                'resource_names' => $resourceNames,
                'notes' => $notes,
                'requires_payment' => $requiresPayment,
                'gym_reservation' => $gymReservation,
                'payment_amount' => $totalPaymentAmount
            ];
            
            // Redirect to payment information page
            header("Location: index.php?page=payment_information");
            exit;
            
        } catch (Exception $e) {
            $errors[] = "Error: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-4xl mx-auto">
    <h1 class="text-2xl font-bold text-blue-800 mb-6">Make a Reservation</h1>
    
    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 text-red-700 p-4 rounded mb-6">
            <ul class="list-disc pl-5">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" enctype="multipart/form-data" x-data="{ 
            selectedResources: [],
            isGymSelected: <?php echo $preSelectedResource && $preSelectedResource['name'] === 'Gymnasium' ? 'true' : 'false'; ?>,
            checkGymSelected() {
                this.isGymSelected = this.selectedResources.includes('1');
            }
        }">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <!-- Include jQuery and Select2 in the head section -->
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
            <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
            
            <!-- Custom styles for Select2 -->
            <style>
                .select2-container {
                    width: 100% !important;
                }
                .select2-container--default .select2-selection--multiple {
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                }
                .select2-container--default.select2-container--focus .select2-selection--multiple {
                    border-color: #3b82f6;
                    outline: 0;
                }
                .select2-container--default .select2-selection--multiple .select2-selection__choice {
                    background-color: #e5e7eb;
                    border: 1px solid #d1d5db;
                    border-radius: 0.25rem;
                    padding: 2px 8px;
                }
                .select2-dropdown {
                    border: 1px solid #d1d5db;
                    border-radius: 0.375rem;
                }
            </style>
            
            <!-- Location Information -->
            <div class="mb-6">
                <div class="bg-blue-50 rounded-lg p-6">
                    <h2 class="text-xl font-semibold text-blue-700 mb-3">Reservation Guidelines</h2>
                    
                    <ul class="list-disc pl-5 text-gray-700 space-y-2">
                        <li>Reservations must be made at least 3 days in advance</li>
                        <li>Gym bookings require payment via GCash</li>
                        <li>Maximum of 1 tent per reservation</li>
                        <li>Maximum of 20 chairs per reservation</li>
                        <li>All equipment must be returned in good condition</li>
                        <li>Cancellations should be made at least 24 hours before the reservation date</li>
                        <li>For special requests, please visit the barangay office</li>
                    </ul>
                </div>
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Location Information</h2>
                
                <div class="mb-4">
                    <label for="landmark" class="block text-gray-700 mb-1">Landmark (Optional)</label>
                    <input type="text" id="landmark" name="landmark" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo isset($landmark) ? $landmark : ''; ?>">
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="address" class="block text-gray-700 mb-1">Complete Address <span class="text-red-500">*</span></label>
                        <textarea id="address" name="address" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" rows="2" required><?php echo isset($address) ? $address : $userInfo['address']; ?></textarea>
                    </div>
                    
                    <div>
                        <label for="purok" class="block text-gray-700 mb-1">Purok <span class="text-red-500">*</span></label>
                        <select id="purok" name="purok" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" required>
                            <option value="">Select Purok</option>
                            <option value="Purok 1" <?php echo (isset($purok) && $purok === 'Purok 1') || $userInfo['purok'] === 'Purok 1' ? 'selected' : ''; ?>>Purok 1</option>
                            <option value="Purok 2" <?php echo (isset($purok) && $purok === 'Purok 2') || $userInfo['purok'] === 'Purok 2' ? 'selected' : ''; ?>>Purok 2</option>
                            <option value="Purok 3" <?php echo (isset($purok) && $purok === 'Purok 3') || $userInfo['purok'] === 'Purok 3' ? 'selected' : ''; ?>>Purok 3</option>
                            <option value="Purok 4" <?php echo (isset($purok) && $purok === 'Purok 4') || $userInfo['purok'] === 'Purok 4' ? 'selected' : ''; ?>>Purok 4</option>
                            <option value="Purok 5" <?php echo (isset($purok) && $purok === 'Purok 5') || $userInfo['purok'] === 'Purok 5' ? 'selected' : ''; ?>>Purok 5</option>
                            <option value="Purok 6" <?php echo (isset($purok) && $purok === 'Purok 6') || $userInfo['purok'] === 'Purok 6' ? 'selected' : ''; ?>>Purok 6</option>
                        </select>
                    </div>
                </div>
            </div>
            
            <!-- Date and Time -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Date and Time</h2>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                    <div>
                        <label for="start_date" class="block text-gray-700 mb-1">Start Date <span class="text-red-500">*</span></label>
                        <input type="date" id="start_date" name="start_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" min="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" value="<?php echo isset($startDate) ? $startDate : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="start_time" class="block text-gray-700 mb-1">Start Time <span class="text-red-500">*</span></label>
                        <input type="time" id="start_time" name="start_time" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo isset($startTime) ? $startTime : '08:00'; ?>" required>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <label for="end_date" class="block text-gray-700 mb-1">End Date <span class="text-red-500">*</span></label>
                        <input type="date" id="end_date" name="end_date" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" min="<?php echo date('Y-m-d', strtotime('+3 days')); ?>" value="<?php echo isset($endDate) ? $endDate : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="end_time" class="block text-gray-700 mb-1">End Time <span class="text-red-500">*</span></label>
                        <input type="time" id="end_time" name="end_time" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" value="<?php echo isset($endTime) ? $endTime : '17:00'; ?>" required>
                    </div>
                </div>
            </div>
            
            <!-- Resources Selection -->
            <div class="mb-6">
                <h2 class="text-lg font-semibold text-blue-700 mb-4">Select Resources</h2>
                
                <!-- Facilities -->
                <?php if (!empty($facilities)): ?>
                    <div class="mb-4">
                        <h3 class="font-medium text-gray-800 mb-2">Facilities:</h3>
                        <div class="relative">
                            <select id="facilities_dropdown" name="resources[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" size="<?php echo min(count($facilities), 4); ?>">
                                <?php foreach ($facilities as $facility): ?>
                                    <option value="<?php echo $facility['id']; ?>" 
                                            <?php echo ($preSelectedResource && $preSelectedResource['id'] === $facility['id']) ? 'selected' : ''; ?>
                                            data-requires-payment="<?php echo $facility['requires_payment'] ? '1' : '0'; ?>"
                                            data-payment-amount="<?php echo $facility['payment_amount']; ?>"
                                            data-description="<?php echo htmlspecialchars($facility['description']); ?>"
                                            <?php echo $facility['name'] === 'Gymnasium' ? 'data-is-gym="1"' : ''; ?>>
                                        <?php echo $facility['name']; ?>
                                        <?php if ($facility['requires_payment']): ?>
                                            (₱<?php echo number_format($facility['payment_amount'], 2); ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="facility_descriptions" class="mt-2 text-sm text-gray-600"></div>
                    </div>
                <?php endif; ?>
                
                <!-- Equipment -->
                <?php if (!empty($equipment)): ?>
                    <div>
                        <h3 class="font-medium text-gray-800 mb-2">Equipment:</h3>
                        <div class="relative">
                            <select id="equipment_dropdown" name="resources[]" multiple class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" size="<?php echo min(count($equipment), 4); ?>">
                                <?php foreach ($equipment as $item): ?>
                                    <option value="<?php echo $item['id']; ?>" 
                                            <?php echo ($preSelectedResource && $preSelectedResource['id'] === $item['id']) ? 'selected' : ''; ?>
                                            data-description="<?php echo htmlspecialchars($item['description']); ?>"
                                            data-max-quantity="<?php echo $item['available_quantity']; ?>"
                                            data-name="<?php echo htmlspecialchars($item['name']); ?>">
                                        <?php echo $item['name']; ?> (Available: <?php echo $item['available_quantity']; ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div id="equipment_descriptions" class="mt-2 text-sm text-gray-600"></div>
                        
                        <!-- Dynamic quantity inputs for selected equipment -->
                        <div id="equipment_quantities" class="mt-3 space-y-2"></div>
                    </div>
                <?php endif; ?>
                
                <?php if (empty($facilities) && empty($equipment)): ?>
                    <p class="text-gray-600">No resources available for reservation at this time.</p>
                <?php endif; ?>
            </div>
            
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const facilitiesDropdown = document.getElementById('facilities_dropdown');
                    const equipmentDropdown = document.getElementById('equipment_dropdown');
                    const facilityDescriptions = document.getElementById('facility_descriptions');
                    const equipmentDescriptions = document.getElementById('equipment_descriptions');
                    const equipmentQuantities = document.getElementById('equipment_quantities');
                    
                    // Initialize Select2
                    if (facilitiesDropdown) {
                        $(facilitiesDropdown).select2();
                    }
                    
                    if (equipmentDropdown) {
                        $(equipmentDropdown).select2();
                        
                        // Use Select2 specific events for better handling of equipment quantities
                        $(equipmentDropdown).on('select2:select select2:unselect', function(e) {
                            updateEquipmentSelections();
                        });
                    }
                    
                    // Function to update facility descriptions
                    function updateFacilityDescriptions() {
                        facilityDescriptions.innerHTML = '';
                        let isGymSelected = false;
                        
                        if (facilitiesDropdown) {
                            Array.from(facilitiesDropdown.selectedOptions).forEach(option => {
                                const description = option.getAttribute('data-description');
                                const requiresPayment = option.getAttribute('data-requires-payment') === '1';
                                const paymentAmount = option.getAttribute('data-payment-amount');
                                
                                if (option.getAttribute('data-is-gym') === '1') {
                                    isGymSelected = true;
                                }
                                
                                const descDiv = document.createElement('div');
                                descDiv.classList.add('mb-1', 'pl-1', 'border-l-2', 'border-blue-300');
                                
                                let content = `<strong>${option.text.split(' (')[0]}</strong>: ${description}`;
                                if (requiresPayment) {
                                    content += ` <span class="text-red-600">(Requires payment: ₱${parseFloat(paymentAmount).toFixed(2)})</span>`;
                                }
                                
                                descDiv.innerHTML = content;
                                facilityDescriptions.appendChild(descDiv);
                            });
                        }
                        
                        // Update Alpine.js data if needed
                        if (typeof Alpine !== 'undefined') {
                            const form = document.querySelector('form[x-data]');
                            if (form && form.__x) {
                                form.__x.updateData('isGymSelected', isGymSelected);
                            }
                        }
                    }
                    
                    // Function to update equipment descriptions and quantity inputs
                    function updateEquipmentSelections() {
                        equipmentDescriptions.innerHTML = '';
                        equipmentQuantities.innerHTML = '';
                        
                        if (equipmentDropdown) {
                            // Get all selected options from the real select element
                            const selectedOptions = $(equipmentDropdown).select2('data');
                            
                            selectedOptions.forEach(optionData => {
                                const option = equipmentDropdown.querySelector(`option[value="${optionData.id}"]`);
                                if (!option) return;
                                
                                const id = option.value;
                                const name = option.getAttribute('data-name');
                                const description = option.getAttribute('data-description');
                                const maxQuantity = parseInt(option.getAttribute('data-max-quantity'));
                                
                                // Add description
                                const descDiv = document.createElement('div');
                                descDiv.classList.add('mb-1', 'pl-1', 'border-l-2', 'border-blue-300');
                                descDiv.innerHTML = `<strong>${name}</strong>: ${description}`;
                                equipmentDescriptions.appendChild(descDiv);
                                
                                // Add quantity input
                                const quantityDiv = document.createElement('div');
                                quantityDiv.classList.add('flex', 'items-center', 'gap-2', 'mb-3', 'p-2', 'bg-gray-50', 'rounded', 'border', 'border-gray-200');
                                
                                // Store previous quantity value if it exists
                                const prevInput = document.querySelector(`input[name="quantities[${id}]"]`);
                                const prevValue = prevInput ? prevInput.value : 1;
                                
                                quantityDiv.innerHTML = `
                                    <label for="quantity_${id}" class="text-sm font-medium text-gray-700">${name} quantity:</label>
                                    <input type="number" id="quantity_${id}" 
                                           name="quantities[${id}]" 
                                           min="1" 
                                           max="${maxQuantity}" 
                                           value="${prevValue}" 
                                           class="w-20 px-2 py-1 border border-gray-300 rounded text-sm">
                                    <span class="text-xs text-gray-500">(Max: ${maxQuantity})</span>
                                `;
                                equipmentQuantities.appendChild(quantityDiv);
                            });
                        }
                    }
                    
                    // Add event listeners
                    if (facilitiesDropdown) {
                        $(facilitiesDropdown).on('change', updateFacilityDescriptions);
                        // Initialize
                        updateFacilityDescriptions();
                    }
                    
                    // Initialize equipment descriptions and quantities
                    updateEquipmentSelections();
                });
            </script>
            
            <!-- Additional Notes -->
            <div class="mb-6">
                <label for="notes" class="block text-gray-700 mb-1">Purpose of Reservation <span class="text-red-500">*</span></label>
                <textarea id="notes" name="notes" class="w-full px-3 py-2 border border-gray-300 rounded focus:outline-none focus:border-blue-500" rows="3" required><?php echo isset($notes) ? $notes : ''; ?></textarea>
            </div>
            
            <div class="text-center">
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Continue to Payment</button>
            </div>
        </form>
    </div>
</div>