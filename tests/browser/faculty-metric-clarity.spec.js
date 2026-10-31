import {test,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
const base=process.env.STUDENT_TREND_URL;
const state=base?JSON.parse(readFileSync(process.env.STUDENT_TREND_STATE,'utf8')):null;
test.skip(!base,'Use the guarded owned-disposable Faculty fixture runner.');
async function login(page,name='assessment_faculty',theme='light'){
 await page.addInitScript(theme=>localStorage.setItem('udmRadarDisplayPreferencesV1',JSON.stringify({version:1,theme,colorStyle:'ccs',motion:'reduce',sidebarCollapsed:false})),theme);
 await page.route('https://cdn.jsdelivr.net/npm/chart.js',r=>r.fulfill({contentType:'application/javascript',body:readFileSync(process.env.STUDENT_TREND_EVIDENCE+'/chart.umd.js')}));
 await page.goto(base+'/login.php');await page.locator('#userid').fill(name);await page.locator('#password').fill(state.password);await page.getByRole('button',{name:/sign in|log in|login/i}).click();await expect(page).toHaveURL(/\/(faculty|student|admin)\//);
}
async function trend(page,key='501|IT-32'){
 const res=await page.goto(base+'/faculty/trend.php?load='+encodeURIComponent(key));expect(res.status()).toBe(200);
 await expect(page.locator('#faculty-assessment-data')).toHaveCount(1);
 await expect.poll(()=>page.evaluate(()=>typeof Chart!=='undefined'&&Boolean(Chart.getChart('progressionChart')))).toBe(true);
 return JSON.parse(await page.locator('#faculty-assessment-data').textContent());
}
async function contained(page){
 const geometry=await page.evaluate(()=>({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth,main:[...document.querySelectorAll('main,.main-content')].map(e=>({width:e.clientWidth,scroll:e.scrollWidth})),canvases:[...document.querySelectorAll('.faculty-chart-wrap canvas')].map(e=>({width:e.getBoundingClientRect().width,parent:e.parentElement.getBoundingClientRect().width}))}));
 expect(geometry.scroll).toBeLessThanOrEqual(geometry.width+1);for(const m of geometry.main)expect(m.scroll).toBeLessThanOrEqual(m.width+1);for(const c of geometry.canvases)expect(c.width).toBeLessThanOrEqual(c.parent+1);
}
for(const [width,height]of [[1440,900],[1280,600],[1024,768]])for(const theme of ['light','dark']){
 test(`Faculty Dashboard interim/final evidence ${width}x${height} ${theme}`,async({page})=>{
  await page.setViewportSize({width,height});const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page,'assessment_interim_only',theme);
  await page.goto(base+'/faculty/dashboard.php');await expect(page.locator('[data-dashboard-metric="assessment-mean"]')).toHaveText('85.0%');await expect(page.locator('[data-dashboard-metric="assessment-coverage"]')).toHaveText('100.0%');
  await expect(page.getByText('4 of 4 Midterm scores recorded',{exact:true})).toBeVisible();
  const help=page.getByText('Finalized outcomes and metric definitions',{exact:true});await help.focus();await page.keyboard.press('Enter');await expect(page.locator('[data-dashboard-metric="grade-coverage"]')).toContainText('0.0%');await expect(page.locator('[data-dashboard-metric="class-mean"]')).toContainText('Unavailable');await expect(help).toBeFocused();
  await contained(page);await expect(page.locator('html')).toHaveAttribute('data-theme',theme);expect(errors).toEqual([]);
 });
 test(`Faculty Trends raw chart, paired change and theme ${width}x${height} ${theme}`,async({page})=>{
  await page.setViewportSize({width,height});const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page,'assessment_faculty',theme);const data=await trend(page);
  await expect(page.locator('[data-faculty-selected="assessment-mean"]')).toHaveText('68.8%');await expect(page.locator('[data-faculty-selected="class-change"]')).toHaveText('+5.00 pp');
  await expect(page.locator('#faculty-class-scope')).toContainText('Machine Learning (Elective 1) â€” IT-32');
  const chart=await page.evaluate(()=>{const c=Chart.getChart('progressionChart'),r=Chart.getChart('riskMovementChart');return {values:c.data.datasets[0].data,min:c.options.scales.y.min,max:c.options.scales.y.max,gaps:c.data.datasets[0].spanGaps,risk:r.data.datasets.map(x=>x.data),motion:c.options.animation};});
  expect(chart.values).toEqual([63.75,68.75,null,null]);expect(chart.min).toBe(0);expect(chart.max).toBe(100);expect(chart.gaps).toBe(false);expect(chart.motion).toBe(false);expect(chart.risk.every(x=>x[2]===null&&x[3]===null)).toBe(true);
  await expect(page.locator('[data-assessment-stage="prefinal"]')).toContainText('Not recorded');await expect(page.locator('[data-assessment-stage="final"]')).toContainText('Not recorded');
  const notable=page.getByRole('region',{name:'Notable assessment changes in selected class'});await expect(notable.locator('tbody tr')).toHaveCount(4);await expect(notable).toContainText('+5.00 percentage points');await expect(notable).toContainText('Midterm');
  await expect(page.getByRole('heading',{name:'Previous-semester comparison'})).toBeVisible();await expect(page.getByText('No verified dated assignment is available for a comparable previous period.',{exact:false})).toBeVisible();
  expect(data.stages.prelim.count).toBe(4);await contained(page);await expect(page.locator('html')).toHaveAttribute('data-theme',theme);expect(errors).toEqual([]);
 });
}
test('Matched partial population uses paired difference, not difference of unmatched means',async({page})=>{
 await login(page);const data=await trend(page,'502|IT-35');expect(data.stages.prelim.mean).toBeCloseTo(63.3333333333);expect(data.stages.midterm.mean).toBe(90);
 await expect(page.locator('[data-faculty-selected="class-change"]')).toHaveText('+10.00 pp');await expect(page.getByText('Preliminary to Midterm Â· 2 matched Students Â· Provisional subset',{exact:true})).toBeVisible();await expect(page.locator('[data-faculty-selected="assessment-coverage"]')).toHaveText('75.0%');
});
test('Empty class keeps explicit recording status, no fabricated average or risk distribution',async({page})=>{
 await login(page);const data=await trend(page,'503|IT-36');expect(Object.values(data.stages).every(s=>s.mean===null&&s.risk===null)).toBe(true);await expect(page.locator('#faculty-chart-status')).toContainText('No assessment percentages');await expect(page.locator('[data-faculty-selected="assessment-mean"]')).toHaveText('Unavailable');await expect(page.locator('[data-faculty-selected="class-change"]')).toHaveText('Unavailable');
});
test('Final numeric/text outcomes remain separate from percentage line and risk states',async({page})=>{
 await login(page);const data=await trend(page,'504|IT-37');expect(data.final.mean).toBe(3.5);expect(data.final.recorded).toBe(4);expect(data.final.count).toBe(1);expect(data.final.risk.HIGH).toBe(1);expect(data.final.risk.NONE).toBe(2);expect(await page.evaluate(()=>Chart.getChart('progressionChart').data.datasets[0].data[3])).toBeNull();await expect(page.locator('[data-assessment-stage="final"]')).toContainText('3.50 eligible grade points');await expect(page.locator('[data-assessment-stage="final"]')).toContainText('Final entries recorded');
});
test('Next recorded adjacent assessment pair and exact period labels',async({page})=>{
 await login(page);await trend(page,'505|IT-38');await expect(page.locator('[data-faculty-selected="class-change"]')).toHaveText('+5.00 pp');await expect(page.getByText('Midterm to Pre-Final Â· 4 matched Students',{exact:true})).toBeVisible();await expect(page.locator('#faculty-class-scope')).toContainText('SY 2026-2027 Â· First Semester');
});
test('Keyboard class selection retains scope and essential explanations are available without tooltip',async({page})=>{
 await login(page);await trend(page);const select=page.getByLabel('Assigned class',{exact:true});await select.focus();expect(await select.evaluate(e=>getComputedStyle(e).outlineStyle)).not.toBe('none');await select.selectOption('502|IT-35');await expect(select).toBeFocused();await page.keyboard.press('Tab');await page.keyboard.press('Enter');await expect(page).toHaveURL(/load=502%7CIT-35/i);await expect(select).toHaveValue('502|IT-35');await expect(page.locator('#faculty-class-scope')).toContainText('Networking 1 â€” IT-35');
});
test('Foreign class, section and identifiers fail closed without disclosing foreign data',async({page})=>{
 await login(page);for(const query of ['load=506%7CFOREIGN','load=501%7CIT-35','faculty_id=32','student_id=5050','load%5B%5D=501%7CIT-32']){const r=await page.request.get(base+'/faculty/trend.php?'+query);expect([403,422]).toContain(r.status());expect(await r.text()).not.toMatch(/FOREIGN_PRIVATE_TEST|password_hash|SQLSTATE/);}
});
test('Past grades cannot be attributed through current loads; no previous mean is fabricated',async({page})=>{
 await login(page);await page.goto(base+'/faculty/trend.php?sy=2025-2026&load=501%7CIT-32');await expect(page.getByText(/Historical Faculty comparison is unavailable/)).toBeVisible();await expect(page.locator('[data-faculty-selected="assessment-mean"]')).toHaveText('Unavailable');const data=JSON.parse(await page.locator('#faculty-assessment-data').textContent());expect(data.expected).toBe(0);expect(Object.values(data.stages).every(s=>s.mean===null)).toBe(true);
});
test('No-load Faculty and wrong portal role do not gain Student records',async({page})=>{
 await login(page,'assessment_no_loads');await page.goto(base+'/faculty/trend.php');await expect(page.getByRole('heading',{name:'No assigned classes'})).toBeVisible();await expect(page.locator('#faculty-assessment-data')).toHaveCount(0);
 await page.goto(base+'/logout.php');await login(page,'analytics_student_a');for(const route of ['faculty/trend.php','faculty/dashboard.php']){const r=await page.request.get(base+'/'+route,{maxRedirects:0});expect(r.status()).toBe(302);expect(r.headers().location).toMatch(/login.php\?error=unauthorized/);expect(await r.text()).not.toContain('Assessment 5000');}
});
test('Missing chart dependency retains exact evidence and safe unavailable explanation',async({page})=>{
 await login(page);await page.route('https://cdn.jsdelivr.net/npm/chart.js',r=>r.fulfill({contentType:'application/javascript',body:'/* unavailable dependency */'}));await page.goto(base+'/faculty/trend.php?load=501%7CIT-32');await expect(page.locator('#faculty-chart-status')).toContainText('Charts unavailable');await expect(page.locator('[data-assessment-stage="midterm"]')).toContainText('68.75%');
});
test('Accepted Faculty navigation and assigned grade-submission GET scope remain intact',async({page})=>{
 await login(page);await page.goto(base+'/faculty/dashboard.php');for(const label of ['Home','Dashboard','Class Analytics','Performance Trends','Term Grade Submission','Concerns & Reports','Settings'])await expect(page.getByRole('navigation',{name:'Main Navigation'}).getByRole('link',{name:label,exact:true})).toBeVisible();
 await page.goto(base+'/faculty/analytics.php');await expect(page.getByRole('heading').first()).toBeVisible();
 const own=await page.request.get(base+'/faculty/grades.php?action=fetch_roster&class=501_IT-32&term=midterm');expect(own.status()).toBe(200);expect((await own.json()).students).toHaveLength(4);
 const foreign=await page.request.get(base+'/faculty/grades.php?action=fetch_roster&class=506_FOREIGN&term=midterm');const body=await foreign.json();expect(body.error).toContain('unavailable');expect(body.students).toBeUndefined();expect(JSON.stringify(body)).not.toContain('FOREIGN_PRIVATE_TEST');
});
