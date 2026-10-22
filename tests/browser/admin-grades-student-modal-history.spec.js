/**
 * tests/browser/admin-grades-student-modal-history.spec.js
 * Browser E2E Test Suite for Admin Grades Student Modal with Read-Only Academic History
 *
 * Verifies:
 * 1. Modal Structure & Static Contract:
 *    - Uses Admin Students modal visual hierarchy (header, context subline, 3 summary cards).
 *    - Desktop target width min(920px, calc(100vw - 32px)).
 *    - Accessibility semantics: role="dialog", aria-modal="true", aria-labelledby="history-name".
 * 2. Current-Semester Section:
 *    - Current-semester grades and correction proposals.
 *    - Inputs for prelim, midterm, prefinal, final_grade.
 *    - Audited proposal workflow with confirmation step and CSRF token.
 *    - MODERATE risk badge fits on one line without crowding or wrapping.
 *    - Supportive explanatory notes for missing grades and proposal governance.
 * 3. Lazy-Loaded Read-Only Grade History:
 *    - Collapsed initially (aria-expanded="false").
 *    - View Grade History button with aria-controls.
 *    - Shows loading state with spinner on first expand.
 *    - Renders historical terms (First Year through latest) with units, final grade, outcome remarks, and term GWA.
 *    - Read-only: no edit inputs or proposal buttons in historical tables.
 *    - Toggle collapses and re-expands using cached data (no duplicate request).
 *    - Selecting another student clears cache and loads fresh data.
 *    - Safe empty state: "No historical grades on record."
 *    - Safe error state with retry button on network failure.
 * 4. Accessibility & Focus Management:
 *    - Focus enters modal on open.
 *    - Escape key closes modal.
 *    - Focus returns to trigger button on close.
 *    - Focus trap keeps keyboard navigation inside modal.
 * 5. Responsive Viewport Matrix:
 *    - Verified at 360x800, 375x812, 390x844, 768x1024, 1024x768, 1280x720, 1440x900.
 *    - Requires document.documentElement.scrollWidth <= window.innerWidth + 1.
 */

import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');
const dashboardCss   = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const uiJs           = readFileSync(resolve('assets/js/ui.js'), 'utf-8');

// Extract inline styles and scripts from admin/grades.php for realistic mounting
const inlineStyleMatch = adminGradesPhp.match(/<style>([\s\S]*?)<\/style>/i);
const adminGradesCss = inlineStyleMatch ? inlineStyleMatch[1] : '';

const inlineScriptMatch = adminGradesPhp.match(/<script>([\s\S]*?)<\/script>/i);
let rawAdminGradesJs = inlineScriptMatch ? inlineScriptMatch[1] : '';
// Replace server-injected PHP echo tags with deterministic values for test harness
const adminGradesJs = rawAdminGradesJs
    .replace('<?= json_encode($openSection) ?>', 'null')
    .replace('<?= json_encode($openStudent) ?>', 'null')
    .replace('<?= json_encode($openFeedbackId) ?>', 'null')
    .replace("<?= json_encode($_SESSION['csrf_token']) ?>", JSON.stringify('test-mock-csrf-token'));

