import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

const dashboardCss = readFileSync(resolve('assets/css/dashboard.css'), 'utf-8');
const evidenceDir = resolve('test-results/evidence/mobile-2b');
try { mkdirSync(evidenceDir, { recursive: true }); } catch (e) {}

/**
 * Helper to mount HTML with dashboard CSS in the page
 */
function wrapHtml(bodyContent, theme = 'dark') {
    return `<!DOCTYPE html>
<html lang="en" data-theme="${theme}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mobile 2B Hardening Verification</title>
    <style>${dashboardCss}</style>
</head>
<body style="margin: 0; padding: 0; background-color: var(--bg-color); color: var(--text-dark);">
    <div class="main-content" style="padding: 24px; box-sizing: border-box; width: 100%;">
        ${bodyContent}
    </div>
</body>
</html>`;
}

test.describe('UI-WP-MOBILE-2B: Mobile Forms, Settings, Hierarchy, and Table Strategies', () => {

    // ─────────────────────────────────────────────────────────────────────────
    // MOB-021: Admin Analytics Filter Component
    // ─────────────────────────────────────────────────────────────────────────
    test.describe('MOB-021: Admin Analytics Filter Component', () => {
        const filterFormHtml = `
        <form method="GET" action="analytics.php" class="card analytics-filter-card">
            <div class="analytics-filter-group analytics-filter-group--scope">
                <span class="analytics-filter-group__label">Academic Scope</span>
                <div class="analytics-filter-controls analytics-filter-controls--dual">
                    <div class="analytics-filter-field">
                        <label for="filter-sy" class="sr-only">School Year</label>
                        <select id="filter-sy" name="sy" class="analytics-filter-select" aria-label="School Year">
                            <option value="2026-2027" selected>S.Y. 2026-2027</option>
                            <option value="2025-2026">S.Y. 2025-2026</option>
                        </select>
                    </div>
                    <div class="analytics-filter-field">
                        <label for="filter-semester" class="sr-only">Semester</label>
                        <select id="filter-semester" name="semester" class="analytics-filter-select" aria-label="Semester">
                            <option value="1" selected>Semester 1</option>
                            <option value="2">Semester 2</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="analytics-filter-group analytics-filter-group--period">
                <label for="filter-period" class="analytics-filter-group__label">Grading Period</label>
                <div class="analytics-filter-controls">
                    <div class="analytics-filter-field">
                        <select id="filter-period" name="period" class="analytics-filter-select" aria-label="Grading Period">
                            <option value="prelim" selected>Preliminary</option>
                            <option value="midterm">Midterm</option>
                            <option value="pre_final">Pre-Final</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="analytics-filter-group analytics-filter-group--curriculum">
                <span class="analytics-filter-group__label">Curriculum Isolation</span>
                <div class="analytics-filter-controls analytics-filter-controls--triple">
                    <div class="analytics-filter-field">
                        <label for="filter-year-level" class="sr-only">Year Level</label>
                        <select id="filter-year-level" name="year_level" class="analytics-filter-select" aria-label="Year Level">
                            <option value="">All Years</option>
                            <option value="1">1st Year</option>
                        </select>
                    </div>
                    <div class="analytics-filter-field">
                        <label for="filter-subject-id" class="sr-only">Subject</label>
                        <select id="filter-subject-id" name="subject_id" class="analytics-filter-select" aria-label="Subject">
                            <option value="">All Subjects</option>
                            <option value="1">CC101 — Programming</option>
                        </select>
                    </div>
                    <div class="analytics-filter-field">
                        <label for="filter-section" class="sr-only">Section</label>
                        <select id="filter-section" name="section" class="analytics-filter-select" aria-label="Section">
                            <option value="">All Sections</option>
                            <option value="BSIT 3-A">BSIT 3-A</option>
                        </select>
                    </div>
                </div>
            </div>

            <div class="analytics-filter-actions">
                <a href="analytics.php" class="btn btn--secondary analytics-filter-btn analytics-filter-btn--reset">Reset</a>
                <button type="submit" class="btn btn--primary analytics-filter-btn analytics-filter-btn--apply">
                    Apply Filters
                </button>
            </div>
        </form>`;

        const testViewports = [
            [360, 800], [390, 844], [393, 851], [412, 915], [600, 960], [768, 1024], [1024, 768], [1440, 900]
        ];

        for (const [w, h] of testViewports) {
            test(`MOB-021 Filter layout & min-touch target: ${w}x${h}`, async ({ page }) => {
                await page.setViewportSize({ width: w, height: h });
                await page.setContent(wrapHtml(filterFormHtml, 'dark'));

                // Verify min-touch target (>=44px) on all controls and buttons
                const controls = page.locator('.analytics-filter-select, .analytics-filter-btn');
                const count = await controls.count();
                expect(count).toBe(8); // 6 selects + 2 action buttons

                for (let i = 0; i < count; i++) {
                    const box = await controls.nth(i).boundingBox();
                    expect(box).not.toBeNull();
                    expect(box.height).toBeGreaterThanOrEqual(43.5); // 44px nominal
                }

                // Verify no horizontal overflow of the page/container
                const overflow = await page.evaluate(() => {
                    const el = document.querySelector('.analytics-filter-card');
                    return {
                        cardScrollWidth: el.scrollWidth,
                        cardClientWidth: el.clientWidth,
                        docScrollWidth: document.documentElement.scrollWidth,
                        windowInnerWidth: window.innerWidth
                    };
                });
                expect(overflow.cardScrollWidth).toBeLessThanOrEqual(overflow.cardClientWidth + 1);
                expect(overflow.docScrollWidth).toBeLessThanOrEqual(overflow.windowInnerWidth);
            });
        }

        for (const [w, h] of [[390, 844], [393, 851]]) {
            test(`MOB-021 Mobile sequence (${w}x${h}): Exact filter order 1-7, min-touch, card containment, and no overflow`, async ({ page }) => {
                await page.setViewportSize({ width: w, height: h });
                await page.setContent(wrapHtml(filterFormHtml, 'dark'));

                // DOM order verification:
                // 1. School Year (sy)
                // 2. Semester (semester)
                // 3. Grading Period (period)
                // 4. Year Level (year_level)
                // 5. Subject (subject_id)
                // 6. Section (section)
                // 7. Reset and Apply Filters
                const selects = page.locator('.analytics-filter-select');
                expect(await selects.count()).toBe(6);
                expect(await selects.nth(0).getAttribute('name')).toBe('sy');
                expect(await selects.nth(1).getAttribute('name')).toBe('semester');
                expect(await selects.nth(2).getAttribute('name')).toBe('period');
                expect(await selects.nth(3).getAttribute('name')).toBe('year_level');
                expect(await selects.nth(4).getAttribute('name')).toBe('subject_id');
                expect(await selects.nth(5).getAttribute('name')).toBe('section');

                const resetBtn = page.locator('.analytics-filter-btn--reset');
                const applyBtn = page.locator('.analytics-filter-btn--apply');
                await expect(resetBtn).toBeVisible();
                await expect(applyBtn).toBeVisible();
                expect(await resetBtn.textContent()).toContain('Reset');
                expect(await applyBtn.textContent()).toContain('Apply Filters');

                // Visual vertical order verification on mobile:
                const boxSy = await selects.nth(0).boundingBox();
                const boxSem = await selects.nth(1).boundingBox();
                const boxPeriod = await selects.nth(2).boundingBox();
                const boxYl = await selects.nth(3).boundingBox();
                const boxSubj = await selects.nth(4).boundingBox();
                const boxSec = await selects.nth(5).boundingBox();
                const boxReset = await resetBtn.boundingBox();
                const boxApply = await applyBtn.boundingBox();

                // Sy and Sem are side-by-side in dual grid
                expect(Math.abs(boxSy.y - boxSem.y)).toBeLessThan(5);
                // Period is below scope
                expect(boxPeriod.y).toBeGreaterThan(boxSy.y + 20);
                // Year Level is below period
                expect(boxYl.y).toBeGreaterThan(boxPeriod.y + 20);
                // Subject is below year level
                expect(boxSubj.y).toBeGreaterThan(boxYl.y + 20);
                // Section is below subject
                expect(boxSec.y).toBeGreaterThan(boxSubj.y + 20);
                // Actions are below section
                expect(boxReset.y).toBeGreaterThan(boxSec.y + 20);
                expect(Math.abs(boxReset.y - boxApply.y)).toBeLessThan(5);

                // Min-touch target >= 43.5px on all controls and buttons
                const allControls = page.locator('.analytics-filter-select, .analytics-filter-btn');
                const count = await allControls.count();
                for (let i = 0; i < count; i++) {
                    const box = await allControls.nth(i).boundingBox();
                    expect(box.height).toBeGreaterThanOrEqual(43.5);
                }

                // Card containment verification: all controls remain inside the filter card bounding box
                const card = page.locator('.analytics-filter-card');
                const cardBox = await card.boundingBox();
                for (let i = 0; i < count; i++) {
                    const box = await allControls.nth(i).boundingBox();
                    expect(box.x).toBeGreaterThanOrEqual(cardBox.x - 1);
                    expect(box.x + box.width).toBeLessThanOrEqual(cardBox.x + cardBox.width + 1);
                    expect(box.y).toBeGreaterThanOrEqual(cardBox.y - 1);
                    expect(box.y + box.height).toBeLessThanOrEqual(cardBox.y + cardBox.height + 1);
                }

                // No document horizontal overflow
                const overflow = await page.evaluate(() => {
                    return {
                        docScrollWidth: document.documentElement.scrollWidth,
                        windowInnerWidth: window.innerWidth
                    };
                });
                expect(overflow.docScrollWidth).toBeLessThanOrEqual(overflow.windowInnerWidth);
            });
        }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MOB-022: Full-width Settings Cards
    // ─────────────────────────────────────────────────────────────────────────
    test.describe('MOB-022: Full-width Settings Cards', () => {
        const settingsCardHtml = `
        <div class="settings-layout">
            <div class="card settings-card">
                <h3 style="margin-top:0; margin-bottom:16px; color:var(--text-dark);">Change Password</h3>
                <form method="POST" action="settings.php" style="display:flex; flex-direction:column; gap:16px;">
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="current_password" style="font-weight:600; font-size:0.85rem; color:var(--text-gray);">Current Password</label>
                        <input type="password" id="current_password" name="current_password" class="form-control" style="min-height:44px; padding:10px 14px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);" required>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="new_password" style="font-weight:600; font-size:0.85rem; color:var(--text-gray);">New Password</label>
                        <input type="password" id="new_password" name="new_password" class="form-control" style="min-height:44px; padding:10px 14px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);" required>
                    </div>
                    <div style="display:flex; flex-direction:column; gap:6px;">
                        <label for="confirm_password" style="font-weight:600; font-size:0.85rem; color:var(--text-gray);">Confirm New Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control" style="min-height:44px; padding:10px 14px; border:1px solid var(--border-color); border-radius:6px; background:var(--bg-color); color:var(--text-dark);" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary" style="min-height:44px; padding:12px 24px; border:none; border-radius:6px; background:var(--accent-blue); color:white; font-weight:600; cursor:pointer;">
                            Update Password
                        </button>
                    </div>
                </form>
            </div>
        </div>`;

        for (const [w, h] of [[390, 844], [393, 851]]) {
            test(`MOB-022 Mobile (${w}x${h}): Settings card spans full width with 44px min-touch`, async ({ page }) => {
                await page.setViewportSize({ width: w, height: h });
                await page.setContent(wrapHtml(settingsCardHtml, 'dark'));

                const card = page.locator('.settings-card');
                const main = page.locator('.main-content');
                const cardBox = await card.boundingBox();
                const mainBox = await main.boundingBox();

                // On mobile, card should span available main-content width (accounting for container padding)
                expect(cardBox.width).toBeGreaterThanOrEqual(mainBox.width - 48); // 24px padding on each side

                // Form inputs and button should have min-height >= 44px
                const inputs = page.locator('.form-control, button[type="submit"]');
                const count = await inputs.count();
                for (let i = 0; i < count; i++) {
                    const b = await inputs.nth(i).boundingBox();
                    expect(b.height).toBeGreaterThanOrEqual(43.5);
                }
            });
        }

        test('MOB-022 Desktop (1440x900): Settings card max-width clamped for readability', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(wrapHtml(settingsCardHtml, 'dark'));

            const card = page.locator('.settings-card');
            const cardBox = await card.boundingBox();

            // Desktop max width should be clamped to 640px
            expect(cardBox.width).toBeLessThanOrEqual(640);
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MOB-023: Table Presentation Strategies
    // ─────────────────────────────────────────────────────────────────────────
    test.describe('MOB-023: Table Presentation Strategies', () => {

        // Category A: Operational Workflows (Faculty Referrals & Admin Open Reports)
        test.describe('Category A: Operational Workflows', () => {
            const referralsHtml = `
            <div class="referrals-container">
                <div class="referrals-desktop-view">
                    <table class="data-table" style="width:100%;">
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Subject</th>
                                <th>Program Risk</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Cruz, Juan</td>
                                <td>CC101 — Computer Programming</td>
                                <td><span class="badge badge--high">High Risk</span></td>
                                <td><span class="badge badge--warning">Needs Review</span></td>
                                <td><button class="btn-primary">Review</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="referrals-mobile-cards" style="display:none;">
                    <div class="referral-mobile-card">
                        <div class="referral-card__header">
                            <div>
                                <h4 class="referral-card__student-name">Juan Cruz</h4>
                                <span class="referral-card__student-no">SYN-00123</span>
                            </div>
                            <span class="badge badge--high">High Risk</span>
                        </div>
                        <div class="referral-card__grid">
                            <div class="referral-card__grid-item">
                                <span class="referral-card__grid-label">Subject</span>
                                <span class="referral-card__grid-value">CC101 — Computer Programming</span>
                            </div>
                            <div class="referral-card__grid-item">
                                <span class="referral-card__grid-label">Status</span>
                                <span class="referral-card__grid-value"><span class="badge badge--warning">Needs Review</span></span>
                            </div>
                        </div>
                        <div class="referral-card__actions">
                            <button type="button" class="btn-primary referral-card__btn">Review Referral</button>
                        </div>
                    </div>
                </div>
            </div>`;

            // System Audit Log Category A
            const auditLogHtml = `
            <div class="card" style="padding: 0; overflow: hidden;">
                <div class="audit-log-desktop-view">
                    <table class="audit-log-table">
                        <tbody>
                            <tr>
                                <td>Oct 15, 2026</td>
                                <td>Admin User modified grade</td>
                                <td><button type="button" class="btn-primary desktop-audit-action">Details</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="audit-log-mobile-cards">
                    <div class="card">
                        <div>Oct 15, 2026</div>
                        <div>Admin User</div>
                        <button type="button" class="btn-primary mobile-audit-action">Details</button>
                    </div>
                </div>
            </div>`;

            for (const [w, h] of [[390, 844], [393, 851]]) {
                test(`MOB-023 Category A (Mobile ${w}x${h}): Desktop table hidden, mobile cards visible with 44px actions`, async ({ page }) => {
                    await page.setViewportSize({ width: w, height: h });
                    await page.setContent(wrapHtml(referralsHtml, 'dark'));

                    const desktopTable = page.locator('.referrals-desktop-view');
                    const mobileCards = page.locator('.referrals-mobile-cards');

                    // Desktop table should be hidden via CSS media query
                    await expect(desktopTable).toHaveCSS('display', 'none');

                    // Mobile cards container should be visible
                    await expect(mobileCards).toHaveCSS('display', 'flex');

                    // Mobile card action button should have min-height >= 44px
                    const btn = page.locator('.referral-card__btn');
                    const btnBox = await btn.boundingBox();
                    expect(btnBox.height).toBeGreaterThanOrEqual(43.5);
                });

                test(`MOB-023 Category A Audit Log (${w}x${h}): Desktop table vs mobile cards display`, async ({ page }) => {
                    await page.setViewportSize({ width: w, height: h });
                    await page.setContent(wrapHtml(auditLogHtml, 'dark'));

                    const desktopView = page.locator('.audit-log-desktop-view');
                    const mobileCards = page.locator('.audit-log-mobile-cards');

                    await expect(desktopView).toHaveCSS('display', 'none');
                    await expect(mobileCards).toHaveCSS('display', 'flex');
                });

                test(`MOB-023 Accessible Duplicate-Content Contract (${w}x${h}): Hidden desktop action not focusable`, async ({ page }) => {
                    await page.setViewportSize({ width: w, height: h });
                    await page.setContent(wrapHtml(referralsHtml, 'dark'));

                    const desktopBtn = page.locator('.referrals-desktop-view button');
                    const mobileBtn = page.locator('.referrals-mobile-cards button');

                    // Hidden desktop action is not visible and cannot receive focus
                    await expect(desktopBtn).toBeHidden();
                    await expect(mobileBtn).toBeVisible();
                    await mobileBtn.focus();
                    await expect(mobileBtn).toBeFocused();

                    // Exactly one visible primary action exists in DOM
                    const visibleButtonsMobile = page.locator('button:visible');
                    expect(await visibleButtonsMobile.count()).toBe(1);
                });
            }

            test('MOB-023 Category A (Desktop >= 768px): Desktop table visible, mobile cards hidden', async ({ page }) => {
                await page.setViewportSize({ width: 1440, height: 900 });
                await page.setContent(wrapHtml(referralsHtml, 'dark'));

                const desktopTable = page.locator('.referrals-desktop-view');
                const mobileCards = page.locator('.referrals-mobile-cards');

                await expect(desktopTable).not.toHaveCSS('display', 'none');
                await expect(mobileCards).toHaveCSS('display', 'none');
            });

            test('MOB-023 Category A Audit Log (Desktop 1440x900): Desktop table visible, mobile cards hidden', async ({ page }) => {
                await page.setViewportSize({ width: 1440, height: 900 });
                await page.setContent(wrapHtml(auditLogHtml, 'dark'));

                const desktopView = page.locator('.audit-log-desktop-view');
                const mobileCards = page.locator('.audit-log-mobile-cards');

                await expect(desktopView).not.toHaveCSS('display', 'none');
                await expect(mobileCards).toHaveCSS('display', 'none');
            });

            test('MOB-023 Accessible Duplicate-Content Contract (Desktop 1440x900): Hidden mobile action not focusable', async ({ page }) => {
                await page.setViewportSize({ width: 1440, height: 900 });
                await page.setContent(wrapHtml(referralsHtml, 'dark'));

                const desktopBtn = page.locator('.referrals-desktop-view button');
                const mobileBtn = page.locator('.referrals-mobile-cards button');

                await expect(mobileBtn).toBeHidden();
                await expect(desktopBtn).toBeVisible();
                await desktopBtn.focus();
                await expect(desktopBtn).toBeFocused();

                const visibleButtonsDesktop = page.locator('button:visible');
                expect(await visibleButtonsDesktop.count()).toBe(1);
            });
        });

        // Category B: Comparison Matrices (Horizontal scroll, sticky column, scroll hint)
        test.describe('Category B: Comparison Matrices', () => {
            const matrixHtml = `
            <div class="data-table-scroll table-matrix-wrapper" role="region" aria-label="Section Academic Overview Table" tabindex="0">
                <span class="table-scroll-hint" aria-hidden="true">Scroll horizontally to view all columns &rarr;</span>
                <table class="table-matrix data-table" style="min-width: 800px; width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: var(--table-header-bg);">
                            <th scope="col" class="table-col--sticky" style="padding: 12px 16px; text-align: left; width: 180px;">Student Name</th>
                            <th scope="col" style="padding: 12px; text-align: center;">Prelim</th>
                            <th scope="col" style="padding: 12px; text-align: center;">Midterm</th>
                            <th scope="col" style="padding: 12px; text-align: center;">Pre-Final</th>
                            <th scope="col" style="padding: 12px; text-align: center;">Current Avg</th>
                            <th scope="col" style="padding: 12px; text-align: center;">Subject Risk</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td class="table-col--sticky" style="font-weight: 600; padding: 12px 16px;">Santos, Maria Clara</td>
                            <td style="text-align: center; padding: 12px;">88%</td>
                            <td style="text-align: center; padding: 12px;">92%</td>
                            <td style="text-align: center; padding: 12px;">90%</td>
                            <td style="text-align: center; padding: 12px;">1.75</td>
                            <td style="text-align: center; padding: 12px;"><span class="badge badge--low">Low</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>`;

            test('MOB-023 Category B: Horizontal scroll container, tabindex, hint, and sticky column', async ({ page }) => {
                await page.setViewportSize({ width: 390, height: 844 });
                await page.setContent(wrapHtml(matrixHtml, 'dark'));

                const wrapper = page.locator('.table-matrix-wrapper');
                await expect(wrapper).toHaveAttribute('tabindex', '0');
                await expect(wrapper).toHaveAttribute('role', 'region');

                // Check scroll hint visibility on mobile
                const hint = page.locator('.table-scroll-hint');
                await expect(hint).toBeVisible();

                // Sticky column position and opaque background
                const stickyHeader = page.locator('th.table-col--sticky');
                await expect(stickyHeader).toHaveCSS('position', 'sticky');
                await expect(stickyHeader).toHaveCSS('left', '0px');

                const stickyCell = page.locator('td.table-col--sticky');
                await expect(stickyCell).toHaveCSS('position', 'sticky');
                await expect(stickyCell).toHaveCSS('left', '0px');

                // Scroll horizontally and verify sticky column remains anchored
                await wrapper.evaluate(el => el.scrollLeft = 200);
                const cellBox = await stickyCell.boundingBox();
                expect(cellBox.x).toBeLessThan(100); // Stays pinned to left edge
            });
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MOB-024: Support Workflow Hierarchy & Badges
    // ─────────────────────────────────────────────────────────────────────────
    test.describe('MOB-024: Support Workflow Hierarchy & Badges', () => {
        const badgeHtml = `
        <div class="referral-card" style="display:flex; flex-direction:column; gap:12px; max-width:200px;">
            <div><span class="badge badge--high">High Risk Indicator</span></div>
            <div><span class="badge badge--warning">Needs Action Required</span></div>
            <div><span class="purpose-pill pill-blue">Student Grade Concern</span></div>
        </div>`;

        test('MOB-024 Badges have nowrap and inline-flex display', async ({ page }) => {
            await page.setViewportSize({ width: 360, height: 800 });
            await page.setContent(wrapHtml(badgeHtml, 'dark'));

            const badges = page.locator('.badge, .purpose-pill');
            const count = await badges.count();
            expect(count).toBe(3);

            for (let i = 0; i < count; i++) {
                const b = badges.nth(i);
                await expect(b).toHaveCSS('white-space', 'nowrap');
                await expect(b).toHaveCSS('display', 'inline-flex');
            }
        });
    });

    // ─────────────────────────────────────────────────────────────────────────
    // MOB-025: Faculty Assigned Classes Mobile Layout
    // ─────────────────────────────────────────────────────────────────────────
    test.describe('MOB-025: Faculty Assigned Classes Mobile Layout', () => {
        const classPreviewHtml = `
        <div style="display: flex; flex-direction: column; gap: 12px;">
            <a href="grades.php?class=1_BSIT%203-A" style="text-decoration: none; color: inherit; display: block;" aria-label="Submit term grades for CC101 — Computer Programming section BSIT 3-A">
                <div class="home-class-preview">
                    <div class="home-class-preview__main">
                        <span class="home-class-preview__title" title="CC101 — Computer Programming and Algorithmic Thinking">CC101 — Computer Programming and Algorithmic Thinking</span>
                        <span class="home-class-preview__section">— BSIT 3-A</span>
                    </div>
                    <div class="home-class-preview__meta">
                        <span class="home-class-preview__students">35 students</span>
                        <span class="home-class-preview__badge" style="color: var(--risk-low);">100% Prelim encoded</span>
                    </div>
                </div>
            </a>
        </div>`;

        for (const [w, h] of [[390, 844], [393, 851]]) {
            test(`MOB-025 Mobile (${w}x${h}): Vertical stack, title wrapping, no section/student collision, deep link intact`, async ({ page }) => {
                await page.setViewportSize({ width: w, height: h });
                await page.setContent(wrapHtml(classPreviewHtml, 'dark'));

                const preview = page.locator('.home-class-preview');
                await expect(preview).toHaveCSS('flex-direction', 'column');

                // Check title wraps without ellipsis clipping
                const title = page.locator('.home-class-preview__title');
                await expect(title).toHaveCSS('white-space', 'normal');
                await expect(title).toHaveCSS('overflow', 'visible');

                // Check section and student count do not collide
                const section = page.locator('.home-class-preview__section');
                const students = page.locator('.home-class-preview__students');
                const boxSection = await section.boundingBox();
                const boxStudents = await students.boundingBox();
                // Students is in __meta below __main (containing section)
                expect(boxStudents.y).toBeGreaterThanOrEqual(boxSection.y + boxSection.height - 2);

                // Check meta spans full width
                const meta = page.locator('.home-class-preview__meta');
                await expect(meta).toHaveCSS('justify-content', 'space-between');

                // Check link href contains grades.php?class= and deep link remains intact
                const link = page.locator('a[href*="grades.php?class="]');
                await expect(link).toBeVisible();
                expect(await link.getAttribute('href')).toBe('grades.php?class=1_BSIT%203-A');
            });
        }

        test('MOB-025 Desktop (1440x900): Horizontal row layout', async ({ page }) => {
            await page.setViewportSize({ width: 1440, height: 900 });
            await page.setContent(wrapHtml(classPreviewHtml, 'dark'));

            const preview = page.locator('.home-class-preview');
            await expect(preview).toHaveCSS('flex-direction', 'row');

            const title = page.locator('.home-class-preview__title');
            await expect(title).toHaveCSS('white-space', 'nowrap');
            await expect(title).toHaveCSS('text-overflow', 'ellipsis');
        });
    });
});
