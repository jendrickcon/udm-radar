import {test,expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {spawnSync} from 'node:child_process';
import {resolve} from 'node:path';
const stateFile=process.env.PEF_CC_STATE_FILE;
const state=stateFile?JSON.parse(readFileSync(stateFile,'utf8')):null;
test.skip(!state,'Requires fresh guarded scratch fixture state; no live Apache fallback');
function guard(){if(process.env.DB_NAME!=='udm_radar_scratch'||state?.fixture.database!=='udm_radar_scratch'||!/^http:\/\/127\.0\.0\.1:\d+\/udm-radar\/$/.test(state?.base_url||''))throw new Error('DATABASE SAFETY BLOCKED');}
async function login(page,account='first'){
 guard();await page.goto(state.base_url+'login.php');await page.locator('#userid').fill(state.fixture.accounts[account].login);await page.locator('#password').fill(state.fixture.password);
 await Promise.all([page.waitForURL('**/student/index.php'),page.getByRole('button',{name:'Login to Portal'}).click()]);await page.goto(state.base_url+'student/enrolment_planning.php');
}
async function preview(page){await page.getByTestId('preview-button').click();await expect(page.getByTestId('download-button')).toBeEnabled();await expect(page.locator('#pef-cc-download-form input[name="preview"]')).not.toHaveValue('');}
async function data(page){return page.locator('#pef-cc-download-form').evaluate(form=>{const result={};for(const [key,value]of new FormData(form)){if(key.endsWith('[]'))(result[key.slice(0,-2)]??=[]).push(value);else result[key]=value;}return result;});}
function encoded(form){const params=new URLSearchParams();for(const[k,v]of Object.entries(form)){if(Array.isArray(v))v.forEach(value=>params.append(k+'[]',value));else params.set(k,v);}return params.toString();}
for(const account of ['regular1','regular3','regular_missing']){
 const mode=state?.fixture.accounts[account].expected_mode||'unavailable';
 const [status,label]=({normal:['Normal','Download planning PDF'],provisional:['Provisional','Download provisional planning PDF'],review:['Review required','Download review planning PDF']})[mode]||['Unavailable','Unavailable'];
 test(`Authenticated ${account} ${status} PDF: signed preview, MIME, filename and two pages`,async({page})=>{
  await login(page,account);await expect(page.getByTestId('planning-status')).toHaveText(status);await preview(page);
  const button=page.getByTestId('download-button');await expect(button).toHaveText(label);
  const responsePromise=page.waitForResponse(r=>r.url().endsWith('/student/export_pef_cc.php'));
  const downloadPromise=page.waitForEvent('download');await button.click();const response=await responsePromise;const download=await downloadPromise;
  expect(response.status()).toBe(200);expect(response.headers()['content-type']).toBe('application/pdf');expect(download.suggestedFilename()).toBe('UDM-RADAR-PEF-CC.pdf');
  const stream=await download.createReadStream();const chunks=[];for await(const chunk of stream)chunks.push(chunk);const bytes=Buffer.concat(chunks);
  expect(bytes.subarray(0,5).toString()).toBe('%PDF-');expect(bytes.toString('latin1').match(/\/Type\s*\/Page\b/g)).toHaveLength(2);
  await expect(button).toBeEnabled();await expect(page.locator('#pef-cc-live-announcer')).toHaveText('Your planning PDF download has started.');
 });
}
test('native PDF download works without JavaScript',async({browser})=>{
 guard();const context=await browser.newContext({javaScriptEnabled:false});try{const page=await context.newPage();await login(page);await preview(page);const pending=page.waitForEvent('download');await page.getByTestId('download-button').click();expect((await pending).suggestedFilename()).toBe('UDM-RADAR-PEF-CC.pdf');}finally{await context.close();}
});
test('tampering, unknown ownership keys and public XLSX requests are rejected',async({page})=>{
 await login(page);await preview(page);const form=await data(page);
 const post=payload=>page.request.post(state.base_url+'student/export_pef_cc.php',{data:encoded(payload),headers:{'Content-Type':'application/x-www-form-urlencoded'}});
 for(const [payload,status] of [[{...form,section:'CHANGED'},409],[{...form,preview:form.preview+'x'},409],[{...form,student_id:state.fixture.accounts.second.id},422],[{...form,gwa:'4.00'},422],[{...form,format:'xlsx'},422]]){
  const response=await post(payload);expect(response.status()).toBe(status);expect(response.headers()['content-type']).not.toContain('spreadsheet');expect((await response.body()).subarray(0,2).toString()).not.toBe('PK');
 }
});
test('changed owned grade rejects stale preview and refresh restores the action',async({page})=>{
 await login(page);await preview(page);
 const mutate=action=>{guard();const r=spawnSync(process.env.PEF_CC_PHP||'C:/xampp/php/php.exe',[resolve('tests/fixtures/pef_cc_fixture.php'),action,stateFile],{env:{...process.env,DB_NAME:'udm_radar_scratch'},encoding:'utf8',windowsHide:true});expect(r.status,r.stderr).toBe(0);};
 try{mutate('mutate-grade');const response=page.waitForResponse(r=>r.url().endsWith('/student/export_pef_cc.php'));await page.getByTestId('download-button').click();expect((await response).status()).toBe(409);await expect(page.getByTestId('download-button')).toBeDisabled();await expect(page.getByTestId('refresh-preview-button')).toBeEnabled();await page.getByTestId('refresh-preview-button').click();await expect(page.getByTestId('download-button')).toBeEnabled();}finally{mutate('restore-grade');}
});
test('transport error restores controls without offering XLSX',async({page})=>{
 await login(page);await preview(page);await page.route('**/student/export_pef_cc.php',route=>route.abort('connectionfailed'));await page.getByTestId('download-button').click();await expect(page.locator('#pef-cc-download-feedback')).toContainText(/interrupted|connection|try again/i);await expect(page.getByTestId('download-button')).toBeEnabled();await expect(page.getByRole('button',{name:/Excel|XLSX/})).toHaveCount(0);
});
test('unsafe D has no signed download and direct export is rejected',async({page})=>{
 await login(page,'overflow');await expect(page.getByTestId('preview-button')).toBeDisabled();await expect(page.getByTestId('download-button')).toHaveCount(0);
 const transport=await page.locator('#pef-cc-planning-form').evaluate(form=>Object.fromEntries(new FormData(form)));
 const response=await page.request.post(state.base_url+'student/export_pef_cc.php',{data:encoded({...transport,action:'download',preview:'invalid'}),headers:{'Content-Type':'application/x-www-form-urlencoded'}});expect([409,422]).toContain(response.status());
});
