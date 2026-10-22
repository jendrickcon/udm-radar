import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

const evidenceDir = resolve('test-results/evidence/mobile-hardening');
try { mkdirSync(evidenceDir, { recursive: true }); } catch (e) {}

// Controlled PHP view output, mocked charts, no authenticated routes or handlers.
const matrix = [[360,800], [390,844], [412,915], [600,960], [768,1024], [844,390], [900,800], [901,800], [1024,768], [1440,900]];
const routes = {
    'MOB-003': 'student/index.php', 'MOB-004': 'faculty/index.php',
    'MOB-005': 'admin/index.php', 'MOB-006': 'student/dashboard.php',
    'MOB-008': 'faculty/analytics.php', 'MOB-009': 'student/trend.php', 'MOB-010': 'admin/settings.php',
    'MOB-018': 'student/grades.php',
};
const fixtureCache = new Map();
function fixture(route) {
    if (!fixtureCache.has(route)) fixtureCache.set(route, execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [resolve('tests/fixtures/mobile_portal_render.php'), route], { encoding: 'utf8' }));
    return fixtureCache.get(route);
}
const chartMock = `window.Chart = class {
    constructor(context, config) {
        this.data = config.data; this.options = config.options;
        context.canvas.style.width = '100%'; context.canvas.style.height = '100%';
        context.canvas.dataset.chartMock = 'true';
    }
    update() {} destroy() {}
};`;
async function mount(page, route, theme, viewport, reducedMotion = 'reduce') {
    await page.setViewportSize({ width: viewport[0], height: viewport[1] });
    await page.emulateMedia({ reducedMotion });
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.addInitScript(theme => localStorage.setItem('theme', theme), theme);
    const html = fixture(route).replace(/<html([^>]*)>/, `<html$1 data-theme="${theme}">`);
    await page.route('**/*', request => {
        const url = new URL(request.request().url());
        if (request.request().method() !== 'GET') {
            errors.push('Unexpected mutation request: ' + url.pathname);
            return request.abort();
        }
        if (url.hostname === 'mobile.test' && url.pathname === '/' + route) return request.fulfill({ contentType: 'text/html', body: html });
        if (url.hostname === 'mobile.test' && ['/assets/js/goal-calculator.js','/assets/js/goal-calculator-ui.js','/assets/css/goal-calculator.css'].includes(url.pathname)) return request.fulfill({contentType:url.pathname.endsWith('.css')?'text/css':'application/javascript',body:readFileSync(resolve('.'+url.pathname))});
        if (url.pathname.endsWith('/logo_sidebar.png')) return request.fulfill({ contentType: 'image/png', body: readFileSync(resolve('assets/img/logo_sidebar.png')) });
        if (url.hostname === 'cdn.jsdelivr.net' && url.pathname === '/npm/chart.js') return request.fulfill({ contentType: 'application/javascript', body: chartMock });
        if (url.hostname === 'fonts.googleapis.com') return request.fulfill({ contentType: 'text/css', body: '' });
        errors.push('Unexpected request: ' + request.request().url());
        return request.abort();
    });
    await page.goto('https://mobile.test/' + route);
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    return errors;
}
async function geometry(page, info, name) {
    const result = await page.evaluate(() => {
        const main = document.querySelector('.main-content');
        const csMain = main ? window.getComputedStyle(main) : null;
        const bMain = main ? main.getBoundingClientRect() : null;
        const pL = csMain ? parseFloat(csMain.paddingLeft) : 0;
        const pR = csMain ? parseFloat(csMain.paddingRight) : 0;

        const cards = [...document.querySelectorAll('.main-content > .card, .main-content > .stat-grid > .stat-card, .main-content > .home-panels .card')]
            .filter(el => el.offsetWidth > 0 && el.offsetHeight > 0)
            .map(el => {
                const r = el.getBoundingClientRect();
                const leftGap = bMain ? r.left - bMain.left : 0;
                const rightGap = bMain ? bMain.right - r.right : 0;
                return { x: r.x, width: r.width, right: r.right, leftGap, rightGap, diff: Math.abs(leftGap - rightGap) };
            });

        return {
            viewport: innerWidth,
            root: document.documentElement.scrollWidth,
            main: main && { client: main.clientWidth, scroll: main.scrollWidth, pL, pR },
            cards,
        };
    });
    await info.attach(name, { body: JSON.stringify(result), contentType: 'application/json' });
    if ([390,1440].includes(result.viewport)) await info.attach(name + '-view', { body: await page.screenshot({ fullPage: true, animations: 'disabled' }), contentType: 'image/png' });
    expect(result.root).toBeLessThanOrEqual(result.viewport + 1);
    if (result.main) {
        expect(result.main.scroll).toBeLessThanOrEqual(result.main.client + 1);
        if (result.viewport <= 600) {
            // Symmetry check: padding-left and padding-right must be symmetric
            expect(Math.abs(result.main.pL - result.main.pR)).toBeLessThanOrEqual(2);
            expect(result.main.pR).not.toBe(76); // Explicitly reject stale 76px padding
            for (const c of result.cards) {
                // Each full-width card must be horizontally centered within tolerance
                expect(c.diff).toBeLessThanOrEqual(4);
            }
        }
    }
    return result;
}
async function within(locator, container) {
    const boxes = await locator.evaluateAll(els => els.filter(el => el.getClientRects().length).map(el => {
        const r = el.getBoundingClientRect(); return { left: r.left, right: r.right };
    }));
    const bounds = await container.boundingBox();
    for (const box of boxes) {
        expect(box.left).toBeGreaterThanOrEqual(bounds.x - 1);
        expect(box.right).toBeLessThanOrEqual(bounds.x + bounds.width + 1);
    }
}

