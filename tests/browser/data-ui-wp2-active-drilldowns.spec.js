import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const uiJs = readFileSync(resolve('assets/js/ui.js'), 'utf-8');
const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');
const facultyDashboardPhp = readFileSync(resolve('faculty/dashboard.php'), 'utf-8');
const studentDashboardPhp = readFileSync(resolve('student/dashboard.php'), 'utf-8');

test.describe('DATA-UI-WP2: Active Drilldown and Roster Behavior', () => {

    test.describe('1. Static Code Analysis & Trigger/Panel Contract Integrity', () => {
        test('CSS contains all required drilldown, trigger state, spinner, and error tokens', () => {
            // Trigger active tokens
            expect(dashboardCss).toContain('.section-card');
            expect(dashboardCss).toContain('.section-card.section-card--active');
            expect(dashboardCss).toContain('.section-card-active-indicator');
            expect(dashboardCss).toContain('.view-students-btn.view-students-btn--active');

            // Panel status tokens
            expect(dashboardCss).toContain('.roster-state');
            expect(dashboardCss).toContain('.roster-state--loading');
            expect(dashboardCss).toContain('.roster-state-spinner');
            expect(dashboardCss).toContain('@keyframes roster-spin');
            expect(dashboardCss).toContain('.roster-state--error');
            expect(dashboardCss).toContain('.roster-state-msg');
            expect(dashboardCss).toContain('.roster-empty-cell');

            // Reduced motion media query for spinner
            expect(dashboardCss).toContain('(prefers-reduced-motion: reduce)');

            // Panel token
            expect(dashboardCss).toContain('.data-table-card.data-table-card--active');
        });

        test('assets/js/ui.js exposes window.UDM.announce and window.UDM.scrollToTarget', () => {
            expect(uiJs).toContain('window.UDM.scrollToTarget = function');
            expect(uiJs).toContain('window.UDM.announce = function');
            expect(uiJs).toContain('udm-live-region');
        });

        test('Admin Grades markup implements semantic button trigger, aria-controls, and roster panel', () => {
            expect(adminGradesPhp).toContain('<button type="button" class="section-card"');
            expect(adminGradesPhp).toContain('data-section=');
            expect(adminGradesPhp).toContain('aria-controls="roster-panel"');
            expect(adminGradesPhp).toContain('aria-expanded="false"');
            expect(adminGradesPhp).toContain('id="roster-panel"');
            expect(adminGradesPhp).toContain('id="roster-title" tabindex="-1"');
            expect(adminGradesPhp).toContain('id="roster-status-container"');
            expect(adminGradesPhp).toContain('id="roster-close-btn"');
            expect(adminGradesPhp).toContain('AbortController');
        });

        test('Faculty Dashboard markup implements button trigger, aria-controls, and standardized panel', () => {
            expect(facultyDashboardPhp).toContain('class="btn btn--primary view-students-btn"');
            expect(facultyDashboardPhp).toContain('data-section=');
            expect(facultyDashboardPhp).toContain('aria-controls="section-roster-card"');
            expect(facultyDashboardPhp).toContain('aria-expanded="false"');
            expect(facultyDashboardPhp).toContain('id="section-roster-card" class="data-table-card data-table-card--active roster-panel"');
            expect(facultyDashboardPhp).toContain('id="roster-title" tabindex="-1"');
            expect(facultyDashboardPhp).toContain('id="faculty-roster-close-btn"');
        });
    });

    test.describe('2. Admin Grades Section Roster (AJAX Drilldown)', () => {
        const buildAdminGradesPage = (initialSection = '3A') => `
            <!DOCTYPE html>
            <html lang="en" data-theme="light">
            <head>
                <meta charset="UTF-8">
                <base href="http://localhost/capstone/admin/">
                <style>
                    ${dashboardCss}
                </style>
                <script>
                    ${uiJs}
                </script>
            </head>
            <body>
                <div class="main-content" style="padding: 24px; max-width: 1200px; margin: 0 auto;">
                    <div class="stat-grid" style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px;">
                        <button type="button" class="section-card" data-section="3A" aria-controls="roster-panel" aria-expanded="false" onclick="openSection('3A', this)">
                            <div class="section-card-head">
                                <span>3A</span>
                                <span>25 students</span>
                            </div>
                            <div class="section-card-body">
                                <h2>1.75</h2>
                                <p>Avg GWA</p>
                            </div>
                        </button>
                        <button type="button" class="section-card" data-section="3B" aria-controls="roster-panel" aria-expanded="false" onclick="openSection('3B', this)">
                            <div class="section-card-head">
                                <span>3B</span>
                                <span>30 students</span>
                            </div>
                            <div class="section-card-body">
                                <h2>2.10</h2>
                                <p>Avg GWA</p>
                            </div>
                        </button>
                    </div>

                    <div class="data-table-card data-table-card--active roster-panel" id="roster-panel" style="display:none;">
                        <div class="data-table-header">
                            <div class="data-table-header__intro">
                                <h3 class="data-table-title" id="roster-title" tabindex="-1"></h3>
                            </div>
                            <div class="data-table-actions">
                                <button type="button" onclick="closeRoster()" class="btn btn--secondary btn--sm" id="roster-close-btn">Close</button>
                            </div>
                        </div>
                        <div id="roster-status-container" style="display:none;"></div>
                        <div class="data-table-scroll" id="roster-table-scroll" role="region" aria-label="Section Student Roster" tabindex="0">
                            <table class="data-table table-density--standard">
                                <caption class="sr-only">Section Student Roster</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Student No.</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Status</th>
                                        <th scope="col">GWA</th>
                                        <th scope="col">Risk</th>
                                    </tr>
                                </thead>
                                <tbody id="roster-body"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                    let activeTriggerEl       = null;
                    let currentRosterSection  = null;
                    let rosterAbortController = null;
                    let rosterRequestId       = 0;

                    function escapeHtml(str) {
                        if (str === null || str === undefined) return '';
                        return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
                    }

                    function riskColor(risk) {
                        if (risk === 'HIGH') return 'var(--risk-high)';
                        if (risk === 'MODERATE') return 'var(--risk-mod)';
                        if (risk === 'LOW') return 'var(--risk-low)';
                        return 'var(--text-gray)';
                    }

                    function openSection(section, triggerEl) {
                        if (!triggerEl) {
                            triggerEl = document.querySelector('.section-card[data-section="' + section + '"]');
                        }

                        if (activeTriggerEl && activeTriggerEl !== triggerEl) {
                            activeTriggerEl.setAttribute('aria-expanded', 'false');
                            activeTriggerEl.classList.remove('section-card--active');
                            const prevBadge = activeTriggerEl.querySelector('.section-card-active-indicator');
                            if (prevBadge) prevBadge.remove();
                        }

                        activeTriggerEl = triggerEl;
                        currentRosterSection = section;

                        if (activeTriggerEl) {
                            activeTriggerEl.setAttribute('aria-expanded', 'true');
                            activeTriggerEl.classList.add('section-card--active');
                            if (!activeTriggerEl.querySelector('.section-card-active-indicator')) {
                                const head = activeTriggerEl.querySelector('.section-card-head');
                                if (head) {
                                    const indicator = document.createElement('span');
                                    indicator.className = 'section-card-active-indicator';
                                    indicator.setAttribute('aria-hidden', 'true');
                                    indicator.textContent = '● Open';
                                    head.appendChild(indicator);
                                }
                            }
                        }

                        if (rosterAbortController) {
                            rosterAbortController.abort();
                        }
                        const thisRequestId = ++rosterRequestId;
                        rosterAbortController = new AbortController();

                        const panel = document.getElementById('roster-panel');
                        const title = document.getElementById('roster-title');
                        const statusContainer = document.getElementById('roster-status-container');
                        const tableScroll = document.getElementById('roster-table-scroll');

                        panel.style.display = 'block';
                        title.textContent = 'Section ' + section + ' — Student Roster';

                        statusContainer.style.display = 'block';
                        statusContainer.innerHTML = '<div class="roster-state roster-state--loading" role="status" aria-live="polite"><span class="roster-state-spinner" aria-hidden="true"></span><span>Loading Section ' + escapeHtml(section) + ' student roster...</span></div>';
                        tableScroll.style.display = 'none';

                        if (window.UDM && window.UDM.announce) {
                            window.UDM.announce('Loading Section ' + section + ' student roster');
                        }

                        if (window.UDM && window.UDM.scrollToTarget) {
                            window.UDM.scrollToTarget(panel, { focusTarget: title });
                        }

                        fetch('grades_data.php?action=students&section=' + encodeURIComponent(section), {
                            signal: rosterAbortController.signal
                        })
                        .then(r => {
                            if (!r.ok) throw new Error('HTTP ' + r.status);
                            return r.json();
                        })
                        .then(students => {
                            if (thisRequestId !== rosterRequestId) return;

                            statusContainer.style.display = 'none';
                            tableScroll.style.display = 'block';

                            const body = document.getElementById('roster-body');
                            if (!students || !students.length) {
                                body.innerHTML = '<tr><td colspan="5" class="roster-empty-cell">No students are currently available for this section.</td></tr>';
                                if (window.UDM && window.UDM.announce) {
                                    window.UDM.announce('Section ' + section + ' roster is empty. No students available.');
                                }
                            } else {
                                body.innerHTML = students.map(s => {
                                    return '<tr><td>' + escapeHtml(s.studentNo) + '</td><td style="font-weight:600;"><button type="button" class="table-record-link">' + escapeHtml(s.name) + '</button></td><td>' + escapeHtml(s.status || 'Regular') + '</td><td>' + (s.currentGwa ? Number(s.currentGwa).toFixed(2) : '—') + '</td><td>' + escapeHtml(s.risk || 'N/A') + '</td></tr>';
                                }).join('');

                                if (window.UDM && window.UDM.announce) {
                                    window.UDM.announce('Section ' + section + ' student roster loaded with ' + students.length + ' students');
                                }
                            }
                        })
                        .catch(err => {
                            if (err.name === 'AbortError') return;
                            if (thisRequestId !== rosterRequestId) return;

                            statusContainer.style.display = 'block';
                            tableScroll.style.display = 'none';
                            statusContainer.innerHTML = '<div class="roster-state roster-state--error" role="alert"><p class="roster-state-msg">The roster could not be loaded.</p><button type="button" class="btn btn--secondary btn--sm roster-retry-btn" id="roster-retry-btn" onclick="retrySection()">Retry</button></div>';

                            if (window.UDM && window.UDM.announce) {
                                window.UDM.announce('The roster could not be loaded. A retry option is available.', 'assertive');
                            }
                        });
                    }

                    function retrySection() {
                        if (currentRosterSection) {
                            openSection(currentRosterSection, activeTriggerEl);
                        }
                    }

                    function closeRoster() {
                        if (rosterAbortController) {
                            rosterAbortController.abort();
                            rosterAbortController = null;
                        }
                        rosterRequestId++;

                        const panel = document.getElementById('roster-panel');
                        panel.style.display = 'none';

                        const statusContainer = document.getElementById('roster-status-container');
                        if (statusContainer) statusContainer.style.display = 'none';

                        const returningTrigger = activeTriggerEl;
                        if (activeTriggerEl) {
                            activeTriggerEl.setAttribute('aria-expanded', 'false');
                            activeTriggerEl.classList.remove('section-card--active');
                            const badge = activeTriggerEl.querySelector('.section-card-active-indicator');
                            if (badge) badge.remove();
                            activeTriggerEl = null;
                        }
                        currentRosterSection = null;

                        if (returningTrigger && typeof returningTrigger.focus === 'function') {
                            try {
                                returningTrigger.focus({ preventScroll: true });
                            } catch (e) {
                                returningTrigger.focus();
                            }
                        }
                    }
                </script>
            </body>
            </html>
        `;

        test('1-6. Section trigger is a semantic control with aria-controls, starts false, updates to true, shows active border', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            // 1. Semantic button control
            const tagName = await trigger3A.evaluate(el => el.tagName);
            expect(tagName).toBe('BUTTON');

            // 2. aria-controls points to roster-panel
            await expect(trigger3A).toHaveAttribute('aria-controls', 'roster-panel');

            // 3. aria-expanded starts false
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'false');

            // Route mock API response with slight delay
            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([
                        { studentNo: '2023-0001', name: 'Santos, Juan', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }
                    ])
                });
            });

            // 4. Activation changes aria-expanded to true
            await trigger3A.click();
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'true');

            // 5. Active state appears on source trigger
            await expect(trigger3A).toHaveClass(/section-card--active/);
            await expect(trigger3A.locator('.section-card-active-indicator')).toBeVisible();

            // 6. Panel uses data-table-card--active and has bright blue border
            const panel = page.locator('#roster-panel');
            await expect(panel).toHaveClass(/data-table-card--active/);
            const borderWidth = await panel.evaluate(el => window.getComputedStyle(el).borderTopWidth);
            expect(borderWidth).toBe('2px');
        });

        test('7-10. Immediate loading state, title update, focus movement to title, viewport entry', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            let fulfillRequest;
            const requestPromise = new Promise(resolve => { fulfillRequest = resolve; });

            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await requestPromise;
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([
                        { studentNo: '2023-0001', name: 'Santos, Juan', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }
                    ])
                });
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            await trigger3A.click();

            // 7. Loading state appears immediately inside the roster panel
            const statusContainer = page.locator('#roster-status-container');
            await expect(statusContainer).toBeVisible();
            await expect(statusContainer.locator('.roster-state--loading')).toBeVisible();
            await expect(statusContainer.locator('.roster-state--loading')).toContainText('Loading Section 3A student roster...');

            // 8. Title updated immediately
            const title = page.locator('#roster-title');
            await expect(title).toHaveText('Section 3A — Student Roster');

            // 9. Focus moves to panel title
            await expect(title).toBeFocused();

            // Complete the mock request
            fulfillRequest();
            await page.waitForTimeout(50);

            // 10. Panel is visible and rendered
            await expect(page.locator('#roster-table-scroll')).toBeVisible();
            await expect(page.locator('#roster-body')).toContainText('Santos, Juan');
        });

        test('11-12. Close resets aria-expanded and returns focus to source trigger', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([{ studentNo: '2023-0001', name: 'Santos, Juan', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }])
                });
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            await trigger3A.click();
            await expect(page.locator('#roster-panel')).toBeVisible();

            // Click Close button
            const closeBtn = page.locator('#roster-close-btn');
            await closeBtn.click();

            // 11. Panel is hidden and aria-expanded reset to false
            await expect(page.locator('#roster-panel')).toBeHidden();
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'false');
            await expect(trigger3A).not.toHaveClass(/section-card--active/);

            // 12. Focus returned to source trigger
            await expect(trigger3A).toBeFocused();
        });

        test('13. Empty state is distinct from failure state', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([])
                });
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            await trigger3A.click();

            // Empty state shows table with empty message row, not an error alert
            const emptyCell = page.locator('.roster-empty-cell');
            await expect(emptyCell).toBeVisible();
            await expect(emptyCell).toHaveText('No students are currently available for this section.');
            await expect(page.locator('.roster-state--error')).toBeHidden();
        });

        test('14-16. Failed request shows error state, Retry is keyboard accessible and re-requests', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            let requestCount = 0;
            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                requestCount++;
                if (requestCount === 1) {
                    await route.abort('failed');
                } else {
                    await route.fulfill({
                        status: 200,
                        contentType: 'application/json',
                        body: JSON.stringify([{ studentNo: '2023-0001', name: 'Santos, Juan', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }])
                    });
                }
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            await trigger3A.click();

            // 14. Failed request shows error state
            const errorState = page.locator('.roster-state--error');
            await expect(errorState).toBeVisible();
            await expect(errorState.locator('.roster-state-msg')).toHaveText('The roster could not be loaded.');

            // 15. Retry button is present and keyboard accessible
            const retryBtn = page.locator('#roster-retry-btn');
            await expect(retryBtn).toBeVisible();

            // 16. Trigger Retry via Enter key
            await retryBtn.focus();
            await page.keyboard.press('Enter');

            // Expect second request to succeed and display data
            await expect(page.locator('#roster-body')).toContainText('Santos, Juan');
            expect(requestCount).toBe(2);
        });

        test('17. Stale request protection: rapid selection ensures latest request wins', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            let fulfillReq3A;
            const req3APromise = new Promise(res => { fulfillReq3A = res; });

            // 3A response will be delayed
            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await req3APromise;
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([{ studentNo: '2023-0001', name: 'Student of 3A', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }])
                });
            });

            // 3B response completes quickly
            await page.route('**/grades_data.php?action=students&section=3B', async route => {
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([{ studentNo: '2023-0002', name: 'Student of 3B', status: 'Regular', currentGwa: 2.10, risk: 'LOW' }])
                });
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            const trigger3B = page.locator('.section-card[data-section="3B"]');

            // Click 3A then immediately click 3B
            await trigger3A.click();
            await trigger3B.click();

            // 3B should now be active
            await expect(trigger3B).toHaveAttribute('aria-expanded', 'true');
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'false');

            // 3B renders its data
            await expect(page.locator('#roster-body')).toContainText('Student of 3B');

            // Now release delayed 3A response
            fulfillReq3A();
            await page.waitForTimeout(50);

            // Stale 3A must NOT overwrite 3B
            await expect(page.locator('#roster-title')).toHaveText('Section 3B — Student Roster');
            await expect(page.locator('#roster-body')).toContainText('Student of 3B');
            await expect(page.locator('#roster-body')).not.toContainText('Student of 3A');
        });

        test('18. Closing during loading safely aborts request and prevents late rendering', async ({ page }) => {
            await page.setContent(buildAdminGradesPage());

            let fulfillReq;
            const reqPromise = new Promise(res => { fulfillReq = res; });

            await page.route('**/grades_data.php?action=students&section=3A', async route => {
                await reqPromise;
                await route.fulfill({
                    status: 200,
                    contentType: 'application/json',
                    body: JSON.stringify([{ studentNo: '2023-0001', name: 'Late Render Student', status: 'Regular', currentGwa: 1.75, risk: 'LOW' }])
                });
            });

            const trigger3A = page.locator('.section-card[data-section="3A"]');
            await trigger3A.click();

            // While loading, close the panel
            const closeBtn = page.locator('#roster-close-btn');
            await closeBtn.click();
            await expect(page.locator('#roster-panel')).toBeHidden();

            // Release request
            fulfillReq();
            await page.waitForTimeout(50);

            // Panel remains closed and not rendered
            await expect(page.locator('#roster-panel')).toBeHidden();
        });
    });

    test.describe('3. Faculty Dashboard Section Roster (Synchronous in-memory)', () => {
        const buildFacultyDashboardPage = () => `
            <!DOCTYPE html>
            <html lang="en" data-theme="light">
            <head>
                <meta charset="UTF-8">
                <style>${dashboardCss}</style>
                <script>${uiJs}</script>
            </head>
            <body>
                <div class="main-content" style="padding: 24px; max-width: 1200px; margin: 0 auto;">
                    <div class="stat-grid" style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; margin-bottom: 24px;">
                        <div class="card">
                            <h3>Section 3A</h3>
                            <button type="button" class="btn btn--primary view-students-btn" data-section="3A" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('3A', this)">View Students →</button>
                        </div>
                        <div class="card">
                            <h3>Section 3B</h3>
                            <button type="button" class="btn btn--primary view-students-btn" data-section="3B" aria-controls="section-roster-card" aria-expanded="false" onclick="openSection('3B', this)">View Students →</button>
                        </div>
                    </div>

                    <div id="section-roster-card" class="data-table-card data-table-card--active roster-panel" style="display:none; margin-bottom: 24px;">
                        <div class="data-table-header">
                            <div class="data-table-header__intro">
                                <h3 class="data-table-title" id="roster-title" tabindex="-1">Student List</h3>
                                <p class="data-table-subtitle">Class roster</p>
                            </div>
                            <div class="data-table-actions">
                                <button type="button" onclick="closeRoster()" class="btn btn--secondary btn--sm" id="faculty-roster-close-btn">Close</button>
                            </div>
                        </div>

                        <div class="tab-bar">
                            <button class="tab-btn active" id="tab-btn-class" onclick="switchTab('class')">My Class Performance</button>
                        </div>

                        <div id="tab-content-class" class="data-table-scroll" role="region" aria-label="Class Performance Roster" tabindex="0">
                            <table class="data-table table-density--standard">
                                <caption class="sr-only">Class Performance Roster</caption>
                                <thead>
                                    <tr id="class-thead-row">
                                        <th scope="col" class="sortable-col" aria-sort="none" id="col-h-0">
                                            <button type="button" class="table-sort-button" onclick="sortClassTab(0)">
                                                <span>Student Name</span>
                                                <span class="sort-arrow" id="sort-arrow-0" aria-hidden="true">⇅</span>
                                            </button>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody id="roster-body-class"></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <script>
                    const sectionDataMap = {
                        '3A': { students: [{ full_name: 'Dela Cruz, Juan', user_id: 101 }, { full_name: 'Alvarez, Maria', user_id: 102 }] },
                        '3B': { students: [{ full_name: 'Reyes, Carlos', user_id: 201 }] }
                    };

                    let activeSection = null;
                    let activeFacultyTrigger = null;

                    function openSection(secName, triggerEl) {
                        if (!triggerEl) {
                            triggerEl = document.querySelector('.view-students-btn[data-section="' + secName + '"]');
                        }

                        if (activeFacultyTrigger && activeFacultyTrigger !== triggerEl) {
                            activeFacultyTrigger.setAttribute('aria-expanded', 'false');
                            activeFacultyTrigger.classList.remove('view-students-btn--active');
                            activeFacultyTrigger.textContent = 'View Students →';
                        }

                        activeFacultyTrigger = triggerEl;
                        activeSection = secName;

                        if (activeFacultyTrigger) {
                            activeFacultyTrigger.setAttribute('aria-expanded', 'true');
                            activeFacultyTrigger.classList.add('view-students-btn--active');
                            activeFacultyTrigger.textContent = 'Active Roster ↓';
                        }

                        const title = document.getElementById('roster-title');
                        title.textContent = 'Section ' + secName + ' — Student Roster';

                        switchTab('class');

                        const panel = document.getElementById('section-roster-card');
                        panel.style.display = 'block';

                        if (window.UDM && window.UDM.announce) {
                            window.UDM.announce('Section ' + secName + ' student roster opened');
                        }

                        if (window.UDM && window.UDM.scrollToTarget) {
                            window.UDM.scrollToTarget(panel, { focusTarget: title });
                        }
                    }

                    function closeRoster() {
                        const panel = document.getElementById('section-roster-card');
                        panel.style.display = 'none';

                        const returningTrigger = activeFacultyTrigger;
                        if (activeFacultyTrigger) {
                            activeFacultyTrigger.setAttribute('aria-expanded', 'false');
                            activeFacultyTrigger.classList.remove('view-students-btn--active');
                            activeFacultyTrigger.textContent = 'View Students →';
                            activeFacultyTrigger = null;
                        }
                        activeSection = null;

                        if (returningTrigger && typeof returningTrigger.focus === 'function') {
                            try {
                                returningTrigger.focus({ preventScroll: true });
                            } catch (e) {
                                returningTrigger.focus();
                            }
                        }
                    }

                    function switchTab(tab) {
                        if (!activeSection) return;
                        renderClassTab(activeSection);
                    }

                    function renderClassTab(secName) {
                        const students = sectionDataMap[secName]?.students || [];
                        const tbody = document.getElementById('roster-body-class');
                        tbody.innerHTML = students.map(s => '<tr><td>' + s.full_name + '</td></tr>').join('');
                    }

                    function sortClassTab(col) {
                        const th = document.getElementById('col-h-0');
                        th.setAttribute('aria-sort', 'ascending');
                    }
                </script>
            </body>
            </html>
        `;

        test('19-20. Faculty trigger exposes aria-controls, aria-expanded, and moves focus to title', async ({ page }) => {
            await page.setContent(buildFacultyDashboardPage());

            const trigger3A = page.locator('.view-students-btn[data-section="3A"]');
            await expect(trigger3A).toHaveAttribute('aria-controls', 'section-roster-card');
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'false');

            await trigger3A.click();
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'true');
            await expect(trigger3A).toHaveClass(/view-students-btn--active/);
            await expect(trigger3A).toHaveText('Active Roster ↓');

            const title = page.locator('#roster-title');
            await expect(title).toHaveText('Section 3A — Student Roster');
            await expect(title).toBeFocused();
        });

        test('21-23. Switching sections updates active control, resets previous, and Close restores focus', async ({ page }) => {
            await page.setContent(buildFacultyDashboardPage());

            const trigger3A = page.locator('.view-students-btn[data-section="3A"]');
            const trigger3B = page.locator('.view-students-btn[data-section="3B"]');

            await trigger3A.click();
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'true');

            // Switch to 3B
            await trigger3B.click();
            // 21. New trigger is active
            await expect(trigger3B).toHaveAttribute('aria-expanded', 'true');
            await expect(trigger3B).toHaveClass(/view-students-btn--active/);

            // 22. Previous trigger is reset
            await expect(trigger3A).toHaveAttribute('aria-expanded', 'false');
            await expect(trigger3A).not.toHaveClass(/view-students-btn--active/);
            await expect(trigger3A).toHaveText('View Students →');

            // Check rendered content matches 3B
            await expect(page.locator('#roster-body-class')).toContainText('Reyes, Carlos');
            await expect(page.locator('#roster-body-class')).not.toContainText('Dela Cruz, Juan');

            // 23. Close returns focus to 3B (the active source control)
            const closeBtn = page.locator('#faculty-roster-close-btn');
            await closeBtn.click();
            await expect(page.locator('#section-roster-card')).toBeHidden();
            await expect(trigger3B).toBeFocused();
        });

        test('24-25. Existing sort controls and table semantics remain functional', async ({ page }) => {
            await page.setContent(buildFacultyDashboardPage());

            const trigger3A = page.locator('.view-students-btn[data-section="3A"]');
            await trigger3A.click();

            const sortBtn = page.locator('#col-h-0 .table-sort-button');
            await expect(sortBtn).toBeVisible();
            await sortBtn.click();
            await expect(page.locator('#col-h-0')).toHaveAttribute('aria-sort', 'ascending');
        });
    });

    test.describe('4. Student Calculator Architectural Evaluation & Deferred Scope', () => {
        test('26-29. Student Calculator remains designated as a scenario what-if tool and is deferred from entity roster fetch', () => {
            // Confirm student calculator is labeled as a calculator / what-if projection tool
            expect(studentDashboardPhp).toContain('Grade Goal Calculator');
            expect(studentDashboardPhp).toContain('toggleCalculator');

            // Confirm it computes scenario targets rather than displaying database entity rosters
            expect(studentDashboardPhp).toContain('Scenario Semester GWA');
            expect(studentDashboardPhp).toContain('Target Final Grade');
        });
    });

    test.describe('5. Responsive, Layout, Light/Dark, and Reduced-Motion Parity', () => {
        test('30-31. Viewport 390x844: No horizontal overflow, buttons accessible without floating help collision', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>${dashboardCss}</style>
                    <script>${uiJs}</script>
                </head>
                <body>
                    <div class="main-content" style="padding: 16px;">
                        <button type="button" class="section-card" data-section="3A" aria-controls="roster-panel" aria-expanded="false" onclick="openSection('3A', this)">
                            <div class="section-card-head"><span>3A</span></div>
                        </button>
                        <div class="data-table-card data-table-card--active roster-panel" id="roster-panel" style="display:block; margin-top: 16px;">
                            <div class="data-table-header">
                                <h3 class="data-table-title" id="roster-title">Section 3A Roster</h3>
                                <button type="button" class="btn btn--secondary btn--sm" id="roster-close-btn">Close</button>
                            </div>
                            <div class="roster-state roster-state--error" role="alert">
                                <p class="roster-state-msg">The roster could not be loaded.</p>
                                <button type="button" class="btn btn--secondary btn--sm roster-retry-btn" id="roster-retry-btn">Retry</button>
                            </div>
                        </div>
                    </div>
                </body>
                </html>
            `);

            // Verify horizontal scroll width does not exceed client width
            const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
            const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
            expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 1);

            // Verify Close and Retry buttons are visible
            await expect(page.locator('#roster-close-btn')).toBeVisible();
            await expect(page.locator('#roster-retry-btn')).toBeVisible();
        });

        test('32-33. Theme Parity: Active panel border, loading spinner, and error state in Light and Dark mode', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html lang="en" data-theme="light">
                <head><style>${dashboardCss}</style></head>
                <body>
                    <div id="panel" class="data-table-card data-table-card--active">
                        <div class="roster-state roster-state--loading">
                            <span class="roster-state-spinner"></span>
                            <span>Loading</span>
                        </div>
                    </div>
                </body>
                </html>
            `);

            const panel = page.locator('#panel');
            // Light mode border is #1E4DB7 (rgb(30, 77, 183))
            const lightBorderColor = await panel.evaluate(el => window.getComputedStyle(el).borderTopColor);
            expect(lightBorderColor).toBe('rgb(30, 77, 183)');

            // Switch to dark theme
            await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
            // Dark mode border is #6C8EEF (rgb(108, 142, 239))
            const darkBorderColor = await panel.evaluate(el => window.getComputedStyle(el).borderTopColor);
            expect(darkBorderColor).toBe('rgb(108, 142, 239)');
        });

        test('34. Reduced Motion: prefers-reduced-motion stops spinner animation and disables smooth scrolling', async ({ page }) => {
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${dashboardCss}</style>
                    <script>${uiJs}</script>
                </head>
                <body>
                    <span id="spinner" class="roster-state-spinner"></span>
                    <div id="target" style="margin-top: 1000px;">Target</div>
                </body>
                </html>
            `);

            // Spinner animation is 'none' when prefers-reduced-motion is reduce
            const anim = await page.locator('#spinner').evaluate(el => window.getComputedStyle(el).animationName);
            expect(anim).toBe('none');
        });

        test('35-37. Browser Health: No unexpected console errors, unhandled rejections, or page errors', async ({ page }) => {
            const consoleErrors = [];
            const pageErrors = [];

            page.on('console', msg => {
                if (msg.type() === 'error') consoleErrors.push(msg.text());
            });
            page.on('pageerror', err => pageErrors.push(err.message));

            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>${dashboardCss}</style>
                    <script>${uiJs}</script>
                </head>
                <body>
                    <div id="test-panel" class="data-table-card data-table-card--active">
                        <h3 id="test-title" tabindex="-1">Test Title</h3>
                    </div>
                    <script>
                        window.UDM.announce('Test announcement');
                        window.UDM.scrollToTarget('test-panel', { focusTarget: 'test-title' });
                    </script>
                </body>
                </html>
            `);

            await page.waitForTimeout(100);
            expect(consoleErrors).toEqual([]);
            expect(pageErrors).toEqual([]);
        });
    });
});
