import { test, expect } from '@playwright/test';

// Opt-in live synthetic account checks. Login and inspected handlers are SELECT-only.
// No migrations, fixture databases, academic POSTs, screenshots, traces or exports.
const base = process.env.PHASE_C_BASE_URL;
const account = role => ({ id: process.env[`PHASE_C_${role.toUpperCase()}_ID`], password: process.env[`PHASE_C_${role.toUpperCase()}_PASSWORD`] });
const path = role => `${role}/${role === 'admin' ? 'analytics.php' : role === 'faculty' ? 'assigned_analytics.php' : 'academic_analytics.php'}`;
const tabName = role => role === 'admin' ? 'Academic Comparisons' : 'Academic Trends';
const tabId = role => role === 'admin' ? 'comparisons' : 'trends';

async function login(page, role, query = '', beforeAnalytics) {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await page.goto(new URL('login.php', base).href);
    await page.locator('[name="userid"]').fill(account(role).id);
    await page.locator('[name="password"]').fill(account(role).password);
    await page.getByRole('button', { name: 'Login to Portal' }).click();
    await page.waitForURL(`**/${role}/index.php`);
    if (beforeAnalytics) await beforeAnalytics();
    await page.goto(new URL(path(role) + query, base).href);
    await expect(page.locator('#analytics-results')).toBeVisible();
    return errors;
}

async function apply(page) {
    const response = page.waitForResponse(r => r.url().includes('/api/academic_analytics.php'));
    await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
    await response;
    await expect(page.locator('#analytics-results')).toBeVisible();
}

async function preferences(page, theme, colorStyle = 'ccs', textSize = 'default') {
    await page.evaluate(p => window.UDMDisplayPreferences.applyPreferences(p), {
        theme, colorStyle, textSize, motion: 'reduce', underlineLinks: false,
    });
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
}

async function contrast(locator) {
    return locator.evaluate(n => {
        const color = s => s.match(/[\d.]+/g).slice(0, 3).map(Number);
        const lum = rgb => rgb.map(c => { const v = c / 255; return v <= .04045 ? v / 12.92 : ((v + .055) / 1.055) ** 2.4; }).reduce((v, c, i) => v + c * [.2126, .7152, .0722][i], 0);
        const style = getComputedStyle(n), a = lum(color(style.color)), b = lum(color(style.backgroundColor));
        return (Math.max(a, b) + .05) / (Math.min(a, b) + .05);
    });
}

