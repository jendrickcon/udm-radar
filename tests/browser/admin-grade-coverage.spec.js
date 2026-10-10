import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { mkdirSync } from 'node:fs';
const fixture = mode => execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [resolve('tests/fixtures/admin_coverage_fixture.php'), mode], { encoding: 'utf8' });
async function open(page, mode = 'default') { await page.setContent(fixture(mode)); }
for (const theme of ['light', 'dark']) for (const width of [390, 768, 1440]) {
    test(`[Generated static HTML] coverage filters, ${theme}, ${width}`, async ({ page }) => {
        const errors = [];
        page.on('pageerror', e => errors.push(e.message));
        page.on('console', m => { if (m.type() === 'error') errors.push(m.text()); });
        page.on('requestfailed', r => errors.push(r.url()));
        await page.setViewportSize({ width, height: 900 }); await open(page);
        await page.evaluate(t => document.documentElement.dataset.theme = t, theme);
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
        await expect(page.locator('.coverage-methodology')).not.toHaveAttribute('open');
        await page.locator('.coverage-methodology > summary').focus(); await page.keyboard.press('Enter');
        await expect(page.locator('.coverage-methodology')).toHaveAttribute('open');
        await page.keyboard.press('Enter'); await expect(page.locator('.coverage-methodology')).not.toHaveAttribute('open');
        await expect(page.getByRole('link', { name: 'Back to Program Analytics' })).toHaveClass(/btn--quiet/);
        await expect(page.locator('.coverage-source-item small, .coverage-summary small')).toHaveCount(0);
        await expect(page.locator('#coverage-filters')).toHaveClass('card coverage-filter-card');
        await expect(page.locator('#coverage-active-filters')).toBeHidden();
        await expect(page.locator('.coverage-metrics .stat-card')).toHaveCount(4);
        await expect(page.locator('table.data-table')).toHaveClass('data-table table-density--compact');
        await expect(page.getByRole('button', { name: /Calculation-based estimate/ })).toContainText('1 students');
        await expect(page.getByRole('button', { name: /Calculation-based estimate/ })).toHaveAccessibleDescription('A calculation used when the Decision Tree model could not be used with the available information.');
        const fallback = page.getByRole('button', { name: /Calculation-based estimate/ });
        const restingBorder = await fallback.evaluate(el => getComputedStyle(el).borderColor);
        await fallback.hover();
        await expect(fallback).not.toHaveCSS('border-color', restingBorder);
        await fallback.focus(); await expect(fallback).toBeFocused();
        expect(await fallback.evaluate(el => getComputedStyle(el).outlineStyle)).toBe('solid');
        await page.getByRole('button', { name: /Decision Tree model/ }).first().click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–2 of 2 students');
        await expect(page.locator('#coverage-source')).toBeFocused();
        await expect(page.locator('[data-source-filter="decision_tree"]')).toHaveAttribute('aria-pressed', 'true');
        await expect(page.locator('[data-source-filter="decision_tree"] [data-source-cue]')).toHaveText('Selected · View students');
        await expect(page.locator('#coverage-active-filters')).toBeVisible();
        await expect(page.locator('#coverage-active-filters')).toHaveText('Active filters: Decision Tree model');
        await page.getByRole('button', { name: 'Clear filters' }).click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
        await expect(page.locator('#coverage-active-filters')).toBeHidden();
        await expect(page.locator('[data-source-filter][aria-pressed="true"]')).toHaveCount(0);
        await page.locator('#coverage-triage').selectOption('missing_prelim');
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–3 of 3 students');
        await expect(page.locator('tr[data-coverage-record]:visible')).toHaveCount(3);
        await page.getByRole('button', { name: 'Clear filters' }).click();
        await page.locator('#coverage-triage').selectOption('provisional');
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–2 of 2 students');
        await expect(page.locator('#coverage-status')).toHaveAttribute('aria-live', 'polite');
        await page.locator('#coverage-period').focus(); await page.keyboard.press('ArrowDown');
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(page.locator('#coverage-results')).toHaveAttribute('aria-busy', 'false');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(errors).toEqual([]);
    });
}
test('[Generated static HTML] empty and load-error states', async ({ page }) => {
    await open(page, 'empty'); await expect(page.locator('#coverage-empty')).toBeVisible();
    await expect(page.locator('#coverage-period-summary')).toBeEmpty();
    await open(page, 'error'); await expect(page.getByRole('alert')).toContainText('Coverage records unavailable');
    await expect(page.getByRole('link', { name: 'Retry loading records' })).toHaveAttribute('href', 'coverage.php');
});

