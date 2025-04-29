<?php
// Check for flash message
$hasFlash = isset($_SESSION['flash_message']);
$flashMessage = $_SESSION['flash_message'] ?? '';
$flashType = $_SESSION['flash_type'] ?? '';

// Unset flash messages after they're retrieved to prevent displaying again on refresh
if ($hasFlash) {
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}

// Session timeout handling - expire after 5 minutes of inactivity
$sessionTimeout = 300; // 5 minutes in seconds
$currentTime = time();

// Check if user is logged in and session timeout is applicable
if (isset($_SESSION['user_id'])) {
    // If last activity timestamp doesn't exist, create it
    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $currentTime;
    }
    
    // Check if session has expired
    if (($currentTime - $_SESSION['last_activity']) > $sessionTimeout) {
        // Session expired - destroy session and redirect to login
        session_unset();
        session_destroy();
        
        // Start a new session to allow setting a flash message
        session_start();
        
        // Set a flash message to notify the user
        $_SESSION['flash_message'] = "Your session has expired due to inactivity. Please log in again.";
        $_SESSION['flash_type'] = "error";
        
        // Redirect to login page using the same format as the rest of the site
        header("Location: index?page=login&session_expired=1");
        exit;
    }
    
    // Session is still valid, update last activity time
    $_SESSION['last_activity'] = $currentTime;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barangay Resource Management System</title>
    <!-- Local Tailwind CSS -->
    <script src="js/tailwind.js"></script>
    <!-- Local Font Awesome for icons -->
    <link rel="stylesheet" href="includes/css/all.min.css">
    <!-- Notification System -->
    <script src="js/notifications.js"></script>
    <!-- Session Monitor Script -->
    <?php if (isset($_SESSION['user_id'])): ?>
    <script src="js/session-monitor.js"></script>
    <?php endif; ?>
    <!-- Custom styles -->
    <style>
        /* User dropdown styles */
        .user-dropdown {
            position: relative;
        }
        .dropdown-menu {
            position: absolute;
            right: 0;
            top: 100%;
            width: 200px;
            margin-top: 0.5rem;
            background-color: white;
            border-radius: 0.375rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            z-index: 50;
            overflow: hidden;
        }
        .dropdown-item {
            display: block;
            padding: 0.75rem 1rem;
            color: #374151;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        .dropdown-item:hover {
            background-color: #f3f4f6;
        }
        .dropdown-divider {
            height: 1px;
            background-color: #e5e7eb;
            margin: 0;
        }
        .flash-message {
            padding: 0.75rem;
            border-radius: 0.375rem;
            margin-bottom: 1rem;
            position: relative;
        }
        .flash-message-success {
            background-color: #d1fae5;
            color: #065f46;
        }
        .flash-message-error {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .flash-message-warning {
            background-color: #fef3c7;
            color: #92400e;
        }
        .flash-message-info {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .flash-message .close-btn {
            position: absolute;
            right: 0.75rem;
            top: 0.75rem;
            cursor: pointer;
        }
        .notification-badge {
            position: absolute;
            top: 0;
            right: 0;
            background-color: red;
            color: white;
            font-size: 0.75rem;
            border-radius: 50%;
            padding: 0.25rem 0.5rem;
        }
        .notification-menu {
            width: 300px;
        }
        .notification-dropdown {
            position: relative;
            display: inline-flex;
            align-items: center;
        }
        .notification-item {
            color: #000 !important;
            transition: background-color 0.2s ease;
        }
        .notification-item:hover {
            background-color: #e2eeff !important;
            cursor: pointer;
        }
        .notification-item p {
            color: #000;
        }
        .notification-item .text-gray-700 {
            color: #000;
        }
        .notification-header h3 {
            color: #000;
        }
        
        /* Sticky footer styles */
        html {
            height: 100%;
        }
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        main {
            flex: 1;
        }
    </style>
    <script>
        // Simple JavaScript for closing flash messages
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.close-btn').forEach(function(btn) {
                btn.addEventListener('click', function() {
                    this.parentElement.remove();
                });
            });
        });
    </script>
