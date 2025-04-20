<?php
/**
 * SMS Configuration
 * 
 * This file contains configuration settings for the SMS notification functionality
 * using Twilio SMS API
 */

return [
    // Twilio credentials from https://www.twilio.com/console
    'account_sid' => '', // Your Twilio Account SID
    'auth_token' => '',  // Your Twilio Auth Token
    
    // Your Twilio phone number (must be purchased from Twilio)
    'from_number' => '',  // Format: +1XXXXXXXXXX (with country code)
    
    // API endpoint (should not need to change this)
    'api_url' => 'https://api.twilio.com/2010-04-01/Accounts/',
    
    // Enable/disable SMS functionality (set to false to disable SMS sending)
    'enabled' => false,
];