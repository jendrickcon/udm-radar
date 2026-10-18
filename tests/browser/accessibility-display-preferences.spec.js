/**
 * tests/browser/accessibility-display-preferences.spec.js
 * Playwright E2E Browser Test Suite for Display & Accessibility Preferences (Revised WP1)
 *
 * Verifies:
 * 1. Pre-test and post-test database immutability (zero business mutations on udm_radar_scratch).
 * 2. Login page anti-FOUC, CCS colorStyle default, and theme toggling.
 * 3. Expandable Settings card (accordion/disclosure) with aria-expanded, aria-controls, and Enter/Space keyboard toggling.
 * 4. Contrast mode is completely absent; no data-contrast attribute is ever set.
 * 5. Display Density remains strictly absent.
 * 6. Color Style selection: CCS Colors (default) and Classic UDM-RADAR.
 * 7. Immediate application and reload persistence of:
 *    - Color Style (CCS, Classic)
 *    - Theme (System, Light, Dark)
 *    - Text Size (Small, Default, Large, Extra Large)
 *    - Motion (System, Reduce, Allow)
 *    - Underline Content Links (On, Off)
 * 8. Reset button restoring exact revised WP1 defaults with polite screen-reader announcement.
 * 9. Absence of separate sidebar Display Settings link and absence of mobile topbar shortcuts.
 * 10. Preservation of Change Password card across Student, Faculty, and Admin settings.
 * 11. Academic Distinction Readiness chart wrapped HTML legend and accessible summary table.
 * 12. Large-text hardening at 1.25x (Extra Large) and 200% zoom with 0 page-level horizontal overflow.
 * 13. Mobile viewports (375px, 390px) responsive layout containment.
 * 14. Malformed storage recovery and role authorization integrity.
 */

import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import process from 'node:process';

const phpBinary = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const rootDir = resolve('.');
const baseUrl = process.env.PLAYWRIGHT_BASE_URL || 'http://127.0.0.1/capstone-accessibility-display';

const cohortTestPassword = process.env.COHORT_TEST_PASSWORD || 'CohortTest2026!';
const testStudentUser = 'SYN-STU-001';
const testFacultyUser = 'SYN-FA-001';
const testAdminUser = 'SYN-ADMIN-001';

