import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { readFileSync } from 'node:fs';

// Generated static HTML from actual PHP fragments; no authenticated portal coverage.
for (const [scenario, value] of Object.entries({ 'all-inc': 'N/A', 'no-records': 'N/A', mixed: '2.50', zero: 'N/A', 'numeric-zero-result': '0.00' })) {
    for (const theme of ['light', 'dark']) {
        for (const width of [390, 1440]) {
            test(`[Generated static HTML] ${scenario}, ${theme}, ${width}`, async ({ page }) => {
                const fixture = JSON.parse(execFileSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', [resolve('tests/fixtures/student_gwa_render.php'), scenario], { encoding: 'utf8' }));
                const errors = [];
                page.on('pageerror', error => errors.push(error.message));
                page.on('console', message => { if (message.type() === 'error') errors.push(message.text()); });
                page.on('requestfailed', request => errors.push(`Failed request: ${request.url()}`));
                page.on('response', response => { if (response.status() >= 400) errors.push(`HTTP ${response.status()}: ${response.url()}`); });
                await page.setViewportSize({ width, height: 900 });
                const css = readFileSync(resolve('assets/css/dashboard.css'), 'utf8');
                await page.setContent(`<html data-theme="${theme}"><head><style>${css}</style></head><body>${fixture.html}<script>${fixture.script}\nwindow.needleLookups=0; needlePlugin.afterDraw({getDatasetMeta:()=>{window.needleLookups++;return {data:[]}}});</script></body></html>`);
                await expect(page.locator('#gwa-card-value')).toHaveText(value);
                await expect(page.locator('#gwa-gauge-value')).toHaveText(value);
                await expect(page.locator('#gwa-card-value')).toBeVisible();
                await expect(page.locator('#gwa-gauge-value')).toBeVisible();
                await expect(page.locator('#honorGauge')).toHaveAttribute('aria-label', `Cumulative GWA: ${value}`);
                if (value === 'N/A') {
                    await expect(page.locator('#gwa-gauge-description')).toHaveText('No numeric final grades are currently available.');
                    await expect(page.getByText(/High Risk/)).toHaveCount(0);
                    expect(await page.evaluate(() => window.needleLookups)).toBe(0);
                } else {
                    expect(await page.evaluate(() => window.needleLookups)).toBe(1);
                }
                expect(errors).toEqual([]);
            });
        }
    }
}
