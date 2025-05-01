<?php
// Start session
session_start();
require_once 'config/database.php';

// Check if already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    echo "<p>You are already logged in as admin.</p>";
    echo "<p><a href='index?page=admin'>Go to Admin Dashboard</a></p>";
    exit;
}

$showForm = true;
$message = '';

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if we're creating a new admin
    if (isset($_POST['create_admin']) && $_POST['create_admin'] == 'yes') {
        // Get form data
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $firstName = filter_input(INPUT_POST, 'first_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $lastName = filter_input(INPUT_POST, 'last_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $password = $_POST['password'];
        $confirmPassword = $_POST['confirm_password'];
        
        $errors = [];
        
        // Validate inputs
        if (empty($email)) {
            $errors[] = "Email is required";
        }
        
        if (empty($firstName)) {
            $errors[] = "First name is required";
        }
        
        if (empty($lastName)) {
            $errors[] = "Last name is required";
        }
        
        if (empty($password)) {
            $errors[] = "Password is required";
        }
        
        if ($password !== $confirmPassword) {
            $errors[] = "Passwords do not match";
        }
        
        // If no errors, create admin
        if (empty($errors)) {
            try {
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert admin into database
                $stmt = $db->prepare("INSERT INTO admins (email, password, first_name, last_name) VALUES (?, ?, ?, ?)");
                $result = $stmt->execute([$email, $hashedPassword, $firstName, $lastName]);
                
                if ($result) {
                    $message = "<div style='color: green; font-weight: bold;'>Admin account created successfully! You can now login with the email and password you provided.</div>";
                    $message .= "<p>Your admin email: " . htmlspecialchars($email) . "</p>";
                    $message .= "<p><a href='index?page=login'>Go to Login Page</a></p>";
                    $showForm = false;
                } else {
                    $message = "<div style='color: red; font-weight: bold;'>Failed to create admin account. Please try again.</div>";
                }
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Duplicate entry
                    $message = "<div style='color: red; font-weight: bold;'>An admin with this email already exists.</div>";
                } else {
                    $message = "<div style='color: red; font-weight: bold;'>Database error: " . $e->getMessage() . "</div>";
                }
            }
        } else {
            $message = "<div style='color: red; font-weight: bold;'>Please fix the following errors:</div><ul>";
            foreach ($errors as $error) {
                $message .= "<li>" . htmlspecialchars($error) . "</li>";
            }
            $message .= "</ul>";
        }
    }
}

// Check if admin table exists and if there are any admins
try {
    $stmt = $db->query("SHOW TABLES LIKE 'admins'");
    $tableExists = $stmt->rowCount() > 0;
    
    if ($tableExists) {
        $stmt = $db->query("SELECT COUNT(*) FROM admins");
        $adminCount = $stmt->fetchColumn();
    } else {
        $adminCount = 0;
    }
} catch (PDOException $e) {
    $message = "<div style='color: red; font-weight: bold;'>Database error: " . $e->getMessage() . "</div>";
    $tableExists = false;
    $adminCount = 0;
}

// Check if we need to create the admin table
if (!$tableExists) {
    try {
        $db->exec("
            CREATE TABLE IF NOT EXISTS admins (
                id INT AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL UNIQUE,
                password VARCHAR(255) NOT NULL,
                first_name VARCHAR(255) NOT NULL,
                last_name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
        $message = "<div style='color: green; font-weight: bold;'>Admin table created successfully.</div>" . $message;
    } catch (PDOException $e) {
        $message = "<div style='color: red; font-weight: bold;'>Failed to create admin table: " . $e->getMessage() . "</div>";
    }
}

// Style for the page
$style = "
    body { font-family: Arial, sans-serif; margin: 0; padding: 20px; background-color: #f4f4f4; }
    .container { max-width: 800px; margin: 0 auto; background-color: white; padding: 20px; border-radius: 5px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #333; }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 5px; font-weight: bold; }
    input[type='text'], input[type='email'], input[type='password'] { width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box; }
    button { background-color: #4CAF50; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; }
    button:hover { background-color: #45a049; }
    .error { color: red; }
    .success { color: green; }
    table { width: 100%; border-collapse: collapse; margin-top: 20px; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 10px; text-align: left; }
    th { background-color: #f2f2f2; }
";

// If admin table exists, list all admins
$adminList = "";
if ($tableExists) {
    try {
        $stmt = $db->query("SELECT id, email, first_name, last_name, created_at FROM admins");
        $admins = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($admins) > 0) {
            $adminList = "
                <h3>Existing Admin Accounts</h3>
                <table>
                    <tr>
                        <th>ID</th>
                        <th>Email</th>
                        <th>Name</th>
                        <th>Created At</th>
                    </tr>
            ";
            
            foreach ($admins as $admin) {
                $adminList .= "
                    <tr>
                        <td>" . $admin['id'] . "</td>
                        <td>" . htmlspecialchars($admin['email']) . "</td>
                        <td>" . htmlspecialchars($admin['first_name'] . ' ' . $admin['last_name']) . "</td>
                        <td>" . $admin['created_at'] . "</td>
                    </tr>
                ";
            }
            
            $adminList .= "</table>";
        } else {
            $adminList = "<p>No admin accounts found. Please create one below.</p>";
        }
    } catch (PDOException $e) {
        $adminList = "<p class='error'>Failed to retrieve admin accounts: " . $e->getMessage() . "</p>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Setup - BCRS</title>
    <style><?php echo $style; ?></style>
</head>
<body>
    <div class="container">
        <h1>Admin Account Setup</h1>
        <?php echo $message; ?>
        
        <?php echo $adminList; ?>
        
        <?php if ($showForm): ?>
            <h2>Create New Admin Account</h2>
            <form method="post">
                <input type="hidden" name="create_admin" value="yes">
                <div class="form-group">
                    <label for="email">Email:</label>
                    <input type="email" id="email" name="email" required value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="first_name">First Name:</label>
                    <input type="text" id="first_name" name="first_name" required value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="last_name">Last Name:</label>
                    <input type="text" id="last_name" name="last_name" required value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                </div>
                <div class="form-group">
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>
                <button type="submit">Create Admin Account</button>
            </form>
            
            <hr>
            <p><a href="index?page=login">Return to Login Page</a></p>
        <?php endif; ?>
    </div>
</body>
</html>