import {test,expect} from '@playwright/test';
import {readFileSync,writeFileSync} from 'node:fs';
import {resolve} from 'node:path';
const base=process.env.STUDENT_TREND_URL;
const evidence=process.env.STUDENT_TREND_EVIDENCE || 'logs/student-trend';
const state=base?JSON.parse(readFileSync(process.env.STUDENT_TREND_STATE,'utf8')):null;
test.skip(!base,'Run through tests/fixtures/run_student_trend.mjs for an owned disposable target.');
test.beforeAll(async({browser})=>{writeFileSync(resolve(evidence,'browser-version.txt'),browser.version());});
async function transport(page){
 await page.route('https://cdn.jsdelivr.net/npm/chart.js',route=>route.fulfill({contentType:'application/javascript',body:readFileSync(resolve(evidence,'chart.umd.js'),'utf8')}));
}
async function login(page,name='analytics_student_a'){
 await page.goto(base+'/login.php');await page.locator('#userid').fill(name);await page.locator('#password').fill(state.password);
 await page.getByRole('button',{name:/sign in|log in|login/i}).click();await expect(page).toHaveURL(/student\/index.php/);
}
async function trend(page){await page.goto(base+'/student/trend.php');await expect(page.locator('#semGwaChart')).toBeVisible();await expect(page.locator('#trend-chart-status')).toContainText('Open the evidence table');}
for(const [width,height] of [[1440,900],[1280,600],[1024,768]]) for(const theme of ['light','dark']) {
 test(`authenticated trend ${width}x${height} ${theme}`,async({page})=>{
  await page.setViewportSize({width,height});await page.emulateMedia({reducedMotion:'reduce'});await transport(page);const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await login(page,'trend_dense_history');await trend(page);
  await page.locator('html').evaluate((el,theme)=>{el.dataset.theme=theme;el.dataset.motion='reduce';},theme);
  await expect(page.locator('html')).toHaveAttribute('data-theme',theme);
  const data=await page.evaluate(()=>{
   const tablePoints=JSON.parse(document.getElementById('student-trend-data').textContent),points=tablePoints.filter(p=>!(p.zone==='history'&&p.state==='Pending'&&tablePoints.some(q=>q.zone==='current'&&q.key===p.key))),chart=Chart.getChart('semGwaChart');
   return {points,tablePoints,rows:[...document.querySelectorAll('.student-performance-trend tbody tr')].map(row=>row.children[2].textContent.trim()),
    values:chart.data.datasets.slice(0,3).map(ds=>ds.data),overflow:document.documentElement.scrollWidth>innerWidth,
    canvas:document.getElementById('semGwaChart').getBoundingClientRect().toJSON(),chartArea:chart.chartArea,
    showLine:chart.data.datasets.slice(0,3).map(ds=>ds.showLine),zero:chart.options.scales.y.min,animation:chart.options.animation};
  });
  expect(data.rows).toEqual(data.tablePoints.map(p=>p.value===null?(p.state==='Pending'?'Pending':'Unavailable'):p.value.toFixed(2)));
  for(const [zone,i]of [['history',0],['current',1],['estimate',2]])expect(data.values[i]).toEqual(data.points.map(p=>p.zone===zone?p.value:null));
  const lowestValue=Math.min(2.5,...data.points.filter(p=>p.value!==null).map(p=>p.value));
  expect(data.showLine).toEqual([true,false,false]);expect(data.zero).toBe(Math.max(0,Math.floor((lowestValue<2.5?lowestValue-.1:2.5)*4)/4));expect(data.animation).toBe(false);expect(data.overflow).toBe(false);expect(data.canvas.right).toBeLessThanOrEqual(width+1);expect(data.chartArea.width).toBeGreaterThan(100);
  const headingBoxes=await page.evaluate(()=>{
   const chart=Chart.getChart('semGwaChart'),ctx=chart.ctx,original=ctx.fillText,boxes=[];
   ctx.fillText=function(text,x,y,...args){if(this.font.includes('18px'))boxes.push({text,left:x-this.measureText(text).width/2,right:x+this.measureText(text).width/2,top:y-18,bottom:y+4});return original.call(this,text,x,y,...args);};
   try{chart.stop();chart.update('none');chart.draw();}finally{ctx.fillText=original;}return [...new Map(boxes.map(b=>[b.text+':'+b.left+':'+b.top,b])).values()];
  });
  for(let i=0;i<headingBoxes.length;i++)for(let j=i+1;j<headingBoxes.length;j++){
   const a=headingBoxes[i],b=headingBoxes[j];expect(a.left<b.right&&a.right>b.left&&a.top<b.bottom&&a.bottom>b.top,`${a.text} overlaps ${b.text}`).toBe(false);
  }
  await page.locator('.trend-evidence-toggle').click();
  // Enter using the keyboard: pointer-triggered programmatic focus need not match :focus-visible.
  const summaries=page.locator('[data-trend-point]');await page.keyboard.press('Tab');await page.keyboard.press('Tab');await expect(summaries.first()).toBeFocused();
  expect(await summaries.first().evaluate(el=>getComputedStyle(el).outlineStyle)).not.toBe('none');
  await page.keyboard.press('Enter');await expect(summaries.first().locator('..')).toHaveAttribute('open','');await page.keyboard.press('Escape');await expect(summaries.first().locator('..')).not.toHaveAttribute('open','');
  await page.evaluate(()=>{document.documentElement.dataset.fontSize='extra-large';});
  expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
  expect(errors).toEqual([]);
 });
}
for(const account of ['trend_no_grades','trend_prelim_only','trend_history_only','trend_projection_only','analytics_student_b']) {
 test(`authenticated sparse evidence ${account}`,async({page})=>{
  await transport(page);await login(page,account);await page.goto(base+'/student/trend.php');
  const payload=JSON.parse(await page.locator('#student-trend-data').textContent());
  if(payload.every(point=>point.value===null)) {
   await expect(page.locator('#trend-chart-status')).toBeVisible();
   await expect(page.locator('#trend-chart-status')).toContainText('No chart is available because');
   await expect(page.locator('#trend-evidence')).not.toHaveAttribute('open');
   await expect(page.locator('#semGwaChart')).toBeHidden();
   return;
  }
  await expect(page.locator('#trend-chart-status')).toContainText('Open the evidence table');
  const result=await page.evaluate(()=>{const p=JSON.parse(document.getElementById('student-trend-data').textContent);return {points:p,line:Chart.getChart('semGwaChart').data.datasets[0].showLine};});
  expect(result.line).toBe(false);
  if(account==='trend_no_grades')expect(result.points.every(p=>p.value===null)).toBe(true);
  if(account==='trend_prelim_only'){expect(result.points.find(p=>p.type==='Current Preliminary snapshot').value).not.toBeNull();expect(result.points.find(p=>p.type==='Current Midterm snapshot').value).toBeNull();}
  if(account==='trend_history_only')expect(result.points.filter(p=>p.zone==='history'&&p.value!==null)).toHaveLength(1);
  if(account==='trend_projection_only'){expect(result.points.find(p=>p.zone==='estimate').value).toBe(2.15);expect(result.points.filter(p=>p.zone==='history')).toHaveLength(0);}
  if(account==='analytics_student_b')expect(result.points.filter(p=>p.zone==='history').at(-1).value).toBeNull();
 });
}
test('authenticated own-record scope, foreign identifiers and cross-role denial',async({page})=>{
 await transport(page);await page.goto(base+'/student/trend.php');await expect(page).toHaveURL(/login.php/);await login(page,'trend_prelim_only');await trend(page);
 const own=await page.locator('#student-trend-data').textContent();
 await page.goto(base+'/student/trend.php?student_id=203&user_id=203&id=203');expect(await page.locator('#student-trend-data').textContent()).toBe(own);
 for(const route of ['admin/index.php','faculty/index.php']){await page.goto(base+'/'+route);await expect(page).toHaveURL(/login.php\?error=unauthorized/);}
 await page.goto(base+'/logout.php');await page.goto(base+'/student/trend.php');await expect(page).toHaveURL(/login.php/);
});

