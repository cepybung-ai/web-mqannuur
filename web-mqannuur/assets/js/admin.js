(() => {
    const toggle = document.getElementById('adminMenuToggle');
    const overlay = document.getElementById('adminOverlay');
    const closeNav = () => {
        document.body.classList.remove('admin-nav-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    };
    if (toggle) {
        toggle.addEventListener('click', () => {
            const open = document.body.classList.toggle('admin-nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    if (overlay) overlay.addEventListener('click', closeNav);
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeNav();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 1000) closeNav();
    });
    document.querySelectorAll('.admin-nav a').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 1000) closeNav();
        });
    });
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', event => {
            const message = form.getAttribute('data-confirm') || 'Lanjutkan aksi ini?';
            if (!window.confirm(message)) event.preventDefault();
        });
    });
    document.querySelectorAll('.admin-alert').forEach(alert => {
        window.setTimeout(() => {
            alert.style.transition = 'opacity .3s ease, transform .3s ease';
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-4px)';
            window.setTimeout(() => alert.remove(), 350);
        }, 7000);
    });

    const copyStatus = (message, failed = false) => {
        let toast = document.getElementById('admin-copy-toast');
        if (!toast) {
            toast = document.createElement('p');
            toast.id = 'admin-copy-toast';
            toast.className = 'admin-copy-toast';
            toast.setAttribute('role', 'status');
            toast.setAttribute('aria-live', 'polite');
            document.body.append(toast);
        }
        toast.textContent = message;
        toast.dataset.state = failed ? 'error' : 'success';
        toast.classList.add('is-visible');
        window.clearTimeout(toast._hideTimer);
        toast._hideTimer = window.setTimeout(() => toast.classList.remove('is-visible'), 2600);
    };

    document.addEventListener('click', async event => {
        const button = event.target.closest('[data-copy-link]');
        if (!button) return;
        event.preventDefault();
        const value = button.getAttribute('data-copy-link') || '';
        if (value === '') return;
        const absolute = new URL(value, window.location.href).href;
        try {
            if (navigator.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(absolute);
            } else {
                const input = document.createElement('textarea');
                input.value = absolute;
                input.setAttribute('readonly', '');
                input.style.position = 'fixed';
                input.style.left = '-10000px';
                document.body.append(input);
                input.select();
                document.execCommand('copy');
                input.remove();
            }
            copyStatus('Link berhasil disalin.');
        } catch (error) {
            copyStatus('Link tidak dapat disalin. Salin manual dari kolom yang tersedia.', true);
        }
    });

    document.querySelectorAll('[data-image-input]').forEach(input => {
        const previewId = input.getAttribute('data-image-input');
        const preview = previewId ? document.getElementById(previewId) : null;
        if (!preview) return;
        input.addEventListener('change', () => {
            const file = input.files && input.files[0];
            if (!file || !file.type.startsWith('image/')) {
                preview.removeAttribute('src');
                preview.hidden = true;
                return;
            }
            const reader = new FileReader();
            reader.addEventListener('load', () => {
                preview.src = String(reader.result || '');
                preview.hidden = false;
            });
            reader.readAsDataURL(file);
        });
    });
})();
