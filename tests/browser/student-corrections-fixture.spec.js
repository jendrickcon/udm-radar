import {test, expect} from '@playwright/test';
import {readFileSync} from 'node:fs';
import {execFileSync} from 'node:child_process';

// Generated markup and real local enhancement scripts; no application server or database.
test('empty trend explanation is visible with all disclosures closed', async ({page}) => {
    await page.setContent(`<main><div class="trend-chart"><canvas id="semGwaChart"></canvas></div>
        <p id="trend-chart-status" role="status"></p><details><summary>Exact evidence</summary><table><tr><td>Unavailable</td></tr></table></details>
        <script id="student-trend-data" type="application/json">[{"zone":"estimate","value":null}]</script></main>`);
    await page.addScriptTag({path:'assets/js/student-performance-trend.js'});
    await expect(page.getByRole('status')).toBeVisible();
    await expect(page.getByRole('status')).toContainText('No chart is available because');
    await expect(page.locator('details')).not.toHaveAttribute('open');
    await expect(page.locator('canvas')).toBeHidden();
});

for (const [width,height] of [[1440,900],[1280,600],[1024,768]]) for (const theme of ['light','dark']) {
    test(`generated planning selection ${width}x${height} ${theme}`, async ({page}) => {
        await page.setViewportSize({width,height});
        const html=execFileSync('C:/xampp/php/php.exe',['tests/unit/pef_cc_print_contract_test.php','--render','manual-preview'],{encoding:'utf8'});
        // Prevent remote dependencies and application requests; serve actual repository assets.
        await page.route('**/*', async route => {
            const path=new URL(route.request().url()).pathname.split('/udm-radar/')[1];
            if (path && /^assets\/[\w./-]+\.(css|js)$/.test(path)) {
                await route.fulfill({body:readFileSync(path),contentType:path.endsWith('.css')?'text/css':'application/javascript'});
            } else await route.abort();
        });
        await page.setContent(html.replace('<head>','<head><base href="http://fixture.invalid/udm-radar/student/">'));
        await page.locator('html').evaluate((el,theme)=>el.dataset.theme=theme,theme);
        await page.addScriptTag({content:readFileSync('assets/js/pef-cc.js','utf8')});
        const boxes=page.locator('#pef-cc-subjects input[type=checkbox]');
        await expect(boxes.filter({visible:true}).first()).toBeVisible();
        expect(await boxes.evaluateAll(inputs=>inputs.filter(input=>input.checked).map(input=>input.value))).toEqual(['1','2']);
        await boxes.nth(2).focus();
        await page.keyboard.press('Space');
        await expect(boxes.nth(2)).toBeChecked();
        await expect(page.getByTestId('proposed-count')).toHaveText('3');
        expect(await boxes.nth(2).evaluate(el=>getComputedStyle(el.closest('label')).outlineStyle)).not.toBe('none');
        await page.keyboard.press('Space');
        await expect(boxes.nth(2)).not.toBeChecked();
        expect(await page.evaluate(()=>document.documentElement.scrollWidth<=innerWidth)).toBe(true);
    });
}
