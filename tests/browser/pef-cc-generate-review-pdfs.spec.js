import { test, expect } from '@playwright/test';
import { readFileSync, statSync } from 'node:fs';

const stateFile = process.env.PEF_CC_STATE_FILE;
const state = stateFile ? JSON.parse(readFileSync(stateFile, 'utf8')) : null;
test.skip(!state, 'Requires guarded scratch-only pef_cc_test_server.mjs state; never normal Apache/live URL.');
function requireScratch() {
  if (process.env.DB_NAME !== 'udm_radar_scratch' || state?.fixture.database !== 'udm_radar_scratch'
      || !/^http:\/\/127\.0\.0\.1:\d+\/udm-radar\/$/.test(state?.base_url || '')) {
    throw new Error('DATABASE SAFETY BLOCKED: explicit guarded scratch state required');
  }
}
async function login(page, account) {
  requireScratch();
  await page.goto(state.base_url + 'login.php');
  await page.fill('#userid', state.fixture.accounts[account].login);
  await page.fill('#password', state.fixture.password);
  await Promise.all([page.waitForURL('**/student/index.php'), page.getByRole('button', { name: 'Login to Portal' }).click()]);
  await page.goto(state.base_url + 'student/enrolment_planning.php');
}
async function assertPdfDownload(page, button, testInfo, outputName) {
  const downloadPromise = page.waitForEvent('download');
  await button.click();
  const download = await downloadPromise;
  expect(download.suggestedFilename()).toBe('UDM-RADAR-PEF-CC.pdf');
  const targetPath = testInfo.outputPath(outputName);
  await download.saveAs(targetPath);
  const stat = statSync(targetPath);
  expect(stat.size).toBeGreaterThan(1000);
  const bytes = readFileSync(targetPath);
  expect(bytes.subarray(0, 5).toString('ascii')).toBe('%PDF-');
  expect(bytes.toString('latin1').match(/\/Type\s*\/Page\b/g)).toHaveLength(2);
  return stat.size;
}

test.describe('Real Apache PDF Generation & Download Audit', () => {
  test.setTimeout(90000); // Allow adequate time for Excel COM rendering

  test('generates and downloads real Normal PDF via guarded server', async ({ page }, testInfo) => {
    await login(page, 'regular1');
    // Verify State A banner
    const banner = page.locator('.pef-cc-banner');
    await expect(banner).toBeVisible();
    await expect(banner.locator('h2')).toHaveText('Planning PDF available');
    await expect(page.getByTestId('planning-status')).toHaveText('Normal');

    // 3. Generate preview (Step 2 -> Step 3)
    const previewBtn = page.getByTestId('preview-button');
    await expect(previewBtn).toBeEnabled();
    await previewBtn.click();

    // 4. Verify Step 3 is visible with download form
    const downloadBtn = page.getByTestId('download-button');
    await expect(downloadBtn).toBeVisible();
    await expect(downloadBtn).toHaveText('Download planning PDF');

    const size = await assertPdfDownload(page, downloadBtn, testInfo, 'UDM-RADAR-PEF-CC-normal.pdf');
    console.log(`Normal PDF verified: ${size} bytes, 2 pages`);
  });

  test('generates and downloads real Provisional PDF via guarded server', async ({ page }, testInfo) => {
    await login(page, 'regular3');
    // Verify State B banner
    const banner = page.locator('.pef-cc-banner');
    await expect(banner).toBeVisible();
    await expect(banner.locator('h2')).toHaveText(/Provisional planning (PDF )?available/);
    await expect(page.getByTestId('planning-status')).toHaveText('Provisional');

    // 3. Generate preview
    const previewBtn = page.getByTestId('preview-button');
    await expect(previewBtn).toBeEnabled();
    await previewBtn.click();

    // 4. Verify Step 3 is visible with provisional download form
    const downloadBtn = page.getByTestId('download-button');
    await expect(downloadBtn).toBeVisible();
    await expect(downloadBtn).toHaveText('Download provisional planning PDF');

    const size = await assertPdfDownload(page, downloadBtn, testInfo, 'UDM-RADAR-PEF-CC-provisional.pdf');
    console.log(`Provisional PDF verified: ${size} bytes, 2 pages`);
  });

  test('generates and downloads real Review-Required PDF via guarded server', async ({ page }, testInfo) => {
    await login(page, 'regular_missing');
    // Verify banner is visible
    const banner = page.locator('.pef-cc-banner');
    await expect(banner).toBeVisible();

    // 3. Generate preview
    const previewBtn = page.getByTestId('preview-button');
    await expect(previewBtn).toBeEnabled();
    await previewBtn.click();

    // 4. Verify Step 3 is visible with download form
    const downloadBtn = page.getByTestId('download-button');
    await expect(downloadBtn).toBeVisible();
    await expect(downloadBtn).toHaveText(/Download (review |provisional )?planning PDF/);

    await expect(banner.locator('h2')).toHaveText('Planning PDF available for evaluator review');
    await expect(page.getByTestId('planning-status')).toHaveText('Review required');
    const size = await assertPdfDownload(page, downloadBtn, testInfo, 'UDM-RADAR-PEF-CC-review.pdf');
    console.log(`Review-Required PDF verified: ${size} bytes, 2 pages`);
  });
});