for(const [account,expected] of [
 ['trend_summer','Your GWA increased by 0.50 points (25.00%). Comparison: SY 2023-2024 — Summer to SY 2024-2025 — Summer.'],
 ['trend_lone_summer','Change unavailable'],
 ['trend_undated','Change unavailable']
]) test(`authenticated period correction ${account}`,async({page})=>{
 await transport(page);await login(page,account);await trend(page);
 await expect(page.locator('.trend-change')).toContainText(expected);
 const points=JSON.parse(await page.locator('#student-trend-data').textContent());
 if(account==='trend_undated'){
  await expect(page.locator('.stat-card').first().locator('.trend-value')).toHaveText('2.00');
  expect(points.find(p=>p.key==='Unknown|1')).toMatchObject({value:null,state:'Unavailable',period:'Academic period unavailable'});
 }
});

for(const role of ['admin','faculty'])test(`${role} denied from Student routes`,async({page})=>{
 await page.goto(base+'/login.php');await page.locator('#userid').fill(role==='admin'?'analytics_admin':'analytics_faculty_a');
 await page.locator('#password').fill(state.password);await page.getByRole('button',{name:/sign in|log in|login/i}).click();
 await expect(page).toHaveURL(new RegExp(role+'/index.php'));
 for(const route of ['student/trend.php','student/dashboard.php','student/academic_analytics.php','student/enrolment_planning.php']){
  await page.goto(base+'/'+route);await expect(page).toHaveURL(/login.php\?error=unauthorized/);
 }
});

