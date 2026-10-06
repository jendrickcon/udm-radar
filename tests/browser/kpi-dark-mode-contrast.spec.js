import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import process from 'node:process';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const adminIndexPhp = readFileSync(resolve('admin/index.php'), 'utf-8');
const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');
const adminActivityPhp = readFileSync(resolve('admin/activity.php'), 'utf-8');

const evidenceDir = resolve('test-results/evidence/kpi-dark-mode');
mkdirSync(evidenceDir, { recursive: true });

test.describe('KPI Dark-Mode Contrast & Action-Hint Audit Verification', () => {

    // ─────────────────────────────────────────────────────────────
    // 1. Source Inspection: CSS Tokens & Semantic Class Verification
    // ─────────────────────────────────────────────────────────────
    test.describe('[Source inspection] CSS Tokens & Selector Hierarchy', () => {
        test('assets/css/dashboard.css defines .stat-card-subtext and .kpi-card__description using var(--text-gray)', () => {
            expect(dashboardCss).toContain('.stat-card-subtext,');
            expect(dashboardCss).toContain('.kpi-card__description');
            
            // Confirm the rule uses var(--text-gray)
            const kpiDescMatch = dashboardCss.match(/\.stat-card-subtext,\s*\.kpi-card__description\s*\{([^}]+)\}/);
            expect(kpiDescMatch).not.toBeNull();
            const ruleBody = kpiDescMatch[1];
            expect(ruleBody).toContain('color: var(--text-gray);');
            expect(ruleBody).toContain('font-size: 0.75rem;');
            expect(ruleBody).toContain('margin-top: 4px;');
            expect(ruleBody).toContain('font-weight: 600;');
            expect(ruleBody).toContain('line-height: 1.4;');

            // Defensive color inheritance on button KPI cards
            const buttonActionMatch = dashboardCss.match(/button\.stat-card\.kpi-card--action,\s*a\.stat-card\.kpi-card--action\s*\{([^}]+)\}/);
            expect(buttonActionMatch).not.toBeNull();
            expect(buttonActionMatch[1]).toContain('color: inherit;');
        });

        test('Source inspection: Redundant title attributes removed and aria-label attributes present', () => {
            // admin/students.php
            expect(adminStudentsPhp).not.toContain('title="Click to view all students"');
            expect(adminStudentsPhp).not.toContain('title="Click to filter High Risk students"');
            expect(adminStudentsPhp).not.toContain('title="Click to filter Moderate Risk students"');
            expect(adminStudentsPhp).not.toContain('title="Click to filter Active Support cases"');
            expect(adminStudentsPhp).toContain('aria-label="Filter student directory: view all students"');
            expect(adminStudentsPhp).toContain('aria-label="Filter student directory: High Risk students"');
            expect(adminStudentsPhp).toContain('aria-label="Filter student directory: Moderate Risk students"');
            expect(adminStudentsPhp).toContain('aria-label="Filter student directory: Active Support cases"');
            expect(adminStudentsPhp).toContain('class="stat-card-subtext kpi-card__description"');

            // admin/index.php
            expect(adminIndexPhp).not.toContain('title="Click to view all students in table"');
            expect(adminIndexPhp).not.toContain('title="Click to filter table to At-Risk students"');
            expect(adminIndexPhp).not.toContain('title="Click to filter table to Irregular students"');
            expect(adminIndexPhp).not.toContain('title="Click to filter table to students missing predictions"');
            expect(adminIndexPhp).toContain('aria-label="Filter student table: view all students"');
            expect(adminIndexPhp).toContain('aria-label="Filter student table: At-Risk students"');
            expect(adminIndexPhp).toContain('aria-label="Filter student table: Irregular students"');
            expect(adminIndexPhp).toContain('aria-label="Filter student table: students missing predictions"');
            expect(adminIndexPhp).toContain('class="stat-card-subtext kpi-card__description"');

            // admin/activity.php
            expect(adminActivityPhp).not.toContain('title="Switch to Inbox tab"');
            expect(adminActivityPhp).not.toContain('title="Switch to Approvals tab"');
            expect(adminActivityPhp).not.toContain('title="Switch to Academic Support tab"');
            expect(adminActivityPhp).toContain('aria-label="Open Reports: switch to Inbox tab"');
            expect(adminActivityPhp).toContain('aria-label="Pending Approvals: switch to Approvals tab"');
            expect(adminActivityPhp).toContain('aria-label="Support Reviews: switch to Academic Support tab"');
            expect(adminActivityPhp).toContain('class="stat-card-subtext kpi-card__description"');
        });

        test('Source inspection: Average Cumulative GWA retains tooltip with approved methodology text', () => {
            expect(adminIndexPhp).toContain('Historical cumulative cohort metric');
            expect(adminIndexPhp).toContain('Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.');
            expect(adminIndexPhp).not.toContain('Historical across active cohort');
        });

        test('Source inspection: Visible action cues and filtering onclick handlers remain unchanged', () => {
            // Visible action cues
            expect(adminStudentsPhp).toContain('View all students &rarr;');
            expect(adminStudentsPhp).toContain('Filter High Risk &rarr;');
            expect(adminStudentsPhp).toContain('Filter Moderate Risk &rarr;');
            expect(adminStudentsPhp).toContain('Filter support cases &rarr;');
            
            expect(adminIndexPhp).toContain('View all students &rarr;');
            expect(adminIndexPhp).toContain('Filter at-risk &rarr;');
            expect(adminIndexPhp).toContain('Filter irregular &rarr;');
            expect(adminIndexPhp).toContain('Filter unpredicted &rarr;');

            expect(adminActivityPhp).toContain('Open Inbox &rarr;');
            expect(adminActivityPhp).toContain('Review Approvals &rarr;');
            expect(adminActivityPhp).toContain('View Support &rarr;');

            // Handlers
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('HIGH', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('MODERATE', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('', 'active')\"");

            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', ''); applyTableFilter('status', '');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', 'AT_RISK');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('status', 'Irregular');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', 'N/A');\"");

            expect(adminActivityPhp).toContain("onclick=\"switchTab('inbox', true)\"");
            expect(adminActivityPhp).toContain("onclick=\"switchTab('approvals', true)\"");
            expect(adminActivityPhp).toContain("onclick=\"switchTab('support', true)\"");
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 2. Generated HTML: DOM Rendering & Contrast Across Themes
    // ─────────────────────────────────────────────────────────────
    test.describe('[Generated HTML] Computed Contrast & Theme Verification', () => {
        const renderDashboardHtml = (theme = 'light') => `
            <!DOCTYPE html>
            <html lang="en" data-theme="${theme}">
            <head>
                <meta charset="UTF-8">
                <style>${dashboardCss}</style>
            </head>
            <body style="background: var(--bg-color); padding: 24px;">
                <div class="stat-grid" id="admin-dashboard-kpis" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); margin-bottom: 24px;">
                    <button type="button" class="stat-card kpi-card--action" id="kpi-total" style="border-left-color: var(--accent-blue) !important;" onclick="window.kpiAction('total')" aria-controls="table-card" aria-label="Filter student table: view all students">
                        <h4>Total Students</h4>
                        <h2 style="color: var(--text-dark);">291</h2>
                        <div class="stat-card-subtext kpi-card__description">Active cohort population</div>
                        <span class="kpi-action-cue">View all students &rarr;</span>
                    </button>
                    
                    <button type="button" class="stat-card kpi-card--action red" id="kpi-at-risk" style="border-left-color: var(--risk-high) !important;" onclick="window.kpiAction('at-risk')" aria-controls="table-card" aria-label="Filter student table: At-Risk students">
                        <h4>At-Risk</h4>
                        <h2 id="at-risk-val" style="margin-bottom: 2px; color: var(--risk-high);">16</h2>
                        <div style="font-size: 0.8rem; font-weight: 700; color: var(--risk-high); margin-top: 4px;">
                            6 High <span style="color: var(--text-gray); font-weight: normal; margin: 0 4px;">|</span> <span style="color: var(--risk-mod);">10 Moderate</span>
                        </div>
                        <span class="kpi-action-cue">Filter at-risk &rarr;</span>
                    </button>

                    <button type="button" class="stat-card kpi-card--action" id="kpi-irregular" style="border-left-color: var(--gold) !important;" onclick="window.kpiAction('irregular')" aria-controls="table-card" aria-label="Filter student table: Irregular students">
                        <h4>Irregular</h4>
                        <h2 id="irregular-val" style="color: var(--gold);">45</h2>
                        <div class="stat-card-subtext kpi-card__description">Non-standard curriculum progression</div>
                        <span class="kpi-action-cue">Filter irregular &rarr;</span>
                    </button>
                    
                    <div class="stat-card" id="kpi-gwa" style="border-left-color: var(--teal) !important;">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h4>Avg Cumulative GWA</h4>
                            <span class="custom-tooltip tooltip-top-right" tabindex="0" aria-label="Calculation Scope">
                                <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                <span class="tooltip-text" role="tooltip">Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.</span>
                            </span>
                        </div>
                        <h2 style="color: var(--text-dark);">1.84</h2>
                        <div class="stat-card-subtext kpi-card__description">Historical cumulative cohort metric</div>
                    </div>
                    
                    <button type="button" class="stat-card kpi-card--action" id="kpi-nopredict" style="border-left-color: var(--text-gray) !important;" onclick="window.kpiAction('nopredict')" aria-controls="table-card" aria-label="Filter student table: students missing predictions">
                        <h4>No Prediction Yet</h4>
                        <h2 style="color:var(--text-gray);">12</h2>
                        <div class="stat-card-subtext kpi-card__description">Excluded from At-Risk count</div>
                        <span class="kpi-action-cue">Filter unpredicted &rarr;</span>
                    </button>
                </div>

                <div class="stat-grid" id="admin-students-kpis" style="margin-bottom: 24px;">
                    <button type="button" class="stat-card kpi-card--action" id="kpi-students-total" style="border-left-color: var(--text-dark) !important;" onclick="window.kpiAction('students-total')" aria-controls="database-section" aria-label="Filter student directory: view all students">
                        <h4>Total Students</h4>
                        <h2>291</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-students-total">Active population</div>
                        <span class="kpi-action-cue">View all students &rarr;</span>
                    </button>
                    <button type="button" class="stat-card kpi-card--action" id="kpi-students-high" style="border-left-color: var(--risk-high) !important;" onclick="window.kpiAction('students-high')" aria-controls="database-section" aria-label="Filter student directory: High Risk students">
                        <h4>High Risk</h4>
                        <h2 id="students-high-val" style="color: var(--risk-high);">6</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-students-high">Critical academic trajectory</div>
                        <span class="kpi-action-cue">Filter High Risk &rarr;</span>
                    </button>
                    <button type="button" class="stat-card kpi-card--action" id="kpi-students-mod" style="border-left-color: var(--risk-mod) !important;" onclick="window.kpiAction('students-mod')" aria-controls="database-section" aria-label="Filter student directory: Moderate Risk students">
                        <h4>Moderate Risk</h4>
                        <h2 id="students-mod-val" style="color: var(--risk-mod);">10</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-students-mod">Borderline trajectory</div>
                        <span class="kpi-action-cue">Filter Moderate Risk &rarr;</span>
                    </button>
                    <button type="button" class="stat-card kpi-card--action" id="kpi-students-support" style="border-left-color: var(--accent-blue) !important;" onclick="window.kpiAction('students-support')" aria-controls="database-section" aria-label="Filter student directory: Active Support cases">
                        <h4>Active Support Cases</h4>
                        <h2 id="students-support-val" style="color: var(--accent-blue);">14</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-students-support">10 cases awaiting review</div>
                        <span class="kpi-action-cue">Filter support cases &rarr;</span>
                    </button>
                </div>

                <div class="stat-grid" id="admin-activity-kpis" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); margin-bottom: 24px;">
                    <button type="button" class="stat-card kpi-card--action" id="kpi-act-inbox" style="border-left-color: var(--accent-blue) !important;" onclick="window.kpiAction('inbox')" aria-controls="tab-inbox" aria-label="Open Reports: switch to Inbox tab">
                        <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Open Reports</h4>
                        <h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;">5</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-act-inbox">Incoming queries</div>
                        <span class="kpi-action-cue">Open Inbox &rarr;</span>
                    </button>
                    <button type="button" class="stat-card kpi-card--action" id="kpi-act-approvals" style="border-left-color: var(--risk-mod) !important;" onclick="window.kpiAction('approvals')" aria-controls="tab-approvals" aria-label="Pending Approvals: switch to Approvals tab">
                        <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Pending Approvals</h4>
                        <h2 style="margin: 8px 0 0; color: var(--risk-mod); font-size: 2rem;">2</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-act-approvals">Batches & corrections</div>
                        <span class="kpi-action-cue">Review Approvals &rarr;</span>
                    </button>
                    <button type="button" class="stat-card kpi-card--action" id="kpi-act-support" style="border-left-color: var(--risk-high) !important;" onclick="window.kpiAction('support')" aria-controls="tab-support" aria-label="Support Reviews: switch to Academic Support tab">
                        <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Support Reviews</h4>
                        <h2 style="margin: 8px 0 0; color: var(--risk-high); font-size: 2rem;">7</h2>
                        <div class="stat-card-subtext kpi-card__description" id="desc-act-support">Active program cases</div>
                        <span class="kpi-action-cue">View Support &rarr;</span>
                    </button>
                </div>
                <script>
                    window.lastAction = null;
                    window.kpiAction = function(name) { window.lastAction = name; };
                </script>
            </body>
            </html>
        `;

        test('Dark Mode: KPI descriptions compute to light gray var(--text-gray) and NEVER to black rgb(0,0,0)', async ({ page }) => {
            await page.setContent(renderDashboardHtml('dark'));

            // Check descriptions in all 3 KPI groups
            const descriptions = page.locator('.kpi-card__description');
            const count = await descriptions.count();
            expect(count).toBeGreaterThanOrEqual(11);

            for (let i = 0; i < count; i++) {
                const desc = descriptions.nth(i);
                const color = await desc.evaluate(el => window.getComputedStyle(el).color);
                const text = (await desc.innerText()).trim();

                // MUST NOT BE BLACK (rgb(0, 0, 0))
                expect(color, `Description "${text}" must not be black in dark mode`).not.toBe('rgb(0, 0, 0)');
                
                // Dark mode --text-gray is #94A3B8 -> rgb(148, 163, 184)
                expect(color, `Description "${text}" should compute to rgb(148, 163, 184)`).toBe('rgb(148, 163, 184)');
            }
        });

        test('Light Mode: KPI descriptions compute to dark gray var(--text-gray) (#64748B)', async ({ page }) => {
            await page.setContent(renderDashboardHtml('light'));

            const descriptions = page.locator('.kpi-card__description');
            const count = await descriptions.count();
            expect(count).toBeGreaterThanOrEqual(11);

            for (let i = 0; i < count; i++) {
                const desc = descriptions.nth(i);
                const color = await desc.evaluate(el => window.getComputedStyle(el).color);
                const text = (await desc.innerText()).trim();

                // Light mode --text-gray is #64748B -> rgb(100, 116, 139)
                expect(color, `Description "${text}" should compute to rgb(100, 116, 139)`).toBe('rgb(100, 116, 139)');
            }
        });

        test('Dark Mode: Semantic KPI value colors retain high contrast and correct tokens', async ({ page }) => {
            await page.setContent(renderDashboardHtml('dark'));

            // High risk red (--risk-high in dark: #EF4444 -> rgb(239, 68, 68))
            const highVal = page.locator('#students-high-val');
            const highColor = await highVal.evaluate(el => window.getComputedStyle(el).color);
            expect(highColor).toBe('rgb(239, 68, 68)');

            // Moderate risk amber (--risk-mod in dark: #F59E0B -> rgb(245, 158, 11))
            const modVal = page.locator('#students-mod-val');
            const modColor = await modVal.evaluate(el => window.getComputedStyle(el).color);
            expect(modColor).toBe('rgb(245, 158, 11)');

            // Irregular gold (--gold in dark: #E0BB4D -> rgb(224, 187, 77))
            const irregVal = page.locator('#irregular-val');
            const irregColor = await irregVal.evaluate(el => window.getComputedStyle(el).color);
            expect(irregColor).toBe('rgb(224, 187, 77)');

            // Support cases blue (--accent-blue in dark: #6C8EEF -> rgb(108, 142, 239))
            const supportVal = page.locator('#students-support-val');
            const supportColor = await supportVal.evaluate(el => window.getComputedStyle(el).color);
            expect(supportColor).toBe('rgb(108, 142, 239)');

            // Action cues blue
            const cues = page.locator('.kpi-action-cue');
            const cueCount = await cues.count();
            for (let i = 0; i < cueCount; i++) {
                const cueColor = await cues.nth(i).evaluate(el => window.getComputedStyle(el).color);
                expect(cueColor).toBe('rgb(108, 142, 239)');
            }
        });

        test('Light Mode: Semantic KPI value colors retain correct tokens', async ({ page }) => {
            await page.setContent(renderDashboardHtml('light'));

            // High risk red (--risk-high in light: #DC2626 -> rgb(220, 38, 38))
            const highVal = page.locator('#students-high-val');
            const highColor = await highVal.evaluate(el => window.getComputedStyle(el).color);
            expect(highColor).toBe('rgb(220, 38, 38)');

            // Moderate risk amber (--risk-mod in light: #D97706 -> rgb(217, 119, 6))
            const modVal = page.locator('#students-mod-val');
            const modColor = await modVal.evaluate(el => window.getComputedStyle(el).color);
            expect(modColor).toBe('rgb(217, 119, 6)');

            // Irregular gold (--gold in light: #C9A227 -> rgb(201, 162, 39))
            const irregVal = page.locator('#irregular-val');
            const irregColor = await irregVal.evaluate(el => window.getComputedStyle(el).color);
            expect(irregColor).toBe('rgb(201, 162, 39)');

            // Action cues blue (--accent-blue in light: #1E4DB7 -> rgb(30, 77, 183))
            const cues = page.locator('.kpi-action-cue');
            const cueCount = await cues.count();
            for (let i = 0; i < cueCount; i++) {
                const cueColor = await cues.nth(i).evaluate(el => window.getComputedStyle(el).color);
                expect(cueColor).toBe('rgb(30, 77, 183)');
            }
        });

        test('Interactive KPI buttons have no title attribute and have valid aria-label', async ({ page }) => {
            await page.setContent(renderDashboardHtml('light'));

            const actionButtons = page.locator('button.stat-card.kpi-card--action');
            const count = await actionButtons.count();
            expect(count).toBe(11);

            for (let i = 0; i < count; i++) {
                const btn = actionButtons.nth(i);
                const title = await btn.getAttribute('title');
                const ariaLabel = await btn.getAttribute('aria-label');
                
                expect(title, 'KPI button must not have title attribute').toBeNull();
                expect(ariaLabel, 'KPI button must have non-empty aria-label').toBeTruthy();
                expect(ariaLabel.length).toBeGreaterThan(10);
            }
        });

        test('Average Cumulative GWA custom tooltip displays approved methodology text', async ({ page }) => {
            await page.setContent(renderDashboardHtml('light'));

            const tooltipContainer = page.locator('#kpi-gwa .custom-tooltip');
            await expect(tooltipContainer).toBeVisible();

            const tooltipText = page.locator('#kpi-gwa .tooltip-text');
            await expect(tooltipText).toHaveText('Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 3. Multi-Viewport & Responsive Layout Testing
    // ─────────────────────────────────────────────────────────────
    test.describe('[Generated HTML] Multi-Viewport Responsive Integrity', () => {
        const viewports = [
            { width: 1440, height: 900, label: '1440x900' },
            { width: 1280, height: 720, label: '1280x720' },
            { width: 1024, height: 768, label: '1024x768' },
            { width: 390, height: 844, label: '390x844' },
        ];

        for (const vp of viewports) {
            test(`KPI layout renders without text clipping at ${vp.label} in dark mode`, async ({ page }) => {
                await page.setViewportSize({ width: vp.width, height: vp.height });
                await page.setContent(`
                    <!DOCTYPE html>
                    <html lang="en" data-theme="dark">
                    <head>
                        <meta charset="UTF-8">
                        <style>${dashboardCss}</style>
                    </head>
                    <body style="background: var(--bg-color); padding: 16px;">
                        <div class="stat-grid" id="admin-students-kpis">
                            <button type="button" class="stat-card kpi-card--action" id="card-total" aria-label="Filter student directory: view all students">
                                <h4>Total Students</h4>
                                <h2>291</h2>
                                <div class="stat-card-subtext kpi-card__description">Active population</div>
                                <span class="kpi-action-cue">View all students &rarr;</span>
                            </button>
                            <button type="button" class="stat-card kpi-card--action" id="card-high" aria-label="Filter student directory: High Risk students">
                                <h4>High Risk</h4>
                                <h2 style="color: var(--risk-high);">6</h2>
                                <div class="stat-card-subtext kpi-card__description">Critical academic trajectory</div>
                                <span class="kpi-action-cue">Filter High Risk &rarr;</span>
                            </button>
                            <button type="button" class="stat-card kpi-card--action" id="card-mod" aria-label="Filter student directory: Moderate Risk students">
                                <h4>Moderate Risk</h4>
                                <h2 style="color: var(--risk-mod);">10</h2>
                                <div class="stat-card-subtext kpi-card__description">Borderline trajectory</div>
                                <span class="kpi-action-cue">Filter Moderate Risk &rarr;</span>
                            </button>
                            <button type="button" class="stat-card kpi-card--action" id="card-support" aria-label="Filter student directory: Active Support cases">
                                <h4>Active Support Cases</h4>
                                <h2 style="color: var(--accent-blue);">14</h2>
                                <div class="stat-card-subtext kpi-card__description">10 cases awaiting review</div>
                                <span class="kpi-action-cue">Filter support cases &rarr;</span>
                            </button>
                        </div>
                    </body>
                    </html>
                `);

                // Verify every card is visible and descriptions wrap naturally without overflowing card boundaries
                const cards = page.locator('.stat-card');
                const cardCount = await cards.count();
                for (let i = 0; i < cardCount; i++) {
                    const card = cards.nth(i);
                    await expect(card).toBeVisible();

                    // Check that inner elements do not horizontally overflow the card
                    const overflows = await card.evaluate(el => el.scrollWidth > el.clientWidth + 2);
                    expect(overflows, `Card ${i} should not horizontally overflow at ${vp.label}`).toBe(false);
                }

                // Capture evidence screenshot for key viewports
                if (vp.label === '1280x720' || vp.label === '390x844') {
                    await page.screenshot({
                        path: resolve(evidenceDir, `kpi-students-${vp.label}-dark.png`),
                        fullPage: true,
                    });
                }
            });
        }

        test('Capture required evidence screenshots across themes and pages', async ({ page }) => {
            await page.setViewportSize({ width: 1280, height: 720 });

            // 1. Admin Dashboard Light
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="light">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 24px;">
                    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student table: view all students">
                            <h4>Total Students</h4>
                            <h2 style="color: var(--text-dark);">291</h2>
                            <div class="stat-card-subtext kpi-card__description">Active cohort population</div>
                            <span class="kpi-action-cue">View all students &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action red" aria-label="Filter student table: At-Risk students">
                            <h4>At-Risk</h4>
                            <h2 style="color: var(--risk-high);">16</h2>
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--risk-high); margin-top: 4px;">6 High | 10 Moderate</div>
                            <span class="kpi-action-cue">Filter at-risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student table: Irregular students">
                            <h4>Irregular</h4>
                            <h2 style="color: var(--gold);">45</h2>
                            <div class="stat-card-subtext kpi-card__description">Non-standard curriculum progression</div>
                            <span class="kpi-action-cue">Filter irregular &rarr;</span>
                        </button>
                        <div class="stat-card" id="kpi-gwa">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h4>Avg Cumulative GWA</h4>
                                <span class="custom-tooltip tooltip-top-right" tabindex="0" aria-label="Calculation Scope">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" role="tooltip">Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.</span>
                                </span>
                            </div>
                            <h2 style="color: var(--text-dark);">1.84</h2>
                            <div class="stat-card-subtext kpi-card__description">Historical cumulative cohort metric</div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'admin-dashboard-light.png') });

            // 2. Admin Dashboard Dark
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="dark">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 24px;">
                    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));">
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student table: view all students">
                            <h4>Total Students</h4>
                            <h2 style="color: var(--text-dark);">291</h2>
                            <div class="stat-card-subtext kpi-card__description">Active cohort population</div>
                            <span class="kpi-action-cue">View all students &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action red" aria-label="Filter student table: At-Risk students">
                            <h4>At-Risk</h4>
                            <h2 style="color: var(--risk-high);">16</h2>
                            <div style="font-size: 0.8rem; font-weight: 700; color: var(--risk-high); margin-top: 4px;">6 High | 10 Moderate</div>
                            <span class="kpi-action-cue">Filter at-risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student table: Irregular students">
                            <h4>Irregular</h4>
                            <h2 style="color: var(--gold);">45</h2>
                            <div class="stat-card-subtext kpi-card__description">Non-standard curriculum progression</div>
                            <span class="kpi-action-cue">Filter irregular &rarr;</span>
                        </button>
                        <div class="stat-card" id="kpi-gwa">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h4>Avg Cumulative GWA</h4>
                                <span class="custom-tooltip tooltip-top-right" tabindex="0" aria-label="Calculation Scope">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" role="tooltip">Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.</span>
                                </span>
                            </div>
                            <h2 style="color: var(--text-dark);">1.84</h2>
                            <div class="stat-card-subtext kpi-card__description">Historical cumulative cohort metric</div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'admin-dashboard-dark.png') });

            // 3. Admin Students KPIs Light
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="light">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 24px;">
                    <div class="stat-grid">
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: view all students">
                            <h4>Total Students</h4><h2>291</h2>
                            <div class="stat-card-subtext kpi-card__description">Active population</div>
                            <span class="kpi-action-cue">View all students &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: High Risk students">
                            <h4>High Risk</h4><h2 style="color: var(--risk-high);">6</h2>
                            <div class="stat-card-subtext kpi-card__description">Critical academic trajectory</div>
                            <span class="kpi-action-cue">Filter High Risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: Moderate Risk students">
                            <h4>Moderate Risk</h4><h2 style="color: var(--risk-mod);">10</h2>
                            <div class="stat-card-subtext kpi-card__description">Borderline trajectory</div>
                            <span class="kpi-action-cue">Filter Moderate Risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: Active Support cases">
                            <h4>Active Support Cases</h4><h2 style="color: var(--accent-blue);">14</h2>
                            <div class="stat-card-subtext kpi-card__description">10 cases awaiting review</div>
                            <span class="kpi-action-cue">Filter support cases &rarr;</span>
                        </button>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'admin-students-light.png') });

            // 4. Admin Students KPIs Dark
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="dark">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 24px;">
                    <div class="stat-grid">
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: view all students">
                            <h4>Total Students</h4><h2>291</h2>
                            <div class="stat-card-subtext kpi-card__description">Active population</div>
                            <span class="kpi-action-cue">View all students &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: High Risk students">
                            <h4>High Risk</h4><h2 style="color: var(--risk-high);">6</h2>
                            <div class="stat-card-subtext kpi-card__description">Critical academic trajectory</div>
                            <span class="kpi-action-cue">Filter High Risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: Moderate Risk students">
                            <h4>Moderate Risk</h4><h2 style="color: var(--risk-mod);">10</h2>
                            <div class="stat-card-subtext kpi-card__description">Borderline trajectory</div>
                            <span class="kpi-action-cue">Filter Moderate Risk &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Filter student directory: Active Support cases">
                            <h4>Active Support Cases</h4><h2 style="color: var(--accent-blue);">14</h2>
                            <div class="stat-card-subtext kpi-card__description">10 cases awaiting review</div>
                            <span class="kpi-action-cue">Filter support cases &rarr;</span>
                        </button>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'admin-students-dark.png') });

            // 5. Admin Activity KPIs Dark
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="dark">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 24px;">
                    <div class="stat-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                        <button type="button" class="stat-card kpi-card--action" aria-label="Open Reports: switch to Inbox tab">
                            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Open Reports</h4>
                            <h2 style="margin: 8px 0 0; color: var(--text-dark); font-size: 2rem;">5</h2>
                            <div class="stat-card-subtext kpi-card__description">Incoming queries</div>
                            <span class="kpi-action-cue">Open Inbox &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Pending Approvals: switch to Approvals tab">
                            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Pending Approvals</h4>
                            <h2 style="margin: 8px 0 0; color: var(--risk-mod); font-size: 2rem;">2</h2>
                            <div class="stat-card-subtext kpi-card__description">Batches & corrections</div>
                            <span class="kpi-action-cue">Review Approvals &rarr;</span>
                        </button>
                        <button type="button" class="stat-card kpi-card--action" aria-label="Support Reviews: switch to Academic Support tab">
                            <h4 style="margin: 0; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase;">Support Reviews</h4>
                            <h2 style="margin: 8px 0 0; color: var(--risk-high); font-size: 2rem;">7</h2>
                            <div class="stat-card-subtext kpi-card__description">Active program cases</div>
                            <span class="kpi-action-cue">View Support &rarr;</span>
                        </button>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'admin-activity-dark.png') });

            // 6. Avg GWA Tooltip screenshot
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="dark">
                <head><style>${dashboardCss}</style></head>
                <body style="background: var(--bg-color); padding: 40px;">
                    <div style="max-width: 300px;">
                        <div class="stat-card" id="kpi-gwa">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                                <h4>Avg Cumulative GWA</h4>
                                <span class="custom-tooltip tooltip-top-right active-for-test" tabindex="0" aria-label="Calculation Scope">
                                    <svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--text-gray);"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
                                    <span class="tooltip-text" role="tooltip" style="visibility: visible; opacity: 1;">Average of recorded cumulative GWAs across active students. This is a historical cohort measure, not a projected GWA.</span>
                                </span>
                            </div>
                            <h2 style="color: var(--text-dark);">1.84</h2>
                            <div class="stat-card-subtext kpi-card__description">Historical cumulative cohort metric</div>
                        </div>
                    </div>
                </body>
                </html>
            `);
            await page.screenshot({ path: resolve(evidenceDir, 'avg-gwa-tooltip-dark.png') });
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. Live Authenticated Application (Conditional Environment)
    // ─────────────────────────────────────────────────────────────
    test.describe('[Live authenticated application] Live Portal Checks', () => {
        test('Live Admin portal session checks (skipped when local test credentials absent)', async ({ page }) => {
            const adminLoginId = process.env.ADMIN_LOGIN_ID;
            const adminPassword = process.env.ADMIN_PASSWORD;

            test.skip(!adminLoginId || !adminPassword, 'Live authenticated tests require ADMIN_LOGIN_ID and ADMIN_PASSWORD in environment');

            await page.goto('login.php');
            await page.locator('input[name="username"]').fill(adminLoginId);
            await page.locator('input[name="password"]').fill(adminPassword);
            await page.getByRole('button', { name: 'Login' }).click();
            await page.waitForURL('**/admin/index.php');

            // Verify live KPI descriptions in dark mode
            await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
            const desc = page.locator('.kpi-card__description').first();
            const color = await desc.evaluate(el => window.getComputedStyle(el).color);
            expect(color).toBe('rgb(148, 163, 184)');
        });
    });
});

