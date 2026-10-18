import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';

// Focused browser test for guided vertical workflow student planning screen (PEF/CC).
const root = process.cwd();
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const htmlInitial = execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', 'provisional'], { cwd: root, encoding: 'utf8' });
const htmlPreview = execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', 'provisional-preview'], { cwd: root, encoding: 'utf8' });

for (const theme of ['light', 'dark']) for (const width of [390, 1440]) {
  test(`[Guided Workflow UI] initial action screen, ${theme}, ${width}px`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setViewportSize({ width, height: 844 });
    await page.addInitScript(t => localStorage.setItem('theme', t), theme);

    await page.route('**/*', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/udm-radar/student/enrolment_planning.php') {
        return route.fulfill({ contentType: 'text/html', body: htmlInitial });
      }
      if (path.startsWith('/udm-radar/assets/')) {
        const local = resolve(root, path.slice('/udm-radar/'.length));
        if (!local.startsWith(resolve(root, 'assets') + sep)) return route.abort();
        try {
          return await route.fulfill({
            body: readFileSync(local),
            contentType: local.endsWith('.css') ? 'text/css' : local.endsWith('.js') ? 'text/javascript' : 'application/octet-stream',
          });
        } catch {
          return route.abort();
        }
      }
      return route.abort();
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);

    // 1. Heading and intro lead sentence
    const heading = page.locator('h1');
    await expect(heading).toHaveText('Enrolment Planning');
    const lead = page.locator('.pef-cc-lead');
    await expect(lead).toHaveText('Review your current semester, check the proposed next-semester plan, and create your planning workbook.');

    // 2. Stepper progress
    const stepper = page.locator('.pef-cc-stepper');
    await expect(stepper).toBeVisible();
    await expect(stepper).toContainText('Current semester');
    await expect(stepper).toContainText('Next-term plan');
    await expect(stepper).toContainText('Workbook');

    // 3. Status banner
    const banner = page.locator('.pef-cc-banner');
    await expect(banner).toBeVisible();
    await expect(banner.locator('h2')).toHaveText('Provisional planning available');
    await expect(page.getByTestId('planning-status')).toHaveText('Provisional');

    // 4. Three sequential full-width sections render
    const panelCurrent = page.getByTestId('panel-current-semester');
    const panelNext = page.getByTestId('panel-next-plan');
    const panelWorkbook = page.getByTestId('panel-workbook');
    await expect(panelCurrent).toBeVisible();
    await expect(panelNext).toBeVisible();
    await expect(panelWorkbook).toBeVisible();

    // 5. Step 1: Current semester
    await expect(panelCurrent.locator('h2')).toHaveText('1. Current semester');
    const viewCurrentBtn = page.getByTestId('view-current-semester-btn');
    await expect(viewCurrentBtn).toHaveText('View recorded subjects');
    const currentDetails = page.getByTestId('current-semester-details');
    await expect(currentDetails).toBeHidden();
    await viewCurrentBtn.click();
    await expect(currentDetails).toBeVisible();
    await expect(viewCurrentBtn).toHaveText('Hide recorded subjects');
    await viewCurrentBtn.click();
    await expect(currentDetails).toBeHidden();
    await expect(viewCurrentBtn).toHaveText('View recorded subjects');

    // 6. Step 2: Next-semester plan
    await expect(panelNext.locator('h2')).toHaveText('2. Next-semester plan');
    const reviewNextBtn = page.getByTestId('review-next-plan-btn');
    await expect(reviewNextBtn).toHaveText('View proposed subjects');
    const nextDetails = page.getByTestId('next-plan-details');
    await expect(nextDetails).toBeHidden();
    await reviewNextBtn.click();
    await expect(nextDetails).toBeVisible();
    await expect(reviewNextBtn).toHaveText('Hide proposed subjects');
    await reviewNextBtn.click();
    await expect(nextDetails).toBeHidden();
    await expect(reviewNextBtn).toHaveText('View proposed subjects');

    // 7. Step 3: Planning workbook (before preview)
    await expect(panelWorkbook.locator('h2')).toHaveText('3. Create planning workbook');
    const previewBtn = page.getByTestId('preview-button');
    await expect(previewBtn).toBeVisible();
    await expect(previewBtn).toHaveText('Generate workbook preview');
    await expect(page.getByTestId('download-button')).toHaveCount(0);

    // 8. Single consolidated disclosure
    const disclosure = page.getByTestId('single-disclosure');
    await expect(disclosure).toBeVisible();
    await expect(disclosure).toContainText('This workbook is a planning aid for academic evaluator review and does not constitute official enrolment approval.');

    // 9. Absence of old verbose items from default flow
    await expect(page.getByTestId('cc-preview')).toHaveCount(0);
    await expect(page.locator('.pef-cc-boundary')).toHaveCount(0);

    // 10. Previous academic history is collapsed by default
    const history = page.getByTestId('pef-cc-history');
    await expect(history).toBeVisible();
    expect(await history.getAttribute('open')).toBeNull();
    await history.locator('summary').click();
    expect(await history.getAttribute('open')).not.toBeNull();
    await expect(history).toContainText('Standing is derived using the approved UDM non-board academic-standing rule.');

    // 11. No raw null or internal status strings in visible text
    const bodyText = await page.innerText('main');
    expect(bodyText).not.toMatch(/\bnull\b/i);
    expect(bodyText).not.toMatch(/\b(CMP|CUR|REVIEW)\b/);

    // 12. Non-punitive language verification
    expect(bodyText).not.toMatch(/\b(strike|dismissal|punitive)\b/i);

    // 13. Responsive layout integrity (no horizontal scroll overflow)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);

    // 14. No JavaScript errors
    expect(errors).toEqual([]);
  });

  test(`[Guided Workflow UI] preview generated view, ${theme}, ${width}px`, async ({ page }) => {
    const errors = [];
    page.on('pageerror', e => errors.push(e.message));
    await page.setViewportSize({ width, height: 844 });
    await page.addInitScript(t => localStorage.setItem('theme', t), theme);

    await page.route('**/*', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/udm-radar/student/enrolment_planning.php') {
        return route.fulfill({ contentType: 'text/html', body: htmlPreview });
      }
      if (path.startsWith('/udm-radar/assets/')) {
        const local = resolve(root, path.slice('/udm-radar/'.length));
        if (!local.startsWith(resolve(root, 'assets') + sep)) return route.abort();
        try {
          return await route.fulfill({
            body: readFileSync(local),
            contentType: local.endsWith('.css') ? 'text/css' : local.endsWith('.js') ? 'text/javascript' : 'application/octet-stream',
          });
        } catch {
          return route.abort();
        }
      }
      return route.abort();
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);

    // 1. Concise workbook preview summary is visible
    const pefPreview = page.getByTestId('pef-preview');
    await expect(pefPreview).toBeVisible();
    await expect(pefPreview.locator('.pef-cc-preview-ready-badge')).toHaveText('Preview ready');
    await expect(pefPreview).toContainText('Student');
    await expect(pefPreview).toContainText('Evaluation term');
    await expect(pefPreview).toContainText('Next planning term');
    await expect(pefPreview).toContainText('Suggested section');
    await expect(pefPreview).toContainText('Proposed subjects');
    await expect(pefPreview).toContainText('Workbook sheets');
    await expect(pefPreview).toContainText('Planning mode');

    // 2. Download and Refresh buttons present
    const downloadBtn = page.getByTestId('download-button');
    await expect(downloadBtn).toBeVisible();
    await expect(downloadBtn).toHaveText('Download provisional workbook');
    const refreshBtn = page.getByTestId('refresh-preview-button');
    await expect(refreshBtn).toBeVisible();
    await expect(refreshBtn).toHaveText('Refresh preview');

    // 3. Full PEF preview table is absent from default flow
    await expect(page.locator('.pef-cc-recorded-table')).toHaveCount(0);

    // 4. Full CC preview remains completely absent
    await expect(page.getByTestId('cc-preview')).toHaveCount(0);

    // 5. Responsive layout integrity
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    expect(errors).toEqual([]);
  });
}
