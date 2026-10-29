import {spawn,spawnSync} from 'node:child_process';
import {readFileSync,writeFileSync,mkdirSync,openSync,closeSync} from 'node:fs';
import {resolve} from 'node:path';
import {createRequire} from 'node:module';
import {createServer} from 'node:net';
import {get} from 'node:http';
import {createHash} from 'node:crypto';
const require=createRequire(resolve('package.json')),php='C:/xampp/php/php.exe',cwd=process.cwd();
const pin=JSON.parse(readFileSync('package.json','utf8')).devDependencies['@playwright/test'];
if(require('@playwright/test/package.json').version!==pin)throw new Error('Pinned runner mismatch');
const evidence=resolve('logs/student-trend');mkdirSync(evidence,{recursive:true});
const fixture=resolve('tests/fixtures/student_trend_fixture.php'),statePath=resolve(evidence,'owned-state.json');
function runPhp(args,input){const r=spawnSync(php,args,{cwd,input,encoding:'utf8',windowsHide:true,maxBuffer:16*1024*1024});if(r.status!==0)throw new Error(r.stderr||r.stdout||'Fixture failed');return r.stdout;}
let state,server,log;
try{
 state=JSON.parse(runPhp([fixture,'setup']));writeFileSync(statePath,JSON.stringify(state));
 console.log('Owned disposable database: '+state.database+'; application tables plus ownership marker: '+state.all_table_count);
 const port=await new Promise((resolvePort,reject)=>{const socket=createServer();socket.on('error',reject);socket.listen(0,'127.0.0.1',()=>{const p=socket.address().port;socket.close(()=>resolvePort(p));});});
 const env={...process.env,DB_NAME:state.database,DB_HOST:'127.0.0.1',STUDENT_TREND_STATE:statePath,STUDENT_TREND_URL:`http://127.0.0.1:${port}/udm-radar`,PLAYWRIGHT_BASE_URL:`http://127.0.0.1:${port}/udm-radar`};
 log=openSync(resolve(evidence,'server.log'),'w');
 server=spawn(php,['-S',`127.0.0.1:${port}`,'-t',cwd,'tests/fixtures/student_trend_router.php'],{cwd,env,windowsHide:true,stdio:['ignore',log,log]});
 const probe=()=>new Promise(done=>{const req=get(env.STUDENT_TREND_URL+'/login.php',res=>{res.resume();res.on('end',()=>done(res.statusCode===200));});req.on('error',()=>done(false));req.setTimeout(1000,()=>{req.destroy();done(false);});});
 let ready=false;for(let i=0;i<40;i++){if(await probe()){ready=true;break;}await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw new Error('Isolated PHP server failed readiness; inspect logs/student-trend/server.log');
 writeFileSync(resolve(evidence,'environment.json'),JSON.stringify({baseline:spawnSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).stdout.trim(),dirtyCandidate:true,sourceSha256:Object.fromEntries(['student/trend.php','student/performance_trend_model.php','assets/js/student-performance-trend.js','assets/css/student-performance-trend.css','student/dashboard.php','student/grades.php','assets/js/goal-calculator-ui.js','assets/css/goal-calculator.css'].map(path=>[path,createHash('sha256').update(readFileSync(path)).digest('hex')])),runner:pin,url:env.STUDENT_TREND_URL,chartAssetSha256:createHash('sha256').update(readFileSync(resolve(evidence,'chart.umd.js'))).digest('hex'),chartTransport:'Exact CDN asset cached and fulfilled locally; real Chart.js, not mocked chart data',screenshots:false},null,2));
 const beforeHash=runPhp([fixture,'fingerprint-stdin'],JSON.stringify(state));
 const cli=require.resolve('@playwright/test/cli');
 const result=spawnSync(process.execPath,[cli,'test','--config=playwright.config.js','tests/browser/student-performance-trend.spec.js','--output=test-results/student-trend','--reporter=json',...process.argv.slice(2)],{cwd,env,windowsHide:true,encoding:'utf8',maxBuffer:32*1024*1024});
 writeFileSync(resolve(evidence,'browser.json'),result.stdout);writeFileSync(resolve(evidence,'browser.stderr.log'),result.stderr||'');
 try{const report=JSON.parse(result.stdout);console.log(JSON.stringify(report.stats));for(const suite of report.suites||[])for(const spec of suite.specs||[])for(const test of spec.tests||[])for(const outcome of test.results||[])if(outcome.status!=='passed')console.log(spec.title+': '+outcome.status+' '+(outcome.error?.message||'').slice(0,1400));}catch{console.log('Runner output is not valid JSON; inspect retained browser logs');}
 const afterHash=runPhp([fixture,'fingerprint-stdin'],JSON.stringify(state));
 writeFileSync(resolve(evidence,'data-integrity.json'),JSON.stringify({beforeHash,afterHash,unchanged:beforeHash===afterHash},null,2));
 if(beforeHash!==afterHash)throw new Error('Browser tests unexpectedly changed fixture records');
 if(result.status!==0)process.exitCode=1;
}finally{
 if(server?.pid&&server.exitCode===null){const exited=new Promise(r=>server.once('exit',r));server.kill();await exited;}
 if(log!==undefined)closeSync(log);
 if(state){const cleanup=JSON.parse(runPhp([fixture,'cleanup-stdin'],JSON.stringify(state)));writeFileSync(resolve(evidence,'lifecycle.json'),JSON.stringify(cleanup,null,2));writeFileSync(statePath,JSON.stringify({...cleanup,password:'removed',token:'removed'}));if(!cleanup.cleanup_asserted||!cleanup.schema_byte_identical)throw new Error('Cleanup or protected schema assertion failed');console.log('Disposable database deletion verified; protected schema unchanged.');}
}