for (const role of ['admin', 'faculty']) {
    test.describe(`${role} Phase C refinements`, () => {
        test.skip(!base || !account(role).id || !account(role).password, 'Requires explicit local URL and documented synthetic credentials; no default target');
        test.afterEach(async ({ page }) => { if (base && !page.isClosed()) await page.goto(new URL('logout.php', base).href); });

        test('comparison cards start with usable authorized groupings', async ({ page }) => {
            await login(page, role);
            await page.getByRole('tab', { name: tabName(role) }).click();
            await expect(page.locator('.card-dimension-select')).toHaveCount(2);
            const offered = await page.locator('.card-dimension-select').first().locator('option').evaluateAll(nodes => nodes.map(n => n.value));
            expect(offered).toEqual(role === 'faculty' ? ['subject', 'section'] : ['semester', 'school_year', 'year_level', 'subject', 'section', 'faculty_subject']);
            if (role === 'faculty') await expect(page.locator('.card-dimension-select').first()).toHaveValue('subject');
        });

        test('card selector changes preserve keyboard focus and add/remove limits', async ({ page }) => {
            await login(page, role);
            await page.getByRole('tab', { name: tabName(role) }).click();
            await page.locator('#other-comparisons-disclosure summary').click();
            await page.locator('#comparison-group').selectOption('subject');
            const dimensions = page.locator('.card-dimension-select').first();
            await dimensions.focus();
            await dimensions.selectOption('section');
            await expect(page.locator('.card-dimension-select').first()).toBeFocused();
            await page.locator('.card-dimension-select').first().selectOption('subject');
            const groups = page.locator('.card-group-select').first();
            await groups.focus(); await groups.selectOption({ index: 1 });
            await expect(page.locator('.card-group-select').first()).toBeFocused();
            await page.locator('#add-comparison-card-btn').click();
            await expect(page.locator('#remove-card-c')).toBeFocused();
            await expect(page.locator('#add-comparison-card-btn')).toBeDisabled();
            await expect(page.locator('.card-dimension-select')).toHaveCount(3);
            await page.locator('#remove-card-c').click();
            await expect(page.locator('#add-comparison-card-btn')).toBeFocused();
            await expect(page.locator('.card-dimension-select:visible')).toHaveCount(2);
            await expect(page.locator('#panel-compare-c')).toBeHidden();
        });

        test('applied scope is announced and survives reload with its tab', async ({ page }) => {
            await login(page, role);
            await page.getByRole('tab', { name: tabName(role) }).click();
            await page.getByLabel('Semester', { exact: true }).selectOption('1');
            await apply(page);
            await expect(page.locator('#analytics-state')).toContainText('Semester: 1');
            await expect(page).toHaveURL(/semester=1/);
            await page.reload(); await expect(page.locator('#analytics-results')).toBeVisible();
            await expect(page.getByLabel('Semester', { exact: true })).toHaveValue('1');
            await expect(page.getByRole('tab', { name: tabName(role) })).toHaveAttribute('aria-selected', 'true');
            await page.getByRole('button', { name: 'Clear filters', exact: true }).click();
            await expect(page.locator('#analytics-state')).not.toContainText('Semester: 1');
            await expect(page).not.toHaveURL(/semester=1/);
        });

        test('tabs support arrows and a keyboard reachable result panel', async ({ page }) => {
            await login(page, role);
            const overview = page.getByRole('tab', { name: 'Overview', exact: true });
            await overview.focus(); await page.keyboard.press('ArrowRight');
            const tab = page.getByRole('tab', { name: tabName(role) });
            await expect(tab).toBeFocused(); await expect(tab).toHaveAttribute('aria-selected', 'true');
            const panel = page.locator(`#tab-panel-${tabId(role)}`);
            await expect(panel).toHaveAttribute('tabindex', '0');
            await page.getByRole('button', { name: 'Clear filters', exact: true }).focus();
            await page.keyboard.press('Tab'); await expect(panel).toBeFocused();
            await tab.focus(); await page.keyboard.press('End');
            await expect(page.getByRole('tab').last()).toHaveAttribute('aria-selected', 'true');
            await page.keyboard.press('Home'); await expect(overview).toBeFocused();
        });

        test('empty review can clear filters and an unavailable API can retry', async ({ page }) => {
            await login(page, role, `?section=NO-MATCH&tab=${tabId(role)}`);
            await expect(page.locator('#comparison-table')).toContainText('No records match these filters.');
            await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('N/A');
            await page.getByRole('button', { name: 'Clear filters', exact: true }).click();
            await expect(page.locator('.card-dimension-select')).toHaveCount(2);
            await page.route('**/api/academic_analytics.php*', route => route.fulfill({ status: 503, json: { error: 'Controlled unavailable response' } }));
            await page.getByRole('button', { name: 'Apply filters', exact: true }).click();
            await expect(page.locator('#analytics-state')).toContainText('could not be loaded');
            await expect(page.locator('#analytics-results')).toBeHidden();
            await page.unroute('**/api/academic_analytics.php*'); await apply(page);
            await expect(page.locator('#analytics-results')).toBeVisible();
        });

        test('a delayed previous filter cannot overwrite the latest results or address', async ({ page }) => {
            await login(page, role);
            let release, observed, finished;
            const gate = new Promise(resolve => { release = resolve; });
            const started = new Promise(resolve => { observed = resolve; });
            const completed = new Promise(resolve => { finished = resolve; });
            await page.route('**/api/academic_analytics.php*', async route => {
                if (new URL(route.request().url()).searchParams.get('semester') !== '1') return route.continue();
                const response = await route.fetch(); observed(); await gate;
                try { await route.fulfill({ response }); } catch { /* Superseded browser request is intentionally aborted. */ }
                finally { finished(); }
            });
            await page.getByLabel('Semester', { exact: true }).selectOption('1');
            await page.getByRole('button', { name: 'Apply filters', exact: true }).click(); await started;
            const latest = role === 'faculty' ? '' : '2'; // Faculty has only its currently assigned term.
            await page.getByLabel('Semester', { exact: true }).selectOption(latest); await apply(page);
            if (latest) await expect(page.locator('#analytics-state')).toContainText('Semester: 2');
            else await expect(page.locator('#analytics-state')).not.toContainText('Semester: 1');
            release(); await completed;
            await expect(page).not.toHaveURL(/semester=1/);
            await expect(page.locator('#active-filters')).not.toContainText('Semester: 1');
            if (latest) await expect(page).toHaveURL(/semester=2/);
        });

        for (const theme of ['light', 'dark']) for (const width of [1440, 1280, 1024]) {
            test(`${theme} ${width}px tabs fit with readable controls and exact trend values`, async ({ page }) => {
                await page.setViewportSize({ width, height: width === 1280 ? 600 : width === 1440 ? 900 : 768 });
                const errors = await login(page, role);
                await preferences(page, theme);
                for (const tab of ['Overview', tabName(role), role === 'admin' ? 'Support & Interventions' : 'Referrals & Interventions']) {
                    await page.getByRole('tab', { name: tab, exact: true }).click();
                    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
                    expect(await page.locator('.main-content').evaluate(n => n.scrollWidth <= n.clientWidth + 1)).toBe(true);
                }
                await page.getByRole('tab', { name: tabName(role) }).click();
                for (const colorStyle of ['ccs', 'classic']) {
                    await preferences(page, theme, colorStyle);
                    expect(await contrast(page.locator('#btn-mode-overlay'))).toBeGreaterThanOrEqual(4.5);
                    expect(await contrast(page.getByRole('button', { name: 'Apply filters', exact: true }))).toBeGreaterThanOrEqual(4.5);
                }
                const report = await (await page.request.get(new URL('api/academic_analytics.php', base).href)).json();
                const rows = page.locator('#trend-table tbody tr');
                await expect(rows).toHaveCount(report.trend.series.length || 1);
                for (let i = 0; i < report.trend.series.length; i++) {
                    const record = report.trend.series[i];
                    await expect(rows.nth(i)).toContainText(record.label);
                    await expect(rows.nth(i).locator('td').nth(1)).toHaveText(record.gwa === null ? 'N/A' : Number(record.gwa).toFixed(2));
                }
                expect(errors).toEqual([]);
            });
        }
    });
}