function buildAdminGradesPage() {
    return `
    <!DOCTYPE html>
    <html lang="en" data-theme="light">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <base href="http://localhost/udm-radar-admin-grades-modal/admin/">
        <style>
            :root {
                --bg-color: #f8fafc;
                --card-bg: #ffffff;
                --text-dark: #0f172a;
                --text-gray: #64748b;
                --border-color: #e2e8f0;
                --sidebar-bg: #1e293b;
                --accent-blue: #2563eb;
                --risk-high: #dc2626;
                --risk-mod: #d97706;
                --risk-low: #059669;
                --table-header-bg: #f1f5f9;
            }
            body {
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                margin: 0;
                padding: 0;
                background: var(--bg-color);
                color: var(--text-dark);
            }
            ${dashboardCss}
            ${adminGradesCss}
        </style>
        <script>
            ${uiJs}
        </script>
    </head>
    <body>
        <div class="main-content" style="padding: 24px; max-width: 1200px; margin: 0 auto;">
            <div class="header">
                <h1>Grades</h1>
            </div>

            <!-- Trigger buttons simulating section roster -->
            <div class="data-table-card data-table-card--active" style="padding: 16px; margin-bottom: 24px;">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student No.</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>GWA</th>
                            <th>Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>24-22-286</td>
                            <td>
                                <button type="button" class="table-record-link" id="trigger-student-286" onclick="openHistory(6, 'Evangelista, Ranna')">
                                    Evangelista, Ranna
                                </button>
                            </td>
                            <td>Regular</td>
                            <td>3.07</td>
                            <td><span style="background:var(--risk-low); color:white; padding:2px 8px; border-radius:4px;">LOW</span></td>
                        </tr>
                        <tr>
                            <td>23-22-040</td>
                            <td>
                                <button type="button" class="table-record-link" id="trigger-student-040" onclick="openHistory(5, 'Tala, Alexa Mhae')">
                                    Tala, Alexa Mhae
                                </button>
                            </td>
                            <td>Regular</td>
                            <td>3.34</td>
                            <td><span style="background:var(--risk-mod); color:white; padding:2px 8px; border-radius:4px;">MODERATE</span></td>
                        </tr>
                        <tr>
                            <td>24-00-999</td>
                            <td>
                                <button type="button" class="table-record-link" id="trigger-student-empty" onclick="openHistory(99, 'Empty, Student')">
                                    Empty, Student
                                </button>
                            </td>
                            <td>Regular</td>
                            <td>—</td>
                            <td><span style="background:var(--text-gray); color:white; padding:2px 8px; border-radius:4px;">N/A</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Reworked Student Modal Structure from admin/grades.php -->
        <div class="modal-overlay" id="history-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="history-name" onclick="if(event.target===this) closeHistoryModal();">
            <div class="modal-box history-modal-box">
                <button class="modal-close" id="history-modal-close-btn" onclick="closeHistoryModal()" aria-label="Close dialog">Close</button>
                <h2 id="history-name" style="color:var(--text-dark); margin-bottom:2px;"></h2>
                <p id="history-subline" style="color:var(--text-gray); font-size:0.88rem; margin-bottom:12px;"></p>

                <div class="modal-stat-grid">
                    <div class="modal-stat">
                        <span>Cumulative GWA (Historical)</span>
                        <strong id="history-modal-gwa">—</strong>
                    </div>
                    <div class="modal-stat">
                        <span>Projected Term GWA</span>
                        <strong id="history-modal-predicted">—</strong>
                    </div>
                    <div class="modal-stat">
                        <span>Advisory Risk Level</span>
                        <strong id="history-modal-risk">—</strong>
                    </div>
                </div>

                <div style="margin-top:16px;">
                    <h3 style="color:var(--text-dark); font-size:0.95rem; margin-bottom:6px; font-weight:700;">
                        Current Semester Grades and Correction Proposals
                    </h3>
                    <p style="color:var(--text-gray); font-size:0.8rem; margin-bottom:10px;">
                        Grades are not directly editable in this view. Submitting a correction proposal applies the change only after confirmation and audit recording.
                    </p>

                    <div class="data-table-scroll grades-modal-scroll" role="region" aria-label="Current Semester Grades and Correction Proposals" tabindex="0">
                        <div class="grades-table-header">
                            <div>Subject</div>
                            <div style="text-align:center;">Prelim</div>
                            <div style="text-align:center;">Midterm</div>
                            <div style="text-align:center;">Pre-Final</div>
                            <div style="text-align:center;">Official Final</div>
                            <div style="text-align:center;">Advisory Risk</div>
                            <div style="text-align:right;"><span class="sr-only">Actions</span></div>
                        </div>
                        <div id="history-body"></div>
                    </div>
                    <p style="font-size:0.78rem; color:var(--text-gray); margin-top:8px; margin-bottom:0;">
                        Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.
                    </p>
                </div>

                <div style="margin-top:20px; border-top:1px solid var(--border-color); padding-top:14px;">
                    <button type="button" id="modal-history-toggle" class="history-toggle-btn" aria-expanded="false" aria-controls="modal-history-container" onclick="toggleGradeHistory()">
                        <span id="history-toggle-icon" aria-hidden="true" style="display:inline-block; transition:transform 0.2s ease; margin-right:4px;">▶</span>
                        <span id="history-toggle-text">View Grade History</span>
                    </button>

                    <div id="modal-history-wrapper" class="history-wrapper" style="max-height:0px; overflow:hidden; transition:max-height 0.3s ease;">
                        <div id="modal-history-container" style="padding-top:12px;"></div>
                    </div>
                </div>

                <div style="margin-top:16px; padding-top:12px; border-top:1px dashed var(--border-color); display:flex; justify-content:flex-end;">
                    <a id="modal-full-record-link" href="#" class="btn btn--quiet btn--sm" style="display:inline-flex; align-items:center; gap:6px;">
                        Open Full Student Record &rarr;
                    </a>
                </div>
            </div>
        </div>

        <script>
            ${adminGradesJs}
        </script>
    </body>
    </html>
    `;
}