for (const theme of ['light', 'dark']) for (const viewport of matrix) {
    const label = `${viewport.join('x')} ${theme}`;
    test(`MOB-001 / MOB-015 Login reachable, static reduced-motion decoration: ${label}`, async ({ page }, info) => {
        const errors = await mount(page, 'login.php', theme, viewport);
        await geometry(page, info, 'login');
        const user = page.locator('#userid');
        const submit = page.getByRole('button', { name: 'Login to Portal' });
        const box = await user.boundingBox();
        expect(box.y).toBeGreaterThanOrEqual(0);
        expect(box.y).toBeLessThan(viewport[1]);
        await user.focus();
        await page.keyboard.press('Tab');
        await expect(page.locator('#password')).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(submit).toBeFocused();
        await expect(submit).toBeInViewport();
        await expect(page.locator('.brand-logo')).toBeVisible();
        expect(await page.locator('.brand-logo').evaluate(el => el.naturalWidth)).toBeGreaterThan(0);
        expect(await page.locator('.chart-bar').first().evaluate(el => getComputedStyle(el).animationName)).toBe('none');
        expect(await page.locator('.chart-bar').first().evaluate(el => getComputedStyle(el).opacity)).toBe('1');
        expect(await page.locator('.chart-line').evaluate(el => getComputedStyle(el).strokeDashoffset)).toBe('0px');
        expect(errors).toEqual([]);
    });
    for (const [id, route] of Object.entries(routes)) test(`${id} scoped page containment: ${label}`, async ({ page }, info) => {
        const errors = await mount(page, route, theme, viewport);
        await geometry(page, info, id);
        const main = page.locator('.main-content');
        await within(main.locator('.card, .stat-card'), main);
        if (id === 'MOB-003' || id === 'MOB-004') {
            await within(page.locator('.home-details > span'), page.locator('.home-profile'));
            await within(page.locator('.home-panels a'), main);
            if (viewport[0] <= 600) expect(await page.locator('.stat-grid').evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length)).toBe(1);
        }
        if (id === 'MOB-005') {
            await within(page.locator('.roster-controls > *'), page.locator('#table-card'));
            await page.locator('#filter-risk').selectOption('HIGH');
            await page.locator('#dashboard-search').fill('synthetic');
            await expect(page.getByRole('button', { name: 'Export Roster CSV' })).toBeEnabled();
            // Do not click exports: real handlers log successful exports.
        }
        if (id === 'MOB-006') {
            const panels = page.locator('.analysis-panels > .card');
            if (viewport[0] <= 1100) {
                const a = await panels.nth(0).boundingBox(), b = await panels.nth(1).boundingBox();
                expect(b.y).toBeGreaterThanOrEqual(a.y + a.height);
            }
            await expect(page.getByRole('region', { name: 'Current subjects and predictions' })).toHaveAttribute('tabindex', '0');
            await page.locator('.risk-info-icon').focus();
            await within(page.locator('.risk-info-icon .tooltip-text'), page.locator('.risk-info-icon').locator('xpath=ancestor::div[contains(@class,"stat-card")][1]'));
            if (viewport[0] <= 600) {
                expect(await page.locator('.student-subjects-table thead').evaluate(el => getComputedStyle(el).display)).toBe('none');
                const firstCode = await page.locator('.student-subjects-table .card-cell-code').first().innerText();
                expect(firstCode).toBe('SYN101');
            } else {
                expect(await page.locator('.student-subjects-table thead').evaluate(el => getComputedStyle(el).display)).toBe('table-header-group');
            }
        }
        if (id === 'MOB-018') {
            if (viewport[0] <= 600) {
                expect(await page.locator('.current-grades-table thead').evaluate(el => getComputedStyle(el).display)).toBe('none');
                expect(await page.locator('.history-grades-table thead').evaluate(el => getComputedStyle(el).display)).toBe('none');
                const firstCode = await page.locator('.current-grades-table .card-cell-code').first().innerText();
                expect(firstCode).toBe('ITE421');
                const firstTitle = await page.locator('.current-grades-table .card-cell-title').first().innerText();
                expect(firstTitle).toContain('Social and Professional');
                const histRows = page.locator('.history-grades-table tbody tr');
                await expect(histRows).toHaveCount(4);
                expect(await histRows.nth(2).locator('[data-label="Final Grade"]').innerText()).toContain('INC');
                expect(await histRows.nth(3).locator('[data-label="Final Grade"]').innerText()).toContain('0.00');
            } else {
                expect(await page.locator('.current-grades-table thead').evaluate(el => getComputedStyle(el).display)).toBe('table-header-group');
                expect(await page.locator('.history-grades-table thead').evaluate(el => getComputedStyle(el).display)).toBe('table-header-group');
            }
        }
        if (id === 'MOB-010') {
            await within(page.locator('.settings-grid input'), main);
            // Render the already-existing candidate state without calling any model API.
            await page.locator('#candidate-panel').evaluate(el => { el.style.display = 'block'; });
            await geometry(page, info, 'candidate-panel');
        }
        if (viewport[0] === 1440 && id !== 'MOB-018') {
            const grid = id === 'MOB-005' ? '.dashboard-charts' : id === 'MOB-006' ? '.analysis-panels' : '.stat-grid';
            const columns = await page.locator(grid).first().evaluate(el => getComputedStyle(el).gridTemplateColumns.split(' ').length);
            expect(columns).toBe(['MOB-003','MOB-004'].includes(id) ? 4 : ['MOB-008','MOB-009'].includes(id) ? 3 : 2);
        }
        expect(errors).toEqual([]);
    });
    test(`MOB-007 Calculator table-only scrolling and unchanged sample computation: ${label}`, async ({ page }, info) => {
        const errors = await mount(page, 'student/dashboard.php', theme, viewport);
        await page.getByRole('button', { name: 'Open Grade Goal Calculator' }).click();
        const calculator = page.locator('#calculator-section');
        await within(calculator.getByRole('button', { name: 'Close' }), calculator);
        const region = page.getByRole('region', { name: 'Grade goal calculator' });
        await expect(region).toHaveAttribute('tabindex', '0');
        for (const field of ['prelim','midterm','prefinal']) await calculator.locator('.calc-' + field).fill('90');
        await expect(calculator.locator('.calc-final-input')).toHaveValue('');
        await expect(calculator.locator('.calc-row-result')).toContainText('Scenario final grade: 3.00');
        await expect(page.locator('#projected-gwa')).toHaveText('3.00');
        if (viewport[0] <= 600) {
            expect(await region.evaluate(el => el.scrollWidth > el.clientWidth)).toBe(true);
            await region.evaluate(el => { el.scrollLeft = el.scrollWidth; });
            expect(await region.evaluate(el => el.scrollLeft)).toBeGreaterThan(0);
            await within(calculator.locator('.calc-final-input'), region);
        }
        await geometry(page, info, 'calculator');
        await calculator.getByRole('button', { name: 'Close' }).click();
        await expect(calculator).toBeHidden();
        expect(errors).toEqual([]);
    });
}

