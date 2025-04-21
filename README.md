# Barangay Resource Management System

A comprehensive PHP-based web application for managing barangay resources, bookings, and reservations with an integrated notification and payment system.

## Features

- **User Management**
  - User Registration with Admin Approval
  - Profile Management with ID Verification
  - User Status (Active, Pending, Blacklisted)
  - Role-based Access Control (Admin/User)
  - Blacklist Management for Problematic Users

- **Resource Management**
  - Categorized Resources (Facilities and Equipment)
  - Availability Tracking in Real-time
  - Quantity Management for Equipment
  - Payment Requirements (for specific resources like Gym)
  - Resource Status Updates

- **Reservation System**
  - Comprehensive Reservation Form with Location Details
  - Date and Time Selection with Calendar View
  - Multiple Resource Selection in a Single Booking
  - Quantity Selection for Equipment
  - Double-booking Prevention System
  - Resource Availability Checks
  - 3-day Advance Booking Requirement
  - Reason for Reservation

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

- **Notification System**
  - SMS Notifications via Twilio API
  - In-app Notifications with Real-time Updates
  - Email Notification Support (configurable)
  - Status Change Notifications
  - Payment Confirmation Notifications

- **Admin Dashboard**
  - User Management with Filtering
  - Resource Management with Search
  - Reservation Management with Status Filters
  - Payment Verification Interface
  - Reports and Analytics
  - Export to CSV Functionality
  - System Stats and Overview

- **User Dashboard**
  - Reservation History with Status
  - Payment History with Receipts
  - Upcoming Reservations View
  - Notification Center
  - Resource Availability Calendar

- **Calendar Integration**
  - Visual Calendar for Reservations
  - Availability Calendar for Resources
  - Admin Calendar View for All Bookings

## Technologies Used

- PHP 7.4+ (Backend)
- MySQL (Database)
- Tailwind CSS (Frontend Styling, Local Version)
- Alpine.js (JavaScript Functionality)
- Select2.js (Enhanced Dropdowns)
- Font Awesome (Icons, Local Version)
- Twilio API (SMS Notifications)
- FullCalendar.js (Calendar Interface)

## Requirements

- Web server (Apache/Nginx)
- PHP 7.4 or higher
- MySQL 5.7 or higher
- PDO PHP Extension
- cURL PHP Extension (for SMS integration)
- GD PHP Extension (for image processing)
- mod_rewrite (for clean URLs, if using Apache)

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
mysql -u username -p barangay_resource_system < database.sql
```

4. **Configure the database connection:**

Edit the `config/database.php` file with your database credentials:

```php
$host = 'localhost';
$dbname = 'barangay_resource_system';
$username = 'your_mysql_username';
$password = 'your_mysql_password';
```

5. **Verify offline CSS and JS files:**

The system uses offline versions of Tailwind CSS and Font Awesome instead of CDN:
- `js/tailwind.js` contains the Tailwind CSS code
- `includes/css/all.min.css` contains the Font Awesome styles

If these files need updating, you can download the latest versions from their respective websites:
- Tailwind CSS: https://tailwindcss.com/
- Font Awesome: https://fontawesome.com/

6. **Configure SMS notifications (optional):**

Edit the `config/sms.php` file with your Twilio credentials:

```php
'account_sid' => 'YOUR_TWILIO_ACCOUNT_SID',
'auth_token' => 'YOUR_TWILIO_AUTH_TOKEN',
'from_number' => 'YOUR_TWILIO_PHONE_NUMBER',
'enabled' => true, // Set to false to disable SMS functionality
```

7. **Set up the upload directories:**

Create the following directories with appropriate permissions:

```bash
mkdir -p uploads/ids
mkdir -p uploads/payments
chmod 755 uploads
chmod 755 uploads/ids
chmod 755 uploads/payments
```

8. **Configure your web server:**

Point your web server's document root to the project folder or set up a virtual host.

9. **Run local development server (alternative):**

If you're using XAMPP or similar:
```bash
# Move the project to your htdocs folder
cd /path/to/xampp/htdocs
# Access via http://localhost/bcrs-php
```

## Default Admin Account

- Email Address: admin@bcrs.com
- Password: admin123

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
   - Admin can generate various reports:
     - Resource utilization reports
     - User activity reports
     - Reservation status reports
     - Financial reports
   - Data can be exported to CSV for further analysis

## File Structure

- `/config` - Database and SMS configuration files
- `/includes` - Helper functions and common components
- `/pages` - Main application pages
  - `/pages/admin` - Admin panel pages
  - `/pages/admin/calendar` - Admin calendar views
- `/uploads` - User uploaded files
  - `/uploads/ids` - User ID verification documents
  - `/uploads/payments` - Payment proof uploads
- `/js` - JavaScript files for frontend functionality

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

## Troubleshooting

- **Database Connection Issues**: Verify credentials in config/database.php
- **Upload Problems**: Check folder permissions for uploads directory
- **SMS Not Sending**: Verify Twilio credentials and PHP cURL extension
- **Missing Calendar**: Ensure JavaScript files are properly loaded

## License

This project is licensed under the MIT License.

## Contact

For any questions or support, please contact the development team at:
- Email: your.email@example.com
- Website: https://example.com
- GitHub: https://github.com/yourusername/bcrs-php