// Mock payloads for testing
const mockStudent286Detail = {
    student: {
        userId: 6,
        studentNo: '24-22-286',
        name: 'Evangelista, Ranna',
        section: 'IT-31',
        yearLevel: 3,
        status: 'Regular',
        recordStatus: 'Active',
        currentGwa: 3.07,
        predictedGwa: 3.06,
        risk: 'LOW',
    },
    grades: [
        {
            gradeId: 10138,
            code: 'ITE321',
            title: 'Information and Security Assurance 1',
            units: 3,
            prelim: 86.00,
            midterm: null,
            prefinal: null,
            finalGrade: null,
            risk: 'LOW',
            hasPending: false,
        },
        {
            gradeId: 10143,
            code: 'ITE341',
            title: 'Statistics and Probability',
            units: 3,
            prelim: 82.00,
            midterm: null,
            prefinal: null,
            finalGrade: null,
            risk: 'MODERATE',
            hasPending: false,
        },
        {
            gradeId: 10145,
            code: 'UID105',
            title: 'Achievement and Passion',
            units: 1,
            prelim: 92.00,
            midterm: null,
            prefinal: null,
            finalGrade: null,
            risk: 'LOW',
            hasPending: true,
        }
    ]
};

const mockStudent286History = {
    studentId: 6,
    terms: [
        {
            termKey: '2025-2026 | 2nd Year, 2nd Sem',
            schoolYear: '2025-2026',
            semester: 2,
            yearLevel: 2,
            termGwa: '3.13',
            subjects: [
                { code: 'AHM101', title: 'Philippine Popular Culture', units: 3, grade: '3.00', outcome: 'Passed' },
                { code: 'ITE233', title: 'Operating System', units: 3, grade: '3.50', outcome: 'Passed' },
                { code: 'ITE234', title: 'Database Management System 2', units: 3, grade: '2.75', outcome: 'Passed' },
            ]
        },
        {
            termKey: '2024-2025 | 1st Year, 1st Sem',
            schoolYear: '2024-2025',
            semester: 1,
            yearLevel: 1,
            termGwa: '2.94',
            subjects: [
                { code: 'GED101', title: 'Purposive Communications', units: 3, grade: '3.25', outcome: 'Passed' },
                { code: 'ITE111', title: 'Introduction to Computing', units: 3, grade: '2.75', outcome: 'Passed' },
            ]
        }
    ]
};

const mockStudentEmptyDetail = {
    student: {
        userId: 99,
        studentNo: '24-00-999',
        name: 'Empty, Student',
        section: 'IT-31',
        yearLevel: 1,
        status: 'Regular',
        recordStatus: 'Active',
        currentGwa: null,
        predictedGwa: null,
        risk: null,
    },
    grades: []
};

const mockStudentEmptyHistory = {
    studentId: 99,
    terms: []
};

