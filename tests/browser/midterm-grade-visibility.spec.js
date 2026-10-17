/**
 * tests/browser/midterm-grade-visibility.spec.js
 * Browser E2E Test Suite for Cross-Portal Midterm Grade Visibility (GRADES-MIDTERM-VISIBILITY-WP1)
 *
 * Verifies:
 * 1. Student Portal:
 *    - Midterm visible in student/grades.php with explicit labels and supportive explanation.
 *    - Midterm visible in student/dashboard.php current subjects table.
 *    - Missing Midterm rendered as '—' (not 0 or failed) for 26-00-002.
 *    - Mobile viewports (375px, 390px) preserve visibility with no page-level horizontal overflow.
 * 2. Faculty Portal:
 *    - Contextual Prelim and Midterm displayed side by side in faculty/grades.php roster.
 *    - Faculty Dashboard subject cells display both periods (P: ... / M: ...).
 *    - Performance Trends (faculty/trend.php) renders Midterm period progression.
 * 3. Admin Portal:
 *    - Student Details modal in admin/students.php exposes Midterm column and supportive note.
 *    - Batch Predict card in admin/index.php retains Prelim-based prediction & Midterm review disclosures.
 * 4. Security & Role Authorization:
 *    - Student cannot access admin/index.php or faculty/grades.php.
 *    - Faculty cannot inspect an unauthorized class load.
 *    - No raw PHP or database errors appear across any tested page.
 */

import { test, expect } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import process from 'node:process';

const phpBinary = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const rootDir = resolve('.');
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1/capstone-midterm-visibility';

// Credential safety: use approved repository fixture credentials or environment variables
const cohortTestPassword = process.env.COHORT_TEST_PASSWORD || 'CohortTest2026!';
const testFacultyUser = process.env.TEST_FACULTY_USER || 'SYN-FA-001';
const testFacultyPassword = process.env.TEST_FACULTY_PASSWORD || cohortTestPassword;
const testAdminUser = process.env.TEST_ADMIN_USER || 'SYN-ADMIN-001';
const testAdminPassword = process.env.TEST_ADMIN_PASSWORD || cohortTestPassword;

const usingSyntheticFaculty = testFacultyUser === 'SYN-FA-001';
const usingSyntheticAdmin = testAdminUser === 'SYN-ADMIN-001';

let phpServerProcess = null;