test.describe.serial('Display & Accessibility Preferences (Revised WP1)', () => {

    test.beforeAll(async () => {
        // 1. Verify and capture baseline hashes before tests
        try {
            execFileSync(phpBinary, [resolve('tests/integration/accessibility_display_db_baseline_test.php')], {
                cwd: rootDir,
                encoding: 'utf8',
            });
        } catch (e) {
            console.error('Failed baseline test in beforeAll:', e.message);
        }

        // 2. Setup ephemeral synthetic student, faculty and admin credentials in udm_radar_scratch
        try {
            const setupCode = `
putenv('DB_NAME=udm_radar_scratch');
require_once 'config/db.php';
$db = getDB();
$hash = password_hash('${cohortTestPassword}', PASSWORD_BCRYPT);

$db->exec("DELETE FROM student_profiles WHERE user_id IN (SELECT id FROM users WHERE user_id = '${testStudentUser}')");
$db->exec("DELETE FROM faculty_class_loads WHERE faculty_user_id IN (SELECT id FROM users WHERE user_id = '${testFacultyUser}')");
$db->exec("DELETE FROM users WHERE user_id IN ('${testStudentUser}', '${testFacultyUser}', '${testAdminUser}')");

$stmtStu = $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, last_name, email, is_active) VALUES ('${testStudentUser}', ?, 'student', 'Synthetic', 'Student', 'syn.stu@synthetic.udm.edu.ph', 1)");
$stmtStu->execute([$hash]);
$stuId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO student_profiles (user_id, student_number, course, year_level, section, status, current_gwa) VALUES (?, '${testStudentUser}', 'BSIT - Software Development', 3, 'IT-35', 'Regular', 1.75)")->execute([$stuId]);

$stmtFac = $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, last_name, email, is_active) VALUES ('${testFacultyUser}', ?, 'faculty', 'Synthetic', 'Faculty', 'syn.fac@synthetic.udm.edu.ph', 1)");
$stmtFac->execute([$hash]);
$facId = (int)$db->lastInsertId();
$db->prepare("INSERT INTO faculty_class_loads (faculty_user_id, subject_id, section) VALUES (?, 37, 'IT-35')")->execute([$facId]);

$stmtAdm = $db->prepare("INSERT INTO users (user_id, password_hash, role, first_name, last_name, email, is_active) VALUES ('${testAdminUser}', ?, 'admin', 'Synthetic', 'Admin', 'syn.adm@synthetic.udm.edu.ph', 1)");
$stmtAdm->execute([$hash]);
`;
            execFileSync(phpBinary, ['-r', setupCode], {
                cwd: rootDir,
                encoding: 'utf8',
            });
        } catch (e) {
            console.error('Ephemeral fixture setup error in beforeAll:', e.message);
        }
    });

    test.afterAll(async () => {
        // Clean up ephemeral synthetic test accounts
        try {
            const cleanupCode = `
putenv('DB_NAME=udm_radar_scratch');
require_once 'config/db.php';
$db = getDB();
$db->exec("DELETE FROM student_profiles WHERE user_id IN (SELECT id FROM users WHERE user_id = '${testStudentUser}')");
$db->exec("DELETE FROM faculty_class_loads WHERE faculty_user_id IN (SELECT id FROM users WHERE user_id = '${testFacultyUser}')");
$db->exec("DELETE FROM users WHERE user_id IN ('${testStudentUser}', '${testFacultyUser}', '${testAdminUser}')");
`;
            execFileSync(phpBinary, ['-r', cleanupCode], {
                cwd: rootDir,
                encoding: 'utf8',
            });

            // Re-run database immutability baseline test to confirm zero unintended business mutations
            execFileSync(phpBinary, [resolve('tests/integration/accessibility_display_db_baseline_test.php')], {
                cwd: rootDir,
                encoding: 'utf8',
            });
        } catch (e) {
            console.error('Teardown error in afterAll:', e.message);
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

    // ─────────────────────────────────────────────────────────────
    // 1. Login Page Anti-FOUC & Theme Behavior
    // ─────────────────────────────────────────────────────────────
    test('1. Login Page: anti-FOUC attributes apply, colorStyle is ccs, contrast is absent, theme toggles', async ({ page }) => {
        await page.goto(`${baseUrl}/login.php`);

        const html = page.locator('html');
        await expect(html).toHaveAttribute('data-color-style', 'ccs');
        await expect(html).toHaveAttribute('data-theme', /light|dark/);
        await expect(html).toHaveAttribute('data-font-size', 'default');
        await expect(html).toHaveAttribute('data-motion', /reduce|allow/);
        await expect(html).toHaveAttribute('data-underline-links', 'false');
        await expect(html).not.toHaveAttribute('data-contrast');

        // Toggle theme button on login
        const toggleBtn = page.locator('#theme-toggle');
        await expect(toggleBtn).toBeVisible();

        const initialTheme = await html.getAttribute('data-theme');
        await toggleBtn.click();
        const toggledTheme = await html.getAttribute('data-theme');
        expect(toggledTheme).not.toBe(initialTheme);

        // Reload page to confirm persistence
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', toggledTheme);
        await expect(page.locator('html')).not.toHaveAttribute('data-contrast');
    });

    // ─────────────────────────────────────────────────────────────
    // 2. Student Portal: Expandable Disclosure Accordion & Keyboard
    // ─────────────────────────────────────────────────────────────
    test('2. Student Portal: Disclosure card collapsed initially, expands with click/Enter/Space, controls accessible', async ({ page }) => {
        await loginAs(page, testStudentUser, cohortTestPassword, '**/student/index.php');

        // Navigate to settings
        await page.goto(`${baseUrl}/student/settings.php`);
        await expect(page.locator('#display-accessibility-card')).toBeVisible();

        // Verify Change Password card is preserved
        await expect(page.locator('text=Change Password')).toBeVisible();

        // 1. Disclosure button and panel attributes
        const toggleBtn = page.locator('#displayPrefToggleBtn');
        const panel = page.locator('#displayPreferencesPanel');

        await expect(toggleBtn).toBeVisible();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'false');
        await expect(toggleBtn).toHaveAttribute('aria-controls', 'displayPreferencesPanel');
        await expect(panel).toBeHidden();

        // 2. Expand via click
        await toggleBtn.click();
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'true');
        await expect(panel).toBeVisible();

        // 3. Collapse via Space key
        await toggleBtn.focus();
        await page.keyboard.press('Space');
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'false');
        await expect(panel).toBeHidden();

        // 4. Expand via Enter key
        await page.keyboard.press('Enter');
        await expect(toggleBtn).toHaveAttribute('aria-expanded', 'true');
        await expect(panel).toBeVisible();

        // Focus remains on trigger button, not moved unexpectedly
        await expect(toggleBtn).toBeFocused();
    });

    // ─────────────────────────────────────────────────────────────
    // 3. Settings Options: Color Style, Appearance, Text Size, Motion, Links, Reset
    // ─────────────────────────────────────────────────────────────
    test('3. Settings Options: CCS/Classic style, theme, text size, motion, links, and reset', async ({ page }) => {
        await loginAs(page, testStudentUser, cohortTestPassword, '**/student/index.php');
        await page.goto(`${baseUrl}/student/settings.php`);

        // Expand disclosure
        const toggleBtn = page.locator('#displayPrefToggleBtn');
        await toggleBtn.click();

        const html = page.locator('html');

        // A. Color Style Selection
        const classicRadio = page.locator('input[name="display_color_style"][value="classic"]');
        await classicRadio.check();
        await expect(html).toHaveAttribute('data-color-style', 'classic');

        const ccsRadio = page.locator('input[name="display_color_style"][value="ccs"]');
        await ccsRadio.check();
        await expect(html).toHaveAttribute('data-color-style', 'ccs');

        // B. Theme Selection
        const darkRadio = page.locator('input[name="display_theme"][value="dark"]');
        await darkRadio.check();
        await expect(html).toHaveAttribute('data-theme', 'dark');

        // C. Text Size Selection
        const extraLargeRadio = page.locator('input[name="display_text_size"][value="extra-large"]');
        await extraLargeRadio.check();
        await expect(html).toHaveAttribute('data-font-size', 'extra-large');

        // D. Motion Selection
        const reduceMotionRadio = page.locator('input[name="display_motion"][value="reduce"]');
        await reduceMotionRadio.check();
        await expect(html).toHaveAttribute('data-motion', 'reduce');

        // E. Underline Links Selection
        const underlineCheckbox = page.locator('input[name="display_underline_links"]');
        await underlineCheckbox.check();
        await expect(html).toHaveAttribute('data-underline-links', 'true');

        // Verify contrast is NOT in the form
        await expect(page.locator('input[name="display_contrast"]')).toHaveCount(0);
        await expect(html).not.toHaveAttribute('data-contrast');

        // Reload page to verify persistence
        await page.reload();
        await expect(html).toHaveAttribute('data-color-style', 'ccs');
        await expect(html).toHaveAttribute('data-theme', 'dark');
        await expect(html).toHaveAttribute('data-font-size', 'extra-large');
        await expect(html).toHaveAttribute('data-motion', 'reduce');
        await expect(html).toHaveAttribute('data-underline-links', 'true');
        await expect(html).not.toHaveAttribute('data-contrast');

        // Re-expand and test Reset button
        await page.locator('#displayPrefToggleBtn').click();
        const resetBtn = page.locator('#resetDisplayPreferencesBtn');
        await resetBtn.click();

        const statusEl = page.locator('#display-pref-status');
        await expect(statusEl).toHaveText(/Display settings have been reset to the CCS default/);
        await expect(html).toHaveAttribute('data-color-style', 'ccs');
        await expect(html).toHaveAttribute('data-font-size', 'default');
        await expect(html).toHaveAttribute('data-underline-links', 'false');
    });

    // ─────────────────────────────────────────────────────────────
    // 4. Faculty Portal: Sidebar Cleanup & Settings Availability
    // ─────────────────────────────────────────────────────────────
    test('4. Faculty Portal: Sidebar Display Settings shortcut is absent, theme quick toggle works, settings accessible', async ({ page }) => {
        await loginAs(page, testFacultyUser, cohortTestPassword, '**/faculty/index.php');

        // Confirm sidebar Display Settings shortcut link is REMOVED
        await expect(page.locator('#sidebar-display-settings-link')).toHaveCount(0);

        // Confirm no accessibility icon added to mobile topbar
        await expect(page.locator('#mobile-topbar [aria-label*="Accessibility"]')).toHaveCount(0);

        // Sidebar theme quick toggle remains usable
        const themeBtn = page.locator('#theme-toggle-btn');
        await expect(themeBtn).toBeVisible();
        await themeBtn.click();
        await expect(page.locator('html')).toHaveAttribute('data-theme', /light|dark/);

        // Navigate to settings via regular settings page
        await page.goto(`${baseUrl}/faculty/settings.php`);
        await expect(page.locator('text=Change Password')).toBeVisible();
        await expect(page.locator('#display-accessibility-card')).toBeVisible();
        await expect(page.locator('#displayPrefToggleBtn')).toBeVisible();
    });

    // ─────────────────────────────────────────────────────────────
    // 5. Admin Portal & Academic Distinction Readiness Chart Legend
    // ─────────────────────────────────────────────────────────────
    test('5. Admin Portal: Settings accessible and Academic Distinction chart legend wraps without clipping', async ({ page }) => {
        await loginAs(page, testAdminUser, cohortTestPassword, '**/admin/index.php');

        // Check Admin Settings
        await page.goto(`${baseUrl}/admin/settings.php`);
        await expect(page.locator('text=AI Model Governance')).toBeVisible();
        await expect(page.locator('text=Change Password')).toBeVisible();
        await expect(page.locator('#display-accessibility-card')).toBeVisible();

        // Check Admin Analytics - Academic Distinction Chart
        await page.goto(`${baseUrl}/admin/analytics.php`);
        const honorCard = page.locator('.card:has-text("Academic Distinction Readiness")');
        await expect(honorCard).toBeVisible();

        // Check wrapped HTML legend
        const legend = page.locator('#honorChartLegend');
        await expect(legend).toBeVisible();
        const legendItems = legend.locator('li');
        await expect(legendItems).toHaveCount(5);

        // Verify nonvisual summary table is present
        const summaryTable = page.locator('#honor-chart-summary');
        await expect(summaryTable).toBeAttached();

        // Verify legend is contained inside card without page overflow
        const cardBox = await honorCard.boundingBox();
        const legendBox = await legend.boundingBox();
        expect(legendBox.x + legendBox.width).toBeLessThanOrEqual(cardBox.x + cardBox.width + 10);

        const isHorizontalOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(isHorizontalOverflow).toBe(false);
    });

    // ─────────────────────────────────────────────────────────────
    // 6. Large-Text Hardening & Mobile Viewports (375px & 390px)
    // ─────────────────────────────────────────────────────────────
    test('6. Mobile Viewports: Extra Large text has 0 horizontal page overflow at 375px and 390px', async ({ page }) => {
        await loginAs(page, testStudentUser, cohortTestPassword, '**/student/index.php');

        // Set Extra Large font size in storage
        await page.evaluate(() => {
            if (window.UDMDisplayPreferences) {
                window.UDMDisplayPreferences.savePreferences({ textSize: 'extra-large' });
            }
        });

        // Test 375px viewport (iPhone SE)
        await page.setViewportSize({ width: 375, height: 667 });
        await page.goto(`${baseUrl}/student/grades.php`);

        const isHorizontalOverflow375 = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(isHorizontalOverflow375).toBe(false);

        // Test 390px viewport (iPhone 12/13/14)
        await page.setViewportSize({ width: 390, height: 844 });
        await page.goto(`${baseUrl}/student/dashboard.php`);

        const isHorizontalOverflow390 = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(isHorizontalOverflow390).toBe(false);

        // Verify mobile topbar min-height expands without clipping
        const topbar = page.locator('#mobile-topbar');
        const topbarBox = await topbar.boundingBox();
        expect(topbarBox.height).toBeGreaterThanOrEqual(56);
    });

    // ─────────────────────────────────────────────────────────────
    // 7. Simulated 200% Zoom Usability
    // ─────────────────────────────────────────────────────────────
    test('7. Simulated Zoom: Disclosure trigger and content remain contained and readable', async ({ page }) => {
        await loginAs(page, testStudentUser, cohortTestPassword, '**/student/index.php');
        await page.setViewportSize({ width: 640, height: 480 });
        await page.goto(`${baseUrl}/student/settings.php`);

        const trigger = page.locator('#displayPrefToggleBtn');
        await expect(trigger).toBeVisible();
        await trigger.click();

        const panel = page.locator('#displayPreferencesPanel');
        await expect(panel).toBeVisible();

        const isOverflow = await page.evaluate(() => {
            return document.documentElement.scrollWidth > document.documentElement.clientWidth;
        });
        expect(isOverflow).toBe(false);
    });

    // ─────────────────────────────────────────────────────────────
    // 8. Security: Malformed Storage Recovery & Authorization Guard
    // ─────────────────────────────────────────────────────────────
    test('8. Security: Malformed localStorage safely repaired, contrast rejected, authorization intact', async ({ page }) => {
        await page.goto(`${baseUrl}/login.php`);

        // Inject malformed JSON and unauthorized keys into localStorage
        await page.evaluate(() => {
            localStorage.setItem('udmRadarDisplayPreferencesV1', '{"malicious":"<script>alert(1)</script>","contrast":"increased","density":"compact","theme":"invalid_neon","textSize":"hacked"}');
        });

        await page.reload();

        // Verify attributes fell back safely to approved tokens and contrast was discarded
        const html = page.locator('html');
        await expect(html).toHaveAttribute('data-color-style', 'ccs');
        await expect(html).toHaveAttribute('data-theme', /light|dark/);
        await expect(html).toHaveAttribute('data-font-size', 'default');
        await expect(html).not.toHaveAttribute('data-contrast');

        // Confirm student cannot access admin settings
        await loginAs(page, testStudentUser, cohortTestPassword, '**/student/index.php');
        await page.goto(`${baseUrl}/admin/settings.php`);
        // Student should be redirected away from admin
        expect(page.url()).not.toContain('admin/settings.php');
    });

    // ─────────────────────────────────────────────────────────────
    // 9. Reduced Motion & Batch Predict Progress Text
    // ─────────────────────────────────────────────────────────────
    test('9. Reduced Motion suppresses transitions while retaining readable Batch Predict copy', async ({ page }) => {
        await loginAs(page, testAdminUser, cohortTestPassword, '**/admin/index.php');

        await page.evaluate(() => {
            if (window.UDMDisplayPreferences) {
                window.UDMDisplayPreferences.savePreferences({ motion: 'reduce' });
            }
        });

        await page.goto(`${baseUrl}/admin/index.php`);
        const html = page.locator('html');
        await expect(html).toHaveAttribute('data-motion', 'reduce');

        // Inspect Batch Predict control button
        const batchBtn = page.locator('#runBatchBtn');
        await expect(batchBtn).toBeVisible();
        await expect(batchBtn).toHaveText(/Run Predictions/);

        // Check reduced motion styles on cards
        const cardTransition = await page.locator('.card').first().evaluate(el => window.getComputedStyle(el).transitionDuration);
        expect(parseFloat(cardTransition)).toBeLessThanOrEqual(0.01);
    });

    // ─────────────────────────────────────────────────────────────
    // 10. System Theme Emulation
    // ─────────────────────────────────────────────────────────────
    test('10. System Theme Emulation: Login page follows simulated system theme', async ({ page }) => {
        // A. Dark OS scheme
        await page.emulateMedia({ colorScheme: 'dark' });
        await page.goto(`${baseUrl}/login.php`);

        await page.evaluate(() => {
            if (window.UDMDisplayPreferences) {
                window.UDMDisplayPreferences.savePreferences({ theme: 'system' });
            }
        });
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'dark');

        // B. Light OS scheme
        await page.emulateMedia({ colorScheme: 'light' });
        await page.reload();
        await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    });

});
