import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const screenshotDir = process.env.VNZ_PREFERENCE_BUTTON_SCREENSHOTS;
if (screenshotDir) {
    assert.ok(resolve(screenshotDir).startsWith(root + sep + '.qa-seller-preference-'));
    await mkdir(screenshotDir, { recursive: true });
}

const settings = await readFile(join(root, 'account/pages/settings.php'), 'utf8');
assert.ok(settings.includes('class="profile-save-button seller-preference-save-button"'));
const languageCopy = await readFile(join(root, 'assets/js/seller-language-copy.js'), 'utf8');
assert.ok(languageCopy.includes("'SAVE LOGIN PREFERENCE': 'I-SAVE ANG LOGIN SETTING'"));
assert.ok(languageCopy.includes("'SAVE PREFERENCES': 'I-SAVE ANG SETTINGS'"));

const labels = {
    en: ['SAVE LOGIN PREFERENCE', 'SAVE PREFERENCES'],
    fil: ['I-SAVE ANG LOGIN SETTING', 'I-SAVE ANG SETTINGS']
};
function actualForm(id, language) {
    const start = settings.indexOf('<form', settings.lastIndexOf('<form', settings.indexOf(`id="${id}"`)));
    const end = settings.indexOf('</form>', start);
    assert.ok(start >= 0 && end > start, 'Render the actual settings form: ' + id);
    return settings.slice(start, end + 7)
        .replace(/<\?=[\s\S]*?\?>/g, '')
        .replace('SAVE LOGIN PREFERENCE', labels[language][0])
        .replace('SAVE PREFERENCES', labels[language][1])
        .replace('<button class="profile-save-button seller-preference-save-button"', '<button id="sellerLoginPreferenceSubmit" class="profile-save-button seller-preference-save-button"');
}
const html = (theme, language) => `<!doctype html>
<html lang="${language === 'fil' ? 'fil' : 'en'}" data-seller-theme="${theme}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_dashboard.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_theme.css">
</head>
<body>
<main id="contentArea">
<section class="profile-form-section seller-2fa-section">
<div class="profile-section-title">Two-Factor Authentication</div>
${actualForm('seller2faLoginRequirementForm', language)}
</section>
${actualForm('sellerNotificationForm', language)}
</main>
</body>
</html>`;

let server;
let client;
let checks = 0;

async function wait(expression) {
    for (let i = 0; i < 100; i++) {
        if (await client.evaluate(expression)) return;
        await delay(50);
    }
    throw new Error('Browser condition timed out: ' + expression);
}

async function pressTab() {
    await client.call('Input.dispatchKeyEvent', {
        type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
    });
    await client.call('Input.dispatchKeyEvent', {
        type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
    });
}

