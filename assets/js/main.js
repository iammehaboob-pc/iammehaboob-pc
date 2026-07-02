/**
 * SmartFix AI - Main Client Script
 * Manages UI animations, light/dark theme toggle, custom utility functions, and CSRF validation for AJAX requests.
 */

document.addEventListener('DOMContentLoaded', () => {
    // Theme Initializer
    initTheme();
    
    // Auto-dismiss Alerts
    initAlertDismissal();
});

/**
 * Handle Theme initialization and toggle action
 */
function initTheme() {
    const themeToggleBtn = document.getElementById('theme-toggle');
    const storedTheme = localStorage.getItem('theme') || 'light';
    
    // Apply initial theme
    document.documentElement.setAttribute('data-theme', storedTheme);
    updateThemeIcon(storedTheme);
    
    if (themeToggleBtn) {
        themeToggleBtn.addEventListener('click', () => {
            const currentTheme = document.documentElement.getAttribute('data-theme');
            const targetTheme = currentTheme === 'dark' ? 'light' : 'dark';
            
            document.documentElement.setAttribute('data-theme', targetTheme);
            localStorage.setItem('theme', targetTheme);
            updateThemeIcon(targetTheme);
        });
    }
}

/**
 * Update the toggle icon based on active theme
 */
function updateThemeIcon(theme) {
    const themeIcon = document.querySelector('#theme-toggle i');
    if (themeIcon) {
        if (theme === 'dark') {
            themeIcon.className = 'fas fa-sun text-warning';
        } else {
            themeIcon.className = 'fas fa-moon text-primary';
        }
    }
}

/**
 * Automatically dismiss alerts after a set timeframe
 */
function initAlertDismissal() {
    const dismissibleAlerts = document.querySelectorAll('.alert-dismissible');
    dismissibleAlerts.forEach(alert => {
        setTimeout(() => {
            alert.classList.add('fade');
            setTimeout(() => alert.remove(), 150);
        }, 5000); // 5 seconds
    });
}

/**
 * Wrapper for AJAX Fetch requests incorporating CSRF validation
 */
async function secureFetch(url, options = {}) {
    const csrfTokenMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfTokenMeta ? csrfTokenMeta.getAttribute('content') : '';

    if (!options.headers) {
        options.headers = {};
    }

    // Assign CSRF header
    if (csrfToken) {
        options.headers['X-CSRF-Token'] = csrfToken;
    }

    try {
        const response = await fetch(url, options);
        if (!response.ok) {
            throw new Error(`HTTP Error Status: ${response.status}`);
        }
        return await response.json();
    } catch (error) {
        console.error('Secure fetch failure:', error);
        throw error;
    }
}

/**
 * Show a quick SweetAlert2 success or error message
 */
function showToast(message, icon = 'success') {
    if (typeof Swal !== 'undefined') {
        Swal.fire({
            toast: true,
            position: 'top-end',
            showConfirmButton: false,
            timer: 3000,
            timerProgressBar: true,
            icon: icon,
            title: message
        });
    } else {
        alert(message);
    }
}
