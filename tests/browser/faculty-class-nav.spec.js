import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';

/**
 * FACULTY-NAV-WP1: Assigned-Class Deep Linking
 * Validates faculty home assigned-class links, grade submission preselection,
 * server-side authorization contracts, keyboard focus, and browser history.
 */

function runFixture(config) {
    const jsonStr = JSON.stringify(config);
    return execFileSync(process.env.PHP_BINARY || 'php', [
        resolve('tests/fixtures/faculty_class_nav_fixture.php'),
        jsonStr
    ], { encoding: 'utf8' });
}

async function setupFacultyNavRoute(page, userId = 2) {
    await page.route('**/*', async (routeHandler) => {
        const req = routeHandler.request();
        const url = new URL(req.url());

        if (url.hostname === 'faculty-nav.test') {
            if (url.searchParams.get('action') === 'fetch_roster') {
                const classVal = url.searchParams.get('class') || '';
                const termVal = url.searchParams.get('term') || 'prelim';
                const jsonOut = runFixture({
                    page: 'grades.php',
                    user_id: userId,
                    get: { action: 'fetch_roster', class: classVal, term: termVal }
                });
                return routeHandler.fulfill({ contentType: 'application/json', body: jsonOut });
            }

            if (url.pathname.endsWith('/index.php')) {
                const indexHtml = runFixture({ page: 'index.php', user_id: userId });
                return routeHandler.fulfill({ contentType: 'text/html', body: indexHtml });
            }

            if (url.pathname.endsWith('/grades.php')) {
                const getParams = {};
                for (const [k, v] of url.searchParams.entries()) {
                    getParams[k] = v;
                }
                const gradesHtml = runFixture({
                    page: 'grades.php',
                    user_id: userId,
                    get: getParams
                });
                return routeHandler.fulfill({ contentType: 'text/html', body: gradesHtml });
            }
        }

        if (url.pathname.endsWith('.css')) {
            return routeHandler.fulfill({ contentType: 'text/css', body: 'body { font-family: sans-serif; }' });
        }

        if (url.pathname.endsWith('.png') || url.pathname.endsWith('.svg') || url.pathname.endsWith('.ico')) {
            return routeHandler.fulfill({ contentType: 'image/png', body: '' });
        }

        return routeHandler.abort();
    });
}

