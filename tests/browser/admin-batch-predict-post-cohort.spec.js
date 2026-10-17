/**
 * tests/browser/admin-batch-predict-post-cohort.spec.js
 * Browser E2E Validation for Admin Batch Predict Flow (BATCH-PREDICT-POST-COHORT-WP1)
 *
 * Verifies:
 * 1. Admin login and navigation to admin/index.php.
 * 2. Presence of Batch Predict button and aria-live="polite" role="status" container.
 * 3. Immediate disabled state and "Running Batch Predict…" label on activation.
 * 4. Duplicate click prevention: double-click dispatches exactly 1 HTTP request.
 * 5. Indeterminate spinner and honest synchronous processing copy.
 * 6. Structured success completion summary (total, evaluated, skipped, cases created).
 * 7. Controlled failure handling with accessible error notice and button restoration.
 * 8. Mobile responsiveness: no horizontal overflow at 390px viewport width.
 */

import { test, expect } from '@playwright/test';
import { spawn } from 'node:child_process';
import { resolve } from 'node:path';
import process from 'node:process';

const phpBinary = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const rootDir = resolve('.');
const testPort = 8089;
const baseUrl = `http://127.0.0.1:${testPort}`;

let phpServerProcess = null;

test.describe.serial('Admin Batch Predict Flow and Interaction Verification', () => {

    test.beforeAll(async () => {
        // Launch PHP built-in web server bound to udm_radar_scratch
        phpServerProcess = spawn(phpBinary, ['-S', `127.0.0.1:${testPort}`], {
            cwd: rootDir,
            env: {
                ...process.env,
                DB_NAME: 'udm_radar_scratch',
            },
            stdio: 'ignore',
        });

        // Wait for server to become responsive
        let connected = false;
        for (let i = 0; i < 30; i++) {
            try {
                const res = await fetch(`${baseUrl}/login.php`);
                if (res.status === 200) {
                    connected = true;
                    break;
                }
            } catch {
                // Wait and retry
            }
            await new Promise(r => setTimeout(r, 200));
        }

        if (!connected) {
            throw new Error(`Failed to connect to local PHP server on port ${testPort}`);
        }
    });

    test.afterAll(() => {
        if (phpServerProcess) {
            phpServerProcess.kill();
        }
    });

    async function loginAsAdmin(page) {
        await page.goto(`${baseUrl}/login.php`);
        await page.fill('#userid', 'admin');
        await page.fill('#password', '123456');
        await page.click('.btn-login');
        await page.waitForURL('**/admin/index.php');
    }

    test('1. Admin dashboard renders Batch Predict button and status region with accessible semantics', async ({ page }) => {
        await loginAsAdmin(page);

        const btn = page.locator('#runBatchBtn');
        await expect(btn).toBeVisible();
        await expect(btn).toBeEnabled();
        await expect(btn).toContainText('Run Predictions');

        const batchCard = page.locator('.card', { has: page.locator('#runBatchBtn') });
        await expect(batchCard).toContainText('Prediction basis: recorded academic history and current Prelim grades.');
        await expect(batchCard).toContainText('Midterm records are available for academic review but are not included in the current trained prediction model.');

        const statusRegion = page.locator('#batchPredictStatus');
        await expect(statusRegion).toHaveAttribute('role', 'status');
        await expect(statusRegion).toHaveAttribute('aria-live', 'polite');
        await expect(statusRegion).toBeHidden();
    });

    test('2. Activating Batch Predict immediately disables action, prevents duplicate requests, and shows honest copy', async ({ page }) => {
        await loginAsAdmin(page);

        let batchRequestsCount = 0;
        page.on('request', req => {
            if (req.url().includes('batch_predict.php') && req.method() === 'POST') {
                batchRequestsCount++;
            }
        });

        const btn = page.locator('#runBatchBtn');
        const statusRegion = page.locator('#batchPredictStatus');

        // Click the button
        await btn.click();

        // Immediate feedback checks
        await expect(btn).toBeDisabled();
        await expect(btn).toHaveAttribute('aria-disabled', 'true');
        await expect(btn).toContainText('Running Batch Predict…');
        await expect(btn.locator('.batch-spinner')).toBeVisible();

        // Status region is displayed with honest synchronous explanation
        await expect(statusRegion).toBeVisible();
        await expect(statusRegion).toContainText('Batch prediction is running. Keep this page open while UDM-RADAR processes the eligible student records.');

        // Attempt a rapid second click and Enter key activation while running
        await btn.click({ force: true }).catch(() => {});
        await btn.press('Enter').catch(() => {});

        // Await the batch completion summary
        await expect(statusRegion).toContainText('Batch Prediction Complete', { timeout: 15000 });
        await expect(statusRegion).toContainText('Processed 306 students (305 evaluated, 1 skipped for insufficient data)');

        // Prove exactly 1 request was dispatched
        expect(batchRequestsCount).toBe(1);
    });

    test('3. Controlled server-side error shows clear accessible notice and safely restores button', async ({ page }) => {
        await loginAsAdmin(page);

        // Intercept batch_predict.php to simulate a 429 lock response
        await page.route('**/api/batch_predict.php', async route => {
            await route.fulfill({
                status: 429,
                contentType: 'application/json',
                body: JSON.stringify({
                    status: 'error',
                    error: 'A batch prediction is already running. Please wait.'
                })
            });
        });

        const btn = page.locator('#runBatchBtn');
        const statusRegion = page.locator('#batchPredictStatus');

        await btn.click();

        // Verify error is shown in inline status region
        await expect(statusRegion).toBeVisible();
        await expect(statusRegion).toContainText('A batch prediction is already running');

        // Verify button is safely restored so user is not stuck
        await expect(btn).toBeEnabled();
        await expect(btn).toContainText('Run Predictions');
        await expect(btn).not.toHaveAttribute('aria-disabled');
    });

    test('4. Mobile viewport (390px) retains readable status without horizontal overflow', async ({ page }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await loginAsAdmin(page);

        const btn = page.locator('#runBatchBtn');
        await expect(btn).toBeVisible();

        const btnBox = await btn.boundingBox();
        expect(btnBox).not.toBeNull();
        expect(btnBox.x + btnBox.width).toBeLessThanOrEqual(390);

        const statusRegion = page.locator('#batchPredictStatus');
        await page.evaluate(() => {
            const s = document.getElementById('batchPredictStatus');
            s.style.display = 'block';
            s.textContent = 'Batch prediction is running. Keep this page open while UDM-RADAR processes the eligible student records.';
        });

        await expect(statusRegion).toBeVisible();
        const statusBox = await statusRegion.boundingBox();
        expect(statusBox).not.toBeNull();
        expect(statusBox.x + statusBox.width).toBeLessThanOrEqual(390);

        // Confirm prefers-reduced-motion rule exists for batch-spinner
        const reducedMotionConfigured = await page.evaluate(() => {
            for (const sheet of document.styleSheets) {
                try {
                    for (const rule of sheet.cssRules) {
                        if (rule.media && rule.media.mediaText.includes('prefers-reduced-motion') && rule.cssText.includes('.batch-spinner')) {
                            return true;
                        }
                    }
                } catch { /* cross-origin */ }
            }
            return false;
        });
        expect(reducedMotionConfigured).toBe(true);

        const hasHorizontalOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > window.innerWidth;
        });
        expect(hasHorizontalOverflow).toBe(false);
    });
});