for (const theme of ['light','dark']) for (const viewport of [...matrix, [390,700]]) {
    test(`MOB-002 bounded long notices, focus lifecycle and scroll lock: ${viewport.join('x')} ${theme}`, async ({ page }, info) => {
        const errors = await mount(page, 'faculty/feedback.php', theme, viewport);
        for (const [trigger, id] of [['#view-trigger','#viewNoticeModal'], ['#issue-trigger','#interventionModal']]) {
            await page.locator(trigger).click();
            const dialog = page.locator(id);
            await expect(dialog).toHaveAttribute('role', 'dialog');
            await expect(dialog).toHaveAttribute('aria-modal', 'true');
            await expect(dialog.locator('h3')).toBeFocused();
            const card = await dialog.locator('.notice-card').boundingBox();
            expect(card.y).toBeGreaterThanOrEqual(0);
            expect(card.y + card.height).toBeLessThanOrEqual(viewport[1] + 1);
            const close = dialog.getByRole('button', { name: id.includes('view') ? 'Close' : 'Cancel', exact: true });
            await expect(close).toBeInViewport();
            if (id.includes('view') && viewport[1] < 960) expect(await dialog.locator('.notice-body').evaluate(el => el.scrollHeight > el.clientHeight)).toBe(true);
            const controls = dialog.locator('button, select, textarea');
            await controls.last().focus();
            await page.keyboard.press('Tab');
            await expect(controls.first()).toBeFocused();
            await page.keyboard.press('Shift+Tab');
            await expect(controls.last()).toBeFocused();
            const positions = await page.evaluate(() => [scrollY, document.querySelector('.main-content').scrollTop]);
            await page.mouse.move(viewport[0] - 2, viewport[1] / 2);
            await page.mouse.wheel(0, 700);
            expect(await page.evaluate(() => [scrollY, document.querySelector('.main-content').scrollTop])).toEqual(positions);
            expect(await page.locator('.main-content').evaluate(el => el.inert)).toBe(true);
            await info.attach(id.slice(1), { body: JSON.stringify(card), contentType: 'application/json' });
            if ([390,844].includes(viewport[0])) await info.attach(id.slice(1) + '-view', { body: await page.screenshot({ animations: 'disabled' }), contentType: 'image/png' });
            await page.keyboard.press('Escape');
            await expect(dialog).toBeHidden();
            await expect(page.locator(trigger)).toBeFocused();
            expect(await page.locator('.main-content').evaluate(el => el.inert)).toBe(false);
            expect(await page.locator('body').evaluate(el => el.style.overflow)).toBe('');
            await page.locator(trigger).click();
            await close.click();
            await expect(page.locator(trigger)).toBeFocused();
        }
        expect(errors).toEqual([]);
    });
}

