(() => {
    'use strict';

    const typedInstances = new Set();
    let aosReady = false;

    const splitLines = value => String(value || '')
        .split(/\r?\n/)
        .map(item => item.trim())
        .filter(Boolean);

    const safeHttpUrl = value => /^https?:\/\//i.test(String(value || '').trim()) ? String(value).trim() : '';

    const initTyping = (scope = document) => {
        scope.querySelectorAll('.pb-typing').forEach(root => {
            const target = root.querySelector('.pb-typing-target');
            if (!target) return;
            const strings = splitLines(root.getAttribute('data-typed-strings'));
            if (strings.length === 0) return;
            const speed = Math.max(10, Math.min(500, parseInt(root.getAttribute('data-typed-speed') || '55', 10) || 55));
            const loop = root.getAttribute('data-typed-loop') !== '0';
            const signature = JSON.stringify([strings, speed, loop]);
            if (root.dataset.pbTypedSignature === signature && root.__pbTyped) return;
            if (root.__pbTyped) {
                root.__pbTyped.destroy();
                typedInstances.delete(root.__pbTyped);
                root.__pbTyped = null;
            }
            if (typeof window.Typed === 'function') {
                target.textContent = '';
                root.__pbTyped = new window.Typed(target, {
                    strings,
                    typeSpeed: speed,
                    backSpeed: Math.max(10, Math.round(speed / 2)),
                    backDelay: 1400,
                    startDelay: 250,
                    loop,
                    showCursor: true,
                    cursorChar: '|',
                    smartBackspace: true
                });
                typedInstances.add(root.__pbTyped);
                root.classList.add('typed-active');
            } else {
                target.textContent = strings[0];
            }
            root.dataset.pbTypedSignature = signature;
        });
    };

    const cleanupTyping = () => {
        typedInstances.forEach(instance => {
            try { instance.destroy(); } catch (error) { /* ignore */ }
        });
        typedInstances.clear();
        document.querySelectorAll('.pb-typing').forEach(root => {
            root.__pbTyped = null;
            delete root.dataset.pbTypedSignature;
        });
    };

    const animateCounterFallback = element => {
        const target = Math.max(0, parseInt(element.textContent || '0', 10) || 0);
        if (target === 0) return;
        const duration = Math.max(300, parseInt(element.closest('.pb-counter-up')?.getAttribute('data-counter-duration') || '1200', 10) || 1200);
        const started = performance.now();
        const tick = now => {
            const progress = Math.min(1, (now - started) / duration);
            const eased = 1 - Math.pow(1 - progress, 3);
            element.textContent = String(Math.round(target * eased));
            if (progress < 1) requestAnimationFrame(tick);
            else element.textContent = String(target);
        };
        element.textContent = '0';
        requestAnimationFrame(tick);
    };

    const initCounters = (scope = document) => {
        const counters = scope.querySelectorAll('.pb-counter-value');
        if (counters.length === 0) return;
        if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.counterUp === 'function') {
            window.jQuery(counters).each(function () {
                if (this.dataset.pbCounterReady === '1') return;
                this.dataset.pbCounterReady = '1';
                const duration = Math.max(300, parseInt(this.closest('.pb-counter-up')?.getAttribute('data-counter-duration') || '1200', 10) || 1200);
                window.jQuery(this).counterUp({ delay: 10, time: duration });
            });
            return;
        }
        counters.forEach(counter => {
            if (counter.dataset.pbCounterReady === '1') return;
            counter.dataset.pbCounterReady = '1';
            if (typeof IntersectionObserver === 'function') {
                const observer = new IntersectionObserver(entries => {
                    entries.forEach(entry => {
                        if (!entry.isIntersecting) return;
                        observer.disconnect();
                        animateCounterFallback(counter);
                    });
                }, { threshold: 0.25 });
                observer.observe(counter);
            } else {
                animateCounterFallback(counter);
            }
        });
    };

    const fillTikTokCard = (root, data) => {
        const cover = root.querySelector('.pb-tiktok-cover');
        const thumbnail = safeHttpUrl(data.thumbnail_url);
        if (cover && thumbnail) {
            const image = document.createElement('img');
            image.src = thumbnail;
            image.alt = data.title || 'Video TikTok';
            image.loading = 'lazy';
            cover.replaceChildren(image);
        }
        const author = root.querySelector('.pb-tiktok-author');
        if (author) author.textContent = data.author_name ? `@${data.author_name}` : 'TikTok';
        const caption = root.querySelector('.pb-tiktok-caption');
        if (caption && data.title) caption.textContent = data.title;
        const link = root.querySelector('.pb-tiktok-link');
        if (link && safeHttpUrl(data.url)) link.setAttribute('href', data.url);
    };

    const initTikTok = (scope = document) => {
        scope.querySelectorAll('.pb-tiktok-feed').forEach(root => {
            const url = String(root.getAttribute('data-tiktok-url') || '').trim();
            if (!url) return;
            if (root.dataset.pbTikTokLoaded === '1' && root.dataset.pbTikTokUrl === url) return;
            if (root.dataset.pbTikTokLoading === url) return;
            root.dataset.pbTikTokUrl = url;
            root.dataset.pbTikTokLoading = url;
            root.setAttribute('data-loading', '1');
            fetch(`api.php?action=tiktok_oembed&url=${encodeURIComponent(url)}`, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            })
                .then(response => response.json())
                .then(result => {
                    if (!result.success || !result.data) throw new Error(result.message || 'Data TikTok tidak tersedia.');
                    if (!root.isConnected || root.getAttribute('data-tiktok-url') !== url) return;
                    fillTikTokCard(root, result.data);
                    root.dataset.pbTikTokLoaded = '1';
                })
                .catch(() => {
                    const caption = root.querySelector('.pb-tiktok-caption');
                    if (caption && !caption.textContent.trim()) caption.textContent = 'Video TikTok belum dapat dimuat. Klik untuk membuka di TikTok.';
                })
                .finally(() => {
                    delete root.dataset.pbTikTokLoading;
                    root.removeAttribute('data-loading');
                });
        });
    };

    const initAos = reset => {
        if (!window.AOS) {
            document.querySelectorAll('[data-aos]').forEach(element => element.classList.add('aos-animate'));
            return;
        }
        const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (!aosReady) {
            try {
                window.AOS.init({
                    duration: 700,
                    easing: 'ease-out-cubic',
                    offset: 60,
                    once: true,
                    disable: reducedMotion
                });
                aosReady = true;
                return;
            } catch (error) {
                document.querySelectorAll('[data-aos]').forEach(element => element.classList.add('aos-animate'));
                return;
            }
        }
        if (reset) window.AOS.refreshHard();
        else window.AOS.refresh();
    };

    const enhance = (options = {}) => {
        if (options.reset) cleanupTyping();
        initTyping(document);
        initTikTok(document);
        initCounters(document);
        initAos(Boolean(options.reset));
    };

    window.pbThemeEnhance = enhance;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => enhance(), { once: true });
    } else {
        enhance();
    }
})();
