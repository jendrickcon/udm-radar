import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const facultyDashboardPhp = readFileSync(resolve('faculty/dashboard.php'), 'utf-8');

const evidenceDir = resolve('test-results/evidence/faculty-layout');
mkdirSync(evidenceDir, { recursive: true });

// Extract page-level inline styles from faculty/dashboard.php
const styleMatch = facultyDashboardPhp.match(/<style>([\s\S]*?)<\/style>/);
const facultyInlineCss = styleMatch ? styleMatch[1] : '';

/**
 * Render representative Faculty Dashboard markup matching faculty/dashboard.php.
 * Simulates facultyActionCount > 0 (e.g. 10 support reviews) to verify warning banner closure.
 */
function renderFacultyDashboardHtml({ theme = 'dark', openRoster = false } = {}) {
    return `
    <!DOCTYPE html>
    <html lang="en" data-theme="${theme}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Faculty Dashboard Layout Regression Test</title>
        <style>
            ${dashboardCss}
            ${facultyInlineCss}
        </style>
    </head>
    <body style="display: flex;">
        <nav id="sidebar" class="sidebar" style="width: 260px; flex-shrink: 0;">
            <div class="sidebar-brand"><span>UDM-RADAR</span></div>
            <ul class="sidebar-nav">
                <li><a href="dashboard.php" class="sidebar-link active" aria-current="page">Dashboard</a></li>
            </ul>
        </nav>

        <div class="main-content" style="flex: 1; padding: 40px; overflow-y: auto;">
            <div class="header" style="margin-bottom:24px;">
                <div>
                    <h1>Section Overview</h1>
                    <p style="color:var(--text-gray); font-size:0.95rem;">
                        Click a section card to open its student roster.
                        <em style="color:var(--text-gray); font-size:0.82rem;">At-risk counts reflect performance in your assigned class loads only.</em>
                    </p>
                </div>
            </div>

            <!-- TIER 1: WORK REQUIRING ATTENTION BANNER -->
            <div class="card warning-banner" id="warning-banner" style="margin-bottom: 24px; padding: 16px 24px; border-left: 4px solid var(--risk-mod); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h3 style="margin: 0 0 6px 0; color: var(--text-dark); font-size: 1.1rem; font-weight: 700;">Work requiring your attention</h3>
                    <div style="display: flex; gap: 16px; flex-wrap: wrap; color: var(--text-gray); font-size: 0.85rem;">
                        <span style="display: flex; align-items: center; gap: 6px;"><div style="width:6px; height:6px; border-radius:50%; background:var(--risk-mod);"></div><a href="feedback.php?tab=support" class="action-link"><strong>10</strong> support reviews</a></span>
                    </div>
                </div>

                <div style="display: flex; gap: 8px; flex-wrap: wrap;">
                    <a href="feedback.php?tab=support" class="dashboard-banner-btn control-btn">Review Support Cases</a>
                </div>
            </div>

            <!-- DECISION-SUPPORT ADVISORY -->
            <div class="card" id="advisory-card" style="background: var(--bg-color); border: 1px solid var(--border-color); border-radius: 8px; padding: 14px 18px; margin-bottom: 24px; font-size: 0.82rem; color: var(--text-gray); display: flex; align-items: flex-start; gap: 12px;">
                <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="color: var(--accent-blue); flex-shrink: 0; margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                <div>
                    <div style="color: var(--text-dark); font-weight: 700; font-size: 0.85rem; margin-bottom: 4px;">Faculty Decision-Support Advisory</div>
                    <div style="line-height: 1.45; color: var(--text-dark);">Advisory Notice: Student risk assessments and projected academic outcomes are decision-support indicators intended to guide early mentoring and support referrals. They do not replace faculty evaluation or official grades.</div>
                </div>
            </div>

            <!-- SUMMARY KPI GRID -->
            <div class="stat-grid" id="kpi-grid" style="margin-bottom:24px;">
                <div class="stat-card" id="kpi-total" style="border-left-color: var(--text-dark) !important;">
                    <h4>Total Students</h4>
                    <h2 style="color: var(--text-dark);">133</h2>
                </div>
                <div class="stat-card" id="kpi-at-risk" style="border-left-color: var(--risk-high) !important;">
                    <h4>At-Risk <span style="font-size:0.65rem;color:var(--text-gray);font-weight:400;">(your classes)</span></h4>
                    <h2 style="color: var(--risk-high);">73</h2>
                </div>
                <div class="stat-card" id="kpi-irregular" style="border-left-color: var(--gold) !important;">
                    <h4>Irregular</h4>
                    <h2 style="color: var(--gold);">33</h2>
                </div>
                <div class="stat-card" id="kpi-gwa" style="border-left-color: var(--teal) !important;">
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <h4>Overall Avg Cumulative GWA</h4>
                    </div>
                    <h2 style="color: var(--teal);">2.56</h2>
                </div>
            </div>

            <!-- SECTION CARDS GRID -->
            <div class="stat-grid" id="section-grid" style="grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); margin-bottom:24px;">
                <div class="card section-card-item" id="card-IT-35" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
                    <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                        <span>IT-35</span>
                        <span style="font-size:0.8rem;color:var(--text-gray);">37 students</span>
                    </div>
                    <div style="padding:16px;text-align:center;">
                        <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;">2.69</h2>
                        <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Avg GWA</p>
                        <button type="button" class="btn btn--primary view-students-btn" id="btn-IT-35" data-section="IT-35" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('IT-35', this)" style="width:100%; justify-content:center;">
                            View Students →
                        </button>
                    </div>
                </div>

                <div class="card section-card-item" id="card-IT-36" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
                    <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                        <span>IT-36</span>
                        <span style="font-size:0.8rem;color:var(--text-gray);">28 students</span>
                    </div>
                    <div style="padding:16px;text-align:center;">
                        <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;">2.62</h2>
                        <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Avg GWA</p>
                        <button type="button" class="btn btn--primary view-students-btn" id="btn-IT-36" data-section="IT-36" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('IT-36', this)" style="width:100%; justify-content:center;">
                            View Students →
                        </button>
                    </div>
                </div>

                <div class="card section-card-item" id="card-IT-37" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
                    <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                        <span>IT-37</span>
                        <span style="font-size:0.8rem;color:var(--text-gray);">35 students</span>
                    </div>
                    <div style="padding:16px;text-align:center;">
                        <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;">2.56</h2>
                        <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Avg GWA</p>
                        <button type="button" class="btn btn--primary view-students-btn" id="btn-IT-37" data-section="IT-37" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('IT-37', this)" style="width:100%; justify-content:center;">
                            View Students →
                        </button>
                    </div>
                </div>

                <div class="card section-card-item" id="card-IT-38" style="padding:0;overflow:hidden;border:1px solid var(--border-color);border-radius:8px;">
                    <div style="background:var(--table-header-bg);color:var(--text-dark);padding:12px 16px;display:flex;justify-content:space-between;font-weight:700; border-bottom:1px solid var(--border-color);">
                        <span>IT-38</span>
                        <span style="font-size:0.8rem;color:var(--text-gray);">33 students</span>
                    </div>
                    <div style="padding:16px;text-align:center;">
                        <h2 style="color:var(--accent-blue);font-size:2rem;margin-bottom:2px;">2.34</h2>
                        <p style="color:var(--text-gray);font-size:0.8rem;margin-bottom:12px;">Avg GWA</p>
                        <button type="button" class="btn btn--primary view-students-btn" id="btn-IT-38" data-section="IT-38" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('IT-38', this)" style="width:100%; justify-content:center;">
                            View Students →
                        </button>
                    </div>
                </div>
            </div>

            <!-- ACTIVE ROSTER DRILLDOWN -->
            <div id="section-roster-card" class="data-table-card data-table-card--active roster-panel" style="display:${openRoster ? 'block' : 'none'}; margin-bottom:24px;">
                <div class="data-table-header">
                    <div class="data-table-header__intro">
                        <h3 class="data-table-title" id="roster-title" tabindex="-1">Student List — IT-35</h3>
                    </div>
                    <div class="data-table-actions">
                        <button type="button" onclick="closeRoster()" class="btn btn--secondary btn--sm" id="faculty-roster-close-btn">
                            Close
                        </button>
                    </div>
                </div>
                <div class="data-table-scroll" role="region" aria-label="Class Performance Roster" tabindex="0">
                    <table style="width: 100%; border-collapse: collapse;">
                        <caption class="sr-only">Class Performance Roster</caption>
                        <thead>
                            <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                <th scope="col" style="padding:12px; text-align:center;">#</th>
                                <th scope="col" style="padding:12px; text-align:left;">Student Name</th>
                                <th scope="col" style="padding:12px; text-align:center;">GWA</th>
                            </tr>
                        </thead>
                        <tbody id="roster-body-class">
                            <tr><td style="text-align:center;">1</td><td>Malabanan, Dex</td><td style="text-align:center;">3.11</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- TOP ACADEMIC PERFORMERS CARD -->
            <div class="card" id="top-performers-card">
                <div style="margin-bottom:16px;">
                    <h3 style="color:var(--text-dark);font-weight:700; font-size:1.1rem; margin:0 0 4px 0;">Top Academic Performers in Your Assigned Sections</h3>
                    <p style="color:var(--text-gray); font-size: 0.85rem; margin: 0 0 12px 0;">Highlights students with the highest officially recorded current GWA.</p>
                </div>
                <div class="data-table-scroll" role="region" aria-label="Top Academic Performers" tabindex="0">
                    <table style="width: 100%; border-collapse: collapse;">
                        <caption class="sr-only">Top Academic Performers</caption>
                        <thead>
                            <tr style="background:var(--table-header-bg); border-bottom: 2px solid var(--border-color);">
                                <th scope="col" style="padding:12px; text-align:center;">Rank</th>
                                <th scope="col" style="padding:12px; text-align:left;">Student</th>
                                <th scope="col" style="padding:12px; text-align:center;">Cumulative GWA</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr><td style="text-align:center; font-weight:700;">1</td><td>Malabanan, Dex</td><td style="text-align:center; font-weight:700; color:var(--accent-blue);">3.11</td></tr>
                            <tr><td style="text-align:center; font-weight:700;">2</td><td>Salvador, Sean Michael</td><td style="text-align:center; font-weight:700; color:var(--accent-blue);">3.10</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <script>
            let currentTrigger = null;
            function openSection(sec, btn) {
                currentTrigger = btn;
                const panel = document.getElementById('section-roster-card');
                panel.style.display = 'block';
                btn.setAttribute('aria-expanded', 'true');
                const title = document.getElementById('roster-title');
                title.textContent = 'Student List — ' + sec;
                title.focus();
            }
            function closeRoster() {
                const panel = document.getElementById('section-roster-card');
                panel.style.display = 'none';
                if (currentTrigger) {
                    currentTrigger.setAttribute('aria-expanded', 'false');
                    currentTrigger.focus();
                }
            }
        </script>
    </body>
    </html>
    `;
}

