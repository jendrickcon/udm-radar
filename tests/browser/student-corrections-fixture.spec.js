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
                    <li><span class="legend-marker legend-marker--circle-outline" aria-hidden="true"></span> Current assessment point</li>
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
                        <tr class="trend-current-group-row"><td>2026-2027 · Semester 1</td><td>Preliminary progress</td><td>92.94%</td><td><span class="status-pill status-pill-success">Recorded</span></td></tr>
                        <tr class="trend-current-group-row"><td><span class="sr-only">2026-2027 · Semester 1</span></td><td>Current-term assessment point</td><td>3.25</td><td><span class="status-pill status-pill-teal">Converted · not completed GWA</span></td></tr>
                        <tr class="trend-current-group-row"><td><span class="sr-only">2026-2027 · Semester 1</span></td><td>Estimated GWA</td><td>3.05</td><td><span class="status-pill status-pill-warning">Provisional</span></td></tr>
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

        // Verification: Opening disclosure reveals all 5 rows
        await summaries.first().click();
        await expect(page.locator('#trend-evidence')).toHaveAttribute('open', '');
        const rows = page.locator('#trend-evidence table tbody tr');
        await expect(rows).toHaveCount(5);
        for (let i = 0; i < 5; i++) {
            await expect(rows.nth(i)).toBeVisible();
        }

        // Verification: Native marker suppressed
        const markerStyle = await summaries.first().evaluate(el => getComputedStyle(el).listStyleType);
        expect(markerStyle).toBe('none');

        // Verification: Plot border and compact styling
        expect(await page.locator('.trend-chart').evaluate(el => getComputedStyle(el).borderStyle)).toBe('solid');
        expect(await page.locator('.trend-chart').evaluate(el => getComputedStyle(el).borderRadius)).toBe('8px');

        // Verification: Callout is removed in favor of single converted assessment endpoint
        await expect(page.locator('#trend-current-callout')).toHaveCount(0);

        // Verification: Three-item legend with outlined circle
        const legendItems = page.locator('.trend-series-legend li');
        await expect(legendItems).toHaveCount(3);
        await expect(legendItems.nth(0)).toContainText('Completed GWA');
        await expect(legendItems.nth(1)).toContainText('Current assessment point');
        await expect(legendItems.nth(2)).toContainText('Estimated GWA');
        await expect(page.locator('.legend-marker--circle-outline')).toBeVisible();

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

