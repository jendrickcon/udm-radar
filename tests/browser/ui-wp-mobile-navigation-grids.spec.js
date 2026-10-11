import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const uiJs = readFileSync(resolve('assets/js/ui.js'), 'utf-8');
const sidebarPhp = readFileSync(resolve('includes/sidebar.php'), 'utf-8');
const adminIndexPhp = readFileSync(resolve('admin/index.php'), 'utf-8');
const adminGradesPhp = readFileSync(resolve('admin/grades.php'), 'utf-8');
const adminStudentsPhp = readFileSync(resolve('admin/students.php'), 'utf-8');

const evidenceDir = resolve('test-results/evidence/mobile-nav');
mkdirSync(evidenceDir, { recursive: true });

/**
 * Helper to generate a realistic portal page containing sidebar, mobile topbar,
 * overlay, drawer close controls, and KPI / section card grids.
 */
function renderPortalHtml({ activePage = 'index.php', theme = 'light' } = {}) {
    const navItems = [
        { label: 'Dashboard', href: 'index.php' },
        { label: 'Students', href: 'students.php' },
        { label: 'Grades', href: 'grades.php' },
        { label: 'Analytics', href: 'analytics.php' },
        { label: 'Activity & Inbox', href: 'activity.php', badge: 3 },
    ];

    const navLinksHtml = navItems.map(item => {
        const isActive = item.href === activePage;
        const currentAttr = isActive ? ' aria-current="page"' : '';
        const activeClass = isActive ? ' active' : '';
        const badgeHtml = item.badge ? `<span class="nav-badge">${item.badge}</span>` : '';
        return `
            <li>
                <a href="#${item.href.replace('.php', '')}" class="sidebar-link${activeClass}"${currentAttr}>
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" class="sidebar-icon"><circle cx="12" cy="12" r="10"></circle></svg>
                    <span>${item.label}</span> ${badgeHtml}
                </a>
            </li>
        `;
    }).join('\n');

    return `
    <!DOCTYPE html>
    <html lang="en" data-theme="${theme}">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>UDM-RADAR Test Portal</title>
        <style>
            ${dashboardCss}
            /* Test styling for visibility of cards */
            .stat-card {
                background: var(--card-bg, #ffffff);
                border: 1px solid var(--border-color, #e2e8f0);
                border-left: 4px solid var(--accent-blue, #2563eb);
                border-radius: 8px;
                padding: 16px;
                min-height: 80px;
            }
            .section-card {
                background: var(--card-bg, #ffffff);
                border: 1px solid var(--border-color, #e2e8f0);
                border-radius: 8px;
                padding: 16px;
                min-height: 90px;
                cursor: pointer;
            }
        </style>
    </head>
    <body>
        <!-- Mobile Top Bar (Hidden on desktop via CSS) -->
        <header class="mobile-topbar" id="mobile-topbar">
            <button type="button" class="mobile-nav-toggle" id="mobile-nav-toggle" aria-controls="sidebar" aria-expanded="false" aria-label="Open navigation menu">
                <svg class="nav-toggle-icon nav-toggle-icon--menu" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="3" y1="12" x2="21" y2="12"></line>
                    <line x1="3" y1="6" x2="21" y2="6"></line>
                    <line x1="3" y1="18" x2="21" y2="18"></line>
                </svg>
                <svg class="nav-toggle-icon nav-toggle-icon--close" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <line x1="18" y1="6" x2="6" y2="18"></line>
                    <line x1="6" y1="6" x2="18" y2="18"></line>
                </svg>
            </button>
            <div class="mobile-topbar__brand">
                <span class="mobile-topbar__title">UDM-RADAR</span>
            </div>
            <div class="mobile-topbar__spacer" aria-hidden="true"></div>
        </header>

        <!-- Mobile Navigation Drawer Overlay -->
        <div class="sidebar-overlay" id="sidebar-overlay" aria-hidden="true"></div>

        <!-- Sidebar Navigation Drawer -->
        <nav id="sidebar" class="sidebar" aria-label="Main Navigation">
            <div class="sidebar-drawer-header">
                <button type="button" class="sidebar-drawer-close-btn" id="sidebar-drawer-close-btn" aria-label="Close navigation menu">
                    <svg viewBox="0 0 24 24" width="20" height="20" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <line x1="18" y1="6" x2="6" y2="18"></line>
                        <line x1="6" y1="6" x2="18" y2="18"></line>
                    </svg>
                </button>
            </div>
            <div class="sidebar-brand">
                <span style="color: white; font-weight: bold; padding: 10px 16px; display: block;">UDM-RADAR</span>
            </div>
            <ul class="sidebar-nav">
                ${navLinksHtml}
            </ul>
            <div class="sidebar-footer">
                <button type="button" class="theme-toggle-btn" id="theme-toggle-btn">
                    <span id="theme-toggle-text">Dark Mode</span>
                </button>
                <a href="#logout" class="logout-btn" id="logout-btn">
                    <span>Logout</span>
                </a>
            </div>
        </nav>

        <!-- Main Content Area -->
        <main class="main-content" id="main-content">
            <h1>Dashboard Overview</h1>

            <!-- Five-Card KPI Grid -->
            <section aria-labelledby="kpi-heading">
                <h2 id="kpi-heading" style="font-size: 1.1rem; margin-bottom: 12px;">Executive Metrics</h2>
                <div class="stat-grid kpi-grid--five" id="test-kpi-grid">
                    <div class="stat-card" id="card-1"><h4>Total Students</h4><h2>291</h2></div>
                    <div class="stat-card" id="card-2"><h4>At-Risk</h4><h2>16</h2></div>
                    <div class="stat-card" id="card-3"><h4>Irregular</h4><h2>45</h2></div>
                    <div class="stat-card" id="card-4"><h4>No Prediction Yet</h4><h2>3</h2></div>
                    <div class="stat-card" id="card-5"><h4>Avg Cumulative GWA</h4><h2>2.15</h2></div>
                </div>
            </section>

            <!-- Section-Card Grid -->
            <section aria-labelledby="sections-heading">
                <h2 id="sections-heading" style="font-size: 1.1rem; margin-bottom: 12px;">Class Sections</h2>
                <div class="stat-grid section-card-grid" id="test-section-grid">
                    <div class="section-card" id="sec-1"><h3>BSCS 3A</h3><p>35 Students</p></div>
                    <div class="section-card" id="sec-2"><h3>BSCS 3B</h3><p>38 Students</p></div>
                    <div class="section-card" id="sec-3"><h3>BSIT 3A</h3><p>42 Students</p></div>
                </div>
            </section>

            <!-- Four-Card Grid -->
            <section aria-labelledby="four-heading">
                <h2 id="four-heading" style="font-size: 1.1rem; margin-bottom: 12px;">Directory Metrics</h2>
                <div class="stat-grid kpi-grid--four" id="test-four-grid">
                    <div class="stat-card" id="fc-1"><h4>Total</h4><h2>291</h2></div>
                    <div class="stat-card" id="fc-2"><h4>High Risk</h4><h2>6</h2></div>
                    <div class="stat-card" id="fc-3"><h4>Moderate Risk</h4><h2>10</h2></div>
                    <div class="stat-card" id="fc-4"><h4>Active Support</h4><h2>4</h2></div>
                </div>
            </section>
        </main>

        <script>
            ${uiJs}
        </script>
    </body>
    </html>
    `;
}