try {
    server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/settings') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html(
                url.searchParams.get('theme') === 'dark' ? 'dark' : 'light',
                url.searchParams.get('language') === 'fil' ? 'fil' : 'en'
            ));
            return;
        }
        const relative = url.pathname.replace(/^\/VNZ-TECH\//, '');
        if (!/^assets\/css\/seller_(dashboard|theme)\.css$/.test(relative)) {
            res.writeHead(404);
            res.end();
            return;
        }
        res.setHeader('Content-Type', 'text/css');
        res.end(await readFile(join(root, relative)));
    });
    await new Promise(resolveListen => server.listen(0, '127.0.0.1', resolveListen));
    client = await browser();

    for (const language of Object.keys(labels)) {
        for (const theme of ['light', 'dark']) {
            for (const viewport of [
                { width: 1200, height: 900 },
                { width: 542, height: 812 },
                { width: 375, height: 812 }
            ]) {
                await client.call('Emulation.setDeviceMetricsOverride', {
                    ...viewport,
                    deviceScaleFactor: 1,
                    mobile: viewport.width < 600
                });
                await client.navigate(
                    `http://127.0.0.1:${server.address().port}/settings?theme=${theme}&language=${language}`
                );
                await wait("document.querySelector('#sellerNotificationSubmit')");

                const metrics = await client.evaluate(`(()=> {
                    const login=document.querySelector('#sellerLoginPreferenceSubmit');
                    const notification=document.querySelector('#sellerNotificationSubmit');
                    const choice=document.querySelector('.seller-2fa-login-choice').getBoundingClientRect();
                    const select=document.querySelector('#seller2faLoginRequired').getBoundingClientRect();
                    const loginActions=login.parentElement;
                    const notificationActions=notification.parentElement;
                    const textLines=button=>{
                        const range=document.createRange();
                        range.selectNodeContents(button);
                        return range.getClientRects().length;
                    };
                    const a=login.getBoundingClientRect();
                    const b=notification.getBoundingClientRect();
                    return {
                        widthA:a.width,
                        widthB:b.width,
                        rightA:a.right,
                        rightB:b.right,
                        heightA:a.height,
                        heightB:b.height,
                        textLinesA:textLines(login),
                        textLinesB:textLines(notification),
                        loginInControlsRow:loginActions.classList.contains('seller-2fa-controls'),
                        notificationInActionRow:notificationActions.classList.contains('profile-form-actions'),
                        loginSharesRow:a.left>=choice.left && a.top<choice.bottom && a.bottom>choice.top,
                        loginWrapsBelowChoice:a.top>=choice.bottom-1,
                        selectTop:select.top,
                        selectBottom:select.bottom,
                        loginTop:a.top,
                        loginBottom:a.bottom,
                        notificationGap:b.top-document.querySelector('.settings-preference-list').getBoundingClientRect().bottom,
                        overflow:document.documentElement.scrollWidth>innerWidth+1
                    };
                })()`);

                assert.ok(Math.abs(metrics.widthA - metrics.widthB) <= 1, JSON.stringify(metrics));
                assert.ok(Math.abs(metrics.rightA - metrics.rightB) <= 1, JSON.stringify(metrics));
                assert.equal(metrics.heightA, metrics.heightB, JSON.stringify(metrics));
                assert.equal(metrics.textLinesA, 1, JSON.stringify(metrics));
                assert.equal(metrics.textLinesB, 1, JSON.stringify(metrics));
                assert.equal(metrics.loginInControlsRow, true, JSON.stringify(metrics));
                assert.equal(metrics.notificationInActionRow, true, JSON.stringify(metrics));
                if (viewport.width >= 500) {
                    assert.equal(metrics.loginSharesRow, true, JSON.stringify(metrics));
                    assert.ok(Math.abs(metrics.loginTop - metrics.selectTop) <= 1, JSON.stringify(metrics));
                    assert.ok(Math.abs(metrics.loginBottom - metrics.selectBottom) <= 1, JSON.stringify(metrics));
                } else {
                    assert.equal(metrics.loginWrapsBelowChoice, true, JSON.stringify(metrics));
                }
                assert.ok(metrics.notificationGap >= 16, JSON.stringify(metrics));
                assert.equal(metrics.overflow, false, JSON.stringify(metrics));

                await client.evaluate('document.activeElement.blur(); document.body.tabIndex=-1; document.body.focus()');
                for (const id of ['sellerLoginPreferenceSubmit', 'sellerNotificationSubmit']) {
                    for (let i = 0; i < 10 && !await client.evaluate(`document.activeElement.id === '${id}'`); i++) await pressTab();
                    const focus = await client.evaluate(`(()=> {
                        const button=document.querySelector('#${id}');
                        const style=getComputedStyle(button);
                        return {
                            focused:document.activeElement===button,
                            outline:style.outlineStyle,
                            outlineWidth:style.outlineWidth,
                            outlineColor:style.outlineColor
                        };
                    })()`);
                    assert.equal(focus.focused, true, JSON.stringify(focus));
                    assert.notEqual(focus.outline, 'none', JSON.stringify(focus));
                    assert.notEqual(focus.outlineWidth, '0px', JSON.stringify(focus));
                    assert.notEqual(focus.outlineColor, 'rgba(0, 0, 0, 0)', JSON.stringify(focus));
                }

                if (screenshotDir && language === 'en') {
                    const screenshot = await client.call('Page.captureScreenshot', { format: 'png' });
                    await writeFile(
                        join(screenshotDir, `seller-preferences-${theme}-${viewport.width}.png`),
                        Buffer.from(screenshot.data, 'base64')
                    );
                }
                checks += viewport.width >= 500 ? 20 : 18;
            }
        }
    }

    console.log(`PASS ${checks} seller preference button checks across English/Filipino, light/dark, desktop/mobile; actual seller CSS and translated labels used with synthetic settings content.`);
} finally {
    if (client) await client.close();
    if (server) {
        server.closeAllConnections();
        await new Promise(resolveClose => server.close(resolveClose));
    }
}
