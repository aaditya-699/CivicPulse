</main> <!-- Closes .global-main-content -->
    </div> <!-- Closes .global-app-container -->

    <!-- Sidebar Toggle Script -->
    <script>
        function toggleSidebar() {
            const body = document.body;
            if (body.classList.contains('sidebar-open')) {
                body.classList.remove('sidebar-open');
                body.classList.add('sidebar-hidden');
            } else {
                body.classList.remove('sidebar-hidden');
                body.classList.add('sidebar-open');
            }
        }
    </script>
</body>
</html>