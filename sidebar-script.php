<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleSidebar = document.getElementById('toggleSidebar');
    if (!sidebar || !mainContent || !toggleSidebar) {
        return;
    }
    toggleSidebar.addEventListener('click', function () {
        sidebar.classList.toggle('collapsed');
        mainContent.classList.toggle('collapsed');
    });
});
</script>
