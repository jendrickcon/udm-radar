import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, sep } from 'node:path';

const root=process.cwd(),php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';
const render=()=>execFileSync(php,['tests/unit/admin_dashboard_interpretability_test.php','--render','overview'],{encoding:'utf8'});
async function open(page,theme='light',scenario='complete'){
 const errors=[],writes=[],transport={fail:false};page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(t=>localStorage.setItem('udmRadarDisplayPreferencesV1',JSON.stringify({version:1,theme:t,colorStyle:'ccs',motion:'reduce'})),theme);
 await page.route('**/*',route=>{
  const r=route.request(),u=new URL(r.url());
  if(!['GET','HEAD'].includes(r.method())){writes.push(r.method());return route.abort();}
  if(u.pathname==='/udm-radar/admin/analytics.php')return route.fulfill({contentType:'text/html',body:render()});
  if(u.pathname==='/udm-radar/api/academic_analytics.php'){
   if(transport.fail)return route.fulfill({status:503,body:'Unavailable'});
   const filters=Object.fromEntries(u.searchParams);delete filters.tab;
   const body=execFileSync(php,['tests/fixtures/admin_comparison_report.php',JSON.stringify(filters),scenario],{encoding:'utf8'});
   return route.fulfill({body,contentType:'application/json'});
  }
  if(u.hostname==='cdn.jsdelivr.net'&&process.env.ADMIN_CHART_ASSET)return route.fulfill({body:readFileSync(process.env.ADMIN_CHART_ASSET),contentType:'application/javascript'});
  if(u.pathname.startsWith('/udm-radar/assets/')){
   const file=resolve(root,u.pathname.slice('/udm-radar/'.length));
   if(file.startsWith(resolve(root,'assets')+sep)&&existsSync(file))return route.fulfill({body:readFileSync(file),contentType:file.endsWith('.js')?'application/javascript':file.endsWith('.css')?'text/css':'application/octet-stream'});
  }
  return route.abort();
 });
 await page.goto('http://comparison.invalid/udm-radar/admin/analytics.php?tab=comparisons');
 await expect(page.locator('#analytics-results')).toBeVisible();
 return {errors,writes,transport};
}
test('Comparisons starts with the visible six-dimension workspace',async({page})=>{
 await open(page);
 await expect(page.locator('#admin-comparisons')).toBeVisible();
 await expect(page.getByRole('group',{name:'Compare by',exact:true})).toBeVisible();
 await expect(page.locator('#admin-comparisons [data-dimension]')).toHaveCount(6);
 await expect(page.locator('#tab-panel-comparisons #trend-chart')).toHaveCount(0);
 await expect(page.locator('#other-comparisons-disclosure')).toHaveCount(0);
});
async function choose(page,id,value){
 const response=page.waitForResponse(r=>r.url().includes('/api/academic_analytics.php'));
 await page.locator('#ac-'+id).selectOption(value);await response;
 await expect(page.locator('#analytics-results')).toHaveAttribute('aria-busy','false');
}
async function faculty(page){
 await page.locator('[data-dimension="faculty_subject"]').click();
 await choose(page,'school_year','2026-2027');await choose(page,'semester','1');await choose(page,'subject','1');
}
for(const [width,height] of [[1440,900],[1280,600],[1024,768]])for(const theme of ['light','dark']){
 test(`Comparison task, validation, exact evidence and modes ${width} ${theme}`,async({page})=>{
  await page.setViewportSize({width,height});const {errors,writes}=await open(page,theme);
  await expect(page.locator('[data-dimension]')).toHaveCount(6);
  await page.locator('[data-dimension="faculty_subject"]').click();
  await page.getByRole('button',{name:'Compare groups',exact:true}).click();
  await expect(page.locator('#ac-school_year')).toBeFocused();
  await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);
  await choose(page,'school_year','2026-2027');await choose(page,'semester','1');await choose(page,'subject','1');
  await expect(page.locator('[data-comparison-chart]')).toBeVisible();
  await expect(page.locator('[data-comparison-summary]')).toContainText('Difference A − B: -0.50 recorded-grade points');

  // Verify conventional vertical bar chart elements
  await expect(page.locator('.ac-vertical-chart-card')).toBeVisible();
  await expect(page.locator('.ac-chart-stage')).toBeVisible();
  await expect(page.locator('.ac-chart-y-axis')).toBeVisible();
  await expect(page.locator('.ac-chart-baseline')).toBeVisible();
  expect(await page.locator('.ac-bar').evaluateAll(es=>es.map(e=>Number(e.dataset.value)))).toEqual([2.5,3]);
  await expect(page.locator('.ac-bar-val-badge').nth(0)).toHaveText('2.50');
  await expect(page.locator('.ac-bar-val-badge').nth(1)).toHaveText('3.00');

  // Test Display Mode: Side by side vs Overlay
  await expect(page.locator('.ac-chart-plot-area')).toHaveClass(/ac-plot-side-by-side/);
  await page.locator('[data-display-select="overlay"]').click();
  await expect(page.locator('.ac-chart-plot-area')).toHaveClass(/ac-plot-overlay/);
  await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-chart-display','overlay');
  await page.locator('[data-display-select="side-by-side"]').click();
  await expect(page.locator('.ac-chart-plot-area')).toHaveClass(/ac-plot-side-by-side/);

  // Test Group Highlighting
  await page.locator('[data-group-select="A"]').click();
  await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','A');
  await expect(page.locator('[data-group-select="A"]')).toHaveAttribute('aria-pressed','true');
  await expect(page.locator('#ac-highlight-announcement')).toHaveText('Group A highlighted.');
  await expect(page.locator('.ac-highlight-title')).toContainText('Group A');
  await expect(page.locator('.ac-relational-callout')).toContainText('0.50 recorded-grade points lower than Group B');

  await page.locator('[data-group-select="B"]').click();
  await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','B');
  await expect(page.locator('[data-group-select="B"]')).toHaveAttribute('aria-pressed','true');
  await expect(page.locator('#ac-highlight-announcement')).toHaveText('Group B highlighted.');
  await expect(page.locator('.ac-relational-callout')).toContainText('0.50 recorded-grade points higher than Group A');

  await page.locator('[data-group-select="all"]').click();
  await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','all');
  await expect(page.locator('[data-group-select="all"]')).toHaveAttribute('aria-pressed','true');
  await expect(page.locator('#ac-highlight-announcement')).toHaveText('Both groups displayed.');

  await page.getByText('View exact evidence',{exact:true}).click();
  const rows=page.locator('#ac-result tbody tr');await expect(rows).toHaveCount(2);
  await expect(rows.nth(0).locator('td').nth(3)).toHaveText('2.50');await expect(rows.nth(1).locator('td').nth(3)).toHaveText('3.00');
  await expect(rows.nth(0).locator('td').nth(4)).toHaveText('2');await expect(rows.nth(0).locator('td').nth(5)).toHaveText('100.00%');
  await page.getByRole('button',{name:'Separate cards',exact:true}).click();
  await expect(page.locator('.ac-separate-cards .ac-group-summary')).toHaveCount(2);await expect(page.locator('#ac-group-a')).toHaveValue('11|1');
  await expect(page.locator('.ac-separate-cards .comparison-card .panel-header')).toHaveCount(2);
  await expect(page.locator('.ac-separate-cards .card-metric-box')).toHaveCount(2);
  await expect(page.locator('.ac-separate-cards .card-stats')).toHaveCount(2);
  await page.getByRole('button',{name:'+ Add Group C',exact:true}).click();await expect(page.locator('#ac-group-c')).toBeFocused();
  await expect(page.locator('#ac-group-c')).toHaveValue('13|1');await expect(page.getByRole('button',{name:'+ Add Group C',exact:true})).toBeDisabled();

  // Overlay with Group C
  await page.getByRole('button',{name:'Overlay chart',exact:true}).click();
  await expect(page.locator('.ac-col-c')).toBeVisible();
  expect(await page.locator('.ac-bar').evaluateAll(es=>es.map(e=>Number(e.dataset.value)))).toEqual([2.5,3,3.5]);
  await expect(page.locator('[data-group-select="C"]')).toBeVisible();
  await page.locator('[data-group-select="C"]').click();
  await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','C');
  await expect(page.locator('[data-comparison-summary]')).toContainText('Difference A − B: -0.50 recorded-grade points');
  await expect(page.locator('[data-comparison-summary]')).toContainText('Group C is shown for context');

  await page.getByRole('button',{name:'Remove Group C',exact:true}).click();await expect(page.getByRole('button',{name:'+ Add Group C',exact:true})).toBeFocused();
  await page.getByRole('button',{name:'Overlay chart',exact:true}).click();await expect(page.locator('#ac-group-b')).toHaveValue('12|1');
  await page.locator('#ac-group-b').evaluate(e=>e.add(new Option('Tampered different subject','11|2')));
  await page.locator('#ac-group-b').selectOption('11|2');await page.getByRole('button',{name:'Compare groups',exact:true}).click();
  await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);await expect(page.locator('#ac-group-b')).toHaveAttribute('aria-invalid','true');await expect(page.locator('#ac-group-b')).toBeFocused();
  await page.locator('#ac-group-b').selectOption('12|1');await expect(page.locator('[data-comparison-chart]')).toBeVisible();
  await choose(page,'subject','2');await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);await expect(page.locator('#ac-notice')).toContainText('No common verified subject');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  await page.getByRole('button',{name:'View historical trend',exact:true}).click();await expect(page.locator('#analytics-trend-heading')).toBeFocused();
  await expect(page.locator('#tab-panel-overview #trend-chart')).toBeVisible();await expect(page.locator('#tab-panel-comparisons #trend-chart')).toHaveCount(0);
  await page.getByRole('tab',{name:'Academic Comparisons',exact:true}).click();await expect(page.locator('#ac-subject')).toHaveValue('2');
  expect(errors).toEqual([]);expect(writes).toEqual([]);
 });
}
test('Direct bar, legend, and keyboard interaction on vertical bar chart',async({page})=>{
 await open(page);
 await faculty(page);
 await expect(page.locator('.ac-vertical-chart-card')).toBeVisible();

 // Direct bar click
 const barB=page.locator('.ac-col-b .ac-vertical-bar');
 await barB.click();
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','B');

 // Keyboard activation on bar
 const barA=page.locator('.ac-col-a .ac-vertical-bar');
 await barA.focus();
 await page.keyboard.press('Enter');
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','A');

 // Direct legend item click
 const legB=page.locator('.ac-legend-b');
 await legB.click();
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','B');

 // Direct counterpart click in summary
 const counterpart=page.locator('.ac-counterpart-card');
 await counterpart.click();
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','A');
});
for(const scenario of ['unavailable','zero','empty'])test('Compact '+scenario+' comparison state',async({page})=>{
 await open(page,'dark',scenario);
 await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);
 if(scenario!=='empty'){
  await expect(page.locator('[data-comparison-value]').first()).toHaveText('Unavailable');
  await page.locator('#ac-metric').selectOption('numeric_coverage_pct');
  await expect(page.locator('[data-comparison-chart]')).toBeVisible();
  expect(await page.locator('.ac-bar').evaluateAll(es=>es.map(e=>Number(e.dataset.value)))).toEqual([0,0]);
 }
});
test('Failed scope retains controls and retries with the primary action',async({page})=>{
 const {transport,errors}=await open(page);transport.fail=true;
 await choose(page,'subject','1');
 await expect(page.locator('#ac-controls')).toBeVisible();await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);
 transport.fail=false;await page.getByRole('button',{name:'Compare groups',exact:true}).click();
 await expect(page.locator('[data-comparison-chart]')).toBeVisible();expect(errors).toEqual([]);
});