for (const theme of ['light','dark']) test(`MOB-011 drawer wraps inside and clears across 900/901: ${theme}`, async ({ page }) => {
    const errors = await mount(page, 'student/index.php', theme, [900,800]);
    const toggle = page.locator('#mobile-nav-toggle'), close = page.locator('#sidebar-drawer-close-btn');
    const last = page.locator('#sidebar .logout-btn');
    await toggle.click(); await last.focus(); await page.keyboard.press('Tab');
    await expect(close).toBeFocused();
    await page.keyboard.press('Shift+Tab'); await expect(last).toBeFocused();
    await page.keyboard.press('Escape'); await expect(toggle).toBeFocused();
    await toggle.click(); await page.setViewportSize({width:901,height:800});
    await expect(page.locator('#sidebar')).not.toHaveClass(/sidebar--open/);
    await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
    await expect(toggle).toBeHidden();
    await page.setViewportSize({width:900,height:800});
    await toggle.click(); await last.focus(); await page.keyboard.press('Tab'); await expect(close).toBeFocused();
    await page.keyboard.press('Escape'); await expect(toggle).toBeFocused();
    expect(errors).toEqual([]);
});

for (const theme of ['light','dark']) test(`MOB-002 open dialog follows a shrinking or rotated viewport: ${theme}`, async ({ page }) => {
    const errors = await mount(page, 'faculty/feedback.php', theme, [390,844], 'no-preference');
    await page.locator('#issue-trigger').click();
    for (const viewport of [[390,400],[844,390],[390,844]]) {
        await page.setViewportSize({width:viewport[0],height:viewport[1]});
        await expect.poll(async () => {
            const box = await page.locator('#interventionModal .notice-card').boundingBox();
            return box.y + box.height;
        }).toBeLessThanOrEqual(viewport[1]);
        await expect(page.locator('#interventionModal').getByRole('button', {name:'Cancel'})).toBeInViewport();
    }
    await page.keyboard.press('Escape');
    await expect(page.locator('#issue-trigger')).toBeFocused();
    expect(errors).toEqual([]);
});

test('MOB-001 reduced-height keyboard approximation remains scrollable', async ({ page }) => {
    const errors = await mount(page, 'login.php', 'light', [390,844]);
    await page.locator('#password').focus();
    await page.setViewportSize({width:390,height:400});
    await page.locator('#password').scrollIntoViewIfNeeded();
    await expect(page.locator('#password')).toBeInViewport();
    await page.keyboard.press('Tab');
    await expect(page.locator('.btn-login')).toBeInViewport();
    expect(await page.evaluate(() => scrollY)).toBeGreaterThan(0);
    await page.setViewportSize({width:390,height:844});
    expect(errors).toEqual([]);
});

