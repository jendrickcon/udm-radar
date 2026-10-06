import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');
const adminFacultyPhp = readFileSync(resolve('admin/faculty.php'), 'utf-8');
const adminActivityPhp = readFileSync(resolve('admin/activity.php'), 'utf-8');
const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');

test.describe('DATA-UI-WP1B: Visual Table Foundation', () => {

    test.describe('1. Static Code Analysis & Design Token Integrity', () => {
        test('CSS contains all required container, header, density, footer, and button tokens', () => {
            // Container tokens
            expect(dashboardCss).toContain('.data-table-card');
            expect(dashboardCss).toContain('.data-table-card.data-table-card--active');
            expect(dashboardCss).toContain('.data-table-card--operational');

            // Header & Toolbar tokens
            expect(dashboardCss).toContain('.data-table-header');
            expect(dashboardCss).toContain('.data-table-header__intro');
            expect(dashboardCss).toContain('.data-table-title');
            expect(dashboardCss).toContain('.data-table-subtitle');
            expect(dashboardCss).toContain('.data-table-actions');
            expect(dashboardCss).toContain('.data-table-toolbar');

            // Table, column & density tokens
            expect(dashboardCss).toContain('.data-table');
            expect(dashboardCss).toContain('.table-col-action');
            expect(dashboardCss).toContain('.table-density--compact');
            expect(dashboardCss).toContain('.table-density--standard');
            expect(dashboardCss).toContain('.table-density--comfortable');
            expect(dashboardCss).toContain('.data-table-footer');

            // Scoped Button System tokens
            expect(dashboardCss).toContain('.btn');
            expect(dashboardCss).toContain('.btn--primary');
            expect(dashboardCss).toContain('.btn--secondary');
            expect(dashboardCss).toContain('.btn--quiet');
            expect(dashboardCss).toContain('.btn--danger');
            expect(dashboardCss).toContain('.btn--sm');

            // Must NOT use !important on active table card border
            expect(dashboardCss).not.toMatch(/\.data-table-card--active\s*\{[^}]*!important/);
        });

        test('Admin Students: Implements approved table card, two-tier header, standard density, actions col, and footer', () => {
            expect(adminStudentsPhp).toContain('data-table-card');
            expect(adminStudentsPhp).toContain('data-table-header');
            expect(adminStudentsPhp).toContain('data-table-toolbar');
            expect(adminStudentsPhp).toContain('class="data-table table-density--standard"');
            expect(adminStudentsPhp).toContain('<th scope="col" class="table-col-action">Actions</th>');
            expect(adminStudentsPhp).toContain('class="btn btn--secondary"'); // Export CSV
            expect(adminStudentsPhp).toContain('class="btn btn--quiet btn--sm"'); // Edit
            expect(adminStudentsPhp).toContain('class="btn btn--danger btn--sm"'); // Archive
            expect(adminStudentsPhp).toContain('class="data-table-footer pagination-bar"');
        });

        test('Admin Faculty: Implements approved table card, header, toolbar, standard density, actions col, and footer', () => {
            expect(adminFacultyPhp).toContain('data-table-card');
            expect(adminFacultyPhp).toContain('data-table-header');
            expect(adminFacultyPhp).toContain('data-table-toolbar');
            expect(adminFacultyPhp).toContain('class="data-table table-density--standard"');
            expect(adminFacultyPhp).toContain('<th scope="col" class="table-col-action">Actions</th>');
            expect(adminFacultyPhp).toContain('class="btn btn--quiet btn--sm"'); // Edit
            expect(adminFacultyPhp).toContain('class="btn btn--danger btn--sm"'); // Delete
            expect(adminFacultyPhp).toContain('class="data-table-footer pagination-bar"');
        });

        test('Admin Activity: Academic Support Oversight is compact operational and Closed Feedback is comfortable', () => {
            // Tab 3: Academic Support Oversight
            expect(adminActivityPhp).toContain('data-table-card data-table-card--operational');
            expect(adminActivityPhp).toContain('class="data-table table-density--compact"');
            expect(adminActivityPhp).toContain('class="btn btn--secondary"'); // Export CSV
            expect(adminActivityPhp).toContain('class="btn btn--primary"'); // Download PDF

            // Tab 4: Closed Feedback History
            expect(adminActivityPhp).toContain('Closed Feedback History');
            expect(adminActivityPhp).toContain('class="data-table table-density--comfortable"');
            expect(adminActivityPhp).toContain('class="btn btn--secondary btn--sm"'); // View button
        });

        test('Admin Grades: Active Section Roster uses data-table-card--active, standard density, and scoped Close button', () => {
            expect(adminGradesPhp).toContain('data-table-card data-table-card--active roster-panel');
            expect(adminGradesPhp).toContain('class="data-table table-density--standard"');
            expect(adminGradesPhp).toContain('class="btn btn--secondary btn--sm"'); // Close button
        });
    });

    test.describe('2. Semantic Border System (Neutral vs Active Drilldown)', () => {
        test('Default table card has 1px neutral border, while active table card has 2px accent blue border', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --card-bg: #ffffff;
                            --border-color: #e5e7eb;
                            --accent-blue: #1e4db7;
                            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
                        }
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <div id="standard-card" class="data-table-card">
                        <div class="data-table-header">Standard Table</div>
                    </div>
                    <div id="active-card" class="data-table-card data-table-card--active">
                        <div class="data-table-header">Active Detail Roster</div>
                    </div>
                </body>
                </html>
            `);

            const standardCard = page.locator('#standard-card');
            const activeCard = page.locator('#active-card');

            // Standard card border check: 1px border width, neutral border color
            const stdBorderWidth = await standardCard.evaluate(el => window.getComputedStyle(el).borderTopWidth);
            const stdBorderColor = await standardCard.evaluate(el => window.getComputedStyle(el).borderTopColor);
            expect(stdBorderWidth).toBe('1px');
            expect(stdBorderColor).toBe('rgb(226, 232, 240)'); // #E2E8F0

            // Active card border check: 2px border width, accent-blue border color
            const actBorderWidth = await activeCard.evaluate(el => window.getComputedStyle(el).borderTopWidth);
            const actBorderColor = await activeCard.evaluate(el => window.getComputedStyle(el).borderTopColor);
            expect(actBorderWidth).toBe('2px');
            expect(actBorderColor).toBe('rgb(30, 77, 183)'); // #1E4DB7
        });
    });

    test.describe('3. Assigned Density System (Compact, Standard, Comfortable)', () => {
        test('Density classes apply correct vertical and horizontal cell padding', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --card-bg: #ffffff;
                            --border-color: #e5e7eb;
                            --table-header-bg: #f8fafc;
                            --text-dark: #1f2937;
                        }
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <table id="compact-tbl" class="data-table table-density--compact">
                        <thead><tr><th>Col A</th></tr></thead>
                        <tbody><tr><td>Data A</td></tr></tbody>
                    </table>

                    <table id="standard-tbl" class="data-table table-density--standard">
                        <thead><tr><th>Col B</th></tr></thead>
                        <tbody><tr><td>Data B</td></tr></tbody>
                    </table>

                    <table id="comfortable-tbl" class="data-table table-density--comfortable">
                        <thead><tr><th>Col C</th></tr></thead>
                        <tbody><tr><td>Data C</td></tr></tbody>
                    </table>
                </body>
                </html>
            `);

            // Compact: 10px vertical, 16px horizontal
            const compactTh = page.locator('#compact-tbl th');
            const compactTd = page.locator('#compact-tbl td');
            expect(await compactTh.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('10px');
            expect(await compactTh.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('16px');
            expect(await compactTd.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('10px');
            expect(await compactTd.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('16px');

            // Standard: 12px vertical, 16px horizontal
            const standardTh = page.locator('#standard-tbl th');
            const standardTd = page.locator('#standard-tbl td');
            expect(await standardTh.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('12px');
            expect(await standardTh.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('16px');
            expect(await standardTd.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('12px');
            expect(await standardTd.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('16px');

            // Comfortable: 16px vertical, 24px horizontal
            const comfortableTh = page.locator('#comfortable-tbl th');
            const comfortableTd = page.locator('#comfortable-tbl td');
            expect(await comfortableTh.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('16px');
            expect(await comfortableTh.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('24px');
            expect(await comfortableTd.evaluate(el => window.getComputedStyle(el).paddingTop)).toBe('16px');
            expect(await comfortableTd.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('24px');
        });
    });

    test.describe('4. Full-Bleed Card Architecture (Edge-to-Edge Table with Padded Controls)', () => {
        test('Card container has zero padding, while header and toolbar have 24px padding', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --card-bg: #ffffff;
                            --border-color: #e5e7eb;
                            --table-header-bg: #f8fafc;
                            --text-dark: #1f2937;
                            --text-gray: #6b7280;
                            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
                        }
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <div id="test-card" class="data-table-card" style="width: 800px;">
                        <div class="data-table-header">
                            <div class="data-table-header__intro">
                                <h3 class="data-table-title">Student Directory</h3>
                                <p class="data-table-subtitle">Cohort management</p>
                            </div>
                            <div class="data-table-actions">
                                <button class="btn btn--secondary">Export CSV</button>
                            </div>
                        </div>
                        <div class="data-table-toolbar">
                            <input type="text" placeholder="Search...">
                        </div>
                        <div class="data-table-scroll">
                            <table class="data-table table-density--standard">
                                <thead><tr><th>Col A</th><th class="table-col-action">Actions</th></tr></thead>
                                <tbody><tr><td>Value A</td><td class="table-col-action"><button class="btn btn--quiet btn--sm">Edit</button></td></tr></tbody>
                            </table>
                        </div>
                        <div class="data-table-footer">
                            <span>Showing 1 to 10</span>
                        </div>
                    </div>
                </body>
                </html>
            `);

            const card = page.locator('#test-card');
            const header = page.locator('.data-table-header');
            const toolbar = page.locator('.data-table-toolbar');
            const footer = page.locator('.data-table-footer');

            // Card container must have 0px padding (full-bleed)
            expect(await card.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('0px');
            expect(await card.evaluate(el => window.getComputedStyle(el).paddingRight)).toBe('0px');

            // Header and toolbar retain comfortable internal padding (24px)
            expect(await header.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('24px');
            expect(await toolbar.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('24px');
            expect(await footer.evaluate(el => window.getComputedStyle(el).paddingLeft)).toBe('24px');
        });
    });

    test.describe('5. Scoped Button System (Base + Modifier)', () => {
        test('Buttons render with correct styles and variants', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --card-bg: #ffffff;
                            --border-color: #e5e7eb;
                            --accent-blue: #1e4db7;
                            --text-dark: #1f2937;
                            --risk-high: #dc2626;
                        }
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <button id="btn-pri" class="btn btn--primary">Primary</button>
                    <button id="btn-sec" class="btn btn--secondary">Secondary</button>
                    <button id="btn-quiet" class="btn btn--quiet btn--sm">Quiet Sm</button>
                    <button id="btn-danger" class="btn btn--danger btn--sm">Danger Sm</button>
                </body>
                </html>
            `);

            // Primary: background accent-blue
            const priBg = await page.locator('#btn-pri').evaluate(el => window.getComputedStyle(el).backgroundColor);
            expect(priBg).toBe('rgb(30, 77, 183)');

            // Secondary: border-color border-color
            const secBorder = await page.locator('#btn-sec').evaluate(el => window.getComputedStyle(el).borderTopColor);
            expect(secBorder).toBe('rgb(226, 232, 240)');

            // Quiet Sm: background transparent, color accent-blue, smaller padding
            const quietBg = await page.locator('#btn-quiet').evaluate(el => window.getComputedStyle(el).backgroundColor);
            const quietPad = await page.locator('#btn-quiet').evaluate(el => window.getComputedStyle(el).paddingTop);
            expect(quietBg).toBe('rgba(0, 0, 0, 0)');
            expect(quietPad).toBe('4px');

            // Danger Sm: color risk-high, smaller padding
            const dangerColor = await page.locator('#btn-danger').evaluate(el => window.getComputedStyle(el).color);
            expect(dangerColor).toBe('rgb(220, 38, 38)');
        });
    });

    test.describe('6. Mobile Containment at 390px Viewport', () => {
        test('Full-bleed card and wide table do not blow out 390px page viewport', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        :root {
                            --card-bg: #ffffff;
                            --border-color: #e5e7eb;
                            --table-header-bg: #f8fafc;
                            --text-dark: #1f2937;
                            --text-gray: #6b7280;
                            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
                        }
                        ${dashboardCss}
                        body { margin: 0; padding: 16px; box-sizing: border-box; }
                    </style>
                </head>
                <body>
                    <div class="data-table-card">
                        <div class="data-table-header">
                            <div class="data-table-header__intro">
                                <h3 class="data-table-title">Mobile Test</h3>
                                <p class="data-table-subtitle">Viewport test</p>
                            </div>
                        </div>
                        <div class="data-table-scroll" role="region" aria-label="Mobile Test Table" tabindex="0">
                            <table class="data-table table-density--standard" style="min-width: 800px;">
                                <thead>
                                    <tr><th>Col 1</th><th>Col 2</th><th>Col 3</th><th>Col 4</th><th>Col 5</th></tr>
                                </thead>
                                <tbody>
                                    <tr><td>A</td><td>B</td><td>C</td><td>D</td><td>E</td></tr>
                                </tbody>
                            </table>
                        </div>
                        <div class="data-table-footer">
                            <span>Footer info</span>
                        </div>
                    </div>
                </body>
                </html>
            `);

            // Verify document scrollWidth does NOT exceed viewport width
            const docWidth = await page.evaluate(() => document.documentElement.scrollWidth);
            const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
            expect(docWidth).toBeLessThanOrEqual(390);
            expect(bodyWidth).toBeLessThanOrEqual(390);

            // Verify internal scroll region scrolls horizontally
            const regionScrollWidth = await page.locator('.data-table-scroll').evaluate(el => el.scrollWidth);
            const regionClientWidth = await page.locator('.data-table-scroll').evaluate(el => el.clientWidth);
            expect(regionScrollWidth).toBeGreaterThan(regionClientWidth);
        });
    });

    test.describe('7. Theme Parity (Light and Dark Mode)', () => {
        test('Table card and header backgrounds update correctly with theme switch', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <div class="data-table-card" id="theme-card">
                        <div class="data-table-header" id="theme-header">Title</div>
                        <table class="data-table table-density--standard">
                            <thead><tr id="theme-row"><th>Head</th></tr></thead>
                        </table>
                    </div>
                </body>
                </html>
            `);

            // Light mode assertions
            const lightCardBg = await page.locator('#theme-card').evaluate(el => window.getComputedStyle(el).backgroundColor);
            expect(lightCardBg).toBe('rgb(255, 255, 255)');

            // Switch to dark theme
            await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
            const darkCardBg = await page.locator('#theme-card').evaluate(el => window.getComputedStyle(el).backgroundColor);
            expect(darkCardBg).toBe('rgb(30, 41, 59)'); // #1E293B
        });
    });
});
