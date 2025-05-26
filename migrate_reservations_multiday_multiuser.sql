-- Add new fields to the reservations table for multi-day and multi-user bookings
ALTER TABLE reservations ADD COLUMN is_multi_day TINYINT(1) NOT NULL DEFAULT 0;
ALTER TABLE reservations ADD COLUMN is_multi_user TINYINT(1) NOT NULL DEFAULT 0;

-- Update the description
ALTER TABLE reservations MODIFY COLUMN is_multi_day TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if this is a multi-day reservation';
ALTER TABLE reservations MODIFY COLUMN is_multi_user TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'Flag indicating if this is a multi-user reservation';

-- Remove problematic INSERT statement
-- The table reservation_status_types doesn't exist in this database
-- Status types are handled in PHP code directly (see getStatusBadge function)

-- Add an index to improve query performance
ALTER TABLE reservations ADD INDEX idx_reservation_type (is_multi_day, is_multi_user); 