test('MOB-001 / MOB-015 ordinary motion preserves decoration and theme controls', async ({ page }) => {
    const errors = await mount(page, 'login.php', 'light', [1440,900], 'no-preference');
    expect(await page.locator('.chart-bar').first().evaluate(el => getComputedStyle(el).animationName)).toBe('riseUp');
    await page.getByRole('button', {name:'Toggle dark mode'}).click();
    await expect(page.locator('html')).toHaveAttribute('data-theme','dark');
    expect(errors).toEqual([]);
});

for (const theme of ['light', 'dark']) for (const viewport of [[360,800], [390,844], [412,915], [844,390], [1440,900]]) {
    const label = `${viewport.join('x')} ${theme}`;
    test(`MOB-019 Academic Guide visual viewport sizing, internal scrolling, and bottom clearance: ${label}`, async ({ page }) => {
        const errors = await mount(page, 'student/dashboard.php', theme, viewport);
        const trigger = page.locator('#udm-floating-help-btn');
        await expect(trigger).toBeVisible();
        await trigger.click();
        const overlay = page.locator('#glossary-drawer-overlay');
        const drawer = page.locator('#glossary-drawer');
        await expect(overlay).toHaveClass(/open/);

        // Measure drawer bounds within visual viewport
        const drawerBox = await drawer.boundingBox();
        expect(drawerBox.y).toBeGreaterThanOrEqual(0);
        expect(drawerBox.y + drawerBox.height).toBeLessThanOrEqual(viewport[1] + 1);

        // Body scroll lock verification
        expect(await page.evaluate(() => document.body.style.overflow)).toBe('hidden');

        // Test shortened visual viewport (approximating dynamic toolbar or keyboard)
        const reducedH = Math.min(viewport[1], 700);
        await page.setViewportSize({ width: viewport[0], height: reducedH });
        await expect.poll(async () => {
            const b = await drawer.boundingBox();
            return b.y + b.height;
        }).toBeLessThanOrEqual(reducedH + 1);

        // Verify body internal scrollability to final content
        const body = drawer.locator('.guide-drawer-body');
        await body.evaluate(el => { el.scrollTop = el.scrollHeight; });
        const lastBox = drawer.locator('.guide-tab-content.active .guide-card-box').last();
        await expect(lastBox).toBeInViewport();

        // Capture evidence screenshots
        if (viewport[0] === 390 && theme === 'dark') {
            await page.screenshot({ path: resolve(evidenceDir, 'MOB-019_guide_390x700_dark_bottom-toolbar.png'), animations: 'disabled' });
        }
        if (viewport[0] === 844 && theme === 'dark') {
            await page.screenshot({ path: resolve(evidenceDir, 'MOB-019_guide_844x390_dark_landscape.png'), animations: 'disabled' });
        }

        // Close guide and restore focus
        await page.keyboard.press('Escape');
        await expect(overlay).not.toHaveClass(/open/);
        await expect(trigger).toBeFocused();
        expect(await page.evaluate(() => document.body.style.overflow)).toBe('');
        expect(errors).toEqual([]);
    });
}

test('MOB-018 and MOB-020 Student Grade Card Playwright Screenshot Capture', async ({ page }) => {
    // 1. Dark 390x844 grades cards
    await mount(page, 'student/grades.php', 'dark', [390, 844]);
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-020_student-grades_390x844_dark_cards.png'), animations: 'disabled' });

    // 2. Dark 390x844 history cards (scroll to history section)
    await page.locator('.history-grades-table').first().scrollIntoViewIfNeeded();
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-020_student-history_390x844_dark_cards.png'), animations: 'disabled' });

    // 3. Dark 844x390 landscape
    await mount(page, 'student/grades.php', 'dark', [844, 390]);
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-020_student-grades_844x390_dark_landscape.png'), animations: 'disabled' });

    // 4. Dark 1440x900 desktop table
    await mount(page, 'student/grades.php', 'dark', [1440, 900]);
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-020_student-grades_1440x900_dark_table.png'), animations: 'disabled' });

    // 5. MOB-018 Academic History initial position
    await mount(page, 'student/grades.php', 'dark', [390, 844]);
    await page.locator('.history-grades-table').first().scrollIntoViewIfNeeded();
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-018_student_academic-history_390x844_dark_initial.png'), animations: 'disabled' });

    // 6. MOB-018 Current Subjects
    await mount(page, 'student/dashboard.php', 'dark', [390, 844]);
    await page.locator('.student-subjects-table').scrollIntoViewIfNeeded();
    await page.screenshot({ path: resolve(evidenceDir, 'MOB-018_student_current-subjects_390x844_dark_scrolled-right.png'), animations: 'disabled' });
});
