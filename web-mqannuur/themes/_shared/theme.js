(() => {
    const toggle = document.getElementById('theme-menu-toggle');
    const nav = document.getElementById('theme-nav');
    const closeButton = document.getElementById('theme-nav-close');
    const closeNav = () => {
        document.body.classList.remove('theme-nav-open');
        if (toggle) toggle.setAttribute('aria-expanded', 'false');
    };
    if (toggle) {
        toggle.addEventListener('click', () => {
            const open = document.body.classList.toggle('theme-nav-open');
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    }
    if (closeButton) closeButton.addEventListener('click', closeNav);
    if (nav) {
        nav.querySelectorAll('a').forEach(link => link.addEventListener('click', closeNav));
    }
    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeNav();
    });
    window.addEventListener('resize', () => {
        if (window.innerWidth > 980) closeNav();
    });

    const lightbox = document.getElementById('theme-lightbox');
    if (lightbox) {
        const image = lightbox.querySelector('img');
        const caption = lightbox.querySelector('figcaption');
        const closeButton = lightbox.querySelector('.theme-lightbox-close');
        const close = () => {
            lightbox.hidden = true;
            document.body.style.overflow = '';
            if (image) image.removeAttribute('src');
        };
        document.querySelectorAll('[data-lightbox]').forEach(button => {
            button.addEventListener('click', () => {
                if (!image) return;
                image.src = button.getAttribute('data-lightbox') || '';
                image.alt = button.getAttribute('data-caption') || '';
                if (caption) caption.textContent = button.getAttribute('data-caption') || '';
                lightbox.hidden = false;
                document.body.style.overflow = 'hidden';
            });
        });
        if (closeButton) closeButton.addEventListener('click', close);
        lightbox.addEventListener('click', event => {
            if (event.target === lightbox) close();
        });
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !lightbox.hidden) close();
        });
    }
})();