test('Non-selected group visibility contract in side-by-side and highlight modes',async({page})=>{
 await open(page);
 await faculty(page);
 await expect(page.locator('.ac-vertical-chart-card')).toBeVisible();

 // Highlight Group B: Group A must remain clearly visible and not aggressively dimmed
 await page.locator('[data-group-select="B"]').click();
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','B');

 const barA = page.locator('.ac-col-a .ac-vertical-bar');
 const barB = page.locator('.ac-col-b .ac-vertical-bar');
 await expect(barA).toBeVisible();
 await expect(barB).toBeVisible();

 const opacityBarA = await barA.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 const opacityBarB = await barB.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityBarB).toBeCloseTo(1, 1);
 expect(opacityBarA).toBeGreaterThanOrEqual(0.58);
 expect(opacityBarA).toBeLessThanOrEqual(0.70);

 const topInfoA = page.locator('.ac-col-a .ac-bar-top-info');
 const opacityTopA = await topInfoA.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityTopA).toBeGreaterThanOrEqual(0.85);

 const bottomLabelA = page.locator('.ac-col-a .ac-bar-bottom-label');
 const opacityBottomA = await bottomLabelA.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityBottomA).toBeGreaterThanOrEqual(0.80);

 const legA = page.locator('.ac-legend-a');
 const opacityLegA = await legA.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityLegA).toBeGreaterThanOrEqual(0.75);

 // Now Highlight Group A: Group B must remain clearly visible
 await page.locator('[data-group-select="A"]').click();
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted','A');

 const opacityBarBWhenA = await barB.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityBarBWhenA).toBeGreaterThanOrEqual(0.58);
 expect(opacityBarBWhenA).toBeLessThanOrEqual(0.70);

 const topInfoB = page.locator('.ac-col-b .ac-bar-top-info');
 const opacityTopB = await topInfoB.evaluate(el => parseFloat(window.getComputedStyle(el).opacity));
 expect(opacityTopB).toBeGreaterThanOrEqual(0.85);

 // Group A has a rich, solid, non-transparent teal fill in normal and highlighted states
 const bgBarA = await barA.evaluate(el => window.getComputedStyle(el).backgroundColor);
 expect(bgBarA).not.toBe('rgba(0, 0, 0, 0)');
 expect(bgBarA).not.toBe('transparent');
 expect(bgBarA).toMatch(/rgb\(13,\s*148,\s*136\)|rgb\(15,\s*143,\s*131\)/);
});