for(const role of ['admin','faculty'])test(`${role} shared analytics smoke`,async({page})=>{
 await transport(page);await page.goto(base+'/login.php');await page.locator('#userid').fill(role==='admin'?'analytics_admin':'analytics_faculty_a');
 await page.locator('#password').fill(state.password);await page.getByRole('button',{name:/sign in|log in|login/i}).click();
 await expect(page).toHaveURL(new RegExp(role+'/index.php'));
 const response=await page.goto(base+'/'+(role==='admin'?'admin/analytics.php':'faculty/assigned_analytics.php'));
 expect(response.status()).toBe(200);await expect(page.locator('#analytics-results')).toBeVisible();
 expect(await page.locator('body').innerText()).not.toMatch(/Fatal error|SQLSTATE/);
});

test('private PDF prerequisite fails safely during authenticated preview',async({page})=>{
 await transport(page);await login(page,'trend_planning');await page.goto(base+'/student/enrolment_planning.php');
 const data=await page.locator('#pef-cc-planning-form').evaluate(form=>new URLSearchParams(new FormData(form)).toString());
 const response=await page.request.post(base+'/student/enrolment_planning.php',{data,headers:{'Content-Type':'application/x-www-form-urlencoded'}});
 expect(response.status()).toBe(503);
 const html=await response.text();expect(html).toContain('The approved planning template is unavailable.');
 expect(html).not.toMatch(/C:[\\/]|ProgramData|password_hash|SQLSTATE|Fatal error/);
 expect(html).not.toContain('id="pef-cc-download-form"');
});

test('authenticated manual planning retains keyboard focus and selected IDs on unavailable preview',async({page})=>{
 await transport(page);await login(page,'trend_manual_plan');await page.goto(base+'/student/enrolment_planning.php');
 const choice=page.locator('#pef-cc-subjects input[type="checkbox"]').first();
 await expect(choice).toBeVisible();await choice.focus();await page.keyboard.press('Space');
 await expect(choice).toBeChecked();await expect(choice).toBeFocused();
 expect(await choice.evaluate(el=>getComputedStyle(el.closest('label')).outlineStyle)).not.toBe('none');
 const selected=await choice.getAttribute('value');
 const data=await page.locator('#pef-cc-planning-form').evaluate(form=>new URLSearchParams(new FormData(form)).toString());
 const response=await page.request.post(base+'/student/enrolment_planning.php',{data,headers:{'Content-Type':'application/x-www-form-urlencoded'}});
 expect(response.status()).toBe(503);
 const html=await response.text();expect(html).toContain(`value="${selected}" checked`);
 expect(html).not.toContain('id="pef-cc-download-form"');
});

