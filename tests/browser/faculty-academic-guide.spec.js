import { mkdir } from 'node:fs/promises';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';
import { test as base, expect } from '@playwright/test';

const evidenceDirectory = resolve('docs/audits/evidence/member2');
const diagnosticsByPage = new WeakMap();
const configuredBaseURL = process.env.PLAYWRIGHT_BASE_URL || 'http://localhost/capstone';
const localBaseUrl = configuredBaseURL.endsWith('/') ? configuredBaseURL : `${configuredBaseURL}/`;
const localOrigin = new URL(localBaseUrl).origin;
const facultyLoginId = process.env.FACULTY_LOGIN_ID?.trim();
const facultyPassword = process.env.FACULTY_PASSWORD;

const viewports = [
    { width: 1440, height: 900 },
    { width: 1024, height: 768 },
    { width: 390, height: 844 },
];

const test = base.extend({
    page: async ({ page }, use, testInfo) => {
        const diagnostics = {
            consoleErrors: [],
            pageErrors: [],
            failedRequests: [],
            badResponses: [],
        };

        page.on('console', message => {
            if (message.type() === 'error') {
                diagnostics.consoleErrors.push(message.text());
            }
        });
        page.on('pageerror', error => diagnostics.pageErrors.push(error.message));
        page.on('requestfailed', request => {
            const failure = request.failure()?.errorText || 'Unknown request failure';
            const classification = failure.includes('ERR_ABORTED')
                ? 'expected-navigation-abort'
                : new URL(request.url()).origin === localOrigin
                    ? 'unexpected-app-failure'
                    : 'external-request-failure';
            diagnostics.failedRequests.push({
                url: request.url(),
                error: failure,
                classification,
            });
        });
        page.on('response', response => {
            if (response.status() < 400) return;

            const url = new URL(response.url());
            const isOptionalFavicon = url.pathname === '/favicon.ico' && response.status() === 404;
            diagnostics.badResponses.push({
                url: response.url(),
                status: response.status(),
                classification: isOptionalFavicon
                    ? 'expected-optional-favicon'
                    : url.origin === localOrigin
                        ? 'unexpected-app-response'
                        : 'external-response',
            });
        });

        diagnosticsByPage.set(page, diagnostics);
        await use(page);

        await testInfo.attach('browser-health.json', {
            body: JSON.stringify(diagnostics, null, 2),
            contentType: 'application/json',
        });

        const optionalFavicon404s = diagnostics.badResponses.filter(
            item => item.classification === 'expected-optional-favicon'
        ).length;
        let classifiedFaviconErrors = 0;
        const unexpectedConsoleErrors = diagnostics.consoleErrors.filter(message => {
            if (
                message === 'Failed to load resource: the server responded with a status of 404 (Not Found)'
                && classifiedFaviconErrors < optionalFavicon404s
            ) {
                classifiedFaviconErrors++;
                return false;
            }
            return true;
        });

        expect(unexpectedConsoleErrors, 'unexpected browser console errors').toEqual([]);
        expect(diagnostics.pageErrors, 'unhandled page errors').toEqual([]);
        expect(
            diagnostics.failedRequests.filter(item => item.classification === 'unexpected-app-failure'),
            'failed requests to the local application'
        ).toEqual([]);
        expect(
            diagnostics.badResponses.filter(item => item.classification === 'unexpected-app-response'),
            'unexpected local application HTTP errors'
        ).toEqual([]);
    },
});

test.describe('Academic Guide Role Scoping and Focus Management (Static HTML)', () => {
    const renderGuide = (role = '') => execFileSync(
        process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
        ['-r', `
            $_SESSION = ['role' => '${role}'];
            echo '<!doctype html><html lang="en" data-theme="light"><head><style>'
                . file_get_contents('assets/css/dashboard.css')
                . '</style></head><body>'
                . '<button id="test-invoking-link" onclick="openGlossaryModal(\\'coverage\\', this)">Invoking Link</button>';
            require 'includes/glossary_modal.php';
            echo '</body></html>';
        `],
        { encoding: 'utf8' }
    );

    test('Non-admin role (faculty) renders exactly 4 tabs; Data Coverage is excluded', async ({ page }) => {
        await page.setContent(renderGuide('faculty'));
        const drawer = page.locator('#glossary-drawer-overlay');
        const tabs = drawer.locator('.guide-tab-btn');
        await expect(tabs).toHaveCount(4);
        await expect(page.locator('#tab-btn-coverage')).toHaveCount(0);
        await expect(page.locator('#tab-pane-coverage')).toHaveCount(0);
        await expect(tabs.last()).toHaveText('About Prototype');
    });

    test('Admin role renders exactly 5 tabs; Data Coverage is included', async ({ page }) => {
        await page.setContent(renderGuide('admin'));
        const drawer = page.locator('#glossary-drawer-overlay');
        const tabs = drawer.locator('.guide-tab-btn');
        await expect(tabs).toHaveCount(5);
        await expect(page.locator('#tab-btn-coverage')).toHaveCount(1);
        await expect(page.locator('#tab-pane-coverage')).toHaveCount(1);
        await expect(tabs.last()).toHaveText('Data Coverage');
    });

    test('Direct open to Data Coverage tab works and Escape restores focus to invoking trigger', async ({ page }) => {
        await page.setContent(renderGuide('admin'));
        const link = page.locator('#test-invoking-link');
        await link.click();
        const drawer = page.locator('#glossary-drawer-overlay');
        await expect(drawer).toHaveClass(/open/);
        await expect(page.locator('#tab-btn-coverage')).toHaveClass(/active/);
        await expect(page.locator('#tab-btn-coverage')).toHaveAttribute('aria-selected', 'true');
        await expect(page.locator('#tab-pane-coverage')).toHaveClass(/active/);

        await page.keyboard.press('Escape');
        await expect(drawer).not.toHaveClass(/open/);
        await expect(link).toBeFocused();
    });
});

