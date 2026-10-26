import { test, expect } from '@playwright/test';
import { readFileSync, mkdirSync } from 'node:fs';
import { resolve } from 'node:path';
const base=process.env.ANALYTICS_MVP_URL;
const state=process.env.ANALYTICS_MVP_STATE?JSON.parse(readFileSync(process.env.ANALYTICS_MVP_STATE,'utf8')):null;
test.skip(!base||!state,'Requires the owned disposable analytics runner; never falls back to localhost/scratch');
const evidence=resolve('logs/post-defense-mvp/screenshots');mkdirSync(evidence,{recursive:true});
const paths={student:'student/academic_analytics.php',admin:'admin/analytics.php',faculty:'faculty/assigned_analytics.php'};

async function login(page,role,account){
 await page.route('https://fonts.googleapis.com/**',r=>r.fulfill({contentType:'text/css',body:''}));
 await page.goto(base+'/login.php');
 await page.locator('#userid').fill(account||({student:'analytics_student_a',admin:'analytics_admin',faculty:'analytics_faculty_a'}[role]));
 await page.locator('#password').fill(state.password);
 await page.getByRole('button',{name:'Login to Portal'}).click();
 await page.waitForURL('**/'+role+'/index.php');
 await page.goto(base+'/'+paths[role]);
 await expect(page.getByRole('heading',{name:/Academic review|BSIT Program Analytics|Program Analytics|Assigned-class review/,exact:true})).toBeVisible();
 await expect(page.locator('#analytics-results')).toBeVisible();
}

test('Student sees canonical change, distinct prediction meaning and unavailable range',async({page})=>{
 await login(page,'student');
 await expect(page.locator('[data-metric="current-gwa"]')).toHaveText('3.63');
 await expect(page.locator('[data-metric="previous-gwa"]')).toHaveText('3.00');
 await expect(page.locator('[data-metric="point-change"]')).toHaveText('+0.63');
 await expect(page.locator('[data-metric="relative-change"]')).toHaveText('+21.00%');
 await expect(page.locator('.analytics-metrics [data-metric="relative-change"]')).toHaveCount(0);
 await expect(page.locator('[data-metric="estimate"]')).toHaveText('3.25');
 await expect(page.locator('[data-metric="lower"]')).toHaveText('Unavailable');
 await expect(page.locator('[data-metric="upper"]')).toHaveText('Unavailable');
 await expect(page.getByText('Decision Tree Regressor',{exact:true})).toBeVisible();
 await expect(page.getByText(/separate rule-derived advisory/)).toBeVisible();
 await expect(page.locator('#trend-chart svg')).toBeVisible();
 await page.getByRole('button',{name:'Apply filters'}).focus();await page.keyboard.press('Tab');
 await expect(page.getByRole('button',{name:'Clear filters'})).toBeFocused();
});

test('Administrator filters refresh actual comparisons and retain coverage and fairness context',async({page})=>{
 await login(page,'admin');
 await page.getByRole('tab',{name:'Academic Comparisons'}).click();
 await page.getByLabel('School year',{exact:true}).selectOption('2026-2027');
 await page.getByLabel('Semester',{exact:true}).selectOption('1');
 await page.getByRole('button',{name:'Apply filters'}).click();
 await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('2.93');
 await expect(page.locator('[data-metric="student-count"]')).toHaveText('4');
 await page.getByLabel('Subject',{exact:true}).selectOption('101');await page.getByRole('button',{name:'Apply filters'}).click();
 await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('2.75');
 await expect(page.locator('[data-metric="student-count"]')).toHaveText('3');
 await page.getByLabel('Comparison view').selectOption('faculty_subject');
 await expect(page.locator('#comparison-chart svg')).toBeVisible();
 await expect(page.locator('#comparison-table')).toContainText('Synthetic analytics_faculty_a');
 await expect(page.locator('#comparison-table')).toContainText('Synthetic analytics_faculty_b');
 await expect(page.getByText(/do not independently measure teaching performance/)).toBeVisible();
 await expect(page.locator('#active-filters')).toContainText('2026-2027');
});

