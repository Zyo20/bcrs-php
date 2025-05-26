<?php
/**
 * Encryption Utility Class
 * 
 * This class handles encryption and decryption of sensitive data.
 * It uses OpenSSL for secure encryption with AES-256-CBC.
 */
class EncryptionUtil {
    private $encryptionKey;
    private $cipher = 'AES-256-CBC';
    
    /**
     * Constructor
     * 
     * @param string $encryptionKey Encryption key (will be generated if not provided)
     */
    public function __construct($encryptionKey = null) {
        // If no key provided, try to get it from configuration
        if ($encryptionKey === null) {
            $this->loadEncryptionKey();
        } else {
            $this->encryptionKey = $encryptionKey;
        }
    }
    
    /**
     * Load encryption key from configuration or environment
     */
    private function loadEncryptionKey() {
        // Check if encryption key exists in configuration file
        $configFile = __DIR__ . '/../config/encryption.php';
        
        if (file_exists($configFile)) {
            $config = require $configFile;
            if (isset($config['key']) && !empty($config['key'])) {
                $this->encryptionKey = $config['key'];
                return;
            }
        }
        
        // As a fallback, generate a key based on server information
        // Note: This is less secure than using a properly stored key
        $this->encryptionKey = hash('sha256', $_SERVER['SERVER_NAME'] . $_SERVER['DOCUMENT_ROOT']);
        
        // Log a warning about using fallback key
        error_log('WARNING: Using fallback encryption key. Create a config/encryption.php file with a secure key.');
    }
    
    /**
     * Encrypt data
     * 
     * @param string $data Data to encrypt
     * @return string Encrypted data (base64 encoded)
     */
    public function encrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            // Generate IV (Initialization Vector)
            $ivLength = openssl_cipher_iv_length($this->cipher);
            $iv = openssl_random_pseudo_bytes($ivLength);
            
            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
            
            // Return base64 encoded IV + encrypted data
            return base64_encode($iv . $encrypted);
        } catch (Exception $e) {
            error_log('Encryption error: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Decrypt data
     * 
     * @param string $data Encrypted data (base64 encoded)
     * @return string Decrypted data
     */
    public function decrypt($data) {
        if (empty($data)) {
            return '';
        }
        
        try {
            // Decode the base64 data
            $data = base64_decode($data);
            
            // Get IV length
            $ivLength = openssl_cipher_iv_length($this->cipher);
            
            // Extract IV and encrypted data
            $iv = substr($data, 0, $ivLength);
            $encrypted = substr($data, $ivLength);
            
            // Decrypt the data
            return openssl_decrypt(
                $encrypted,
                $this->cipher,
                $this->encryptionKey,
                OPENSSL_RAW_DATA,
                $iv
            );
        } catch (Exception $e) {
            error_log('Decryption error: ' . $e->getMessage());
            return '';
        }
    }
    
    /**
     * Check if a string is already encrypted
     * 
     * @param string $data Data to check
     * @return bool True if data appears to be encrypted
     */
    public function isEncrypted($data) {
        if (empty($data)) {
            return false;
        }
        
        // Try to base64 decode
        $decoded = base64_decode($data, true);
        if ($decoded === false) {
            return false;
        }
        
        // Check length - encrypted data should be longer than IV length
        $ivLength = openssl_cipher_iv_length($this->cipher);
        if (strlen($decoded) <= $ivLength) {
            return false;
        }
        
        // This is a heuristic check - not foolproof but good enough for most cases
        return true;
    }
}
?> 