test.describe('Authenticated Faculty Portal', () => {
    test.beforeEach(async ({ page }) => {
        test.skip(
            !facultyLoginId || !facultyPassword,
            'Set synthetic Faculty credentials in the ignored .env.playwright.local file.'
        );

        await page.goto('login.php');
        await page.getByRole('textbox', { name: /Student No\. \/ Username/ }).fill(facultyLoginId);
        await page.getByRole('textbox', { name: 'Password' }).fill(facultyPassword);
        await page.getByRole('button', { name: 'Login to Portal' }).click();
        await page.waitForURL('**/faculty/index.php');
        await page.goto('faculty/dashboard.php');
        await expect(page.getByRole('heading', { name: 'Section Overview' })).toBeVisible();
    });

    test.afterEach(async ({ page }) => {
        const diagnostics = diagnosticsByPage.get(page);
        if (diagnostics) diagnosticsByPage.delete(page);
    });

    async function saveEvidence(page, filename) {
        await mkdir(evidenceDirectory, { recursive: true });
        await page.screenshot({
            path: resolve(evidenceDirectory, filename),
            fullPage: false,
            animations: 'disabled',
            mask: [page.locator('.main-content table tbody td')],
        });
    }

    function overlaps(first, second) {
        return first.x < second.x + second.width
            && first.x + first.width > second.x
            && first.y < second.y + second.height
            && first.y + first.height > second.y;
    }

    for (const viewport of viewports) {
        const suffix = `${viewport.width}x${viewport.height}`;

        test(`Faculty dashboard and guide layout at ${suffix}`, async ({ page }) => {
            await page.setViewportSize(viewport);
            await expect(page.getByRole('heading', { name: 'Section Overview' })).toBeVisible();

            const guideTrigger = page.getByRole('button', { name: 'Open Academic Guide & Glossary' });
            await expect(guideTrigger).toBeVisible();
            await saveEvidence(page, `faculty-dashboard-${suffix}.png`);

            if (viewport.width === 390) {
                const sectionCards = page.locator('.main-content > .stat-grid').nth(1).locator(':scope > .card');
                await sectionCards.first().scrollIntoViewIfNeeded();

                const helpBox = await guideTrigger.boundingBox();
                const cardBoxes = await sectionCards.evaluateAll(cards => cards.map(card => {
                    const box = card.getBoundingClientRect();
                    return { x: box.x, y: box.y, width: box.width, height: box.height };
                }));
                expect(helpBox).not.toBeNull();
                expect(cardBoxes.length).toBeGreaterThan(0);
                expect(cardBoxes.some(cardBox => overlaps(helpBox, cardBox))).toBe(false);
                await saveEvidence(page, 'faculty-dashboard-mobile-clearance.png');
            }

            await guideTrigger.focus();
            await page.keyboard.press('Enter');
            const drawer = page.getByRole('dialog', { name: 'UDM Academic Reference & Guide' });
            await expect(drawer).toBeVisible();
            await expect(drawer).toHaveAttribute('aria-modal', 'true');
            await expect(drawer.getByRole('button', { name: 'Close guide' })).toBeVisible();
            await saveEvidence(page, viewport.width === 390
                ? `academic-guide-mobile-${suffix}.png`
                : `academic-guide-desktop-${suffix}.png`);
            await page.keyboard.press('Escape');
            await expect(drawer).toBeHidden();
            await expect(guideTrigger).toBeFocused();
        });
    }

    test('Academic Guide contains Tab and Shift+Tab and restores focus after Escape', async ({ page }) => {
        await page.setViewportSize({ width: 1440, height: 900 });
        const guideTrigger = page.getByRole('button', { name: 'Open Academic Guide & Glossary' });
        const drawer = page.getByRole('dialog', { name: 'UDM Academic Reference & Guide' });
        const closeButton = drawer.getByRole('button', { name: 'Close guide' });
        const lastTab = drawer.getByRole('tab').last();

        await guideTrigger.focus();
        await page.keyboard.press('Enter');
        await expect(drawer).toBeVisible();

        await closeButton.focus();
        await page.keyboard.press('Shift+Tab');
        await expect(lastTab).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(closeButton).toBeFocused();

        await page.keyboard.press('Escape');
        await expect(drawer).toBeHidden();
        await expect(guideTrigger).toBeFocused();

        await guideTrigger.focus();
        await page.keyboard.press('Space');
        await expect(drawer).toBeVisible();
        await drawer.getByRole('tab', { name: 'About Prototype' }).press('Space');
        await expect(drawer.getByRole('tabpanel', { name: 'About Prototype' })).toBeVisible();
        await page.keyboard.press('Escape');
    });

    test('Faculty theme toggle supports light and dark mode', async ({ page }) => {
        const themeToggle = page.locator('button.theme-toggle-btn');
        await expect(themeToggle).toBeVisible();

        const initialLabel = await themeToggle.innerText();
        await themeToggle.click();
        const switchedLabel = await themeToggle.innerText();
        expect(switchedLabel).not.toBe(initialLabel);

        await page.getByRole('button', { name: 'Open Academic Guide & Glossary' }).click();
        await expect(page.getByRole('dialog', { name: 'UDM Academic Reference & Guide' })).toBeVisible();
        await page.getByRole('button', { name: 'Close guide' }).click();
        await themeToggle.click();
        await expect(themeToggle).toHaveText(initialLabel);
    });
});