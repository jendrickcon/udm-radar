import { test, expect } from '@playwright/test';
import { readFileSync, readdirSync } from 'node:fs';
import { resolve } from 'node:path';

const stateFile = process.env.PEF_CC_STATE_FILE;
const state = stateFile ? JSON.parse(readFileSync(stateFile, 'utf8')) : null;
test.skip(!state, 'Requires guarded scratch-only pef_cc_test_server.mjs state; never normal Apache/live URL.');
function requireScratch() {
  if (process.env.DB_NAME !== 'udm_radar_scratch' || state?.fixture.database !== 'udm_radar_scratch' || !/^http:\/\/127\.0\.0\.1:\d+\/udm-radar\/$/.test(state?.base_url || '')) throw new Error('DATABASE SAFETY BLOCKED: explicit guarded scratch state required');
}
async function login(page, account = 'first') {
  requireScratch();
  await page.goto(state.base_url + 'login.php',{waitUntil:'domcontentloaded'});
  await page.locator('#userid').fill(state.fixture.accounts[account].login);
  await page.locator('#password').fill(state.fixture.password);
  await Promise.all([page.waitForURL('**/student/index.php',{waitUntil:'domcontentloaded'}), page.getByRole('button', { name: 'Login to Portal' }).click()]);
  await page.goto(state.base_url + 'student/enrolment_planning.php',{waitUntil:'domcontentloaded'});
}
async function preview(page,ids=[]){
  if(await page.locator('#pef-cc-subjects').count())await page.locator('#pef-cc-subjects').selectOption(ids.map(String));
  await page.getByTestId('preview-button').click();await expect(page.getByTestId('pef-preview')).toBeVisible();
}
for (const account of ['first', 'second', 'third', 'fourth', 'irregular']) {
  test(`${account} synthetic Irregular student retains reviewed manual preview`, async ({ page }) => {
    await login(page, account); await preview(page, state.fixture.subjects.slice(0, 2));
    const safeDisplayName = `StudentPEFCC_${state.fixture.token.toUpperCase()}_${account.toUpperCase()}, SYNTHETIC`;
    const previewText = await page.getByTestId('pef-preview').innerText();
    await expect(page.getByTestId('recorded-count')).toContainText(String(state.fixture.accounts[account].count));
    await expect(page.getByTestId('proposed-count')).toContainText('2');
    await expect(page.getByTestId('cc-preview')).toHaveCount(0); // Full CC table is intentionally absent from the guided UI.
    await expect(page.getByTestId('pef-preview')).toContainText(safeDisplayName);
    await expect(page.getByTestId('pef-preview')).toContainText('Planning modeReview required');
    await expect(page.getByTestId('pef-preview')).toContainText('Evaluation term2025-2026 · Second Semester');
    await expect(page.getByTestId('pef-preview')).toContainText('Suggested sectionIT-IRREG');
    expect(previewText).not.toContain(state.fixture.accounts[account].login);
    expect(previewText).not.toMatch(/[\w.+-]+@[\w.-]+\.[A-Za-z]{2,}/);
    expect(previewText).not.toContain(String(state.fixture.accounts[account].id));
    expect(previewText).not.toContain(state.fixture.password);
    await expect(page.getByTestId('download-button')).toBeEnabled();
    await expect(page.locator('input[name="gwa"],input[name="student_id"],input[name="standing"],input[name="professor"]')).toHaveCount(0);
    await expect(page.getByText('Prototype performance on synthetic academic records', { exact: false })).toHaveCount(1);
  });
}

