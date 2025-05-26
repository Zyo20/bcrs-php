<div class="text-center mb-10">
    <h1 class="text-3xl font-bold text-blue-800 mb-4">Barangay Resource Reservation Management System</h1>
    <p class="text-lg text-gray-600 max-w-3xl mx-auto">Streamlining resource management and booking within the barangay</p>
</div>

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-10">
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
        <div class="text-blue-600 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">User Registration</h3>
        <p class="text-gray-600">Register to access resource booking and management features. Verified residents can make reservations and track their requests.</p>
        <div class="mt-4">
            <a href="index.php?page=register" class="text-blue-600 hover:underline">Register Now →</a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
        <div class="text-blue-600 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Resource Booking</h3>
        <p class="text-gray-600">Book facilities like the gymnasium or equipment such as tents, chairs, and audio systems for your community events.</p>
        <div class="mt-4">
            <a href="index.php?page=resources" class="text-blue-600 hover:underline">View Resources →</a>
        </div>
    </div>
    
    <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
        <div class="text-blue-600 mb-3">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-10 w-10" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
            </svg>
        </div>
        <h3 class="text-xl font-semibold mb-2">Reservation Tracking</h3>
        <p class="text-gray-600">Track the status of your reservations from approval to completion. Receive notifications at each step of the process.</p>
        <div class="mt-4">
            <a href="index.php?page=dashboard" class="text-blue-600 hover:underline">Your Dashboard →</a>
        </div>
    </div>
</div>

<div class="bg-blue-50 rounded-lg p-6 mb-10">
    <h2 class="text-2xl font-bold text-blue-800 mb-4">How It Works</h2>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="flex flex-col items-center text-center p-4">
            <div class="bg-blue-600 text-white rounded-full w-10 h-10 flex items-center justify-center mb-3">1</div>
            <h3 class="font-semibold mb-2">Register</h3>
            <p class="text-sm text-gray-600">Create an account and verify your identity as a barangay resident</p>
        </div>
        
        <div class="flex flex-col items-center text-center p-4">
            <div class="bg-blue-600 text-white rounded-full w-10 h-10 flex items-center justify-center mb-3">2</div>
            <h3 class="font-semibold mb-2">Browse Resources</h3>
            <p class="text-sm text-gray-600">View available facilities and equipment for booking</p>
        </div>
        
        <div class="flex flex-col items-center text-center p-4">
            <div class="bg-blue-600 text-white rounded-full w-10 h-10 flex items-center justify-center mb-3">3</div>
            <h3 class="font-semibold mb-2">Make Reservation</h3>
            <p class="text-sm text-gray-600">Submit your booking request with details and wait for approval</p>
        </div>
        
        <div class="flex flex-col items-center text-center p-4">
            <div class="bg-blue-600 text-white rounded-full w-10 h-10 flex items-center justify-center mb-3">4</div>
            <h3 class="font-semibold mb-2">Track & Collect</h3>
            <p class="text-sm text-gray-600">Monitor your reservation status and collect or use your resources</p>
        </div>
    </div>
</div>

<?php if (!isLoggedIn()): ?>
<div class="bg-white rounded-lg shadow-md p-6 text-center">
    <h2 class="text-2xl font-bold text-blue-800 mb-3">Ready to Get Started?</h2>
    <p class="text-gray-600 mb-6">Join the community and start booking resources for your events.</p>
    <div class="flex justify-center space-x-4">
        <a href="index.php?page=register" class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition duration-300">Register Now</a>
        <a href="index.php?page=login" class="px-6 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300 transition duration-300">Login</a>
    </div>
</div>
<?php endif; ?> 