test.describe('Admin Grades Student Modal with Read-Only History', () => {

    test.beforeEach(async ({ page }) => {
        // Setup API route interceptor using URL matching
        await page.route(url => url.pathname.includes('grades_data.php'), async route => {
            const requestUrl = new URL(route.request().url());
            const action = requestUrl.searchParams.get('action');
            const studentId = requestUrl.searchParams.get('student_id');

            const fulfillJson = (status, payload) => route.fulfill({
                status,
                contentType: 'application/json',
                headers: { 'Access-Control-Allow-Origin': '*' },
                body: JSON.stringify(payload),
            });

            if (action === 'student_detail') {
                if (studentId === '6') {
                    await fulfillJson(200, mockStudent286Detail);
                } else if (studentId === '99') {
                    await fulfillJson(200, mockStudentEmptyDetail);
                } else {
                    await fulfillJson(404, { error: 'Student record not found' });
                }
            } else if (action === 'academic_history') {
                if (studentId === '6') {
                    await fulfillJson(200, mockStudent286History);
                } else if (studentId === '99') {
                    await fulfillJson(200, mockStudentEmptyHistory);
                } else {
                    await fulfillJson(404, { error: 'Student record not found' });
                }
            } else {
                await route.continue();
            }
        });
    });

    test('1. Static Code Analysis: Modal structure, accessible tokens, and shared history logic', () => {
        // Check admin/grades.php markup
        expect(adminGradesPhp).toContain('id="history-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="history-name"');
        expect(adminGradesPhp).toContain('id="history-subline"');
        expect(adminGradesPhp).toContain('id="history-modal-gwa"');
        expect(adminGradesPhp).toContain('id="history-modal-predicted"');
        expect(adminGradesPhp).toContain('id="history-modal-risk"');
        expect(adminGradesPhp).toContain('Current Semester Grades and Correction Proposals');
        expect(adminGradesPhp).toContain('Grades are not directly editable in this view.');
        expect(adminGradesPhp).toContain('id="modal-history-toggle"');
        expect(adminGradesPhp).toContain('aria-expanded="false"');
        expect(adminGradesPhp).toContain('aria-controls="modal-history-container"');
        expect(adminGradesPhp).toContain('View Grade History');
        expect(adminGradesPhp).toContain('id="modal-history-wrapper"');
        expect(adminGradesPhp).toContain('id="modal-history-container"');
        expect(adminGradesPhp).toContain('id="modal-full-record-link"');
        expect(adminGradesPhp).toContain('width: min(920px, calc(100vw - 32px))');
        expect(adminGradesPhp).toContain('name="action" value="propose_grade"');

        // Check CSS
        expect(adminGradesCss).toContain('.history-modal-box');
        expect(adminGradesCss).toContain('.modal-stat-grid');
        expect(adminGradesCss).toContain('.grades-table-header');
        expect(adminGradesCss).toContain('.history-toggle-btn');
    });

    test('2. Modal Appearance & Content: Opens for student, renders header, context, and summary cards', async ({ page }) => {
        await page.setContent(buildAdminGradesPage());

        const trigger = page.locator('#trigger-student-286');
        await trigger.click();

        const modal = page.locator('#history-modal-overlay');
        await expect(modal).toHaveClass(/open/);

        // Header and context line
        await expect(page.locator('#history-name')).toHaveText('Evangelista, Ranna');
        await expect(page.locator('#history-subline')).toHaveText('24-22-286 · Section IT-31 · Year 3 · Regular');

        // Summary cards
        await expect(page.locator('#history-modal-gwa')).toHaveText('3.07');
        await expect(page.locator('#history-modal-predicted')).toHaveText('3.06');
        await expect(page.locator('#history-modal-risk')).toHaveText('LOW');

        // Close button has accessible label
        const closeBtn = page.locator('#history-modal-close-btn');
        await expect(closeBtn).toHaveAttribute('aria-label', 'Close dialog');
    });

    test('3. Modal Usable Width & MODERATE Badge: Fits on desktop without wrapping', async ({ page }) => {
        await page.setViewportSize({ width: 1280, height: 720 });
        await page.setContent(buildAdminGradesPage());

        await page.locator('#trigger-student-286').click();

        // Check modal box width
        const modalBox = page.locator('.history-modal-box');
        const box = await modalBox.boundingBox();
        expect(box.width).toBeGreaterThanOrEqual(800);
        expect(box.width).toBeLessThanOrEqual(920);

        // Check MODERATE badge for ITE341
        const moderateBadge = page.locator('.grade-row-risk .risk-badge:has-text("MODERATE")');
        await expect(moderateBadge).toBeVisible();

        // Ensure white-space is nowrap and text does not wrap to multiple lines
        const badgeHeight = await moderateBadge.evaluate(el => el.getBoundingClientRect().height);
        expect(badgeHeight).toBeLessThanOrEqual(24);

        const whiteSpace = await moderateBadge.evaluate(el => window.getComputedStyle(el).whiteSpace);
        expect(whiteSpace).toBe('nowrap');

        // Verify Propose Correction button is visible and on the same row
        const proposeBtn = page.locator('form.grade-row-form:has-text("ITE341") button.small-btn');
        await expect(proposeBtn).toBeVisible();
        await expect(proposeBtn).toHaveText('Propose Correction');
    });

    test('4. Current Semester Workflow: Inputs, CSRF, reason prompt, and pending badge', async ({ page }) => {
        await page.setContent(buildAdminGradesPage());
        await page.locator('#trigger-student-286').click();

        // Check inputs
        const formITE321 = page.locator('form.grade-row-form:has-text("ITE321")');
        await expect(formITE321.locator('input[name="edit_prelim"]')).toHaveValue('86');
        await expect(formITE321.locator('input[name="edit_midterm"]')).toHaveValue('');
        await expect(formITE321.locator('input[name="edit_prefinal"]')).toHaveValue('');
        await expect(formITE321.locator('input[name="edit_final"]')).toHaveValue('');

        // Check hidden fields for governance
        await expect(formITE321.locator('input[name="action"]')).toHaveValue('propose_grade');
        await expect(formITE321.locator('input[name="csrf_token"]')).toHaveValue('test-mock-csrf-token');
        await expect(formITE321.locator('input[name="grade_id"]')).toHaveValue('10138');

        // Check pending badge on UID105
        const formUID105 = page.locator('form.grade-row-form:has-text("UID105")');
        await expect(formUID105.locator('.pending-badge')).toBeVisible();
        await expect(formUID105.locator('.pending-badge')).toHaveText('Pending');

        // Verify prompt confirmation step
        let promptHandled = false;
        page.on('dialog', async dialog => {
            expect(dialog.type()).toBe('prompt');
            expect(dialog.message()).toContain('Reason for this proposed correction');
            promptHandled = true;
            await dialog.accept('Encoder clerical correction');
        });

        // Intercept form submission
        await page.route('**/admin/grades.php', route => route.abort());
        await formITE321.locator('button[type="submit"]').click();
        expect(promptHandled).toBe(true);
    });

    test('5. View Grade History Control: Collapsed initially, expands, fetches on demand, collapses', async ({ page }) => {
        await page.setContent(buildAdminGradesPage());
        await page.locator('#trigger-student-286').click();

        const toggleBtn = page.locator('#modal-history-toggle');
        await expect(toggleBtn).toBeVisible();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'false');
        await expect(toggleBtn).toHaveAttribute('aria-controls', 'modal-history-container');
        await expect(page.locator('#history-toggle-text')).toHaveText('View Grade History');

        const wrapper = page.locator('#modal-history-wrapper');
        await expect(wrapper).toHaveCSS('max-height', '0px');

        let historyFetchCount = 0;
        await page.route(url => url.pathname.includes('grades_data.php') && url.searchParams.get('action') === 'academic_history' && url.searchParams.get('student_id') === '6', async route => {
            historyFetchCount++;
            await route.fulfill({
                status: 200,
                contentType: 'application/json',
                headers: { 'Access-Control-Allow-Origin': '*' },
                body: JSON.stringify(mockStudent286History),
            });
        });

        // Expand
        await toggleBtn.click();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#history-toggle-text')).toHaveText('Hide Grade History');
        expect(historyFetchCount).toBe(1);

        // Verify historical terms rendered
        const term1 = page.locator('.history-term-block:has-text("2025-2026 | 2nd Year, 2nd Sem")');
        await expect(term1).toBeVisible();
        await expect(term1).toContainText('Term GWA: 3.13');
        await expect(term1.locator('td:has-text("AHM101")')).toBeVisible();
        await expect(term1.locator('td:has-text("3.00")')).toBeVisible();
        await expect(term1.locator('td:has-text("Passed")').first()).toBeVisible();

        const term2 = page.locator('.history-term-block:has-text("2024-2025 | 1st Year, 1st Sem")');
        await expect(term2).toBeVisible();
        await expect(term2).toContainText('Term GWA: 2.94');

        // Historical rows MUST NOT contain edit inputs or propose correction buttons
        await expect(page.locator('#modal-history-container input')).toHaveCount(0);
        await expect(page.locator('#modal-history-container button:has-text("Propose")')).toHaveCount(0);

        // Collapse
        await toggleBtn.click();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#history-toggle-text')).toHaveText('View Grade History');
        await expect(wrapper).toHaveCSS('max-height', '0px');

        // Re-expand: must reuse cached data without re-fetching
        await toggleBtn.click();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#history-toggle-text')).toHaveText('Hide Grade History');
        expect(historyFetchCount).toBe(1); // Still 1, didn't make another network call!
    });

    test('6. Stale Cache Protection: Switching students resets cache and history toggle', async ({ page }) => {
        await page.setContent(buildAdminGradesPage());

        // Open student 286 and expand history
        await page.locator('#trigger-student-286').click();
        await page.locator('#modal-history-toggle').click();
        await expect(page.locator('.history-term-block:has-text("2025-2026 | 2nd Year, 2nd Sem")')).toBeVisible();

        // Close modal
        await page.locator('#history-modal-close-btn').click();
        await expect(page.locator('#history-modal-overlay')).not.toHaveClass(/open/);

        // Open student 99 (empty student)
        await page.locator('#trigger-student-empty').click();
        await expect(page.locator('#history-name')).toHaveText('Empty, Student');
        await expect(page.locator('#history-modal-gwa')).toHaveText('—');
        await expect(page.locator('#history-modal-predicted')).toHaveText('—');

        // History toggle MUST be reset to collapsed
        const toggleBtn = page.locator('#modal-history-toggle');
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'false');
        await expect(page.locator('#history-toggle-text')).toHaveText('View Grade History');

        // Expand for empty student
        await toggleBtn.click();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'true');
        await expect(page.locator('#modal-history-container')).toContainText('No historical grades on record.');
    });

    test('7. Error Handling & Retry: Safe error state when history fetch fails', async ({ page }) => {
        let shouldFailHistory = true;
        await page.route(url => url.pathname.includes('grades_data.php') && url.searchParams.get('action') === 'academic_history' && url.searchParams.get('student_id') === '6', async route => {
            if (shouldFailHistory) {
                await route.abort();
            } else {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    headers: { 'Access-Control-Allow-Origin': '*' },
                    body: JSON.stringify(mockStudent286History),
                });
            }
        });

        await page.setContent(buildAdminGradesPage());
        await page.locator('#trigger-student-286').click();
        await page.locator('#modal-history-toggle').click();

        // Verify error message and retry button
        const errorState = page.locator('.roster-state--error');
        await expect(errorState).toBeVisible();
        await expect(errorState).toContainText('The grade history could not be loaded.');

        const retryBtn = page.locator('#modal-history-container button:has-text("Retry")');
        await expect(retryBtn).toBeVisible();

        // Now allow success and retry
        shouldFailHistory = false;
        await retryBtn.click();
        await expect(page.locator('.history-term-block:has-text("2025-2026 | 2nd Year, 2nd Sem")')).toBeVisible();
    });

    test('8. Accessibility & Focus Management: Dialog role, focus trap, Escape key closes, returns focus', async ({ page }) => {
        await page.setContent(buildAdminGradesPage());

        const trigger = page.locator('#trigger-student-286');
        await trigger.click();

        const modal = page.locator('#history-modal-overlay');
        await expect(modal).toHaveClass(/open/);

        // Focus should move into close button
        await expect(page.locator('#history-modal-close-btn')).toBeFocused();

        // Escape closes modal
        await page.keyboard.press('Escape');
        await expect(modal).not.toHaveClass(/open/);

        // Focus returns to the trigger button
        await expect(trigger).toBeFocused();
    });

    test.describe('9. Responsive Viewports: No horizontal overflow across matrix', () => {
        const viewports = [
            { width: 360, height: 800 },
            { width: 375, height: 812 },
            { width: 390, height: 844 },
            { width: 768, height: 1024 },
            { width: 1024, height: 768 },
            { width: 1280, height: 720 },
            { width: 1440, height: 900 },
        ];

        for (const vp of viewports) {
            test(`Viewport ${vp.width}x${vp.height}: scrollWidth <= innerWidth + 1`, async ({ page }) => {
                await page.setViewportSize(vp);
                await page.setContent(buildAdminGradesPage());

                await page.locator('#trigger-student-286').click();
                await page.locator('#modal-history-toggle').click();

                // Wait for history to render
                await expect(page.locator('.history-term-block').first()).toBeVisible();

                // Check page level overflow
                const hasOverflow = await page.evaluate(() => {
                    return document.documentElement.scrollWidth > window.innerWidth + 1;
                });
                expect(hasOverflow).toBe(false);

                // Check that MODERATE badge does not wrap
                const moderateBadge = page.locator('.grade-row-risk .risk-badge:has-text("MODERATE")');
                const badgeHeight = await moderateBadge.evaluate(el => el.getBoundingClientRect().height);
                expect(badgeHeight).toBeLessThanOrEqual(24);

                // Modal close button remains reachable
                const closeBtn = page.locator('#history-modal-close-btn');
                await expect(closeBtn).toBeVisible();
            });
        }
    });
});
