import {test,expect} from '@playwright/test';
import {execFileSync} from 'node:child_process';
import {readFileSync,mkdirSync} from 'node:fs';
import {resolve} from 'node:path';
const evidence=resolve('logs/goal-feasibility/screenshots');
mkdirSync(evidence,{recursive:true});
async function mount(page,scenario='initial',width=1440,theme='light',large=false){
 const errors=[];page.on('pageerror',e=>errors.push(e.message));
 await page.route('**/*',r=>{errors.push('Unexpected network request');return r.abort();});
 await page.setViewportSize({width,height:width<500?844:900});await page.emulateMedia({reducedMotion:'reduce'});
 const html=execFileSync(process.env.PHP_BINARY||'C:/xampp/php/php.exe',[resolve('tests/fixtures/goal_calculator_render.php'),scenario],{encoding:'utf8'});
 await page.setContent(html);await page.addStyleTag({content:readFileSync(resolve('assets/css/accessibility-preferences.css'),'utf8')});
 await page.locator('html').evaluate((e,v)=>{e.dataset.theme=v.theme;e.dataset.colorStyle='ccs';e.dataset.fontSize=v.large?'extra-large':'default';e.dataset.motion='reduce';},{theme,large});
 await page.getByRole('button',{name:'Open Grade Goal Calculator'}).click();return errors;
}
test('initial native choices and descriptions; no automatically selected academic goal',async({page})=>{
 const errors=await mount(page);const select=page.locator('.calc-final-input');
 await expect(select.locator('option:not([value=""])')).toHaveCount(13);await expect(select.locator('option:disabled')).toHaveCount(0);await expect(select).toHaveValue('');
 await expect(select).toHaveAttribute('aria-describedby',/calculator-goal-help/);await expect(page.locator('.calc-goal-status')).toHaveAttribute('aria-live','polite');
 await expect(page.locator('.calc-goal-status')).toContainText('4.00');await page.screenshot({path:resolve(evidence,'initial.png'),fullPage:true});expect(errors).toEqual([]);
});
test('limited evidence leaves impossible options visible but disabled, tampering rejected',async({page})=>{
 const errors=await mount(page,'limited');const select=page.locator('.calc-final-input');await expect(select.locator('option[value="4.00"]')).toBeDisabled();await expect(select.locator('option[value="2.25"]')).toBeEnabled();
 await expect(select.locator('option[value="4.00"]')).toHaveText('4.00');await expect(select.locator('option[value="4.00"]')).toHaveAttribute('title',/Not mathematically reachable/);await expect(page.locator('.calc-goal-status')).toContainText('2.25');
 await select.focus();await page.keyboard.press('Alt+ArrowDown');await page.screenshot({path:resolve(evidence,'some-disabled.png'),fullPage:true});await page.keyboard.press('Escape');
 await select.evaluate(e=>{e.querySelector('option[value="4.00"]').disabled=false;e.value='4.00';e.dispatchEvent(new Event('change',{bubbles:true}));});
 await expect(select).toHaveValue('');await expect(page.locator('.calc-midterm')).toHaveValue('');await expect(page.locator('.calc-row-result')).toContainText('not reachable');expect(errors).toEqual([]);
});
test('changing evidence clears the invalid goal and stale suggestions, preserving a valid goal',async({page})=>{
 const errors=await mount(page);const select=page.locator('.calc-final-input');await page.locator('.calc-prelim').fill('90');await select.selectOption('3.75');await expect(page.locator('.calc-midterm')).toHaveValue('100');
 await page.locator('.calc-prelim').fill('89.99');await expect(select).toHaveValue('');await expect(page.locator('.calc-midterm')).toHaveValue('');await expect(page.locator('.calc-prefinal')).toHaveValue('');await expect(page.locator('#projected-gwa')).toHaveText('N/A');await expect(page.locator('.calc-row-result')).toContainText('Choose another');
 await page.screenshot({path:resolve(evidence,'selection-cleared.png'),fullPage:true});
 await select.selectOption('3.00');await page.locator('.calc-prelim').fill('90');await expect(select).toHaveValue('3.00');expect(errors).toEqual([]);
});
test('completed and explicit unresolved evidence are handled without future calculations',async({page})=>{
 await mount(page,'completed');await page.locator('.calc-final-input').selectOption('3.00');await expect(page.locator('.calc-row-result')).toContainText('already achieved');await expect(page.locator('.calc-row-result')).toContainText('no remaining');
 await mount(page,'unresolved');await expect(page.locator('.calc-final-input')).toBeDisabled();await expect(page.locator('.calc-row-result')).toContainText('unresolved');await expect(page.locator('#projected-gwa')).toHaveText('N/A');
});
test('no feasible goal disables the action and announces a neutral status',async({page})=>{
 await mount(page,'no-goals');await expect(page.locator('.calc-final-input')).toBeDisabled();await expect(page.locator('.calc-row-result')).toContainText('do not permit');
 await expect(page.locator('.calc-final-input option[value="1.00"]')).toBeDisabled();
});
for(const [width,height]of[[1440,900],[1024,768],[768,1024],[390,844],[360,800],[844,600]])for(const theme of['light','dark'])for(const large of[false,true]){
 test(`layout, keyboard and readable status ${width} ${theme} ${large?'extra-large':'default'}`,async({page})=>{
  const errors=await mount(page,'boundary',width,theme,large);await page.setViewportSize({width,height});const select=page.locator('.calc-final-input');await select.focus();await page.keyboard.press('ArrowDown');await page.keyboard.press('Enter');await page.keyboard.press('Escape');await expect(select).toBeFocused();
  const geometry=await page.evaluate(()=>{const select=document.querySelector('.calc-final-input'),status=document.querySelector('.calc-goal-status');return {overflow:document.documentElement.scrollWidth>innerWidth,focus:getComputedStyle(select).outlineStyle,statusOverflow:status.scrollWidth>status.clientWidth+1,description:status.textContent,text:getComputedStyle(status).color,background:getComputedStyle(document.getElementById('calculator-section')).backgroundColor};});
  expect(geometry.overflow).toBe(false);expect(geometry.focus).not.toBe('none');expect(geometry.description).toContain('3.75');
  const statusSize=await page.locator('.calc-goal-status').boundingBox();expect(statusSize.width).toBe(1);expect(statusSize.height).toBe(1);
  expect(await select.evaluate(e=>e.clientWidth>=parseFloat(getComputedStyle(e).fontSize)*4.5)).toBe(true);
  await expect(select.locator('option[value="4.00"]')).toBeDisabled();
  if(width===390&&large&&theme==='dark'){await select.scrollIntoViewIfNeeded();await page.screenshot({path:resolve(evidence,'mobile-dark-extra-large.png'),fullPage:true});}
  expect(errors).toEqual([]);
 });
}
