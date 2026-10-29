import { readFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { createServer } from 'node:http';
import { test, expect } from '@playwright/test';

// Generated-component tests: real sidebar PHP/CSS/JS, synthetic badge I/O only.
// Never includes application bootstrap, authentication, or a database connection.
const php = process.env.PHP_BIN || (process.platform === 'win32' ? 'C:/xampp/php/php.exe' : 'php');
const css = readFileSync(resolve('assets/css/dashboard.css'), 'utf8');
const accessibilityCss = readFileSync(resolve('assets/css/accessibility-preferences.css'), 'utf8');
const ui = readFileSync(resolve('assets/js/ui.js'), 'utf8');
const sidebarPath = resolve('includes/sidebar.php').replaceAll('\\', '/');
const logo = readFileSync(resolve('assets/img/logo_sidebar.png'));

function menu(role) {
    const source = readFileSync(resolve(`${role}/index.php`), 'utf8');
    const literal = source.match(/\$navItems\s*=\s*(\[[\s\S]*?\]);/)[1];
    const items = [...literal.matchAll(/\['([^']+)'\s*,\s*'([^']+)'/g)].map(m => ({ label: m[1], href: m[2] }));
    return { literal, items };
}

function sidebar(role, file) {
    const code = `define('BASE_URL','http://sidebar.test/');
        function getDB(){return new class{function query($sql){return new class{function fetchColumn(){return 1;}};}};}
        function countFacultyActionableConcerns($db,$id){return 1;}
        function countFacultyPendingReferrals($db,$id){return 0;}
        function countStudentUnacknowledgedNotices($db,$id){return 1;}
        $user=['role'=>${JSON.stringify(role)},'id'=>1];
        $_SERVER['PHP_SELF']=${JSON.stringify(`/${role}/${file}`)};
        $navItems=${menu(role).literal};
        include ${JSON.stringify(sidebarPath)};`;
    return execFileSync(php, ['-r', code], { encoding: 'utf8' });
}

const preferences = readFileSync(resolve('assets/js/display-preferences.js'),'utf8');
const preRender = readFileSync(resolve('includes/header.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const preferencesPath = resolve('includes/display_preferences_control.php').replaceAll('\\','/');
const KEY='udmRadarDisplayPreferencesV1';
function settingsMarkup(){return execFileSync(php,['-r',`include ${JSON.stringify(preferencesPath)};`],{encoding:'utf8'});}

async function mount(page,{role='admin',theme='dark',width=1440,height=900,motion='allow',textSize='default',automatic=false,collapsed=false}={}){
    await page.setViewportSize({width,height});
    await page.addInitScript(({key,prefs})=>{
        if (!localStorage.getItem(key)) localStorage.setItem(key,JSON.stringify(prefs));
    },{key:KEY,prefs:{version:1,theme,colorStyle:'ccs',motion,textSize,underlineLinks:false,automaticSidebarExpansion:automatic,sidebarCollapsed:collapsed}});
    await page.route('http://sidebar.test/**',route=>{
        const path=new URL(route.request().url()).pathname;
        if(path.endsWith('logo_sidebar.png')) return route.fulfill({contentType:'image/png',body:logo});
        if(!path.endsWith('.php')) return route.abort();
        const file=path.split('/').pop();
        return route.fulfill({contentType:'text/html',body:`<!doctype html><html lang="en"><head><meta name="viewport" content="width=device-width,initial-scale=1"><script>${preRender}</script><style>${css}\n${accessibilityCss}</style></head><body>${sidebar(role,file)}<main class="main-content"><h1>Navigation fixture</h1><button id="content-control" type="button">Content control</button><section class="card"><svg viewBox="0 0 500 100" style="display:block;width:100%;height:auto"><path d="M0 80 L150 20 L500 10" fill="none" stroke="currentColor"/></svg><table><caption>Synthetic records</caption><tr><th>Record</th><th>Status</th></tr><tr><td>Demo</td><td>Available</td></tr></table></section>${file==='settings.php'?settingsMarkup():''}</main><script>${ui}</script><script>${preferences}</script></body></html>`});
    });
    await page.goto(`http://sidebar.test/${role}/index.php`);
}
async function widthIs(page,width){await expect.poll(()=>page.locator('#sidebar').evaluate(el=>Math.round(el.getBoundingClientRect().width))).toBe(width);}
async function uncovered(page){
    const g=await page.evaluate(()=>{const s=document.getElementById('sidebar').getBoundingClientRect(),m=document.querySelector('.main-content').getBoundingClientRect();return {right:s.right,left:m.left,mainRight:m.right,overflow:document.documentElement.scrollWidth>innerWidth,width:innerWidth};});
    expect(g.left).toBeGreaterThanOrEqual(g.right-1);expect(g.mainRight).toBeLessThanOrEqual(g.width+1);expect(g.overflow).toBe(false);
}
async function saved(page){return page.evaluate(key=>JSON.parse(localStorage.getItem(key)),KEY);}
const tip=page=>page.locator('#sidebar-navigation-tooltip');

for(const role of ['admin','faculty','student']) for(const theme of ['light','dark']) for(const [width,height] of [[1440,900],[1280,600],[1024,768]]){
    test(`${role} ${theme} ${width}: expanded default, manual persistence and unchanged navigation`,async({page})=>{
        await mount(page,{role,theme,width,height});await widthIs(page,280);await uncovered(page);
        const links=page.locator('.sidebar-nav a');
        const before=await links.evaluateAll(nodes=>nodes.map(a=>({href:a.getAttribute('href'),icon:a.querySelector('svg').innerHTML})));
        for(const item of menu(role).items) await expect(page.getByRole('link',{name:item.label,exact:true})).toHaveAttribute('href',item.href);
        const icon=page.locator('.sidebar-nav .sidebar-icon').first();const label=await links.first().getAttribute('aria-label');
        const iconX=(await icon.boundingBox()).x;
        await icon.hover();await expect(tip(page)).toHaveText(label);await expect(tip(page)).toBeVisible();await widthIs(page,280);
        await page.locator('#content-control').hover();await widthIs(page,280);
        await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);await uncovered(page);
        expect((await icon.boundingBox()).x).toBeCloseTo(iconX,0);
        await page.locator('#content-control').hover();await icon.hover();await widthIs(page,80);await expect(tip(page)).toBeVisible();
        await expect(page.locator('.sidebar-label').first()).toHaveCSS('opacity','0');
        expect((await saved(page)).sidebarCollapsed).toBe(true);
        const item=menu(role).items.find(i=>i.href!=='index.php');
        await page.getByRole('link',{name:item.label,exact:true}).click();await widthIs(page,80);
        await expect(page.getByRole('link',{name:item.label,exact:true})).toHaveAttribute('aria-current','page');
        await page.reload();await widthIs(page,80);
        await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,280);
        await page.reload();await widthIs(page,280);await uncovered(page);
        expect(await links.evaluateAll(nodes=>nodes.map(a=>({href:a.getAttribute('href'),icon:a.querySelector('svg').innerHTML})))).toEqual(before);
        await expect(page.getByRole('link',{name:'Logout',exact:true})).toHaveAttribute('href','http://sidebar.test/logout.php');
    });
}
for(const role of ['admin','faculty','student']){
    test(`${role}: opt-in automatic hover expansion is temporary`,async({page})=>{
        await mount(page,{role,automatic:true});await widthIs(page,280);
        await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);
        await page.locator('#content-control').hover();await page.locator('.sidebar-nav .sidebar-icon').first().hover();await widthIs(page,280);
        await page.locator('#logout-btn').hover();await widthIs(page,280);
        await page.locator('#content-control').hover();await widthIs(page,80);
        expect((await saved(page)).sidebarCollapsed).toBe(true);
        await page.reload();await widthIs(page,80);
        await page.locator('.sidebar-nav .sidebar-icon').first().hover();await widthIs(page,280);
        await page.locator('#theme-toggle-btn').click();await widthIs(page,280);
        await page.locator('#content-control').hover();await widthIs(page,80);
    });
    test(`${role}: shared Accessibility checkbox applies immediately, persists and resets`,async({page})=>{
        await mount(page,{role});await page.getByRole('link',{name:'Settings',exact:true}).click();
        await page.locator('#displayPrefToggleBtn').click();
        const auto=page.getByRole('checkbox',{name:'Automatic sidebar expansion',exact:true});
        await expect(auto).not.toBeChecked();
        await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);
        await auto.check();await widthIs(page,280);expect((await saved(page)).automaticSidebarExpansion).toBe(true);
        await page.reload();await widthIs(page,280);await page.locator('#displayPrefToggleBtn').click();await expect(auto).toBeChecked();
        await auto.uncheck();await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);
        await page.locator('#resetDisplayPreferencesBtn').click();await widthIs(page,280);await expect(auto).not.toBeChecked();
        await page.locator('#content-control').hover();await widthIs(page,280);
    });
}
test('keyboard tooltips supplement accessible labels without expanding or moving focus',async({page})=>{
    await mount(page,{collapsed:true});await widthIs(page,80);
    await page.keyboard.press('Tab');const first=page.locator('.sidebar-nav a').first();await expect(first).toBeFocused();
    await expect(tip(page)).toBeVisible();await widthIs(page,80);
    await page.keyboard.press('Escape');await expect(tip(page)).toBeHidden();await expect(first).toBeFocused();await widthIs(page,80);
    await page.locator('#sidebar-collapse-toggle').focus();await page.keyboard.press('Enter');await widthIs(page,280);
    await expect(page.locator('#sidebar-collapse-toggle')).toBeFocused();await expect(tip(page)).toHaveText('Collapse navigation');
    await page.locator('#theme-toggle-btn').focus();await page.keyboard.press('Enter');await expect(tip(page)).toHaveText((await page.locator('#theme-toggle-text').textContent()).trim());
    await page.locator('#content-control').focus();await widthIs(page,280);
});
test('expanded icon tooltips leave the navigation label clickable',async({page})=>{
    await mount(page);const link=page.getByRole('link',{name:'Students',exact:true});
    await link.locator('.sidebar-icon').hover();await expect(tip(page)).toBeVisible();
    const label=await link.locator('.sidebar-label').boundingBox();
    const hit=await page.evaluate(({x,y})=>document.elementFromPoint(x,y)?.closest('a')?.getAttribute('href'),{x:label.x+label.width/2,y:label.y+label.height/2});
    expect(hit).toBe('students.php');await link.locator('.sidebar-label').click();await expect(page).toHaveURL('http://sidebar.test/admin/students.php');
});
test('automatic expansion frames keep tooltips outside links and hoverable after leaving',async({page})=>{
    await mount(page,{automatic:true,collapsed:true});await widthIs(page,80);
    await page.evaluate(()=>{window.tooltipFrames=[];const start=performance.now();function sample(){const t=document.getElementById('sidebar-navigation-tooltip'),s=document.getElementById('sidebar');if(t&&!t.hidden)window.tooltipFrames.push({left:t.getBoundingClientRect().left,right:s.getBoundingClientRect().right});if(performance.now()-start<450)requestAnimationFrame(sample);}sample();});
    await page.locator('.sidebar-nav .sidebar-icon').first().hover();await widthIs(page,280);await page.waitForTimeout(480);
    const frames=await page.evaluate(()=>window.tooltipFrames);expect(frames.length).toBeGreaterThan(3);expect(frames.every(f=>f.left>=f.right)).toBe(true);
    await tip(page).hover();await widthIs(page,80);await page.waitForTimeout(260);await expect(tip(page)).toBeVisible();
    await page.locator('#content-control').hover();await expect(tip(page)).toBeHidden();
});
test('tooltip keeps badge descriptions, is hoverable and fits the viewport',async({page})=>{
    await mount(page,{width:1024,height:600,collapsed:true});
    const badged=page.locator('.sidebar-link[aria-describedby]').first();const initial=await badged.getAttribute('aria-describedby');
    await badged.locator('.sidebar-icon').hover();await expect(tip(page)).toBeVisible();
    expect((await badged.getAttribute('aria-describedby')).split(' ')).toContain(initial);
    await tip(page).hover();await expect(tip(page)).toBeVisible();
    const box=await tip(page).boundingBox();expect(box.x).toBeGreaterThanOrEqual(0);expect(box.x+box.width).toBeLessThanOrEqual(1024);
    await page.keyboard.press('Escape');await expect(tip(page)).toBeHidden();await expect(badged).toHaveAttribute('aria-describedby',initial);
    await page.locator('#content-control').hover();await badged.locator('.sidebar-icon').hover();await expect(tip(page)).toBeVisible();
});
async function holdIcon(page){
    const icon=page.locator('.sidebar-nav .sidebar-icon').first();await icon.scrollIntoViewIfNeeded();await expect.poll(()=>page.locator('#sidebar').evaluate(el=>Math.abs(el.getBoundingClientRect().left))).toBeLessThan(0.5);const box=await icon.boundingBox();
    const session=await page.context().newCDPSession(page);
    await session.send('Input.dispatchTouchEvent',{type:'touchStart',touchPoints:[{x:box.x+box.width/2,y:box.y+box.height/2}]});
    await expect(tip(page)).toBeVisible();
    await session.send('Input.dispatchTouchEvent',{type:'touchEnd',touchPoints:[]});
    await session.detach();
}
for(const role of ['admin','faculty','student']){
    test(`${role}: touch hold reveals tooltip; tap, drawer and desktop state remain usable`,async({browser})=>{
        const context=await browser.newContext({hasTouch:true,isMobile:true,viewport:{width:1440,height:900}});const page=await context.newPage();
        try{
            await mount(page,{role});await page.locator('#sidebar-collapse-toggle').tap();await widthIs(page,80);
            const initial=page.url();await holdIcon(page);await expect(page).toHaveURL(initial);await widthIs(page,80);
            await page.setViewportSize({width:390,height:844});await expect(page.locator('#sidebar')).toHaveCSS('visibility','hidden');await expect(page.locator('#mobile-nav-toggle')).toHaveAttribute('aria-expanded','false');
            await page.locator('#mobile-nav-toggle').tap();await expect(page.locator('#sidebar')).toHaveClass(/sidebar--open/);
            await expect(page.locator('.sidebar-label').first()).toBeVisible();await holdIcon(page);
            const t=await tip(page).boundingBox();expect(t.x).toBeGreaterThanOrEqual(0);expect(t.x+t.width).toBeLessThanOrEqual(390);
            const item=menu(role).items.find(i=>i.href!=='index.php');await page.getByRole('link',{name:item.label,exact:true}).tap();
            await expect(page).toHaveURL(`http://sidebar.test/${role}/${item.href}`);
            await page.setViewportSize({width:1440,height:900});await widthIs(page,80);await uncovered(page);
        }finally{await context.close();}
    });
}
test('reduced motion, enlarged text and intermediate frames keep content uncovered',async({page})=>{
    await mount(page,{width:1024,height:768,textSize:'extra-large',motion:'reduce'});await widthIs(page,280);await uncovered(page);
    expect(await page.locator('#sidebar').evaluate(el=>Math.max(...getComputedStyle(el).transitionDuration.split(',').map(parseFloat)))).toBeLessThanOrEqual(0.00001);
    for(const el of await page.locator('.sidebar-nav .sidebar-label').all()) await expect(el).toHaveCSS('white-space','nowrap');
    await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);await uncovered(page);
    await page.evaluate(()=>window.UDMDisplayPreferences.savePreferences({motion:'allow'}));
    await page.evaluate(()=>{window.sidebarFrames=[];let n=0;function sample(){const s=document.getElementById('sidebar').getBoundingClientRect(),m=document.querySelector('.main-content').getBoundingClientRect();window.sidebarFrames.push({gap:m.left-s.right,icon:document.querySelector('.sidebar-icon').getBoundingClientRect().x,overflow:document.documentElement.scrollWidth>innerWidth});if(n++<25)requestAnimationFrame(sample);}requestAnimationFrame(sample);});
    await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,280);await page.waitForTimeout(450);
    const frames=await page.evaluate(()=>window.sidebarFrames);expect(frames.every(f=>f.gap>=-1&&!f.overflow)).toBe(true);
    expect(Math.max(...frames.map(f=>f.icon))-Math.min(...frames.map(f=>f.icon))).toBeLessThanOrEqual(1);
});
test('blocked storage and repeated initialization keep manual controls operable',async({page})=>{
    await mount(page);await page.evaluate(()=>{window.UDM.initDesktopNav();window.UDM.initDesktopNav();Storage.prototype.setItem=function(){throw new Error('Storage unavailable');};});
    await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);await page.locator('#theme-toggle-btn').click();await widthIs(page,80);await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,280);
});
test('malformed sidebar preferences safely restore the expanded manual default',async({page})=>{
    await mount(page);await page.evaluate(key=>localStorage.setItem(key,JSON.stringify({automaticSidebarExpansion:'true',sidebarCollapsed:'true'})),KEY);
    await page.reload();await widthIs(page,280);await page.locator('#content-control').hover();await widthIs(page,280);
});