test('Side-by-side centered cluster, bar-edge spacing, and GWA scale lines',async({page})=>{
 await open(page);
 await faculty(page);

 // 1. Verify two-group centered cluster and bar-edge spacing (48px to 88px)
 const plotArea = page.locator('.ac-plot-side-by-side');
 await expect(plotArea).toBeVisible();
 await expect(plotArea).toHaveClass(/has-2-groups/);

 const colA = page.locator('.ac-col-a');
 const colB = page.locator('.ac-col-b');
 const boxA = await colA.boundingBox();
 const boxB = await colB.boundingBox();
 expect(boxA).not.toBeNull();
 expect(boxB).not.toBeNull();

 // Bar width in approved range (72px to 96px)
 expect(boxA.width).toBeGreaterThanOrEqual(72);
 expect(boxA.width).toBeLessThanOrEqual(96);

 // Gap between bar edges in approved range (48px to 88px)
 const barEdgeGap2 = boxB.x - (boxA.x + boxA.width);
 expect(barEdgeGap2).toBeGreaterThanOrEqual(48);
 expect(barEdgeGap2).toBeLessThanOrEqual(88);

 // Centered cluster check: outer margins of the plot should be balanced
 const boxPlot = await plotArea.boundingBox();
 expect(boxPlot).not.toBeNull();
 const leftSpace2 = boxA.x - boxPlot.x;
 const rightSpace2 = (boxPlot.x + boxPlot.width) - (boxB.x + boxB.width);
 expect(Math.abs(leftSpace2 - rightSpace2)).toBeLessThanOrEqual(4);

 // GWA scale grid lines: major lines only, readable theme-aware line
 const gridlines = page.locator('.ac-chart-gridline');
 expect(await gridlines.count()).toBeLessThanOrEqual(4);
 const baseline = page.locator('.ac-chart-baseline');
 await expect(baseline).toBeVisible();

 // 2. Add Group C to verify three-group centered cluster (28px to 56px gap)
 await page.getByRole('button',{name:'+ Add Group C',exact:true}).click();
 await expect(plotArea).toHaveClass(/has-3-groups/);

 const colC = page.locator('.ac-col-c');
 const box3A = await colA.boundingBox();
 const box3B = await colB.boundingBox();
 const box3C = await colC.boundingBox();
 expect(box3A).not.toBeNull();
 expect(box3B).not.toBeNull();
 expect(box3C).not.toBeNull();

 const gapAB = box3B.x - (box3A.x + box3A.width);
 const gapBC = box3C.x - (box3B.x + box3B.width);
 expect(gapAB).toBeGreaterThanOrEqual(28);
 expect(gapAB).toBeLessThanOrEqual(56);
 expect(gapBC).toBeGreaterThanOrEqual(28);
 expect(gapBC).toBeLessThanOrEqual(56);

 // Three-group centered cluster check
 const box3Plot = await plotArea.boundingBox();
 const leftSpace3 = box3A.x - box3Plot.x;
 const rightSpace3 = (box3Plot.x + box3Plot.width) - (box3C.x + box3C.width);
 expect(Math.abs(leftSpace3 - rightSpace3)).toBeLessThanOrEqual(4);

 // No label collision: labels beneath bars must not overlap horizontally
 const labelA = page.locator('.ac-col-a .ac-bar-bottom-label');
 const labelB = page.locator('.ac-col-b .ac-bar-bottom-label');
 const labelC = page.locator('.ac-col-c .ac-bar-bottom-label');
 const boxLabelA = await labelA.boundingBox();
 const boxLabelB = await labelB.boundingBox();
 const boxLabelC = await labelC.boundingBox();
 expect(boxLabelA).not.toBeNull();
 expect(boxLabelB).not.toBeNull();
 expect(boxLabelC).not.toBeNull();
 expect(boxLabelA.x + boxLabelA.width).toBeLessThanOrEqual(boxLabelB.x + 4);
 expect(boxLabelB.x + boxLabelB.width).toBeLessThanOrEqual(boxLabelC.x + 4);
});