test.describe('UI-WP-MOBILE: Mobile Navigation and Responsive Card Grids', () => {

    // ─────────────────────────────────────────────────────────────
    // 1. Source Inspection: Contracts, Selectors, and Tokens
    // ─────────────────────────────────────────────────────────────
    test.describe('1. Source Code Inspection & Contract Conformance', () => {
        test('includes/sidebar.php includes mobile topbar, toggle button, overlay, and drawer close button', () => {
            expect(sidebarPhp).toContain('class="mobile-topbar"');
            expect(sidebarPhp).toContain('id="mobile-nav-toggle"');
            expect(sidebarPhp).toContain('aria-controls="sidebar"');
            expect(sidebarPhp).toContain('aria-expanded="false"');
            expect(sidebarPhp).toContain('aria-label="Open navigation menu"');
            expect(sidebarPhp).toContain('class="sidebar-overlay"');
            expect(sidebarPhp).toContain('id="sidebar-overlay"');
            expect(sidebarPhp).toContain('id="sidebar-drawer-close-btn"');
            expect(sidebarPhp).toContain('aria-label="Close navigation menu"');
            expect(sidebarPhp).toContain('aria-current="page"');
        });

        test('assets/css/dashboard.css contains mobile drawer tokens, z-index layering, and responsive grids', () => {
            expect(dashboardCss).toContain('.mobile-topbar');
            expect(dashboardCss).toContain('.mobile-nav-toggle');
            expect(dashboardCss).toContain('.sidebar-overlay');
            expect(dashboardCss).toContain('.sidebar.sidebar--open');
            expect(dashboardCss).toContain('body.body--nav-open');
            expect(dashboardCss).toContain('@media (max-width: 900px)');

            // Z-index hierarchy
            expect(dashboardCss).toContain('z-index: 500'); // topbar
            expect(dashboardCss).toContain('z-index: 20000'); // overlay
            expect(dashboardCss).toContain('z-index: 20010'); // drawer

            // Reduced motion media query
            expect(dashboardCss).toContain('@media (prefers-reduced-motion: reduce)');

            // Responsive grid classes
            expect(dashboardCss).toContain('.kpi-grid--five');
            expect(dashboardCss).toContain('.kpi-grid--four');
            expect(dashboardCss).toContain('.kpi-grid--three');
            expect(dashboardCss).toContain('.section-card-grid');

            // 3+2 layout rule for 5-card grid at 901px-1280px
            expect(dashboardCss).toContain('@media (max-width: 1280px) and (min-width: 901px)');
            expect(dashboardCss).toContain('repeat(6, 1fr)');
        });

        test('assets/js/ui.js exposes mobile nav controller functions', () => {
            expect(uiJs).toContain('window.UDM.openMobileNav = function');
            expect(uiJs).toContain('window.UDM.closeMobileNav = function');
            expect(uiJs).toContain('window.UDM.isMobileNavOpen = function');
            expect(uiJs).toContain('window.UDM.initMobileNav = function');
        });

        test('Target pages employ standardized responsive grid classes', () => {
            // admin/index.php
            expect(adminIndexPhp).toContain('class="stat-grid kpi-grid--five"');

            // admin/grades.php
            expect(adminGradesPhp).toContain('class="stat-grid section-card-grid"');

            // admin/students.php
            expect(adminStudentsPhp).toContain('class="stat-grid kpi-grid--four"');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 2. Desktop Integrity (1440x900)
    // ─────────────────────────────────────────────────────────────
    test.describe('2. Desktop Viewport (1440x900) Baseline', () => {
        test('Sidebar is visible in-flow, mobile topbar is hidden, overlay is absent', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderPortalHtml());

            const topbar = page.locator('#mobile-topbar');
            await expect(topbar).toBeHidden();

            const toggleBtn = page.locator('#mobile-nav-toggle');
            await expect(toggleBtn).toBeHidden();

            const overlay = page.locator('#sidebar-overlay');
            await expect(overlay).toBeHidden();

            const sidebar = page.locator('#sidebar');
            await expect(sidebar).toBeVisible();

            // Verify sidebar is in normal desktop flow (not fixed offscreen)
            const sidebarBox = await sidebar.boundingBox();
            expect(sidebarBox).not.toBeNull();
            expect(sidebarBox.x).toBe(0);
            expect(sidebarBox.width).toBeGreaterThanOrEqual(240);

            // Body does not have nav open class
            const bodyClass = await page.locator('body').getAttribute('class');
            expect(bodyClass || '').not.toContain('body--nav-open');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 3. Mobile Viewport (390x844) Navigation Interaction
    // ─────────────────────────────────────────────────────────────
    test.describe('3. Mobile Navigation Drawer Interaction (390x844)', () => {
        test.beforeEach(async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());
        });

        test('Mobile topbar is sticky and hamburger toggle meets touch target >= 44x44px', async ({ page }) => {
            const topbar = page.locator('#mobile-topbar');
            await expect(topbar).toBeVisible();

            const toggle = page.locator('#mobile-nav-toggle');
            await expect(toggle).toBeVisible();
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(toggle).toHaveAttribute('aria-label', 'Open navigation menu');
            await expect(toggle).toHaveAttribute('aria-controls', 'sidebar');

            const toggleBox = await toggle.boundingBox();
            expect(toggleBox).not.toBeNull();
            expect(toggleBox.width).toBeGreaterThanOrEqual(44);
            expect(toggleBox.height).toBeGreaterThanOrEqual(44);

            // Drawer is closed and not visible
            const sidebar = page.locator('#sidebar');
            await expect(sidebar).not.toHaveClass(/sidebar--open/);
            const overlay = page.locator('#sidebar-overlay');
            await expect(overlay).not.toHaveClass(/open/);
        });

        test('Clicking hamburger opens drawer, locks scroll, updates aria-expanded, and shifts focus', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');
            const overlay = page.locator('#sidebar-overlay');
            const body = page.locator('body');

            await toggle.click();

            // Verified state changes
            await expect(sidebar).toHaveClass(/sidebar--open/);
            await expect(overlay).toHaveClass(/open/);
            await expect(body).toHaveClass(/body--nav-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'true');
            await expect(toggle).toHaveAttribute('aria-label', 'Close navigation menu');

            // Scroll lock on body
            const bodyOverflow = await body.evaluate(el => window.getComputedStyle(el).overflow);
            expect(bodyOverflow).toBe('hidden');

            // Focus shifted inside drawer
            const focusedInsideSidebar = await sidebar.evaluate((nav) => {
                return nav.contains(document.activeElement);
            });
            expect(focusedInsideSidebar).toBe(true);
        });

        test('Focus containment (trap): Tab and Shift+Tab wrap within drawer', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');
            await toggle.click();
            await expect(sidebar).toHaveClass(/sidebar--open/);

            // Focus last focusable element (logout button)
            const logoutBtn = page.locator('#logout-btn');
            await logoutBtn.focus();
            await expect(logoutBtn).toBeFocused();

            // Press Tab from last element -> should wrap to first focusable element
            await page.keyboard.press('Tab');
            const activeElementAfterTab = await page.evaluate(() => document.activeElement.id || document.activeElement.tagName);
            expect(['mobile-nav-toggle', 'sidebar-drawer-close-btn']).toContain(activeElementAfterTab);

            // Now press Shift+Tab -> should wrap back to last element
            await page.keyboard.press('Shift+Tab');
            await expect(logoutBtn).toBeFocused();
        });

        test('Escape key closes drawer and returns focus to menu toggle button', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');
            const overlay = page.locator('#sidebar-overlay');

            await toggle.click();
            await expect(sidebar).toHaveClass(/sidebar--open/);

            await page.keyboard.press('Escape');

            // Verify drawer closed
            await expect(sidebar).not.toHaveClass(/sidebar--open/);
            await expect(overlay).not.toHaveClass(/open/);
            await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');

            // Verify focus restored to toggle button
            await expect(toggle).toBeFocused();
        });

        test('Backdrop/Overlay click closes drawer and returns focus to menu toggle button', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');
            const overlay = page.locator('#sidebar-overlay');

            await toggle.click();
            await expect(sidebar).toHaveClass(/sidebar--open/);

            // Click overlay
            await overlay.click({ position: { x: 350, y: 300 } });

            // Verify closed
            await expect(sidebar).not.toHaveClass(/sidebar--open/);
            await expect(overlay).not.toHaveClass(/open/);
            await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(toggle).toBeFocused();
        });

        test('Drawer close button closes drawer and returns focus to menu toggle button', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');
            const closeBtn = page.locator('#sidebar-drawer-close-btn');

            await toggle.click();
            await expect(sidebar).toHaveClass(/sidebar--open/);

            await closeBtn.click();

            await expect(sidebar).not.toHaveClass(/sidebar--open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
            await expect(toggle).toBeFocused();
        });

        test('Nav link click closes drawer', async ({ page }) => {
            const toggle = page.locator('#mobile-nav-toggle');
            const sidebar = page.locator('#sidebar');

            await toggle.click();
            await expect(sidebar).toHaveClass(/sidebar--open/);

            // Click a sidebar link
            const firstLink = sidebar.locator('.sidebar-link').first();
            await firstLink.click();

            await expect(sidebar).not.toHaveClass(/sidebar--open/);
            await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        });

        test('Active page link exposes aria-current="page"', async ({ page }) => {
            const activeLink = page.locator('.sidebar-link.active');
            await expect(activeLink).toHaveAttribute('aria-current', 'page');

            const inactiveLink = page.locator('.sidebar-link:not(.active)').first();
            await expect(inactiveLink).not.toHaveAttribute('aria-current', 'page');
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 4. Viewport Transitions & Screen Orientation
    // ─────────────────────────────────────────────────────────────
    test.describe('4. Viewport Transitions & Screen Orientation', () => {
        test('Resizing from mobile to desktop automatically closes drawer and clears state', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());

            const toggle = page.locator('#mobile-nav-toggle');
            await toggle.click();
            await expect(page.locator('#sidebar')).toHaveClass(/sidebar--open/);

            // Resize viewport to desktop width 1024px
            await page.setViewportSize({ width: 1024, height: 768 });

            await expect(page.locator('#sidebar')).not.toHaveClass(/sidebar--open/);
            await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
            await expect(toggle).toHaveAttribute('aria-expanded', 'false');
        });

        test('Short height viewport (390x700): Drawer allows vertical scrolling to reach theme and logout controls', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 700 });
            await page.setContent(renderPortalHtml());

            const toggle = page.locator('#mobile-nav-toggle');
            await toggle.click();

            const sidebar = page.locator('#sidebar');
            await expect(sidebar).toHaveClass(/sidebar--open/);

            const scrollHeight = await sidebar.evaluate(el => el.scrollHeight);
            const clientHeight = await sidebar.evaluate(el => el.clientHeight);
            expect(clientHeight).toBeLessThanOrEqual(700);

            // Scroll footer into view
            const logoutBtn = page.locator('#logout-btn');
            await logoutBtn.scrollIntoViewIfNeeded();
            await expect(logoutBtn).toBeVisible();
        });

        test('Landscape mobile viewport (844x390): Drawer remains vertically scrollable and operable', async ({ page }) => {
            await page.setViewportSize({ width: 844, height: 390 });
            await page.setContent(renderPortalHtml());

            const toggle = page.locator('#mobile-nav-toggle');
            await toggle.click();

            const sidebar = page.locator('#sidebar');
            await expect(sidebar).toHaveClass(/sidebar--open/);

            const themeBtn = page.locator('#theme-toggle-btn');
            await themeBtn.scrollIntoViewIfNeeded();
            await expect(themeBtn).toBeVisible();

            const logoutBtn = page.locator('#logout-btn');
            await logoutBtn.scrollIntoViewIfNeeded();
            await expect(logoutBtn).toBeVisible();
        });

        test('Reduced motion preference disables drawer and overlay transition', async ({ page }) => {
            await page.emulateMedia({ reducedMotion: 'reduce' });
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());

            const sidebarTransition = await page.locator('#sidebar').evaluate(el => window.getComputedStyle(el).transition);
            expect(['none', 'none 0s ease 0s']).toContain(sidebarTransition);

            const overlayTransition = await page.locator('#sidebar-overlay').evaluate(el => window.getComputedStyle(el).transition);
            expect(['none', 'none 0s ease 0s']).toContain(overlayTransition);
        });

        test('Z-index layering ensures drawer and overlay sit above content and guide dialogs', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());

            const overlayZIndex = await page.locator('#sidebar-overlay').evaluate(el => parseInt(window.getComputedStyle(el).zIndex, 10));
            const sidebarZIndex = await page.locator('#sidebar').evaluate(el => parseInt(window.getComputedStyle(el).zIndex, 10));
            const topbarZIndex = await page.locator('#mobile-topbar').evaluate(el => parseInt(window.getComputedStyle(el).zIndex, 10));

            expect(topbarZIndex).toBe(500);
            expect(overlayZIndex).toBe(20000);
            expect(sidebarZIndex).toBe(20010);
            expect(sidebarZIndex).toBeGreaterThan(overlayZIndex);
            expect(overlayZIndex).toBeGreaterThan(10001); // Academic guide is 10000/10001
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 5. Responsive KPI and Section Grid Breakpoints
    // ─────────────────────────────────────────────────────────────
    test.describe('5. Responsive KPI and Section Grid Layouts', () => {
        test('Five-Card KPI Grid: 5 columns on desktop > 1280px', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderPortalHtml());

            const cards = page.locator('#test-kpi-grid > .stat-card');
            expect(await cards.count()).toBe(5);

            const boxes = await Promise.all([
                cards.nth(0).boundingBox(),
                cards.nth(1).boundingBox(),
                cards.nth(2).boundingBox(),
                cards.nth(3).boundingBox(),
                cards.nth(4).boundingBox(),
            ]);

            // In 5-column layout, all 5 cards have identical top Y coordinate
            const y0 = Math.round(boxes[0].y);
            for (let i = 1; i < 5; i++) {
                expect(Math.round(boxes[i].y)).toBe(y0);
            }
        });

        test('Five-Card KPI Grid: Intentional 3 + 2 layout at medium desktop (1200px)', async ({ page }) => {
            await page.setViewportSize({ width: 1200, height: 720 });
            await page.setContent(renderPortalHtml());

            const cards = page.locator('#test-kpi-grid > .stat-card');
            const boxes = await Promise.all([
                cards.nth(0).boundingBox(),
                cards.nth(1).boundingBox(),
                cards.nth(2).boundingBox(),
                cards.nth(3).boundingBox(),
                cards.nth(4).boundingBox(),
            ]);

            // Row 1: cards 0, 1, 2 share the same Y
            const yRow1 = Math.round(boxes[0].y);
            expect(Math.round(boxes[1].y)).toBe(yRow1);
            expect(Math.round(boxes[2].y)).toBe(yRow1);

            // Row 2: cards 3, 4 share a lower Y
            const yRow2 = Math.round(boxes[3].y);
            expect(yRow2).toBeGreaterThan(yRow1);
            expect(Math.round(boxes[4].y)).toBe(yRow2);

            // Cards 3 and 4 span 3 cols each (50% each), so they are wider than row 1 cards
            expect(boxes[3].width).toBeGreaterThan(boxes[0].width);
        });

        test('Five-Card KPI Grid: 2 columns on tablet (768px)', async ({ page }) => {
            await page.setViewportSize({ width: 768, height: 1024 });
            await page.setContent(renderPortalHtml());

            const cards = page.locator('#test-kpi-grid > .stat-card');
            const boxes = await Promise.all([
                cards.nth(0).boundingBox(),
                cards.nth(1).boundingBox(),
                cards.nth(2).boundingBox(),
                cards.nth(3).boundingBox(),
                cards.nth(4).boundingBox(),
            ]);

            // Row 1: 0 & 1
            expect(Math.round(boxes[0].y)).toBe(Math.round(boxes[1].y));
            // Row 2: 2 & 3
            expect(Math.round(boxes[2].y)).toBe(Math.round(boxes[3].y));
            // Row 3: 4 (spans 2 cols)
            expect(Math.round(boxes[4].y)).toBeGreaterThan(Math.round(boxes[2].y));
        });

        test('Five-Card KPI Grid: 1 column on mobile (390px)', async ({ page }) => {
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());

            const cards = page.locator('#test-kpi-grid > .stat-card');
            const boxes = await Promise.all([
                cards.nth(0).boundingBox(),
                cards.nth(1).boundingBox(),
                cards.nth(2).boundingBox(),
                cards.nth(3).boundingBox(),
                cards.nth(4).boundingBox(),
            ]);

            // Stacked vertically: each card Y is strictly greater than the previous
            for (let i = 1; i < 5; i++) {
                expect(boxes[i].y).toBeGreaterThan(boxes[i - 1].y);
            }
        });

        test('Section-Card Grid: 3 columns desktop, 2 columns tablet, 1 column mobile', async ({ page }) => {
            // Desktop 1200px: 3 columns
            await page.setViewportSize({ width: 1200, height: 720 });
            await page.setContent(renderPortalHtml());
            const secCards = page.locator('#test-section-grid > .section-card');
            let b0 = await secCards.nth(0).boundingBox();
            let b1 = await secCards.nth(1).boundingBox();
            let b2 = await secCards.nth(2).boundingBox();
            expect(Math.round(b0.y)).toBe(Math.round(b1.y));
            expect(Math.round(b1.y)).toBe(Math.round(b2.y));

            // Tablet 768px: 2 columns
            await page.setViewportSize({ width: 768, height: 1024 });
            b0 = await secCards.nth(0).boundingBox();
            b1 = await secCards.nth(1).boundingBox();
            b2 = await secCards.nth(2).boundingBox();
            expect(Math.round(b0.y)).toBe(Math.round(b1.y));
            expect(Math.round(b2.y)).toBeGreaterThan(Math.round(b0.y));

            // Mobile 390px: 1 column
            await page.setViewportSize({ width: 390, height: 844 });
            b0 = await secCards.nth(0).boundingBox();
            b1 = await secCards.nth(1).boundingBox();
            b2 = await secCards.nth(2).boundingBox();
            expect(Math.round(b1.y)).toBeGreaterThan(Math.round(b0.y));
            expect(Math.round(b2.y)).toBeGreaterThan(Math.round(b1.y));
        });

        test('Four-Card KPI Grid: 4 columns desktop, 2 columns tablet, 1 column mobile', async ({ page }) => {
            // Desktop 1200px: 4 columns
            await page.setViewportSize({ width: 1200, height: 720 });
            await page.setContent(renderPortalHtml());
            const fc = page.locator('#test-four-grid > .stat-card');
            let fb = await Promise.all([fc.nth(0).boundingBox(), fc.nth(1).boundingBox(), fc.nth(2).boundingBox(), fc.nth(3).boundingBox()]);
            expect(Math.round(fb[0].y)).toBe(Math.round(fb[1].y));
            expect(Math.round(fb[1].y)).toBe(Math.round(fb[2].y));
            expect(Math.round(fb[2].y)).toBe(Math.round(fb[3].y));

            // Tablet 768px: 2 columns
            await page.setViewportSize({ width: 768, height: 1024 });
            fb = await Promise.all([fc.nth(0).boundingBox(), fc.nth(1).boundingBox(), fc.nth(2).boundingBox(), fc.nth(3).boundingBox()]);
            expect(Math.round(fb[0].y)).toBe(Math.round(fb[1].y));
            expect(Math.round(fb[2].y)).toBe(Math.round(fb[3].y));
            expect(Math.round(fb[2].y)).toBeGreaterThan(Math.round(fb[0].y));

            // Mobile 390px: 1 column
            await page.setViewportSize({ width: 390, height: 844 });
            fb = await Promise.all([fc.nth(0).boundingBox(), fc.nth(1).boundingBox(), fc.nth(2).boundingBox(), fc.nth(3).boundingBox()]);
            expect(fb[1].y).toBeGreaterThan(fb[0].y);
            expect(fb[2].y).toBeGreaterThan(fb[1].y);
            expect(fb[3].y).toBeGreaterThan(fb[2].y);
        });
    });

    // ─────────────────────────────────────────────────────────────
    // 6. Evidence Screenshot Capture
    // ─────────────────────────────────────────────────────────────
    test.describe('6. Evidence Screenshot Generation', () => {
        test('Capture all required verification screenshots in test-results/evidence/mobile-nav/', async ({ page }) => {
            // 1. Mobile topbar closed
            await page.setViewportSize({ width: 390, height: 844 });
            await page.setContent(renderPortalHtml());
            await page.screenshot({ path: resolve(evidenceDir, 'mobile-topbar-closed.png'), fullPage: false });

            // 2. Mobile drawer open
            const toggle = page.locator('#mobile-nav-toggle');
            await toggle.click();
            await expect(page.locator('#sidebar')).toHaveClass(/sidebar--open/);
            await page.screenshot({ path: resolve(evidenceDir, 'mobile-drawer-open.png'), fullPage: false });

            // 3. Mobile landscape drawer open
            await page.setViewportSize({ width: 844, height: 390 });
            await page.setContent(renderPortalHtml());
            const toggleLandscape = page.locator('#mobile-nav-toggle');
            await toggleLandscape.click();
            await expect(page.locator('#sidebar')).toHaveClass(/sidebar--open/);
            await page.screenshot({ path: resolve(evidenceDir, 'mobile-landscape-drawer.png'), fullPage: false });

            // 4. Desktop sidebar
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(renderPortalHtml());
            await page.screenshot({ path: resolve(evidenceDir, 'desktop-sidebar.png'), fullPage: false });

            // 5. KPI grid 5-col desktop
            const kpiSection = page.locator('#test-kpi-grid');
            await kpiSection.screenshot({ path: resolve(evidenceDir, 'kpi-grid-5-col-desktop.png') });

            // 6. KPI grid 3+2 col medium
            await page.setViewportSize({ width: 1200, height: 720 });
            await kpiSection.screenshot({ path: resolve(evidenceDir, 'kpi-grid-3-2-col-medium.png') });

            // 7. KPI grid 1-col mobile
            await page.setViewportSize({ width: 390, height: 844 });
            await kpiSection.screenshot({ path: resolve(evidenceDir, 'kpi-grid-mobile.png') });
        });
    });
});
