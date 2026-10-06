import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const uiJs = readFileSync(resolve('assets/js/ui.js'), 'utf-8');
const glossaryModalPhp = readFileSync(resolve('includes/glossary_modal.php'), 'utf-8');
const adminIndexPhp = readFileSync(resolve('admin/index.php'), 'utf-8');
const adminActivityPhp = readFileSync(resolve('admin/activity.php'), 'utf-8');
const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');
const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');
const adminAnalyticsPhp = readFileSync(resolve('admin/analytics.php'), 'utf-8');
const studentDashboardPhp = readFileSync(resolve('student/dashboard.php'), 'utf-8');

test.describe('UI-WP1A: Critical Display and Interaction Foundation', () => {

    test.describe('F-01: Admin Grades History Null/Missing-Risk Fallback', () => {
        test('renderGradeRow maps null, empty, or unclassified risk to N/A with title "Not classified"', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --risk-high: #e53e3e;
                            --risk-mod: #dd6b20;
                            --risk-low: #38a169;
                            --text-gray: #718096;
                            --text-dark: #2d3748;
                            --accent-blue: #3182ce;
                            --border-color: #e2e8f0;
                        }
                    </style>
                </head>
                <body>
                    <div id="container"></div>
                    <script>
                        const csrfToken = 'test-token';
                        const openSectionName = 'BSIT 3-A';
                        const openFeedbackId = '';

                        function riskColor(risk) {
                            if (risk === 'HIGH') return 'var(--risk-high)';
                            if (risk === 'MODERATE') return 'var(--risk-mod)';
                            if (risk === 'LOW') return 'var(--risk-low)';
                            return 'var(--text-gray)';
                        }

                        function renderGradeRow(g, studentId) {
                            const rawRisk = g.risk ? String(g.risk).toUpperCase() : '';
                            const risk = ['HIGH', 'MODERATE', 'LOW'].includes(rawRisk) ? rawRisk : 'N/A';
                            const riskLabel = risk === 'N/A' ? 'Not classified' : risk;
                            return \`
                            <form method="POST" action="grades.php" class="grade-row-form" style="padding:8px 0; border-bottom:1px solid var(--border-color);">
                                <span style="font-size:0.8rem; color:var(--text-dark);" title="\${g.title || ''}">
                                    <strong>\${g.code}</strong>
                                    <span class="risk-badge" style="background:\${riskColor(risk)}; color:white; padding:1px 7px; border-radius:4px; font-size:0.68rem; font-weight:700; margin-left:6px;" title="\${riskLabel}">\${risk}</span>
                                </span>
                            </form>\`;
                        }

                        window.renderGradeRow = renderGradeRow;
                    </script>
                </body>
                </html>
            `);

            // Verify with null risk
            const nullResult = await page.evaluate(() => {
                const html = window.renderGradeRow({ code: 'CS101', title: 'Intro', risk: null }, 1);
                document.getElementById('container').innerHTML = html;
                const badge = document.querySelector('.risk-badge');
                return { text: badge.innerText.trim(), title: badge.getAttribute('title') };
            });
            expect(nullResult.text).toBe('N/A');
            expect(nullResult.title).toBe('Not classified');

            // Verify with empty string risk
            const emptyResult = await page.evaluate(() => {
                const html = window.renderGradeRow({ code: 'CS101', title: 'Intro', risk: '' }, 1);
                document.getElementById('container').innerHTML = html;
                const badge = document.querySelector('.risk-badge');
                return { text: badge.innerText.trim(), title: badge.getAttribute('title') };
            });
            expect(emptyResult.text).toBe('N/A');
            expect(emptyResult.title).toBe('Not classified');

            // Verify with undefined risk
            const undefResult = await page.evaluate(() => {
                const html = window.renderGradeRow({ code: 'CS101', title: 'Intro' }, 1);
                document.getElementById('container').innerHTML = html;
                const badge = document.querySelector('.risk-badge');
                return { text: badge.innerText.trim(), title: badge.getAttribute('title') };
            });
            expect(undefResult.text).toBe('N/A');
            expect(undefResult.title).toBe('Not classified');

            // Verify with LOW risk
            const lowResult = await page.evaluate(() => {
                const html = window.renderGradeRow({ code: 'CS101', title: 'Intro', risk: 'LOW' }, 1);
                document.getElementById('container').innerHTML = html;
                const badge = document.querySelector('.risk-badge');
                return { text: badge.innerText.trim(), title: badge.getAttribute('title') };
            });
            expect(lowResult.text).toBe('LOW');
            expect(lowResult.title).toBe('LOW');

            // Verify with HIGH risk
            const highResult = await page.evaluate(() => {
                const html = window.renderGradeRow({ code: 'CS101', title: 'Intro', risk: 'HIGH' }, 1);
                document.getElementById('container').innerHTML = html;
                const badge = document.querySelector('.risk-badge');
                return { text: badge.innerText.trim(), title: badge.getAttribute('title') };
            });
            expect(highResult.text).toBe('HIGH');
            expect(highResult.title).toBe('HIGH');
        });

        test('admin/grades.php repository file does not fall back to LOW when risk is falsy', () => {
            expect(adminGradesPhp).not.toMatch(/const risk = g\.risk \|\| ['"]LOW['"]/);
            expect(adminGradesPhp).toContain("const risk = ['HIGH', 'MODERATE', 'LOW'].includes(rawRisk) ? rawRisk : 'N/A';");
            expect(adminGradesPhp).toContain("const riskLabel = risk === 'N/A' ? 'Not classified' : risk;");
        });
    });

    test.describe('F-02 & F-04: Semantic KPI Drilldown Controls and Scroll/Focus Target', () => {
        test('Admin Activity KPIs are semantic button controls with action cues and scroll-target', async ({ page }) => {
            // Verify admin/activity.php markup
            expect(adminActivityPhp).toContain('<button type="button" class="stat-card kpi-card--action"');
            expect(adminActivityPhp).toContain('onclick="switchTab(\'inbox\', true)"');
            expect(adminActivityPhp).toContain('onclick="switchTab(\'approvals\', true)"');
            expect(adminActivityPhp).toContain('onclick="switchTab(\'support\', true)"');
            expect(adminActivityPhp).toContain('class="kpi-action-cue"');
            expect(adminActivityPhp).toContain('id="workspace-tabs" class="scroll-target"');

            // Load interactive test page with assets/js/ui.js
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${dashboardCss}</style>
                </head>
                <body>
                    <div style="height: 200px;"></div>
                    <button type="button" id="kpi-inbox" class="stat-card kpi-card--action" onclick="switchTab('inbox', true)" aria-controls="tab-inbox" title="Switch to Inbox tab">
                        <h4>Open Reports</h4>
                        <div class="stat-value">5</div>
                        <span class="kpi-action-cue">Open Inbox &rarr;</span>
                    </button>
                    <div style="height: 800px;"></div>
                    <div id="workspace-tabs" class="scroll-target">
                        <button type="button" id="btn-inbox" class="tab-btn active">Inbox</button>
                        <button type="button" id="btn-approvals" class="tab-btn">Approvals</button>
                    </div>
                    <script>${uiJs}</script>
                    <script>
                        let switchedTab = null;
                        function switchTab(tabId, shouldScroll) {
                            switchedTab = tabId;
                            if (shouldScroll && window.UDM && window.UDM.scrollToTarget) {
                                window.UDM.scrollToTarget('workspace-tabs', { focusTarget: 'btn-' + tabId });
                            }
                        }
                    </script>
                </body>
                </html>
            `);

            const kpiBtn = page.locator('#kpi-inbox');
            await expect(kpiBtn).toHaveAttribute('type', 'button');
            await expect(kpiBtn).toHaveRole('button');

            // Test keyboard Enter activation
            await kpiBtn.focus();
            await page.keyboard.press('Enter');

            // Verify focus transferred to destination
            const focusedId = await page.evaluate(() => document.activeElement ? document.activeElement.id : null);
            expect(focusedId).toBe('btn-inbox');
        });

        test('Admin Dashboard KPIs: 4 semantic buttons, 1 static card, and table-card scroll-target', async ({ page }) => {
            // Verify admin/index.php markup
            expect(adminIndexPhp).toContain('<button type="button" class="stat-card kpi-card--action"');
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', ''); applyTableFilter('status', '');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', 'AT_RISK');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('status', 'Irregular');\"");
            expect(adminIndexPhp).toContain("onclick=\"applyTableFilter('risk', 'N/A');\"");
            // Static GWA card is div.stat-card
            expect(adminIndexPhp).toMatch(/<div class="stat-card"[^>]*>\s*<div[^>]*>\s*<h4>Avg Cumulative GWA<\/h4>/);
            // Table container has scroll-target
            expect(adminIndexPhp).toContain('class="card scroll-target" id="table-card"');
        });

        test('Admin Students KPIs are semantic buttons and target database-section has scroll-target', () => {
            expect(adminStudentsPhp).toContain('<button type="button" class="stat-card kpi-card--action"');
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('HIGH', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('MODERATE', '')\"");
            expect(adminStudentsPhp).toContain("onclick=\"applyTableFilter('', 'active')\"");
            expect(adminStudentsPhp).toMatch(/class=["'][^"']*\bscroll-target\b[^"']*["']\s+id="database-section"/);
        });
    });

    test.describe('F-04: Shared Reduced-Motion-Aware Scroll and Focus Helper', () => {
        test('UDM.scrollToTarget handles reduced-motion preference and focuses target', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <body>
                    <div style="height: 600px;"></div>
                    <div id="target-div" class="scroll-target">Destination</div>
                    <script>${uiJs}</script>
                </body>
                </html>
            `);

            // Verify UDM namespace exists
            const hasHelper = await page.evaluate(() => typeof window.UDM?.scrollToTarget === 'function');
            expect(hasHelper).toBe(true);

            // Test execution with reduced-motion: reduce
            await page.emulateMedia({ reducedMotion: 'reduce' });
            const resultReduced = await page.evaluate(() => {
                let optionsPassed = null;
                const el = document.getElementById('target-div');
                const origScroll = el.scrollIntoView;
                el.scrollIntoView = function(opts) { optionsPassed = opts; };
                window.UDM.scrollToTarget('target-div');
                el.scrollIntoView = origScroll;
                return {
                    optionsPassed,
                    activeId: document.activeElement ? document.activeElement.id : null,
                    tabIndex: el.getAttribute('tabindex')
                };
            });

            expect(resultReduced.optionsPassed?.behavior).toBe('auto');
            expect(resultReduced.activeId).toBe('target-div');
            expect(resultReduced.tabIndex).toBe('-1');

            // Test execution with standard motion
            await page.emulateMedia({ reducedMotion: 'no-preference' });
            const resultSmooth = await page.evaluate(() => {
                let optionsPassed = null;
                const el = document.getElementById('target-div');
                const origScroll = el.scrollIntoView;
                el.scrollIntoView = function(opts) { optionsPassed = opts; };
                window.UDM.scrollToTarget('target-div');
                el.scrollIntoView = origScroll;
                return {
                    optionsPassed
                };
            });
            expect(resultSmooth.optionsPassed?.behavior).toBe('smooth');
        });
    });

    test.describe('F-10: Consistent Institutional Branding', () => {
        test('Glossary modal uses "College of Computing Studies" and contains no "CET" reference', () => {
            expect(glossaryModalPhp).toContain('College of Computing Studies');
            expect(glossaryModalPhp).not.toContain('College of Engineering and Technology');
            expect(glossaryModalPhp).not.toContain('(CET)');
            expect(glossaryModalPhp).not.toContain('CET');
        });
    });

    test.describe('F-SUP-01: Visible Decision-Support Context', () => {
        test('Student dashboard has visible risk summary line without requiring hover', () => {
            expect(studentDashboardPhp).toContain('$shortRiskSummary');
            expect(studentDashboardPhp).toContain('<?= htmlspecialchars($shortRiskSummary) ?>');
        });

        test('Admin dashboard "No Prediction Yet" card has visible exclusion explanation', () => {
            expect(adminIndexPhp).toContain('Excluded from At-Risk count');
            expect(adminIndexPhp).toContain('<span class="kpi-action-cue">Filter unpredicted &rarr;</span>');
        });

        test('Admin analytics prediction coverage card has visible source breakdown', () => {
            expect(adminAnalyticsPhp).toContain('<?= $sourceTotals[\'decision_tree\'] ?> Model &bull; <?= $sourceTotals[\'calculation_fallback\'] ?> Calculation');
        });
    });

    test.describe('F-SUP-03: No Nested Interactive Elements in Clickable KPI', () => {
        test('Admin dashboard "No Prediction Yet" KPI card does not contain nested button or tooltip trigger', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <body>
                    <button type="button" class="stat-card kpi-card--action" id="test-unpredicted-card">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                            <h4>No Prediction Yet</h4>
                            <span style="font-size: 0.72rem; color: var(--text-gray); font-weight: 500;">Excluded from At-Risk count</span>
                        </div>
                        <h2 style="color: var(--text-gray);">5</h2>
                        <span class="kpi-action-cue">Filter unpredicted &rarr;</span>
                    </button>
                </body>
                </html>
            `);

            // Verify no nested buttons, links, or tabindex="0" inside the button
            const nestedInteractive = await page.locator('#test-unpredicted-card button, #test-unpredicted-card a, #test-unpredicted-card [tabindex]:not([tabindex="-1"])').count();
            expect(nestedInteractive).toBe(0);

            // Verify adminIndexPhp does not have custom-tooltip inside unpredicted button
            const unpredictedCardSection = adminIndexPhp.substring(
                adminIndexPhp.indexOf('title="Click to filter table to students missing predictions"'),
                adminIndexPhp.indexOf('<!-- CHARTS -->')
            );
            expect(unpredictedCardSection).not.toContain('class="custom-tooltip"');
            expect(unpredictedCardSection).not.toContain('tabindex="0"');
        });
    });

    test.describe('F-SUP-04: Floating Help Button Opacity and Layout Bottom Clearance', () => {
        const viewports = [
            { width: 1440, height: 900, name: 'desktop' },
            { width: 1024, height: 768, name: 'tablet' },
            { width: 390, height: 844, name: 'mobile' }
        ];

        for (const vp of viewports) {
            test(`Floating help button has full opacity 1.0 and does not collide with content at ${vp.name} (${vp.width}x${vp.height})`, async ({ page }) => {
                await page.setViewportSize({ width: vp.width, height: vp.height });
                await page.setContent(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta name="viewport" content="width=device-width, initial-scale=1.0">
                        <style>${dashboardCss}</style>
                    </head>
                    <body>
                        <div class="main-content">
                            <div class="card" id="bottom-card" style="margin-top: 400px; height: 100px; background: #fff;">
                                Bottom Card Content
                            </div>
                        </div>
                        <button type="button" class="floating-help-btn" id="guide-trigger" aria-label="Open Academic Guide">
                            <svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle></svg>
                        </button>
                    </body>
                    </html>
                `);

                const btn = page.locator('#guide-trigger');
                await expect(btn).toBeVisible();

                // Check idle opacity is 1
                const opacity = await btn.evaluate(el => window.getComputedStyle(el).opacity);
                expect(Number(opacity)).toBe(1);

                // Check main-content padding-bottom clearance
                const mainContentPaddingBottom = await page.locator('.main-content').evaluate(el => {
                    return parseFloat(window.getComputedStyle(el).paddingBottom);
                });
                expect(mainContentPaddingBottom).toBeGreaterThanOrEqual(84);

                // Scroll to bottom and check bounding box collision
                await page.evaluate(() => window.scrollTo(0, document.body.scrollHeight));
                const btnBox = await btn.boundingBox();
                const cardBox = await page.locator('#bottom-card').boundingBox();

                expect(btnBox).not.toBeNull();
                expect(cardBox).not.toBeNull();

                // Overlap function: check that bottom card and floating button do not vertically collide inside the viewport
                const collides = (
                    btnBox.x < cardBox.x + cardBox.width &&
                    btnBox.x + btnBox.width > cardBox.x &&
                    btnBox.y < cardBox.y + cardBox.height &&
                    btnBox.y + btnBox.height > cardBox.y
                );
                expect(collides).toBe(false);
            });
        }
    });

    test.describe('F-SUP-06: File-Removal Button Accessible Name, Decorative SVG, and Touch Target', () => {
        test('File removal button has aria-label="Remove selected file", aria-hidden="true" SVG, and >= 44x44px touch target', async ({ page }) => {
            // Verify admin/students.php markup
            expect(adminStudentsPhp).toContain('id="removeFile"');
            expect(adminStudentsPhp).toContain('aria-label="Remove selected file"');
            expect(adminStudentsPhp).toMatch(/<button[^>]*id="removeFile"[^>]*aria-label="Remove selected file"[\s\S]*?<svg[^>]*aria-hidden="true"/);

            // Render in browser and measure bounding box
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${dashboardCss}</style>
                </head>
                <body>
                    <div id="fileInfo" style="display: flex; align-items: center; justify-content: space-between; padding: 12px; background: #f8fafc; border-radius: 8px;">
                        <span id="fileName">test.csv</span>
                        <button type="button" id="removeFile" aria-label="Remove selected file" style="background: none; border: none; color: #ef4444; cursor: pointer; padding: 12px; min-width: 44px; min-height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 6px;">
                            <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>
                        </button>
                    </div>
                </body>
                </html>
            `);

            const removeBtn = page.getByRole('button', { name: 'Remove selected file' });
            await expect(removeBtn).toBeVisible();

            const box = await removeBtn.boundingBox();
            expect(box).not.toBeNull();
            expect(box.width).toBeGreaterThanOrEqual(44);
            expect(box.height).toBeGreaterThanOrEqual(44);

            const svgAriaHidden = await removeBtn.locator('svg').getAttribute('aria-hidden');
            expect(svgAriaHidden).toBe('true');
        });
    });
});