test('Overlay mode top value strip, major gridlines only, and label isolation',async({page})=>{
 await open(page);
 await faculty(page);

 // 1. Verify Side-by-side mode: exact values above bars, full labels below, no value strip, no side callouts
 await expect(page.locator('.ac-plot-side-by-side')).toBeVisible();
 await expect(page.locator('.ac-overlay-value-strip')).toHaveCount(0);
 await expect(page.locator('.ac-overlay-callout')).toHaveCount(0);
 await expect(page.locator('.ac-bar-guideline')).toHaveCount(0);
 await expect(page.locator('.ac-plot-side-by-side .ac-bar-top-info')).toHaveCount(2);
 await expect(page.locator('.ac-plot-side-by-side .ac-bar-bottom-label')).toHaveCount(2);
 await expect(page.locator('.ac-plot-side-by-side .ac-bar-sub-label')).toHaveCount(2);

 // 2. Switch to Overlay mode
 await page.locator('[data-display-select="overlay"]').click();
 const plotArea = page.locator('.ac-chart-plot-area');
 await expect(plotArea).toHaveClass(/ac-plot-overlay/);
 await expect(plotArea).toHaveClass(/has-2-groups/);

 // Gridlines: only major scale gridlines exist (<= 4), zero unlabeled dotted guidelines
 await expect(page.locator('.ac-bar-guideline')).toHaveCount(0);
 const gridlines = page.locator('.ac-chart-gridline');
 expect(await gridlines.count()).toBeLessThanOrEqual(4);

 // Top value strip is visible, with interactive button items
 const valueStrip = page.locator('.ac-overlay-value-strip');
 await expect(valueStrip).toBeVisible();
 const stripItems = page.locator('.ac-value-strip-item');
 await expect(stripItems).toHaveCount(2);

 // Verify no side callouts, no duplicate values above bars, and no full labels below bars in overlay mode
 await expect(page.locator('.ac-overlay-callout')).toHaveCount(0);
 await expect(page.locator('.ac-plot-overlay .ac-bar-top-info')).toHaveCount(0);
 await expect(page.locator('.ac-plot-overlay .ac-bar-sub-label')).toHaveCount(0);

 // Two-group pill buttons do not overlap and are stacked vertically inside plot
 const boxItemA = await stripItems.nth(0).boundingBox();
 const boxItemB = await stripItems.nth(1).boundingBox();
 expect(boxItemA).not.toBeNull();
 expect(boxItemB).not.toBeNull();
 expect(boxItemA.y + boxItemA.height).toBeLessThanOrEqual(boxItemB.y + 2);

 // Columns have distinct horizontal positions
 const boxColA = await page.locator('.ac-col-a').boundingBox();
 const boxColB = await page.locator('.ac-col-b').boundingBox();
 expect(boxColA).not.toBeNull();
 expect(boxColB).not.toBeNull();
 expect(Math.abs(boxColA.x - boxColB.x)).toBeGreaterThanOrEqual(25);

 // 3. Add Group C to verify three-group overlay layout
 await page.getByRole('button',{name:'+ Add Group C',exact:true}).click();
 await expect(plotArea).toHaveClass(/has-3-groups/);
 await expect(page.locator('.ac-col-c')).toBeVisible();
 await expect(stripItems).toHaveCount(3);

 const boxItemC = await stripItems.nth(2).boundingBox();
 expect(boxItemC).not.toBeNull();

 const boxColC = await page.locator('.ac-col-c').boundingBox();
 expect(boxColC).not.toBeNull();
 expect(Math.abs(boxColB.x - boxColC.x)).toBeGreaterThanOrEqual(25);

 // 4. Interactive selection on value strip buttons with aria-pressed synchronization
 const stripBtnB = page.locator('.ac-value-strip-item.ac-item-b');
 await stripBtnB.click();
 await expect(stripBtnB).toHaveAttribute('aria-pressed', 'true');
 await expect(page.locator('.ac-value-strip-item.ac-item-a')).toHaveAttribute('aria-pressed', 'false');
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted', 'B');
 await expect(page.locator('[data-group-select="B"]').first()).toHaveAttribute('aria-pressed', 'true');

 // Keyboard activation on value strip button A
 const stripBtnA = page.locator('.ac-value-strip-item.ac-item-a');
 await stripBtnA.focus();
 await page.keyboard.press('Enter');
 await expect(stripBtnA).toHaveAttribute('aria-pressed', 'true');
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted', 'A');

 // Group C remains contextual and does not alter A minus B difference
 const diffTextBefore = await page.locator('.ac-chart-diff-annotation').textContent();
 const stripBtnC = page.locator('.ac-value-strip-item.ac-item-c');
 await stripBtnC.click();
 await expect(stripBtnC).toHaveAttribute('aria-pressed', 'true');
 await expect(page.locator('.ac-result-layout')).toHaveAttribute('data-highlighted', 'C');
 const diffTextAfter = await page.locator('.ac-chart-diff-annotation').textContent();
 expect(diffTextAfter).toBe(diffTextBefore);
 expect(diffTextAfter).toContain('Group C contextual');

 // 5. Constrained width responsiveness (1024x768)
 await page.setViewportSize({ width: 1024, height: 768 });
 await expect(valueStrip).toBeVisible();
 const scrollWidth = await page.evaluate(() => document.documentElement.scrollWidth);
 const clientWidth = await page.evaluate(() => document.documentElement.clientWidth);
 expect(scrollWidth).toBeLessThanOrEqual(clientWidth + 2); // No horizontal overflow
});

