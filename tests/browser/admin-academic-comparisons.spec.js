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
  expect(await page.locator('.ac-bar').evaluateAll(es=>es.map(e=>Number(e.dataset.value)))).toEqual([2.5,3]);
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
