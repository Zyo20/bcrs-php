/**
 * Session Monitor - Real-time session tracking without page reloads
 * Provides countdown timer and warning when session is about to expire
 */
class SessionMonitor {
    constructor(options = {}) {
        // Configuration with defaults
        this.options = {
            checkInterval: options.checkInterval || 10000,  // How often to check session status (10 seconds)
            warningThreshold: options.warningThreshold || 10, // Show warning when session has this many seconds left
            sessionEndpoint: options.sessionEndpoint || 'includes/session_manager.php',
            debug: options.debug || false,
            onWarning: options.onWarning || null,
            onExpired: options.onExpired || null,
            onRenewed: options.onRenewed || null
        };

        // Internal state
        this.remainingTime = null;
        this.sessionTimeout = null;
        this.checkIntervalId = null;
        this.countdownIntervalId = null;
        this.warningDisplayed = false;
        this.modalElement = null;

        // Initialize
        this.init();
    }

    // Initialize the session monitor
    init() {
        // Only initialize if user is logged in (indicated by session checking div)
        if (document.getElementById('session-monitor-container')) {
            this.createWarningModal();
            this.startChecking();
            
            if (this.options.debug) {
                console.log('Session monitor initialized');
            }
        }
    }

    // Create the warning modal
    createWarningModal() {
        // Create modal container if it doesn't exist
        if (!document.getElementById('session-expiry-modal')) {
            const modal = document.createElement('div');
            modal.id = 'session-expiry-modal';
            modal.className = 'fixed inset-0 bg-gray-600 bg-opacity-75 flex items-center justify-center z-50 hidden';
            modal.innerHTML = `
                <div class="bg-white rounded-lg shadow-xl p-6 max-w-md w-full">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold text-gray-900">Session Expiring</h3>
                        <div class="flex items-center justify-center bg-red-100 text-red-800 font-bold rounded-full h-8 w-8 text-sm">
                            <span id="session-countdown">60</span>
                        </div>
                    </div>
                    <div class="mb-4">
                        <p class="text-gray-700">Your session is about to expire due to inactivity.</p>
                        <p class="text-gray-700 mt-2">Would you like to continue your session?</p>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button id="session-logout-btn" class="px-4 py-2 bg-gray-300 text-gray-800 rounded hover:bg-gray-400">Logout</button>
                        <button id="session-renew-btn" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Continue Session</button>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
            
            // Add event listeners
            document.getElementById('session-renew-btn').addEventListener('click', () => this.renewSession());
            document.getElementById('session-logout-btn').addEventListener('click', () => this.logout());
            
            this.modalElement = modal;
        }
    }

    // Start checking session status
    startChecking() {
        // Initial check
        this.checkSessionStatus();
        
        // Set interval for periodic checks
        this.checkIntervalId = setInterval(() => {
            this.checkSessionStatus();
        }, this.options.checkInterval);
        
        // Add activity listeners to reset session timeout
        ['click', 'keypress', 'scroll', 'mousemove'].forEach(eventType => {
            document.addEventListener(eventType, this.handleUserActivity.bind(this), { passive: true });
        });
    }
    
    // Handle user activity
    handleUserActivity() {
        // Only renew if the session is active but not showing a warning yet
        if (this.remainingTime > 0 && this.remainingTime > this.options.warningThreshold) {
            this.renewSession(true); // Silent renewal
        }
    }

    // Check session status with the server
    checkSessionStatus() {
        fetch(`${this.options.sessionEndpoint}?action=check`)
            .then(response => response.json())
            .then(data => {
                if (this.options.debug) {
                    console.log('Session status:', data);
                }
                
                if (data.status === 'active') {
                    this.remainingTime = data.remainingTime;
                    this.sessionTimeout = data.timeout;
                    
                    // If session is about to expire, show warning
                    if (this.remainingTime <= this.options.warningThreshold && !this.warningDisplayed) {
                        this.showWarning();
                    }
                } else if (data.status === 'expired') {
                    this.handleExpiredSession();
                }
            })
            .catch(error => {
                console.error('Error checking session status:', error);
            });
    }

    // Show warning modal with countdown
    showWarning() {
        this.warningDisplayed = true;
        this.modalElement.classList.remove('hidden');
        
        // Update countdown display
        const countdownElement = document.getElementById('session-countdown');
        if (countdownElement) {
            countdownElement.textContent = Math.ceil(this.remainingTime);
        }
        
        // Start countdown timer
        this.countdownIntervalId = setInterval(() => {
            this.remainingTime -= 1;
            
            if (countdownElement) {
                countdownElement.textContent = Math.max(0, Math.ceil(this.remainingTime));
            }
            
            if (this.remainingTime <= 0) {
                clearInterval(this.countdownIntervalId);
                this.handleExpiredSession();
            }
        }, 1000);
        
        // Call custom warning handler if provided
        if (typeof this.options.onWarning === 'function') {
            this.options.onWarning();
        }
    }

    // Renew the session
    renewSession(silent = false) {
        fetch(`${this.options.sessionEndpoint}?action=renew`)
            .then(response => response.json())
            .then(data => {
                if (this.options.debug) {
                    console.log('Session renewal:', data);
                }
                
                if (data.status === 'renewed') {
                    // Reset internal state
                    this.remainingTime = data.remainingTime;
                    this.warningDisplayed = false;
                    
                    // Clear countdown interval if it exists
                    if (this.countdownIntervalId) {
                        clearInterval(this.countdownIntervalId);
                    }
                    
                    // Hide the modal
                    if (!silent && this.modalElement) {
                        this.modalElement.classList.add('hidden');
                    }
                    
                    // Call custom renewal handler if provided
                    if (!silent && typeof this.options.onRenewed === 'function') {
                        this.options.onRenewed();
                    }
                } else if (data.status === 'expired') {
                    this.handleExpiredSession();
                }
            })
            .catch(error => {
                console.error('Error renewing session:', error);
            });
    }

    // Handle expired session
    handleExpiredSession() {
        // Clear intervals
        if (this.checkIntervalId) {
            clearInterval(this.checkIntervalId);
        }
        if (this.countdownIntervalId) {
            clearInterval(this.countdownIntervalId);
        }
        
        // Call custom expired handler if provided
        if (typeof this.options.onExpired === 'function') {
            this.options.onExpired();
        }
        
        // Redirect to login page - using the same format as the rest of the site (without .php extension)
        window.location.href = 'index?page=login&session_expired=1';
    }

    // Logout function
    logout() {
        // Use the same URL format as the rest of the site
        window.location.href = 'includes/logout';
    }
}

// Initialize the session monitor when document is ready
document.addEventListener('DOMContentLoaded', function() {
    window.sessionMonitor = new SessionMonitor({
        // Custom options can be set here
        warningThreshold: 60, // Show warning when 60 seconds are left
        onExpired: function() {
            console.log('Session expired');
        },
        onRenewed: function() {
            // Show a brief notification that session was extended
            const notification = document.createElement('div');
            notification.className = 'fixed bottom-4 right-4 bg-green-100 border-l-4 border-green-500 text-green-700 p-4 shadow-md rounded';
            notification.innerHTML = '<div class="flex"><div class="py-1"><svg class="w-6 h-6 mr-4" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg></div><div><p class="font-bold">Session Extended</p><p class="text-sm">Your session has been successfully extended.</p></div></div>';
            document.body.appendChild(notification);
            
            // Remove the notification after 3 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s ease-out';
                setTimeout(() => {
                    notification.remove();
                }, 500);
            }, 3000);
        }
    });
});