<?php
// Check if user is logged in and is admin
if (!isLoggedIn() || !isAdmin()) {
    $_SESSION['flash_message'] = "You don't have permission to access this page";
    $_SESSION['flash_type'] = "error";
    header("Location: index.php");
    exit;
}

// Process flash messages
$flashMessage = isset($_SESSION['flash_message']) ? $_SESSION['flash_message'] : '';
$flashType = isset($_SESSION['flash_type']) ? $_SESSION['flash_type'] : '';

// Clear flash message after retrieving it
if (isset($_SESSION['flash_message'])) {
    unset($_SESSION['flash_message']);
    unset($_SESSION['flash_type']);
}
?>

<div class="container mx-auto px-4 py-8">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
        <div>
            <h1 class="text-3xl font-bold text-blue-800">Reservation Calendar</h1>
            <p class="text-gray-600 mt-1">View all reservations in a calendar format</p>
        </div>
        <div class="mt-4 md:mt-0">
            <a href="index.php?page=admin&section=reservations" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-gray-600 hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                <i class="fas fa-list mr-2"></i> List View
            </a>
        </div>
    </div>
    
    <!-- Calendar Filter Section -->
    <div class="bg-white shadow rounded-lg mb-8">
        <div class="px-4 py-5 sm:p-6">
            <form id="calendarFilters" class="space-y-4 sm:space-y-0 sm:flex sm:flex-wrap sm:items-end sm:gap-4">
                <div class="w-full sm:w-[calc(30%-16px)]">
                    <label for="status-filter" class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                    <select id="status-filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="all">All Status</option>
                        <option value="pending">Pending</option>
                        <option value="approved">Approved</option>
                        <option value="rejected">Rejected</option>
                        <option value="cancelled">Cancelled</option>
                        <option value="completed">Completed</option>
                        <option value="for_delivery">For Delivery</option>
                    </select>
                </div>
                
                <div class="w-full sm:w-[calc(30%-16px)]">
                    <label for="resource-filter" class="block text-sm font-medium text-gray-700 mb-1">Resource Type</label>
                    <select id="resource-filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="all">All Resources</option>
                        <option value="facility">Facilities Only</option>
                        <option value="equipment">Equipment Only</option>
                    </select>
                </div>
                
                <div class="w-full sm:w-[calc(30%-16px)]">
                    <label for="view-filter" class="block text-sm font-medium text-gray-700 mb-1">Calendar View</label>
                    <select id="view-filter" class="shadow-sm focus:ring-blue-500 focus:border-blue-500 block w-full sm:text-sm border-gray-300 rounded-md">
                        <option value="dayGridMonth">Month</option>
                        <option value="timeGridWeek">Week</option>
                        <option value="timeGridDay">Day</option>
                        <option value="listWeek">List</option>
                    </select>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Main Calendar Container -->
    <div class="bg-white shadow rounded-lg">
        <div class="px-4 py-5 sm:p-6">
            <div id="calendar" class="w-full" style="min-height: 700px;"></div>
        </div>
    </div>
</div>

<!-- Modal for Event Details -->
<div id="eventModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 hidden overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center border-b pb-3">
            <h3 class="text-lg font-medium text-gray-900" id="modal-title">Reservation Details</h3>
            <button id="closeModal" class="text-gray-400 hover:text-gray-500">
                <span class="sr-only">Close</span>
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
        <div class="mt-4" id="modal-content">
            <div class="animate-pulse flex flex-col">
                <div class="h-4 bg-gray-200 rounded w-3/4 mb-4"></div>
                <div class="h-4 bg-gray-200 rounded w-1/2 mb-3"></div>
                <div class="h-4 bg-gray-200 rounded w-5/6 mb-3"></div>
                <div class="h-4 bg-gray-200 rounded w-2/3 mb-3"></div>
            </div>
        </div>
        <div class="mt-5 flex justify-end">
            <a id="viewDetailsBtn" href="#" class="inline-flex justify-center px-4 py-2 text-sm font-medium text-white bg-blue-600 border border-transparent rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                View Full Details
            </a>
        </div>
    </div>
</div>