test('Administrator intervention counts and drill-down use actual existing records',async({page})=>{
 await login(page,'admin');
 await page.getByRole('tab',{name:'Support & Interventions'}).click();
 await expect(page.locator('[data-support="eligible_cases"]')).toHaveText('4');
 await expect(page.locator('[data-support="notices_deferred"]')).toHaveText('Unavailable');
 await expect(page.locator('[data-support="mean_first_action_hours"]')).toHaveText('2.50 hours (n=2)');
 await expect(page.getByText('Mean referral time to first evidenced notice (MTR-20)',{exact:true})).toBeVisible();
 await expect(page.getByText('Median recorded parent-case closed duration (limited indicator)',{exact:true})).toBeVisible();
 await page.getByLabel('Case status').selectOption('closed');await page.getByRole('button',{name:'Apply filters'}).click();
 await expect(page.locator('[data-support="eligible_cases"]')).toHaveText('1');
 const link=page.locator('#drilldown-table').getByRole('link',{name:'Case 2'});await expect(link).toBeVisible();
 await link.click();await expect(page).toHaveURL(/analytics_case=2/);
 await expect(page.locator('#tab-support')).toBeVisible();
 await expect(page.locator('#tab-support')).toContainText('analytics_student_b');
 await expect(page.locator('#tab-support')).not.toContainText('analytics_student_c');
});

test('Faculty scope is assigned-only and foreign filter/drill-down fails closed',async({page})=>{
 await login(page,'faculty');
 await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('3.63');
 await expect(page.locator('#analytics-results')).not.toContainText('analytics_faculty_b');
 await expect(page.getByText(/dated historical assignments/)).toBeVisible();
 const wrong=await page.request.get(base+'/api/academic_analytics.php?faculty=12');expect(wrong.status()).toBe(403);
 await page.goto(base+'/faculty/feedback.php?tab=support&analytics_referral=13');
 await expect(page.locator('#tab-support')).not.toContainText('analytics_student_c');
 await expect(page.locator('#tab-support')).not.toContainText('analytics_student_a');
 await page.goto(base+'/faculty/feedback.php?tab=support&analytics_referral=10');
 await expect(page.locator('#tab-support')).toContainText('analytics_student_a');
 await expect(page.locator('#tab-support')).not.toContainText('analytics_student_b');
 expect((await page.request.get(base+'/faculty/feedback.php?analytics_referral[]=10')).status()).toBe(422);
});

test('Student record tampering and unauthenticated API access are denied',async({page,browser})=>{
 await login(page,'student');
 expect((await page.request.get(base+'/api/academic_analytics.php?student_id=103')).status()).toBe(422);
 const context=await browser.newContext();expect((await context.request.get(base+'/api/academic_analytics.php')).status()).toBe(401);await context.close();
});

test('Incomplete and no-history accounts have safe unavailable states',async({page})=>{
 await login(page,'student','analytics_student_b');
 await expect(page.locator('[data-metric="current-gwa"]')).toHaveText('N/A');
 await expect(page.locator('[data-metric="point-change"]')).toHaveText('Unavailable');
 await expect(page.getByText(/Provisional/).first()).toBeVisible();
 await page.goto(base+'/logout.php');await login(page,'student','analytics_no_records');
 await expect(page.getByText('No recorded terms match these filters.',{exact:true})).toBeVisible();
 await expect(page.locator('[data-metric="estimate"]')).toHaveText('N/A');
});

test('Loading, empty and error states do not display stale results',async({page})=>{
 await login(page,'admin');
 await page.getByRole('tab',{name:'Academic Comparisons'}).click();
 await page.getByLabel('School year',{exact:true}).selectOption('2026-2027');await page.getByLabel('Semester',{exact:true}).selectOption('2');
 await page.getByRole('button',{name:'Apply filters'}).click();await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('N/A');
 await expect(page.locator('#comparison-table')).toContainText('No records match these filters.');
 await page.route('**/api/academic_analytics.php*',async route=>{await new Promise(r=>setTimeout(r,250));await route.fulfill({status:503,json:{error:'Controlled unavailable state'}});});
 await page.getByRole('button',{name:'Apply filters'}).click();await expect(page.locator('#analytics-state')).toContainText('Loading');
 await expect(page.locator('#analytics-results')).toBeHidden();
 await expect(page.locator('#analytics-state')).toContainText('could not be loaded');
});

test('A slower previous filter response cannot overwrite the latest selected period',async({page})=>{
 await login(page,'admin');
 let release;const gate=new Promise(resolve=>release=resolve);let observed;const started=new Promise(resolve=>observed=resolve);
 await page.route('**/api/academic_analytics.php*',async route=>{
  const url=new URL(route.request().url());
  if(url.searchParams.get('subject')!=='101'){await route.continue();return;}
  const response=await route.fetch();observed();await gate;try{await route.fulfill({response});}catch{} // The browser may already have aborted this older request.
 });
 await page.getByLabel('School year',{exact:true}).selectOption('2026-2027');
 await page.getByLabel('Subject',{exact:true}).selectOption('101');await page.getByRole('button',{name:'Apply filters'}).click();await started;
 await page.getByLabel('Subject',{exact:true}).selectOption('102');
 const latest=page.waitForResponse(response=>response.url().includes('/api/academic_analytics.php')&&new URL(response.url()).searchParams.get('subject')==='102');
 await page.getByRole('button',{name:'Apply filters'}).click();await latest;
 await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('4.00');release();
 await expect(page.locator('#active-filters')).toContainText('AN202');
 await expect(page.locator('[data-metric="average-gwa"]')).toHaveText('4.00');
});

