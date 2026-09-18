(() => {
    if (typeof window.gsap === 'undefined' || window.matchMedia('(prefers-reduced-motion: reduce)').matches) return;

    const shell = document.querySelector('.workspace-shell');
    if (!shell) return;

    const gsap = window.gsap;
    const page = document.body;
    const entrance = gsap.timeline({ defaults: { ease: 'power3.out' } });

    // Match Central's quick, staggered reveal without delaying navigation.
    const reveal = (selector, animation, position) => {
        const targets = gsap.utils.toArray(selector);
        if (!targets.length) return;
        entrance.from(targets, {
            ...animation,
            clearProps: 'opacity,transform'
        }, position);
    };

    reveal('.workspace-topbar', { y: -8, opacity: 0.7, duration: 0.3 }, 0);
    reveal('.workspace-sidebar .workspace-brand, .workspace-nav a', {
        y: 6, scale: 0.98, opacity: 0.65, duration: 0.28, stagger: 0.02
    }, 0.02);

    if (page.classList.contains('dashboard-page')) {
        reveal('.dashboard-hero', { scale: 0.98, opacity: 0.55, duration: 0.35 }, 0.08);
        reveal('.dashboard-metric', {
            y: 10, scale: 0.96, opacity: 0.5, duration: 0.28, stagger: 0.025, ease: 'power2.out'
        }, 0.16);
        reveal('.dashboard-summary', { y: 10, scale: 0.99, opacity: 0.6, duration: 0.32 }, 0.22);
        const rows = [...document.querySelectorAll('.dashboard-table tbody tr')].slice(0, 8);
        reveal(rows, { y: 6, opacity: 0.7, duration: 0.24, stagger: 0.018 }, 0.32);
    } else if (page.classList.contains('request-form-page')) {
        reveal('.form-workspace-heading', { y: 8, opacity: 0.6, duration: 0.3 }, 0.07);
        reveal('.request-wizard', { scale: 0.98, opacity: 0.65, duration: 0.35 }, 0.12);
        reveal('.wizard-step', {
            y: 7, scale: 0.96, opacity: 0.6, duration: 0.26, stagger: 0.025, ease: 'power2.out'
        }, 0.19);
        reveal('.wizard-panel.is-active > *', {
            y: 8, opacity: 0.7, duration: 0.28, stagger: 0.025
        }, 0.25);
    } else if (page.classList.contains('detail-page')) {
        reveal('.detail-overview', { scale: 0.98, opacity: 0.6, duration: 0.35 }, 0.08);
        reveal('.detail-panel', {
            y: 10, scale: 0.98, opacity: 0.6, duration: 0.32, stagger: 0.035
        }, 0.18);
    }

    const form = document.querySelector('#request-form');
    if (form) {
        let stepFrame = 0;
        let itemFrame = 0;
        form.addEventListener('securepass:stepchange', (event) => {
            cancelAnimationFrame(stepFrame);
            stepFrame = requestAnimationFrame(() => {
                const panel = event.detail.panel;
                if (!panel || panel.hidden) return;
                gsap.killTweensOf(panel);
                gsap.fromTo(panel, { y: 8, scale: 0.98, opacity: 0.7 }, {
                    y: 0, scale: 1, opacity: 1, duration: 0.3, ease: 'power3.out',
                    overwrite: 'auto', clearProps: 'opacity,transform'
                });
                const activeStep = form.querySelector('.wizard-step.is-active');
                if (activeStep) gsap.fromTo(activeStep, { scale: 0.95 }, {
                    scale: 1, duration: 0.28, ease: 'back.out(1.5)', clearProps: 'transform'
                });
            });
        });
        form.addEventListener('securepass:itemchange', (event) => {
            cancelAnimationFrame(itemFrame);
            itemFrame = requestAnimationFrame(() => {
                const item = event.detail.item;
                if (!item || item.hidden || item.closest('.wizard-panel')?.hidden) return;
                gsap.killTweensOf(item);
                gsap.fromTo(item, { y: 7, scale: 0.96, opacity: 0.75 }, {
                    y: 0, scale: 1, opacity: 1, duration: 0.26, ease: 'power2.out',
                    overwrite: 'auto', clearProps: 'opacity,transform'
                });
            });
        });
    }

    const pressable = 'button:not(:disabled), .workspace-nav a, .dashboard-primary-button, .dashboard-metric, .outline-button, .dashboard-export, .back-link, .detail-back, .detail-attachments a';
    let pressed = null;
    shell.addEventListener('pointerdown', (event) => {
        if (event.button !== 0 || !(event.target instanceof Element)) return;
        const target = event.target.closest(pressable);
        if (!target) return;
        pressed = target;
        gsap.to(target, { scale: 0.96, duration: 0.08, ease: 'power2.out', overwrite: 'auto' });
    });
    const release = () => {
        if (!pressed) return;
        const target = pressed;
        pressed = null;
        gsap.to(target, {
            scale: 1, duration: 0.28, ease: 'elastic.out(1.2, 0.55)',
            overwrite: 'auto', clearProps: 'transform'
        });
    };
    window.addEventListener('pointerup', release);
    window.addEventListener('pointercancel', release);
    window.addEventListener('blur', release);

})();
