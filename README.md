# Barangay Resource Management System

A comprehensive PHP-based web application for managing barangay resources, bookings, and reservations with an integrated notification and payment system.

## Features

- **User Management**
  - User Registration with Admin Approval
  - Profile Management with ID Verification
  - User Status (Active, Pending, Blacklisted)
  - Role-based Access Control (Admin/User)
  - Blacklist Management for Problematic Users
  - User Import/Export via Excel (.xls/.xlsx)
  - Resident Masterlist Management

- **Resource Management**
  - Categorized Resources (Facilities and Equipment)
  - Availability Tracking in Real-time
  - Quantity Management for Equipment
  - Payment Requirements (for specific resources like Gym)
  - Resource Status Updates
  - Resource Image Management
  - Resource Search and Filtering
  - Import/Export Resource Data

- **Reservation System**
  - Comprehensive Reservation Form with Location Details
  - Date and Time Selection with Calendar View
  - Multiple Resource Selection in a Single Booking
  - Quantity Selection for Equipment
  - Double-booking Prevention System
  - Resource Availability Checks
  - 3-day Advance Booking Requirement
  - Reason for Reservation
  - Multi-day Reservation Support

- **Status Tracking**
  - Pending Approval
  - Approved
  - For Delivery
  - For Pickup
  - Completed
  - Cancelled
  - Rejected
  - Status History Timeline with Timestamps

- **Payment System**
  - GCash Integration with QR Code
  - Payment Proof Upload and Verification
  - Admin Payment Verification Process
  - Payment Status Tracking (Not Required, Pending, Paid, Rejected)
  - Payment Receipt Generation
  - Payment History Tracking

- **Notification System**
  - SMS Notifications via Twilio API
  - In-app Notifications with Real-time Updates
  - Email Notification Support (configurable)
  - Status Change Notifications
  - Payment Confirmation Notifications
  - Registration Approval Notifications

- **Admin Dashboard**
  - User Management with Filtering
  - Resource Management with Search
  - Reservation Management with Status Filters
  - Payment Verification Interface
  - Reports and Analytics with Charts
  - Export to CSV/Excel Functionality
  - System Stats and Overview
  - Settings Configuration Panel
  - SMS Testing Interface

- **User Dashboard**
  - Reservation History with Status
  - Payment History with Receipts
  - Upcoming Reservations View
  - Notification Center
  - Resource Availability Calendar
  - User Feedback System

- **Calendar Integration**
  - Visual Calendar for Reservations
  - Availability Calendar for Resources
  - Admin Calendar View for All Bookings
  - FullCalendar.js Integration
  - Event Details Modal

- **Reporting and Analytics**
  - User Registration Reports
  - Resource Utilization Reports
  - Financial Reports
  - Reservation Status Reports
  - Data Visualization with Charts
  - Export Reports to CSV/Excel

- **Data Management**
  - Excel Import/Export for Users
  - Masterlist Import/Export
  - Data Validation and Error Handling
  - Bulk Operations Support

- **Security Features**
  - CSRF Token Protection
  - Data Encryption for Sensitive Information
  - Secure File Upload Handling
  - Session Management
  - Input Validation and Sanitization

## Technologies Used

- PHP 8.0+ (Backend)
- MySQL 8.0+ (Database)
- Tailwind CSS (Frontend Styling, Local Version)
- Alpine.js (JavaScript Functionality)
- Select2.js (Enhanced Dropdowns)
- Font Awesome 6 (Icons, Local Version)
- Twilio API (SMS Notifications)
- FullCalendar.js (Calendar Interface)
- Chart.js (Data Visualization)
- PhpSpreadsheet (Excel Import/Export)
- Composer (Dependency Management)

## Requirements

- Web server (Apache/Nginx)
- PHP 8.0 or higher
- MySQL 8.0 or higher
- PDO PHP Extension
- cURL PHP Extension (for SMS integration)
- GD PHP Extension (for image processing)
- Zip PHP Extension (for Excel import/export)
- XML PHP Extension (for Excel import/export)
- mod_rewrite (for clean URLs, if using Apache)
- Composer (for dependency management)

## Installation

1. **Clone the repository:**

```bash
git clone https://github.com/yourusername/bcrs-php.git
cd bcrs-php
```

2. **Create a MySQL database:**

```sql
CREATE DATABASE barangay_resource_system;
```

3. **Import the database schema:**

```bash
mysql -u username -p barangay_resource_system < schema.sql
```

4. **Install PHP dependencies using Composer:**

```bash
composer install
```

4. **Install PHP dependencies using Composer:**

```bash
composer install
```

5. **Configure the database connection:**

5. **Configure the database connection:**

Edit the `config/database.php` file with your database credentials:

```php
$host = 'localhost';
$dbname = 'barangay_resource_system';
$username = 'your_mysql_username';
$password = 'your_mysql_password';
```

6. **Verify offline CSS and JS files:**

6. **Verify offline CSS and JS files:**

The system uses offline versions of Tailwind CSS and Font Awesome instead of CDN:
- `js/tailwind.js` contains the Tailwind CSS code
- `includes/css/all.min.css` contains the Font Awesome styles

If these files need updating, you can download the latest versions from their respective websites:
- Tailwind CSS: https://tailwindcss.com/
- Font Awesome: https://fontawesome.com/

7. **Configure SMS notifications (optional):**

Edit the SMS settings through the admin panel at **Settings > SMS Configuration**, or directly edit the `config/sms.php` file with your Twilio credentials:

```php
'account_sid' => 'YOUR_TWILIO_ACCOUNT_SID',
'auth_token' => 'YOUR_TWILIO_AUTH_TOKEN',
'from_number' => 'YOUR_TWILIO_PHONE_NUMBER',
'enabled' => true, // Set to false to disable SMS functionality
```

8. **Set up the upload directories:**

8. **Set up the upload directories:**

Create the following directories with appropriate permissions:

```bash
mkdir -p uploads/ids
mkdir -p uploads/payments
mkdir -p uploads/resources
chmod 755 uploads
chmod 755 uploads/ids
chmod 755 uploads/payments
chmod 755 uploads/resources
```

9. **Configure your web server:**

9. **Configure your web server:**

Point your web server's document root to the project folder or set up a virtual host.

10. **Run local development server (alternative):**

If you're using XAMPP or similar:
```bash
# Move the project to your htdocs folder
cd /path/to/xampp/htdocs
# Access via http://localhost/bcrs-php
```

## Admin Account

Create an admin account using http://localhost/bcrs-php/admin_setup.

## System Workflow

1. **User Registration Process**
   - User submits registration form with ID proof
   - Admin reviews and approves/rejects registration
   - User receives notification of approval/rejection
   - Approved users can log in and access the system

2. **Reservation Process**
   - User selects resources and quantities
   - User provides location details and date/time
   - System checks for availability and conflicts
   - Payment required for specific facilities (e.g., Gym)
   - User submits payment proof (if required)
   - Admin verifies payment and approves/rejects reservation
   - User receives notification on status updates

3. **Resource Delivery/Pickup**
   - Admin marks resources for delivery or pickup
   - System sends notification to user with details
   - User confirms receipt of resources
   - Admin marks reservation as completed after use
   - Resources return to available inventory
   - System updates resource availability

4. **Reporting and Analytics**
   - Admin can generate various reports with data visualization:
     - Resource utilization reports
     - User activity reports
     - Reservation status reports
     - Financial reports with charts
     - Registration analytics
   - Data can be exported to CSV/Excel for further analysis
   - Interactive charts and graphs for better insights

5. **Data Management**
   - Import/export user data via Excel files
   - Bulk user operations
   - Masterlist management for residents
   - Data validation and error handling

## File Structure

- `/config` - Database and SMS configuration files
- `/includes` - Helper functions and common components
- `/pages` - Main application pages
  - `/pages/admin` - Admin panel pages
  - `/pages/admin/calendar` - Admin calendar views
  - `/pages/admin/feedback` - Feedback management
- `/uploads` - User uploaded files
  - `/uploads/ids` - User ID verification documents
  - `/uploads/payments` - Payment proof uploads
  - `/uploads/resources` - Resource images
- `/js` - JavaScript files for frontend functionality
- `/vendor` - Composer dependencies
- `/logs` - System logs for debugging

## Development and Extension

### Adding New Features

1. Create new PHP files in the appropriate directories
2. Update the routing in `index.php`
3. Add database tables if needed (update schema.sql)
4. Implement the feature
5. Test thoroughly before deployment

### Database Schema

The main database tables include:
- `users` - User accounts and profiles
- `resources` - Available facilities and equipment
- `reservations` - Booking records
- `reservation_items` - Individual items in bookings
- `payments` - Payment records
- `notifications` - System notifications
- `status_history` - Reservation status tracking
- `admins` - Administrator accounts
- `settings` - System configuration settings
- `masterlist` - Resident masterlist data

## Recent Updates and Fixes

### System Improvements
- **Resource Availability Logic**: Fixed resource deduction timing - resources are now only deducted after admin approval, not during pending status
- **SMS Notifications**: Added automatic SMS notifications for user registration approval
- **Excel Integration**: Added comprehensive Excel import/export functionality for users and masterlist
- **Enhanced Security**: Implemented CSRF token protection and improved data validation
- **Admin Settings Panel**: Added configurable SMS settings through admin interface
- **Calendar Enhancement**: Improved reservation calendar with better event handling
- **Data Visualization**: Added charts and graphs for analytics and reports

### Bug Fixes
- Fixed double-booking prevention system
- Improved resource quantity management
- Enhanced error handling for file uploads
- Better session management and security

For detailed information about applied fixes, see `FIXES_APPLIED.md`.

## Troubleshooting

- **Database Connection Issues**: Verify credentials in config/database.php
- **Upload Problems**: Check folder permissions for uploads directory
- **SMS Not Sending**: Verify Twilio credentials in admin settings panel and PHP cURL extension
- **Missing Calendar**: Ensure JavaScript files are properly loaded
- **Excel Import Issues**: Verify that Zip and XML PHP extensions are installed
- **Composer Dependencies**: Run `composer install` if vendor folder is missing
- **Permission Errors**: Ensure proper file permissions for uploads and logs directories

## Changelog

### Version 2.0 (June 2025)
- Added Excel import/export functionality
- Implemented masterlist management
- Enhanced SMS notification system
- Added data visualization with charts
- Improved security with CSRF protection
- Enhanced admin settings panel
- Fixed resource availability logic
- Added comprehensive reporting features

### Version 1.0 (Initial Release)
- Basic user registration and management
- Resource and reservation system
- Payment integration with GCash
- SMS notifications via Twilio
- Admin dashboard and calendar
- Basic reporting functionality

## License

This project is licensed under the MIT License.

## Contact

For any questions or support, please contact the development team at:
- **Project**: Barangay Resource Management System (BCRS-PHP)
- **Repository**: Local development version
- **Documentation**: See `Design.md` for detailed system specifications
- **Support**: Contact your system administrator
