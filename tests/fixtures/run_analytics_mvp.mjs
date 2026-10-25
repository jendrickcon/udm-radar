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
const evidence=resolve('logs/post-defense-mvp');mkdirSync(evidence,{recursive:true});
const fixture=resolve('tests/fixtures/analytics_mvp_fixture.php');
function phpRun(args,env=process.env){const r=spawnSync(php,args,{cwd,env,encoding:'utf8',windowsHide:true,maxBuffer:16*1024*1024});if(r.status!==0)throw new Error(r.stderr||r.stdout||'PHP fixture failed');return r.stdout;}
const state=JSON.parse(phpRun([fixture,'setup']));
const statePath=resolve(evidence,'owned-fixture-state.json');writeFileSync(statePath,JSON.stringify(state));
const env={...process.env,DB_NAME:state.database,ANALYTICS_MVP_STATE:statePath};
let server;let log;
try{
 console.log('Owned disposable target: '+state.database+'; Playwright '+pin);
 const integration=phpRun(['tests/integration/post_defense_analytics_test.php'],env);writeFileSync(resolve(evidence,'integration-final.txt'),integration);console.log(integration.trim());
 const port=await new Promise((resolvePort,reject)=>{const s=createServer();s.on('error',reject);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>resolvePort(p));});});
 env.ANALYTICS_MVP_URL='http://127.0.0.1:'+port+'/capstone';
 log=openSync(resolve(evidence,'owned-server.log'),'w');
 server=spawn(php,['-S','127.0.0.1:'+port,'-t',cwd,'tests/fixtures/analytics_mvp_router.php'],{cwd,env,windowsHide:true,stdio:['ignore',log,log]});
 const probe=()=>new Promise(resolveProbe=>{const request=get(env.ANALYTICS_MVP_URL+'/login.php',{agent:false,headers:{Connection:'close'}},res=>{res.resume();res.on('end',()=>resolveProbe(res.statusCode===200));});request.on('error',()=>resolveProbe(false));request.setTimeout(1000,()=>{request.destroy();resolveProbe(false);});});
 let ready=false;for(let i=0;i<50;i++){if(await probe()){ready=true;break;}await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw new Error('Owned server unavailable; inspect owned-server.log');
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
 if(server){server.kill();await new Promise(r=>server.once('exit',r));}
 if(log!==undefined)closeSync(log);
 console.log(phpRun([fixture,'cleanup',statePath]).trim());
 // Keep the namespace/provenance result, but remove the run-only login secret.
 const record={database:state.database,seed:state.seed,removed:true};writeFileSync(resolve(evidence,'fixture-lifecycle.json'),JSON.stringify(record,null,2));
 writeFileSync(statePath,JSON.stringify({...record,token:'removed',password:'removed'}));
}