</head>
<body class="bg-gray-300 flex flex-col min-h-screen">
    <?php if (isset($_SESSION['user_id'])): ?>
    <!-- Session monitor container - this element activates the session monitor -->
    <div id="session-monitor-container" class="hidden" data-timeout="<?php echo $sessionTimeout; ?>"></div>
    <?php endif; ?>
    
    <header class="bg-gray-600 text-white shadow-md">
        <div class="container mx-auto px-4 py-3">
            <div class="flex justify-between items-center">
                <?php if (isLoggedIn()): ?>
                    <?php if (isAdmin()): ?>
                        <a href="index?page=admin" class="text-xl font-bold">BARSERVE - Admin</a>
                    <?php else: ?>
                        <a href="index?page=dashboard" class="text-xl font-bold">BARSERVE</a>
                    <?php endif; ?>
                <?php else: ?>
                    <a href="index" class="text-xl font-bold">BARSERVE</a>
                <?php endif; ?>
                
                <nav class="hidden md:flex space-x-4">
                    <?php if (!isLoggedIn()): ?>
                    <a href="index" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-home"></i> Home</a>
                    <?php endif; ?>
                    <?php if (isLoggedIn() && isAdmin()): ?>
                        <a href="index?page=admin&section=resources" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-box-archive"></i> Resources</a>
                    <?php else: ?>
                        <a href="index?page=resources" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-box-archive"></i> Resources</a>
                    <?php endif; ?>
                    <?php if (isLoggedIn()): ?>
                        <?php if ($_SESSION['role'] === 'user'): ?>
                            <a href="index?page=reservation" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-calendar-plus"></i> Make Reservation</a>
                            <a href="index?page=dashboard" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-gauge-high"></i> My Dashboard</a>
                        <?php endif; ?>
                        <?php if (isAdmin()): ?>
                            <a href="index?page=admin" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-user-shield"></i> Admin Panel</a>
                        <?php endif; ?>
                        
                        <!-- Notification Dropdown -->
                        <div class="notification-dropdown" id="notificationDropdown">
                            <button onclick="toggleNotifications()" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2">
                                <i class="fas fa-bell"></i> Notifications
                                <span id="notificationCount" class="notification-badge hidden"></span>
                            </button>
                            <div id="notificationsMenu" class="dropdown-menu hidden notification-menu">
                                <div class="notification-header py-2 px-4 bg-gray-100 flex justify-between items-center">
                                    <h3 class="font-medium text-gray-700">Notifications</h3>
                                    <button onclick="clearAllNotifications()" class="text-xs text-blue-600 hover:text-blue-800">Clear all</button>
                                </div>
                                <div id="notificationsList" class="max-h-80 overflow-y-auto">
                                    <div class="notification-loading p-4 text-center text-gray-500">
                                        <i class="fas fa-spinner fa-spin mr-2"></i> Loading...
                                    </div>
                                </div>
                                <div class="notification-footer py-2 px-4 bg-gray-100 text-center border-t border-gray-200">
                                    
                                </div>
                            </div>
                        </div>
                        
                        <div class="user-dropdown">
                            <button onclick="this.nextElementSibling.classList.toggle('hidden')" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 focus:outline-none flex items-center gap-2">
                                <i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?>
                            </button>
                            <div class="dropdown-menu hidden">
                                <a href="index?page=edit_profile" class="dropdown-item flex items-center gap-2"><i class="fas fa-user-pen"></i> Edit Profile</a>
                                <?php if ($_SESSION['role'] === 'user'): ?>
                                    <a href="index?page=feedback" class="dropdown-item flex items-center gap-2"><i class="fas fa-comment-dots"></i> Send Feedback</a>
                                <?php endif; ?>
                                <div class="dropdown-divider"></div>
                                <a href="includes/logout" class="dropdown-item flex items-center gap-2"><i class="fas fa-right-from-bracket"></i> Logout</a>
                            </div>
                        </div>
                    <?php else: ?>
                        <a href="index?page=login" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-right-to-bracket"></i> Login</a>
                        <a href="index?page=register" class="hover:bg-gray-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-user-plus"></i> Register</a>
                    <?php endif; ?>
                </nav>
                
                <!-- Mobile menu button -->
                <div class="md:hidden">
                    <button onclick="document.getElementById('mobile-menu').classList.toggle('hidden')" class="text-white focus:outline-none">
                        <svg class="h-6 w-6 fill-current" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M4 6h16"></path>
                            <path d="M4 12h16"></path>
                            <path d="M4 18h16"></path>
                        </svg>
                    </button>
                    
                    <!-- Mobile menu -->
                    <div id="mobile-menu" class="hidden absolute top-16 right-0 left-0 bg-gray-600 shadow-md z-50">
                        <div class="flex flex-col p-4 space-y-3">
                            <?php if (!isLoggedIn()): ?>
                            <a href="index" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-home"></i> Home</a>
                            <?php endif; ?>
                            <?php if (isLoggedIn() && isAdmin()): ?>
                                <a href="index?page=admin&section=resources" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-box-archive"></i> Resources</a>
                            <?php else: ?>
                                <a href="index?page=resources" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-box-archive"></i> Resources</a>
                            <?php endif; ?>
                            <?php if (isLoggedIn() && $_SESSION['role'] === 'user'): ?>
                                <a href="index?page=reservation" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-calendar-plus"></i> Make Reservation</a>
                            <?php endif; ?>
                            <?php if (isLoggedIn()): ?>
                                <?php if ($_SESSION['role'] === 'user'): ?>
                                    <a href="index?page=dashboard" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-gauge-high"></i> My Dashboard</a>
                                <?php endif; ?>
                                <?php if (isAdmin()): ?>
                                    <a href="index?page=admin" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-user-shield"></i> Admin Panel</a>
                                <?php endif; ?>
                                
                                <div class="font-medium text-white flex items-center gap-2 px-3 py-2 border-t border-blue-500 mt-2"><i class="fas fa-user"></i> <?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                                <a href="index?page=edit_profile" class="hover:bg-blue-700 px-3 py-2 pl-6 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-user-pen"></i> Edit Profile</a>
                                <a href="includes/logout" class="hover:bg-blue-700 px-3 py-2 pl-6 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-right-from-bracket"></i> Logout</a>
                            <?php else: ?>
                                <a href="index?page=login" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-right-to-bracket"></i> Login</a>
                                <a href="index?page=register" class="hover:bg-blue-700 px-3 py-2 rounded transition duration-200 flex items-center gap-2"><i class="fas fa-user-plus"></i> Register</a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </header>
    
    <main class="container mx-auto px-4 py-6">
        <!-- Flash Messages -->
        <?php if ($hasFlash): ?>
            <div class="flash-message <?php echo $flashType === 'success' ? 'flash-message-success' : 'flash-message-error'; ?>">
                <span><?php echo htmlspecialchars($flashMessage); ?></span>
                <span class="close-btn">&times;</span>
            </div>
        <?php endif; ?>
        
        <!-- Original content starts here -->