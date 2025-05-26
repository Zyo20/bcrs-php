-- Create settings table for application configurations
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `setting_key` varchar(100) NOT NULL,
  `setting_value` varchar(255) DEFAULT NULL,
  `setting_description` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert SMS toggle setting (default to disabled)
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('sms_enabled', '0', 'Toggle SMS notifications (0 = disabled, 1 = enabled)');

-- Insert SMS gateway settings
-- Note: API key should be encrypted when saved to the database for security
INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('sms_api_key', '', 'API key for SMS gateway service');

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('sms_sender_id', '', 'Sender ID for SMS messages');

INSERT INTO `settings` (`setting_key`, `setting_value`, `setting_description`) 
VALUES ('sms_admin_number', '', 'Admin phone number for SMS notifications'); 