for(const [role,path,label]of[
 ['student','student/dashboard.php','Review GWA trends and estimate details'],
 ['admin','admin/index.php','View Program Analytics'],
 ['faculty','faculty/dashboard.php','View Assigned-Class Analytics']
]){
 test(`${role} existing workspace links to its scoped analytics view`,async({page})=>{
  await login(page,role);
  await expect(page.getByRole('navigation',{name:'Main Navigation'}).getByRole('link',{name:'Dashboard',exact:true})).toBeVisible();
  await page.goto(base+'/'+path);await page.getByRole('link',{name:label,exact:true}).click();
  await expect(page).toHaveURL(base+'/'+paths[role]);await expect(page.locator('#analytics-results')).toBeVisible();
 });
}

test('Administrator comparative_analytics.php safely redirects to Program Analytics comparisons tab',async({page})=>{
 await login(page,'admin');
 await page.goto(base+'/admin/comparative_analytics.php?school_year=2026-2027');
 await expect(page).toHaveURL(/admin\/analytics\.php\?.*tab=comparisons/);
 await expect(page.getByRole('tab',{name:'Academic Comparisons'})).toHaveAttribute('aria-selected','true');
 await expect(page.locator('#tab-panel-comparisons')).toBeVisible();
});

test('Administrator dashboard.php canonical redirect targets index.php',async({page})=>{
 await login(page,'admin');
 await page.goto(base+'/admin/dashboard.php');
 await expect(page).toHaveURL(base+'/admin/index.php');
});

test('Administrator dashboard features compact analytics snapshot cards',async({page})=>{
 await login(page,'admin');
 await page.goto(base+'/admin/index.php');
 await expect(page.locator('[data-dashboard-metric="mean-gwa"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="point-change"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="prediction-coverage"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="grade-coverage"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="open-cases"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="mean-time-action"]')).toBeVisible();
});

test('Faculty dashboard features compact role-scoped assigned-class analytics cards',async({page})=>{
 await login(page,'faculty');
 await page.goto(base+'/faculty/dashboard.php');
 await expect(page.locator('[data-dashboard-metric="assigned-sections"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="grade-coverage"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="class-mean"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="active-referrals"]')).toBeVisible();
 await expect(page.locator('[data-dashboard-metric="notices-issued"]')).toBeVisible();
});

test('Advanced filters progressive disclosure exposes curriculum year and faculty with active badge',async({page})=>{
 await login(page,'admin');
 const details=page.locator('#analytics-advanced-details');
 const summary=page.locator('#analytics-advanced-summary');
 await expect(details).toBeVisible();
 await expect(page.locator('#analytics-advanced-badge')).toBeHidden();
 await summary.click();
 await expect(details).toHaveAttribute('open','');
 await expect(page.locator('#analytics-year_level')).toBeVisible();
 await expect(page.locator('#analytics-faculty')).toBeVisible();
 await page.locator('#analytics-year_level').selectOption('2');
 await page.getByRole('button',{name:'Apply filters'}).click();
 await expect(page.locator('#analytics-advanced-badge')).toHaveText('1');
 await expect(page.locator('#analytics-advanced-badge')).toBeVisible();
});

// Owner instruction, 26 October 2026: desktop only; no further mobile tests or screenshots.
for(const role of ['student','admin','faculty'])for(const [width,height] of [[1440,900],[1024,768],[1280,600]])for(const theme of ['light','dark']){
 test(`${role} ${width}x${height} ${theme} remains readable and contained`,async({page})=>{
  await page.setViewportSize({width,height});await page.emulateMedia({colorScheme:theme,reducedMotion:'reduce'});const errors=[];page.on('pageerror',e=>errors.push(e.message));
  await login(page,role);
  await expect(page.locator('html')).toHaveAttribute('data-theme',theme);
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
  await expect(page.getByLabel('School year',{exact:true})).toBeVisible();
  expect(errors).toEqual([]);
 });
}
