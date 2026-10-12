import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';
import { test, expect } from '@playwright/test';

const feedbackPhpSource = readFileSync(resolve('faculty/feedback.php'), 'utf-8');
const studentPhpSource = readFileSync(resolve('student/feedback.php'), 'utf-8');

test.describe('FACULTY-WP1A: Structured Academic-Support Reason Capture UI & Modals', () => {

    test.describe('Part 1: Template Structure & Contract Inspection', () => {

        test('feedback.php contains modalReasonCode select with required attribute', () => {
            expect(feedbackPhpSource).toContain('id="modalReasonCode"');
            expect(feedbackPhpSource).toContain('name="reason_code"');
            expect(feedbackPhpSource).toMatch(/<select[^>]*name=["']reason_code["'][^>]*id=["']modalReasonCode["'][^>]*required/i);
        });

        test('feedback.php renders reason options from canonical getSupportReasons()', () => {
            expect(feedbackPhpSource).toContain('getSupportReasons()');
            expect(feedbackPhpSource).toContain('Select a reason...');
        });

        test('feedback.php modalMessageToStudent textarea enforces minlength="10" and maxlength="2000"', () => {
            expect(feedbackPhpSource).toContain('id="modalMessageToStudent"');
            expect(feedbackPhpSource).toContain('name="message_to_student"');
            expect(feedbackPhpSource).toMatch(/minlength=["']10["']/);
            expect(feedbackPhpSource).toMatch(/maxlength=["']2000["']/);
            expect(feedbackPhpSource).toContain('Minimum 10 characters, maximum 2,000 characters');
        });

        test('feedback.php does NOT contain internal faculty notes or student-hidden fields', () => {
            expect(feedbackPhpSource).not.toContain('faculty_notes');
            expect(feedbackPhpSource).not.toContain('internal_notes');
            expect(feedbackPhpSource).not.toContain('private_notes');
        });

        test('feedback.php viewNoticeModal contains timeline and dedicated reason display container', () => {
            expect(feedbackPhpSource).toContain('id="viewNoticeModal"');
            expect(feedbackPhpSource).toContain('id="viewNoticeTimelineWrap"');
            expect(feedbackPhpSource).toContain('id="viewNoticeIssuedAt"');
            expect(feedbackPhpSource).toContain('id="viewNoticeAcknowledgedAt"');
            expect(feedbackPhpSource).toContain('id="viewNoticeReasonWrap"');
            expect(feedbackPhpSource).toContain('id="viewNoticeReason"');
            expect(feedbackPhpSource).toContain('id="viewNoticeText"');

            // Timeline positioned after subtitle and before Primary Reason
            const subtitlePos = feedbackPhpSource.indexOf('This is the notice transmitted to the student.');
            const timelinePos = feedbackPhpSource.indexOf('id="viewNoticeTimelineWrap"');
            const reasonPos = feedbackPhpSource.indexOf('id="viewNoticeReasonWrap"');
            expect(subtitlePos).toBeLessThan(timelinePos);
            expect(timelinePos).toBeLessThan(reasonPos);
        });

        test('feedback.php and student/feedback.php tab badges enforce zero-suppression', () => {
            expect(feedbackPhpSource).toContain('.tab-badge');
            expect(feedbackPhpSource).toContain('$concernsCount > 0');
            expect(feedbackPhpSource).toContain('$referralsCount > 0');
            expect(studentPhpSource).toContain('.tab-badge');
            expect(studentPhpSource).toContain('$supportNoticesCount > 0');
        });

        test('feedback.php JS functions handle structured reasons cleanly', () => {
            expect(feedbackPhpSource).toContain('function openInterventionModal(');
            expect(feedbackPhpSource).toContain('function openViewNoticeModal(');
            expect(feedbackPhpSource).toContain('reasonSelect.selectedIndex = 0');
            expect(feedbackPhpSource).toContain('viewNoticeReasonWrap');
        });
    });

    test.describe('Part 2: Dynamic Browser DOM & Modal Interaction', () => {

        // Render isolated HTML page using PHP binary to extract exact markup from feedback.php
        const renderModalsHtml = () => {
            return execFileSync(
                process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
                ['-r', `
                    require_once 'config/constants.php';
                    $_SESSION = ['csrf_token' => 'test-csrf-token'];

                    $feedbackFile = file_get_contents('faculty/feedback.php');
                    // Extract HTML starting from interventionModal through scripts
                    $modalStart = strpos($feedbackFile, '<!-- Modal: Issue Notice -->');
                    $modalEnd = strpos($feedbackFile, '</script>', $modalStart);
                    $markup = substr($feedbackFile, $modalStart, ($modalEnd + 9) - $modalStart);
                    ob_start();
                    eval('?>' . $markup);
                    $rendered = ob_get_clean();

                    echo '<!doctype html><html lang="en" data-theme="light"><head><style>'
                        . ':root { --text-dark: #1e293b; --text-gray: #64748b; --accent-blue: #1e4db7; --risk-high: #dc2626; --border-color: #e2e8f0; --bg-color: #ffffff; }'
                        . '</style></head><body>'
                        . '<button id="btn-trigger" onclick="openInterventionModal(42, \\'Dela Cruz, Juan\\', \\'BSIT 3-1\\', \\'Web Development\\')">Open Notice Modal</button>'
                        . '<button id="btn-view-with-reason" onclick="openViewNoticeModal(\\'Please attend office hours.\\', \\'Low recorded assessment score\\', \\'October 13, 2026 at 12:28 AM\\', \\'Not yet acknowledged\\')">View Notice With Reason</button>'
                        . '<button id="btn-view-ack" onclick="openViewNoticeModal(\\'Please attend office hours.\\', \\'Low recorded assessment score\\', \\'October 13, 2026 at 12:28 AM\\', \\'October 13, 2026 at 9:14 AM\\')">View Notice Acknowledged</button>'
                        . '<button id="btn-view-legacy" onclick="openViewNoticeModal(\\'Legacy notice without reason.\\', null)">View Notice Legacy</button>'
                        . $rendered
                        . '</body></html>';
                `],
                { encoding: 'utf8', cwd: resolve('.') }
            );
        };

        test('Issue Notice Modal opens with 6 canonical options and required constraints', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const modal = page.locator('#interventionModal');
            await expect(modal).not.toBeVisible();

            // Trigger modal opening
            await page.locator('#btn-trigger').click();
            await expect(modal).toBeVisible();

            // Verify populated context info
            await expect(page.locator('#modalCaseId')).toHaveValue('42');
            await expect(page.locator('#modalStudentName')).toHaveText('Dela Cruz, Juan');
            await expect(page.locator('#modalSection')).toHaveText('BSIT 3-1');
            await expect(page.locator('#modalSubjTitle')).toHaveText('Web Development');

            // Verify reason dropdown has exact 6 canonical options plus 1 placeholder
            const reasonSelect = page.locator('#modalReasonCode');
            const options = reasonSelect.locator('option');
            await expect(options).toHaveCount(7);

            // Placeholder option is selected by default and disabled
            await expect(options.nth(0)).toHaveText('Select a reason...');
            await expect(options.nth(0)).toBeDisabled();
            await expect(reasonSelect).toHaveValue('');

            // Verify exact values and labels of the 6 canonical reasons
            const expectedOptions = [
                { value: 'low_assessment_score', label: 'Low recorded assessment score' },
                { value: 'missing_course_requirement', label: 'Missing course requirement or deliverable' },
                { value: 'performance_needs_review', label: 'Current subject performance needs review' },
                { value: 'incomplete_grade_data', label: 'Incomplete or provisional grade information' },
                { value: 'consultation_recommended', label: 'Faculty consultation recommended' },
                { value: 'other_academic_concern', label: 'Other Faculty-observed academic concern' },
            ];

            for (let i = 0; i < expectedOptions.length; i++) {
                const opt = options.nth(i + 1);
                await expect(opt).toHaveAttribute('value', expectedOptions[i].value);
                await expect(opt).toHaveText(expectedOptions[i].label);
            }

            // Message textarea attributes
            const textarea = page.locator('#modalMessageToStudent');
            await expect(textarea).toHaveAttribute('required', '');
            await expect(textarea).toHaveAttribute('minlength', '10');
            await expect(textarea).toHaveAttribute('maxlength', '2000');
            await expect(textarea).toHaveValue('');

            // Test selection works cleanly
            await reasonSelect.selectOption('performance_needs_review');
            await expect(reasonSelect).toHaveValue('performance_needs_review');

            // Test typing message works
            await textarea.fill('Please schedule a consultation session this Thursday to review coursework.');
            await expect(textarea).toHaveValue('Please schedule a consultation session this Thursday to review coursework.');

            // Cancel button closes modal
            await modal.locator('button:has-text("Cancel")').click();
            await expect(modal).not.toBeVisible();
        });

        test('View Notice Modal renders primary reason and timeline records', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const modal = page.locator('#viewNoticeModal');
            await expect(modal).not.toBeVisible();

            // 1. Trigger view notice with reason and unacknowledged timeline
            await page.locator('#btn-view-with-reason').click();
            await expect(modal).toBeVisible();

            const reasonWrap = page.locator('#viewNoticeReasonWrap');
            const reasonEl = page.locator('#viewNoticeReason');
            const textEl = page.locator('#viewNoticeText');
            const issuedEl = page.locator('#viewNoticeIssuedAt');
            const ackEl = page.locator('#viewNoticeAcknowledgedAt');

            await expect(reasonWrap).toBeVisible();
            await expect(reasonEl).toHaveText('Low recorded assessment score');
            await expect(textEl).toHaveText('Please attend office hours.');
            await expect(issuedEl).toHaveText('October 13, 2026 at 12:28 AM');
            await expect(ackEl).toHaveText('Not yet acknowledged');

            // Close modal
            await modal.locator('button:has-text("Close")').click();
            await expect(modal).not.toBeVisible();

            // 2. Trigger view notice with acknowledged timeline
            await page.locator('#btn-view-ack').click();
            await expect(modal).toBeVisible();
            await expect(issuedEl).toHaveText('October 13, 2026 at 12:28 AM');
            await expect(ackEl).toHaveText('October 13, 2026 at 9:14 AM');

            await modal.locator('button:has-text("Close")').click();
            await expect(modal).not.toBeVisible();
        });

        test('View Notice Modal safely provides fallbacks for legacy records (null reason/dates)', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const modal = page.locator('#viewNoticeModal');
            await expect(modal).not.toBeVisible();

            // Trigger view notice legacy (null reason, omitted dates)
            await page.locator('#btn-view-legacy').click();
            await expect(modal).toBeVisible();

            const reasonWrap = page.locator('#viewNoticeReasonWrap');
            const textEl = page.locator('#viewNoticeText');
            const issuedEl = page.locator('#viewNoticeIssuedAt');
            const ackEl = page.locator('#viewNoticeAcknowledgedAt');

            await expect(reasonWrap).not.toBeVisible();
            await expect(textEl).toHaveText('Legacy notice without reason.');
            await expect(issuedEl).toHaveText('Not recorded');
            await expect(ackEl).toHaveText('Not yet acknowledged');

            // Close modal
            await modal.locator('button:has-text("Close")').click();
            await expect(modal).not.toBeVisible();
        });

        test('Student-safe vocabulary: modals contain zero punitive or disciplinary terms', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const pageText = await page.innerText('body');
            const forbiddenTerms = [
                'strike',
                'probation',
                'dismissal',
                'penalty',
                'disciplinary',
                'fault',
                'guilty',
                'failing course',
            ];

            for (const term of forbiddenTerms) {
                expect(pageText.toLowerCase()).not.toContain(term);
            }
        });
    });

    test.describe('Part 3: Per-Tab Alert Badges Browser Rendering', () => {

        const renderFacultyTabsHtml = (concerns, referrals) => {
            return execFileSync(
                process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
                ['-r', `
                    require_once 'config/constants.php';
                    $concernsCount = ${concerns};
                    $referralsCount = ${referrals};
                    $feedbackFile = file_get_contents('faculty/feedback.php');
                    $barStart = strpos($feedbackFile, '<div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color);');
                    $barEnd = strpos($feedbackFile, '<!-- TAB 1:', $barStart);
                    $markup = substr($feedbackFile, $barStart, $barEnd - $barStart);
                    ob_start();
                    eval('?>' . $markup);
                    $rendered = ob_get_clean();
                    echo '<!doctype html><html lang="en"><head><style>'
                        . ':root { --risk-high: #dc2626; --border-color: #e2e8f0; --accent-blue: #1e4db7; --text-gray: #64748b; }'
                        . '.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 6px; font-size: 0.72rem; font-weight: 700; line-height: 1; border-radius: 9999px; background: var(--risk-high); color: #ffffff; margin-left: 6px; }'
                        . '</style></head><body>' . $rendered . '</body></html>';
                `],
                { encoding: 'utf8', cwd: resolve('.') }
            );
        };

        const renderStudentTabsHtml = (noticesCount) => {
            return execFileSync(
                process.env.PHP_BINARY || 'C:/xampp/php/php.exe',
                ['-r', `
                    require_once 'config/constants.php';
                    $supportNoticesCount = ${noticesCount};
                    $feedbackFile = file_get_contents('student/feedback.php');
                    $barStart = strpos($feedbackFile, '<div style="display: flex; gap: 8px; border-bottom: 1px solid var(--border-color);');
                    $barEnd = strpos($feedbackFile, '<!-- TAB 1:', $barStart);
                    $markup = substr($feedbackFile, $barStart, $barEnd - $barStart);
                    ob_start();
                    eval('?>' . $markup);
                    $rendered = ob_get_clean();
                    echo '<!doctype html><html lang="en"><head><style>'
                        . ':root { --risk-high: #dc2626; --border-color: #e2e8f0; --accent-blue: #1e4db7; --text-gray: #64748b; }'
                        . '.tab-badge { display: inline-flex; align-items: center; justify-content: center; min-width: 18px; height: 18px; padding: 0 6px; font-size: 0.72rem; font-weight: 700; line-height: 1; border-radius: 9999px; background: var(--risk-high); color: #ffffff; margin-left: 6px; }'
                        . '</style></head><body>' . $rendered . '</body></html>';
                `],
                { encoding: 'utf8', cwd: resolve('.') }
            );
        };

        test('Faculty tab buttons display numeric badges when counts > 0 and hide zero badges', async ({ page }) => {
            // Positive counts
            const htmlWithCounts = renderFacultyTabsHtml(2, 7);
            await page.setContent(htmlWithCounts);

            const inboxBadge = page.locator('#btn-inbox .tab-badge');
            const supportBadge = page.locator('#btn-support .tab-badge');
            const reportsBadge = page.locator('#btn-reports .tab-badge');

            await expect(inboxBadge).toHaveText('2');
            await expect(supportBadge).toHaveText('7');
            await expect(reportsBadge).toHaveCount(0);

            // Zero counts (zero suppression)
            const htmlZero = renderFacultyTabsHtml(0, 0);
            await page.setContent(htmlZero);

            await expect(page.locator('#btn-inbox .tab-badge')).toHaveCount(0);
            await expect(page.locator('#btn-support .tab-badge')).toHaveCount(0);
            await expect(page.locator('#btn-reports .tab-badge')).toHaveCount(0);
        });

        test('Student tab buttons display numeric badge on Academic Support Notices when count > 0', async ({ page }) => {
            // Positive count: 1 unacknowledged notice
            const htmlWithCount = renderStudentTabsHtml(1);
            await page.setContent(htmlWithCount);

            const supportBtn = page.locator('button.tab-btn:has-text("Academic Support Notices")');
            const ticketsBtn = page.locator('button.tab-btn:has-text("My Tickets")');

            await expect(supportBtn.locator('.tab-badge')).toHaveText('1');
            await expect(ticketsBtn.locator('.tab-badge')).toHaveCount(0);

            // Zero count: all notices acknowledged
            const htmlZero = renderStudentTabsHtml(0);
            await page.setContent(htmlZero);

            await expect(page.locator('button.tab-btn .tab-badge')).toHaveCount(0);
        });

        test('Large badge counts (99, 100, 125) render full numeric values cleanly without clipping across viewports and themes', async ({ page }) => {
            const viewports = [
                { width: 1440, height: 900 },
                { width: 900, height: 600 },
                { width: 768, height: 1024 },
                { width: 390, height: 844 },
            ];

            const htmlLarge = renderFacultyTabsHtml(100, 125);

            for (const vp of viewports) {
                await page.setViewportSize(vp);

                for (const theme of ['light', 'dark']) {
                    await page.setContent(htmlLarge);
                    await page.evaluate((t) => document.documentElement.setAttribute('data-theme', t), theme);

                    const inboxBadge = page.locator('#btn-inbox .tab-badge');
                    const supportBadge = page.locator('#btn-support .tab-badge');

                    // Assert full numeric text
                    await expect(inboxBadge).toHaveText('100');
                    await expect(supportBadge).toHaveText('125');

                    // Assert assistive technology accessible label contains exact count
                    await expect(inboxBadge).toHaveAttribute('aria-label', '100 actionable concerns');
                    await expect(supportBadge).toHaveAttribute('aria-label', '125 pending referrals');

                    // Verify geometry: badge is visible, has nonzero width/height, and does not clip
                    const inboxBox = await inboxBadge.boundingBox();
                    const supportBox = await supportBadge.boundingBox();

                    expect(inboxBox).not.toBeNull();
                    expect(supportBox).not.toBeNull();
                    expect(inboxBox.width).toBeGreaterThanOrEqual(18);
                    expect(supportBox.width).toBeGreaterThanOrEqual(18);
                }
            }

            // Also test 99
            const html99 = renderFacultyTabsHtml(99, 99);
            await page.setContent(html99);
            await expect(page.locator('#btn-inbox .tab-badge')).toHaveText('99');
            await expect(page.locator('#btn-support .tab-badge')).toHaveText('99');
        });
    });
});