for (const account of ['regular1', 'regular2', 'regular3', 'regular4']) {
  test(`${account}: authenticated nominal plan uses coherent current-term evidence`, async ({ page }) => {
    await login(page, account);
    const expected = state.fixture.accounts[account];
    await expect(page.locator('#pef-cc-subjects')).toHaveCount(0);
    await expect(page.locator('#pef-cc-planning-form input[name="target_year"]')).toHaveAttribute('type', 'hidden');
    await expect(page.locator('#pef-cc-planning-form input[name="target_semester"]')).toHaveAttribute('type', 'hidden');
    if (!expected.expected_exportable) {
      await expect(page.getByTestId('preview-button')).toBeDisabled();
      await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
      return;
    }
    await expect(page.locator('#pef-cc-planning-form input[name="target_year"]')).toHaveValue(expected.inputs.target_year);
    await expect(page.locator('#proposed-section')).toHaveValue(expected.inputs.section);
    await expect(page.locator('#proposed-section')).toHaveAttribute('type', 'hidden');
    await expect(page.getByTestId('nominal-subjects').locator('tbody tr:not(:has(.empty-state))')).toHaveCount(expected.inputs.subject_ids.length);
    expect(state.fixture.accounts[account].section).toBe(expected.section);
    await page.getByTestId('preview-button').click();
    await expect(page.getByTestId('pef-preview')).toBeVisible();
    await expect(page.getByTestId('term-gwa')).toHaveText(expected.expected_gwa_display);
    await expect(page.getByTestId('download-button')).toBeEnabled();
    await expect(page.getByTestId('pef-preview')).toContainText(/subjects/i);
    await expect(page.getByTestId('single-disclosure')).toContainText(/evaluator review/i);
  });
}

for (const account of ['legacy_cy', 'legacy_ds']) {
  test(`${account}: unresolved or historical context has no automatic plan`, async ({ page }) => {
    await login(page, account);
    await expect(page.getByTestId('preview-button')).toBeDisabled();
    await expect(page.getByTestId('blocked-planning-banner')).toContainText(/review/i);
    await expect(page.locator('#pef-cc-subjects')).toHaveCount(0);
    await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
    await expect(page.getByTestId('download-button')).toHaveCount(0);
  });
}

test('regular hidden-field target and subject overrides are rejected by real preview POST', async ({ page }) => {
  await login(page, 'regular2');
  test.skip(!state.fixture.accounts.regular2.expected_exportable, 'Actual future section availability requires review in this term.');
  const fields = await page.locator('#pef-cc-planning-form').evaluate(form => Array.from(new FormData(form)));
  for (const attack of ['delete_subjects', 'replace_section', 'replace_target', 'irregular_marker']) {
    const body = new URLSearchParams(fields);
    if (attack === 'delete_subjects') body.delete('subject_ids[]');
    if (attack === 'replace_section') body.set('section', 'IT-29');
    if (attack === 'replace_target') body.set('target_semester',body.get('target_semester')==='1'?'2':'1');
    if (attack === 'irregular_marker') body.set('section', 'IT-IRREG');
    const response = await page.request.post(state.base_url + 'student/enrolment_planning.php', {
      data: body.toString(), headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
    });
    expect(response.status()).toBe(422);
    expect(await response.text()).not.toContain('id="pef-cc-download-form"');
  }
});

