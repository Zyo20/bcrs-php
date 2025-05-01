<?php
// We don't need to include session_manager as it's already included in index.php
// Just need database connection and autoload for PhpSpreadsheet
require_once 'config/database.php';
require_once 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

$errors = [];
$success = false;
$import_count = 0;
$sample_data = [];

// Process form submission for file upload
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES['excel_file'])) {
    // Validate file upload
    if ($_FILES['excel_file']['error'] == 0) {
        $file_name = $_FILES['excel_file']['name'];
        $file_size = $_FILES['excel_file']['size'];
        $file_tmp = $_FILES['excel_file']['tmp_name'];
        $file_type = $_FILES['excel_file']['type'];
        
        // Get file extension
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        // Allowed extensions for Excel files
        $extensions = ["xlsx", "xls", "csv"];
        
        if (in_array($file_ext, $extensions)) {
            try {
                // Load the Excel file
                $spreadsheet = IOFactory::load($file_tmp);
                $worksheet = $spreadsheet->getActiveSheet();
                $highestRow = $worksheet->getHighestRow();
                
                // Start transaction
                $db->beginTransaction();
                
                $import_count = 0;
                
                // Define column letters for easier reference
                $columns = [
                    'last_name' => 'A',
                    'first_name' => 'B',
                    'middle_name' => 'C',
                    'contact_number' => 'D',
                    'age' => 'E',
                    'year_of_residency' => 'F',
                    'purok' => 'G'
                ];
                
                // Process data starting from row 2 (assuming row 1 is header)
                for ($row = 2; $row <= $highestRow; $row++) {
                    // Use the modern cell reference method
                    $last_name = $worksheet->getCell($columns['last_name'] . $row)->getValue();
                    $first_name = $worksheet->getCell($columns['first_name'] . $row)->getValue();
                    $middle_name = $worksheet->getCell($columns['middle_name'] . $row)->getValue();
                    $contact_number = $worksheet->getCell($columns['contact_number'] . $row)->getValue();
                    $age = $worksheet->getCell($columns['age'] . $row)->getValue();
                    $year_of_residency = $worksheet->getCell($columns['year_of_residency'] . $row)->getValue();
                    $purok = $worksheet->getCell($columns['purok'] . $row)->getValue();
                    
                    // Skip empty rows
                    if (empty($last_name) && empty($first_name)) {
                        continue;
                    }
                    
                    // Basic validation
                    if (empty($last_name) || empty($first_name) || empty($contact_number) || 
                        empty($age) || empty($year_of_residency) || empty($purok)) {
                        $errors[] = "Row $row: Missing required data. Skipping this row.";
                        continue;
                    }
                    
                    // Validate numeric fields
                    if (!is_numeric($age) || $age < 1 || $age > 120) {
                        $errors[] = "Row $row: Invalid age value. Skipping this row.";
                        continue;
                    }
                    
                    if (!is_numeric($year_of_residency) || $year_of_residency < 1900 || $year_of_residency > date('Y')) {
                        $errors[] = "Row $row: Invalid year of residency. Skipping this row.";
                        continue;
                    }
                    
                    // Insert into database
                    $stmt = $db->prepare("INSERT INTO masterlist (last_name, first_name, middle_name, contact_number, age, year_of_residency, purok) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([$last_name, $first_name, $middle_name, $contact_number, $age, $year_of_residency, $purok]);
                    $import_count++;
                }
                
                if ($import_count > 0) {
                    $db->commit();
                    $success = true;
                    $_SESSION['success_message'] = "$import_count masterlist entries imported successfully!";
                    header("Location: index.php?page=admin&section=masterlist");
                    exit;
                } else {
                    $db->rollBack();
                    $errors[] = "No valid data found in the Excel file.";
                }
                
            } catch (Exception $e) {
                $db->rollBack();
                $errors[] = "Error processing the Excel file: " . $e->getMessage();
            }
        } else {
            $errors[] = "Invalid file format. Please upload an Excel file (.xlsx, .xls, .csv)";
        }
    } else {
        $errors[] = "Error uploading file. Error code: " . $_FILES['excel_file']['error'];
    }
}

// Create sample data for download
$sample_data = [
    ['Last Name', 'First Name', 'Middle Name', 'Contact Number', 'Age', 'Year of Residency', 'Purok'],
    ['Doe', 'John', 'Smith', '09123456789', '35', '2010', 'Purok 1'],
    ['Reyes', 'Maria', 'Santos', '09987654321', '42', '2005', 'Purok 3'],
    ['Cruz', 'Jose', 'Mendoza', '09456789123', '28', '2018', 'Purok 2']
];

// Generate sample Excel file for download if requested
if (isset($_GET['download_sample'])) {
    // Make sure no output has been sent before
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    try {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Masterlist Sample');
        
        // Use cell references instead of column/row indexes
        // Header row - make it bold
        $sheet->setCellValue('A1', 'Last Name');
        $sheet->setCellValue('B1', 'First Name');
        $sheet->setCellValue('C1', 'Middle Name');
        $sheet->setCellValue('D1', 'Contact Number');
        $sheet->setCellValue('E1', 'Age');
        $sheet->setCellValue('F1', 'Year of Residency');
        $sheet->setCellValue('G1', 'Purok');
        
        // Make headers bold
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        
        // Sample data rows
        // Row 2
        $sheet->setCellValue('A2', 'Doe');
        $sheet->setCellValue('B2', 'John');
        $sheet->setCellValue('C2', 'Smith');
        $sheet->setCellValue('D2', '09123456789');
        $sheet->setCellValue('E2', '35');
        $sheet->setCellValue('F2', '2010');
        $sheet->setCellValue('G2', 'Purok 1');
        
        // Row 3
        $sheet->setCellValue('A3', 'Reyes');
        $sheet->setCellValue('B3', 'Maria');
        $sheet->setCellValue('C3', 'Santos');
        $sheet->setCellValue('D3', '09987654321');
        $sheet->setCellValue('E3', '42');
        $sheet->setCellValue('F3', '2005');
        $sheet->setCellValue('G3', 'Purok 3');
        
        // Row 4
        $sheet->setCellValue('A4', 'Cruz');
        $sheet->setCellValue('B4', 'Jose');
        $sheet->setCellValue('C4', 'Mendoza');
        $sheet->setCellValue('D4', '09456789123');
        $sheet->setCellValue('E4', '28');
        $sheet->setCellValue('F4', '2018');
        $sheet->setCellValue('G4', 'Purok 2');
        
        // Set column widths
        $sheet->getColumnDimension('A')->setWidth(15); // Last Name
        $sheet->getColumnDimension('B')->setWidth(15); // First Name
        $sheet->getColumnDimension('C')->setWidth(15); // Middle Name
        $sheet->getColumnDimension('D')->setWidth(15); // Contact Number
        $sheet->getColumnDimension('E')->setWidth(10); // Age
        $sheet->getColumnDimension('F')->setWidth(18); // Year of Residency
        $sheet->getColumnDimension('G')->setWidth(15); // Purok
        
        // Format the contact number column as text to preserve leading zeros
        $sheet->getStyle('D2:D4')->getNumberFormat()->setFormatCode('@');
        
        // Set headers before generating file content
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="masterlist_sample.xlsx"');
        header('Cache-Control: max-age=0');
        
        // Create Excel writer
        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        
        // Save to php output
        $writer->save('php://output');
        exit;
    } catch (Exception $e) {
        // If there's an error, redirect back with an error message
        $_SESSION['error_message'] = "Error generating sample file: " . $e->getMessage();
        header("Location: index.php?page=admin&section=import_masterlist");
        exit;
    }
}
?>

<div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-bold text-blue-800">Import Masterlist from Excel</h1>
        <a href="index.php?page=admin&section=masterlist" class="bg-gray-200 hover:bg-gray-300 text-gray-700 px-4 py-2 rounded-md text-sm font-medium transition-colors duration-150">
            ← Back to Masterlist
        </a>
    </div>
    
    <?php if (!empty($errors)): ?>
    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6">
        <h5 class="font-semibold">Errors occurred during import:</h5>
        <ul class="list-disc list-inside">
            <?php foreach ($errors as $error): ?>
                <li><?php echo $error; ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
    
    <?php if ($success): ?>
    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6">
        <p>Successfully imported <?php echo $import_count; ?> masterlist entries!</p>
    </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow-md overflow-hidden">
        <div class="p-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-6">
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Instructions</h2>
                    <ol class="list-decimal list-inside space-y-2 text-gray-700">
                        <li>Prepare your Excel file with the following columns in order:
                            <ul class="list-disc list-inside ml-5 mt-2 space-y-1 text-gray-600">
                                <li>Column A: <strong>Last Name</strong> (required) - Resident's surname</li>
                                <li>Column B: <strong>First Name</strong> (required) - Resident's given name</li>
                                <li>Column C: <strong>Middle Name</strong> (optional) - Can be left blank if not applicable</li>
                                <li>Column D: <strong>Contact Number</strong> (required) - Format: 11 digits (e.g., 09123456789)</li>
                                <li>Column E: <strong>Age</strong> (required) - Must be a number between 1-120</li>
                                <li>Column F: <strong>Year of Residency</strong> (required) - Four-digit year when resident moved to the barangay (e.g., 2010)</li>
                                <li>Column G: <strong>Purok</strong> (required) - Must be one of: Purok 1, Purok 2, Purok 3, Purok 4, Purok 5, or Purok 6</li>
                            </ul>
                        </li>
                        <li>The first row (Row 1) must contain column headers exactly as shown above.</li>
                        <li>Data should start from the second row (Row 2).</li>
                        <li>All cells should use plain text format (no special formatting).</li>
                        <li>Contact numbers should not include spaces, dashes, or other special characters.</li>
                        <li>Year of Residency must be a valid year between 1900 and the current year (<?php echo date('Y'); ?>).</li>
                        <li>Empty rows will be skipped during import.</li>
                        <li>If a row has missing required data, it will be skipped and an error will be logged.</li>
                        <li>Save your file as Excel (.xlsx, .xls) or CSV format.</li>
                    </ol>
                    
                    <div class="mt-6 mb-6 p-4 bg-blue-50 border border-blue-200 rounded-md">
                        <h3 class="text-md font-semibold text-blue-800 mb-2">Excel Formatting Tips:</h3>
                        <ul class="list-disc list-inside space-y-1 text-gray-700">
                            <li>For contact numbers, set the cell format to "Text" in Excel before entering data to prevent automatic formatting.</li>
                            <li>If using CSV, ensure the delimiter is set to comma (,).</li>
                            <li>Avoid using formulas in your Excel file; use plain text values only.</li>
                            <li>Check for trailing spaces in text fields as they may affect matching with existing records.</li>
                            <li>Excel's "Text to Columns" feature can help properly format data if you're copying from another source.</li>
                        </ul>
                    </div>
                    
                    <div class="mt-6">
                        <a href="index.php?page=admin&section=import_masterlist&download_sample=1" class="inline-flex items-center px-4 py-2 border border-blue-500 text-blue-500 bg-white rounded-md hover:bg-blue-50 transition-colors duration-150">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
                            </svg>
                            Download Sample Excel File
                        </a>
                    </div>
                </div>
                
                <div>
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Sample Data Format</h2>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border">
                            <thead class="bg-gray-50">
                                <tr>
                                    <?php foreach ($sample_data[0] as $header): ?>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                        <?php echo $header; ?>
                                    </th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php for ($i = 1; $i < count($sample_data); $i++): ?>
                                <tr>
                                    <?php foreach ($sample_data[$i] as $cell): ?>
                                    <td class="px-3 py-2 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo $cell; ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endfor; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="border-t pt-6">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">
                    <i class="fas fa-file-import mr-2"></i> Upload Excel File
                </h2>
                
                <form method="post" enctype="multipart/form-data" class="max-w-2xl">
                    <div class="mb-4">
                        <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-1">Select Excel File</label>
                        <input type="file" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-blue-500 focus:border-blue-500" 
                               id="excel_file" name="excel_file" accept=".xlsx,.xls,.csv" required>
                        <p class="mt-1 text-sm text-gray-500">Accepted formats: .xlsx, .xls, .csv</p>
                    </div>
                    
                    <div class="mt-6 flex items-center">
                        <button type="submit" class="px-4 py-2 bg-green-500 hover:bg-green-600 text-white rounded-md transition-colors duration-150 flex items-center">
                            <i class="fas fa-upload mr-2"></i> Import Masterlist
                        </button>
                        <a href="index.php?page=admin&section=masterlist" class="ml-3 px-4 py-2 bg-gray-300 hover:bg-gray-400 text-gray-800 rounded-md transition-colors duration-150">
                            Cancel
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>