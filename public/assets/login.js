(() => {
    if (!document.querySelector('.login-page') || typeof window.gsap === 'undefined') {
        return;
    }

    const gsap = window.gsap;
    const motion = gsap.matchMedia();

    motion.add('(prefers-reduced-motion: no-preference)', () => {
        const entrance = gsap.timeline({ defaults: { ease: 'power3.out' } });

        entrance
            .from('.login-layout', { scale: 0.98, opacity: 0.6, duration: 0.35, clearProps: 'opacity,transform' })
            .from('.login-brand', { y: 7, opacity: 0.65, duration: 0.28, clearProps: 'opacity,transform' }, 0.08)
            .from(['.login-kicker', '.login-art', '.login-intro h1', '.login-intro-content > p', '.login-environment'], {
                opacity: 0.65, y: 8, duration: 0.3, stagger: 0.025, clearProps: 'opacity,transform'
            }, 0.13)
            .from('.login-panel-inner', { scale: 0.98, opacity: 0.65, duration: 0.32, clearProps: 'opacity,transform' }, 0.18);

        gsap.to('.login-art', { y: -6, rotation: -4, duration: 3.2, ease: 'sine.inOut', repeat: -1, yoyo: true, delay: 1.5 });
        gsap.to('.login-glow', { y: -12, duration: 6, ease: 'sine.inOut', repeat: -1, yoyo: true });

        const button = document.querySelector('.login-panel button[type="submit"]');
        const inputs = [...document.querySelectorAll('.login-panel input:not([type="hidden"])')];
        const listeners = [];
        const listen = (element, type, handler) => {
            element.addEventListener(type, handler);
            listeners.push([element, type, handler]);
        };

        if (button) {
            listen(button, 'pointerenter', () => gsap.to(button, { scale: 1.01, duration: 0.18, ease: 'power2.out', overwrite: 'auto' }));
            listen(button, 'pointerleave', () => gsap.to(button, { scale: 1, duration: 0.28, ease: 'elastic.out(1.2, 0.55)', overwrite: 'auto', clearProps: 'transform' }));
            listen(button, 'pointerdown', () => gsap.to(button, { scale: 0.96, duration: 0.08, ease: 'power2.out', overwrite: 'auto' }));
            listen(button, 'pointerup', () => gsap.to(button, { scale: 1, duration: 0.28, ease: 'elastic.out(1.2, 0.55)', overwrite: 'auto', clearProps: 'transform' }));
        }

        inputs.forEach((input) => {
            listen(input, 'focus', () => gsap.to(input, { boxShadow: '0 0 0 4px rgba(36, 122, 210, 0.14)', duration: 0.25, ease: 'power2.out', overwrite: 'auto' }));
            listen(input, 'blur', () => gsap.to(input, { boxShadow: 'none', duration: 0.2, ease: 'power2.out', overwrite: 'auto' }));
        });

        return () => {
            listeners.forEach(([element, type, handler]) => element.removeEventListener(type, handler));
            gsap.killTweensOf([button, ...inputs].filter(Boolean));
        };
    });
})();