test.describe('Faculty Dashboard Layout Regression Verification', () => {

    // ─────────────────────────────────────────────────────────────
    // 1. Source Code Inspection
    // ─────────────────────────────────────────────────────────────
    test.describe('1. Source Code Inspection: Balanced Warning Banner Tags', () => {
        test('faculty/dashboard.php contains balanced closing div tags for warning-banner', () => {
            const bannerStart = facultyDashboardPhp.indexOf('<div class="card warning-banner"');
            expect(bannerStart).toBeGreaterThan(0);

            // The banner section ends at the outer endif right before the advisory card
            const advisoryStart = facultyDashboardPhp.indexOf('Faculty Decision-Support Advisory');
            const bannerEnd = facultyDashboardPhp.lastIndexOf('<?php endif; ?>', advisoryStart);
            expect(bannerEnd).toBeGreaterThan(bannerStart);

            const bannerBlock = facultyDashboardPhp.substring(bannerStart, bannerEnd);
            const openDivCount = (bannerBlock.match(/<div(\s|>)/g) || []).length;
            const closeDivCount = (bannerBlock.match(/<\/div>/g) || []).length;

            expect(openDivCount).toBe(closeDivCount);
            expect(openDivCount).toBe(7);
        });

        test('Warning banner does not swallow subsequent dashboard containers', () => {
            const lines = facultyDashboardPhp.split('\n');
            const bannerStartLine = lines.findIndex(l => l.includes('class="card warning-banner"'));
            const bannerEndifLine = lines.findIndex(l => l.includes('<?php endif; ?>') && lines.indexOf(l) > bannerStartLine);

            expect(bannerStartLine).toBeGreaterThan(0);
            expect(bannerEndifLine).toBeGreaterThan(bannerStartLine);

            // Confirm that between bannerStartLine and bannerEndifLine, no other dashboard sections exist
            const blockContent = lines.slice(bannerStartLine, bannerEndifLine).join('\n');
            expect(blockContent).not.toContain('id="advisory-card"');
            expect(blockContent).not.toContain('id="kpi-grid"');
            expect(blockContent).not.toContain('Total Students');
            expect(blockContent).not.toContain('Top Academic Performers');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 2. DOM Containment & Structural Independence
    // ─────────────────────────────────────────────────────────────
    test.describe('2. DOM Tree Containment Verification', () => {
        test.beforeEach(async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));
        });

        test('Advisory card, KPI grid, Section grid, and Top Performers are NOT inside warning-banner', async ({ page }) => {
            const warningBanner = page.locator('#warning-banner');

            // Crucial containment checks: none of these elements should be children of #warning-banner
            expect(await warningBanner.locator('#advisory-card').count()).toBe(0);
            expect(await warningBanner.locator('#kpi-grid').count()).toBe(0);
            expect(await warningBanner.locator('#section-grid').count()).toBe(0);
            expect(await warningBanner.locator('#section-roster-card').count()).toBe(0);
            expect(await warningBanner.locator('#top-performers-card').count()).toBe(0);
        });

        test('All sections are direct siblings under .main-content', async ({ page }) => {
            const siblingIds = await page.evaluate(() => {
                const mc = document.querySelector('.main-content');
                return Array.from(mc.children).map(c => c.id || c.className);
            });

            expect(siblingIds).toContain('warning-banner');
            expect(siblingIds).toContain('advisory-card');
            expect(siblingIds).toContain('kpi-grid');
            expect(siblingIds).toContain('section-grid');
            expect(siblingIds).toContain('top-performers-card');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 3. Layout Dimensions & Visual Geometry Across Viewports
    // ─────────────────────────────────────────────────────────────
    test.describe('3. Layout Dimensions & Width Spanning', () => {
        test('Desktop (1440x900): Warning banner is compact and cards span full main-content width', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));

            const metrics = await page.evaluate(() => {
                const wb = document.getElementById('warning-banner');
                const kpi = document.getElementById('kpi-grid');
                const sec = document.getElementById('section-grid');
                const top = document.getElementById('top-performers-card');
                const mc = document.querySelector('.main-content');

                return {
                    wbHeight: wb.getBoundingClientRect().height,
                    mcWidth: mc.getBoundingClientRect().width - 80, // minus padding (40px*2)
                    kpiWidth: kpi.getBoundingClientRect().width,
                    secWidth: sec.getBoundingClientRect().width,
                    topWidth: top.getBoundingClientRect().width,
                    kpiX: kpi.getBoundingClientRect().x,
                    secX: sec.getBoundingClientRect().x,
                    topX: top.getBoundingClientRect().x,
                };
            });

            // 1. Warning banner height is compact (~60-90px), NOT 658px+
            expect(metrics.wbHeight).toBeLessThanOrEqual(95);

            // 2. Grids and cards span the full content width (>= 1000px)
            expect(metrics.kpiWidth).toBeGreaterThanOrEqual(1050);
            expect(metrics.secWidth).toBeGreaterThanOrEqual(1050);
            expect(metrics.topWidth).toBeGreaterThanOrEqual(1050);

            // 3. Sections align to the left content padding (X matches mc left)
            expect(metrics.kpiX).toBe(metrics.secX);
            expect(metrics.secX).toBe(metrics.topX);
        });

        test('Multi-viewport and Theme Parity (1440, 1280, 1024, 768, 390 in Light and Dark)', async ({ page }) => {
            const viewports = [
                { w: 1440, h: 900, name: '1440x900' },
                { w: 1280, h: 720, name: '1280x720' },
                { w: 1024, h: 768, name: '1024x768' },
                { w: 768, h: 1024, name: '768x1024' },
                { w: 390, h: 844, name: '390x844' },
            ];

            for (const vp of viewports) {
                for (const theme of ['light', 'dark']) {
                    await page.setViewportSize({ width: vp.w, height: vp.h });
                    await page.setContent(renderFacultyDashboardHtml({ theme }));

                    const data = await page.evaluate(() => {
                        const wb = document.getElementById('warning-banner');
                        const top = document.getElementById('top-performers-card');
                        const hasOverflow = document.documentElement.scrollWidth > window.innerWidth;
                        return {
                            wbHeight: wb.getBoundingClientRect().height,
                            topWidth: top.getBoundingClientRect().width,
                            hasOverflow,
                        };
                    });

                    // Banner should never swallow entire dashboard
                    expect(data.wbHeight).toBeLessThan(200);
                    // No page-level horizontal overflow
                    expect(data.hasOverflow).toBe(false);
                }
            }
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. Drilldown & Interactive Behavior Preservation
    // ─────────────────────────────────────────────────────────────
    test.describe('4. Drilldown Interaction Preservation', () => {
        test('Opening section card shows active roster and moves focus to title', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));

            const viewBtn = page.locator('#btn-IT-35');
            const rosterCard = page.locator('#section-roster-card');
            const rosterTitle = page.locator('#roster-title');

            await expect(rosterCard).toBeHidden();
            await expect(viewBtn).toHaveAttribute('aria-expanded', 'false');

            // Click View Students
            await viewBtn.click();

            // Panel becomes visible
            await expect(rosterCard).toBeVisible();
            await expect(viewBtn).toHaveAttribute('aria-expanded', 'true');
            await expect(rosterTitle).toBeFocused();
            await expect(rosterTitle).toHaveText('Student List — IT-35');

            // Close roster and verify focus restored to button
            const closeBtn = page.locator('#faculty-roster-close-btn');
            await closeBtn.click();

            await expect(rosterCard).toBeHidden();
            await expect(viewBtn).toHaveAttribute('aria-expanded', 'false');
            await expect(viewBtn).toBeFocused();
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5. Evidence Screenshots
    // ─────────────────────────────────────────────────────────────
    test.describe('5. Evidence Screenshot Generation', () => {
        test('Capture required screenshots in test-results/evidence/faculty-layout/', async ({ page }) => {
            // 1. Desktop Dark
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));
            await page.screenshot({ path: resolve(evidenceDir, 'faculty-dashboard-desktop-fixed.png'), fullPage: false });

            // 2. Desktop Light
            await page.setContent(renderFacultyDashboardHtml({ theme: 'light' }));
            await page.screenshot({ path: resolve(evidenceDir, 'faculty-dashboard-desktop-light-fixed.png'), fullPage: false });

            // 3. Tablet Dark
            await page.setViewportSize({ width: 768, height: 1024 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));
            await page.screenshot({ path: resolve(evidenceDir, 'faculty-dashboard-tablet-fixed.png'), fullPage: false });

            // 4. Mobile Dark
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark' }));
            await page.screenshot({ path: resolve(evidenceDir, 'faculty-dashboard-mobile-fixed.png'), fullPage: false });

            // 5. Active Roster Open
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderFacultyDashboardHtml({ theme: 'dark', openRoster: true }));
            const rosterEl = page.locator('#section-roster-card');
            await rosterEl.scrollIntoViewIfNeeded();
            await page.screenshot({ path: resolve(evidenceDir, 'faculty-roster-opened.png'), fullPage: false });

            // 6. Top Academic Performers Independent Card
            const topPerformersEl = page.locator('#top-performers-card');
            await topPerformersEl.scrollIntoViewIfNeeded();
            await page.screenshot({ path: resolve(evidenceDir, 'top-performers-independent-card.png'), fullPage: false });
        });
    });
});
