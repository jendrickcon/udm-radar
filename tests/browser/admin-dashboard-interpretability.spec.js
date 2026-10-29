import { test, expect } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { readFileSync, existsSync } from 'node:fs';
import { resolve, sep } from 'node:path';

// Generated actual PHP markup + controlled report transport, never login or DB.
const root=process.cwd();
const php=process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const render=(page,scenario='normal')=>execFileSync(php,['tests/unit/admin_dashboard_interpretability_test.php','--render',page,scenario],{encoding:'utf8'});
const reports=JSON.parse(execFileSync(php,['tests/fixtures/admin_faculty_acceptance_reports.php'],{encoding:'utf8'}));
const chartAsset=process.env.ADMIN_CHART_ASSET;
const chartSource=chartAsset && existsSync(chartAsset) ? readFileSync(chartAsset) : null;
async function open(page,kind,theme,scenario='normal') {
 const errors=[],writes=[];
 page.on('pageerror',e=>errors.push(e.message));
 await page.addInitScript(t=>localStorage.setItem('udmRadarDisplayPreferencesV1',JSON.stringify({version:1,colorStyle:'ccs',theme:t,motion:'reduce',textSize:'default',underlineLinks:false})),theme);
 const markup=render(kind,scenario);
 await page.route('**/*',route=>{
  const r=route.request(),u=new URL(r.url());
  if(!['GET','HEAD'].includes(r.method())){writes.push(r.method()+' '+u.pathname);return route.abort();}
  if(u.hostname==='cdn.jsdelivr.net' && u.pathname==='/npm/chart.js')return chartSource?route.fulfill({body:chartSource,contentType:'application/javascript'}):route.continue();
  if(u.pathname==='/udm-radar/admin/index.php')return route.fulfill({body:markup,contentType:'text/html'});
  if(u.pathname==='/udm-radar/api/academic_analytics.php'){
   const report=structuredClone(reports[kind==='faculty'?'faculty':'admin'].complete);report.filters.school_year=u.searchParams.get('school_year')||'';
   return route.fulfill({json:report});
  }
  if(u.pathname.startsWith('/udm-radar/assets/')){
   const local=resolve(root,u.pathname.slice('/udm-radar/'.length));
   if(local.startsWith(resolve(root,'assets')+sep)&&existsSync(local))return route.fulfill({body:readFileSync(local),contentType:local.endsWith('.css')?'text/css':local.endsWith('.js')?'application/javascript':'application/octet-stream'});
  }
  return route.abort();
 });
 await page.goto('http://interpretability.invalid/udm-radar/admin/index.php');
 await expect(page.locator('html')).toHaveAttribute('data-theme',theme);
 if(kind==='faculty')await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('2.75');
 else await expect.poll(()=>page.evaluate(()=>typeof Chart!=='undefined'&&Boolean(Chart.getChart(document.querySelector('canvas'))))).toBe(true);
 return {errors,writes};
}
async function contained(page){
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
 const owners=await page.evaluate(()=>[...new Set([document.scrollingElement,document.querySelector('.main-content')])].filter(e=>e && e.scrollHeight>e.clientHeight+2 && (e===document.scrollingElement||/auto|scroll/.test(getComputedStyle(e).overflowY))).length);
 expect(owners).toBeLessThanOrEqual(1);
}
for(const [width,height] of [[1440,900],[1280,600],[1024,768]])for(const theme of ['light','dark']){
 test('[Generated] Faculty shared analytics '+width+' '+theme,async({page})=>{
  await page.setViewportSize({width,height});const {errors,writes}=await open(page,'faculty',theme);
  await expect(page.locator('#academic-analytics')).toHaveAttribute('data-role','faculty');
  await expect(page.locator('[data-focused-chart]')).toHaveCount(0);
  await page.getByRole('tab',{name:'Academic Trends',exact:true}).click();
  await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('2.75');
  await contained(page);expect(errors).toEqual([]);expect(writes).toEqual([]);
 });
 test('[Generated] Dashboard help, grid, bars '+width+' '+theme,async({page})=>{
  await page.setViewportSize({width,height});const {errors,writes}=await open(page,'dashboard',theme);
  const card=page.locator('.point-change-card'),trigger=page.getByRole('button',{name:'Explain comparison requirements'}),help=page.locator('#comparison-help');
  await expect(card).toContainText('No comparable previous term is available for this scope.');
  const before=(await card.boundingBox()).height;
  await trigger.focus();await page.keyboard.press('Enter');await expect(trigger).toHaveAttribute('aria-expanded','true');await expect(help).toBeVisible();
  await expect(help).toContainText('academic period');expect((await card.boundingBox()).height).toBeCloseTo(before,1);
  const box=await help.boundingBox();expect(box.x).toBeGreaterThanOrEqual(0);expect(box.y).toBeGreaterThanOrEqual(0);expect(box.x+box.width).toBeLessThanOrEqual(width);expect(box.y+box.height).toBeLessThanOrEqual(height);
  await page.keyboard.press('Escape');await expect(help).toBeHidden();await expect(trigger).toBeFocused();
  await page.keyboard.press('Space');await expect(help).toBeVisible();await page.locator('h1').click();await expect(help).toBeHidden();await expect(trigger).toBeFocused();
  const grid=page.locator('.kpi-grid--four-cohort'),cards=grid.locator(':scope > *');await expect(cards).toHaveCount(4);
  const boxes=await cards.evaluateAll(es=>es.map(e=>e.getBoundingClientRect().toJSON()));expect(Math.abs(boxes[0].width-boxes[1].width)).toBeLessThan(2);
  if(width===1440)expect(Math.abs(boxes[0].y-boxes[3].y)).toBeLessThan(2);else{expect(Math.abs(boxes[0].y-boxes[1].y)).toBeLessThan(2);expect(boxes[2].y).toBeGreaterThan(boxes[0].y+20);}
  const strip=page.locator('.card-strip--compact');await expect(strip).toContainText('No referral has a recorded first-notice time yet.');expect(await strip.evaluate(e=>e.scrollHeight<=e.clientHeight+1)).toBe(true);
  const values=await page.evaluate(()=>({chart:Chart.getChart('sectionGwaBarChart').data.datasets[0].data,table:[...document.querySelectorAll('#section-gwa-exact-table tbody td')].map(e=>Number(e.textContent)),color:Chart.getChart('sectionGwaBarChart').data.datasets[0].backgroundColor,hover:Chart.getChart('sectionGwaBarChart').data.datasets[0].hoverBorderWidth,axis:Chart.getChart('sectionGwaBarChart').options.scales.y.title.text}));
  expect(values.chart).toEqual(values.table);expect(values.color).not.toMatch(/^(#000(000)?|rgb\(0,\s*0,\s*0\))$/i);expect(values.hover).toBeGreaterThan(1);expect(values.axis).toBe('Recorded-grade average');
  for(const id of ['dashboard-year','dashboard-section','filter-risk','filter-status'])expect(await page.locator('#'+id).evaluate(e=>Boolean(e.labels?.length||e.getAttribute('aria-label')))).toBe(true);
  await page.evaluate(()=>window.UDMDisplayPreferences.applyPreferences({textSize:'extra-large'}));await contained(page);expect(await strip.evaluate(e=>e.scrollHeight<=e.clientHeight+1)).toBe(true);
  expect(errors).toEqual([]);expect(writes).toEqual([]);
 });
 test('[Generated] Overview equal-height workspace '+width+' '+theme,async({page})=>{
  await page.setViewportSize({width,height});const {errors,writes}=await open(page,'overview',theme);
  const risk=page.locator('[data-chart-panel="risk"]'),distinction=page.locator('[data-chart-panel="distinction"]');
  const paired=async(first,second)=>{
   const a=await first.boundingBox(),b=await second.boundingBox();
   if(width>=1280){
    expect(Math.abs(a.y-b.y),'Both cards share the row').toBeLessThan(2);
    expect(Math.abs(a.height-b.height),'Both cards have equal computed heights').toBeLessThan(2);
   }else{
    expect(b.y,'Stacked panels do not overlap').toBeGreaterThanOrEqual(a.y+a.height-1);
    expect(Math.min(a.height,b.height),'Stacked panels retain a useful minimum height').toBeGreaterThanOrEqual(500);
   }
  };
  await paired(risk,distinction);
  const normalHeight=(await risk.boundingBox()).height;
  const panelStyle=panel=>panel.evaluate(e=>{const s=getComputedStyle(e);return [s.borderRadius,s.borderColor,s.backgroundColor,s.padding];});
  expect(await panelStyle(risk)).toEqual(await panelStyle(distinction));
  if(width>=1280)expect(Math.abs((await risk.locator('button').boundingBox()).y-(await distinction.locator('button').boundingBox()).y),'Chart actions share the footer line').toBeLessThan(2);
  for(const kind of ['risk','distinction']){
   const chart=kind==='risk'?risk:distinction,region=page.locator(kind==='risk'?'#section-risk-data':'#honor-chart-data');
   const button=page.locator('#'+kind+'-exact-values'),buttonOffset=await button.evaluate(e=>e.offsetTop);await button.click();
   expect(await button.evaluate(e=>e.offsetTop),'Toggle stays in the same panel position').toBeCloseTo(buttonOffset,0);
   expect(await panelStyle(chart)).toEqual(await panelStyle(region));
   if(width>=1280){
    await paired(chart,region);
    expect((await chart.boundingBox()).height,'One height contract across modes').toBeCloseTo(normalHeight,0);
    const body=region.locator('.focused-chart-details__body');
    expect(await body.evaluate(e=>/auto|scroll/.test(getComputedStyle(e).overflowY))).toBe(true);
    const box=await body.boundingBox(),panelBox=await region.boundingBox();
    expect(box.y+box.height).toBeLessThanOrEqual(panelBox.y+panelBox.height);
   }else await paired(chart,region);
   await contained(page);await button.click();
  }
  await page.evaluate(()=>window.UDMDisplayPreferences.applyPreferences({textSize:'extra-large'}));
  for(const panel of [risk,distinction])expect(await panel.evaluate(e=>e.scrollHeight<=e.clientHeight+2),'Chart content fits at enlarged text size').toBe(true);
  await contained(page);
  expect(errors).toEqual([]);expect(writes).toEqual([]);
 });
 test('[Generated] Overview focused values and keyboard '+width+' '+theme,async({page})=>{
  await page.setViewportSize({width,height});const {errors,writes}=await open(page,'overview',theme);
  const grid=page.locator('[data-overview-charts]'),risk=page.locator('[data-chart-panel="risk"]'),distinction=page.locator('[data-chart-panel="distinction"]');
  await page.locator('#analytics-school_year').selectOption('2026-2027');
  await page.getByRole('button',{name:'Apply filters',exact:true}).click();
  await expect(page.locator('#active-filters')).toContainText('2026-2027');
  let activeTheme=theme;
  await expect(risk).toBeVisible();await expect(distinction).toBeVisible();
  for(const kind of ['risk','distinction']){
   const button=page.locator('#'+kind+'-exact-values'),region=page.locator(kind==='risk'?'#section-risk-data':'#honor-chart-data');
   await expect(region).toBeHidden();await expect(button).toHaveText('Exact values');await expect(button).toHaveAccessibleName('Exact values');
   await expect(button).toHaveAttribute('aria-expanded','false');await expect(button).toHaveAttribute('aria-pressed','false');
   await button.focus();await page.keyboard.press(kind==='risk'?'Enter':'Space');
   await expect(button).toHaveAttribute('aria-expanded','true');await expect(button).toHaveAttribute('aria-pressed','true');await expect(region).toBeVisible();
   await expect(button).toHaveAttribute('aria-controls',kind==='risk'?'section-risk-data':'honor-chart-data');
   await expect(button).toHaveText('Back to both charts');await expect(button).toHaveAccessibleName('Back to both charts');
   await expect(region.getByRole('button',{name:'Back to both charts'})).toHaveCount(0);
   await expect(page.getByRole('button',{name:'Back to both charts',exact:true})).toHaveCount(1);
   await expect(kind==='risk'?risk:distinction).toBeVisible();await expect(kind==='risk'?distinction:risk).toBeHidden();
   await expect(region.getByRole('heading')).toContainText('Focused view:');
   await expect(region.getByRole('heading')).toBeFocused();
   await expect(region.getByRole('heading')).toBeInViewport();
   const opaque=await region.locator('caption').evaluate(e=>getComputedStyle(e).backgroundColor);
   expect(opaque).not.toMatch(/rgba\([^)]*,\s*0(?:\.\d+)?\)/);
   const chart=kind==='risk'?risk:distinction,cb=await chart.boundingBox(),rb=await region.boundingBox();
   if(width>=1280){expect(Math.abs(cb.y-rb.y)).toBeLessThan(3);expect(kind==='risk'?cb.x<rb.x:rb.x<cb.x,'Details replace the opposite column').toBe(true);}else expect(rb.y).toBeGreaterThanOrEqual(cb.y+cb.height-2);
   if(kind==='risk'){
    const data=await page.evaluate(()=>({chart:Chart.getChart('sectionRiskChart').data.datasets.map(d=>d.data),table:[...document.querySelectorAll('#section-risk-chart-summary tbody tr')].map(r=>[...r.querySelectorAll('td')].map(t=>Number(t.textContent)))}));
    expect(data.chart).toEqual([0,1,2,3].map(i=>data.table.map(r=>r[i])));
    const scroll=region.locator(width>=1280?'.focused-chart-details__body':'.section-risk-table-scroll');expect(await scroll.evaluate(e=>e.scrollHeight>e.clientHeight)).toBe(true);
    if(width===1024)expect((await scroll.boundingBox()).height).toBeLessThanOrEqual(342);
    const headingBefore=await region.getByRole('heading').boundingBox();
    await scroll.evaluate(e=>e.scrollTop=e.scrollHeight);const caption=await region.locator('caption').boundingBox(),header=await region.locator('thead th').first().boundingBox();expect(header.y).toBeGreaterThanOrEqual(caption.y+caption.height-2);
    expect(Math.abs(caption.y-(await scroll.boundingBox()).y),'Caption stays at the top of the scroll area').toBeLessThan(4);
    expect(await region.locator('thead th').first().evaluate(e=>getComputedStyle(e).backgroundColor)).not.toMatch(/rgba\([^)]*,\s*0(?:\.\d+)?\)/);
    expect((await region.getByRole('heading').boundingBox()).y,'Heading remains outside scrolling details').toBeCloseTo(headingBefore.y,1);
   }else{
    await expect(region).toContainText('does not confer official eligibility');
    const data=await page.evaluate(()=>({chart:Chart.getChart('honorChart').data.datasets[0].data,table:[...document.querySelectorAll('#honor-chart-summary tbody td')].map(t=>Number(t.textContent))}));expect(data.chart).toEqual(data.table);
    expect(await region.locator('.distinction-table-scroll').evaluate(e=>e.scrollHeight<=e.clientHeight+2)).toBe(true);
   }
   await contained(page);
   activeTheme=activeTheme==='light'?'dark':'light';
   await page.evaluate(t=>window.UDMDisplayPreferences.applyPreferences({theme:t}),activeTheme);
   await button.click();await expect(risk).toBeVisible();await expect(distinction).toBeVisible();await expect(button).toBeFocused();
   await expect(button).toHaveText('Exact values');await expect(button).toHaveAccessibleName('Exact values');
   await expect(button).toHaveAttribute('aria-expanded','false');await expect(button).toHaveAttribute('aria-pressed','false');
   await expect(button).toBeInViewport();
   await expect(page.locator('html')).toHaveAttribute('data-theme',activeTheme);await expect(page.locator('#analytics-school_year')).toHaveValue('2026-2027');
  }
  expect(errors).toEqual([]);expect(writes).toEqual([]);
 });
}
test('[Generated] Active chart button reverses focused mode repeatedly',async({page})=>{
 await page.setViewportSize({width:1440,height:900});const {errors,writes}=await open(page,'overview','light');
 const grid=page.locator('[data-overview-charts]');
 for(const kind of ['risk','distinction']){
  const button=page.locator('#'+kind+'-exact-values'),region=page.locator(kind==='risk'?'#section-risk-data':'#honor-chart-data');
  for(let repeat=0;repeat<2;repeat++){
   await button.click();await expect(region).toBeVisible();await expect(grid.locator('.focused-chart-details:visible')).toHaveCount(1);
   await button.click();await expect(region).toBeHidden();await expect(button).toBeFocused();
   await expect(grid.locator('[data-chart-panel]:visible')).toHaveCount(2);
  }
  await button.click();await page.keyboard.press('Escape');await expect(region).toBeHidden();await expect(button).toBeFocused();
 }
 expect(errors).toEqual([]);expect(writes).toEqual([]);
});
for(const scenario of ['high','missing','empty','error'])test('[Generated] truthful '+scenario+' Dashboard state',async({page})=>{
 await page.setViewportSize({width:1440,height:900});const {errors}=await open(page,'dashboard','light',scenario);
 const text=await page.locator('.admin-dashboard').innerText();
 if(scenario==='error'){await expect(page.locator('[data-dashboard-metric="open-cases"]')).toHaveText('Unavailable');expect(text).toContain('Temporarily unavailable because analytics could not be loaded.');}
 else expect(text).not.toContain('Most displayed Students are currently classified as Low Risk');
 await expect(page.locator('.kpi-grid--four-cohort > *')).toHaveCount(4);expect(errors).toEqual([]);
});