test('Student prediction range and trend cohort remain explicitly unavailable',async({page})=>{
 await transport(page);await login(page);await page.goto(base+'/student/academic_analytics.php');
 await expect(page.locator('#analytics-results')).toBeVisible();
 await expect(page.locator('[data-metric="lower"]')).toHaveText('Unavailable');
 await expect(page.locator('[data-metric="upper"]')).toHaveText('Unavailable');
 await expect(page.getByText('Decision Tree Regressor',{exact:true})).toBeVisible();
 await trend(page);await page.getByText('About this chart',{exact:true}).click();
 await expect(page.getByText('Comparable cohort average unavailable pending an approved cohort calculation and privacy contract.',{exact:true})).toBeVisible();
});
test('live Grades tooltip, calculator Reset/Close, Home and Student navigation regression',async({page})=>{
 await transport(page);const errors=[];page.on('pageerror',e=>errors.push(e.message));await login(page,'trend_prelim_only');
 await page.goto(base+'/student/grades.php');const badge=page.locator('.grades-page .custom-tooltip').first();await badge.focus();
 const tip=page.locator('.grades-risk-tooltip');await expect(tip).toBeVisible();const box=await tip.boundingBox();expect(box.y).toBeGreaterThanOrEqual(0);await page.keyboard.press('Escape');await expect(tip).toHaveCount(0);
 await page.goto(base+'/student/dashboard.php');await page.getByRole('button',{name:'Open Grade Goal Calculator'}).click();
 const row=page.locator('.calc-row').first(),select=row.locator('.calc-final-input');
 await select.selectOption('3.00');await expect(row.locator('.calc-midterm')).not.toHaveValue('');await expect(row.locator('.calc-midterm')).toHaveAttribute('readonly','');
 await page.getByRole('button',{name:'Reset',exact:true}).click();await expect(select).toHaveValue('');await expect(row.locator('.calc-midterm')).toHaveValue('');await expect(row.locator('.calc-prefinal')).toHaveValue('');await expect(row.locator('.calc-prelim')).toHaveValue('89');await expect(row.locator('.calc-prelim')).toBeDisabled();
 await row.locator('.calc-midterm').fill('90');await page.getByRole('button',{name:'Close',exact:true}).click();await page.getByRole('button',{name:'Open Grade Goal Calculator'}).click();await expect(row.locator('.calc-midterm')).toHaveValue('');
 for(const route of ['student/index.php','student/feedback.php']){const response=await page.goto(base+'/'+route);expect(response.status()).toBe(200);expect(await page.locator('body').innerText()).not.toMatch(/Fatal error|SQLSTATE/);}
 await expect(page.getByRole('link',{name:'Enrollment Planning',exact:true})).toHaveAttribute('href',/enrolment_planning.php/);
 expect(errors).toEqual([]);
});
test('no-canvas/library fallback retains exact evidence table',async({page})=>{
 await page.route('https://cdn.jsdelivr.net/npm/chart.js',r=>r.fulfill({contentType:'application/javascript',body:'/* intentional unavailable chart dependency test */'}));
 await login(page,'trend_prelim_only');await page.goto(base+'/student/trend.php');await expect(page.locator('#trend-chart-status')).toContainText('Chart unavailable');await page.locator('.trend-evidence-toggle').click();await expect(page.getByRole('table')).toBeVisible();await expect(page.getByRole('cell',{name:/Current Preliminary progress/})).toBeVisible();
});
test('Dashboard and Academic Review derive MODERATE from 2.15 despite stale saved LOW',async({page})=>{
 await transport(page);await login(page,'trend_risk_mismatch');await page.goto(base+'/student/dashboard.php');
 const dashboard=await page.locator('main,.main-content').last().innerText();expect(dashboard).toContain('2.15');expect(dashboard).toContain('MODERATE');expect(dashboard).not.toContain('2.50 or above');
 await page.goto(base+'/student/academic_analytics.php');await expect(page.locator('[data-metric="estimate"]')).toHaveText('2.15');
 const review=await page.locator('body').innerText();expect(review).toContain('MODERATE');
 writeFileSync(resolve(evidence,'risk-investigation.json'),JSON.stringify({classification:'CORRECTION VERIFIED under controlled synthetic saved mismatch',estimate:2.15,savedRisk:'LOW',dashboardRisk:'MODERATE',reviewRisk:'MODERATE',database:state.database,existingLocalRecordsInspected:false,correctionPerformed:true},null,2));
});
test('Student analytics API rejects foreign identifiers and support filters',async({page})=>{
 await transport(page);await login(page,'trend_prelim_only');
 for(const filter of ['student_id=203','case_id=1','faculty=11']){const response=await page.request.get(base+'/api/academic_analytics.php?'+filter);expect(response.status()).toBe(422);expect(await response.json()).toHaveProperty('error');}
});
test('Enrollment Planning GET availability (no exports or form submission)',async({page})=>{
 await transport(page);await login(page,'trend_planning');const response=await page.goto(base+'/student/enrolment_planning.php');

 expect(response.status()).toBe(200);await expect(page.getByRole('heading',{name:/Enrollment Planning/i}).first()).toBeVisible();
});
test('Dashboard and Academic Review select the same latest prediction when timestamps tie',async({page})=>{
 await transport(page);await login(page,'analytics_student_a');await page.goto(base+'/student/dashboard.php');
 const estimate=page.locator('.stat-card').filter({has:page.getByRole('heading',{name:/Projected Semester GWA/})});
 const risk=page.locator('.stat-card').filter({has:page.getByRole('heading',{name:'Overall Academic Risk',exact:true})});
 await expect(estimate.locator('h2')).toHaveText('3.25');await expect(risk.locator('h2')).toHaveText('LOW');
 await page.goto(base+'/student/academic_analytics.php');await expect(page.locator('[data-metric="estimate"]')).toHaveText('3.25');
});
for(const [width,height] of [[1440,900],[1280,600],[1024,768]])for(const theme of ['light','dark']){
 test(`Enrollment Planning pending-grade desktop ${width}x${height} ${theme}`,async({page})=>{
  await transport(page);await page.setViewportSize({width,height});await page.emulateMedia({reducedMotion:'reduce'});const errors=[];page.on('pageerror',error=>errors.push(error.message));
  await login(page,'trend_planning');const response=await page.goto(base+'/student/enrolment_planning.php');expect(response.status()).toBe(200);
  await page.locator('html').evaluate((el,theme)=>{el.dataset.theme=theme;el.dataset.motion='reduce';},theme);
  await expect(page.getByTestId('final-grades-meta')).toHaveText(/^0 of [1-9]/);await expect(page.getByTestId('term-gwa')).not.toHaveText('0.00');
  const toggle=page.getByTestId('view-current-semester-btn');await toggle.focus();await page.keyboard.press('Enter');await expect(toggle).toHaveAttribute('aria-expanded','true');
  await expect(page.getByTestId('current-semester-details')).toBeVisible();await expect(page.getByTestId('current-semester-details')).toContainText('ITE321');
  expect(await page.evaluate(()=>document.documentElement.scrollWidth>innerWidth)).toBe(false);expect(errors).toEqual([]);
 });
}
test('Enrollment Planning rejects another Student identifier without leaking their details',async({page})=>{
 await transport(page);await login(page,'trend_planning');const response=await page.goto(base+'/student/enrolment_planning.php?student_id=203');expect(response.status()).toBe(422);
 await expect(page.locator('body')).toContainText('Unexpected request fields');await expect(page.locator('body')).not.toContainText('trend_dense_history');
});
