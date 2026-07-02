<?php
/**
 * SmartFix AI - Footer Template
 */
?>
<?php if (Session::isLoggedIn() && !isset($noLayout)): ?>
</div> <!-- .app-layout -->
<?php endif; ?>

<!-- Core JavaScript Dependencies -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?php echo SITE_URL; ?>/assets/js/main.js"></script>

<script>
    // Custom responsive mobile menu support
    document.addEventListener('DOMContentLoaded', () => {
        const toggleBtn = document.querySelector('.toggle-sidebar');
        const sidebar = document.querySelector('.sidebar');
        
        if (toggleBtn && sidebar) {
            toggleBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                sidebar.classList.toggle('active');
            });
            
            document.addEventListener('click', (e) => {
                if (sidebar.classList.contains('active') && !sidebar.contains(e.target) && !toggleBtn.contains(e.target)) {
                    sidebar.classList.remove('active');
                }
            });
        }
    });
</script>

</body>
</html>
