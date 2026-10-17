/** Loopback-only real PHP application server. No injected login/session or public fixture route. */
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { mkdir, writeFile, access, realpath, unlink } from 'node:fs/promises';
import { randomBytes } from 'node:crypto';
import { dirname, resolve, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import process from 'node:process';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');
const php = process.env.PEF_CC_PHP || 'C:/xampp/php/php.exe';
const stateIndex = process.argv.indexOf('--state-file');
if (stateIndex < 0 || !process.argv[stateIndex + 1]) throw new Error('--state-file private absolute path required');
if (process.env.DB_NAME !== 'udm_radar_scratch') throw new Error('DATABASE SAFETY BLOCKED: explicit scratch environment required');
const statePath = resolve(process.argv[stateIndex + 1]);
await mkdir(dirname(statePath), { recursive: true });
const privateDir = await realpath(dirname(statePath));
if (privateDir.toLowerCase().startsWith(root.toLowerCase()) || /[\\/]htdocs(?:[\\/]|$)/i.test(privateDir)) throw new Error('State/session/router files must be outside every web root');
try { await access(statePath); throw new Error('Use a fresh state-file path; preserve existing run evidence.'); } catch (error) { if (error.code !== 'ENOENT') throw error; }
const fixturePath = join(root, 'tests/fixtures/pef_cc_fixture.php');
const env = { ...process.env, DB_NAME: 'udm_radar_scratch', DB_HOST: process.env.DB_HOST || '127.0.0.1' };
const cli = (args, input) => {
  const r = spawnSync(php, [fixturePath, ...args], { cwd: root, env, input, encoding: 'utf8', windowsHide: true });
  if (r.status !== 0) throw new Error(r.stderr || 'Scratch fixture failed');
  return JSON.parse(r.stdout);
};
let child; let stopping = false;
const runId = randomBytes(8).toString('hex');
const router = join(privateDir, `pef-cc-router-${runId}.php`);
const sessionDir = join(privateDir, `sessions-${runId}`);
await mkdir(sessionDir, { recursive: true });
const port = await new Promise((accept, reject) => {
  const s = createServer(); s.once('error', reject); s.listen(0, '127.0.0.1', () => { const p = s.address().port; s.close(() => accept(p)); });
});
// Reserve and prove the private writable manifest location before any fixture DML.
await writeFile(statePath, '{}', { flag: 'wx', mode: 0o600 });
let fixture;
try { fixture = cli(['create']); } catch (error) { await unlink(statePath); throw error; }
const state = { base_url: `http://127.0.0.1:${port}/udm-radar/`, fixture, pid: process.pid, root };
try { await writeFile(statePath, JSON.stringify(state), { mode: 0o600 }); }
catch (error) { cli(['cleanup-stdin'], JSON.stringify(fixture)); throw error; }
// Exact route allowlist: application handlers already inspected for request/DB side effects.
// Everything else (including config, vendor, private evidence, tests and source dirs) is 404.
const allowed = [
  'login.php', 'logout.php', 'student/index.php', 'student/dashboard.php', 'student/grades.php',
  'student/trend.php', 'student/feedback.php', 'student/settings.php', 'student/enrolment_planning.php',
  'student/export_pef_cc.php', 'faculty/index.php', 'admin/index.php',
];
try { await writeFile(router, `<?php
if(getenv('DB_NAME')!=='udm_radar_scratch'){http_response_code(503);exit;}
$db=new PDO('mysql:host='.(getenv('DB_HOST')?:'127.0.0.1').';dbname=udm_radar_scratch;charset=utf8mb4',getenv('DB_USER')?:'root',getenv('DB_PASS')?:'');
if($db->query('SELECT DATABASE()')->fetchColumn()!=='udm_radar_scratch'){http_response_code(503);exit;}
$root=${JSON.stringify(root.replaceAll('\\', '/'))};
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(!is_string($path)||!str_starts_with($path,'/udm-radar/')){http_response_code(404);exit;}
$relative=substr($path,11);
if(preg_match('~(?:^|/)\\.\\.?(/|$)~',$relative)||str_contains($relative,chr(0))||str_contains($relative,chr(92))||str_contains($relative,'%')){http_response_code(404);exit;}
$real=realpath($root.'/'.$relative);
if(!$real||!str_starts_with(str_replace('\\\\','/',$real),$root.'/')){http_response_code(404);exit;}
if(preg_match('~^assets/(css|js|img|fonts)/[a-zA-Z0-9_./-]+\\.(css|js|png|jpg|jpeg|svg|webp|ico|woff2?)$~',$relative)){
 $ext=strtolower(pathinfo($real,PATHINFO_EXTENSION));$types=['css'=>'text/css','js'=>'text/javascript','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2'];header('Content-Type: '.$types[$ext]);readfile($real);exit;
}
if(!in_array($relative,${JSON.stringify(allowed).replaceAll('[', '[').replaceAll(']', ']')},true)){http_response_code(404);exit;}
$_SERVER['SCRIPT_NAME']='/udm-radar/'.$relative;$_SERVER['PHP_SELF']=$_SERVER['SCRIPT_NAME'];$_SERVER['SCRIPT_FILENAME']=$real;
chdir(dirname($real));require $real;
`, { mode: 0o600 }); }
catch (error) { cli(['cleanup-stdin'], JSON.stringify(fixture)); throw error; }
async function stop(code = 0) {
  if (stopping) return; stopping = true;
  if (child && child.exitCode === null) {
    child.kill();
    await Promise.race([new Promise(r => child.once('exit', r)), new Promise(r => setTimeout(r, 3000))]);
  }
  try { const result = cli(['cleanup', statePath]); console.log(JSON.stringify({ stopped: true, ...result })); }
  catch (error) { console.error(error.message); code = 1; }
  try { await unlink(router); } catch (error) { if (error.code !== 'ENOENT') console.error(error.message); }
  // The private state is retained as failure evidence; it contains synthetic credentials only.
  process.exit(code);
}
process.on('SIGINT', () => stop()); process.on('SIGTERM', () => stop());
child = spawn(php, ['-d', `session.save_path=${sessionDir}`, '-d', 'display_errors=0', '-d', 'log_errors=1', '-S', `127.0.0.1:${port}`, router], { cwd: root, env, stdio: ['ignore', 'ignore', 'pipe'], windowsHide: true });
const errorLog = join(privateDir, 'php-server.log');
child.stderr.on('data', async data => { const { appendFile } = await import('node:fs/promises'); await appendFile(errorLog, data); });
child.once('exit', code => { if (!stopping) stop(code || 1); });
for (let attempt = 0; attempt < 100; attempt++) {
  try { const response = await fetch(state.base_url + 'login.php'); if (response.status === 200) { console.log(JSON.stringify({ ready: true, base_url: state.base_url, state_file: statePath, database: 'udm_radar_scratch' })); break; } }
  catch { /* Wait only for this known local child to bind. */ }
  if (attempt === 99) await stop(1);
  await new Promise(r => setTimeout(r, 100));
}
