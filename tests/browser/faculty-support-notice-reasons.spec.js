import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { execFileSync } from 'node:child_process';
import process from 'node:process';
import { test, expect } from '@playwright/test';

const feedbackPhpSource = readFileSync(resolve('faculty/feedback.php'), 'utf-8');

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

        test('feedback.php viewNoticeModal contains dedicated reason display container', () => {
            expect(feedbackPhpSource).toContain('id="viewNoticeModal"');
            expect(feedbackPhpSource).toContain('id="viewNoticeReasonWrap"');
            expect(feedbackPhpSource).toContain('id="viewNoticeReason"');
            expect(feedbackPhpSource).toContain('id="viewNoticeText"');
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
                        . '<button id="btn-view-with-reason" onclick="openViewNoticeModal(\\'Please attend office hours.\\', \\'Low recorded assessment score\\')">View Notice With Reason</button>'
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

        test('View Notice Modal renders primary reason when present', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const modal = page.locator('#viewNoticeModal');
            await expect(modal).not.toBeVisible();

            // Trigger view notice with reason
            await page.locator('#btn-view-with-reason').click();
            await expect(modal).toBeVisible();

            const reasonWrap = page.locator('#viewNoticeReasonWrap');
            const reasonEl = page.locator('#viewNoticeReason');
            const textEl = page.locator('#viewNoticeText');

            await expect(reasonWrap).toBeVisible();
            await expect(reasonEl).toHaveText('Low recorded assessment score');
            await expect(textEl).toHaveText('Please attend office hours.');

            // Close modal
            await modal.locator('button:has-text("Close")').click();
            await expect(modal).not.toBeVisible();
        });

        test('View Notice Modal safely hides reason container for legacy records (null reason)', async ({ page }) => {
            const html = renderModalsHtml();
            await page.setContent(html);

            const modal = page.locator('#viewNoticeModal');
            await expect(modal).not.toBeVisible();

            // Trigger view notice legacy (null reason)
            await page.locator('#btn-view-legacy').click();
            await expect(modal).toBeVisible();

            const reasonWrap = page.locator('#viewNoticeReasonWrap');
            const textEl = page.locator('#viewNoticeText');

            await expect(reasonWrap).not.toBeVisible();
            await expect(textEl).toHaveText('Legacy notice without reason.');

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
});
