/* Administrator-only presentation controls. No requests or academic calculations. */
(() => {
    'use strict';
    const trigger = document.getElementById('comparison-help-trigger');
    const help = document.getElementById('comparison-help');
    if (trigger && help && !trigger.dataset.bound) {
        trigger.dataset.bound = 'true';
        document.body.append(help); // Escape card/scroll clipping without changing card geometry.
        const close = () => {
            if (help.hidden) return;
            help.hidden = true;
            trigger.setAttribute('aria-expanded', 'false');
            trigger.focus({ preventScroll: true });
        };
        const position = () => {
            const anchor = trigger.getBoundingClientRect();
            const box = help.getBoundingClientRect();
            help.style.left = `${Math.max(12, Math.min(anchor.right - box.width, innerWidth - box.width - 12))}px`;
            help.style.top = `${Math.max(12, Math.min(anchor.bottom + 8, innerHeight - box.height - 12))}px`;
        };
        trigger.addEventListener('click', () => {
            if (!help.hidden) return close();
            help.hidden = false;
            trigger.setAttribute('aria-expanded', 'true');
            position();
            help.querySelector('button').focus({ preventScroll: true });
        });
        help.querySelector('[data-close-help]').addEventListener('click', close);
        document.addEventListener('keydown', event => {
            if (event.key === 'Escape' && !help.hidden) { event.preventDefault(); close(); }
        });
        document.addEventListener('click', event => {
            if (!help.hidden && !help.contains(event.target) && !trigger.contains(event.target)) close();
        });
        window.addEventListener('resize', () => { if (!help.hidden) position(); });
        window.addEventListener('scroll', event => {
            if (!help.hidden && !help.contains(event.target)) position();
        }, true);
    }

    const grid = document.querySelector('[data-overview-charts]');
    if (!grid || grid.dataset.bound) return;
    grid.dataset.bound = 'true';
    const buttons = Array.from(grid.querySelectorAll('[data-focused-chart]'));
    const panels = Array.from(grid.querySelectorAll('[data-chart-panel]'));
    const homes = new Map();
    for (const button of buttons) {
        const region = document.getElementById(button.getAttribute('aria-controls'));
        if (region) homes.set(region, region.parentElement);
    }
    let opener = null;
    const announce = text => { document.getElementById('overview-mode-status').textContent = text; };
    const resize = () => requestAnimationFrame(() => {
        grid.querySelectorAll('canvas').forEach(canvas => {
            if (canvas.getClientRects().length && typeof Chart !== 'undefined') Chart.getChart(canvas)?.resize();
        });
        for (const region of homes.keys()) {
            if (region.hidden) continue;
            const caption = region.querySelector('caption');
            if (caption) region.style.setProperty('--exact-caption-height', `${caption.getBoundingClientRect().height}px`);
        }
    });
    const restore = () => {
        for (const [region, home] of homes) { region.hidden = true; home.append(region); }
        panels.forEach(panel => { panel.hidden = false; });
        buttons.forEach(button => button.setAttribute('aria-expanded', 'false'));
        delete grid.dataset.focused;
    };
    const close = () => {
        const target = opener;
        restore(); opener = null;
        announce('Showing both charts.');
        resize();
        target?.focus();
    };
    buttons.forEach(button => button.addEventListener('click', () => {
        const region = document.getElementById(button.getAttribute('aria-controls'));
        if (!region) return;
        restore(); opener = button;
        grid.dataset.focused = button.dataset.focusedChart;
        panels.forEach(panel => { panel.hidden = panel.dataset.chartPanel !== grid.dataset.focused; });
        region.hidden = false;
        grid.append(region);
        button.setAttribute('aria-expanded', 'true');
        announce(region.querySelector('h3').textContent);
        region.querySelector('h3').focus();
        resize();
    }));
    grid.querySelectorAll('[data-close-focused]').forEach(button => button.addEventListener('click', close));
    grid.addEventListener('keydown', event => {
        if (event.key === 'Escape' && opener) { event.preventDefault(); close(); }
    });
    window.addEventListener('resize', resize);
    if (typeof ResizeObserver !== 'undefined') {
        const observer = new ResizeObserver(resize);
        grid.querySelectorAll('caption').forEach(caption => observer.observe(caption));
        window.addEventListener('pagehide', () => observer.disconnect(), { once: true });
    }
})();
