<?php
// Admin only access
if (!isAdmin()) {
    redirect('index');
}

// Check for required extensions
$requiredExtensions = ['zip', 'xml'];
$missingExtensions = [];
foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

require 'vendor/autoload.php'; // Include Composer's autoloader

use PhpOffice\PhpSpreadsheet\IOFactory;

$pageTitle = "Import Users";
$errors = [];
$successMessages = [];
$importedCount = 0;
$skippedCount = 0;

// Display error if required extensions are missing
if (!empty($missingExtensions)) {
    $errors[] = "Required PHP extension(s) missing: " . implode(', ', $missingExtensions) . 
                ". Please enable these extensions in your php.ini file and restart your web server.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['user_file'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
        $errors[] = "CSRF token validation failed. Please try again.";
    } else {
        // Skip processing if required extensions are missing
        if (!empty($missingExtensions)) {
            $errors[] = "Cannot process file due to missing PHP extensions.";
        } else {
            $file = $_FILES['user_file'];

            // Check for upload errors
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "File upload failed with error code: " . $file['error'];
            } else {
                $allowedMimeTypes = [
                    'application/vnd.ms-excel', // .xls
                    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' // .xlsx
                ];
                $fileMimeType = mime_content_type($file['tmp_name']);

                if (!in_array($fileMimeType, $allowedMimeTypes)) {
                    $errors[] = "Invalid file type. Please upload an Excel file (.xls or .xlsx).";
                } else {
                    try {
                        $spreadsheet = IOFactory::load($file['tmp_name']);
                        $sheet = $spreadsheet->getActiveSheet();
                        $highestRow = $sheet->getHighestRow();
                        $highestColumn = $sheet->getHighestColumn();

                        // Assuming header row is 1
                        // Expected columns: First Name, Last Name, Email, Contact Number, Address
                        $header = $sheet->rangeToArray('A1:' . $highestColumn . '1', NULL, TRUE, FALSE)[0];
                        // Basic header check (can be made more robust)
                        if (count($header) < 5) {
                            $errors[] = "Invalid Excel format. Expected at least 5 columns: First Name, Last Name, Email, Contact Number, Address.";
                        } else {
                            $db->beginTransaction();

                            for ($row = 2; $row <= $highestRow; $row++) {
                                $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, NULL, TRUE, FALSE)[0];

                                $firstName = sanitize($rowData[0] ?? '');
                                $lastName = sanitize($rowData[1] ?? '');
                                $email = sanitize($rowData[2] ?? '');
                                $contactNumber = sanitize($rowData[3] ?? '');
                                $address = sanitize($rowData[4] ?? '');
                                
                                // Basic validation
                                if (empty($firstName) || empty($lastName) || empty($email) || empty($contactNumber) || empty($address) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                    $errors[] = "Skipping row $row: Invalid or missing data (First Name, Last Name, Email, Contact, Address required; Email must be valid).";
                                    $skippedCount++;
                                    continue;
                                }

                                // Check if email already exists
                                $stmtCheck = $db->prepare("SELECT id FROM users WHERE email = ? UNION SELECT id FROM admins WHERE email = ?");
                                $stmtCheck->execute([$email, $email]);
                                if ($stmtCheck->fetch()) {
                                    $errors[] = "Skipping row $row: Email '$email' already exists.";
                                    $skippedCount++;
                                    continue;
                                }

                                // Generate a default password (e.g., 'password123') and hash it
                                $defaultPassword = 'password123'; // Consider making this more secure or configurable
                                $hashedPassword = password_hash($defaultPassword, PASSWORD_DEFAULT);

                                // Insert user (default status: approved)
                                $stmtInsert = $db->prepare("
                                    INSERT INTO users (first_name, last_name, email, password, contact_number, address, status)
                                    VALUES (?, ?, ?, ?, ?, ?, 'approved')
                                ");
                                if ($stmtInsert->execute([$firstName, $lastName, $email, $hashedPassword, $contactNumber, $address])) {
                                    $importedCount++;
                                } else {
                                    $errors[] = "Failed to insert user from row $row: " . $stmtInsert->errorInfo()[2];
                                    $skippedCount++;
                                }
                            }

                            if (empty($errors)) {
                                $db->commit();
                                $successMessages[] = "Successfully imported $importedCount users.";
                                if ($skippedCount > 0) {
                                    $successMessages[] = "Skipped $skippedCount rows due to errors or existing emails.";
                                }
                            } else {
                                $db->rollBack();
                                $errors[] = "Import failed due to errors. No users were imported.";
                                $importedCount = 0; // Reset count on rollback
                            }
                        }
                    } catch (\PhpOffice\PhpSpreadsheet\Reader\Exception $e) {
                        $errors[] = "Error reading file: " . $e->getMessage();
                    } catch (PDOException $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $errors[] = "Database error during import: " . $e->getMessage();
                    } catch (Exception $e) {
                        $errors[] = "An unexpected error occurred: " . $e->getMessage();
                    }
                }
            }
            // Clean up uploaded file
            if (isset($file['tmp_name']) && file_exists($file['tmp_name'])) {
                unlink($file['tmp_name']);
            }
        }
    }
}

?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-blue-800 mb-6">Import Users from Excel</h1>

    <div class="bg-blue-100 border border-blue-400 text-blue-700 px-4 py-3 rounded relative mb-6" role="alert">
        <strong class="font-bold">Instructions:</strong>
        <ul class="list-disc pl-5 mt-2">
            <li>Upload an Excel file (.xls or .xlsx).</li>
            <li>The first row should be the header row.</li>
            <li>Required columns: <strong>First Name, Last Name, Email, Contact Number, Address</strong> (in that order).</li>
            <li>Users will be imported with 'approved' status and a default password ('password123').</li>
            <li>Existing email addresses will be skipped.</li>
        </ul>
    </div>

    <?php if (!empty($errors)): ?>
        <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Errors:</strong>
            <ul class="list-disc pl-5 mt-2">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <?php if (!empty($successMessages)): ?>
        <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-6" role="alert">
            <strong class="font-bold">Import Result:</strong>
            <ul class="list-disc pl-5 mt-2">
                <?php foreach ($successMessages as $msg): ?>
                    <li><?php echo htmlspecialchars($msg); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="bg-white rounded-lg shadow-md p-6">
        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="mb-4">
                <label for="user_file" class="block text-gray-700 font-bold mb-2">Select Excel File:</label>
                <input type="file" id="user_file" name="user_file" class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline" accept=".xls,.xlsx" required>
            </div>
            
            <div class="text-center">
                <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                    <i class="fas fa-upload mr-2"></i>Import Users
                </button>
            </div>
        </form>
    </div>

    <div class="mt-6 text-center">
        <a href="index?page=admin&sub_page=users" class="text-blue-600 hover:underline">&larr; Back to User Management</a>
    </div>

</div>
