import {spawn,spawnSync} from 'node:child_process';
import {readFileSync,writeFileSync,mkdirSync,existsSync,openSync,closeSync} from 'node:fs';
import {resolve} from 'node:path';
import {createRequire} from 'node:module';
import {createServer} from 'node:net';
import {get} from 'node:http';
import {createHash} from 'node:crypto';

// Explicit owned output and local Chart.js cache; no installs, existing DB fallback or captures.
const cwd=process.cwd(),require=createRequire(resolve('package.json'));
const pin=JSON.parse(readFileSync('package.json','utf8')).devDependencies['@playwright/test'];
if(require('@playwright/test/package.json').version!==pin)throw new Error('Pinned Playwright runner mismatch');
const php=process.env.PHP_BINARY||'C:/xampp/php/php.exe';
const ev=resolve(process.env.FACULTY_EVIDENCE_DIR||'logs/faculty-assessment-'+Date.now());
const chart=process.env.FACULTY_CHART_ASSET;
if(!chart||!existsSync(chart))throw new Error('FACULTY_CHART_ASSET must name a local real Chart.js library cache');
if(existsSync(ev))throw new Error('Evidence destination already exists; preserve prior run');
mkdirSync(ev,{recursive:true});writeFileSync(resolve(ev,'chart.umd.js'),readFileSync(chart));
const fixture=resolve('tests/fixtures/faculty_assessment_fixture.php'),statePath=resolve(ev,'owned-state-private.json');
const sha=x=>createHash('sha256').update(x).digest('hex');
const sources=['faculty/dashboard.php','faculty/trend.php','includes/faculty_assessment.php','includes/faculty_assessment_repository.php','assets/css/faculty-assessment.css','assets/js/faculty-assessment.js','tests/browser/faculty-metric-clarity.spec.js','tests/fixtures/faculty_assessment_fixture.php','tests/fixtures/faculty_assessment_router.php','tests/fixtures/run_faculty_assessment.mjs','tests/unit/faculty_assessment_test.php','tests/integration/faculty_assessment_scope_test.php'];
const schemaBefore=sha(readFileSync('database/udm_radar.sql'));
const absent=resolve(ev,'deliberately-absent-private');
const baseEnv={...process.env,DB_HOST:process.env.DB_HOST||'127.0.0.1'};
function runPhp(args,input){const r=spawnSync(php,args,{cwd,env:baseEnv,input,encoding:'utf8',windowsHide:true,maxBuffer:16*1024*1024});if(r.status!==0)throw new Error('Fixture/contract command failed: '+(r.stderr||r.stdout||'').slice(0,1200));return r.stdout;}
let state,server,log;
try {
 state=JSON.parse(runPhp([fixture,'setup']));if(!/^udm_radar_analytics_mvp_[a-f0-9]{16}$/.test(state.database))throw new Error('Unsafe disposable name');
 writeFileSync(statePath,JSON.stringify(state));console.log('Created guarded owned target '+state.database);
 writeFileSync(resolve(ev,'PROVENANCE.json'),JSON.stringify({database:state.database,marker:state.provenance,schema_source:state.schema_source,synthetic:'Committed identifier-free synthetic prototype scenarios only; no dump DML',protected_database_access:false},null,2));
 const fingerprintBefore=runPhp([fixture,'fingerprint-stdin'],JSON.stringify(state));
 const scope=spawnSync(php,['tests/integration/faculty_assessment_scope_test.php'],{cwd,env:baseEnv,input:JSON.stringify(state),encoding:'utf8',windowsHide:true});writeFileSync(resolve(ev,'scope-integration.log'),scope.stdout+(scope.stderr||''));if(scope.status!==0)throw new Error('Faculty scope integration failed; inspect owned evidence');
 const port=await new Promise((ok,no)=>{const s=createServer();s.once('error',no);s.listen(0,'127.0.0.1',()=>{const p=s.address().port;s.close(()=>ok(p));});});
 const url=`http://127.0.0.1:${port}/udm-radar`;mkdirSync(resolve(ev,'sessions'));log=openSync(resolve(ev,'server.log'),'w');
 const env={...baseEnv,DB_NAME:state.database,STUDENT_TREND_STATE:statePath,STUDENT_TREND_EVIDENCE:ev,STUDENT_TREND_URL:url,ANALYTICS_MVP_STATE:statePath,ANALYTICS_MVP_URL:url,PLAYWRIGHT_BASE_URL:url,ADMIN_CHART_ASSET:resolve(ev,'chart.umd.js'),PEF_CC_PRIVATE_ROOT:absent,PEF_CC_TEMPLATE_PATH:resolve(absent,'template.xlsx'),PEF_CC_TEMP_PATH:resolve(absent,'tmp')};
 server=spawn(php,['-d',`session.save_path=${resolve(ev,'sessions')}`,'-S',`127.0.0.1:${port}`,'-t',cwd,'tests/fixtures/faculty_assessment_router.php'],{cwd,env,windowsHide:true,stdio:['ignore',log,log]});
 const probe=()=>new Promise(ok=>{const q=get(url+'/login.php',r=>{r.resume();r.on('end',()=>ok(r.statusCode===200));});q.on('error',()=>ok(false));q.setTimeout(1000,()=>q.destroy());});
 let ready=false;for(let i=0;i<40;i++){if(await probe()){ready=true;break;}await new Promise(r=>setTimeout(r,100));}if(!ready)throw new Error('Owned server did not start');
 writeFileSync(resolve(ev,'ENVIRONMENT.json'),JSON.stringify({head:spawnSync('git',['rev-parse','HEAD'],{encoding:'utf8'}).stdout.trim(),sourceSha256:Object.fromEntries(sources.map(p=>[p,sha(readFileSync(p))])),runner:pin,url,chart_sha256:sha(readFileSync(chart)),captures:false,private_template:'Deliberately absent owned path'},null,2));
 const groups={
  layout:{testMatch:'**/faculty-dashboard-layout-regression.spec.js',grep:/contains balanced|does not swallow|are NOT inside|direct siblings|Desktop \(1440x900\)|Opening section card/},
  faculty:{testMatch:'**/faculty-metric-clarity.spec.js'},
  student:{testMatch:'**/student-performance-trend.spec.js'},
  sharedAnalytics:{testMatch:['**/admin-dashboard-interpretability.spec.js','**/admin-academic-comparisons.spec.js']},
  sidebar:{testMatch:'**/desktop-sidebar.spec.js',grep:/expanded default, manual persistence and unchanged navigation/},
  dashboardCompatibility:{testMatch:'**/post-defense-analytics.spec.js',grep:/Faculty dashboard features compact/}
 };
 // Separate categories get their own suite budget; assertions, 30s test limits and zero retries stay unchanged.
 const requested=(process.env.FACULTY_TEST_GROUPS||'layout,faculty,student,sharedAnalytics,sidebar,dashboardCompatibility').split(',');
 for(const group of requested){
  const selected=groups[group];if(!selected)throw new Error('Unknown verification group');
  const cfg={testDir:resolve('tests/browser'),...selected,workers:1,retries:0,timeout:30000,globalTimeout:240000,reporter:[['json',{outputFile:resolve(ev,group+'.json')}]],outputDir:resolve(ev,group+'-output'),use:{browserName:'chromium',channel:'msedge',screenshot:'off',trace:'off',video:'off'}};
  const path=resolve(ev,group+'.config.cjs');writeFileSync(path,'module.exports='+JSON.stringify({...cfg,grep:undefined})+(selected.grep?';module.exports.grep='+selected.grep.toString():'')+';');
  const run=spawnSync(process.execPath,[require.resolve('@playwright/test/cli'),'test','--config='+path],{cwd,env,windowsHide:true,encoding:'utf8',maxBuffer:32*1024*1024});writeFileSync(resolve(ev,group+'-runner.log'),run.stdout+(run.stderr||''));
  const report=JSON.parse(readFileSync(resolve(ev,group+'.json'),'utf8'));console.log(group+': '+JSON.stringify(report.stats));if(run.status!==0){process.exitCode=1;break;}
 }
 const fingerprintAfter=runPhp([fixture,'fingerprint-stdin'],JSON.stringify(state));writeFileSync(resolve(ev,'DATA_INTEGRITY.json'),JSON.stringify({before:fingerprintBefore,after:fingerprintAfter,unchanged:fingerprintBefore===fingerprintAfter},null,2));if(fingerprintBefore!==fingerprintAfter)throw new Error('Fixture records changed during verification');
}finally{
 if(server?.pid&&server.exitCode===null){const exited=new Promise(r=>server.once('exit',r));server.kill();await exited;}
 if(log!==undefined)closeSync(log);
 if(state){const cleanup=JSON.parse(runPhp([fixture,'cleanup-stdin'],JSON.stringify(state)));writeFileSync(resolve(ev,'CLEANUP.json'),JSON.stringify(cleanup,null,2));writeFileSync(statePath,JSON.stringify({database:state.database,removed:cleanup.removed,cleanup_asserted:cleanup.cleanup_asserted,credentials:'removed',marker_token:'removed'}));if(!cleanup.cleanup_asserted||!cleanup.schema_byte_identical||sha(readFileSync('database/udm_radar.sql'))!==schemaBefore)throw new Error('Cleanup/schema-preservation failure');console.log('Disposable deletion and information_schema absence verified.');}
}
