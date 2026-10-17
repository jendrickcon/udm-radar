import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';

// Generated actual PHP page markup. No login, server, fixture DML or database.
const root = process.cwd();
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const render = kind => execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', kind], { cwd: root, encoding: 'utf8' });
const html = Object.fromEntries(['normal', 'record', 'proposed', 'identity'].map(kind => [kind, render(kind)]));
for (const theme of ['light', 'dark']) for (const width of [390, 1440]) for (const kind of ['normal', 'record', 'proposed', 'identity']) {
  test(`[Generated static HTML] ${kind} print contract, ${theme}, ${width}`, async ({ page }) => {
    const errors = []; page.on('pageerror', e => errors.push(e.message));
    await page.setViewportSize({ width, height: 844 });
    await page.addInitScript(t => localStorage.setItem('theme', t), theme);
    await page.route('**/*', async route => {
      const path = new URL(route.request().url()).pathname;
      if (path === '/udm-radar/student/enrolment_planning.php') return route.fulfill({ contentType: 'text/html', body: html[kind] });
      if (path.startsWith('/udm-radar/assets/')) {
        const local = resolve(root, path.slice('/udm-radar/'.length));
        if (!local.startsWith(resolve(root, 'assets') + sep)) return route.abort();
        try { return await route.fulfill({ body: readFileSync(local), contentType: local.endsWith('.css') ? 'text/css' : local.endsWith('.js') ? 'text/javascript' : 'application/octet-stream' }); }
        catch { return route.abort(); }
      }
      return route.abort();
    });
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    await expect(page.locator('#proposed-section')).toHaveValue('IT-32');
    expect(await page.locator('#pef-cc-subjects').evaluate(e => e.selectedOptions.length)).toBe(2);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await expect(page.getByTestId('cc-preview').locator('[aria-invalid]')).toHaveCount(0);
    if (kind === 'normal') {
      await expect(page.getByTestId('download-button')).toBeEnabled();
      await expect(page.locator('.pef-cc-print-invalid')).toHaveCount(0);
    } else {
      await expect(page.locator('#pef-print-limit-explanation')).toContainText('exceeds the printable form limit');
      await expect(page.getByTestId('download-button')).toHaveCount(0);
      const marked = page.locator('.pef-cc-print-invalid').first();
      await expect(marked).toHaveAttribute('aria-invalid', 'true');
      await expect(marked).toHaveAttribute('aria-describedby', 'pef-print-limit-explanation');
      expect(await marked.evaluate(e => getComputedStyle(e).outlineWidth)).toBe('2px');
      await page.getByRole('link', { name: 'Reload planning records', exact: true }).focus();
      await expect(page.getByRole('link', { name: 'Reload planning records', exact: true })).toBeFocused();
    }
    expect(errors).toEqual([]);
  });
}
