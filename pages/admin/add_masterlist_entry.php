<?php
// We don't need to include session_manager as it's already included in index.php
// Just need database connection
require_once 'config/database.php';

$errors = [];
$success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate inputs
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $age = trim($_POST['age'] ?? '');
    $year_of_residency = trim($_POST['year_of_residency'] ?? '');
    $purok = trim($_POST['purok'] ?? '');
    
    if (empty($last_name)) {
        $errors[] = "Last Name is required";
    }
    
    if (empty($first_name)) {
        $errors[] = "First Name is required";
    }
    
    if (empty($contact_number)) {
        $errors[] = "Contact Number is required";
    }
    
    if (empty($age) || !is_numeric($age) || $age < 1 || $age > 120) {
        $errors[] = "Valid Age is required (between 1 and 120)";
    }
    
    if (empty($year_of_residency) || !is_numeric($year_of_residency) || $year_of_residency < 1900 || $year_of_residency > date('Y')) {
        $errors[] = "Valid Year of Residency is required (between 1900 and current year)";
    }
    
    if (empty($purok)) {
        $errors[] = "Purok is required";
    }
    
    // If no errors, insert into database
    if (empty($errors)) {
        try {
            $stmt = $db->prepare("INSERT INTO masterlist (last_name, first_name, middle_name, contact_number, age, year_of_residency, purok) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$last_name, $first_name, $middle_name, $contact_number, $age, $year_of_residency, $purok]);
            
            $_SESSION['success_message'] = "Masterlist entry added successfully!";
            header("Location: index.php?page=admin&section=masterlist");
            exit;
            
        } catch (PDOException $e) {
            $errors[] = "Database error: " . $e->getMessage();
        }
    }
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Add Masterlist Entry</h1>
        <a href="index.php?page=admin&section=masterlist" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
            ← Back to Masterlist
        </a>
    </div>

    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="mb-4">
                <h2 class="text-xl font-semibold text-gray-800">
                    <i class="fas fa-user-plus mr-2"></i> New Masterlist Entry
                </h2>
            </div>
            
            <form method="post" action="">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <div>
                        <label for="last_name" class="block text-sm font-medium text-gray-700 mb-1">Last Name <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="last_name" name="last_name" value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="first_name" class="block text-sm font-medium text-gray-700 mb-1">First Name <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="first_name" name="first_name" value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="middle_name" class="block text-sm font-medium text-gray-700 mb-1">Middle Name</label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="middle_name" name="middle_name" value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                    <div>
                        <label for="contact_number" class="block text-sm font-medium text-gray-700 mb-1">Contact Number <span class="text-red-600">*</span></label>
                        <input type="text" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="contact_number" name="contact_number" value="<?php echo isset($_POST['contact_number']) ? htmlspecialchars($_POST['contact_number']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="age" class="block text-sm font-medium text-gray-700 mb-1">Age <span class="text-red-600">*</span></label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="age" name="age" min="1" max="120" value="<?php echo isset($_POST['age']) ? htmlspecialchars($_POST['age']) : ''; ?>" required>
                    </div>
                    
                    <div>
                        <label for="year_of_residency" class="block text-sm font-medium text-gray-700 mb-1">Year of Residency <span class="text-red-600">*</span></label>
                        <input type="number" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="year_of_residency" name="year_of_residency" min="1900" max="<?php echo date('Y'); ?>" 
                               value="<?php echo isset($_POST['year_of_residency']) ? htmlspecialchars($_POST['year_of_residency']) : date('Y'); ?>" required>
                    </div>
                    
                    <div>
                        <label for="purok" class="block text-sm font-medium text-gray-700 mb-1">Purok <span class="text-red-600">*</span></label>
                        <select class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                                id="purok" name="purok" required>
                            <option value="">Select Purok</option>
                            <option value="Purok 1" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 1') ? 'selected' : ''; ?>>Purok 1</option>
                            <option value="Purok 2" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 2') ? 'selected' : ''; ?>>Purok 2</option>
                            <option value="Purok 3" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 3') ? 'selected' : ''; ?>>Purok 3</option>
                            <option value="Purok 4" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 4') ? 'selected' : ''; ?>>Purok 4</option>
                            <option value="Purok 5" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 5') ? 'selected' : ''; ?>>Purok 5</option>
                            <option value="Purok 6" <?php echo (isset($_POST['purok']) && $_POST['purok'] === 'Purok 6') ? 'selected' : ''; ?>>Purok 6</option>
                        </select>
                    </div>
                </div>
                
                <div class="mt-6 flex justify-end space-x-3">
                    <a href="index.php?page=admin&section=masterlist" class="px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-150">
                        Cancel
                    </a>
                    <button type="submit" class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-md transition-colors duration-150">
                        <i class="fas fa-plus mr-2"></i> Add Entry
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>