for (const theme of ['light', 'dark']) for (const width of [390, 768, 1440]) {
    test(`[Generated header fragment] Analytics actions, ${theme}, ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 }); await open(page, 'analytics');
        await page.evaluate(t => document.documentElement.dataset.theme = t, theme);
        const entry = page.getByRole('link', { name: /Review Data Coverage/ });
        const exportButton = page.getByRole('button', { name: 'Download PDF Report' });
        await expect(entry).toHaveClass(/btn--secondary/); await expect(entry).toHaveAttribute('href', 'coverage.php');
        await expect(exportButton).toHaveClass('btn btn--primary');
        await expect(exportButton).toHaveAttribute('onclick', 'triggerProgramAnalyticsExportPdf()');
        expect(await exportButton.evaluate(el => getComputedStyle(el).backgroundColor)).not.toBe(await entry.evaluate(el => getComputedStyle(el).backgroundColor));
        await exportButton.focus(); expect(await exportButton.evaluate(el => getComputedStyle(el).outlineStyle)).toBe('solid');
        await expect(page.locator('.analytics-header-actions > a')).toHaveCount(1);
        await expect(page.locator('h1 + p')).toHaveText('Program-level academic patterns and curriculum bottleneck identification.');
        await entry.focus(); await expect(entry).toBeFocused();
        const linkBounds = await entry.boundingBox(), pdfBounds = await exportButton.boundingBox();
        expect(linkBounds.height).toBeGreaterThanOrEqual(44); expect(pdfBounds.height).toBeGreaterThanOrEqual(44);
        expect(linkBounds.y < pdfBounds.y || linkBounds.x + linkBounds.width <= pdfBounds.x).toBe(true);
        expect(await entry.evaluate(el => getComputedStyle(el).outlineStyle)).toBe('solid');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });

    test(`[Generated static HTML] paginated compact review, ${theme}, ${width}`, async ({ page }) => {
        await page.setViewportSize({ width, height: 900 }); await open(page, 'paged');
        await page.evaluate(t => document.documentElement.dataset.theme = t, theme);
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–25 of 125');
        await expect(page.locator('tr[data-coverage-record]:visible')).toHaveCount(25);
        await expect(page.locator('[data-page-label]').first()).toHaveText('Page 1 of 5');
        await expect(page.locator('[data-page-action="previous"]').first()).toBeDisabled();
        await expect(page.locator('[data-page-action="next"]').first()).toBeEnabled();
        await expect(page.locator('[data-pagination]')).toHaveCount(1);
        const firstRow = page.locator('tr[data-coverage-record]:visible').first();
        expect((await firstRow.boundingBox()).height).toBeLessThan(145);
        const detailRow = page.locator('#coverage-detail-1');
        await expect(detailRow).toBeHidden();
        const toggle = firstRow.locator('.coverage-detail-toggle');
        await expect(toggle).toHaveAttribute('aria-label', 'View details for Synthetic Student 001');
        await expect(toggle).toHaveText('View details');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toHaveAttribute('aria-controls', 'coverage-detail-1');
        await toggle.focus(); await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'true');
        await expect(toggle).toHaveText('Hide details');
        await expect(toggle).toHaveAttribute('aria-label', 'Hide details for Synthetic Student 001');
        await expect(detailRow).toBeVisible();
        await expect(firstRow).toHaveClass(/is-expanded/);
        await expect(detailRow.locator('.coverage-detail-meta')).toContainText('Generated on');
        await page.keyboard.press('Enter');
        await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        await expect(toggle).toHaveText('View details');
        await expect(toggle).toHaveAttribute('aria-label', 'View details for Synthetic Student 001');
        await expect(detailRow).toBeHidden();
        await expect(firstRow).not.toHaveClass(/is-expanded/);
        await page.locator('[data-page-action="next"]').first().click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 26–50 of 125');
        await expect(page.locator('#coverage-status')).toBeFocused();
        await expect(page.locator('tr[data-coverage-record]:visible').first()).toContainText('Synthetic Student 026');
        await page.locator('[data-page-action="previous"]').first().click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–25 of 125');
        for (const size of [50, 100]) {
            await page.locator('#coverage-page-size').selectOption(String(size));
            await page.getByRole('button', { name: 'Apply filters' }).click();
            await expect(page.locator('tr[data-coverage-record]:visible')).toHaveCount(size);
            await expect(page.locator('#coverage-status')).toContainText(`Showing 1–${size} of 125`);
        }
        await page.locator('[data-page-action="next"]').last().click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 101–125 of 125');
        await expect(page.locator('[data-page-action="next"]').last()).toBeDisabled();
        await page.locator('#coverage-section').selectOption('SYN-A');
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(page.locator('[data-page-label]').first()).toHaveText('Page 1 of 1');
        await expect(page.locator('#coverage-active-filters')).toContainText('SYN-A');
        await page.getByRole('button', { name: 'Clear filters' }).click();
        await expect(page.locator('#coverage-page-size')).toHaveValue('25');
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–25 of 125');
        await page.locator('[data-page-action="next"]').first().click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 26–50 of 125');
        await page.getByRole('button', { name: 'Clear filters' }).click();
        await expect(page.locator('#coverage-status')).toContainText('Showing 1–25 of 125');
        await expect(page.locator('#coverage-status')).toHaveAttribute('aria-live', 'polite');
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
        expect(await page.evaluate(() => {
            const ids = [...document.querySelectorAll('[id]')].map(el => el.id);
            return new Set(ids).size === ids.length && ![...document.querySelectorAll('[tabindex]')].some(el => Number(el.getAttribute('tabindex')) > 0);
        })).toBe(true);
        await expect(page.locator('.coverage-table-region thead')).toHaveCSS('position', 'sticky');
        await page.locator('#coverage-page').evaluate(el => {
            const distance = el.querySelector('table.data-table').getBoundingClientRect().top + 100;
            if (el.scrollHeight > el.clientHeight) el.scrollTop += distance - el.getBoundingClientRect().top;
            else window.scrollBy(0, distance);
        });
        await expect.poll(() => page.locator('.coverage-table-region thead').evaluate(el => Math.round(el.getBoundingClientRect().top))).toBe(0);
        expect(await page.locator('.coverage-table-region').evaluate(el => el.scrollHeight === el.clientHeight)).toBe(true);
    });
}

test('[Generated static HTML with URL state] out-of-range pages normalize; filters survive navigation', async ({ page }) => {
    await page.route('https://coverage.test/**', route => route.fulfill({ contentType: 'text/html', body: fixture('paged') }));
    await page.goto('https://coverage.test/review?page=999&page_size=50&section=SYN-A');
    await expect(page.locator('[data-page-label]').first()).toHaveText('Page 2 of 2');
    await expect(page.locator('#coverage-status')).toContainText('Showing 51–54 of 54');
    await expect(page).toHaveURL(/page=2/);
    await page.locator('[data-page-action="previous"]').first().click();
    await expect(page.locator('#coverage-section')).toHaveValue('SYN-A');
    await expect(page.locator('#coverage-status')).toContainText('Showing 1–50 of 54');
    await page.locator('#coverage-source').selectOption('unknown');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('#coverage-empty')).toBeVisible();
    await expect(page.locator('#coverage-status')).toHaveText('No students match the selected filters.');
    await expect(page.locator('[data-pagination]').first()).toBeHidden();
    await page.getByRole('button', { name: 'Clear filters' }).first().click();
    await expect(page.locator('#coverage-status')).toContainText('Showing 1–25 of 125');
});

test('[JavaScript disabled generated HTML] all rows and native disclosures remain useful', async ({ browser }) => {
    const context = await browser.newContext({ javaScriptEnabled: false });
    try {
        const page = await context.newPage();
        await page.route('https://coverage.test/**', route => route.fulfill({ contentType: 'text/html; charset=UTF-8', body: fixture('paged') }));
        await page.goto('https://coverage.test/no-script');
        await expect(page.locator('tr[data-coverage-record]:visible')).toHaveCount(125);
        await expect(page.locator('[data-pagination]').first()).toBeHidden();
        await expect(page.locator('#coverage-enhancement-note')).toBeVisible();
        await expect(page.locator('#coverage-enhancement-note')).toContainText('All recorded results are shown');
        const detailRow = page.locator('.coverage-detail-row').first();
        await expect(detailRow).toBeVisible();
        await expect(detailRow.locator('.coverage-detail-meta')).toContainText('Generated on');
        await page.getByText('How this view counts results', { exact: true }).click();
        await expect(page.locator('.coverage-methodology')).toHaveAttribute('open');
    } finally { await context.close(); }
});

test('[Controlled animation frame] filter loading announces busy state until results settle', async ({ page }) => {
    await open(page); await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
    await page.evaluate(() => {
        window.coverageFrames = [];
        window.requestAnimationFrame = callback => window.coverageFrames.push(callback);
        document.getElementById('coverage-filters').requestSubmit();
    });
    await expect(page.locator('#coverage-loading')).toBeVisible();
    await expect(page.locator('#coverage-results')).toHaveAttribute('aria-busy', 'true');
    await page.evaluate(() => window.coverageFrames.shift()());
    await expect(page.locator('#coverage-loading')).toBeHidden();
    await expect(page.locator('#coverage-results')).toHaveAttribute('aria-busy', 'false');
    await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
});
test('[Mocked browser fault] failed filter parsing can recover without requests', async ({ page }) => {
    await open(page); await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
    const original = await page.locator('[data-coverage-record]').first().getAttribute('data-coverage-record');
    await page.locator('[data-coverage-record]').first().evaluate(el => el.dataset.coverageRecord = '{');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('#coverage-filter-error')).toBeVisible();
    await page.locator('[data-coverage-record]').first().evaluate((el, value) => el.dataset.coverageRecord = value, original);
    await page.getByRole('button', { name: 'Retry filters' }).click();
    await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
    await expect(page.locator('#coverage-filter-error')).toBeHidden();
});
test('[Generated static HTML] every triage filter and reset', async ({ page }) => {
    await open(page);
    for (const [filter, count] of Object.entries({ missing_midterm: 5, missing_prefinal: 5, no_numeric: 3, partial_inputs: 1, insufficient: 3, no_records: 1 })) {
        await page.locator('#coverage-triage').selectOption(filter);
        await page.getByRole('button', { name: 'Apply filters' }).click();
        await expect(page.locator('#coverage-status')).toContainText(`Showing 1–${count} of ${count} students`);
    }
    await page.locator('#coverage-source').selectOption('unknown');
    await page.getByRole('button', { name: 'Apply filters' }).click(); await expect(page.locator('#coverage-empty')).toBeVisible();
    await page.getByRole('button', { name: 'Clear filters' }).first().click(); await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
});

test('[Generated static HTML] Preliminary results coexist clearly with 0 final outcomes', async ({ page }) => {
    await open(page);
    const firstRow = page.locator('tr[data-coverage-record]').first();
    await expect(firstRow.locator('[data-period-coverage]')).toContainText('1 of 1 Preliminary');
    await expect(firstRow.locator('[data-period-coverage]')).not.toContainText('Complete');
    await expect(firstRow.locator('[data-period-coverage] .badge')).toHaveCount(0);
    await expect(page.locator('tbody .table-header-help')).toHaveCount(0);
    await expect(page.locator('tbody .coverage-badge-complete')).toHaveCount(0);

    const toggle = firstRow.locator('.coverage-detail-toggle');
    await toggle.click();
    const detailRow = page.locator('#coverage-detail-1');
    await expect(detailRow.locator('.coverage-detail-heading').first()).toHaveText('Final Grade Records');
    await expect(detailRow).toContainText('Final grades recorded for 1 of 1 subjects');

    const secondRow = page.locator('tr[data-coverage-record]').nth(1);
    const secondToggle = secondRow.locator('.coverage-detail-toggle');
    await secondToggle.click();
    const secondDetail = page.locator('#coverage-detail-2');
    await expect(secondDetail.locator('.coverage-detail-heading').first()).toHaveText('Final Grade Records');
    await expect(secondDetail).toContainText('Final grades recorded for 1 of 2 subjects');
    await expect(secondDetail).toContainText('1 subject has no final grade recorded yet');
});

test('[Generated static HTML] Learn about coverage link opens Data Coverage guide and restores focus', async ({ page }) => {
    await open(page);
    const guideLink = page.locator('#coverage-guide-trigger');
    await expect(guideLink).toBeVisible();
    await expect(guideLink).toHaveText(/Learn about coverage and prediction sources/);

    await guideLink.click();
    const drawer = page.locator('#glossary-drawer-overlay');
    await expect(drawer).toBeVisible();
    await expect(drawer).toHaveClass(/open/);

    const coverageTab = page.locator('#tab-btn-coverage');
    await expect(coverageTab).toHaveClass(/active/);
    await expect(coverageTab).toHaveAttribute('aria-selected', 'true');
    const coveragePane = page.locator('#tab-pane-coverage');
    await expect(coveragePane).toBeVisible();
    await expect(coveragePane).toHaveClass(/active/);

    await expect(coveragePane).toContainText('Available Grade Records');
    await expect(coveragePane).toContainText('Period Results and Final Grades');
    await expect(coveragePane).toContainText('Prediction Sources');
    await expect(coveragePane).toContainText('Decision Tree model');
    await expect(coveragePane).toContainText('Calculation-based estimate');
    await expect(coveragePane).toContainText('Earlier calculation method');
    await expect(coveragePane).toContainText('Saved Prediction Information');
    await expect(coveragePane).toContainText('Important Reminders');

    await page.keyboard.press('Escape');
    await expect(drawer).not.toHaveClass(/open/);
    await expect(guideLink).toBeFocused();
});

test('[Generated static HTML] changing recorded period updates period-aware labels and badges', async ({ page }) => {
    await open(page);
    // Preliminary (default)
    await expect(page.locator('#coverage-kpi-context')).toContainText('Current-term results · Preliminary');
    await expect(page.locator('#coverage-kpi-context')).toHaveAttribute('aria-label', 'Current-term result summary for the selected Preliminary grading period.');
    await expect(page.locator('#coverage-kpi-period-label')).toHaveText('Preliminary');
    await expect(page.locator('#coverage-reviewed-title')).toHaveText('Students reviewed');
    await expect(page.locator('#coverage-complete-title')).toHaveText('Complete');
    await expect(page.locator('#coverage-partial-title')).toHaveText('Some missing');
    await expect(page.locator('#coverage-unavailable-title')).toHaveText('Needs review');
    await expect(page.locator('#coverage-card-complete')).toHaveAttribute('aria-label', /students have results recorded for every current subject row in the selected Preliminary period\./);
    await expect(page.locator('#coverage-card-partial')).toHaveAttribute('aria-label', /students have at least one recorded Preliminary result and at least one missing Preliminary result\./);
    await expect(page.locator('#coverage-card-needs-review')).toHaveAttribute('aria-label', /students have no Preliminary results recorded or no current subjects found\./);
    await expect(page.locator('#coverage-unavailable-subtext')).toHaveText('no Preliminary results recorded or no current subjects found');

    // Switch to Midterm
    await page.locator('#coverage-period').selectOption('midterm');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('#coverage-kpi-context')).toContainText('Current-term results · Midterm');
    await expect(page.locator('#coverage-kpi-context')).toHaveAttribute('aria-label', 'Current-term result summary for the selected Midterm grading period.');
    await expect(page.locator('#coverage-kpi-period-label')).toHaveText('Midterm');
    await expect(page.locator('#coverage-complete-title')).toHaveText('Complete');
    await expect(page.locator('#coverage-partial-title')).toHaveText('Some missing');
    await expect(page.locator('#coverage-card-complete')).toHaveAttribute('aria-label', /students have results recorded for every current subject row in the selected Midterm period\./);
    await expect(page.locator('#coverage-unavailable-subtext')).toHaveText('no Midterm results recorded or no current subjects found');
    await expect(page.locator('tr[data-coverage-record]').first().locator('[data-period-coverage]')).toContainText('Midterm');

    // Switch to Pre-Final
    await page.locator('#coverage-period').selectOption('prefinal');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(page.locator('#coverage-kpi-context')).toContainText('Current-term results · Pre-Final');
    await expect(page.locator('#coverage-kpi-context')).toHaveAttribute('aria-label', 'Current-term result summary for the selected Pre-Final grading period.');
    await expect(page.locator('#coverage-kpi-period-label')).toHaveText('Pre-Final');
    await expect(page.locator('#coverage-complete-title')).toHaveText('Complete');
    await expect(page.locator('#coverage-partial-title')).toHaveText('Some missing');
    await expect(page.locator('#coverage-card-complete')).toHaveAttribute('aria-label', /students have results recorded for every current subject row in the selected Pre-Final period\./);
    await expect(page.locator('#coverage-unavailable-subtext')).toHaveText('no Pre-Final results recorded or no current subjects found');
    await expect(page.locator('tr[data-coverage-record]').first().locator('[data-period-coverage]')).toContainText('Pre-Final');
});

test('[Generated static HTML] column-header help tooltips open downward without clipping, toggle on click, and dismiss properly', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);

    const resultsHelp = page.locator('#help-tooltip-results');
    const infoHelp = page.locator('#help-tooltip-info');
    const predHelp = page.locator('#help-tooltip-prediction');

    await expect(resultsHelp).toHaveAttribute('aria-describedby', 'tooltip-text-results');
    await expect(infoHelp).toHaveAttribute('aria-describedby', 'tooltip-text-info');
    await expect(predHelp).toHaveAttribute('aria-describedby', 'tooltip-text-prediction');

    const resultsTooltip = page.locator('#tooltip-text-results');
    const infoTooltip = page.locator('#tooltip-text-info');
    const predTooltip = page.locator('#tooltip-text-prediction');

    await expect(resultsTooltip).toHaveAttribute('role', 'tooltip');
    await expect(resultsTooltip).toContainText('Shows how many existing subjects have a recorded result');

    const region = page.locator('.coverage-table-region');
    const regionBox = await region.boundingBox();

    // Test 1: Results Tooltip downward placement and containment
    await resultsHelp.focus();
    await expect(resultsHelp).toBeFocused();
    await expect(resultsTooltip).toBeVisible();

    let btnBox = await resultsHelp.boundingBox();
    let ttBox = await resultsTooltip.boundingBox();
    expect(ttBox.y).toBeGreaterThanOrEqual(btnBox.y + btnBox.height - 2);
    expect(ttBox.y).toBeGreaterThanOrEqual(regionBox.y - 1);
    expect(ttBox.y + ttBox.height).toBeLessThanOrEqual(regionBox.y + regionBox.height + 2);

    await page.keyboard.press('Escape');
    await expect(resultsHelp).not.toBeFocused();

    // Test 2: Info Tooltip downward placement
    await infoHelp.focus();
    await expect(infoTooltip).toBeVisible();
    btnBox = await infoHelp.boundingBox();
    ttBox = await infoTooltip.boundingBox();
    expect(ttBox.y).toBeGreaterThanOrEqual(btnBox.y + btnBox.height - 2);
    expect(ttBox.y).toBeGreaterThanOrEqual(regionBox.y - 1);
    expect(ttBox.y + ttBox.height).toBeLessThanOrEqual(regionBox.y + regionBox.height + 2);
    await page.keyboard.press('Escape');

    // Test 3: Prediction Tooltip downward placement
    await predHelp.focus();
    await expect(predTooltip).toBeVisible();
    btnBox = await predHelp.boundingBox();
    ttBox = await predTooltip.boundingBox();
    expect(ttBox.y).toBeGreaterThanOrEqual(btnBox.y + btnBox.height - 2);
    expect(ttBox.y).toBeGreaterThanOrEqual(regionBox.y - 1);
    expect(ttBox.y + ttBox.height).toBeLessThanOrEqual(regionBox.y + regionBox.height + 2);
    await page.keyboard.press('Escape');

    // Test 4: Click toggle and outside click dismissal
    await resultsHelp.click();
    await expect(resultsHelp).toHaveClass(/is-open/);
    await expect(resultsTooltip).toBeVisible();

    await page.locator('.coverage-heading h1').click();
    await expect(resultsHelp).not.toHaveClass(/is-open/);

    // Test 5: Click toggle and Escape dismissal
    await predHelp.click();
    await expect(predHelp).toHaveClass(/is-open/);
    await expect(predTooltip).toBeVisible();

    await page.keyboard.press('Escape');
    await expect(predHelp).not.toHaveClass(/is-open/);

    // Test 6: Hover persistence
    await infoHelp.hover();
    await expect(infoTooltip).toBeVisible();
    await page.mouse.move(0, 0);
});

test('[Generated static HTML] methodology card is compact when collapsed and detail columns have unequal widths', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);

    // Collapsed methodology height assertion
    const methodology = page.locator('.coverage-methodology');
    const summary = page.locator('.coverage-methodology > summary');
    const closedBox = await methodology.boundingBox();
    const summaryBox = await summary.boundingBox();
    expect(closedBox.height).toBeLessThanOrEqual(summaryBox.height + 6);
    expect(closedBox.height).toBeLessThanOrEqual(52);

    // Expand detail row on desktop (1440px)
    const firstRow = page.locator('tr[data-coverage-record]').first();
    const toggle = firstRow.locator('.coverage-detail-toggle');
    await toggle.click();

    const detailRow = page.locator('#coverage-detail-1');
    await expect(detailRow).toBeVisible();

    const sections = detailRow.locator('.coverage-detail-section');
    await expect(sections).toHaveCount(3);

    const finalBox = await sections.nth(0).boundingBox();
    const savedBox = await sections.nth(1).boundingBox();
    const aboutBox = await sections.nth(2).boundingBox();

    // Unequal column widths: Saved Prediction and About This Result have significantly more width than Final Grade Records
    expect(savedBox.width).toBeGreaterThan(finalBox.width);
    expect(aboutBox.width).toBeGreaterThan(finalBox.width);
});

test('[Generated static HTML] row geometry, compact 36px button with 44px target, and calm typography', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);

    const firstRow = page.locator('tr[data-coverage-record]').first();
    const rowBox = await firstRow.boundingBox();
    console.log('REPRESENTATIVE CLOSED ROW BOX:', rowBox);
    // Closed summary row is compact (height <= 65px)
    expect(rowBox.height).toBeLessThanOrEqual(65);

    const toggle = firstRow.locator('.coverage-detail-toggle');
    const toggleBox = await toggle.boundingBox();
    console.log('REPRESENTATIVE DETAILS BUTTON BOX:', toggleBox);

    // 1. Details button visible surface is compact (36px to 38px, strictly less than 44px).
    expect(toggleBox.height).toBeLessThan(44);
    expect(toggleBox.height).toBeGreaterThanOrEqual(36);
    expect(toggleBox.height).toBeLessThanOrEqual(38);

    // 2. Effective pointer/touch target is at least 44px via pseudo-element.
    const effectiveHeight = await toggle.evaluate(el => {
        const r = el.getBoundingClientRect();
        const before = window.getComputedStyle(el, '::before');
        const top = parseFloat(before.top) || 0;
        const bottom = parseFloat(before.bottom) || 0;
        return r.height + Math.abs(top) + Math.abs(bottom);
    });
    expect(effectiveHeight).toBeGreaterThanOrEqual(44);

    // 3. Details button is vertically centered in its cell with balanced visible gaps.
    const actionCell = firstRow.locator('.table-col-action');
    const cellBox = await actionCell.boundingBox();
    const buttonCenter = toggleBox.y + toggleBox.height / 2;
    const cellCenter = cellBox.y + cellBox.height / 2;
    expect(Math.abs(buttonCenter - cellCenter)).toBeLessThanOrEqual(1);

    const topGap = toggleBox.y - cellBox.y;
    const bottomGap = (cellBox.y + cellBox.height) - (toggleBox.y + toggleBox.height);
    expect(topGap).toBeGreaterThanOrEqual(5);
    expect(bottomGap).toBeGreaterThanOrEqual(5);
    expect(Math.abs(topGap - bottomGap)).toBeLessThanOrEqual(1);

    // 4. Typography hierarchy checks:
    // Student name: medium/semibold (600), not 700+ heading bold
    const studentTh = firstRow.locator('th[scope="row"]');
    const studentWeight = await studentTh.evaluate(el => getComputedStyle(el).fontWeight);
    expect(['500', '600']).toContain(studentWeight);

    // Available-result count: regular/medium (400 or 500), not bold heading
    const countSpan = firstRow.locator('.coverage-count');
    const countWeight = await countSpan.evaluate(el => getComputedStyle(el).fontWeight);
    expect(['400', '500']).toContain(countWeight);
    expect(countWeight).not.toBe('700');

    // Grade-information value: normal body weight (400)
    const infoCell = firstRow.locator('td').nth(1);
    const infoWeight = await infoCell.evaluate(el => getComputedStyle(el).fontWeight);
    expect(['400', 'normal']).toContain(infoWeight);

    // Saved prediction source: regular/medium weight (400 or 500)
    const sourceCell = firstRow.locator('td').nth(2);
    const sourceLabel = sourceCell.locator('.coverage-source-label');
    const sourceWeight = await sourceLabel.evaluate(el => getComputedStyle(el).fontWeight);
    expect(['400', '500']).toContain(sourceWeight);
    expect(sourceWeight).not.toBe('700');

    // Secondary metadata: smaller muted text (<= 13px, 400 weight)
    const meta = firstRow.locator('small, .coverage-subtext').first();
    const metaWeight = await meta.evaluate(el => getComputedStyle(el).fontWeight);
    const metaSize = await meta.evaluate(el => parseFloat(getComputedStyle(el).fontSize));
    expect(['400', 'normal']).toContain(metaWeight);
    expect(metaSize).toBeLessThanOrEqual(13);

    // Action button: medium/semibold weight (600)
    const actionWeight = await toggle.evaluate(el => getComputedStyle(el).fontWeight);
    expect(['500', '600']).toContain(actionWeight);


    // 5. Normal rows do not receive hover background changes.
    const restingBg = await firstRow.evaluate(el => getComputedStyle(el).backgroundColor);
    await firstRow.hover();
    const hoverBg = await firstRow.evaluate(el => getComputedStyle(el).backgroundColor);
    expect(hoverBg).toBe(restingBg);

    // 8. Closed button reads View details.
    await expect(toggle).toHaveText('View details');
    await expect(toggle).toHaveAttribute('aria-label', 'View details for Synthetic Complete');
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');

    // 9 & 10. Click toggle -> Open button reads Hide details and aria-expanded="true"
    await toggle.click();
    await expect(toggle).toHaveText('Hide details');
    await expect(toggle).toHaveAttribute('aria-label', 'Hide details for Synthetic Complete');
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');

    // 6 & 7. Expanded summary row does NOT use table-header background or broad blue fill
    const tableHeaderBg = await page.evaluate(() => getComputedStyle(document.documentElement).getPropertyValue('--table-header-bg').trim());
    const expandedRowBg = await firstRow.evaluate(el => getComputedStyle(el).backgroundColor);
    expect(expandedRowBg).not.toBe(tableHeaderBg);

    const expandedRowBox = await firstRow.boundingBox();
    console.log('REPRESENTATIVE EXPANDED ROW BOX:', expandedRowBox);

    const detailRow = page.locator('#coverage-detail-1');
    await expect(detailRow).toBeVisible();
    const detailBox = await detailRow.boundingBox();
    console.log('REPRESENTATIVE EXPANDED DETAIL ROW BOX:', detailBox);

    // 13. Detail row spans all five columns
    await expect(detailRow.locator('td')).toHaveAttribute('colspan', '5');

    // 12. Visual connection: both summary row th and detail row cell share accent border/shadow
    const thAccent = await firstRow.locator('th').evaluate(el => getComputedStyle(el).boxShadow);
    const detailAccent = await detailRow.locator('.coverage-detail-cell').evaluate(el => getComputedStyle(el).boxShadow);
    expect(thAccent).toContain('rgb');
    expect(detailAccent).toContain('rgb');

    // 11. One-open-at-a-time behavior: expanding second row closes first row
    const secondRow = page.locator('tr[data-coverage-record]').nth(1);
    const secondToggle = secondRow.locator('.coverage-detail-toggle');
    await secondToggle.click();
    await expect(secondToggle).toHaveText('Hide details');
    await expect(toggle).toHaveText('View details');
    await expect(detailRow).toBeHidden();
    await expect(page.locator('#coverage-detail-2')).toBeVisible();

    // Close second row
    await secondToggle.click();
    await expect(secondToggle).toHaveText('View details');
    await expect(page.locator('#coverage-detail-2')).toBeHidden();
});

test('[Generated static HTML] empty state in tbody with clear action and no page 0', async ({ page }) => {
    await open(page);

    // 1. Filter to 0 results
    await page.locator('#coverage-source').selectOption('unknown');
    await page.getByRole('button', { name: 'Apply filters' }).click();

    // 2. Headings remain visible
    await expect(page.locator('.coverage-table-region thead th').first()).toBeVisible();

    // 3. Status above table: No students match the selected filters.
    await expect(page.locator('#coverage-status')).toHaveText('No students match the selected filters.');
    await expect(page.locator('#coverage-status')).not.toContainText('0–0');
    await expect(page.locator('#coverage-status')).not.toContainText('Page 0');

    // 4. Empty state row inside tbody with colspan=5
    const emptyRow = page.locator('#coverage-empty');
    await expect(emptyRow).toBeVisible();
    expect(await emptyRow.evaluate(el => el.tagName.toLowerCase())).toBe('tr');
    expect(await emptyRow.evaluate(el => el.parentElement.tagName.toLowerCase())).toBe('tbody');
    await expect(emptyRow.locator('td')).toHaveAttribute('colspan', '5');

    // 5. Visible wording
    await expect(emptyRow.locator('.coverage-empty-title')).toHaveText('No students match the selected filters.');
    await expect(emptyRow.locator('.coverage-empty-desc')).toHaveText('Try changing the filters or clear them to view all students.');

    // 6. Pagination is completely hidden
    await expect(page.locator('[data-pagination]')).toBeHidden();

    // 7. Compact empty table height
    const regionBox = await page.locator('.coverage-table-region').boundingBox();
    expect(regionBox.height).toBeLessThan(260);

    // 8. Source control shows 0 students and "Selected · No matching students"
    const unknownSource = page.locator('[data-source-filter="unknown"]');
    await expect(unknownSource.locator('.coverage-source-count')).toHaveText('0 students');
    await expect(unknownSource.locator('[data-source-cue]')).toHaveText('Selected · No matching students');

    // 9. Keyboard operation: focus Clear filters inside empty state and press Enter
    const emptyClear = emptyRow.locator('#coverage-empty-clear');
    await emptyClear.focus();
    await expect(emptyClear).toBeFocused();
    await page.keyboard.press('Enter');

    // 10. Default results restored
    await expect(page.locator('#coverage-status')).toContainText('Showing 1–7 of 7 students');
    await expect(emptyRow).toBeHidden();
    await expect(page.locator('#coverage-source')).toHaveValue('');
});

test('[Layout & Polish Pass] compact notice, uniform KPI cards, needs-review tooltip, and grid filters', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);

    // 1. Compact prototype notice
    const notice = page.locator('.coverage-notice');
    await expect(notice).toBeVisible();
    await expect(notice.locator('.coverage-notice-title')).toHaveText('Prototype data');
    await expect(notice.locator('.coverage-notice-body')).toContainText('Prototype performance on synthetic academic records');
    await expect(notice.locator('.coverage-notice-body')).toContainText('Registrar finalization');
    const noticeBox = await notice.boundingBox();
    expect(noticeBox.height).toBeLessThanOrEqual(95);

    // 2. Shared context heading above KPI grid and concise single-line KPI labels
    const contextHeading = page.locator('#coverage-kpi-context');
    await expect(contextHeading).toBeVisible();
    await expect(contextHeading).toContainText('Current-term results · Preliminary');
    await expect(contextHeading).toHaveAttribute('aria-label', 'Current-term result summary for the selected Preliminary grading period.');
    await expect(page.locator('#coverage-kpi-period-label')).toHaveText('Preliminary');

    const cards = page.locator('.coverage-metrics .stat-card');
    await expect(cards).toHaveCount(4);

    // Exact concise visible titles
    expect(await cards.nth(0).locator('h4').textContent()).toBe('Students reviewed');
    expect(await cards.nth(1).locator('h4').textContent()).toBe('Complete');
    expect(await cards.nth(2).locator('h4').textContent()).toBe('Some missing');
    expect(await cards.nth(3).locator('h4').textContent()).toBe('Needs review');

    // Verify card labels do NOT repeat Preliminary, Midterm, or Pre-Final
    for (let i = 0; i < 4; i++) {
        const text = await cards.nth(i).locator('h4').textContent();
        expect(text).not.toMatch(/Preliminary|Midterm|Pre-Final/i);
    }

    // Verify accessible descriptions on cards
    await expect(page.locator('#coverage-card-reviewed')).toHaveAttribute('aria-label', /students reviewed/);
    await expect(page.locator('#coverage-card-complete')).toHaveAttribute('aria-label', /students have results recorded for every current subject row in the selected Preliminary period\./);
    await expect(page.locator('#coverage-card-partial')).toHaveAttribute('aria-label', /students have at least one recorded Preliminary result and at least one missing Preliminary result\./);
    await expect(page.locator('#coverage-card-needs-review')).toHaveAttribute('aria-label', /students have no Preliminary results recorded or no current subjects found\./);

    const cardBoxes = await Promise.all([0, 1, 2, 3].map(i => cards.nth(i).boundingBox()));
    const cardHeights = cardBoxes.map(b => b.height);
    const heightDiff = Math.max(...cardHeights) - Math.min(...cardHeights);
    expect(heightDiff).toBeLessThanOrEqual(2);
    for (const h of cardHeights) {
        expect(h).toBeLessThanOrEqual(80);
    }

    const headingRows = page.locator('.coverage-metrics .coverage-kpi-heading-row');
    const headingBoxes = await Promise.all([0, 1, 2, 3].map(i => headingRows.nth(i).boundingBox()));
    const headingHeights = headingBoxes.map(b => b.height);
    const headingHeightDiff = Math.max(...headingHeights) - Math.min(...headingHeights);
    expect(headingHeightDiff).toBeLessThanOrEqual(1);

    // Headings remain single-line (<= 20px) at 1440px
    for (const h of headingHeights) {
        expect(h).toBeLessThanOrEqual(20);
    }

    const h2Elements = page.locator('.coverage-metrics .stat-card h2');
    const h2Boxes = await Promise.all([0, 1, 2, 3].map(i => h2Elements.nth(i).boundingBox()));
    const h2YPositions = h2Boxes.map(b => b.y);
    const h2YDiff = Math.max(...h2YPositions) - Math.min(...h2YPositions);
    expect(h2YDiff).toBeLessThanOrEqual(1);

    // Gaps between heading and value are compact (between 4px and 6px) and lower padding <= 16px
    for (let i = 0; i < 4; i++) {
        const gap = h2Boxes[i].y - (headingBoxes[i].y + headingBoxes[i].height);
        expect(gap).toBeGreaterThanOrEqual(4);
        expect(gap).toBeLessThanOrEqual(6);
        const lowerPadding = (cardBoxes[i].y + cardBoxes[i].height) - (h2Boxes[i].y + h2Boxes[i].height);
        expect(lowerPadding).toBeGreaterThanOrEqual(10);
        expect(lowerPadding).toBeLessThanOrEqual(16);
    }

    // Verify lower descriptions removed from cards
    await expect(page.locator('.coverage-metrics .stat-card-subtext')).toHaveCount(0);

    // 3. Needs review tooltip interaction and dynamic period text
    const needsReviewHelp = page.locator('#help-tooltip-needs-review');
    await expect(needsReviewHelp).toBeVisible();

    // Verify accessible touch target (>= 44px effective target via pseudo-element)
    const helpHitTarget = await needsReviewHelp.evaluate(el => {
        const rect = el.getBoundingClientRect();
        const beforeStyle = window.getComputedStyle(el, '::before');
        const top = parseFloat(beforeStyle.top) || 0;
        const bottom = parseFloat(beforeStyle.bottom) || 0;
        const left = parseFloat(beforeStyle.left) || 0;
        const right = parseFloat(beforeStyle.right) || 0;
        return {
            effectiveW: rect.width + Math.abs(left) + Math.abs(right),
            effectiveH: rect.height + Math.abs(top) + Math.abs(bottom)
        };
    });
    expect(helpHitTarget.effectiveW).toBeGreaterThanOrEqual(44);
    expect(helpHitTarget.effectiveH).toBeGreaterThanOrEqual(44);

    const tooltipText = page.locator('#tooltip-text-needs-review');

    // Default period: Preliminary
    await expect(tooltipText).toContainText('no Preliminary results recorded or no current subjects found');

    // Click toggle
    await needsReviewHelp.click();
    await expect(tooltipText).toBeVisible();
    expect(await needsReviewHelp.evaluate(el => el.classList.contains('is-open'))).toBe(true);

    // Escape dismissal
    await page.keyboard.press('Escape');
    expect(await needsReviewHelp.evaluate(el => el.classList.contains('is-open'))).toBe(false);
    await page.mouse.move(0, 0);
    await expect(tooltipText).toBeHidden();

    // Hover reveals tooltip
    await needsReviewHelp.hover();
    await expect(tooltipText).toBeVisible();
    await page.mouse.move(0, 0);
    await expect(tooltipText).toBeHidden();

    // Dynamic update when changing period to Midterm
    await page.locator('#coverage-period').selectOption('midterm');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(tooltipText).toContainText('no Midterm results recorded or no current subjects found');

    // Dynamic update when changing period to Pre-Final
    await page.locator('#coverage-period').selectOption('prefinal');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(tooltipText).toContainText('no Pre-Final results recorded or no current subjects found');

    // Reset period back to prelim
    await page.locator('#coverage-period').selectOption('prelim');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await expect(tooltipText).toContainText('no Preliminary results recorded or no current subjects found');

    // 4. Prediction source select width and filter grid distribution at 1440px
    const sourceSelect = page.locator('#coverage-source');
    const sourceBox = await sourceSelect.boundingBox();
    expect(sourceBox.width).toBeGreaterThanOrEqual(240);

    // 5. Tablet 768px layout
    await page.setViewportSize({ width: 768, height: 900 });
    const filtersToolbar = page.locator('.coverage-filters');
    const filterLabels = filtersToolbar.locator('label');
    const label0Box = await filterLabels.nth(0).boundingBox();
    const label3Box = await filterLabels.nth(3).boundingBox();
    expect(label3Box.y).toBeGreaterThan(label0Box.y);

    // 6. Mobile 390px layout
    await page.setViewportSize({ width: 390, height: 900 });
    const label1Box = await filterLabels.nth(1).boundingBox();
    expect(label1Box.y).toBeGreaterThan(label0Box.y);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
});

test('[Restrained Motion & Conditional Auto-Scroll] detail animation, conditional scroll, and reduced motion', async ({ page }) => {
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);

    const getScrollState = async () => page.evaluate(() => {
        const main = document.querySelector('.main-content');
        if (main && (main.scrollHeight > main.clientHeight || main.scrollTop > 0)) {
            return { owner: 'main-content', top: main.scrollTop, left: main.scrollLeft };
        }
        return { owner: 'window', top: window.scrollY, left: window.scrollX };
    });

    const firstRow = page.locator('tr[data-coverage-record]').first();
    const firstToggle = firstRow.locator('.coverage-detail-toggle');
    const firstDetail = page.locator('#coverage-detail-1');

    // 1. Animation properties on inner grid
    await firstToggle.click();
    await expect(firstDetail).toBeVisible();
    const animName = await firstDetail.locator('.coverage-detail-grid').evaluate(el => getComputedStyle(el).animationName);
    const animDuration = await firstDetail.locator('.coverage-detail-grid').evaluate(el => parseFloat(getComputedStyle(el).animationDuration));
    expect(animName).toContain('coverageDetailFadeIn');
    expect(animDuration).toBeLessThanOrEqual(0.2); // ~150ms

    // 2. Summary row remains stationary
    const summaryBoxBefore = await firstRow.boundingBox();
    await page.waitForTimeout(160); // let animation settle
    const summaryBoxAfter = await firstRow.boundingBox();
    expect(summaryBoxAfter.y).toBe(summaryBoxBefore.y);
    expect(summaryBoxAfter.x).toBe(summaryBoxBefore.x);

    // 3. No auto-scroll when detail is already within viewport
    const scrollStateAfterFirstOpen = await getScrollState();
    expect(scrollStateAfterFirstOpen.top).toBe(0);
    expect(await firstToggle.evaluate(el => el === document.activeElement)).toBe(true);

    // 4. Closing detail does not auto-scroll and immediately hides content
    await firstToggle.click();
    await expect(firstDetail).toBeHidden();
    const scrollStateAfterClose = await getScrollState();
    expect(scrollStateAfterClose.top).toBe(0);
    expect(await firstToggle.evaluate(el => el === document.activeElement)).toBe(true);
    expect(await firstToggle.textContent()).toBe('View details');

    // 5. Conditional auto-scroll with animated smooth progression when detail panel opens below viewport
    await page.setViewportSize({ width: 1440, height: 600 });
    // Position the table row near the bottom of the viewport so its detail panel extends below the safe margin
    await page.evaluate(() => {
        const main = document.querySelector('.main-content');
        const row = document.querySelector('tr[data-coverage-record]');
        const rowRect = row.getBoundingClientRect();
        const mainRect = main.getBoundingClientRect();
        const currentRelativeTop = rowRect.top - mainRect.top;
        const desiredRelativeTop = mainRect.height - 90;
        main.scrollTop += (currentRelativeTop - desiredRelativeTop);
    });

    const targetRow = page.locator('tr[data-coverage-record]').first();
    const targetToggle = targetRow.locator('.coverage-detail-toggle');
    const targetDetailId = await targetToggle.getAttribute('aria-controls');
    const targetDetail = page.locator('#' + targetDetailId);

    // Record initial start position before opening detail panel
    const startScroll = await getScrollState();

    // Click to open detail panel
    await targetToggle.click();
    await expect(targetDetail).toBeVisible();

    // Sample intermediate scroll positions over time during smooth scroll animation
    await page.waitForTimeout(60);
    const intermediate1 = await getScrollState();

    await page.waitForTimeout(100);
    const intermediate2 = await getScrollState();

    // Wait for smooth scroll to finish settling (~400ms total)
    await page.waitForTimeout(350);
    const finalScroll = await getScrollState();

    // Confirm vertical scroll owner moved smoothly over time
    expect(finalScroll.owner).toBe('main-content');
    expect(finalScroll.top).toBeGreaterThan(startScroll.top);
    expect(intermediate1.top).toBeGreaterThanOrEqual(startScroll.top);
    expect(intermediate1.top).toBeLessThan(finalScroll.top);
    expect(intermediate2.top).toBeGreaterThanOrEqual(intermediate1.top);
    expect(finalScroll.left).toBe(startScroll.left); // No horizontal scroll

    // Focus remains on Hide details control
    expect(await targetToggle.evaluate(el => el === document.activeElement)).toBe(true);
    expect(await targetToggle.textContent()).toBe('Hide details');

    // Detail panel heading and opening content are visible in viewport
    const detailBox = await targetDetail.boundingBox();
    expect(detailBox.y).toBeLessThan(600);

    // Closing produces no scroll
    const posBeforeClose = await getScrollState();
    await targetToggle.click();
    await page.waitForTimeout(150);
    const posAfterClose = await getScrollState();
    expect(posAfterClose.top).toBe(posBeforeClose.top);
    expect(await targetToggle.textContent()).toBe('View details');

    // 6. Reduced-motion mode: immediate jump with no animated progression
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await open(page);
    await page.locator('tr[data-coverage-record]').first().waitFor();
    await page.evaluate(() => {
        const main = document.querySelector('.main-content');
        const row = document.querySelector('tr[data-coverage-record]');
        const rowRect = row.getBoundingClientRect();
        const mainRect = main.getBoundingClientRect();
        const currentRelativeTop = rowRect.top - mainRect.top;
        const desiredRelativeTop = mainRect.height - 90;
        main.scrollTop += (currentRelativeTop - desiredRelativeTop);
    });
    const redRow = page.locator('tr[data-coverage-record]').first();
    const redToggle = redRow.locator('.coverage-detail-toggle');
    const rmStart = await getScrollState();
    await redToggle.click();
    // After double rAF execution, position reaches final destination immediately
    await page.waitForTimeout(60);
    const rmFast = await getScrollState();
    await page.waitForTimeout(300);
    const rmFinal = await getScrollState();
    expect(rmFinal.top).toBeGreaterThan(rmStart.top);
    expect(rmFast.top).toBe(rmFinal.top);

    const redDetail = page.locator('#' + await redToggle.getAttribute('aria-controls'));
    const redAnimName = await redDetail.locator('.coverage-detail-grid').evaluate(el => getComputedStyle(el).animationName);
    expect(redAnimName).toBe('none');
    await page.emulateMedia({ reducedMotion: 'no-preference' });

    // 7. No row-by-row staggered animations
    const rowAnimDelay = await firstRow.evaluate(el => getComputedStyle(el).animationDelay);
    expect(['0s', '', undefined]).toContain(rowAnimDelay);

    // 8. No page-level horizontal overflow
    for (const width of [1440, 768, 390]) {
        await page.setViewportSize({ width, height: 900 });
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    }
});

test('[Evidence Screenshots] capture comprehensive visual evidence', async ({ page }) => {

    const evidenceDir = resolve('test-results/evidence/admin-coverage');
    mkdirSync(evidenceDir, { recursive: true });

    // 1. closed-rows-1440.png, closed-details.png & closed-compact-rows.png
    await page.setViewportSize({ width: 1440, height: 900 });
    await open(page);
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'closed-rows-1440.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'closed-details.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'closed-compact-rows.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'compact-table-rows.png') });

    // 2. program-analytics-table-reference.png
    await open(page, 'analytics_reference');
    await page.locator('.main-content .card').screenshot({ path: resolve(evidenceDir, 'program-analytics-table-reference.png') });
    await open(page); // restore coverage page

    // 3. view-details-resting.png
    const firstRow = page.locator('tr[data-coverage-record]').first();
    const firstToggle = firstRow.locator('.coverage-detail-toggle');
    await firstRow.screenshot({ path: resolve(evidenceDir, 'view-details-resting.png') });

    // 4. view-details-hover.png
    await firstToggle.hover();
    await firstRow.screenshot({ path: resolve(evidenceDir, 'view-details-hover.png') });

    // 5. view-details-focus.png & view-details-keyboard-focus.png
    await firstToggle.focus();
    await firstRow.screenshot({ path: resolve(evidenceDir, 'view-details-focus.png') });
    await firstRow.screenshot({ path: resolve(evidenceDir, 'view-details-keyboard-focus.png') });

    // 6. expanded-row-hide-details.png, expanded-row-neutral-summary.png & fully-opened-detail-panel.png
    await firstToggle.click(); // opens detail row
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'expanded-row-neutral-summary.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'fully-opened-detail-panel.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'no-auto-scroll-needed.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'no-scroll-required.png') });
    await firstRow.screenshot({ path: resolve(evidenceDir, 'expanded-row-hide-details.png') });
    await firstRow.screenshot({ path: resolve(evidenceDir, 'hide-details-active-state.png') });

    // Mid-animation capture during opening
    await firstToggle.click(); // close
    const animPromise = page.locator('#coverage-detail-1').screenshot({ path: resolve(evidenceDir, 'opening-detail-panel.png') }).catch(() => {});
    await firstToggle.click(); // open
    await animPromise;

    // Auto-scroll three-point motion sequence: start, intermediate, final
    await page.setViewportSize({ width: 1440, height: 600 });
    await open(page);
    await page.evaluate(() => {
        const main = document.querySelector('.main-content');
        const row = document.querySelector('tr[data-coverage-record]');
        const rowRect = row.getBoundingClientRect();
        const mainRect = main.getBoundingClientRect();
        const currentRelativeTop = rowRect.top - mainRect.top;
        const desiredRelativeTop = mainRect.height - 90;
        main.scrollTop += (currentRelativeTop - desiredRelativeTop);
    });
    const lastRowEv = page.locator('tr[data-coverage-record]').first();
    const lastToggleEv = lastRowEv.locator('.coverage-detail-toggle');
    await page.screenshot({ path: resolve(evidenceDir, 'scroll-start.png') });

    await lastToggleEv.click();
    await page.waitForTimeout(90);
    await page.screenshot({ path: resolve(evidenceDir, 'scroll-intermediate.png') });

    await page.waitForTimeout(350);
    await page.screenshot({ path: resolve(evidenceDir, 'scroll-final.png') });
    await page.screenshot({ path: resolve(evidenceDir, 'auto-scroll-needed.png') });
    await page.setViewportSize({ width: 1440, height: 900 });

    // Reduced-motion open state
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await open(page);
    const rmToggle = page.locator('tr[data-coverage-record]').first().locator('.coverage-detail-toggle');
    await rmToggle.click();
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'reduced-motion-open.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'reduced-motion-detail-open.png') });
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await open(page);

    // 7. full-width-details-1440.png
    const detailRowOne = page.locator('#coverage-detail-1');
    const toggleOne = page.locator('tr[data-coverage-record]').first().locator('.coverage-detail-toggle');
    await toggleOne.click();
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'full-width-details-1440.png') });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'details-desktop-1440.png') });

    // 8. full-width-details-768.png & tablet-table-768.png
    await page.setViewportSize({ width: 768, height: 900 });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'full-width-details-768.png') });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'details-tablet-768.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'tablet-table-768.png') });

    // 9. stacked-details-390.png, narrow-table-390.png & narrow-width-details.png
    await page.setViewportSize({ width: 390, height: 900 });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'stacked-details-390.png') });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'details-mobile-390.png') });
    await detailRowOne.screenshot({ path: resolve(evidenceDir, 'narrow-width-details.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'narrow-table-390.png') });

    // 10. dark-closed-rows.png, dark-expanded-row.png & dark-theme-details.png
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => document.documentElement.dataset.theme = 'dark');
    await toggleOne.click(); // close detail row
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'dark-closed-rows.png') });

    await toggleOne.click(); // open detail row
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'dark-expanded-row.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'dark-table-details.png') });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'dark-theme-details.png') });
    await page.evaluate(() => document.documentElement.dataset.theme = 'light');
    await toggleOne.click(); // close detail row


    // Empty state visual evidence
    await page.evaluate(() => document.documentElement.dataset.theme = 'light');
    await page.locator('#coverage-source').selectOption('unknown');
    await page.getByRole('button', { name: 'Apply filters' }).click();

    // 11. empty-state-light-1440.png
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'empty-state-light-1440.png') });

    // 12. empty-state-dark-1440.png
    await page.evaluate(() => document.documentElement.dataset.theme = 'dark');
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'empty-state-dark-1440.png') });

    // 13. empty-state-tablet-768.png
    await page.setViewportSize({ width: 768, height: 900 });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'empty-state-tablet-768.png') });

    // 14. empty-state-mobile-390.png
    await page.setViewportSize({ width: 390, height: 900 });
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'empty-state-mobile-390.png') });

    // 15. empty-state-clear-focus.png
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => document.documentElement.dataset.theme = 'light');
    const emptyClear = page.locator('#coverage-empty-clear');
    await emptyClear.focus();
    await page.locator('#coverage-empty').screenshot({ path: resolve(evidenceDir, 'empty-state-clear-focus.png') });

    // 16. empty-state-restored-results.png
    await emptyClear.click();
    await page.locator('#coverage-results').screenshot({ path: resolve(evidenceDir, 'empty-state-restored-results.png') });

    // 17. empty-state-zero-source.png
    await page.locator('#coverage-source').selectOption('unknown');
    await page.getByRole('button', { name: 'Apply filters' }).click();
    await page.locator('.coverage-sources-section').screenshot({ path: resolve(evidenceDir, 'empty-state-zero-source.png') });

    // 18. empty-state-table-bottom-leading-to-sources.png
    await page.locator('.coverage-table-region').screenshot({ path: resolve(evidenceDir, 'empty-state-table-bottom-leading-to-sources.png') });

    // Preserved tooltips and filter headers
    await page.getByRole('button', { name: 'Clear filters' }).first().click();
    const resultsHelp = page.locator('#help-tooltip-results');
    await resultsHelp.click();
    await page.locator('.coverage-table-region').screenshot({ path: resolve(evidenceDir, 'tooltip-available-results.png') });
    await page.keyboard.press('Escape');

    const infoHelp = page.locator('#help-tooltip-info');
    await infoHelp.click();
    await page.locator('.coverage-table-region').screenshot({ path: resolve(evidenceDir, 'tooltip-grade-info.png') });
    await page.keyboard.press('Escape');

    const predHelp = page.locator('#help-tooltip-prediction');
    await predHelp.click();
    await page.locator('.coverage-table-region').screenshot({ path: resolve(evidenceDir, 'tooltip-saved-prediction.png') });
    await page.keyboard.press('Escape');

    await page.locator('.coverage-methodology').screenshot({ path: resolve(evidenceDir, 'collapsed-methodology.png') });
    await page.locator('.coverage-methodology > summary').click();
    await page.locator('.coverage-methodology').screenshot({ path: resolve(evidenceDir, 'expanded-methodology.png') });
    await page.locator('.coverage-methodology > summary').click();

    await page.locator('#coverage-filters').screenshot({ path: resolve(evidenceDir, 'filter-header-compact.png') });
    await page.locator('.coverage-sources-strip').screenshot({ path: resolve(evidenceDir, 'source-strip-bottom.png') });

    // 19. Polish pass screenshots
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => document.documentElement.dataset.theme = 'light');
    await page.locator('.coverage-notice').screenshot({ path: resolve(evidenceDir, 'compact-prototype-panel-1440.png') });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'compact-kpi-cards-1440.png') });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'compact-kpi-cards.png') });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'four-kpi-cards-1440.png') });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'needs-review-tooltip-closed.png') });

    const needsHelp = page.locator('#help-tooltip-needs-review');
    await needsHelp.click();
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'needs-review-tooltip-open.png') });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'needs-review-tooltip.png') });
    await page.keyboard.press('Escape');

    await page.locator('#coverage-filters').screenshot({ path: resolve(evidenceDir, 'balanced-filter-fields-1440.png') });

    await page.setViewportSize({ width: 768, height: 900 });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'kpi-layout-768.png') });
    await page.locator('#coverage-filters').screenshot({ path: resolve(evidenceDir, 'filter-controls-768.png') });

    await page.setViewportSize({ width: 390, height: 900 });
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'kpi-layout-390.png') });
    await page.locator('#coverage-filters').screenshot({ path: resolve(evidenceDir, 'filter-controls-390.png') });

    await page.setViewportSize({ width: 1440, height: 900 });
    await page.evaluate(() => document.documentElement.dataset.theme = 'dark');
    await page.locator('.coverage-metrics').screenshot({ path: resolve(evidenceDir, 'dark-theme-kpi-cards.png') });
    await page.locator('#coverage-page').screenshot({ path: resolve(evidenceDir, 'dark-compact-panel-kpis.png'), clip: { x: 0, y: 0, width: 1440, height: 350 } });

    await page.evaluate(() => document.documentElement.dataset.theme = 'light');
    await page.locator('#coverage-page').screenshot({ path: resolve(evidenceDir, 'complete-page-top-to-filters.png'), clip: { x: 0, y: 0, width: 1440, height: 500 } });
});
