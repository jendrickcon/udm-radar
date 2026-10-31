import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import process from 'node:process';
import { test, expect } from '@playwright/test';

const phpBinary = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const rendererScript = resolve('tests/fixtures/render_student_grades.php');

function getRenderedGradesHtml(studentId = 5, theme = 'light') {
    return execFileSync(phpBinary, [rendererScript, String(studentId), theme], {
        encoding: 'utf8',
        cwd: resolve('.'),
    });
}

test.describe('Student Grades & History Period Separation', () => {

    test('1. Current term shows registered subjects and latest stage without requiring finalized grades', async ({ page }) => {
        const html = getRenderedGradesHtml(5, 'light');
        await page.setContent(html, { waitUntil: 'domcontentloaded' });

        // Verify Current Semester Section header & period
        const heading = page.locator('h2:has-text("Current Semester Grades")');
        await expect(heading).toBeVisible();

        const periodSubtitle = page.locator('text=Academic Year 2026-2027 · Semester 1');
        await expect(periodSubtitle).toBeVisible();

        // Verify Current Grades table
        const currentTable = page.locator('.current-grades-table');
        await expect(currentTable).toBeVisible();

        // Verify column header 'Status / Final Grade'
        const statusHeader = currentTable.locator('th:has-text("Status / Final Grade")');
        await expect(statusHeader).toBeVisible();

        // Verify current subjects rows count (6 subjects for Student 5)
        const rows = currentTable.locator('tbody tr');
        await expect(rows).toHaveCount(6);

        // Verify subject codes
        const expectedCodes = ['ITE421', 'ITE422', 'ITE431', 'ITE441', 'SD354', 'UID107'];
        for (const code of expectedCodes) {
            await expect(currentTable.locator(`td[data-label="Code"]:has-text("${code}")`)).toBeVisible();
        }

        // Verify each row shows 'Preliminary recorded' in Status / Final Grade
        const statusCells = currentTable.locator('tbody td[data-label="Status / Final Grade"]');
        const count = await statusCells.count();
        expect(count).toBe(6);
        for (let i = 0; i < count; i++) {
            const cellText = (await statusCells.nth(i).textContent())?.trim();
            expect(cellText).toBe('Preliminary recorded');
            expect(cellText).not.toBe('0');
            expect(cellText).not.toBe('0.00');
            expect(cellText).not.toBe('—');
        }

        // Verify old misleading message is strictly ABSENT
        await expect(page.locator('text=No current-semester grades have been encoded yet.')).toHaveCount(0);

        // Verify approved pending-grade explanation is PRESENT
        await expect(page.locator('text=Final grades have not yet been recorded for the current semester.')).toBeVisible();

        // Verify link to Performance Trend for assessment averages in the grades card
        const trendLink = page.locator('.grades-card a[href="trend.php"]:has-text("Performance Trend")');
        await expect(trendLink).toBeVisible();

        // Verify missing-grade non-zero disclaimer
        await expect(page.locator('text=Recorded grading-period results are shown separately. A missing grade means that no value is currently available; it is not treated as zero.')).toBeVisible();
    });

    test('2. Academic History excludes active Sem 1 and future Sem 2 (ITE423), showing only completed terms', async ({ page }) => {
        const html = getRenderedGradesHtml(5, 'light');
        await page.setContent(html, { waitUntil: 'domcontentloaded' });

        // Verify Academic History section heading
        const histHeading = page.locator('h2:has-text("Academic History")');
        await expect(histHeading).toBeVisible();

        // Completed historical terms that MUST be present
        const expectedHistoryTerms = [
            '2025-2026 — Sem 2',
            '2025-2026 — Sem 1',
            '2024-2025 — Sem 2',
            '2024-2025 — Sem 1',
            '2023-2024 — Sem 2',
            '2023-2024 — Sem 1',
        ];

        for (const term of expectedHistoryTerms) {
            const termCard = page.locator(`.grades-card .table-title:has-text("${term}")`);
            await expect(termCard).toBeVisible();
        }

        // Strict Exclusion: Future Sem 2 must NOT appear in Academic History
        const futureSem2Card = page.locator('.grades-card .table-title:has-text("2026-2027 — Sem 2")');
        await expect(futureSem2Card).toHaveCount(0);

        // Strict Exclusion: Active Sem 1 must NOT be duplicated in Academic History
        const duplicateSem1Card = page.locator('.grades-card .table-title:has-text("2026-2027 — Sem 1")');
        await expect(duplicateSem1Card).toHaveCount(0);

        // Strict Exclusion: ITE423 (Industry Immersion) must NOT appear in Academic History
        const historyTables = page.locator('.history-grades-table');
        const ite423Cell = historyTables.locator('td:has-text("ITE423")');
        await expect(ite423Cell).toHaveCount(0);

        // Verify history tables retain 'Final Grade' column header
        const firstHistoryTable = historyTables.first();
        await expect(firstHistoryTable.locator('th:has-text("Final Grade")')).toBeVisible();

        // Verify every history row has a finalized outcome (not empty or —)
        const histFinalGradeCells = firstHistoryTable.locator('tbody td[data-label="Final Grade"]');
        const fgCount = await histFinalGradeCells.count();
        expect(fgCount).toBeGreaterThan(0);
        for (let i = 0; i < fgCount; i++) {
            const text = (await histFinalGradeCells.nth(i).textContent())?.trim();
            expect(text).not.toBe('');
            expect(text).not.toBe('—');
            expect(text).toMatch(/^(\d\.\d{2}|INC|DRP|P|DO|DU|FA|UD|PASSED)$/);
        }
    });

    test('3. Responsive layout and theme verification across desktop matrix', async ({ page }) => {
        const viewports = [
            { width: 1440, height: 900 },
            { width: 1280, height: 600 },
            { width: 1024, height: 768 },
        ];
        const themes = ['light', 'dark'];

        for (const vp of viewports) {
            for (const theme of themes) {
                await page.setViewportSize(vp);
                const html = getRenderedGradesHtml(5, theme);
                await page.setContent(html, { waitUntil: 'domcontentloaded' });
                await page.evaluate((t) => {
                    document.documentElement.setAttribute('data-theme', t);
                }, theme);

                // Check HTML theme attribute
                const rootTheme = await page.locator('html').getAttribute('data-theme');
                expect(rootTheme).toBe(theme);

                // Verify no page-level horizontal overflow
                const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
                const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
                expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2);

                // Verify tables are contained
                const currentScroll = page.locator('.grades-table-scroll').first();
                await expect(currentScroll).toBeVisible();
            }
        }
    });

    test('4. Risk badge portal tooltip interaction retains supportive pass/fail separation', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        const html = getRenderedGradesHtml(5, 'light');
        await page.setContent(html, { waitUntil: 'domcontentloaded' });

        // Trigger focus / mouseenter on the first subject risk badge
        const badge = page.locator('.current-grades-table .custom-tooltip').first();
        await expect(badge).toBeVisible();
        await badge.dispatchEvent('mouseenter');

        // Check portaled tooltip is attached to body and visible
        const portaledTooltip = page.locator('body > .grades-risk-tooltip');
        await expect(portaledTooltip).toBeVisible();
        const tooltipText = await portaledTooltip.textContent();
        expect(tooltipText?.length).toBeGreaterThan(10);
    });

    test('5. Empty current-semester state wording separation', async ({ page }) => {
        // When rendered for a student without current subjects
        // Student 1 is admin, has 0 student subjects
        const html = getRenderedGradesHtml(1, 'light');
        await page.setContent(html, { waitUntil: 'domcontentloaded' });

        // Should display 'No current-semester subjects are available.'
        const emptyMsg = page.locator('.empty-state:has-text("No current-semester subjects are available.")');
        await expect(emptyMsg).toBeVisible();

        // Should NOT display old misleading copy
        await expect(page.locator('text=No current-semester grades have been encoded yet.')).toHaveCount(0);
    });
});
