import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { test, expect } from '@playwright/test';

// Real synthetic login/GET checks and separately labeled in-memory report fixtures.
// No database creation, academic POSTs, screenshots or exports.
const base=process.env.PHASE_C_BASE_URL;
const php=process.env.PHP_BIN||(process.platform==='win32'?'C:/xampp/php/php.exe':'php');
const reports=JSON.parse(execFileSync(php,[resolve('tests/fixtures/admin_faculty_acceptance_reports.php')],{encoding:'utf8'}));
const credential=key=>({id:process.env[`PHASE_C_${key.toUpperCase()}_ID`],password:process.env[`PHASE_C_${key.toUpperCase()}_PASSWORD`]});
const routeFor=role=>`${role}/${role==='admin'?'analytics.php':'assigned_analytics.php'}`;
const comparisonTab=role=>role==='admin'?'Academic Comparisons':'Academic Trends';
const supportTab=role=>role==='admin'?'Support & Interventions':'Referrals & Interventions';

async function signIn(page,key,fixture){
 const role=key==='faculty_other'?'faculty':key;
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.goto(new URL('login.php',base).href);
 await page.locator('[name="userid"]').fill(credential(key).id);
 await page.locator('[name="password"]').fill(credential(key).password);
 await page.getByRole('button',{name:'Login to Portal',exact:true}).click();
 await page.waitForURL(`**/${role}/index.php`);
 if(fixture)await page.route('**/api/academic_analytics.php*',r=>r.fulfill({json:fixture}));
 await page.goto(new URL(routeFor(role),base).href);
 await expect(page.locator('#analytics-results')).toBeVisible();
 return errors;
}
async function logOut(page){if(base&&!page.isClosed())await page.goto(new URL('logout.php',base).href);}
async function display(page,theme='light',textSize='default'){
 await page.evaluate(p=>window.UDMDisplayPreferences.applyPreferences(p),{theme,colorStyle:'ccs',motion:'reduce',textSize,underlineLinks:false});
}
async function supportParity(page,report){
 for(const key of ['eligible_cases','referrals_created','referrals_reviewed','notices_issued','notices_deferred','student_acknowledgments','open_cases','closed_cases']){
  await expect(page.locator(`[data-support="${key}"]`)).toHaveText(report.support[key]===null?'Unavailable':String(report.support[key]));
 }
 const flow=await page.locator('#chart-notice-flow text[font-size="18px"]').allTextContents();
 expect(flow.map(Number)).toEqual([report.support.referrals_created,report.support.notices_issued,report.support.student_acknowledgments]);
 await expect(page.locator('#support-limits')).toContainText('MTR-20 is the arithmetic mean');
 await expect(page.locator('#support-limits')).toContainText('not MTR-19 rates');
 await expect(page.locator('#support-limits')).toContainText('not canonical MTR-21');
 const expected=report.drilldowns.map(d=>d.href);
 expect(await page.locator('#drilldown-table a').evaluateAll(nodes=>nodes.map(n=>n.getAttribute('href')))).toEqual(expected);
}
function changeSection(page){return page.locator('#comparison-details-content .comparison-details-section').filter({has:page.getByRole('heading',{name:'Change or difference',exact:true})});}