test('Overview tab displays full-width Level 1 metrics, Level 2 details grid, and historical trend details', async({ page }) => {
 await open(page);
 await page.locator('#tab-btn-overview').click();
 await expect(page.locator('#analytics-summary')).toBeVisible();

 // Level 1 metrics
 const metricsGrid = page.locator('.analytics-metrics');
 await expect(metricsGrid).toBeVisible();
 await expect(metricsGrid.locator('dd[data-metric="average-gwa"]')).toBeVisible();

 // Level 2 details workspace
 const detailsWorkspace = page.locator('.analytics-details-workspace');
 await expect(detailsWorkspace).toBeVisible();
 await expect(detailsWorkspace.locator('.analytics-detail-block')).toHaveCount(4);

 // Check important highlighted details
 const detailsText = await detailsWorkspace.textContent();
 expect(detailsText).toContain('Comparison period');
 expect(detailsText).toContain('versus');
 expect(detailsText).toContain('Point change');
 expect(detailsText).toContain('Relative change');
 expect(detailsText).toContain('Evidence used');
 expect(detailsText).toContain('Data status');
 expect(detailsText).toContain('Changes are unavailable when the compared term is incomplete or has no numeric GWA.');

 // Historical semester trend details card
 const trendCard = page.locator('#trend-details-card');
 await expect(trendCard).toBeVisible();
 const trendItems = page.locator('#trend-details-content .trend-detail-item');
 await expect(trendItems.first()).toBeVisible();
});

