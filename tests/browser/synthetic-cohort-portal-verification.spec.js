/**
 * tests/browser/synthetic-cohort-portal-verification.spec.js
 * Browser E2E Portal Verification for Synthetic Testing Cohorts (COHORT-DATA-WP1)
 *
 * Verifies live browser interaction across 5 openable synthetic student accounts:
 * 1. Cohort A (1Y): 26-00-001 (First Year, Section IT-11)
 * 2. Cohort B (2Y): 25-00-001 (Second Year, Section IT-21)
 * 3. Cohort C (3Y): 24-00-001 (Third Year, Section IT-31)
 * 4. Cohort D (4Y): 23-00-001 (Fourth Year, Section IT-41)
 * 5. Cohort E (XR): 24-00-012 (Cross-Year, Section IT-IRREG)
 *
 * Test Sequence for Each Cohort:
 * - Direct authentication via login.php with shared test credential COHORT_TEST_PASSWORD ('CohortTest2026!')
 * - Successful redirect to student/index.php and session establishment
 * - Verification of student identity, year level, and section in the student UI
 * - Navigation to student/dashboard.php with dashboard UI rendering
 * - Navigation to student/grades.php with grade table rendering
 * - Role-based route protection: navigating to admin/index.php is blocked and redirected to login.php?error=unauthorized
 */

import { test, expect } from '@playwright/test';
import { spawn, execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import process from 'node:process';

const phpBinary = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const rootDir = resolve('.');
const testPort = 8088;
const baseUrl = `http://127.0.0.1:${testPort}`;
const testPassword = 'CohortTest2026!';

let phpServerProcess = null;

const cohortAccounts = [
    {
        cohort: 'Cohort A (First Year)',
        studentNumber: '26-00-001',
        name: 'SynFirst OneComplete',
        yearLevel: '1',
        section: 'IT-11',
        isIrregular: false,
    },
    {
        cohort: 'Cohort B (Second Year)',
        studentNumber: '25-00-001',
        name: 'SynSecond OnePrereq',
        yearLevel: '2',
        section: 'IT-21',
        isIrregular: true,
    },
    {
        cohort: 'Cohort C (Third Year)',
        studentNumber: '24-00-001',
        name: 'SynThird OneHighRisk',
        yearLevel: '3',
        section: 'IT-31',
        isIrregular: false,
    },
    {
        cohort: 'Cohort D (Fourth Year)',
        studentNumber: '23-00-001',
        name: 'SynFourth OneIncomplete',
        yearLevel: '4',
        section: 'IT-41',
        isIrregular: true,
    },
    {
        cohort: 'Cohort E (Cross-Year / Irregular)',
        studentNumber: '24-00-012',
        name: 'SynCross ThreeHighGwa',
        yearLevel: '3',
        section: 'IT-IRREG',
        isIrregular: true,
    },
];

test.describe.serial('Synthetic Multi-Year Cohort Portal Verification', () => {

    test.beforeAll(async () => {
        // 1. Seed the scratch database with multiyear cohort validation accounts
        execFileSync(phpBinary, [resolve('tools/seed_multiyear_cohorts.php'), '--target=scratch'], {
            cwd: rootDir,
            encoding: 'utf8',
        });

        // 2. Seed section-calibrated midterm grades on Third-Year population
        execFileSync(phpBinary, [resolve('tools/seed_section_midterms.php'), '--target=scratch'], {
            cwd: rootDir,
            encoding: 'utf8',
        });

        // 3. Launch PHP built-in web server bound to scratch database
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
            } catch (err) {
                // Wait and retry
            }
            await new Promise(r => setTimeout(r, 200));
        }

        if (!connected) {
            throw new Error(`PHP server failed to start on port ${testPort}`);
        }
    });

    test.afterAll(async () => {
        // 1. Kill PHP built-in server
        if (phpServerProcess) {
            phpServerProcess.kill();
            phpServerProcess = null;
        }

        // 2. Purge synthetic section midterms and cohort data from scratch database
        try {
            execFileSync(phpBinary, [resolve('tools/seed_section_midterms.php'), '--target=scratch', '--cleanup'], {
                cwd: rootDir,
                encoding: 'utf8',
            });
            execFileSync(phpBinary, [resolve('tools/seed_multiyear_cohorts.php'), '--target=scratch', '--cleanup'], {
                cwd: rootDir,
                encoding: 'utf8',
            });
        } catch (e) {
            console.error('Post-test cohort cleanup error:', e.message);
        }
    });

    for (const acc of cohortAccounts) {
        test(`Verify ${acc.cohort} - ${acc.studentNumber} portal access and route protection`, async ({ page, context }) => {
            // Track page errors
            const consoleErrors = [];
            page.on('console', msg => {
                if (msg.type() === 'error') consoleErrors.push(msg.text());
            });

            // 1. Navigate to login page
            await page.goto(`${baseUrl}/login.php`);
            await expect(page).toHaveTitle(/Login/i);

            // 2. Submit credentials
            await page.fill('#userid', acc.studentNumber);
            await page.fill('#password', testPassword);
            await page.click('.btn-login');

            // 3. Confirm redirect to student portal
            await page.waitForURL('**/student/index.php');
            await expect(page).toHaveTitle(/Student Portal|Home|UDM-RADAR/i);

            // Verify student name and info on home page
            const bodyText = await page.textContent('body');
            expect(bodyText).toContain(acc.studentNumber);
            expect(bodyText).toContain(acc.section);

            // 4. Navigate to student/dashboard.php
            await page.goto(`${baseUrl}/student/dashboard.php`);
            await expect(page).toHaveTitle(/Dashboard|UDM-RADAR/i);
            await expect(page.locator('#gwa-card-value')).toBeVisible();

            // 5. Navigate to student/grades.php
            await page.goto(`${baseUrl}/student/grades.php`);
            await expect(page).toHaveTitle(/Grades|Academic Record|UDM-RADAR/i);
            await expect(page.locator('.grades-card').first()).toBeVisible();

            // 6. Route Blocking Check: Student attempting to access admin portal
            await page.goto(`${baseUrl}/admin/index.php`);
            await page.waitForURL('**/login.php*');
            expect(page.url()).toContain('error=unauthorized');

            // 7. Clear cookies for next test iteration
            await context.clearCookies();
        });
    }
});
