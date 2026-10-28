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

async function mount(page, { role = 'admin', theme = 'dark', width = 1440, height = 900, motion = 'allow', textSize = 'default' } = {}) {
    await page.setViewportSize({ width, height });
    await page.route('http://sidebar.test/**', route => {
        const path = new URL(route.request().url()).pathname;
        if (path.endsWith('logo_sidebar.png')) return route.fulfill({ contentType: 'image/png', body: logo });
        if (!path.endsWith('.php')) return route.abort();
        const markup = sidebar(role, path.split('/').pop());
        return route.fulfill({ contentType: 'text/html', body: `<!doctype html><html lang="en" data-theme="${theme}" data-color-style="ccs" data-motion="${motion}" data-font-size="${textSize}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><style>${css}\n${accessibilityCss}</style></head><body>${markup}<main class="main-content"><h1>Navigation fixture</h1><button id="content-control" type="button">Content control</button><section class="card"><svg aria-label="Synthetic layout sample" viewBox="0 0 500 100" style="display:block;width:100%;height:auto"><path d="M0 80 L150 20 L300 50 L500 10" fill="none" stroke="currentColor"/></svg><table style="width:100%"><caption>Synthetic records</caption><tr><th>Record</th><th>Status</th></tr><tr><td>Demo record</td><td>Available</td></tr></table></section></main><script>${ui}</script></body></html>` });
    });
    await page.goto(`http://sidebar.test/${role}/index.php`);
}

async function widthIs(page, expected) {
    await expect.poll(() => page.locator('#sidebar').evaluate(el => Math.round(el.getBoundingClientRect().width))).toBe(expected);
}

async function uncovered(page) {
    const geometry = await page.evaluate(() => {
        const rail = document.getElementById('sidebar').getBoundingClientRect();
        const main = document.querySelector('.main-content').getBoundingClientRect();
        return { railRight: rail.right, mainLeft: main.left, mainRight: main.right, viewport: innerWidth, overflow: document.documentElement.scrollWidth > innerWidth };
    });
    expect(geometry.mainLeft).toBeGreaterThanOrEqual(geometry.railRight - 1);
    expect(geometry.mainRight).toBeLessThanOrEqual(geometry.viewport + 1);
    expect(geometry.overflow).toBe(false);
}

for (const role of ['admin', 'faculty', 'student']) {
    for (const theme of ['light', 'dark']) {
        for (const [width, height] of [[1440, 900], [1280, 600], [1024, 768]]) {
            test(`${role} ${theme} ${width}: hover rail releases content and collapses after mouse leave`, async ({ page }) => {
                await mount(page, { role, theme, width, height });
                await widthIs(page, 80);
                await uncovered(page);
                const firstIcon = page.locator('.sidebar-nav .sidebar-icon').first();
                const compactX = (await firstIcon.boundingBox()).x;
                const links = page.locator('.sidebar-nav a');
                const before = await links.evaluateAll(nodes => nodes.map(a => ({ href: a.getAttribute('href'), icon: a.querySelector('svg').innerHTML })));
                for (const item of menu(role).items) await expect(page.getByRole('link', { name: item.label, exact: true })).toHaveAttribute('href', item.href);
                await expect(page.locator('.sidebar-label').first()).toHaveCSS('opacity', '0');
                await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
                await widthIs(page, 280);
                await uncovered(page);
                expect((await firstIcon.boundingBox()).x).toBeCloseTo(compactX, 0);
                await expect(page.locator('.sidebar-label').first()).toHaveCSS('opacity', '1');
                await expect(page.locator('.sidebar-label').first()).toHaveCSS('white-space', 'nowrap');
                await page.locator('#logout-btn').hover();
                await widthIs(page, 280);
                await page.locator('#content-control').hover();
                await widthIs(page, 80);
                await uncovered(page);
                expect(await links.evaluateAll(nodes => nodes.map(a => ({ href: a.getAttribute('href'), icon: a.querySelector('svg').innerHTML })))).toEqual(before);
                await expect(page.locator('.sidebar-link.active')).toHaveAttribute('aria-current', 'page');
                await expect(page.getByRole('link', { name: 'Logout', exact: true })).toHaveAttribute('href', 'http://sidebar.test/logout.php');
            });
        }
    }
}