test.describe('FACULTY-NAV-WP1: Assigned-Class Deep Linking', () => {

    test.describe('1. Faculty Home & General Entry', () => {
        test('Faculty Home renders assigned class cards with validated deep links and Submit Term Grades unselected link', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/index.php');

            // Quick actions Submit Term Grades links to grades.php without class parameter
            const submitTermGradesBtn = page.locator('a[href="grades.php"]:has-text("Submit Term Grades")');
            await expect(submitTermGradesBtn.first()).toBeVisible();
            await expect(submitTermGradesBtn.first()).toHaveAttribute('href', 'grades.php');

            // Assigned class preview cards have class deep link parameter
            const classCards = page.locator('a[href^="grades.php?class="]');
            const count = await classCards.count();
            expect(count).toBeGreaterThan(0);

            // Verify specific class cards exist with subject title and section
            const firstCard = classCards.first();
            const href = await firstCard.getAttribute('href');
            expect(href).toMatch(/^grades\.php\?class=\d+_[A-Za-z0-9\-]+/);
            await expect(firstCard).toHaveAttribute('aria-label', /Open grade submission for/);
        });

        test('Submit Term Grades opens Grade Submission with safe unselected state', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php');

            // Class dropdown has no class preselected
            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('');

            // Prompt message displays safe unselected guidance
            const msg = page.locator('#message-container');
            await expect(msg).toContainText('Please select a class to submit term grades');

            // Roster table remains hidden
            await expect(page.locator('#roster-card')).toBeHidden();

            // No routing error alert
            await expect(page.locator('#routing-error-alert')).toHaveCount(0);
        });
    });

    test.describe('2. Valid Class Deep Link Entry', () => {
        test('Clicking Assigned Class card preselects dropdown, loads roster, and focuses workspace', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/index.php');

            // Click the first assigned class card
            const firstCard = page.locator('a[href^="grades.php?class="]').first();
            const cardHref = await firstCard.getAttribute('href');
            const expectedClass = new URL('https://faculty-nav.test/faculty/' + cardHref).searchParams.get('class');

            await firstCard.click();
            await page.waitForURL(`https://faculty-nav.test/faculty/${cardHref}`);

            // Preselected class in dropdown matches clicked class
            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue(expectedClass);

            // Roster card appears with active student records
            const rosterCard = page.locator('#roster-card');
            await expect(rosterCard).toBeVisible();

            const rows = page.locator('#roster-tbody tr');
            await expect(rows.first()).toBeVisible();
            const rowCount = await rows.count();
            expect(rowCount).toBeGreaterThan(0);

            // Heading displays subject code and section
            await expect(page.locator('#roster-subj-code')).not.toBeEmpty();
            await expect(page.locator('#roster-section')).not.toBeEmpty();

            // Workspace heading receives focus
            const rosterHeading = page.locator('#roster-heading');
            await expect(rosterHeading).toBeFocused();
        });

        test('Direct URL with valid class parameter preselects class and loads roster', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=39_IT-31');

            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('39_IT-31');

            const rosterCard = page.locator('#roster-card');
            await expect(rosterCard).toBeVisible();
            await expect(page.locator('#roster-section')).toHaveText('IT-31');

            // Ensure no error alert appears
            await expect(page.locator('#routing-error-alert')).toHaveCount(0);
        });

        test('Alternative parameter contract (subject_id + section) safely resolves and preselects', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?subject_id=39&section=IT-31');

            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('39_IT-31');

            const rosterCard = page.locator('#roster-card');
            await expect(rosterCard).toBeVisible();
            await expect(page.locator('#roster-section')).toHaveText('IT-31');
        });
    });

    test.describe('3. Authorization & Safe Fallback', () => {
        test('Forged non-existent class parameter is rejected with neutral advisory and unselected workspace', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=999_FORGED');

            // Fallback to unselected state
            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('');

            // Neutral advisory message shown
            const alert = page.locator('#routing-error-alert');
            await expect(alert).toBeVisible();
            await expect(alert).toHaveAttribute('role', 'alert');
            await expect(alert).toContainText('The requested class is unavailable. Please select one of your assigned classes.');

            // Roster table is hidden, no student data disclosed
            await expect(page.locator('#roster-card')).toBeHidden();
            await expect(page.locator('#roster-tbody tr')).toHaveCount(0);
        });

        test('Another faculty member class is rejected without revealing class ownership', async ({ page }) => {
            await setupFacultyNavRoute(page, 2); // User 2 does not teach 37_IT-35 (taught by 302)
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=37_IT-35');

            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('');

            const alert = page.locator('#routing-error-alert');
            await expect(alert).toBeVisible();
            await expect(alert).toContainText('The requested class is unavailable. Please select one of your assigned classes.');

            // No roster data
            await expect(page.locator('#roster-card')).toBeHidden();
            await expect(page.locator('#roster-tbody tr')).toHaveCount(0);
        });

        test('Own subject with unauthorized section is rejected safely', async ({ page }) => {
            await setupFacultyNavRoute(page, 2); // User 2 teaches subject 39 in IT-31/32/33, but not IT-37
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=39_IT-37');

            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('');

            const alert = page.locator('#routing-error-alert');
            await expect(alert).toBeVisible();
            await expect(alert).toContainText('The requested class is unavailable. Please select one of your assigned classes.');

            await expect(page.locator('#roster-card')).toBeHidden();
        });

        test('Empty or malformed class parameters fail safely', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=');

            const classSelect = page.locator('#classSelect');
            await expect(classSelect).toHaveValue('');

            const alert = page.locator('#routing-error-alert');
            await expect(alert).toBeVisible();
            await expect(alert).toContainText('The requested class is unavailable. Please select one of your assigned classes.');
        });
    });

    test.describe('4. Navigation, History & URL State', () => {
        test('Browser Back returns to Faculty Home and Forward restores deep-linked workspace', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });

            // 1. Start at Home
            await page.goto('https://faculty-nav.test/faculty/index.php');
            await expect(page.locator('h1')).toContainText('Welcome');

            // 2. Click an assigned class
            const firstCard = page.locator('a[href^="grades.php?class="]').first();
            const cardHref = await firstCard.getAttribute('href');
            await firstCard.click();
            await page.waitForURL(`https://faculty-nav.test/faculty/${cardHref}`);
            await expect(page.locator('#roster-card')).toBeVisible();

            // 3. Browser Back
            await page.goBack();
            await page.waitForURL('https://faculty-nav.test/faculty/index.php');
            await expect(page.locator('h1')).toContainText('Welcome');

            // 4. Browser Forward
            await page.goForward();
            await page.waitForURL(`https://faculty-nav.test/faculty/${cardHref}`);
            await expect(page.locator('#roster-card')).toBeVisible();
            await expect(page.locator('#classSelect')).not.toHaveValue('');
        });

        test('Dropdown changes update URL via replaceState without adding duplicate history entries', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });

            // 1. Start at Home
            await page.goto('https://faculty-nav.test/faculty/index.php');

            // 2. Click class card (e.g. 39_IT-31)
            await page.locator('a[href^="grades.php?class=39_IT-31"]').first().click();
            await page.waitForURL('https://faculty-nav.test/faculty/grades.php?class=39_IT-31');
            await expect(page.locator('#classSelect')).toHaveValue('39_IT-31');

            // 3. Change dropdown to 40_IT-33
            await page.locator('#classSelect').selectOption('40_IT-33');
            await page.waitForFunction(() => window.location.search.includes('class=40_IT-33'));

            // Verify URL is updated
            expect(page.url()).toContain('class=40_IT-33');

            // 4. Browser Back should return directly to Home, because replaceState did not push a new history entry
            await page.goBack();
            await page.waitForURL('https://faculty-nav.test/faculty/index.php');
            await expect(page.locator('h1')).toContainText('Welcome');
        });

        test('Page refresh preserves validated class selection', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.goto('https://faculty-nav.test/faculty/grades.php?class=39_IT-32');

            await expect(page.locator('#classSelect')).toHaveValue('39_IT-32');
            await expect(page.locator('#roster-card')).toBeVisible();

            // Refresh
            await page.reload();
            await expect(page.locator('#classSelect')).toHaveValue('39_IT-32');
            await expect(page.locator('#roster-card')).toBeVisible();
            await expect(page.locator('#roster-section')).toHaveText('IT-32');
        });
    });

    test.describe('5. Viewport & Responsive Parity', () => {
        test('Mobile viewport (390x844) renders accessible Assigned Class cards and supports deep linking', async ({ page }) => {
            await setupFacultyNavRoute(page, 2);
            await page.setViewportSize({ width: 390, height: 844 });
            await page.goto('https://faculty-nav.test/faculty/index.php');

            const firstCard = page.locator('a[href^="grades.php?class="]').first();
            await expect(firstCard).toBeVisible();

            // Title is truncated with ellipsis or title attribute for accessibility
            const titleSpan = firstCard.locator('span[title]').first();
            await expect(titleSpan).toBeVisible();
            const fullTitle = await titleSpan.getAttribute('title');
            expect(fullTitle).toBeTruthy();

            // Click deep link on mobile
            const cardHref = await firstCard.getAttribute('href');
            await firstCard.click();
            await page.waitForURL(`https://faculty-nav.test/faculty/${cardHref}`);

            // Roster and dropdown behave identically on mobile
            await expect(page.locator('#classSelect')).not.toHaveValue('');
            await expect(page.locator('#roster-card')).toBeVisible();
        });
    });
});