test('versioned shared scripts upgrade a warm browser cache and retain manual state',async({page})=>{
    let base,upgraded=false,oldFetches=0,currentFetches=0;
    const footer=readFileSync(resolve('includes/footer.php'),'utf8');
    const server=createServer((request,response)=>{
        const path=new URL(request.url,base);
        if(path.pathname==='/assets/js/ui.js'){
            response.writeHead(200,{'Content-Type':'text/javascript','Cache-Control':'public,max-age=3600'});
            if(path.searchParams.has('v')){currentFetches++;response.end(ui);}else{oldFetches++;response.end('window.cachedOldSidebar=true;');}return;
        }
        if(path.pathname==='/assets/js/display-preferences.js'){response.writeHead(200,{'Content-Type':'text/javascript'});response.end(preferences);return;}
        if(path.pathname.endsWith('.png')){response.writeHead(200,{'Content-Type':'image/png'});response.end(logo);return;}
        const scripts=upgraded?[...footer.matchAll(/<script src="([^"]+)"><\/script>/g)].map(m=>'<script src="'+m[1].replace(/<\?= BASE_URL \?>/g,base).replace(/<\?=[\s\S]*?\?>/g,'20261029')+'"></script>').join(''):`<script src="${base}assets/js/ui.js"></script>`;
        response.writeHead(200,{'Content-Type':'text/html','Cache-Control':'no-store'});
        response.end(`<!doctype html><html><head><script>${preRender}</script><style>${css}\n${accessibilityCss}</style></head><body>${sidebar('admin','index.php').replaceAll('http://sidebar.test/',base)}<main class="main-content"><button id="content-control">Content</button></main>${scripts}</body></html>`);
    });
    await new Promise(resolve=>server.listen(0,'127.0.0.1',resolve));base=`http://127.0.0.1:${server.address().port}/`;
    try{
        await page.goto(base+'admin/index.php');expect(await page.evaluate(()=>window.cachedOldSidebar)).toBe(true);
        upgraded=true;await page.reload();await widthIs(page,280);await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);
        await page.locator('.sidebar-nav .sidebar-icon').first().hover();await widthIs(page,80);await page.reload();await widthIs(page,80);
        expect(oldFetches).toBe(1);expect(currentFetches).toBeGreaterThanOrEqual(1);
    }finally{await page.goto('about:blank');server.closeAllConnections();await new Promise(resolve=>server.close(resolve));}
});