<!-- FullCalendar and Dependencies -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Initialize FullCalendar
    const calendarEl = document.getElementById('calendar');
    const statusFilter = document.getElementById('status-filter');
    const resourceFilter = document.getElementById('resource-filter');
    const viewFilter = document.getElementById('view-filter');
    const modal = document.getElementById('eventModal');
    const closeModal = document.getElementById('closeModal');
    const modalContent = document.getElementById('modal-content');
    const modalTitle = document.getElementById('modal-title');
    const viewDetailsBtn = document.getElementById('viewDetailsBtn');
    
    // Modal functionality
    closeModal.addEventListener('click', function() {
        modal.classList.add('hidden');
    });
    
    window.addEventListener('click', function(event) {
        if (event.target === modal) {
            modal.classList.add('hidden');
        }
    });
    
    // Define status colors
    const statusColors = {
        'pending': '#FBBF24',     // Yellow
        'approved': '#34D399',    // Green
        'rejected': '#F87171',    // Red
        'cancelled': '#F87171',   // Red
        'completed': '#60A5FA',   // Blue
        'for_delivery': '#818CF8', // Indigo
        'for_pickup': '#A78BFA'   // Purple
    };
    
    // Initialize calendar
    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: 'dayGridMonth',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
        },
        events: function(info, successCallback, failureCallback) {
            // Get current filter values
            const status = statusFilter.value;
            const resourceType = resourceFilter.value;
            
            // Fetch events with the filters
            fetch(`index.php?page=admin&section=calendar&action=get_reservations&start=${info.startStr}&end=${info.endStr}&status=${status}&resource_type=${resourceType}`)
                .then(response => response.json())
                .then(data => {
                    // Map reservations to calendar events
                    const events = data.events.map(reservation => {
                        return {
                            id: reservation.id,
                            title: reservation.title || `#${reservation.id}`,
                            start: reservation.start_datetime,
                            end: reservation.end_datetime,
                            extendedProps: {
                                userId: reservation.user_id,
                                userName: reservation.user_name || 'Unknown',
                                resources: reservation.resources || 'Not specified',
                                status: reservation.status || 'unknown',
                                contact: reservation.contact_number || 'Not provided',
                                address: reservation.address || 'Not provided',
                                paymentStatus: reservation.payment_status || 'unknown'
                            },
                            backgroundColor: statusColors[reservation.status] || '#9CA3AF',
                            borderColor: statusColors[reservation.status] || '#9CA3AF'
                        };
                    });
                    
                    successCallback(events);
                })
                .catch(error => {
                    console.error('Error fetching events:', error);
                    failureCallback(error);
                });
        },
        
        // Handle event click to show details
        eventClick: function(info) {
            const event = info.event;
            const props = event.extendedProps;
            
            // Update modal content
            modalTitle.textContent = `Reservation #${event.id}`;
            
            // Create status badge
            let statusBadgeClass = 'inline-block px-2 py-1 text-xs font-semibold rounded-full';
            switch (props.status) {
                case 'pending': statusBadgeClass += ' bg-yellow-100 text-yellow-800'; break;
                case 'approved': statusBadgeClass += ' bg-green-100 text-green-800'; break;
                case 'rejected': statusBadgeClass += ' bg-red-100 text-red-800'; break;
                case 'cancelled': statusBadgeClass += ' bg-red-100 text-red-800'; break;
                case 'completed': statusBadgeClass += ' bg-blue-100 text-blue-800'; break;
                case 'for_delivery': statusBadgeClass += ' bg-indigo-100 text-indigo-800'; break;
                case 'for_pickup': statusBadgeClass += ' bg-purple-100 text-purple-800'; break;
                default: statusBadgeClass += ' bg-gray-100 text-gray-800';
            }
            
            // Format the date and time
            const start = new Date(event.start);
            const end = event.end ? new Date(event.end) : new Date(start.getTime() + 3600000); // Default to 1 hour
            const dateOptions = { year: 'numeric', month: 'long', day: 'numeric' };
            const timeOptions = { hour: '2-digit', minute: '2-digit' };
            
            const dateStr = start.toLocaleDateString('en-US', dateOptions);
            const startTimeStr = start.toLocaleTimeString('en-US', timeOptions);
            const endTimeStr = end.toLocaleTimeString('en-US', timeOptions);
            
            // Populate modal content
            modalContent.innerHTML = `
                <div class="mb-4">
                    <p class="font-bold">Status: <span class="${statusBadgeClass}">${props.status.charAt(0).toUpperCase() + props.status.slice(1)}</span></p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-500">Reserved By</p>
                    <p class="text-base">${props.userName}</p>
                    <p class="text-sm text-gray-500">${props.contact}</p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-500">Resources</p>
                    <p class="text-base">${props.resources}</p>
                </div>
                <div class="mb-4">
                    <p class="text-sm font-medium text-gray-500">Date and Time</p>
                    <p class="text-base">${dateStr}</p>
                    <p class="text-sm text-gray-500">${startTimeStr} - ${endTimeStr}</p>
                </div>
                <div>
                    <p class="text-sm font-medium text-gray-500">Address</p>
                    <p class="text-base">${props.address}</p>
                </div>
            `;
            
            // Update view details button
            viewDetailsBtn.href = `index.php?page=admin&section=view_reservation&id=${event.id}`;
            
            // Show modal
            modal.classList.remove('hidden');
        }
    });
    
    calendar.render();
    
    // Add filter change event listeners
    statusFilter.addEventListener('change', function() {
        calendar.refetchEvents();
    });
    
    resourceFilter.addEventListener('change', function() {
        calendar.refetchEvents();
    });
    
    viewFilter.addEventListener('change', function() {
        calendar.changeView(viewFilter.value);
    });
});
</script>

<style>
/* Additional styles for the calendar */
.fc-event {
    cursor: pointer;
}

.fc-event-title {
    font-weight: 500;
}

/* Fix for FullCalendar with TailwindCSS conflicts */
.fc-theme-standard td, .fc-theme-standard th {
    border: 1px solid #ddd;
}

.fc-theme-standard .fc-scrollgrid {
    border: 1px solid #ddd;
}

.fc-col-header-cell-cushion, .fc-daygrid-day-number {
    color: #374151;
}
</style>