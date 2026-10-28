/**
 * Dukaan OS — Main JS Entry
 *
 * Import order:
 *   1. Axios (HTTP helper, CSRF configured)
 *   2. Bootstrap JS (dropdowns, modals, collapse)
 *   3. App-specific modules (added per step)
 */

// 1. Axios + CSRF
import axios from 'axios';
window.axios = axios;
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// 2. Bootstrap JS (full bundle: Popper bundled)
import * as bootstrap from 'bootstrap';
window.bootstrap = bootstrap;

// 3. Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', () => {
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.querySelector('.app-sidebar');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });

        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', (e) => {
            if (
                sidebar.classList.contains('show') &&
                !sidebar.contains(e.target) &&
                !sidebarToggle.contains(e.target)
            ) {
                sidebar.classList.remove('show');
            }
        });
    }
});
