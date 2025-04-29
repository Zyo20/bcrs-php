</main>
    
    <footer class="bg-gray-600 text-white py-4 mt-auto">
        <div class="container mx-auto px-4">
            <div class="text-center">
                <p>&copy; <?php echo date('Y'); ?> BARSERVE. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <!-- Remove any notification bubble here if it exists -->
    <script>
        // Make sure Alpine.js has initialized before running custom code
        document.addEventListener('alpine:init', () => {
            // This will help ensure Alpine components are properly initialized
            console.log('Alpine.js initialized successfully');
        });
    </script>
</body>
</html>