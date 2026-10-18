import { test, expect } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { spawnSync } from 'node:child_process';
import { resolve } from 'node:path';

const stateFile=process.env.PEF_CC_STATE_FILE;
const state=stateFile?JSON.parse(readFileSync(stateFile,'utf8')):null;
test.skip(!state,'Requires guarded real-login scratch application server.');
function guard(){if(process.env.DB_NAME!=='udm_radar_scratch'||state?.fixture.database!=='udm_radar_scratch'||!/^http:\/\/127\.0\.0\.1:\d+\/udm-radar\/$/.test(state?.base_url||''))throw new Error('DATABASE SAFETY BLOCKED');}
async function login(page,account='first'){
 guard();await page.goto(state.base_url+'login.php',{waitUntil:'domcontentloaded'});await page.locator('#userid').fill(state.fixture.accounts[account].login);await page.locator('#password').fill(state.fixture.password);
 await Promise.all([page.waitForURL('**/student/index.php',{waitUntil:'domcontentloaded'}),page.getByRole('button',{name:'Login to Portal'}).click()]);await page.goto(state.base_url+'student/enrolment_planning.php',{waitUntil:'domcontentloaded'});
}
async function preview(page){
 await page.locator('[name="evaluation_year"]').selectOption(state.fixture.evaluation_year);await page.locator('[name="evaluation_semester"]').selectOption('2');await page.locator('[name="target_year"]').fill(state.fixture.target_year);await page.locator('[name="target_semester"]').selectOption('1');await page.locator('[name="section"]').selectOption('IT-IRREG');await page.getByRole('button',{name:'Preview planning copy',exact:true}).click();await expect(page.locator('#pef-cc-download-form')).toBeVisible();
}
async function data(page){return page.locator('#pef-cc-download-form').evaluate(form=>{const result={};for(const [key,value]of new FormData(form)){if(key.endsWith('[]')){(result[key.slice(0,-2)]??=[]).push(value);}else result[key]=value;}return result;});}
function encoded(form){const p=new URLSearchParams();for(const[k,v]of Object.entries(form)){if(Array.isArray(v))v.forEach(value=>p.append(k+'[]',value));else p.set(k,v);}return p.toString();}
test('actual login, preview and enhanced XLSX download have fixed name and recover busy state',async({page})=>{
 await login(page);await preview(page);
 const pending=page.waitForEvent('download');await page.getByRole('button',{name:'Download Excel',exact:true}).click();const download=await pending;
 expect(download.suggestedFilename()).toBe('UDM-RADAR-PEF-CC.xlsx');const stream=await download.createReadStream();const chunks=[];for await(const part of stream)chunks.push(part);const bytes=Buffer.concat(chunks);
 expect(bytes.subarray(0,2).toString()).toBe('PK');expect(bytes.length).toBeGreaterThan(10000);
 await expect(page.getByRole('button',{name:'Download Excel',exact:true})).toBeEnabled();
 await expect(page.getByTestId('download-status')).toContainText(/download|ready|saved/i);
});
test('native download still works without JavaScript',async({browser})=>{
 guard();const context=await browser.newContext({javaScriptEnabled:false});try{const page=await context.newPage();await login(page);await preview(page);const pending=page.waitForEvent('download');await page.getByRole('button',{name:'Download Excel',exact:true}).click();expect((await pending).suggestedFilename()).toBe('UDM-RADAR-PEF-CC.xlsx');}finally{await context.close();}
});
test('tampered planning inputs and unknown ownership keys fail through actual endpoint',async({page})=>{
 await login(page);await preview(page);const form=await data(page);
 const post=async payload=>page.request.post(state.base_url+'student/export_pef_cc.php',{data:encoded(payload),headers:{'Content-Type':'application/x-www-form-urlencoded'}});
 expect((await post({...form,section:'CHANGED'})).status()).toBe(409);
 expect((await post({...form,preview:form.preview+'x'})).status()).toBe(409);
 expect((await post({...form,student_id:state.fixture.accounts.second.id})).status()).toBe(422);
 expect((await post({...form,gwa:'4.00'})).status()).toBe(422);
});
test('changed authoritative grade rejects old preview and UI recovers after refresh',async({page})=>{
 await login(page);await preview(page);const fixture=resolve('tests/fixtures/pef_cc_fixture.php');
 const mutate=action=>{guard();const r=spawnSync(process.env.PEF_CC_PHP||'C:/xampp/php/php.exe',[fixture,action,stateFile],{env:{...process.env,DB_NAME:'udm_radar_scratch'},encoding:'utf8',windowsHide:true});expect(r.status,r.stderr).toBe(0);};
 try{mutate('mutate-grade');const response=page.waitForResponse(r=>r.url().endsWith('student/export_pef_cc.php'));await page.getByRole('button',{name:'Download Excel',exact:true}).click();expect((await response).status()).toBe(409);await expect(page.getByTestId('download-status')).toContainText(/preview|changed|refresh/i);await expect(page.getByRole('button',{name:'Download Excel',exact:true})).toBeDisabled();await page.getByRole('button',{name:'Update preview',exact:true}).click();await expect(page.getByRole('button',{name:'Download Excel',exact:true})).toBeEnabled();}
 finally{mutate('restore-grade');}
});
test('transport errors recover busy state without discarding the preview',async({page})=>{
 await login(page);await preview(page);await page.route('**/student/export_pef_cc.php',route=>route.abort('connectionfailed'));
 await page.getByRole('button',{name:'Download Excel',exact:true}).click();await expect(page.getByTestId('download-status')).toContainText(/connect|retry|network|try again/i);await expect(page.getByRole('button',{name:'Download Excel',exact:true})).toBeEnabled();
});