test('regular nominal controls remain usable on mobile in both themes', async ({ browser }) => {
  for (const theme of ['light', 'dark']) {
    const context = await browser.newContext({ viewport: { width: 390, height: 700 } });
    await context.addInitScript(t => {
      localStorage.setItem('udmRadarDisplayPreferencesV1', JSON.stringify({
        colorStyle: 'ccs',
        theme: t,
        fontSize: 'default',
        motion: 'system',
        underlineLinks: false,
      }));
    }, theme);
    const page = await context.newPage();
    await login(page, 'regular3');
    await expect(page.locator('html')).toHaveAttribute('data-theme', theme);
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1)).toBe(true);
    await expect(page.locator('#pef-cc-planning-form input[name="target_semester"]')).toHaveAttribute('type','hidden');
    await page.getByRole('link', { name: 'Back to Grades', exact: true }).focus();
    await expect(page.getByRole('link', { name: 'Back to Grades', exact: true })).toBeFocused();
    await context.close();
  }
});
test('editing planning fields invalidates preview and restores update action', async ({ page }) => {
  await login(page); await preview(page);
  await page.locator('#proposed-section').selectOption('');
  await expect(page.getByTestId('preview-invalidated')).toBeVisible();
  await expect(page.getByTestId('download-button')).toBeDisabled();
  await expect(page.getByTestId('refresh-preview-button')).toBeEnabled();
});
test('all-INC and over-capacity records remain visible with distinct availability states', async ({ page }) => {
  await login(page, 'allinc'); await preview(page);
  await expect(page.getByTestId('term-gwa')).toHaveText('Pending');
  await expect(page.getByTestId('download-button')).toBeEnabled();
  await login(page, 'overflow');
  await expect(page.getByTestId('preview-button')).toBeDisabled();
  await expect(page.locator('.pef-cc-banner-alert')).toContainText(/13/);
  await expect(page.getByTestId('download-button')).toHaveCount(0);
});
test('server-rendered preview remains functional with JavaScript disabled', async ({ browser }) => {
  requireScratch();const context=await browser.newContext({ javaScriptEnabled:false });
  try{const page=await context.newPage();await login(page);await preview(page);await expect(page.locator('#pef-cc-download-form')).toBeAttached();await expect(page.getByTestId('download-button')).toBeEnabled();}finally{await context.close();}
});
test('empty academic history keeps reference CC visible and PEF action unavailable',async({page})=>{
 await login(page,'empty');await expect(page.getByTestId('blocked-planning-banner')).toBeVisible();expect(state.fixture.accounts.empty.count).toBe(0);
 await expect(page.getByTestId('cc-preview')).toHaveCount(0); // Full CC table is intentionally absent from the guided UI.
 await expect(page.getByTestId('preview-button')).toBeDisabled();
 await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
});
test('markup-looking section is rejected with safe choices retained',async({page})=>{
 await login(page);const text='<img src=x onerror=window.pefInjection=true>';await page.locator('#proposed-section').selectOption('IT-IRREG');
 await page.locator('#pef-cc-subjects').selectOption(state.fixture.subjects.slice(0,2).map(String));
 await page.route('**/student/enrolment_planning.php',async route=>{
  if(route.request().method()!=='POST')return route.continue();
  const body=new URLSearchParams(route.request().postData());body.set('section',text);await route.continue({postData:body.toString()});
 });
 await page.getByTestId('preview-button').click();
 await expect(page.locator('.pef-cc-banner-alert')).toContainText('not available for this planning context');
 await expect(page.locator('#proposed-section')).toHaveAttribute('aria-invalid','true');
 await expect(page.locator('#pef-cc-subjects option:checked')).toHaveCount(2);
 await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
 await expect(page.locator('img[src="x"]')).toHaveCount(0);
 expect(await page.evaluate(()=>window.pefInjection)).toBeUndefined();
});
test('arbitrary 96-character section gets a field error without a preview or export',async({page})=>{
 await page.setViewportSize({width:390,height:844});await login(page);
 await page.locator('#pef-cc-subjects').selectOption(state.fixture.subjects.slice(0,2).map(String));
 const temp=process.env.PEF_CC_TEMP_PATH;const before=temp?readdirSync(temp).sort():null;
 await page.route('**/student/enrolment_planning.php',async route=>{
  if(route.request().method()!=='POST')return route.continue();
  const body=new URLSearchParams(route.request().postData());body.set('section','W'.repeat(96));await route.continue({postData:body.toString()});
 });
 const pending=page.waitForResponse(r=>r.url().endsWith('/student/enrolment_planning.php')&&r.request().method()==='POST');
 await page.getByTestId('preview-button').click();expect((await pending).status()).toBe(422);
 await expect(page.locator('.pef-cc-banner-alert')).toContainText('not available for this planning context');
 await expect(page.locator('#proposed-section')).toHaveAttribute('aria-describedby',/section-error/);
 await expect(page.locator('#pef-cc-subjects option:checked')).toHaveCount(2);
 await expect(page.locator('#pef-cc-planning-form input[name="target_year"]')).toHaveValue(state.fixture.target_year);
 await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
 expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
 if(temp)expect(readdirSync(temp).sort()).toEqual(before);
 if(process.env.PEF_CC_REVIEW_EVIDENCE_DIR){await page.locator('.pef-cc-banner-alert').scrollIntoViewIfNeeded();await page.screenshot({path:resolve(process.env.PEF_CC_REVIEW_EVIDENCE_DIR,'excessive-section-mobile.png')});}
});
test('thirteen proposed choices stay selected with visible native capacity validation',async({page})=>{
 await login(page);await page.locator('#pef-cc-subjects').selectOption(state.fixture.subjects.slice(0,13).map(String));
 await expect(page.getByTestId('proposed-count')).toHaveText('13');
 expect(await page.locator('#pef-cc-subjects').evaluate(e=>e.selectedOptions.length)).toBe(13);
 expect(await page.locator('#pef-cc-subjects').evaluate(e=>e.validity.customError)).toBe(true);
 await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
});
test('mobile drawer coexists with preview and returns keyboard focus',async({page})=>{
 await page.setViewportSize({width:390,height:700});await login(page);await preview(page);
 const toggle=page.locator('#mobile-nav-toggle');await toggle.click();await expect(toggle).toHaveAttribute('aria-expanded','true');
 await expect(page.locator('#sidebar').getByRole('link',{name:'Enrolment Planning',exact:true})).toBeVisible();
 await page.keyboard.press('Escape');await expect(toggle).toHaveAttribute('aria-expanded','false');await expect(toggle).toBeFocused();
 await expect(page.getByTestId('download-button')).toBeEnabled();
});
const sizes = [[1440,900],[1280,720],[1024,768],[768,1024],[390,844],[360,800],[390,700],[844,390],[1280,600]];
for (const theme of ['light','dark']) for (const [width,height] of sizes) {
  test(`${theme} ${width}x${height}: contained tables, labels, navigation and focus`, async ({ page }) => {
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.setViewportSize({width,height});
    await page.addInitScript(t=>localStorage.setItem('udmRadarDisplayPreferencesV1',JSON.stringify({
      colorStyle:'ccs',theme:t,fontSize:'default',motion:'system',underlineLinks:false,
    })),theme);
    await login(page);await preview(page,state.fixture.subjects.slice(0,2));
    await expect(page.locator('html')).toHaveAttribute('data-theme',theme);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
    for(const name of ['section']) {
      const field=page.locator(`#proposed-section`);
      await expect(field).toHaveAccessibleName(/\S/);
      if(width<=768) expect((await field.boundingBox()).height).toBeGreaterThanOrEqual(44);
    }
    const primary=page.getByTestId('download-button');await primary.focus();
    expect(await primary.evaluate(e=>{const s=getComputedStyle(e);return s.outlineStyle!=='none'||s.boxShadow!=='none';})).toBe(true);
    await expect(page.getByRole('link',{name:'Back to Grades',exact:true})).toBeVisible();
    expect(errors).toEqual([]);
  });
}
test('reduced motion preserves readable preview and keyboard submission',async({page})=>{
 await page.emulateMedia({reducedMotion:'reduce'});await login(page);await preview(page);
 await page.locator('#proposed-section').focus();await page.keyboard.press('Tab');
 expect(await page.evaluate(()=>document.activeElement!==document.body)).toBe(true);
 await expect(page.getByTestId('pef-preview')).toBeVisible();
});

test('safe unresolved Regular context is C with no invented planning values',async({page})=>{
 await login(page,'regular_missing');await expect(page.getByTestId('planning-status')).toHaveText('Review required');await expect(page.getByTestId('preview-button')).toBeEnabled();
 await expect(page.locator('#proposed-section')).toHaveValue('');await preview(page);await expect(page.getByTestId('download-button')).toHaveText('Download review planning PDF');
});
