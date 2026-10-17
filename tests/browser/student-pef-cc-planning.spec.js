import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';

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
async function preview(page, ids = []) {
  await page.locator('[name="evaluation_year"]').selectOption(state.fixture.evaluation_year);
  await page.locator('[name="evaluation_semester"]').selectOption(String(state.fixture.evaluation_semester));
  await page.locator('[name="target_year"]').fill(state.fixture.target_year);
  await page.locator('[name="target_semester"]').selectOption(String(state.fixture.target_semester));
  await page.locator('[name="section"]').fill('SYN-A');
  await page.locator('#pef-cc-subjects').selectOption(ids.map(String));
  await Promise.all([page.waitForLoadState('domcontentloaded'), page.getByRole('button', { name: 'Preview planning copy', exact: true }).click()]);
  await expect(page.getByTestId('pef-preview')).toBeVisible();
}

for (const account of ['first', 'second', 'third', 'fourth', 'irregular']) {
  test(`${account} synthetic student can manually preview without curriculum/pacing inference`, async ({ page }) => {
    await login(page, account); await preview(page, state.fixture.subjects.slice(0, 2));
    await expect(page.getByTestId('recorded-count')).toContainText(String(state.fixture.accounts[account].count));
    await expect(page.getByTestId('proposed-count')).toContainText('2');
    await expect(page.getByTestId('cc-preview')).toContainText('Reference / Requires Review');
    await expect(page.getByTestId('pef-preview')).toContainText(state.fixture.accounts[account].login);
    await expect(page.getByRole('button', { name: 'Download Excel', exact: true })).toBeEnabled();
    await expect(page.locator('input[name="gwa"],input[name="student_id"],input[name="standing"],input[name="professor"]')).toHaveCount(0);
    await expect(page.getByText('Prototype performance on synthetic academic records', { exact: false }).first()).toBeVisible();
  });
}
test('editing planning fields invalidates preview and restores update action', async ({ page }) => {
  await login(page); await preview(page);
  await page.locator('#pef-cc-planning-form [name="section"]').fill('UPDATED');
  await expect(page.getByTestId('preview-invalidated')).toBeVisible();
  await expect(page.getByRole('button', { name: 'Download Excel', exact: true })).toBeDisabled();
  await expect(page.getByRole('button', { name: 'Update preview', exact: true })).toBeEnabled();
});
test('all-INC and over-capacity records remain visible with distinct availability states', async ({ page }) => {
  await login(page, 'allinc'); await preview(page);
  await expect(page.getByTestId('term-gwa')).toContainText(/Unavailable/i);
  await expect(page.getByRole('button', { name: 'Download Excel', exact: true })).toBeEnabled();
  await login(page, 'overflow');
  await page.locator('[name="evaluation_year"]').selectOption(state.fixture.evaluation_year);
  await page.locator('[name="evaluation_semester"]').selectOption('2');
  await page.locator('[name="target_year"]').fill(state.fixture.target_year);
  await page.getByRole('button', { name: 'Preview planning copy', exact: true }).click();
  await expect(page.getByTestId('recorded-count')).toContainText('14');
  await expect(page.getByTestId('preview-errors')).toContainText(/13/);
  await expect(page.getByRole('button', { name: 'Download Excel', exact: true })).toHaveCount(0);
});
test('server-rendered preview remains functional with JavaScript disabled', async ({ browser }) => {
  requireScratch();const context=await browser.newContext({ javaScriptEnabled:false });
  try{const page=await context.newPage();await login(page);await preview(page);await expect(page.locator('#pef-cc-download-form')).toBeVisible();}finally{await context.close();}
});
test('empty academic history keeps reference CC visible and PEF action unavailable',async({page})=>{
 await login(page,'empty');await expect(page.getByTestId('no-completed-term')).toBeVisible();
 await expect(page.getByTestId('cc-preview')).toContainText('Reference / Requires Review');
 await expect(page.getByRole('button',{name:'Preview planning copy',exact:true})).toBeDisabled();
 await expect(page.locator('#pef-cc-download-form')).toHaveCount(0);
});
test('markup-looking section is rendered as literal planning text',async({page})=>{
 await login(page);const text='<img src=x onerror=window.pefInjection=true>';
 await page.locator('[name="evaluation_year"]').selectOption(state.fixture.evaluation_year);await page.locator('[name="evaluation_semester"]').selectOption('2');
 await page.locator('[name="target_year"]').fill(state.fixture.target_year);await page.locator('[name="section"]').fill(text);
 await page.getByRole('button',{name:'Preview planning copy',exact:true}).click();
 await expect(page.getByTestId('pef-preview')).toContainText(text);await expect(page.getByTestId('pef-preview').locator('img[src="x"]')).toHaveCount(0);
 expect(await page.evaluate(()=>window.pefInjection)).toBeUndefined();
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
 await expect(page.getByRole('button',{name:'Download Excel',exact:true})).toBeEnabled();
});
const sizes = [[1440,900],[1280,720],[1024,768],[768,1024],[390,844],[360,800],[390,700],[844,390],[1280,600]];
for (const theme of ['light','dark']) for (const [width,height] of sizes) {
  test(`${theme} ${width}x${height}: contained tables, labels, navigation and focus`, async ({ page }) => {
    const errors=[];page.on('pageerror',error=>errors.push(error.message));
    await page.setViewportSize({width,height});
    await page.addInitScript(t=>localStorage.setItem('theme',t),theme);
    await login(page);await preview(page,state.fixture.subjects.slice(0,2));
    await expect(page.locator('html')).toHaveAttribute('data-theme',theme);
    expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth+1)).toBe(true);
    for(const name of ['evaluation_year','evaluation_semester','target_year','target_semester','section']) {
      const field=page.locator(`#pef-cc-planning-form [name="${name}"]`);
      await expect(field).toHaveAccessibleName(/\S/);
      if(width<=768) expect((await field.boundingBox()).height).toBeGreaterThanOrEqual(44);
    }
    const primary=page.getByRole('button',{name:'Download Excel',exact:true});await primary.focus();
    expect(await primary.evaluate(e=>{const s=getComputedStyle(e);return s.outlineStyle!=='none'||s.boxShadow!=='none';})).toBe(true);
    await expect(page.getByRole('link',{name:'Back to Grades',exact:true})).toBeVisible();
    expect(errors).toEqual([]);
  });
}
test('reduced motion preserves readable preview and keyboard submission',async({page})=>{
 await page.emulateMedia({reducedMotion:'reduce'});await login(page);await preview(page);
 await page.locator('#pef-cc-planning-form [name="section"]').focus();await page.keyboard.press('Tab');
 expect(await page.evaluate(()=>document.activeElement!==document.body)).toBe(true);
 await expect(page.getByTestId('cc-preview')).toBeVisible();
});
