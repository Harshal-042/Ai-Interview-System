/**
 * Admin Panel Vanilla JavaScript
 * Ensures seamless authentication & iframe compatibility
 */
document.addEventListener('DOMContentLoaded', () => {
    // 1. Sync & propagate admin auth token
    try {
        const urlParams = new URLSearchParams(window.location.search);
        let token = urlParams.get('token') || urlParams.get('admin_token');

        if (token) {
            localStorage.setItem('admin_token', token);
            sessionStorage.setItem('admin_token', token);
        } else {
            token = localStorage.getItem('admin_token');
        }

        if (token) {
            // Append token to all internal admin links
            document.querySelectorAll('a').forEach(a => {
                const href = a.getAttribute('href');
                if (href && !href.startsWith('http') && !href.startsWith('#') && !href.startsWith('mailto:') && !href.includes('candidate/')) {
                    if (!href.includes('token=')) {
                        const sep = href.includes('?') ? '&' : '?';
                        a.setAttribute('href', href + sep + 'token=' + encodeURIComponent(token));
                    }
                }
            });

            // Append token as hidden input to all admin forms
            document.querySelectorAll('form').forEach(form => {
                if (!form.querySelector('input[name="token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'token';
                    input.value = token;
                    form.appendChild(input);
                }
            });
        }
    } catch (e) {
        console.warn('Token sync error:', e);
    }

    // 2. Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