test.describe('Admin overview chart access', () => {
    test.skip(!base || !account('admin').id || !account('admin').password, 'Requires explicit synthetic admin account');
    test.afterEach(async ({ page }) => { if (base && !page.isClosed()) await page.goto(new URL('logout.php', base).href); });

    test('canvas-free exact chart data remains available when Chart.js fails', async ({ page }) => {
        const errors = await login(page, 'admin', '', () => page.route('https://cdn.jsdelivr.net/npm/chart.js', route => route.abort()));
        await expect(page.locator('#sectionRiskChart')).toHaveAttribute('role', 'img');
        await page.locator('#section-risk-data summary').click();
        const headers = await page.locator('#section-risk-chart-summary thead th').allTextContents();
        expect(headers).toEqual(['Section', 'High Risk (%)', 'Moderate Risk (%)', 'Low Risk (%)', 'No Prediction (%)']);
        const rows = await page.locator('#section-risk-chart-summary tbody tr').evaluateAll(nodes => nodes.map(row => [...row.cells].map(c => c.textContent.trim())));
        expect(rows.length).toBeGreaterThan(0);
        for (const row of rows) expect(row.slice(1).reduce((sum, n) => sum + Number.parseFloat(n), 0)).toBeCloseTo(100, 0);
        await expect(page.locator('[data-chart-fallback="sectionRiskChart"]')).toBeVisible();
        await page.locator('#honor-chart-data summary').click();
        await expect(page.locator('#honor-chart-summary')).toBeVisible();
        expect(errors).toEqual([]);
    });

    test('chart preference synchronization uses current tokens and reduced motion (Chart.js stand-in)', async ({ page }) => {
        await page.route('https://cdn.jsdelivr.net/npm/chart.js', route => route.fulfill({ contentType: 'text/javascript', body: `
            window.Chart = class { static instances = {}; constructor(context, config) { this.canvas=context.canvas; this.data=config.data; this.options=config.options; Chart.instances[this.canvas.id]=this; } update(mode) { this.lastUpdate=mode; } };
        ` }));
        await login(page, 'admin');
        await preferences(page, 'dark');
        await expect.poll(() => page.evaluate(() => Chart.instances.sectionRiskChart.options.scales.x.ticks.color)).toBe(await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--text-dark').trim()));
        expect(await page.evaluate(() => Chart.instances.sectionRiskChart.options.animation)).toBe(false);
        await expect(page.locator('[data-chart-fallback="sectionRiskChart"]')).toBeHidden();
        const chartValues = await page.evaluate(() => Chart.instances.sectionRiskChart.data.datasets.map(d => d.data));
        const tableValues = await page.locator('#section-risk-chart-summary tbody tr').evaluateAll(nodes => nodes.map(row => [...row.cells].slice(1).map(c => Number.parseFloat(c.textContent))));
        expect(tableValues).toEqual(tableValues.map((row, i) => row.map((_, j) => chartValues[j][i])));
        const honorCounts = await page.locator('#honor-chart-summary tbody tr td:last-child').allTextContents();
        expect(honorCounts.map(Number)).toEqual(await page.evaluate(() => Chart.instances.honorChart.data.datasets[0].data));
    });

    test('real overview charts update axes, tooltip and exact data across themes', async ({ page }) => {
        const errors = await login(page, 'admin');
        await expect.poll(() => page.evaluate(() => typeof window.Chart === 'function' && !!Chart.getChart('sectionRiskChart'))).toBe(true);
        for (const theme of ['dark', 'light']) {
            await preferences(page, theme);
            const result = await page.evaluate(() => {
                const chart = Chart.getChart('sectionRiskChart');
                const root = getComputedStyle(document.documentElement);
                return { axis: chart.options.scales.x.ticks.color, text: root.getPropertyValue('--text-dark').trim(), tooltip: chart.options.plugins.tooltip.bodyColor, motion: chart.options.animation, values: chart.data.datasets.map(d => d.data) };
            });
            expect(result.axis).toBe(result.text); expect(result.tooltip).toBe(result.text); expect(result.motion).toBe(false);
            const values = await page.locator('#section-risk-chart-summary tbody tr').evaluateAll(nodes => nodes.map(row => [...row.cells].slice(1).map(c => Number.parseFloat(c.textContent))));
            expect(values).toEqual(values.map((row, i) => row.map((_, j) => result.values[j][i])));
        }
        expect(errors).toEqual([]);
    });

    for (const gap of ['historical assignment', 'all groups empty']) {
        test(`unavailable card C retains selectors, removal and focus (${gap}; controlled response)`, async ({ page }) => {
            await login(page, 'admin');
            await page.getByRole('tab', { name: 'Academic Comparisons' }).click();
            await page.locator('#other-comparisons-disclosure summary').click();
            await page.locator('#comparison-group').selectOption('subject');
            await page.locator('#add-comparison-card-btn').click();
            const endpoint = new URL('api/academic_analytics.php' + (gap === 'all groups empty' ? '?section=NO-MATCH' : ''), base).href;
            const response = await (await page.request.get(endpoint)).json();
            if (gap === 'historical assignment') response.groups.faculty_subject = [{ ...response.groups.subject[0], key: 'unknown|assignment', label: 'Historical assignment unavailable' }];
            await page.route('**/api/academic_analytics.php*', route => route.fulfill({ json: response }));
            await apply(page);
            const dimension = page.locator('#panel-compare-c .card-dimension-select');
            if (gap === 'historical assignment') {
                await dimension.focus(); await dimension.selectOption('faculty_subject');
                await expect(dimension).toBeFocused();
                await expect(page.locator('#panel-compare-c .card-group-select')).toBeDisabled();
                await expect(page.locator('#panel-compare-c')).toContainText('Unavailable');
                await dimension.selectOption('subject');
                await expect(dimension).toBeFocused();
                await expect(page.locator('#panel-compare-c .card-group-select')).toBeEnabled();
            } else {
                await expect(dimension).toBeVisible();
                await expect(page.locator('#panel-compare-c .card-group-select')).toBeDisabled();
            }
            await page.locator('#remove-card-c').click();
            await expect(page.locator('#add-comparison-card-btn')).toBeFocused();
            await expect(page.locator('#add-comparison-card-btn')).toBeEnabled();
            await expect(page.locator('#panel-compare-c')).toBeHidden();
        });
    }
});

test('Student shared analytics seam preserves own-record filters, themes and unavailable bounds', async ({ page }) => {
    test.skip(!base || !account('student').id || !account('student').password, 'Requires explicit synthetic Student account');
    try {
        const errors = await login(page, 'student');
        await expect(page.locator('#academic-analytics')).toHaveAttribute('data-role', 'student');
        await expect(page.locator('#academic-analytics [role="tablist"]')).toHaveCount(0);
        expect(await page.locator('#analytics-filters select').evaluateAll(nodes => nodes.map(n => n.name))).toEqual(['school_year', 'semester']);
        await expect(page.locator('[data-metric="lower"]')).toHaveText('Unavailable');
        await expect(page.locator('[data-metric="upper"]')).toHaveText('Unavailable');
        await page.getByLabel('Semester', { exact: true }).selectOption('1'); await apply(page);
        await expect(page.locator('#analytics-state')).toContainText('Semester: 1');
        await page.reload(); await expect(page.locator('#analytics-results')).toBeVisible();
        await expect(page.getByLabel('Semester', { exact: true })).toHaveValue('1');
        await page.setViewportSize({ width: 1024, height: 768 });
        for (const theme of ['light', 'dark']) {
            await preferences(page, theme);
            expect(await contrast(page.getByRole('button', { name: 'Apply filters', exact: true }))).toBeGreaterThanOrEqual(4.5);
            expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        }
        expect((await page.request.get(new URL('api/academic_analytics.php?section=BSIT', base).href)).status()).toBe(422);
        expect(errors).toEqual([]);
    } finally { if (base) await page.goto(new URL('logout.php', base).href); }
});

test('analytics API denies anonymous and foreign Faculty scope (live read-only)', async ({ browser }) => {
    test.skip(!base || !account('faculty').id || !account('faculty').password, 'Requires explicit synthetic Faculty account');
    const context = await browser.newContext();
    try {
        expect((await context.request.get(new URL('api/academic_analytics.php', base).href)).status()).toBe(401);
        const page = await context.newPage(); await login(page, 'faculty');
        expect((await context.request.get(new URL('api/academic_analytics.php?faculty=2147483647', base).href)).status()).toBe(403);
        expect((await context.request.get(new URL('api/academic_analytics.php?student_id=2147483647', base).href)).status()).toBe(422);
        await page.goto(new URL('logout.php', base).href);
    } finally { await context.close(); }
});