test('Progressive filter intersection, duplicate exclusion, and empty intersection guidance',async({page})=>{
 await open(page);
 await page.locator('[data-dimension="faculty_subject"]').click();
 await choose(page,'school_year','2026-2027');
 await choose(page,'semester','1');
 await choose(page,'subject','1');

 // Group A is selected as 11|1
 await expect(page.locator('#ac-group-a')).toHaveValue('11|1');

 // Group B options must exclude Group A (11|1)
 const groupBOptionValues = await page.locator('#ac-group-b option').evaluateAll(opts => opts.map(o => o.value));
 expect(groupBOptionValues).not.toContain('11|1');
 expect(groupBOptionValues).toContain('12|1');

 // Add Group C
 await page.getByRole('button',{name:'+ Add Group C',exact:true}).click();
 await expect(page.locator('#ac-group-c')).toHaveValue('13|1');

 // Group C options must exclude Group A (11|1) and Group B (12|1)
 const groupCOptionValues = await page.locator('#ac-group-c option').evaluateAll(opts => opts.map(o => o.value));
 expect(groupCOptionValues).not.toContain('11|1');
 expect(groupCOptionValues).not.toContain('12|1');
 expect(groupCOptionValues).toContain('13|1');

 // Switch subject to '2' where only 1 faculty member has an assignment (no comparable counterpart)
 await choose(page,'subject','2');
 await expect(page.locator('[data-comparison-chart]')).toHaveCount(0);
 await expect(page.locator('#ac-notice')).toContainText('No common verified subject');
 await expect(page.locator('#ac-notice')).toContainText('No comparable assignment is available');

 // Empty intersection card should be rendered with actionable recovery buttons
 const emptyCard = page.locator('.ac-empty-intersection');
 await expect(emptyCard).toBeVisible();
 await expect(emptyCard).toContainText('No comparable assignment is available');
 await expect(emptyCard).toContainText('The selected groups do not share an eligible subject and academic period');

 const btnChangeDim = emptyCard.getByRole('button',{name:'Change comparison dimension'});
 await expect(btnChangeDim).toBeVisible();
 await btnChangeDim.click();
 await expect(page.locator('[data-dimension="faculty_subject"]')).toBeFocused();

 const btnClear = emptyCard.getByRole('button',{name:'Clear comparison'});
 await expect(btnClear).toBeVisible();
});
