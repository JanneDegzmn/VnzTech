import { createServer } from 'node:http';
import { mkdir, readFile, writeFile } from 'node:fs/promises';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const screenshotDir = process.env.VNZ_2FA_SCREENSHOTS;
if (screenshotDir) {
    assert.ok(resolve(screenshotDir).startsWith(root + sep + '.qa-2fa-'));
    await mkdir(screenshotDir, { recursive: true });
}
const settings = await readFile(join(root, 'account/pages/settings.php'), 'utf8');
assert.ok(settings.includes('class="profile-save-button seller-2fa-disable-button"'));

let server;
let client;
let checks = 0;

const html = theme => `<!doctype html>
<html lang="en" data-seller-theme="${theme}">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_dashboard.css">
<link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_theme.css">
</head>
<body>
<main id="contentArea">
<section class="profile-form-section seller-2fa-section">
<div class="profile-section-title">Two-Factor Authentication</div>
<p class="settings-supporting-text">Authenticator is set up and can be used for account recovery.</p>
<div class="seller-2fa-status is-enabled">Authenticator enabled</div>
<form class="seller-2fa-form">
<div class="seller-2fa-controls seller-2fa-disable-controls">
<div><label for="currentPassword">Current password</label><input id="currentPassword" type="password"></div>
<div><label for="authenticatorCode">Authenticator code</label><input id="authenticatorCode" type="text"></div>
<button class="profile-save-button seller-2fa-disable-button" type="button">DISABLE 2FA</button>
</div>
</form>
</section>
</main>
</body>
</html>`;

async function wait(expression) {
    for (let i = 0; i < 100; i++) {
        if (await client.evaluate(expression)) return;
        await delay(50);
    }
    throw new Error('Browser condition timed out: ' + expression);
}

const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
    const channel = value / 255;
    return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
}).reduce((sum, value, index) => sum + value * [0.2126, 0.7152, 0.0722][index], 0);
const contrast = (background, foreground) => (
    (Math.max(luminance(background), luminance(foreground)) + 0.05)
    / (Math.min(luminance(background), luminance(foreground)) + 0.05)
);

try {
    server = createServer(async (req, res) => {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/settings') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(html(url.searchParams.get('theme') === 'dark' ? 'dark' : 'light'));
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

    for (const theme of ['light', 'dark']) {
        for (const viewport of [{ width: 1200, height: 900 }, { width: 375, height: 812 }]) {
            await client.call('Emulation.setDeviceMetricsOverride', {
                ...viewport,
                deviceScaleFactor: 1,
                mobile: viewport.width < 600
            });
            await client.navigate(`http://127.0.0.1:${server.address().port}/settings?theme=${theme}`);
            await wait("document.querySelector('.seller-2fa-disable-button')");

            const palette = await client.evaluate(`(()=> {
                const button=document.querySelector('.seller-2fa-disable-button');
                const style=getComputedStyle(button);
                return {
                    background:style.backgroundColor,
                    color:style.color,
                    border:style.borderTopColor,
                    width:button.getBoundingClientRect().width,
                    scrollX:document.documentElement.scrollWidth>innerWidth+1,
                    focusStyle:style.outlineStyle
                };
            })()`);
            assert.ok(contrast(palette.background, palette.color) >= 4.5, JSON.stringify(palette));
            const dangerRgb = palette.background.match(/\d+/g).slice(0, 3).map(Number);
            assert.ok(dangerRgb[0] > dangerRgb[1] && dangerRgb[0] > dangerRgb[2], JSON.stringify(palette));
            assert.ok(palette.width > 0 && !palette.scrollX, JSON.stringify(palette));

            await client.evaluate("document.body.focus(); document.activeElement.blur()");
            for (let tab = 0; tab < 3; tab++) {
                await client.call('Input.dispatchKeyEvent', {
                    type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
                });
                await client.call('Input.dispatchKeyEvent', {
                    type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9
                });
            }
            const focus = await client.evaluate(`(()=> {
                const button=document.querySelector('.seller-2fa-disable-button');
                const style=getComputedStyle(button);
                return {
                    focused:document.activeElement===button,
                    outline:style.outlineStyle,
                    outlineColor:style.outlineColor
                };
            })()`);
            assert.equal(focus.focused, true);
            assert.equal(focus.outline, 'solid');
            assert.notEqual(focus.outlineColor, 'rgba(0, 0, 0, 0)');
            if (screenshotDir) {
                const screenshot = await client.call('Page.captureScreenshot', { format: 'png' });
                await writeFile(
                    join(screenshotDir, `seller-2fa-${theme}-${viewport.width}.png`),
                    Buffer.from(screenshot.data, 'base64')
                );
            }
            checks += 2;
        }
    }

    console.log(`PASS ${checks} seller 2FA button theme, contrast, keyboard focus, and viewport checks; actual settings class and seller CSS used with synthetic settings content.`);
} finally {
    if (client) await client.close();
    if (server) {
        server.closeAllConnections();
        await new Promise(resolveClose => server.close(resolveClose));
    }
}