test('keyboard entry reveals labels; Escape and toggle preserve focus and control', async ({ page }) => {
    await mount(page);
    await widthIs(page, 80);
    await page.keyboard.press('Tab');
    await widthIs(page, 280);
    await expect(page.locator('.sidebar-nav a').first()).toBeFocused();
    await page.keyboard.press('Escape');
    await widthIs(page, 80);
    const toggle = page.locator('#sidebar-collapse-toggle');
    await expect(toggle).toBeFocused();
    await expect(toggle).toHaveAttribute('aria-expanded', 'false');
    await page.keyboard.press('Enter');
    await widthIs(page, 280);
    await expect(toggle).toHaveAttribute('aria-expanded', 'true');
    await page.locator('#content-control').focus();
    await widthIs(page, 80);
});

test('mouse activation does not leave a focus-stuck rail; navigation retains routes and active state', async ({ page }) => {
    await mount(page);
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await widthIs(page, 280);
    await page.locator('#theme-toggle-btn').click();
    await expect(page.locator('html')).toHaveAttribute('data-theme', 'light');
    await page.locator('#content-control').hover();
    await widthIs(page, 80);
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await page.getByRole('link', { name: 'Students', exact: true }).click();
    await expect(page).toHaveURL('http://sidebar.test/admin/students.php');
    await page.locator('#content-control').hover();
    await widthIs(page, 80);
    await expect(page.getByRole('link', { name: 'Students', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.locator('#logout-btn')).toHaveAttribute('href', 'http://sidebar.test/logout.php');
});

for (const role of ['admin', 'faculty', 'student']) {
    test(`${role}: desktop touch toggle and existing small-screen drawer remain independent`, async ({ browser }) => {
        const context = await browser.newContext({ hasTouch: true, viewport: { width: 1024, height: 768 } });
        const page = await context.newPage();
        try {
            await mount(page, { role, width: 1024, height: 768 });
            await widthIs(page, 80);
            await page.locator('#sidebar-collapse-toggle').tap();
            await widthIs(page, 280);
            await uncovered(page);
            await page.locator('#content-control').tap();
            await widthIs(page, 80);
            await page.setViewportSize({ width: 390, height: 844 });
            await expect(page.locator('#sidebar-collapse-toggle')).toBeHidden();
            await page.locator('#mobile-nav-toggle').tap();
            await expect(page.locator('#sidebar')).toHaveClass(/sidebar--open/);
            await expect(page.locator('#mobile-nav-toggle')).toHaveAttribute('aria-expanded', 'true');
            await expect(page.locator('.sidebar-label').first()).toBeVisible();
            await page.locator('#sidebar-drawer-close-btn').tap();
            await expect(page.locator('#sidebar')).not.toHaveClass(/sidebar--open/);
            await page.setViewportSize({ width: 1024, height: 768 });
            await widthIs(page, 80);
            await uncovered(page);
            await expect(page.locator('body')).not.toHaveClass(/body--nav-open/);
        } finally { await context.close(); }
    });
}

test('reduced motion and enlarged text keep labels single-line and content uncovered', async ({ page }) => {
    await mount(page, { role: 'faculty', width: 1024, height: 768, motion: 'reduce', textSize: 'extra-large' });
    await widthIs(page, 80);
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await widthIs(page, 280);
    const seconds = await page.locator('#sidebar').evaluate(el => Math.max(...getComputedStyle(el).transitionDuration.split(',').map(parseFloat)));
    expect(seconds).toBeLessThanOrEqual(0.001); // Existing reduced-motion preference is 0.001ms.
    for (const label of await page.locator('.sidebar-nav .sidebar-label').all()) await expect(label).toHaveCSS('white-space', 'nowrap');
    await uncovered(page);
});

test('Student preserves Enrollment Planning, Grades and footer controls on the icon rail', async ({ page }) => {
    await mount(page, { role: 'student' });
    await widthIs(page, 80);
    await expect(page.locator('#sidebar-collapse-toggle')).toBeVisible();
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await widthIs(page, 280);
    await expect(page.getByRole('link', { name: 'Enrollment Planning', exact: true })).toHaveAttribute('href', 'enrolment_planning.php');
    await expect(page.getByRole('link', { name: 'Grades & History', exact: true })).toHaveAttribute('href', 'grades.php');
    await expect(page.locator('#theme-toggle-btn')).toBeVisible();
    await page.getByRole('link', { name: 'Grades & History', exact: true }).click();
    await expect(page).toHaveURL('http://sidebar.test/student/grades.php');
    await page.locator('#content-control').hover();
    await widthIs(page, 80);
    await expect(page.getByRole('link', { name: 'Grades & History', exact: true })).toHaveAttribute('aria-current', 'page');
    await expect(page.getByRole('link', { name: 'Logout', exact: true })).toHaveAttribute('href', 'http://sidebar.test/logout.php');
});

test('returning to desktop honors a pointer already inside the rail', async ({ page }) => {
    await mount(page);
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await widthIs(page, 280);
    await page.setViewportSize({ width: 390, height: 844 });
    await expect(page.locator('#mobile-nav-toggle')).toBeVisible();
    await page.setViewportSize({ width: 1440, height: 900 });
    await widthIs(page, 280);
    await page.locator('#content-control').hover();
    await widthIs(page, 80);
});

test('intermediate expansion frames keep icons aligned and content uncovered', async ({ page }) => {
    await mount(page);
    await widthIs(page, 80);
    await page.evaluate(() => {
        window.sidebarFrames = [];
        const start = performance.now();
        function sample() {
            const rail = document.getElementById('sidebar').getBoundingClientRect();
            const main = document.querySelector('.main-content').getBoundingClientRect();
            const icon = document.querySelector('.sidebar-nav .sidebar-icon').getBoundingClientRect();
            window.sidebarFrames.push({ width: rail.width, right: rail.right, left: main.left, iconX: icon.x });
            if (performance.now() - start < 300) requestAnimationFrame(sample);
        }
        sample();
    });
    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
    await widthIs(page, 280);
    const frames = await page.evaluate(() => window.sidebarFrames);
    expect(frames.some(frame => frame.width > 81 && frame.width < 279)).toBe(true);
    for (const frame of frames) {
        expect(frame.left).toBeGreaterThanOrEqual(frame.right - 1);
        expect(frame.iconX).toBeCloseTo(frames[0].iconX, 0);
    }
});

test('an existing browser with cached pre-sidebar JavaScript receives the new hover controller', async ({ page }) => {
    let upgraded = false;
    let base;
    const server = createServer((request, response) => {
        const url = new URL(request.url, base);
        if (url.pathname === '/assets/js/ui.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript', 'Cache-Control': 'public, max-age=3600, immutable' });
            response.end(upgraded ? ui : 'window.UDM = window.UDM || {};');
        } else if (url.pathname === '/assets/js/display-preferences.js') {
            response.writeHead(200, { 'Content-Type': 'text/javascript' });
            response.end(readFileSync(resolve('assets/js/display-preferences.js')));
        } else if (url.pathname.endsWith('logo_sidebar.png')) {
            response.writeHead(200, { 'Content-Type': 'image/png' });
            response.end(logo);
        } else if (url.pathname === '/warm.php') {
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' });
            response.end('<!doctype html><script src="/assets/js/ui.js"></script>Earlier application version');
        } else if (url.pathname === '/admin/index.php') {
            // Actual footer PHP has no DB bootstrap; its glossary is static markup.
            const footerPath = resolve('includes/footer.php').replaceAll('\\', '/');
            const footer = execFileSync(php, ['-r', `define('BASE_URL',${JSON.stringify(base)});include ${JSON.stringify(footerPath)};`], { encoding: 'utf8' });
            const navigation = sidebar('admin', 'index.php').replaceAll('http://sidebar.test/', base);
            response.writeHead(200, { 'Content-Type': 'text/html', 'Cache-Control': 'no-store' });
            response.end(`<!doctype html><html lang="en" data-theme="dark" data-color-style="ccs"><head><style>${css}</style></head><body>${navigation}<main class="main-content"><h1>Updated application</h1></main>${footer}`);
        } else { response.writeHead(404); response.end(); }
    });
    await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
    base = `http://127.0.0.1:${server.address().port}/`;
    try {
        await page.setViewportSize({ width: 1440, height: 900 });
        // No Playwright routes: real browser HTTP caching must remain enabled.
        await page.goto(`${base}warm.php`);
        upgraded = true;
        await page.goto(`${base}admin/index.php`);
        await widthIs(page, 80);
        await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
        await widthIs(page, 280);
        await page.locator('.main-content').hover({ position: { x: 30, y: 30 } });
        await widthIs(page, 80);
    } finally {
        server.closeAllConnections();
        await new Promise(resolve => server.close(resolve));
    }
});