test('three-region layout geometry and scale separation contract', async ({page}) => {
    const fixtureHtml = `<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>Three Region Test</title>
</head>
<body class="student-performance-trend">
    <div class="trend-chart" style="position: relative; width: 800px; height: 380px;">
        <canvas id="semGwaChart" width="800" height="380"></canvas>
    </div>
    <p id="trend-chart-status"></p>
    <script id="student-trend-data" type="application/json">[
        {"key":"2023-2024|1","zone":"history","type":"Completed-term GWA","value":3.02,"connect":true},
        {"key":"2023-2024|2","zone":"history","type":"Completed-term GWA","value":3.15,"connect":true},
        {"key":"2024-2025|1","zone":"history","type":"Completed-term GWA","value":3.22,"connect":true},
        {"key":"2024-2025|2","zone":"history","type":"Completed-term GWA","value":3.28,"connect":true},
        {"key":"2025-2026|1","zone":"history","type":"Completed-term GWA","value":3.43,"connect":true},
        {"key":"2025-2026|2","zone":"history","type":"Completed-term GWA","value":3.34,"connect":false},
        {"zone":"estimate","type":"Estimated semester GWA","value":3.05}
    ]</script>
    <script id="student-trend-current-progress" type="application/json">{"has_recorded_stage":true,"latest_stage_field":"prelim","latest_stage_label":"Preliminary","primary_value":"92.94%","percentage":92.94,"converted_point":3.25,"stage_based_label":"Preliminary-based","subtitle":"Preliminary average","coverage_text":"6 of 6 subjects recorded","earlier_context":null}</script>
    <script id="student-trend-active-term" type="application/json">{"school_year":"2026-2027","semester":1}</script>
    <script id="student-trend-guides" type="application/json">[
        {"names":["Dean's List","Cum Laude"],"value":3.25,"style":0},
        {"names":["Magna Cum Laude"],"value":3.50,"style":1},
        {"names":["Summa Cum Laude"],"value":3.75,"style":2}
    ]</script>
</body>
</html>`;
    await page.setContent(fixtureHtml);
    await page.evaluate(() => {
        window.chartInstances = [];
        window.Chart = function(canvas, config) {
            this.canvas = canvas;
            this.config = config;
            this.data = config.data;
            this.options = config.options;
            this.chartArea = { top: 26, right: 752, bottom: 320, left: 58, width: 694, height: 294 };
            this.scales = {
                x: {
                    getPixelForValue: (v) => 58 + (v / 100) * 694
                },
                y: {
                    getPixelForValue: (v) => 320 - ((v - 1) / 3) * 294
                }
            };
            this.ctx = {
                save: () => {}, restore: () => {}, fillText: () => {}, strokeText: () => {},
                beginPath: () => {}, moveTo: () => {}, lineTo: () => {}, stroke: () => {},
                fillRect: () => {}, measureText: () => ({ width: 40 })
            };
            this.isDatasetVisible = () => true;
            this.setActiveElements = () => {};
            this.tooltip = { setActiveElements: () => {} };
            this.update = () => {};
            this.destroy = () => {};
            window.chartInstances.push(this);
            if (config.plugins && config.plugins[0] && config.plugins[0].afterDatasetsDraw) {
                config.plugins[0].afterDatasetsDraw(this);
            }
        };
        window.Chart.getChart = () => window.chartInstances[0];
    });
    await page.addScriptTag({ path: 'assets/js/student-performance-trend.js' });

    const chartData = await page.evaluate(() => {
        const chart = window.chartInstances[0];
        const canvas = document.getElementById('semGwaChart');
        return {
            histPoints: chart.data.datasets[0].data,
            currentAssessmentPoints: chart.data.datasets[1].data,
            estimatePoints: chart.data.datasets[2].data,
            currentConnector: chart.data.datasets[3].data,
            estimateConnector: chart.data.datasets[4].data,
            ariaLabel: canvas.getAttribute('aria-label'),
            histWidthPct: canvas.dataset.histWidthPct,
            currentWidthPct: canvas.dataset.currentWidthPct,
            estWidthPct: canvas.dataset.estWidthPct,
            dividers: canvas.dataset.dividers,
            currentAssessmentPoint: canvas.dataset.currentAssessmentPoint
        };
    });

    // Verification: Three region layout geometry
    expect(Number(chartData.histWidthPct)).toBe(78);
    expect(Number(chartData.currentWidthPct)).toBe(10);
    expect(Number(chartData.estWidthPct)).toBe(12);
    expect(Number(chartData.dividers)).toBe(2);
    expect(Number(chartData.currentAssessmentPoint)).toBe(3.25);

    // Verification: Historical points fit strictly inside Region 1 (0 to 78)
    expect(chartData.histPoints.length).toBe(6);
    expect(chartData.histPoints[0].x).toBeGreaterThan(0); // does not touch y-axis
    expect(chartData.histPoints[chartData.histPoints.length - 1].x).toBeLessThan(78); // does not touch Divider 1

    // Verification: Current assessment point is centered in Region 2 (78 to 88) at x = 83, y = 3.25
    expect(chartData.currentAssessmentPoints.length).toBe(1);
    expect(chartData.currentAssessmentPoints[0].x).toBe(83);
    expect(chartData.currentAssessmentPoints[0].y).toBe(3.25);

    // Verification: Estimate point is centered in Region 3 (88 to 100) at x = 94, y = 3.05
    expect(chartData.estimatePoints.length).toBe(1);
    expect(chartData.estimatePoints[0].x).toBe(94);
    expect(chartData.estimatePoints[0].y).toBe(3.05);

    // Verification: Independent branching dashed connectors from last completed GWA (3.34 at x = 72)
    // Connector 1: to Current assessment point (3.25)
    expect(chartData.currentConnector.length).toBe(2);
    expect(chartData.currentConnector[0].y).toBe(3.34);
    expect(chartData.currentConnector[1].y).toBe(3.25);

    // Connector 2: to Estimated GWA (3.05)
    expect(chartData.estimateConnector.length).toBe(2);
    expect(chartData.estimateConnector[0].y).toBe(3.34);
    expect(chartData.estimateConnector[1].y).toBe(3.05);

    // Verification: Raw percentage 92.94% is NOT in any GWA dataset
    const allGwaValues = [
        ...chartData.histPoints,
        ...chartData.currentAssessmentPoints,
        ...chartData.estimatePoints,
        ...chartData.currentConnector,
        ...chartData.estimateConnector
    ].map(p => p.y);
    expect(allGwaValues.includes(92.94)).toBe(false);

    // Verification: Canvas accessible description distinguishes all three parts
    expect(chartData.ariaLabel).toContain('Completed GWA values range from 3.02 to 3.43 across 6 completed semesters');
    expect(chartData.ariaLabel).toContain('Current-term assessment point 3.25, converted from the recorded Preliminary average of 92.94 percent');
    expect(chartData.ariaLabel).toContain('The provisional estimated GWA is 3.05');
});
