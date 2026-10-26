import { spawn, spawnSync } from 'node:child_process';
import { readFileSync, writeFileSync, mkdirSync, openSync, closeSync } from 'node:fs';
import { resolve } from 'node:path';
import { createServer } from 'node:net';
import { get } from 'node:http';
import { createRequire } from 'node:module';
const cwd=process.cwd();const require=createRequire(resolve('package.json'));
const pin=JSON.parse(readFileSync('package.json','utf8')).devDependencies['@playwright/test'];
if(require('@playwright/test/package.json').version!==pin)throw new Error('Installed runner must match repository pin');
const php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';
const evidence=resolve('logs/analytics-reconciliation');mkdirSync(evidence,{recursive:true});
const fixture=resolve('tests/fixtures/analytics_mvp_fixture.php');
function phpRun(args,env=process.env,input){const r=spawnSync(php,args,{cwd,env,input,encoding:'utf8',windowsHide:true,maxBuffer:16*1024*1024});if(r.status!==0)throw new Error(r.stderr||r.stdout||'PHP fixture failed');return r.stdout;}
const statePath=resolve(evidence,'owned-fixture-state.json');
const env={...process.env};
let state;let server;let serverError;let log;
try{
 state=JSON.parse(phpRun([fixture,'setup']));
 if(process.env.ANALYTICS_MVP_SIMULATE_STATE_WRITE_FAILURE==='1')throw new Error('Controlled state-file write failure after owned setup');
 writeFileSync(statePath,JSON.stringify(state));
 env.DB_NAME=state.database;env.ANALYTICS_MVP_STATE=statePath;
 console.log('Owned disposable target: '+state.database+'; Playwright '+pin);
 const integration=phpRun(['tests/integration/post_defense_analytics_test.php'],env);writeFileSync(resolve(evidence,'integration-final.txt'),integration);console.log(integration.trim());
 const port=await new Promise((resolvePort,reject)=>{const s=createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolvePort(p));});});
 env.ANALYTICS_MVP_URL='http://127.0.0.1:'+port+'/capstone';
 log=openSync(resolve(evidence,'owned-server.log'),'w');
 const serverArgs=process.env.ANALYTICS_MVP_SIMULATE_SERVER_EXIT==='1'?['-r','exit(1);']:['-S','127.0.0.1:'+port,'-t',cwd,'tests/fixtures/analytics_mvp_router.php'];
 server=spawn(php,serverArgs,{cwd,env,windowsHide:true,stdio:['ignore',log,log]});server.once('error',error=>serverError=error);
 const probe=()=>new Promise(resolveProbe=>{const request=get(env.ANALYTICS_MVP_URL+'/login.php',{agent:false,headers:{Connection:'close'}},res=>{res.resume();res.on('end',()=>resolveProbe(res.statusCode===200));});request.on('error',()=>resolveProbe(false));request.setTimeout(1000,()=>{request.destroy();resolveProbe(false);});});
 let ready=false;for(let i=0;i<50;i++){if(await probe()){ready=true;break;}await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw new Error('Owned server unavailable; inspect owned-server.log'+(serverError?': '+serverError.message:''));
 const cli=require.resolve('@playwright/test/cli');
 const extra=process.argv.slice(2);
 if(!extra.length){
  const layout=spawnSync(process.execPath,[cli,'test','--config=playwright.config.js','tests/browser/faculty-dashboard-layout-regression.spec.js','--grep-invert=Multi-viewport|Capture required screenshots','--reporter=json'],{cwd,env,windowsHide:true,encoding:'utf8',maxBuffer:16*1024*1024});
  writeFileSync(resolve(evidence,'faculty-layout.json'),layout.stdout);writeFileSync(resolve(evidence,'faculty-layout.stderr.log'),layout.stderr||'');
  if(layout.status!==0)throw new Error('Faculty layout regression failed; evidence retained');
  console.log('Faculty layout regression passed');
 }
 const browser=spawnSync(process.execPath,[cli,'test','--config=playwright.config.js','tests/browser/post-defense-analytics.spec.js','--reporter=json',...extra],{cwd,env,windowsHide:true,encoding:'utf8',maxBuffer:32*1024*1024});
 writeFileSync(resolve(evidence,'browser-final.json'),browser.stdout);writeFileSync(resolve(evidence,'browser-final.stderr.log'),browser.stderr||'');
 let stats;try{stats=JSON.parse(browser.stdout).stats;}catch{}
 console.log('Analytics browser result: '+JSON.stringify({exit:browser.status,stats}));
 if(browser.status!==0)process.exitCode=1;
}finally{
 if(server?.pid&&server.exitCode===null&&server.signalCode===null){const exited=new Promise(r=>server.once('exit',r));server.kill();await exited;}
 if(log!==undefined)closeSync(log);
 if(state){
 const record=JSON.parse(phpRun([fixture,'cleanup-stdin'],process.env,JSON.stringify(state)));
 // Keep the namespace/provenance result, but remove the run-only login secret.
 writeFileSync(resolve(evidence,'fixture-lifecycle.json'),JSON.stringify(record,null,2));
 writeFileSync(statePath,JSON.stringify({...record,token:'removed',password:'removed'}));
 if(!record.schema_byte_identical||!record.cleanup_asserted)throw new Error('Disposable lifecycle/source verification failed; inspect fixture-lifecycle.json');
 console.log('Owned disposable database removed; cleanup asserted; accepted DDL source byte-identical.');
 }
}
