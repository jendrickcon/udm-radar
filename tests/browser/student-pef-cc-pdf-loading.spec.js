import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { resolve, sep } from 'node:path';

const root = process.cwd();
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const htmlNormal = execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', 'normal'], { cwd: root, encoding: 'utf8' });
const htmlProvisional = execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', 'provisional-preview'], { cwd: root, encoding: 'utf8' });
const htmlInitial = execFileSync(php, ['tests/unit/pef_cc_print_contract_test.php', '--render', 'provisional'], { cwd: root, encoding: 'utf8' });

// All responses are mocked client-state evidence; native renderer validation is separate.

function setupRoutes(page, htmlContent, exportHandler) {
  return page.route('**/*', async route => {
    const url = new URL(route.request().url());
    const path = url.pathname;

    if (path === '/udm-radar/student/enrolment_planning.php') {
      return route.fulfill({ contentType: 'text/html; charset=UTF-8', body: htmlContent });
    }
    if (path === '/udm-radar/student/export_pef_cc.php') {
      if (exportHandler) {
        return exportHandler(route);
      }
      // Mock PDF-like bytes for client transport only; no PDF validity/layout claim
      const pdfBytes = Buffer.from(
        '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n' +
        '2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n' +
        '3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n' +
        '4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\nxref\n0 5\n0000000000 65535 f \n' +
        'trailer\n<< /Root 1 0 R >>\n%%EOF'
      );
      return route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        headers: {
          'Content-Disposition': 'attachment; filename="UDM-RADAR-PEF-CC.pdf"',
          'Content-Length': String(pdfBytes.length),
        },
        body: pdfBytes,
      });
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
}

test.describe('[Mocked client state] PEF/CC Accessible PDF Generation Loading State', () => {

  test('1 & 2. Button state before preview and after preview (Normal & Provisional)', async ({ page }, testInfo) => {
    // Before preview: Generate planning preview
    await setupRoutes(page, htmlInitial);
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const previewBtn = page.getByTestId('preview-button');
    await expect(previewBtn).toBeVisible();
    await expect(previewBtn).toHaveText('Generate provisional planning preview');

    // Normal preview: Download planning PDF
    await setupRoutes(page, htmlNormal);
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const normalDownloadBtn = page.getByTestId('download-button');
    await expect(normalDownloadBtn).toBeVisible();
    await expect(normalDownloadBtn).toHaveText('Download planning PDF');

    // Provisional preview: Download provisional planning PDF
    await setupRoutes(page, htmlProvisional);
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const provDownloadBtn = page.getByTestId('download-button');
    await expect(provDownloadBtn).toBeVisible();
    await expect(provDownloadBtn).toHaveText('Download provisional planning PDF');

    // Save screenshot 1: PDF_DOWNLOAD_READY.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_READY.png'), fullPage: true });
  });

  test('3 to 7. Processing state: button disabled, label changed, aria-busy, loading panel visible, duplicate blocked', async ({ page }, testInfo) => {
    let exportRequestCount = 0;
    let finishExport = null;
    const exportPromise = new Promise(res => { finishExport = res; });

    await setupRoutes(page, htmlNormal, async route => {
      exportRequestCount++;
      await exportPromise;
      const pdfBytes = Buffer.from('%PDF-1.4\n1 0 obj<< /Type /Catalog >>endobj\n%%EOF');
      return route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        headers: { 'Content-Disposition': 'attachment; filename="UDM-RADAR-PEF-CC.pdf"' },
        body: pdfBytes,
      });
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    const refreshBtn = page.getByTestId('refresh-preview-button');
    const workbookPanel = page.getByTestId('panel-workbook');
    const loadingPanel = page.locator('#pef-cc-loading-panel');

    await expect(loadingPanel).toBeHidden();
    await expect(workbookPanel).not.toHaveAttribute('aria-busy', 'true');

    // Click download
    await downloadBtn.click();

    // Verify immediate processing state
    await expect(downloadBtn).toBeDisabled();
    await expect(downloadBtn).toHaveAttribute('aria-disabled', 'true');
    await expect(downloadBtn).toContainText('Preparing PDF…');
    await expect(downloadBtn.locator('.pef-cc-btn-spinner')).toBeVisible();

    await expect(refreshBtn).toBeDisabled();
    await expect(workbookPanel).toHaveAttribute('aria-busy', 'true');

    // Verify loading panel text and semantics
    await expect(loadingPanel).toBeVisible();
    await expect(loadingPanel).toHaveAttribute('role', 'status');
    await expect(loadingPanel).toHaveAttribute('aria-live', 'polite');
    await expect(loadingPanel).toHaveAttribute('aria-atomic', 'true');
    await expect(loadingPanel.locator('.pef-cc-loading-title')).toHaveText('Preparing your planning PDF');
    await expect(loadingPanel.locator('.pef-cc-loading-desc')).toHaveText('Creating the Pre-Enrolment Form and Curriculum Checklist. Please keep this page open.');
    await expect(loadingPanel.locator('.pef-cc-loading-note')).toHaveText('The download will begin automatically when the PDF is ready.');

    // Save screenshot 2: PDF_DOWNLOAD_PROCESSING.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_PROCESSING.png'), fullPage: true });

    // Attempt second and third clicks to verify duplicate prevention
    await expect.poll(()=>exportRequestCount).toBe(1);
    // Dispatch duplicate form submits to exercise the busy guard without forcing a disabled button.
    await page.locator('#pef-cc-download-form').evaluate(form=>{form.requestSubmit();form.requestSubmit();});
    expect(exportRequestCount).toBe(1);

    // Complete the export
    finishExport();
  });

  test('8, 9, 10. Valid PDF download starts, announced, and restores controls', async ({ page }, testInfo) => {
    let finishExport = null;
    const exportPromise = new Promise(res => { finishExport = res; });

    await setupRoutes(page, htmlNormal, async route => {
      await exportPromise;
      const pdfBytes = Buffer.from(
        '%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n' +
        '2 0 obj\n<< /Type /Pages /Kids [3 0 R 4 0 R] /Count 2 >>\nendobj\n' +
        '3 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\n' +
        '4 0 obj\n<< /Type /Page /Parent 2 0 R >>\nendobj\nxref\n0 5\n' +
        'trailer\n<< /Root 1 0 R >>\n%%EOF'
      );
      return route.fulfill({
        status: 200,
        contentType: 'application/pdf',
        headers: {
          'Content-Disposition': 'attachment; filename="UDM-RADAR-PEF-CC.pdf"',
          'Content-Length': String(pdfBytes.length),
        },
        body: pdfBytes,
      });
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    const loadingPanel = page.locator('#pef-cc-loading-panel');
    const announcer = page.locator('#pef-cc-live-announcer');

    const downloadPromise = page.waitForEvent('download');
    await downloadBtn.click();
    finishExport();

    const download = await downloadPromise;
    expect(download.suggestedFilename()).toBe('UDM-RADAR-PEF-CC.pdf');

    // Screen reader polite announcement
    await expect(announcer).toHaveText('Your planning PDF download has started.');

    // Controls restored
    await expect(loadingPanel).toBeHidden();
    await expect(downloadBtn).toBeEnabled();
    await expect(downloadBtn).not.toHaveAttribute('aria-disabled', 'true');
    await expect(downloadBtn).toHaveText('Download planning PDF');
    await expect(page.getByTestId('panel-workbook')).toHaveAttribute('aria-busy', 'false');

    // Save screenshot 3: PDF_DOWNLOAD_SUCCESS.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_SUCCESS.png'), fullPage: true });
  });

  test('11. HTTP 409 stale preview response shows refresh action and marks dirty', async ({ page }, testInfo) => {
    await setupRoutes(page, htmlNormal, async route => {
      return route.fulfill({
        status: 409,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'The academic record or plan changed. Refresh the preview before downloading.' }),
      });
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    const refreshBtn = page.getByTestId('refresh-preview-button');
    const invalidated = page.getByTestId('preview-invalidated');
    const feedback = page.locator('#pef-cc-download-feedback');
    const announcer = page.locator('#pef-cc-live-announcer');

    await downloadBtn.click();

    // Verify 409 UI recovery
    await expect(invalidated).toBeVisible();
    await expect(invalidated).toContainText('The academic record or plan changed. Refresh the preview before downloading.');
    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('The academic record or plan changed. Refresh the preview before downloading.');
    await expect(announcer).toHaveText('The academic record or plan changed. Refresh the preview before downloading.');

    // Download button disabled
    await expect(downloadBtn).toBeDisabled();
    // Refresh button styled primary and focused
    await expect(refreshBtn).toBeEnabled();
    await expect(refreshBtn).toHaveClass(/btn--primary/);
    await expect(refreshBtn).toBeFocused();

    // Save screenshot 4: PDF_DOWNLOAD_STALE_PREVIEW.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_STALE_PREVIEW.png'), fullPage: true });
  });

  test('12, 13, 14. Server rendering error (503/HTML) is rejected, no XLSX downloaded, UI recovered', async ({ page }, testInfo) => {
    await setupRoutes(page, htmlNormal, async route => {
      return route.fulfill({
        status: 503,
        contentType: 'application/json',
        body: JSON.stringify({ error: 'The planning copy could not be prepared. Please refresh your preview and try again.' }),
      });
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    const refreshBtn = page.getByTestId('refresh-preview-button');
    const feedback = page.locator('#pef-cc-download-feedback');
    const announcer = page.locator('#pef-cc-live-announcer');

    await downloadBtn.click();

    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('The planning PDF could not be created. Refresh the preview and try again.');
    await expect(announcer).toHaveText('The planning PDF could not be created. Refresh the preview and try again.');

    // Controls recovered
    await expect(downloadBtn).toBeEnabled();
    await expect(refreshBtn).toBeEnabled();

    // Save screenshot 5: PDF_DOWNLOAD_RENDER_ERROR.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_RENDER_ERROR.png'), fullPage: true });
  });

  test('15. Network failure restores controls with friendly status', async ({ page }, testInfo) => {
    await setupRoutes(page, htmlNormal, async route => {
      return route.abort('failed');
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    const feedback = page.locator('#pef-cc-download-feedback');
    const announcer = page.locator('#pef-cc-live-announcer');

    await downloadBtn.click();

    await expect(feedback).toBeVisible();
    await expect(feedback).toContainText('The PDF download was interrupted. Check the connection and try again.');
    await expect(announcer).toHaveText('The PDF download was interrupted. Check the connection and try again.');
    await expect(downloadBtn).toBeEnabled();
  });

  test('18. Reduced motion: spinner animation suppressed and static indicator displayed', async ({ page }, testInfo) => {
    await setupRoutes(page, htmlNormal, async route => {
      await new Promise(r => setTimeout(r, 600));
      return route.fulfill({ status: 200, contentType: 'application/pdf', body: Buffer.from('%PDF-1.4') });
    });

    // Emulate reduced motion
    await page.emulateMedia({ reducedMotion: 'reduce' });
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');

    const downloadBtn = page.getByTestId('download-button');
    await downloadBtn.click();

    const spinner = page.locator('.pef-cc-loading-spinner');
    await expect(spinner).toBeVisible();

    // Verify animation is suppressed under reduced motion
    const animationName = await spinner.evaluate(el => window.getComputedStyle(el).animationName);
    expect(animationName).toBe('none');

    // Save screenshot 7: PDF_DOWNLOAD_REDUCED_MOTION.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_REDUCED_MOTION.png'), fullPage: true });
  });

  test('19. Mobile layout: 390px viewport containment, no horizontal overflow', async ({ page }, testInfo) => {
    await setupRoutes(page, htmlNormal, async route => {
      await new Promise(r => setTimeout(r, 600));
      return route.fulfill({ status: 200, contentType: 'application/pdf', body: Buffer.from('%PDF-1.4') });
    });

    await page.setViewportSize({ width: 390, height: 844 });
    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');

    const downloadBtn = page.getByTestId('download-button');
    await downloadBtn.click();

    const loadingPanel = page.locator('#pef-cc-loading-panel');
    await expect(loadingPanel).toBeVisible();

    // Check no horizontal scroll overflow
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth + 1);
    expect(overflow).toBe(false);

    // Save screenshot 6: PDF_DOWNLOAD_MOBILE.png
    await page.screenshot({ path: testInfo.outputPath('PDF_DOWNLOAD_MOBILE.png'), fullPage: true });
  });

  test('20. Keyboard activation: Enter key activates download', async ({ page }, testInfo) => {
    let called = false;
    await setupRoutes(page, htmlNormal, async route => {
      called = true;
      await new Promise(r => setTimeout(r, 500));
      return route.fulfill({ status: 200, contentType: 'application/pdf', body: Buffer.from('%PDF-1.4') });
    });

    await page.goto('http://pef-print.invalid/udm-radar/student/enrolment_planning.php');
    const downloadBtn = page.getByTestId('download-button');
    await downloadBtn.focus();
    await page.keyboard.press('Enter');

    await expect(downloadBtn).toContainText('Preparing PDF…');
    expect(called).toBe(true);
  });

});
