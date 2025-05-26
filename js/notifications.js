/**
 * Notifications JavaScript functionality
 * Handles fetching, displaying, and managing notifications
 */

// Initialize notifications when the DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if user is logged in (notification dropdown exists)
    if (document.getElementById('notificationDropdown')) {
        loadNotifications();
        
        // Set up click away listener to close the notifications dropdown
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationsMenu');
            const button = document.querySelector('#notificationDropdown button');
            
            if (dropdown && !dropdown.classList.contains('hidden') && 
                !dropdown.contains(event.target) && event.target !== button && !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });
    }
});

/**
 * Load notifications from the server
 */
function loadNotifications() {
    fetch('index.php?get_notifications=true')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                updateNotifications(data.notifications);
                updateNotificationCount(data.unreadCount);
            } else {
                showNotificationError();
            }
        })
        .catch(error => {
            console.error('Error loading notifications:', error);
            showNotificationError();
        });
}

/**
 * Update the notifications dropdown with notifications
 */
function updateNotifications(notifications) {
    const notificationsList = document.getElementById('notificationsList');
    
    if (!notifications || notifications.length === 0) {
        notificationsList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications</div>';
        return;
    }
    
    let html = '';
    
    notifications.forEach(notification => {
        const isUnread = !notification.is_read;
        const timeAgo = timeAgoFormat(notification.created_at);
        
        // Create a wrapper that's clickable if there's a link
        const wrapperStart = notification.link ? 
            `<div class="notification-item p-3 border-b border-gray-100 hover:bg-gray-50 ${isUnread ? 'bg-blue-50' : ''}" data-id="${notification.id}" onclick="handleNotificationClick(${notification.id}, '${notification.link}')">` : 
            `<div class="notification-item p-3 border-b border-gray-100 hover:bg-gray-50 ${isUnread ? 'bg-blue-50' : ''}" data-id="${notification.id}">`;
        
        html += wrapperStart + `
                <div class="flex items-start">
                    <div class="flex-grow">
                        <p class="text-sm ${isUnread ? 'font-medium' : 'text-gray-700'}">${notification.message}</p>
                        <p class="text-xs text-gray-500 mt-1">${timeAgo}</p>
                    </div>
                    <div>
                        <button onclick="deleteNotification(event, ${notification.id})" class="text-gray-400 hover:text-gray-600">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </div>
            </div>
        `;
    });
    
    notificationsList.innerHTML = html;
}

/**
 * Update notification count badge
 */
function updateNotificationCount(count) {
    const countBadge = document.getElementById('notificationCount');
    
    if (count > 0) {
        countBadge.textContent = count > 99 ? '99+' : count;
        countBadge.classList.remove('hidden');
    } else {
        countBadge.classList.add('hidden');
    }
}

/**
 * Show error message when notifications can't be loaded
 */
function showNotificationError() {
    const notificationsList = document.getElementById('notificationsList');
    notificationsList.innerHTML = '<div class="p-4 text-center text-red-500">Failed to load notifications</div>';
}

/**
 * Mark a notification as read
 */
function markNotificationRead(id) {
    fetch('index.php?notification_action=true', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_read',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadNotifications(); // Refresh notifications
        }
    })
    .catch(error => {
        console.error('Error marking notification as read:', error);
    });
}

/**
 * Delete a notification
 */
function deleteNotification(event, id) {
    event.preventDefault(); // Prevent event bubbling
    event.stopPropagation(); // Stop propagation to prevent triggering parent elements
    
    fetch('index.php?notification_action=true', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'clear',
            id: id
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Reload notifications instead of trying to manipulate the DOM
            loadNotifications();
        } else {
            console.error('Error deleting notification:', data.message);
        }
    })
    .catch(error => {
        console.error('Error deleting notification:', error);
    });
}

/**
 * Clear all notifications
 */
function clearAllNotifications() {
    fetch('index.php?notification_action=true', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'clear_all'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            const notificationsList = document.getElementById('notificationsList');
            notificationsList.innerHTML = '<div class="p-4 text-center text-gray-500">No notifications</div>';
            
            // Update the notification count
            updateNotificationCount(0);
        }
    })
    .catch(error => {
        console.error('Error clearing notifications:', error);
    });
}

/**
 * Toggle the notifications dropdown
 */
function toggleNotifications() {
    const menu = document.getElementById('notificationsMenu');
    menu.classList.toggle('hidden');
    
    // When opening the dropdown, mark all as read
    if (!menu.classList.contains('hidden')) {
        markAllNotificationsRead();
    }
}

/**
 * Mark all notifications as read
 */
function markAllNotificationsRead() {
    fetch('index.php?notification_action=true', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            action: 'mark_all_read'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove unread styling from all notifications
            document.querySelectorAll('.notification-item').forEach(item => {
                item.classList.remove('bg-blue-50');
            });
            
            // Update the notification count
            updateNotificationCount(0);
        }
    })
    .catch(error => {
        console.error('Error marking all notifications as read:', error);
    });
}

/**
 * Format timestamp to "time ago" format
 */
function timeAgoFormat(timestamp) {
    // Ensure timestamp is treated as Manila time (UTC+8)
    // Convert timestamp format to include 'T' and add '+08:00' for Manila timezone
    const formattedTimestamp = timestamp.replace(' ', 'T') + '+08:00';
    
    const now = new Date();
    const date = new Date(formattedTimestamp);
    const seconds = Math.floor((now - date) / 1000);
    
    let interval = Math.floor(seconds / 31536000);
    if (interval >= 1) {
        return interval + ' year' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 2592000);
    if (interval >= 1) {
        return interval + ' month' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 86400);
    if (interval >= 1) {
        return interval + ' day' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 3600);
    if (interval >= 1) {
        return interval + ' hour' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    interval = Math.floor(seconds / 60);
    if (interval >= 1) {
        return interval + ' minute' + (interval === 1 ? '' : 's') + ' ago';
    }
    
    return 'just now';
}

/**
 * Handle click on a notification item
 */
function handleNotificationClick(id, link) {
    // Mark the notification as read
    markNotificationRead(id);
    
    // Navigate to the link
    if (link) {
        window.location.href = link;
    }
}