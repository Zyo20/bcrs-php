<div class="flex flex-col items-center justify-center py-12">
    <div class="text-6xl font-bold text-blue-600 mb-4">404</div>
    <h1 class="text-2xl font-semibold text-gray-800 mb-6">Page Not Found</h1>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="bg-red-100 border border-red-200 text-red-700 px-4 py-3 rounded mb-6 max-w-md text-center">
            <?php 
                echo $_SESSION['error_message'];
                // Clear the message after displaying it
                unset($_SESSION['error_message']);
            ?>
        </div>
    <?php else: ?>
        <p class="text-gray-600 mb-8">The page you are looking for might have been removed or is temporarily unavailable.</p>
    <?php endif; ?>
    
    <a href="index" class="px-6 py-3 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition duration-300">Return to Home</a>
</div>