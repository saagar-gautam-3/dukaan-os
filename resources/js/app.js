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

// 3. Accessible password visibility and strong-password controls
import './password-fields';

// 4. Sidebar toggle for mobile
document.addEventListener('DOMContentLoaded', () => {
    const networkStatus = document.getElementById('network-status');

    const showNetworkMessage = (message) => {
        if (!networkStatus) return;
        networkStatus.textContent = message;
        networkStatus.classList.remove('d-none');
    };
    const hideNetworkMessage = () => networkStatus?.classList.add('d-none');

    window.addEventListener('offline', () => showNetworkMessage(networkStatus?.dataset.offline ?? ''));
    window.addEventListener('online', () => {
        showNetworkMessage(networkStatus?.dataset.online ?? '');
        window.setTimeout(hideNetworkMessage, 3500);
    });
    const nativeFetch = window.fetch.bind(window);
    window.fetch = async (...args) => {
        const startedAt = performance.now();
        try {
            const response = await nativeFetch(...args);
            if (performance.now() - startedAt > 3000 && navigator.onLine) {
                showNetworkMessage(networkStatus?.dataset.slow ?? '');
                window.setTimeout(hideNetworkMessage, 5000);
            }
            return response;
        } catch (error) {
            if (!navigator.onLine) showNetworkMessage(networkStatus?.dataset.offline ?? '');
            throw error;
        }
    };

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || form.dataset.skipLoading === 'true') return;

        for (const button of form.querySelectorAll('button[type="submit"], input[type="submit"]')) {
            button.disabled = true;
            if (button instanceof HTMLButtonElement) {
                const spinner = document.createElement('span');
                spinner.className = 'spinner-border spinner-border-sm me-2';
                spinner.setAttribute('aria-hidden', 'true');
                button.replaceChildren(spinner, document.createTextNode(window.appMessages?.submitting ?? button.textContent));
                button.setAttribute('aria-busy', 'true');
            }
        }
    });

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    const sidebarBackdrop = document.getElementById('sidebarBackdrop');
    const sidebarNav = document.querySelector('[data-sidebar-scroll-region]');

    if (sidebarNav) {
        const activeItem = sidebarNav.querySelector('.nav-link.active');
        if (activeItem) {
            activeItem.scrollIntoView({ block: 'nearest' });
        }
    }

    if (sidebarToggle && sidebar) {
        const setSidebarOpen = (isOpen) => {
            sidebar.classList.toggle('show', isOpen);
            sidebarBackdrop?.classList.toggle('d-none', !isOpen);
            document.body.classList.toggle('sidebar-open', isOpen);
            sidebarToggle.setAttribute('aria-expanded', String(isOpen));
        };

        sidebarToggle.addEventListener('click', () => {
            setSidebarOpen(!sidebar.classList.contains('show'));
        });

        sidebarBackdrop?.addEventListener('click', () => setSidebarOpen(false));

        sidebar.addEventListener('click', (event) => {
            if (event.target.closest('a.nav-link, form.sidebar-logout button')) {
                setSidebarOpen(false);
            }
        });

        document.addEventListener('click', (event) => {
            if (sidebar.classList.contains('show') &&
                !sidebar.contains(event.target) &&
                !sidebarToggle.contains(event.target)) {
                setSidebarOpen(false);
            }
        });

        window.addEventListener('resize', () => {
            if (window.innerWidth >= 992 && sidebar.classList.contains('show')) {
                setSidebarOpen(false);
            }
        });
    }
});
