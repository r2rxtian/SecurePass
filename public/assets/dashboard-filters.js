(() => {
    const form = document.querySelector('#dashboard-filters');
    const summary = document.querySelector('#request-summary');
    if (!form || !summary || !window.fetch || !window.DOMParser || !window.AbortController) return;

    const menu = form.querySelector('#dashboard-filter-menu');
    const instruction = menu.querySelector('.dashboard-filter-panel-heading span');
    const feedback = summary.querySelector('.dashboard-filter-feedback');
    const search = form.elements.namedItem('search');
    const startDate = form.elements.namedItem('start_date');
    const endDate = form.elements.namedItem('end_date');
    const status = form.elements.namedItem('status');
    const stage = form.elements.namedItem('stage');
    let controller = null;
    let revision = 0;
    let feedbackTimer = 0;

    const formUrl = () => {
        const url = new URL(form.action, window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        url.hash = 'request-summary';
        return url;
    };

    const validDates = () => {
        endDate.setCustomValidity('');
        if (startDate.value && endDate.value && endDate.value < startDate.value) {
            menu.open = true;
            endDate.setCustomValidity('End date must be on or after start date.');
            endDate.reportValidity();
            return false;
        }
        return true;
    };

    const update = async (url, { validate = true, commitForm = true } = {}) => {
        if (validate && !validDates()) return;
        controller?.abort();
        controller = new AbortController();
        const request = ++revision;
        clearTimeout(feedbackTimer);
        feedback.textContent = 'Updating…';
        summary.querySelector('.dashboard-table-scroll')?.setAttribute('aria-busy', 'true');

        try {
            const response = await fetch(url.pathname + url.search, {
                signal: controller.signal,
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'Accept': 'text/html' }
            });
            if (response.redirected && new URL(response.url).pathname.endsWith('/login.php')) {
                window.location.assign(response.url);
                return;
            }
            if (!response.ok) throw new Error('Request failed');
            const result = new DOMParser().parseFromString(await response.text(), 'text/html');
            const nextTable = result.querySelector('.dashboard-table-scroll');
            const nextFooter = result.querySelector('.dashboard-table-footer');
            const nextExport = result.querySelector('.dashboard-export');
            const nextApplied = result.querySelector('.dashboard-applied-filters');
            const nextCount = result.querySelector('.dashboard-filter-count');
            if (!nextTable || !nextFooter || !nextExport || !nextApplied || !nextCount) {
                throw new Error('Incomplete response');
            }
            if (request !== revision) return;

            summary.querySelector('.dashboard-table-scroll').replaceWith(nextTable);
            summary.querySelector('.dashboard-table-footer').replaceWith(nextFooter);
            summary.querySelector('.dashboard-applied-filters').replaceWith(nextApplied);
            summary.querySelector('.dashboard-export').setAttribute('href', nextExport.getAttribute('href'));
            const count = menu.querySelector('.dashboard-filter-count');
            count.textContent = nextCount.textContent;
            count.hidden = nextCount.hidden;

            summary.querySelector('.dashboard-filter-error')?.remove();
            const nextError = result.querySelector('.dashboard-filter-error');
            if (nextError) form.insertAdjacentElement('afterend', nextError);

            if (commitForm) {
                menu.open = false;
                menu.classList.remove('is-dirty');
                instruction.textContent = 'Choose filters, then select Apply filters.';
            }
            window.history.replaceState(null, '', url.pathname + url.search + url.hash);
            feedback.textContent = nextFooter.firstElementChild?.textContent.trim() || 'Results updated';
            feedbackTimer = window.setTimeout(() => { feedback.textContent = ''; }, 3000);
        } catch (error) {
            if (error.name !== 'AbortError' && request === revision) {
                feedback.textContent = 'Opening results…';
                window.location.assign(url.href);
            }
        } finally {
            if (request === revision) {
                summary.querySelector('.dashboard-table-scroll')?.removeAttribute('aria-busy');
            }
        }
    };

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        update(formUrl());
    });

    for (const control of [startDate, endDate, status, stage]) {
        control.addEventListener('change', () => {
            menu.classList.add('is-dirty');
            instruction.textContent = 'Changes not applied. Select Apply filters.';
        });
    }

    summary.addEventListener('click', (event) => {
        if (!(event.target instanceof Element) || event.button !== 0
            || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

        const clear = event.target.closest('.dashboard-clear-filters');
        if (clear) {
            event.preventDefault();
            search.value = '';
            startDate.value = '';
            endDate.value = '';
            endDate.setCustomValidity('');
            status.value = 'all';
            stage.value = 'all';
            update(formUrl());
            return;
        }

        const page = event.target.closest('.dashboard-footer-controls nav a:not(.is-disabled)');
        if (page) {
            event.preventDefault();
            update(new URL(page.href), { validate: false, commitForm: false });
        }
    });

    document.addEventListener('pointerdown', (event) => {
        if (menu.open && !menu.contains(event.target)) menu.open = false;
    });
    menu.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            menu.open = false;
            menu.querySelector('summary').focus();
        }
    });
})();
