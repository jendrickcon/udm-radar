import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';

// Generated markup and real local enhancement scripts; no application server or database.
test('empty trend explanation is visible with all disclosures closed', async ({page}) => {
    await page.setContent(`<main><div class="trend-chart"><canvas id="semGwaChart"></canvas></div>
        <p id="trend-chart-status" role="status"></p><details><summary>Exact evidence</summary><table><tr><td>Unavailable</td></tr></table></details>
        <script id="student-trend-data" type="application/json">[{"zone":"estimate","value":null}]</script></main>`);
    await page.addScriptTag({path:'assets/js/student-performance-trend.js'});
    await expect(page.getByRole('status')).toBeVisible();
    await expect(page.getByRole('status')).toContainText('No chart is available because');
    await expect(page.locator('details')).not.toHaveAttribute('open');
    await expect(page.locator('canvas')).toBeHidden();
});

for (const [width,height] of [[1440,900],[1280,600],[1024,768]]) for (const theme of ['light','dark']) {
    test(`generated planning selection ${width}x${height} ${theme}`, async ({page}) => {
        await page.setViewportSize({width,height});
        const html=execFileSync('C:/xampp/php/php.exe',['tests/unit/pef_cc_print_contract_test.php','--render','manual-preview'],{encoding:'utf8'});
        // Prevent remote dependencies and application requests; serve actual repository assets.
        await page.route('**/*', async route => {
            const path=new URL(route.request().url()).pathname.split('/udm-radar/')[1];
            if (path && /^assets\/[\w./-]+\.(css|js)$/.test(path)) {
                await route.fulfill({body:readFileSync(path),contentType:path.endsWith('.css')?'text/css':'application/javascript'});
            } else await route.abort();
        });
        await page.setContent(html.replace('<head>','<head><base href="http://fixture.invalid/udm-radar/student/">'));
        await page.locator('html').evaluate((el,theme)=>el.dataset.theme=theme,theme);
        await page.addScriptTag({content:readFileSync('assets/js/pef-cc.js','utf8')});
        const boxes=page.locator('#pef-cc-subjects input[type=checkbox]');
        await expect(boxes.filter({visible:true}).first()).toBeVisible();
        expect(await boxes.evaluateAll(inputs=>inputs.filter(input=>input.checked).map(input=>input.value))).toEqual(['1','2']);
        await boxes.nth(2).focus();
        await page.keyboard.press('Space');
        await expect(boxes.nth(2)).toBeChecked();
        await expect(page.getByTestId('proposed-count')).toHaveText('3');
        expect(await boxes.nth(2).evaluate(el=>getComputedStyle(el.closest('label')).outlineStyle)).not.toBe('none');
        await page.keyboard.press('Space');
        await expect(boxes.nth(2)).not.toBeChecked();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    });

    test(`student trend header and standardized disclosures ${width}x${height} ${theme}`, async ({page}) => {
        await page.setViewportSize({width,height});
        const css = readFileSync('assets/css/student-performance-trend.css', 'utf8');
        const fixtureHtml = `<!DOCTYPE html>
<html lang="en" data-theme="${theme}">
<head>
    <meta charset="utf-8">
    <title>Trend Fixture</title>
    <style>
        :root {
            --bg-color: #f8fafc; --card-bg: #ffffff; --border-color: #e2e8f0;
            --text-dark: #0f172a; --text-gray: #64748b; --accent-blue: #2563eb;
        }
        [data-theme="dark"] {
            --bg-color: #0f172a; --card-bg: #1e293b; --border-color: #334155;
            --text-dark: #f8fafc; --text-gray: #94a3b8; --accent-blue: #38bdf8;
        }
        body { margin: 0; padding: 20px; background: var(--bg-color); font-family: sans-serif; color: var(--text-dark); }
        ${css}
    </style>
</head>
<body class="student-performance-trend">
    <div class="stat-grid kpi-grid--four">
        <div class="stat-card kpi-card--growth">
            <h2>Growth</h2>
            <h2 class="growth-kpi-value" style="color: #0d9488; font-size: clamp(1.45rem, 1.8vw, 1.95rem); font-weight: 700; margin: 0; line-height: 1.15; overflow-wrap: normal;">
                +0.24 <span class="growth-unit" style="font-size: 0.92rem; font-weight: 600; color: var(--text-gray); display: inline-block; white-space: nowrap;">GWA points</span>
            </h2>
            <p class="trend-card-sub">+7.74% from previous comparable term</p>
        </div>
    </div>
    <section class="card trend-chart-card">
        <div class="trend-chart-header">
            <div class="trend-chart-title-area">
                <h2 id="trend-chart-heading">Your performance over time</h2>
                <p id="trend-summary" class="trend-chart-subtitle">Recorded results, current progress, and estimate.</p>
                <ul class="trend-series-legend" aria-label="Evidence types">
                    <li><span class="legend-marker legend-marker--circle" aria-hidden="true"></span> Completed GWA</li>
                    <li><span class="legend-marker legend-marker--diamond" aria-hidden="true"></span> Estimated GWA</li>
                </ul>
            </div>
            <div class="trend-honors-header-block" aria-label="Academic distinction reference thresholds">
                <p class="trend-honors-disclaimer">
                    <span class="trend-disclaimer-line">Honors guides are reference thresholds.</span>
                    <span class="trend-disclaimer-line">Official eligibility remains subject to institutional review.</span>
                </p>
                <div class="trend-honors-guide-row">
                    <span class="trend-guide-item"><span class="trend-guide-swatch trend-guide-0"></span> Dean’s List / Cum Laude <strong>3.25</strong></span>
                    <span class="trend-guide-item"><span class="trend-guide-swatch trend-guide-1"></span> Magna Cum Laude <strong>3.50</strong></span>
                    <span class="trend-guide-item"><span class="trend-guide-swatch trend-guide-2"></span> Summa Cum Laude <strong>3.75</strong></span>
                </div>
            </div>
        </div>
        <div class="trend-chart">
            <canvas id="semGwaChart"></canvas>
        </div>
    </section>
    <div class="card trend-progress-card">
        <div class="trend-progress-header">
            <h3>Current Term Assessment Progress</h3>
            <span class="trend-progress-term">SY 2026-2027 · Semester 1</span>
        </div>
        <div class="trend-stages-strip">
            <div class="trend-stage-item trend-stage-item--latest">
                <div class="trend-stage-head">
                    <span class="trend-stage-title">Preliminary</span>
                    <span class="trend-latest-badge">Latest recorded</span>
                </div>
                <div class="trend-stage-value">87.55%</div>
                <div class="trend-stage-status">Recorded</div>
            </div>
            <div class="trend-stage-item">
                <div class="trend-stage-head">
                    <span class="trend-stage-title">Midterm</span>
                </div>
                <div class="trend-stage-value trend-stage-value--pending">Not recorded</div>
                <div class="trend-stage-status">Assessment pending</div>
            </div>
            <div class="trend-stage-item">
                <div class="trend-stage-head">
                    <span class="trend-stage-title">Pre-Final</span>
                </div>
                <div class="trend-stage-value trend-stage-value--pending">Not recorded</div>
                <div class="trend-stage-status">Assessment pending</div>
            </div>
        </div>
    </div>
    <details id="trend-evidence" class="trend-disclosure portal-info-disclosure">
        <summary class="trend-disclosure-summary trend-evidence-toggle">
            <svg class="trend-disclosure-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><line x1="3" y1="9" x2="21" y2="9"></line><line x1="3" y1="15" x2="21" y2="15"></line><line x1="9" y1="3" x2="9" y2="21"></line></svg>
            <span class="trend-disclosure-title">View exact values</span>
        </summary>
        <div class="trend-disclosure-content">
            <div class="trend-table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th scope="col">Academic period</th>
                            <th scope="col">Result type</th>
                            <th scope="col">Value</th>
                            <th scope="col">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>2024-2025 · Semester 1</td><td>Completed-term GWA</td><td>3.00</td><td><span class="status-pill status-pill-success">Recorded</span></td></tr>
                        <tr><td>2024-2025 · Semester 2</td><td>Completed-term GWA</td><td>3.24</td><td><span class="status-pill status-pill-success">Recorded</span></td></tr>
                        <tr class="trend-current-group-row"><td>2026-2027 · Semester 1</td><td>Preliminary progress</td><td>87.55%</td><td><span class="status-pill status-pill-success">Recorded</span></td></tr>
                        <tr class="trend-current-group-row"><td><span class="sr-only">2026-2027 · Semester 1</span></td><td>Estimated GWA</td><td>2.15</td><td><span class="status-pill status-pill-warning">Provisional</span></td></tr>
                    </tbody>
                </table>
            </div>
            <p class="trend-table-note">Completed-term GWA uses finalized results. Current progress and estimated GWA are shown separately.</p>
        </div>
    </details>
    <details class="trend-disclosure portal-info-disclosure">
        <summary class="trend-disclosure-summary">
            <svg class="trend-disclosure-icon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"></path><line x1="12" y1="17" x2="12.01" y2="17"></line></svg>
            <span class="trend-disclosure-title">About these results and reference guides</span>
        </summary>
        <div class="trend-disclosure-content">
            <ul>
                <li>Honors guides are reference thresholds; official eligibility requires institutional review.</li>
                <li>Estimates provide provisional decision support and do not replace official registrar evaluations.</li>
            </ul>
        </div>
    </details>
</body>
</html>`;
        await page.setContent(fixtureHtml);

        // Verification: Growth card fits without overflow
        const growthCard = page.locator('.kpi-card--growth');
        const growthValue = page.locator('.growth-kpi-value');
        const cardBox = await growthCard.boundingBox();
        const valueBox = await growthValue.boundingBox();
        expect(valueBox.x + valueBox.width).toBeLessThanOrEqual(cardBox.x + cardBox.width + 1);

        // Verification: Standardized disclosures
        const summaries = page.locator('.trend-disclosure summary');
        await expect(summaries).toHaveCount(2);
        const sumBox0 = await summaries.nth(0).boundingBox();
        const sumBox1 = await summaries.nth(1).boundingBox();
        expect(Math.abs(sumBox0.height - sumBox1.height)).toBeLessThanOrEqual(2);
        expect(await page.locator('.portal-info-disclosure').count()).toBe(2);

        // Verification: Reversed legend hierarchy and disclaimer lines
        const seriesLegendInTitleArea = page.locator('.trend-chart-title-area .trend-series-legend');
        await expect(seriesLegendInTitleArea).toBeVisible();
        const disclaimerLines = page.locator('.trend-honors-disclaimer .trend-disclaimer-line');
        await expect(disclaimerLines).toHaveCount(2);

        // Verification: No nested details/summaries inside table
        expect(await page.locator('table details').count()).toBe(0);
        expect(await page.locator('table summary').count()).toBe(0);

        // Verification: Opening disclosure reveals all 4 rows
        await summaries.first().click();
        await expect(page.locator('#trend-evidence')).toHaveAttribute('open', '');
        const rows = page.locator('#trend-evidence table tbody tr');
        await expect(rows).toHaveCount(4);
        for (let i = 0; i < 4; i++) {
            await expect(rows.nth(i)).toBeVisible();
        }

        // Verification: Native marker suppressed
        const markerStyle = await summaries.first().evaluate(el => getComputedStyle(el).listStyleType);
        expect(markerStyle).toBe('none');

        // Verification: Plot border and compact styling
        expect(await page.locator('.trend-chart').evaluate(el => getComputedStyle(el).borderStyle)).toBe('solid');
        expect(await page.locator('.trend-chart').evaluate(el => getComputedStyle(el).borderRadius)).toBe('8px');

        // Verification: Compact progress card and latest stage badge
        await expect(page.locator('.trend-progress-card')).toBeVisible();
        await expect(page.locator('.trend-progress-header h3')).toHaveText('Current Term Assessment Progress');
        await expect(page.locator('.trend-progress-term')).toHaveText('SY 2026-2027 · Semester 1');
        await expect(page.locator('.trend-latest-badge')).toBeVisible();
        await expect(page.locator('.trend-latest-badge')).toHaveText('Latest recorded');
        expect(await page.locator('.trend-stage-item').count()).toBe(3);

        // Verification: No horizontal overflow
        expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth)).toBe(true);
    });
}