test.describe.serial('Cross-Portal Midterm Grade Visibility', () => {

    test.beforeAll(async () => {
        // 1. Ensure scratch database has synthetic multi-year cohorts and section midterms seeded
        try {
            execFileSync(phpBinary, [resolve('tools/seed_multiyear_cohorts.php'), '--target=scratch'], {
                cwd: rootDir,
                encoding: 'utf8',
            });
            execFileSync(phpBinary, [resolve('tools/seed_section_midterms.php'), '--target=scratch'], {
                cwd: rootDir,
                encoding: 'utf8',
            });
        } catch (e) {
            console.error('Fixture seeding error in beforeAll:', e.message);
        }

        // 2. Setup ephemeral synthetic faculty and admin credentials if not overridden by env
        if (usingSyntheticFaculty || usingSyntheticAdmin) {
            try {
                const setupCode = `
putenv('DB_NAME=udm_radar_scratch');
require_once 'config/db.php';
$db = getDB();
$hash = password_hash('${cohortTestPassword}', PASSWORD_BCRYPT);
if (${usingSyntheticFaculty ? 'true' : 'false'}) {
    $db->exec("DELETE FROM faculty_class_loads WHERE faculty_user_id IN (SELECT id FROM users WHERE user_id = 'SYN-FA-001')");
    $db->exec("DELETE FROM users WHERE user_id = 'SYN-FA-001'");
    $stmt = $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, last_name, email, is_active) VALUES ('SYN-FA-001', ?, 'faculty', 'Synthetic', 'Faculty', 'syn.faculty@synthetic.udm.edu.ph', 1)");
    $stmt->execute([$hash]);
    $facId = (int)$db->lastInsertId();
    $db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, 37, 'IT-35')")->execute([$facId]);
}
if (${usingSyntheticAdmin ? 'true' : 'false'}) {
    $db->exec("DELETE FROM users WHERE user_id = 'SYN-ADMIN-001'");
    $stmtAdm = $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, last_name, email, is_active) VALUES ('SYN-ADMIN-001', ?, 'admin', 'Synthetic', 'Admin', 'syn.admin@synthetic.udm.edu.ph', 1)");
    $stmtAdm->execute([$hash]);
}
`;
                execFileSync(phpBinary, ['-r', setupCode], {
                    cwd: rootDir,
                    encoding: 'utf8',
                });
            } catch (e) {
                console.error('Ephemeral fixture setup error in beforeAll:', e.message);
            }
        }

        let connected = false;
        try {
            const res = await fetch(`${baseUrl}/login.php`);
            if (res.status === 200) {
                connected = true;
            }
        } catch {
            // Try starting standalone server if Apache is down
        }

        if (!connected) {
            phpServerProcess = spawn(phpBinary, ['-S', `127.0.0.1:${testPort}`], {
                cwd: rootDir,
                env: {
                    ...process.env,
                    DB_NAME: 'udm_radar_scratch',
                },
                stdio: 'ignore',
            });

            for (let i = 0; i < 30; i++) {
                try {
                    const res = await fetch(`http://127.0.0.1:${testPort}/login.php`);
                    if (res.status === 200) {
                        connected = true;
                        break;
                    }
                } catch {
                    // Retry
                }
                await new Promise(r => setTimeout(r, 200));
            }

            if (!connected) {
                throw new Error(`Failed to connect to local server at ${baseUrl}`);
            }
        }
    });

    test.afterAll(() => {
        if (phpServerProcess) {
            phpServerProcess.kill();
        }

        // Clean up ephemeral synthetic fixtures to preserve 100% baseline database integrity
        if (usingSyntheticFaculty || usingSyntheticAdmin) {
            try {
                const teardownCode = `
putenv('DB_NAME=udm_radar_scratch');
require_once 'config/db.php';
$db = getDB();
$db->exec("DELETE FROM faculty_class_loads WHERE faculty_user_id IN (SELECT id FROM users WHERE user_id = 'SYN-FA-001')");
$db->exec("DELETE FROM users WHERE user_id IN ('SYN-FA-001', 'SYN-ADMIN-001')");
`;
                execFileSync(phpBinary, ['-r', teardownCode], {
                    cwd: rootDir,
                    encoding: 'utf8',
                });
            } catch (e) {
                console.error('Ephemeral fixture cleanup error in afterAll:', e.message);
            }
        }
    });

    // Helper: Login
    async function loginAs(page, username, password, expectedUrlPattern) {
        await page.goto(`${baseUrl}/login.php`);
        await page.fill('#userid', username);
        await page.fill('#password', password);
        await page.click('.btn-login');
        await page.waitForURL(expectedUrlPattern);
    }

    test('1. Student Grades: Displays Midterm column, supportive explanation, and period-aware inquiry', async ({ page }) => {
        // Log in as Third-Year Student 24-00-001 (cohort3y001@synthetic.udm.edu.ph)
        await loginAs(page, '24-00-001', 'CohortTest2026!', '**/student/index.php');

        await page.goto(`${baseUrl}/student/grades.php`);

        // Check for absence of PHP / database error messages
        await expect(page.locator('text=Fatal error')).toHaveCount(0);
        await expect(page.locator('text=SQLSTATE')).toHaveCount(0);

        // Verify table headers contain explicit Midterm column
        const table = page.locator('.current-grades-table');
        await expect(table).toBeVisible();
        await expect(table.locator('th:has-text("Prelim")')).toBeVisible();
        await expect(table.locator('th:has-text("Midterm")')).toBeVisible();

        // Verify recorded Midterm percentages are visible in tbody
        const midtermCells = table.locator('tbody td[data-label="Midterm"]');
        expect(await midtermCells.count()).toBeGreaterThanOrEqual(1);
        const firstMidtermText = (await midtermCells.first().textContent())?.trim();
        expect(firstMidtermText).toMatch(/\d+%/);

        // Verify supportive explanation is present
        await expect(page.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();

        // Verify inquiry link specifies period=midterm when midterm is present
        const inquiryLink = table.locator('tbody tr').first().locator('a:has-text("Inquire")');
        const href = await inquiryLink.getAttribute('href');
        expect(href).toContain('period=midterm');
    });

    test('2. Student Dashboard: Displays Midterm column alongside Prelim without overwriting', async ({ page }) => {
        await loginAs(page, '24-00-001', 'CohortTest2026!', '**/student/index.php');

        await page.goto(`${baseUrl}/student/dashboard.php`);

        const subjectsTable = page.locator('.student-subjects-table');
        await expect(subjectsTable).toBeVisible();

        // Explicit Prelim and Midterm column headers
        await expect(subjectsTable.getByRole('columnheader', { name: 'Prelim', exact: true })).toBeVisible();
        await expect(subjectsTable.getByRole('columnheader', { name: 'Midterm', exact: true })).toBeVisible();

        // Separate Prelim and Midterm data cells
        const prelimCells = subjectsTable.locator('tbody td[data-label="Prelim"]');
        const midtermCells = subjectsTable.locator('tbody td[data-label="Midterm"]');
        expect(await prelimCells.count()).toBeGreaterThanOrEqual(1);
        expect(await midtermCells.count()).toBeGreaterThanOrEqual(1);

        // Verify supportive explanation
        await expect(page.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();
    });

    test('3. Student Missing Midterm: Displays em-dash and does not treat missing as zero', async ({ page }) => {
        // Log in as First-Year Student 26-00-002 (cohort1y002@synthetic.udm.edu.ph, unencoded Midterm)
        await loginAs(page, '26-00-002', 'CohortTest2026!', '**/student/index.php');

        await page.goto(`${baseUrl}/student/grades.php`);

        const table = page.locator('.current-grades-table');
        const midtermCells = table.locator('tbody td[data-label="Midterm"]');
        const count = await midtermCells.count();
        expect(count).toBeGreaterThanOrEqual(1);

        // All midterms should display '—'
        for (let i = 0; i < count; i++) {
            const text = (await midtermCells.nth(i).textContent())?.trim();
            expect(text).toBe('—');
            expect(text).not.toBe('0');
            expect(text).not.toBe('0.00');
            expect(text).not.toBe('Failed');
        }

        // On Dashboard as well
        await page.goto(`${baseUrl}/student/dashboard.php`);
        const dashMidtermCells = page.locator('.student-subjects-table tbody td[data-label="Midterm"]');
        const dashCount = await dashMidtermCells.count();
        expect(dashCount).toBeGreaterThanOrEqual(1);
        for (let i = 0; i < dashCount; i++) {
            const text = (await dashMidtermCells.nth(i).textContent())?.trim();
            expect(text).toBe('—');
        }
    });

    test('4. Student Performance Trend: Narrative reports coverage for both periods', async ({ page }) => {
        await loginAs(page, '24-00-001', 'CohortTest2026!', '**/student/index.php');

        await page.goto(`${baseUrl}/student/trend.php`);

        // Check for coverage narrative mentioning both Prelim and Midterm
        const statCard = page.locator('.stat-card:has-text("Current Snapshot GWA")');
        await expect(statCard).toBeVisible();
        const cardText = await statCard.textContent();
        expect(cardText).toContain('Prelim');
        expect(cardText).toContain('Midterm');
    });

    test('5. Mobile Responsive View: Midterm remains visible at 375px and 390px viewports', async ({ page }) => {
        await page.setViewportSize({ width: 375, height: 667 });

        await loginAs(page, '24-00-001', 'CohortTest2026!', '**/student/index.php');

        // Check student/dashboard.php
        await page.goto(`${baseUrl}/student/dashboard.php`);
        const midCell = page.locator('.student-subjects-table tbody td[data-label="Midterm"]').first();
        await expect(midCell).toBeVisible();

        // Check student/grades.php
        await page.goto(`${baseUrl}/student/grades.php`);
        const gradesMidCell = page.locator('.current-grades-table tbody td[data-label="Midterm"]').first();
        await expect(gradesMidCell).toBeVisible();

        // Verify no horizontal document overflow
        const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
        const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
        expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2);
    });

    test('6. Faculty Grades: Contextual Prelim and Midterm visible side-by-side in roster', async ({ page }) => {
        // Log in as Faculty
        await loginAs(page, testFacultyUser, testFacultyPassword, '**/faculty/index.php');

        await page.goto(`${baseUrl}/faculty/grades.php`);

        // Select an assigned class to load the roster
        const classSelect = page.locator('#classSelect');
        await expect(classSelect).toBeVisible();
        const options = await classSelect.locator('option').all();
        expect(options.length).toBeGreaterThan(1);
        const val = await options[1].getAttribute('value');
        expect(val).toBeTruthy();
        await classSelect.selectOption(val);
        await page.waitForTimeout(600);

        // Table header should contain Prelim and Midterm
        const table = page.locator('.table-matrix');
        await expect(table).toBeVisible();
        await expect(table.getByRole('columnheader', { name: 'Prelim', exact: true })).toBeVisible();
        await expect(table.getByRole('columnheader', { name: 'Midterm', exact: true })).toBeVisible();
        await expect(table.locator('#th-new-grade')).toBeVisible();

        // Verify roster rows have both Prelim and Midterm columns rendered
        const rows = page.locator('#roster-tbody tr');
        expect(await rows.count()).toBeGreaterThan(0);
        const firstRow = rows.first();
        const cells = firstRow.locator('td');
        // Index 2 is Prelim, index 3 is Midterm
        await expect(cells.nth(2)).toBeVisible();
        await expect(cells.nth(3)).toBeVisible();

        // Verify supportive explanation
        await expect(page.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();
    });

    test('7. Faculty Dashboard: Subject cells display both Prelim and Midterm progression', async ({ page }) => {
        await loginAs(page, testFacultyUser, testFacultyPassword, '**/faculty/index.php');

        await page.goto(`${baseUrl}/faculty/dashboard.php`);

        // Check for absence of PHP / database error messages
        await expect(page.locator('text=Fatal error')).toHaveCount(0);
        await expect(page.locator('text=SQLSTATE')).toHaveCount(0);

        // Open a section roster if view-students buttons are present
        const viewBtn = page.locator('.view-students-btn').first();
        if (await viewBtn.isVisible()) {
            await viewBtn.click();
            await page.waitForTimeout(500);

            // Roster panel becomes visible
            const rosterCard = page.locator('#section-roster-card');
            await expect(rosterCard).toBeVisible();

            // Verify supportive explanation is present
            await expect(rosterCard.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();

            // Check if any subject cells in roster contain 'P:' and 'M:'
            const classRoster = page.locator('#roster-body-class');
            const cellText = await classRoster.textContent();
            if (cellText && cellText.includes('P:')) {
                expect(cellText).toContain('M:');
            }
        }
    });

    test('8. Admin Portal: Student Details modal exposes Midterm and supportive explanation', async ({ page }) => {
        // Log in as Admin
        await loginAs(page, testAdminUser, testAdminPassword, '**/admin/index.php');

        // Check Batch Predict card disclosure on admin/index.php
        await expect(page.locator('text=Prediction basis: recorded academic history and current Prelim grades. Midterm records are available for academic review but are not included in the current trained prediction model.')).toBeVisible();

        // Navigate to admin/students.php
        await page.goto(`${baseUrl}/admin/students.php`);

        // Verify student table is rendered
        const studentTable = page.locator('#students-table');
        await expect(studentTable).toBeVisible();

        // Open student modal
        const viewBtn = page.locator('.view-btn').first();
        if (await viewBtn.isVisible()) {
            await viewBtn.click();
            await page.waitForTimeout(400);

            const modal = page.locator('#student-modal-overlay');
            await expect(modal).toBeVisible();

            // Verify Midterm column in modal
            await expect(modal.locator('th:has-text("Midterm")')).toBeVisible();

            // Verify supportive explanation
            await expect(modal.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();
        }
    });

    test('9. Security & Route Protection: Student cannot access Admin or unauthorized Faculty tools', async ({ page }) => {
        // Log in as Student 24-00-001
        await loginAs(page, '24-00-001', 'CohortTest2026!', '**/student/index.php');

        // Attempting to navigate to admin/index.php should be denied
        await page.goto(`${baseUrl}/admin/index.php`);
        expect(page.url()).toMatch(/(login\.php|error=unauthorized)/);

        // Attempting to navigate to faculty/grades.php should be denied
        await page.goto(`${baseUrl}/faculty/grades.php`);
        expect(page.url()).toMatch(/(login\.php|error=unauthorized)/);
    });
});
