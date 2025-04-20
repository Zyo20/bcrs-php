<?php
/**
 * SMS Configuration
 * 
 * This file contains configuration settings for the SMS notification functionality
 * using Twilio SMS API
 */

return [
    // Twilio credentials from https://www.twilio.com/console
    'account_sid' => 'ACcc4fb6e6c7278ea1d6c9aa6805ea66c9', // Your Twilio Account SID
    'auth_token' => '41d0a733b8fe44c033a168587cf9c518',  // Your Twilio Auth Token
    
    // Your Twilio phone number (must be purchased from Twilio)
    'from_number' => '+15709091652',  // Format: +1XXXXXXXXXX (with country code)
    
    // API endpoint (should not need to change this)
    'api_url' => 'https://api.twilio.com/2010-04-01/Accounts/',
    
    // Enable/disable SMS functionality (set to false to disable SMS sending)
    'enabled' => false,
];