for(const key of ['admin','faculty','faculty_other'])test.describe(`${key} actual acceptance`,()=>{
 test.skip(!base||!credential(key).id||!credential(key).password,'Explicit local synthetic URL/account required');
 test.afterEach(async({page})=>logOut(page));
 test('support chart/table and authorized drill-down parity (live)',async({page})=>{
  const errors=await signIn(page,key);const role=key==='faculty_other'?'faculty':key;
  const report=await(await page.request.get(new URL('api/academic_analytics.php',base).href)).json();
  await page.getByRole('tab',{name:supportTab(role),exact:true}).click();await supportParity(page,report);
  if(report.drilldowns.length){
   const record=report.drilldowns[0];const link=page.locator('#drilldown-table a').first();await link.click();
   await expect(page).toHaveURL(new RegExp(role==='admin'?'analytics_case=':'analytics_referral='));
   await expect(page.locator('#tab-support')).toBeVisible();
   await expect(page.locator(role==='admin'?'#tab-support tbody tr':'#tab-support .referrals-desktop-view tbody tr')).toHaveCount(1);
   expect(new URL(page.url()).searchParams.get(role==='admin'?'analytics_case':'analytics_referral')).toBe(String(record.record_id));
  }else{
   await expect(page.locator('#drilldown-table')).toContainText('No authorized records match these filters.');
  }
  expect(errors).toEqual([]);
 });
 test('malformed drill-down and unauthorized workspace access fail closed (live)',async({page})=>{
  await signIn(page,key);const role=key==='faculty_other'?'faculty':key;
  const target=role==='admin'?'admin/activity.php?analytics_case[]=1':'faculty/feedback.php?analytics_referral[]=1';
  expect((await page.request.get(new URL(target,base).href)).status()).toBe(422);
  if(role==='faculty'){
   const denied=await page.request.get(new URL('admin/activity.php?analytics_case=1',base).href,{maxRedirects:0});
   expect(denied.status()).toBe(302);expect(denied.headers().location).toContain('unauthorized');
   const own=await(await page.request.get(new URL('api/academic_analytics.php',base).href)).json();
   const missing=await(await page.request.get(new URL('api/academic_analytics.php?section=UNASSIGNED-ACCEPTANCE',base).href)).json();
   expect(missing.summary.expected_grade_count).toBe(0);expect(missing.drilldowns).toEqual([]);
   if(key==='faculty_other'){
    expect(own.drilldowns).toEqual([]);
    await page.goto(new URL('faculty/feedback.php?tab=support&analytics_referral=1',base).href);
    await expect(page.locator('#tab-support .referrals-desktop-view')).toHaveCount(0);
    await expect(page.locator('#tab-support')).toContainText('No active subject referrals');
    await expect(page.locator('#tab-support')).not.toContainText('24-00-003');
   }
  }
 });
});

for(const role of ['admin','faculty'])test.describe(`${role} controlled report acceptance`,()=>{
 test.skip(!base||!credential(role).id||!credential(role).password,'Explicit local synthetic URL/account required');
 test.afterEach(async({page})=>logOut(page));
 for(const state of ['complete','partial','provisional','unavailable','empty','single','gap'])test(`${state} data, exact comparisons and supportive limits (pure report fixture)`,async({page})=>{
  const report=reports[role][state];const errors=await signIn(page,role,report);
  await page.getByRole('tab',{name:comparisonTab(role),exact:true}).click();
  const rows=page.locator('#trend-table tbody tr');await expect(rows).toHaveCount(report.trend.series.length||1);
  for(let i=0;i<report.trend.series.length;i++){
   const term=report.trend.series[i];await expect(rows.nth(i)).toContainText(term.label);
   await expect(rows.nth(i).locator('td').nth(1)).toHaveText(term.gwa===null?'N/A':Number(term.gwa).toFixed(2));
  }
  if(report.trend.change.point_change===null)await expect(page.locator('#trend-details-content')).toContainText('Change unavailable');
  else{
   expect(report.trend.change.point_change).toBe(0.5);expect(report.trend.change.relative_change_pct).toBe(20);
   await expect(page.locator('#trend-details-content')).toContainText('+0.50');
   await expect(page.locator('#trend-details-content')).toContainText('+20.0%');
  }
  if(state==='single')await expect(page.locator('#trend-chart polyline')).toHaveCount(0);
  if(state==='gap'&&role==='admin'){
   await expect(page.locator('#trend-chart polyline')).toHaveCount(0);
   await expect(page.locator('#trend-chart .analytics-dot')).toHaveCount(2);
  }
  await page.locator('#other-comparisons-disclosure summary').click();await page.locator('#comparison-group').selectOption('subject');
  if(state==='empty')await expect(page.locator('#comparison-table')).toContainText('No records match these filters.');
  else{
   const wanted=report.groups.subject.slice(0,2);if(wanted.length===1)wanted.push(wanted[0]);
   await expect(page.locator('#side-by-side-table tbody tr')).toHaveCount(2);
   for(let i=0;i<2;i++){
    const record=wanted[i];const row=page.locator('#side-by-side-table tbody tr').nth(i);
    await expect(row.locator('td').nth(0)).toHaveText(record.label);
    await expect(row.locator('td').nth(1)).toHaveText((record.gwa===null?'N/A':Number(record.gwa).toFixed(2))+(record.provisional?' (provisional)':''));
    await expect(row.locator('td').nth(2)).toHaveText(String(record.student_count));
    await expect(row.locator('td').nth(3)).toHaveText(`${record.eligible_grade_count} / ${record.expected_grade_count}`);
   }
  }
  await page.getByRole('tab',{name:supportTab(role),exact:true}).click();await supportParity(page,report);
  if(state!=='empty'){
   expect(report.support.mean_first_action_hours).toBe(4);expect(report.support.first_action_sample).toBe(3);
   await expect(page.locator('#chart-first-notice')).toContainText('4 hours');
   await expect(page.locator('[data-support="mean_first_action_hours"]')).toContainText('4 hours (Based on 3 referrals');
   await expect(page.locator('#chart-first-notice')).toContainText('48.00 hours (n=1)');
  }
  expect(errors).toEqual([]);
 });
 test('one/two-notice samples keep matching duration and visible caution (controlled)',async({page})=>{
  for(const [state,n,mean]of[['small-one',1,1],['small-two',2,1.5]]){
   await signIn(page,role,reports[role][state]);await page.getByRole('tab',{name:supportTab(role),exact:true}).click();
   expect(reports[role][state].support.first_action_sample).toBe(n);expect(reports[role][state].support.mean_first_action_hours).toBe(mean);
   await expect(page.locator('#chart-first-notice .support-sample-warning')).toBeVisible();
   await expect(page.locator('#chart-first-notice .support-sample-warning')).toContainText(`n=${n}`);
   await expect(page.locator('[data-support="mean_first_action_hours"]')).toContainText(`${mean} hours`);
   await logOut(page);await page.unroute('**/api/academic_analytics.php*');
  }
 });
 test('mixed-dimension context does not calculate a direct difference (controlled)',async({page})=>{
  await signIn(page,role,reports[role].complete);await page.getByRole('tab',{name:comparisonTab(role),exact:true}).click();
  await page.locator('#other-comparisons-disclosure summary').click();await page.locator('#comparison-group').selectOption('subject');
  await page.locator('#panel-compare-b .card-dimension-select').selectOption('section');
  await expect(changeSection(page)).toContainText('Change unavailable');
 });
 for(const theme of ['light','dark'])test(`enlarged text and one page scroll owner in ${theme} (controlled)`,async({page})=>{
  await page.setViewportSize({width:1024,height:768});await signIn(page,role,reports[role].complete);await display(page,theme,'extra-large');
  for(const tab of ['Overview',comparisonTab(role),supportTab(role)]){
   await page.getByRole('tab',{name:tab,exact:true}).click();
   const geometry=await page.evaluate(()=>{
    const main=document.querySelector('.main-content');const style=getComputedStyle(main);
    const nested=[...main.querySelectorAll('*')].filter(n=>['auto','scroll'].includes(getComputedStyle(n).overflowY)&&n.scrollHeight>n.clientHeight+2).map(n=>n.id||n.className);
    return {documentOverflow:document.documentElement.scrollWidth>innerWidth,mainOverflow:main.scrollWidth>main.clientWidth+1,mainScroll:['auto','scroll'].includes(style.overflowY),nested};
   });
   expect(geometry).toEqual({documentOverflow:false,mainOverflow:false,mainScroll:true,nested:[]});
  }
 });
});