// Explicit live opt-in. Only login, read-only GETs and browser-local preferences.
for(const role of ['admin','faculty','student']){
    test(`authenticated ${role}: manual state, Accessibility preference, routes and logout`,async({page})=>{
        const base=process.env.SIDEBAR_LIVE_BASE_URL,id=process.env[`SIDEBAR_${role.toUpperCase()}_ID`],password=process.env[`SIDEBAR_${role.toUpperCase()}_PASSWORD`];
        test.skip(!base||!id||!password,'Explicit local URL and synthetic account required.');
        const writes=[];await page.route('**/*',route=>{const r=route.request();if(!['GET','HEAD','OPTIONS'].includes(r.method())&&!new URL(r.url()).pathname.endsWith('/login.php')){writes.push(r.url());return route.abort();}return route.continue();});
        await page.goto(`${base}login.php`);await page.locator('[name="userid"]').fill(id);await page.locator('[name="password"]').fill(password);await page.getByRole('button',{name:'Login to Portal',exact:true}).click();
        await expect(page).toHaveURL(`${base}${role}/index.php`);await page.waitForLoadState('domcontentloaded');await expect.poll(()=>page.locator('#sidebar').evaluate(el=>Boolean(el._udmDesktopNavBound))).toBe(true);
        try{
            await widthIs(page,280);await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);
            const item=role==='admin'?['Students','students.php']:role==='faculty'?['Dashboard','dashboard.php']:['Grades & History','grades.php'];
            await page.locator('#sidebar').getByRole('link',{name:item[0],exact:true}).click();await expect(page).toHaveURL(`${base}${role}/${item[1]}`);await widthIs(page,80);
            await expect(page.locator('#sidebar').getByRole('link',{name:item[0],exact:true})).toHaveAttribute('aria-current','page');
            await page.locator('#sidebar').getByRole('link',{name:'Settings',exact:true}).click();await page.waitForLoadState('domcontentloaded');await page.locator('#displayPrefToggleBtn').click();
            const auto=page.getByRole('checkbox',{name:'Automatic sidebar expansion',exact:true});await expect(auto).not.toBeChecked();await auto.check();await widthIs(page,280);
            await page.reload();await page.locator('#displayPrefToggleBtn').click();await expect(auto).toBeChecked();
            await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,80);await page.locator('.main-content').hover({position:{x:30,y:30}});await page.locator('.sidebar-nav .sidebar-icon').first().hover();await widthIs(page,280);
            await page.locator('.main-content').hover({position:{x:30,y:30}});await widthIs(page,80);await auto.uncheck();await page.locator('#sidebar-collapse-toggle').click();await widthIs(page,280);
            for(const [width,height] of [[1440,900],[1280,600],[1024,768]]){await page.setViewportSize({width,height});for(let i=0;i<2;i++){await uncovered(page);await page.locator('#theme-toggle-btn').click();await page.locator('.main-content').hover({position:{x:30,y:30}});await widthIs(page,280);}}
            expect(writes).toEqual([]);
        }finally{await page.goto(`${base}logout.php`);await expect(page).toHaveURL(`${base}login.php`);}
    });
}
