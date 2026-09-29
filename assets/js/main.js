/**
 * CarePulse AI – Master Frontend Application Engine
 * Handles Dark/Light Mode, CSRF-secured AJAX, Toasts, and UI utilities.
 */

window.CarePulse = {
    // 1. Toast Notification Manager
    showToast(message, type = 'info') {
        const toastEl = document.getElementById('carepulseToast');
        if (!toastEl) return;

        const textEl = document.getElementById('toastText');
        const iconEl = document.getElementById('toastIcon');

        textEl.textContent = message;

        // Reset styling
        toastEl.className = 'toast align-items-center border-0 shadow-lg';
        iconEl.className = 'bi fs-5';

        switch (type) {
            case 'success':
                toastEl.classList.add('bg-success', 'text-white');
                iconEl.classList.add('bi-check-circle-fill', 'text-white');
                break;
            case 'error':
            case 'danger':
                toastEl.classList.add('bg-danger', 'text-white');
                iconEl.classList.add('bi-exclamation-triangle-fill', 'text-white');
                break;
            case 'warning':
                toastEl.classList.add('bg-warning', 'text-dark');
                iconEl.classList.add('bi-exclamation-circle-fill', 'text-dark');
                break;
            default:
                toastEl.classList.add('bg-primary', 'text-white');
                iconEl.classList.add('bi-info-circle-fill', 'text-white');
                break;
        }

        const toast = new bootstrap.Toast(toastEl, { delay: 4500 });
        toast.show();
    },

    // 2. Secured Fetch API Wrapper with CSRF header injection
    async api(endpoint, options = {}) {
        const url = endpoint.startsWith('http') ? endpoint : `${window.APP_CONFIG.baseUrl}/${endpoint.replace(/^\//, '')}`;
        
        const defaultHeaders = {
            'Accept': 'application/json',
            'X-CSRF-Token': window.APP_CONFIG.csrfToken
        };

        if (!(options.body instanceof FormData)) {
            defaultHeaders['Content-Type'] = 'application/json';
            if (options.body && typeof options.body === 'object') {
                options.body = JSON.stringify(options.body);
            }
        }

        options.headers = {
            ...defaultHeaders,
            ...(options.headers || {})
        };

        try {
            const response = await fetch(url, options);
            const data = await response.json();

            if (!response.ok && data.redirect) {
                window.location.href = data.redirect;
                return data;
            }

            return data;
        } catch (err) {
            console.error('[CarePulse API Error]', err);
            return {
                success: false,
                error: 'Network or server communication error. Please check your connection.'
            };
        }
    },

    // 3. Button Loading State
    setButtonLoading(btn, isLoading, originalHtml = '') {
        if (!btn) return;
        if (isLoading) {
            btn.dataset.originalHtml = btn.innerHTML;
            btn.disabled = true;
            btn.innerHTML = `<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Processing...`;
        } else {
            btn.disabled = false;
            btn.innerHTML = btn.dataset.originalHtml || originalHtml;
        }
    }
};

// Initialize Theme & Listeners on DOM Load
document.addEventListener('DOMContentLoaded', () => {
    // Theme Switcher Logic
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const darkIcon = document.querySelector('.theme-icon-dark');
    const lightIcon = document.querySelector('.theme-icon-light');

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-bs-theme', theme);
        localStorage.setItem('carepulse_theme', theme);
        if (theme === 'dark') {
            darkIcon?.classList.add('d-none');
            lightIcon?.classList.remove('d-none');
        } else {
            darkIcon?.classList.remove('d-none');
            lightIcon?.classList.add('d-none');
        }
    }

    const savedTheme = localStorage.getItem('carepulse_theme') || 
        (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    applyTheme(savedTheme);

    themeToggleBtn?.addEventListener('click', () => {
        const current = document.documentElement.getAttribute('data-bs-theme');
        applyTheme(current === 'dark' ? 'light' : 'dark');
    });
});