// Explicit opt-in only. Normal login and read-only portal GETs; no predictions,
// submissions, exports, migrations, account writes, or academic-data mutations.
for (const role of ['admin', 'faculty', 'student']) {
    test(`authenticated ${role}: actual login, sidebar geometry, theme and navigation`, async ({ page }) => {
        const base = process.env.SIDEBAR_LIVE_BASE_URL;
        const id = process.env[`SIDEBAR_${role.toUpperCase()}_ID`];
        const password = process.env[`SIDEBAR_${role.toUpperCase()}_PASSWORD`];
        test.skip(!base || !id || !password, 'Explicit local URL and synthetic account required; generated fixtures do not prove authenticated behavior.');
        const errors = [];
        page.on('pageerror', error => errors.push(error.message));
        await page.setViewportSize({ width: 1440, height: 900 });
        await page.goto(`${base}login.php`);
        await page.locator('[name="userid"]').fill(id);
        await page.locator('[name="password"]').fill(password);
        await page.getByRole('button', { name: 'Login to Portal', exact: true }).click();
        await expect(page).toHaveURL(`${base}${role}/index.php`);
        try {
            for (const [width, height] of [[1440, 900], [1280, 600], [1024, 768]]) {
                await page.setViewportSize({ width, height });
                for (let theme = 0; theme < 2; theme++) {
                    await page.locator('.main-content').hover({ position: { x: 30, y: 30 } });
                    await widthIs(page, 80);
                    await uncovered(page);
                    await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
                    await widthIs(page, 280);
                    await uncovered(page);
                    await expect(page.locator('.sidebar-link.active')).toHaveAttribute('aria-current', 'page');
                    await page.locator('#theme-toggle-btn').click();
                    await page.locator('.main-content').hover({ position: { x: 30, y: 30 } });
                    await widthIs(page, 80);
                }
            }
            await page.locator('#sidebar').hover({ position: { x: 30, y: 30 } });
            const label = role === 'admin' ? 'Students' : role === 'student' ? 'Grades & History' : 'Dashboard';
            const destination = role === 'admin' ? 'students.php' : role === 'student' ? 'grades.php' : 'dashboard.php';
            await page.locator('#sidebar').getByRole('link', { name: label, exact: true }).click();
            await expect(page).toHaveURL(`${base}${role}/${destination}`);
            await page.locator('.main-content').hover({ position: { x: 30, y: 30 } });
            await widthIs(page, 80);
            await expect(page.locator('#sidebar').getByRole('link', { name: label, exact: true })).toHaveAttribute('aria-current', 'page');
            expect(errors).toEqual([]);
        } finally {
            await page.goto(`${base}logout.php`);
            await expect(page).toHaveURL(`${base}login.php`);
        }
    });
}
