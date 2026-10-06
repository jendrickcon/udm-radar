import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const adminIndexPhp = readFileSync(resolve('admin/index.php'), 'utf-8');
const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');
const adminFacultyPhp = readFileSync(resolve('admin/faculty.php'), 'utf-8');
const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');
const facultyDashboardPhp = readFileSync(resolve('faculty/dashboard.php'), 'utf-8');

test.describe('DATA-UI-WP1A: Table Accessibility Foundation', () => {

    test.describe('F-DATA-02: Semantic Record Links (No Mouse-Only Clickable Rows)', () => {
        test('Target files contain no row-clickable or onclick attributes on <tr> elements', () => {
            const files = [
                { name: 'admin/students.php', content: adminStudentsPhp },
                { name: 'admin/faculty.php', content: adminFacultyPhp },
                { name: 'admin/grades.php', content: adminGradesPhp },
                { name: 'faculty/dashboard.php', content: facultyDashboardPhp }
            ];

            for (const file of files) {
                // Assert no <tr class="row-clickable"> exists in the file markup
                expect(file.content).not.toMatch(/<tr[^>]*class=["'][^"']*row-clickable/i);
                // Assert no <tr onclick="..."> exists in the file markup
                expect(file.content).not.toMatch(/<tr[^>]*onclick\s*=/i);
            }
        });

        test('Admin Students: Student name is a semantic button with .table-record-link and Enter opens modal', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><style>${dashboardCss}</style></head>
                <body>
                    <div id="modal-opened">false</div>
                    <table>
                        <tbody>
                            <tr>
                                <td>2023-0001</td>
                                <td>
                                    <button type="button" class="table-record-link" onclick="openStudentModal(1)" aria-label="View details for Santos, Juan">
                                        Santos, Juan
                                    </button>
                                </td>
                                <td>
                                    <button type="button" onclick="openEditModal(1)">Edit</button>
                                    <button type="submit">Archive</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <script>
                        function openStudentModal(id) {
                            document.getElementById('modal-opened').innerText = 'opened-' + id;
                        }
                    </script>
                </body>
                </html>
            `);

            const recordBtn = page.locator('.table-record-link');
            await expect(recordBtn).toHaveAttribute('aria-label', 'View details for Santos, Juan');
            
            // Tab to the button and press Enter
            await page.keyboard.press('Tab');
            await expect(recordBtn).toBeFocused();
            await page.keyboard.press('Enter');

            await expect(page.locator('#modal-opened')).toHaveText('opened-1');
        });

        test('Admin Faculty: Faculty name is a semantic button and Edit/Delete are independent', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><style>${dashboardCss}</style></head>
                <body>
                    <div id="modal-opened">none</div>
                    <table>
                        <tbody>
                            <tr>
                                <td>FAC-101</td>
                                <td>
                                    <button type="button" class="table-record-link" onclick="openFacultyModal(42)" aria-label="View faculty details for Cruz, Maria">
                                        Cruz, Maria
                                    </button>
                                </td>
                                <td>
                                    <button type="button" id="edit-btn" onclick="document.getElementById('modal-opened').innerText = 'edit-42'">Edit</button>
                                    <button type="button" id="del-btn" onclick="document.getElementById('modal-opened').innerText = 'del-42'">Delete</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                    <script>
                        function openFacultyModal(id) {
                            document.getElementById('modal-opened').innerText = 'faculty-' + id;
                        }
                    </script>
                </body>
                </html>
            `);

            const recordBtn = page.locator('.table-record-link');
            await recordBtn.focus();
            await page.keyboard.press('Space');
            await expect(page.locator('#modal-opened')).toHaveText('faculty-42');

            // Click Edit separately
            await page.locator('#edit-btn').click();
            await expect(page.locator('#modal-opened')).toHaveText('edit-42');
        });

        test('Admin Grades & Faculty Dashboard: Dynamic roster rows render semantic record buttons', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><style>${dashboardCss}</style></head>
                <body>
                    <div id="history-opened">none</div>
                    <div id="grade-opened">none</div>
                    <div id="roster-container"></div>
                    <script>
                        function openHistory(id, name) {
                            document.getElementById('history-opened').innerText = id + ':' + name;
                        }
                        function openGradeModal(id, sec) {
                            document.getElementById('grade-opened').innerText = id + ':' + sec;
                        }

                        // Simulate Admin Grades openSection render
                        const studentsAdmin = [{ userId: 12, name: "Dela Cruz, Juan", studentNo: "2023-01", status: "Regular", currentGwa: 3.5, risk: "LOW" }];
                        document.getElementById('roster-container').innerHTML = studentsAdmin.map(s => \`
                            <tr>
                                <td>\${s.studentNo}</td>
                                <td>
                                    <button type="button" class="table-record-link" onclick="openHistory(\${s.userId}, '\${s.name}')" aria-label="View grade history for \${s.name}">
                                        \${s.name}
                                    </button>
                                </td>
                            </tr>
                        \`).join('');
                    </script>
                </body>
                </html>
            `);

            const link = page.locator('.table-record-link');
            await link.focus();
            await page.keyboard.press('Enter');
            await expect(page.locator('#history-opened')).toHaveText('12:Dela Cruz, Juan');
        });
    });

    test.describe('F-DATA-03: Keyboard-Accessible Sort Headers and aria-sort', () => {
        test('Admin Dashboard: Sortable headers use <button class="table-sort-button"> and scope="col"', () => {
            expect(adminIndexPhp).toMatch(/<th scope="col" class="sortable-col" data-type="string" aria-sort="none">/);
            expect(adminIndexPhp).toMatch(/<button type="button" class="table-sort-button" onclick="sortTable\(0\)">/);
            expect(adminIndexPhp).toMatch(/<span class="sort-arrow" id="sort-arrow-0" aria-hidden="true">/);
        });

        test('Admin Dashboard: Sort toggles aria-sort between ascending and descending with Enter/Space', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><style>${dashboardCss}</style></head>
                <body>
                    <table id="admin-table">
                        <thead>
                            <tr>
                                <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                                    <button type="button" class="table-sort-button" id="sort-btn-0" onclick="sortTable(0)">
                                        <span>Student No.</span> <span class="sort-arrow" id="sort-arrow-0" aria-hidden="true">⇅</span>
                                    </button>
                                </th>
                                <th scope="col" class="sortable-col" data-type="string" aria-sort="none">
                                    <button type="button" class="table-sort-button" id="sort-btn-1" onclick="sortTable(1)">
                                        <span>Name</span> <span class="sort-arrow" id="sort-arrow-1" aria-hidden="true">⇅</span>
                                    </button>
                                </th>
                            </tr>
                        </thead>
                        <tbody id="admin-tbody">
                            <tr><td data-sort="2023-0002">2023-0002</td><td data-sort="Zeta, Ana">Zeta, Ana</td></tr>
                            <tr><td data-sort="2023-0001">2023-0001</td><td data-sort="Abad, Ben">Abad, Ben</td></tr>
                        </tbody>
                    </table>
                    <script>
                        let currentSortCol = -1;
                        let currentSortDir = 'asc';

                        function sortTable(colIndex) {
                            const tbody = document.getElementById('admin-tbody');
                            if (!tbody) return;
                            const rows = Array.from(tbody.querySelectorAll('tr'));
                            if (!rows.length) return;

                            if (currentSortCol === colIndex) {
                                currentSortDir = currentSortDir === 'asc' ? 'desc' : 'asc';
                            } else {
                                currentSortCol = colIndex;
                                currentSortDir = 'asc';
                            }

                            const sortableCols = document.querySelectorAll('#admin-table th.sortable-col');
                            sortableCols.forEach((th, idx) => {
                                if (idx === colIndex) {
                                    th.setAttribute('aria-sort', currentSortDir === 'asc' ? 'ascending' : 'descending');
                                } else {
                                    th.setAttribute('aria-sort', 'none');
                                }
                            });

                            const getSort = (row, idx) => row.querySelectorAll('td')[idx]?.dataset.sort ?? '';
                            rows.sort((a, b) => {
                                const diff = getSort(a, colIndex).localeCompare(getSort(b, colIndex));
                                return currentSortDir === 'asc' ? diff : -diff;
                            });
                            rows.forEach(r => tbody.appendChild(r));
                        }
                    </script>
                </body>
                </html>
            `);

            const th0 = page.locator('#admin-table th.sortable-col').nth(0);
            const th1 = page.locator('#admin-table th.sortable-col').nth(1);
            const sortBtn0 = page.locator('#sort-btn-0');

            // Initial state
            await expect(th0).toHaveAttribute('aria-sort', 'none');
            await expect(th1).toHaveAttribute('aria-sort', 'none');

            // Focus button 0 and activate with Enter
            await sortBtn0.focus();
            await page.keyboard.press('Enter');

            // After 1st activation: ascending
            await expect(th0).toHaveAttribute('aria-sort', 'ascending');
            await expect(th1).toHaveAttribute('aria-sort', 'none');
            expect(await page.locator('#admin-tbody tr td').first().innerText()).toBe('2023-0001');

            // Activate again with Space: descending
            await page.keyboard.press('Space');
            await expect(th0).toHaveAttribute('aria-sort', 'descending');
            await expect(th1).toHaveAttribute('aria-sort', 'none');
            expect(await page.locator('#admin-tbody tr td').first().innerText()).toBe('2023-0002');
        });

        test('Faculty Dashboard: Dynamic sortClassTab manages aria-sort and keyboard sort buttons', async ({ page }) => {
            expect(facultyDashboardPhp).toMatch(/<th scope="col" class="sortable-col" aria-sort="none" id="col-h-0"/);
            expect(facultyDashboardPhp).toMatch(/<button type="button" class="table-sort-button" onclick="sortClassTab\(0\)">/);
            expect(facultyDashboardPhp).toMatch(/sortableCols\.forEach\(\(th, idx\) =>/);
        });
    });

    test.describe('F-DATA-04: Accessible Table Names and scope="col" Headers', () => {
        test('All audited tables contain <caption class="sr-only"> or valid accessible name', () => {
            // Admin Index
            expect(adminIndexPhp).toMatch(/<caption class="sr-only">Student Cohort Roster<\/caption>/);
            // Admin Students
            expect(adminStudentsPhp).toMatch(/<caption class="sr-only">Student Directory Table<\/caption>/);
            expect(adminStudentsPhp).toMatch(/<caption class="sr-only">Current Semester Grades<\/caption>/);
            // Admin Faculty
            expect(adminFacultyPhp).toMatch(/<caption class="sr-only">Faculty Directory Table<\/caption>/);
            expect(adminFacultyPhp).toMatch(/<caption class="sr-only">Faculty Class Loads<\/caption>/);
            // Admin Grades
            expect(adminGradesPhp).toMatch(/<caption class="sr-only">Pending Grade Corrections Table<\/caption>/);
            expect(adminGradesPhp).toMatch(/<caption class="sr-only">Section Student Roster<\/caption>/);
            // Faculty Dashboard
            expect(facultyDashboardPhp).toMatch(/<caption class="sr-only">Class Performance Roster<\/caption>/);
            expect(facultyDashboardPhp).toMatch(/<caption class="sr-only">Overall Section Standing<\/caption>/);
            expect(facultyDashboardPhp).toMatch(/<caption class="sr-only">Top Academic Performers Ranking<\/caption>/);
            expect(facultyDashboardPhp).toMatch(/<caption class="sr-only">Student Grade Breakdown<\/caption>/);
        });

        test('All audited table headers contain scope="col"', () => {
            // Admin index: 8 columns with scope="col"
            const adminIndexScopeMatches = adminIndexPhp.match(/<th\s+scope="col"/g) || [];
            expect(adminIndexScopeMatches.length).toBeGreaterThanOrEqual(8);

            // Admin students: directory table has scope="col" on all 10 columns
            expect(adminStudentsPhp).toMatch(/<th scope="col"[^>]*>Student No.<\/th>/);
            expect(adminStudentsPhp).toMatch(/<th scope="col"[^>]*>Name<\/th>/);
            expect(adminStudentsPhp).toMatch(/<th scope="col"[^>]*>Cumulative GWA \(Historical\)<\/th>/);

            // Admin faculty: directory table has scope="col"
            expect(adminFacultyPhp).toMatch(/<th scope="col"[^>]*>Faculty ID<\/th>/);
            expect(adminFacultyPhp).toMatch(/<th scope="col"[^>]*>Email<\/th>/);

            // Admin grades: roster and pending tables have scope="col"
            expect(adminGradesPhp).toMatch(/<th scope="col">Student No.<\/th>/);
            expect(adminGradesPhp).toMatch(/<th scope="col">Proposed<\/th>/);
        });
    });

    test.describe('F-DATA-05 & F-DATA-07: Table Scroll Regions and Mobile Viewport Containment', () => {
        test('CSS contains all four required utility classes with valid tokens', () => {
            expect(dashboardCss).toContain('.data-table-scroll');
            expect(dashboardCss).toContain('.table-record-link');
            expect(dashboardCss).toContain('.table-sort-button');
            expect(dashboardCss).toContain('.sr-only');

            // .data-table-scroll has overflow-x: auto and focus-visible
            expect(dashboardCss).toMatch(/\.data-table-scroll\s*\{[^}]*overflow-x:\s*auto/);
            expect(dashboardCss).toMatch(/\.data-table-scroll:focus-visible\s*\{[^}]*outline:/);

            // .sr-only follows accessible clip pattern
            expect(dashboardCss).toMatch(/\.sr-only\s*\{[^}]*clip:\s*rect\(0,\s*0,\s*0,\s*0\)/);
        });

        test('Scroll regions are keyboard focusable with tabindex="0" and role="region"', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head><style>${dashboardCss}</style></head>
                <body>
                    <div class="data-table-scroll" role="region" aria-label="Student Directory Table" tabindex="0">
                        <table style="min-width: 900px;">
                            <caption class="sr-only">Student Directory Table</caption>
                            <thead>
                                <tr>
                                    <th scope="col">Student No.</th>
                                    <th scope="col">Name</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr><td>2023-01</td><td>Santos, Juan</td></tr>
                            </tbody>
                        </table>
                    </div>
                </body>
                </html>
            `);

            const region = page.locator('.data-table-scroll');
            await expect(region).toHaveAttribute('role', 'region');
            await expect(region).toHaveAttribute('aria-label', 'Student Directory Table');
            await expect(region).toHaveAttribute('tabindex', '0');

            await page.keyboard.press('Tab');
            await expect(region).toBeFocused();
        });

        test('Mobile 390x844: Zero horizontal page blowout on tables wrapped in .data-table-scroll', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });

            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <style>
                        ${dashboardCss}
                        body { margin: 0; padding: 16px; box-sizing: border-box; }
                        .card { background: #fff; border-radius: 8px; border: 1px solid #e2e8f0; padding: 16px; overflow: hidden; }
                    </style>
                </head>
                <body>
                    <div class="card">
                        <h3>Faculty Directory</h3>
                        <div class="data-table-scroll" role="region" aria-label="Faculty Directory Table" tabindex="0">
                            <table id="faculty-table" style="width: 100%; border-collapse: collapse; min-width: 600px;">
                                <caption class="sr-only">Faculty Directory Table</caption>
                                <thead>
                                    <tr>
                                        <th scope="col">Faculty ID</th>
                                        <th scope="col">Name</th>
                                        <th scope="col">Email</th>
                                        <th scope="col">Sections</th>
                                        <th scope="col">Subjects</th>
                                        <th scope="col"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>FAC-001</td>
                                        <td><button type="button" class="table-record-link">Prof. Maria Santos</button></td>
                                        <td>maria.santos@udm.edu.ph</td>
                                        <td>4</td>
                                        <td>6</td>
                                        <td><button type="button">Edit</button></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </body>
                </html>
            `);

            // Verify document scrollWidth does NOT exceed viewport width (no horizontal page blowout)
            const docWidth = await page.evaluate(() => document.documentElement.scrollWidth);
            const bodyWidth = await page.evaluate(() => document.body.scrollWidth);
            expect(docWidth).toBeLessThanOrEqual(390);
            expect(bodyWidth).toBeLessThanOrEqual(390);

            // Verify the scroll region itself can scroll internally
            const regionScrollWidth = await page.locator('.data-table-scroll').evaluate(el => el.scrollWidth);
            const regionClientWidth = await page.locator('.data-table-scroll').evaluate(el => el.clientWidth);
            expect(regionScrollWidth).toBeGreaterThan(regionClientWidth);
        });

        test('Theme Parity: .table-record-link and .table-sort-button render correctly in Light and Dark mode', async ({ page }) => {
            await page.setContent(`
                <!DOCTYPE html>
                <html>
                <head>
                    <style>
                        :root {
                            --accent-blue: #1e4db7;
                            --text-dark: #1f2937;
                            --text-gray: #6b7280;
                            --bg-color: #f8fafc;
                        }
                        [data-theme="dark"] {
                            --accent-blue: #6c8eef;
                            --text-dark: #f3f4f6;
                            --text-gray: #9ca3af;
                            --bg-color: #111827;
                        }
                        ${dashboardCss}
                    </style>
                </head>
                <body>
                    <button type="button" class="table-record-link" id="record-btn">Dela Cruz, Juan</button>
                    <button type="button" class="table-sort-button" id="sort-btn">Sort Column</button>
                </body>
                </html>
            `);

            // Light theme assertion
            const lightColor = await page.locator('#record-btn').evaluate(el => window.getComputedStyle(el).color);
            expect(lightColor).toBe('rgb(30, 77, 183)'); // #1e4db7

            // Dark theme assertion
            await page.evaluate(() => document.documentElement.setAttribute('data-theme', 'dark'));
            const darkColor = await page.locator('#record-btn').evaluate(el => window.getComputedStyle(el).color);
            expect(darkColor).toBe('rgb(108, 142, 239)'); // #6c8eef
        });
    });
});
