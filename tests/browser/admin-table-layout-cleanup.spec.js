import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');
const adminIndexPhp = readFileSync(resolve('admin/index.php'), 'utf-8');

test.describe('ADMIN-UI-HOTFIX-WP1: Student Directory and Dashboard Table Cleanup', () => {

    test.describe('Part 1: Student Directory Column and Empty State Integrity', () => {

        test('admin/students.php has exactly 9 columns and no Program column in thead', () => {
            // Extract thead contents
            const theadMatch = adminStudentsPhp.match(/<thead[^>]*>([\s\S]*?)<\/thead>/i);
            expect(theadMatch).toBeTruthy();
            const theadHtml = theadMatch[1];

            // Count th elements
            const thMatches = theadHtml.match(/<th\b[^>]*>(.*?)<\/th>/gi) || [];
            expect(thMatches.length).toBe(9);

            // Verify Program column header is completely absent
            expect(theadHtml).not.toMatch(/<th[^>]*>\s*Program\s*<\/th>/i);

            // Verify the 9 expected headers are present
            expect(theadHtml).toContain('Student No.');
            expect(theadHtml).toContain('Name');
            expect(theadHtml).toContain('Section');
            expect(theadHtml).toContain('Year');
            expect(theadHtml).toContain('Status');
            expect(theadHtml).toContain('Cumulative GWA');
            expect(theadHtml).toContain('Latest Risk');
            expect(theadHtml).toContain('Support Case');
            expect(theadHtml).toContain('Actions');
        });

        test('admin/students.php empty state rows use colspan="9"', () => {
            const emptyColspanMatches = adminStudentsPhp.match(/colspan=["']9["']/g) || [];
            // Both PHP initial fallback and client-side dynamic empty state should use colspan="9"
            expect(emptyColspanMatches.length).toBeGreaterThanOrEqual(2);
            // Neither should reference colspan="10"
            expect(adminStudentsPhp).not.toMatch(/colspan=["']10["']/);
        });

        test('admin/students.php provides clearStudentDirectoryFilters function', () => {
            expect(adminStudentsPhp).toContain('function clearStudentDirectoryFilters()');
        });

        test('Student Directory DOM: Empty state displays in tbody on zero matches and clears cleanly', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>Student Directory Test</title>
                </head>
                <body>
                    <input type="text" id="student-search" value="">
                    <select id="filter-risk"><option value="">All</option><option value="HIGH">High</option></select>
                    <select id="filter-status"><option value="">All</option><option value="Enrolled">Enrolled</option></select>
                    <select id="filter-support"><option value="">All</option><option value="OPEN">Open</option></select>

                    <table>
                        <thead>
                            <tr>
                                <th>Student No.</th>
                                <th>Name</th>
                                <th>Section</th>
                                <th>Year</th>
                                <th>Status</th>
                                <th>Cumulative GWA</th>
                                <th>Latest Risk</th>
                                <th>Support Case</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="students-tbody">
                            <tr data-search="2023-0001 santos juan" data-risk="LOW" data-status="Enrolled" data-support="">
                                <td>2023-0001</td>
                                <td>Santos, Juan</td>
                                <td>BSIT 3-A</td>
                                <td>3rd Year</td>
                                <td>Enrolled</td>
                                <td>1.75</td>
                                <td>LOW</td>
                                <td>None</td>
                                <td><button type="button">Edit</button></td>
                            </tr>
                        </tbody>
                    </table>
                    <div id="pagination-bar" style="display:flex;"><span>Page 1</span></div>

                    <script>
                        function clearStudentDirectoryFilters() {
                            const search = document.getElementById('student-search');
                            const risk = document.getElementById('filter-risk');
                            const status = document.getElementById('filter-status');
                            const support = document.getElementById('filter-support');
                            if (search) search.value = '';
                            if (risk) risk.value = '';
                            if (status) status.value = '';
                            if (support) support.value = '';
                            currentPage = 1;
                            renderPage();
                        }

                        const ROWS_PER_PAGE = 25;
                        let currentPage = 1;
                        function renderPage() {
                            const term = (document.getElementById('student-search')?.value || '').toLowerCase().trim();
                            const riskFilter = document.getElementById('filter-risk')?.value || '';
                            const supportFilter = document.getElementById('filter-support')?.value || '';
                            const statusFilter = document.getElementById('filter-status')?.value || '';

                            const allRows = Array.from(document.querySelectorAll('#students-tbody tr:not(#students-empty-row)'));

                            const visible = allRows.filter(row => {
                                const matchSearch = !term || (row.dataset.search && row.dataset.search.includes(term));
                                const matchRisk = !riskFilter || row.dataset.risk === riskFilter;
                                const matchStatus = !statusFilter || row.dataset.status === statusFilter;
                                let matchSupport = true;
                                if (supportFilter === 'OPEN') matchSupport = row.dataset.support === 'OPEN';
                                else if (supportFilter === 'NONE') matchSupport = !row.dataset.support;
                                return matchSearch && matchRisk && matchStatus && matchSupport;
                            });

                            allRows.forEach(r => r.style.display = 'none');

                            const bar = document.getElementById('pagination-bar');
                            let emptyRow = document.getElementById('students-empty-row');

                            if (visible.length === 0) {
                                if (!emptyRow) {
                                    emptyRow = document.createElement('tr');
                                    emptyRow.id = 'students-empty-row';
                                    emptyRow.innerHTML = \`
                                        <td colspan="9" style="text-align: center; padding: 36px 16px;">
                                            <p id="empty-title">No students match the selected filters.</p>
                                            <button type="button" id="clear-btn" onclick="clearStudentDirectoryFilters()">Clear filters</button>
                                        </td>
                                    \`;
                                    document.getElementById('students-tbody')?.appendChild(emptyRow);
                                }
                                emptyRow.style.display = '';
                                if (bar) bar.style.display = 'none';
                                return;
                            } else if (emptyRow) {
                                emptyRow.style.display = 'none';
                            }
                            if (bar) bar.style.display = 'flex';
                            visible.forEach(r => r.style.display = '');
                        }
                    </script>
                </body>
                </html>
            `);

            // Initial state: row visible, pagination bar visible
            await expect(page.locator('#students-tbody tr:not(#students-empty-row)')).toBeVisible();
            await expect(page.locator('#pagination-bar')).toBeVisible();

            // Trigger search with no matches
            await page.fill('#student-search', 'nonexistent student xyz');
            await page.evaluate(() => renderPage());

            // Empty state row should appear with colspan 9, pagination bar should be hidden
            const emptyRow = page.locator('#students-empty-row');
            await expect(emptyRow).toBeVisible();
            await expect(emptyRow.locator('td')).toHaveAttribute('colspan', '9');
            await expect(page.locator('#empty-title')).toHaveText('No students match the selected filters.');
            await expect(page.locator('#pagination-bar')).toBeHidden();

            // Click clear filters button
            await page.click('#clear-btn');

            // Data row should be visible again, empty row hidden, pagination bar visible
            await expect(page.locator('#students-empty-row')).toBeHidden();
            await expect(page.locator('#students-tbody tr:not(#students-empty-row)')).toBeVisible();
            await expect(page.locator('#pagination-bar')).toBeVisible();
            await expect(page.locator('#student-search')).toHaveValue('');
        });
    });

    test.describe('Part 2: Dashboard Table Scroll and Empty State Integrity', () => {

        test('admin/index.php preserves table-card structure with margin-bottom: 0', () => {
            // Must retain card and scroll-target classes
            expect(adminIndexPhp).toContain('class="card scroll-target" id="table-card"');
            expect(adminIndexPhp).toMatch(/<div class="card scroll-target" id="table-card"\s+style="margin-bottom:\s*0;"/);
        });

        test('admin/index.php uses colspan="8" for empty row inside tbody', () => {
            const emptyColspanMatches = adminIndexPhp.match(/colspan=["']8["']/g) || [];
            expect(emptyColspanMatches.length).toBeGreaterThanOrEqual(2);
        });

        test('admin/index.php contains clearDashboardFilters and handles emptyRow in sortTable', () => {
            expect(adminIndexPhp).toContain('function clearDashboardFilters()');
            expect(adminIndexPhp).toContain('tbody.appendChild(emptyRow)');
        });

        test('Dashboard DOM: Table scroll container does not produce vertical scroll overflow', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        .card { background: white; border: 1px solid #e2e8f0; border-radius: 8px; }
                        .data-table-scroll {
                            overflow-x: auto;
                            width: 100%;
                            -webkit-overflow-scrolling: touch;
                        }
                        table { width: 100%; min-width: 900px; border-collapse: collapse; }
                        th, td { padding: 12px 16px; border-bottom: 1px solid #e2e8f0; }
                    </style>
                </head>
                <body>
                    <div class="card scroll-target" id="table-card" style="margin-bottom: 0;">
                        <div class="data-table-scroll" role="region" aria-label="Student Cohort Roster" tabindex="0">
                            <table id="admin-table">
                                <thead>
                                    <tr>
                                        <th>Student No.</th>
                                        <th>Name</th>
                                        <th>Section</th>
                                        <th>Year Level</th>
                                        <th>Status</th>
                                        <th>Predicted GWA</th>
                                        <th>Risk Category</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody id="admin-tbody">
                                    <tr>
                                        <td>2023-0001</td>
                                        <td>Santos, Juan</td>
                                        <td>BSIT 3-A</td>
                                        <td>3rd Year</td>
                                        <td>Enrolled</td>
                                        <td>1.85</td>
                                        <td>LOW</td>
                                        <td><a href="#">Profile</a></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </body>
                </html>
            `);

            // Verify .data-table-scroll geometry: horizontal scroll container only, no vertical scrollbar
            const metrics = await page.evaluate(() => {
                const scrollEl = document.querySelector('.data-table-scroll');
                const card = document.getElementById('table-card');
                return {
                    scrollOverflowY: getComputedStyle(scrollEl).overflowY,
                    scrollClientHeight: scrollEl.clientHeight,
                    scrollScrollHeight: scrollEl.scrollHeight,
                    cardMarginBottom: getComputedStyle(card).marginBottom,
                };
            });

            // .data-table-scroll must not produce internal vertical scrolling
            expect(metrics.scrollClientHeight).toBe(metrics.scrollScrollHeight);
            expect(metrics.scrollOverflowY).not.toBe('scroll');
            // card margin bottom must be 0px
            expect(metrics.cardMarginBottom).toBe('0px');
        });

        test('Dashboard DOM: Search empty state displays colspan="8" and sortTable reorders safely', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><title>Dashboard Sort and Filter Test</title></head>
                <body>
                    <input type="text" id="dashboard-search" value="">
                    <select id="filter-risk"><option value="">All</option><option value="HIGH">High</option></select>
                    <select id="filter-status"><option value="">All</option><option value="Enrolled">Enrolled</option></select>

                    <div class="card scroll-target" id="table-card" style="margin-bottom: 0;">
                        <table id="admin-table">
                            <thead>
                                <tr>
                                    <th onclick="sortTable(0)">Student No.</th>
                                    <th onclick="sortTable(1)">Name</th>
                                    <th>Section</th>
                                    <th>Year Level</th>
                                    <th>Status</th>
                                    <th>Predicted GWA</th>
                                    <th>Risk Category</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="admin-tbody">
                                <tr data-search="2023-0002 bautista ana" data-risk="LOW" data-status="Enrolled">
                                    <td>2023-0002</td>
                                    <td>Bautista, Ana</td>
                                    <td>BSIT 3-A</td>
                                    <td>3rd Year</td>
                                    <td>Enrolled</td>
                                    <td>1.50</td>
                                    <td>LOW</td>
                                    <td><a href="#">Profile</a></td>
                                </tr>
                                <tr data-search="2023-0001 santos juan" data-risk="HIGH" data-status="Enrolled">
                                    <td>2023-0001</td>
                                    <td>Santos, Juan</td>
                                    <td>BSIT 3-A</td>
                                    <td>3rd Year</td>
                                    <td>Enrolled</td>
                                    <td>2.80</td>
                                    <td>HIGH</td>
                                    <td><a href="#">Profile</a></td>
                                </tr>
                            </tbody>
                        </table>
                        <div id="pagination-bar" style="display:flex;">Page 1</div>
                    </div>

                    <script>
                        function clearDashboardFilters() {
                            const search = document.getElementById('dashboard-search');
                            const risk = document.getElementById('filter-risk');
                            const status = document.getElementById('filter-status');
                            if (search) search.value = '';
                            if (risk) risk.value = '';
                            if (status) status.value = '';
                            currentPage = 1;
                            renderPage();
                        }

                        const ROWS_PER_PAGE = 25;
                        let currentPage = 1;

                        function getVisibleRows() {
                            const term = (document.getElementById('dashboard-search')?.value || '').toLowerCase().trim();
                            const riskFilter = document.getElementById('filter-risk')?.value || '';
                            const statusFilter = document.getElementById('filter-status')?.value || '';

                            const allRows = Array.from(document.querySelectorAll('#admin-tbody tr:not(#admin-table-empty-row)'));

                            return allRows.filter(row => {
                                const matchSearch = !term || (row.dataset.search && row.dataset.search.includes(term));
                                let matchRisk = true;
                                if (riskFilter === 'AT_RISK') {
                                    matchRisk = row.dataset.risk === 'HIGH' || row.dataset.risk === 'MODERATE';
                                } else if (riskFilter) {
                                    matchRisk = row.dataset.risk === riskFilter;
                                }
                                const matchStatus = !statusFilter || row.dataset.status === statusFilter;
                                return matchSearch && matchRisk && matchStatus;
                            });
                        }

                        function renderPage() {
                            const allRows = Array.from(document.querySelectorAll('#admin-tbody tr:not(#admin-table-empty-row)'));
                            const visible = getVisibleRows();
                            allRows.forEach(r => r.style.display = 'none');

                            const bar = document.getElementById('pagination-bar');
                            let emptyRow = document.getElementById('admin-table-empty-row');

                            if (visible.length === 0) {
                                if (!emptyRow) {
                                    emptyRow = document.createElement('tr');
                                    emptyRow.id = 'admin-table-empty-row';
                                    emptyRow.innerHTML = \`
                                        <td colspan="8" style="text-align: center; padding: 36px 16px;">
                                            <p id="empty-dash-msg">No students match the selected filters.</p>
                                            <button type="button" id="clear-dash-btn" onclick="clearDashboardFilters()">Clear filters</button>
                                        </td>
                                    \`;
                                    document.getElementById('admin-tbody')?.appendChild(emptyRow);
                                }
                                emptyRow.style.display = '';
                                if (bar) bar.style.display = 'none';
                                return;
                            } else if (emptyRow) {
                                emptyRow.style.display = 'none';
                            }
                            if (bar) bar.style.display = 'flex';
                            visible.forEach(r => r.style.display = '');
                        }

                        let currentSortCol = -1;
                        let currentSortDir = 'asc';
                        function sortTable(colIndex) {
                            const tbody = document.getElementById('admin-tbody');
                            if (!tbody) return;
                            const rows = Array.from(tbody.querySelectorAll('tr:not(#admin-table-empty-row)'));
                            if (!rows.length) return;

                            if (currentSortCol === colIndex) {
                                currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                            } else {
                                currentSortCol = colIndex;
                                currentSortDir = 'asc';
                            }

                            rows.sort((a, b) => {
                                const valA = (a.cells[colIndex]?.innerText || '').trim().toLowerCase();
                                const valB = (b.cells[colIndex]?.innerText || '').trim().toLowerCase();
                                return currentSortDir === 'asc' ? valA.localeCompare(valB) : valB.localeCompare(valA);
                            });

                            rows.forEach(row => tbody.appendChild(row));
                            const emptyRow = document.getElementById('admin-table-empty-row');
                            if (emptyRow) tbody.appendChild(emptyRow);
                            currentPage = 1;
                            renderPage();
                        }
                    </script>
                </body>
                </html>
            `);

            // Sort column 0 ascending
            await page.evaluate(() => sortTable(0));
            let firstRowStudent = await page.locator('#admin-tbody tr:not(#admin-table-empty-row)').first().locator('td').first().innerText();
            expect(firstRowStudent).toBe('2023-0001');

            // Sort column 0 descending
            await page.evaluate(() => sortTable(0));
            firstRowStudent = await page.locator('#admin-tbody tr:not(#admin-table-empty-row)').first().locator('td').first().innerText();
            expect(firstRowStudent).toBe('2023-0002');

            // Trigger search with no matches
            await page.fill('#dashboard-search', 'no matches at all');
            await page.evaluate(() => renderPage());

            const emptyRow = page.locator('#admin-table-empty-row');
            await expect(emptyRow).toBeVisible();
            await expect(emptyRow.locator('td')).toHaveAttribute('colspan', '8');
            await expect(page.locator('#empty-dash-msg')).toHaveText('No students match the selected filters.');
            await expect(page.locator('#pagination-bar')).toBeHidden();

            // Clear filters button restores rows
            await page.click('#clear-dash-btn');
            await expect(page.locator('#admin-table-empty-row')).toBeHidden();
            await expect(page.locator('#pagination-bar')).toBeVisible();
            expect(await page.locator('#admin-tbody tr:not(#admin-table-empty-row)').count()).toBe(2);
        });
    });
});
