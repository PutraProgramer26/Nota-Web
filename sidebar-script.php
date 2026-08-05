<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    const toggleSidebar = document.getElementById('toggleSidebar');

    if (!sidebar || !mainContent || !toggleSidebar) {
        return;
    }

    const storageKey = 'notaSidebarCollapsed';
    const applyState = function (isCollapsed) {
        sidebar.classList.toggle('collapsed', isCollapsed);
        mainContent.classList.toggle('collapsed', isCollapsed);
        toggleSidebar.setAttribute('aria-expanded', String(!isCollapsed));
        localStorage.setItem(storageKey, isCollapsed ? '1' : '0');
    };

    const savedState = localStorage.getItem(storageKey) === '1';
    applyState(savedState);

    toggleSidebar.addEventListener('click', function () {
        const isCollapsed = !sidebar.classList.contains('collapsed');
        applyState(isCollapsed);
    });
});
</script>