test.describe('Admin comparison eligibility',()=>{
 test.skip(!base||!credential('admin').id||!credential('admin').password,'Explicit local synthetic Admin required');
 test.afterEach(async({page})=>logOut(page));
 test('legacy comparison link preserves filters and opens the current tab (live)',async({page})=>{
  await signIn(page,'admin');await page.goto(new URL('admin/comparative_analytics.php?semester=1',base).href);
  await expect(page.locator('#analytics-results')).toBeVisible();
  await expect(page.getByRole('tab',{name:'Academic Comparisons',exact:true})).toHaveAttribute('aria-selected','true');
  await expect(page.getByLabel('Semester',{exact:true})).toHaveValue('1');
 });
 test('different Faculty subject assignments show unavailable difference, same-subject pair remains comparable (controlled)',async({page})=>{
  await signIn(page,'admin',reports.admin.complete);await page.getByRole('tab',{name:'Academic Comparisons',exact:true}).click();
  await page.locator('#other-comparisons-disclosure summary').click();await page.locator('#comparison-group').selectOption('faculty_subject');
  await page.locator('#panel-compare-a .card-group-select').selectOption('11|1');
  await page.locator('#panel-compare-b .card-group-select').selectOption('11|2');
  await expect(changeSection(page)).toContainText('Change unavailable');
  await expect(changeSection(page)).toContainText('same subject');
  await page.locator('#panel-compare-b .card-group-select').selectOption('12|1');
  await expect(changeSection(page)).toContainText('-1.00');
  await expect(changeSection(page)).not.toContainText('Change unavailable');
 });
});

test('anonymous legacy comparison request redirects to login (live)',async({browser})=>{
 test.skip(!base,'Explicit local URL required');const context=await browser.newContext();
 try{
  const response=await context.request.get(new URL('admin/comparative_analytics.php',base).href,{maxRedirects:0});
  expect(response.status()).toBe(302);expect(response.headers().location).toContain('login.php');
 }finally{await context.close();}
});
