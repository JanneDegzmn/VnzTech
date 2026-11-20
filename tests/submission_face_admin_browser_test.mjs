// Actual PHP panel, admin CSS and viewer JS; flat synthetic JPEG, no application records.
import { createServer } from 'node:http';
import { readFile, writeFile, mkdtemp, rm, mkdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fixture = await mkdtemp(join(tmpdir(), 'vnz-face-admin-ui-'));
const screenshots = process.env.VNZ_FACE_ADMIN_SCREENSHOTS;
if (screenshots) {
    assert.ok(resolve(screenshots).startsWith(root + sep + '.qa-face-'));
    await mkdir(screenshots, { recursive: true });
}
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const source = (await readFile(join(root, 'admin/pages/submission_review.php'), 'utf8')).replaceAll('\r\n', '\n');
const start = source.indexOf('        <?php if ($submissionFaceCanView): ?>');
const closing = '        <?php endif; ?>\n\n    </section>';
const end = source.indexOf(closing, start);
assert.ok(start > 0 && end > start, 'Render the actual submission panel');
const panel = source.slice(start, end + '        <?php endif; ?>'.length);
const templates = {};
for (const state of ['available', 'missing', 'expired', 'unavailable']) {
    const path = join(fixture, state + '.php');
    const meta = state === 'available' ? "['available'=>true]" : state === 'expired' ? "['available'=>false,'state'=>'expired']" : 'null';
    await writeFile(path, `<?php $submissionFaceCanView=true;$submissionFaceMeta=${meta};$submissionFaceUnavailable=${state === 'unavailable' ? 'true' : 'false'};$submissionId=1;?>` + panel);
    const result = spawnSync(php, [path], { encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, result.stderr);
    assert.ok(!/Warning:|Fatal error:/.test(result.stdout));
    templates[state] = result.stdout;
}
// Execute the unchanged real item-photo lightbox handlers as the regression fixture.
const itemMarkupStart = source.indexOf('<div class="submission-photo-lightbox"');
const itemMarkupEnd = source.indexOf('<div class="transaction-chat-approval-modal"', itemMarkupStart);
const itemScriptStart = source.indexOf('    const photoLightbox =');
const itemScriptEnd = source.indexOf('\n})();', itemScriptStart);
assert.ok(itemMarkupStart > 0 && itemMarkupEnd > itemMarkupStart && itemScriptEnd > itemScriptStart);
const itemMarkup = source.slice(itemMarkupStart, itemMarkupEnd);
const itemScript = `(()=>{const reviewPage=document.querySelector('#reviewHost .submission-review-page');\n${source.slice(itemScriptStart, itemScriptEnd)}\n})();`;
const dashboardSource = (await readFile(join(root, 'admin/dashboard.php'), 'utf8')).replaceAll('\r\n', '\n');
const outerEscapeStart = dashboardSource.indexOf("        document.addEventListener('keydown', (event) => {");
const outerEscapeEnd = dashboardSource.indexOf('\n        });', outerEscapeStart);
assert.ok(outerEscapeStart > 0 && outerEscapeEnd > outerEscapeStart);
const outerEscapeScript = dashboardSource.slice(outerEscapeStart, outerEscapeEnd + '\n        });'.length);
const image = spawnSync(php, ['-r', '$i=imagecreatetruecolor(800,600);imagefill($i,0,0,imagecolorallocate($i,80,110,130));imagejpeg($i);imagedestroy($i);'], { windowsHide: true });
assert.equal(image.status, 0);
const imageRequests = [];
const serverErrors = [];
const scriptJson = value => JSON.stringify(value).replaceAll('<', '\\u003c');
const fragment = (state, outcome = 'ready') => `<div class="submission-review-page"><section class="review-card"><div class="section-header"><h2>Seller Information</h2></div>${templates[state].replaceAll('submission_id=1', 'submission_id=1&amp;fixture=' + outcome)}</section><button type="button" class="photo-card" data-lightbox-src="/fixture-item.jpg" data-lightbox-label="Synthetic item photo">Item photo</button><div aria-hidden="true" style="height:1100px"></div></div>`;
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            const theme = url.searchParams.get('theme') || 'light';
            const state = url.searchParams.get('state') || 'available';
            const outcome = url.searchParams.get('outcome') || 'ready';
            assert.ok(['light', 'dark'].includes(theme) && templates[state]);
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(`<!doctype html><html data-admin-theme="${theme}" data-public-theme="${theme === 'light' ? 'dark' : 'light'}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/assets/css/admin_dashboard.css"><link rel="stylesheet" href="/assets/css/admin_theme.css"><link rel="stylesheet" href="/assets/css/admin_submission_review.css"><style>#adminSubmissionModal{position:fixed;inset:12px;z-index:10}.fixture-review-panel{transform:translateZ(0);overflow:hidden;height:100%}#reviewHost{padding:16px;height:100%;overflow:auto}#hiddenReview{display:none}</style></head><body style="overflow:clip"><div class="admin-submission-modal is-open" id="adminSubmissionModal" aria-hidden="false"><div class="fixture-review-panel"><div id="reviewHost"></div></div></div><div id="hiddenReview"></div>${itemMarkup}<script>
window.viewerUrls=new Set();const nativeCreateUrl=URL.createObjectURL.bind(URL),nativeRevokeUrl=URL.revokeObjectURL.bind(URL);URL.createObjectURL=blob=>{const url=nativeCreateUrl(blob);viewerUrls.add(url);return url;};URL.revokeObjectURL=url=>{viewerUrls.delete(url);nativeRevokeUrl(url);};window.outerEscapes=0;const submissionModal=document.querySelector('#adminSubmissionModal');function closeSubmissionModal(){outerEscapes++;submissionModal.classList.remove('is-open');submissionModal.setAttribute('aria-hidden','true');}${outerEscapeScript}
window.mountReview=(html,target='reviewHost')=>{const host=document.getElementById(target);host.innerHTML=html;host.querySelectorAll('script').forEach(oldScript=>{const newScript=document.createElement('script');Array.from(oldScript.attributes).forEach(attribute=>newScript.setAttribute(attribute.name,attribute.value));newScript.textContent=oldScript.textContent;oldScript.replaceWith(newScript);});};window.nextFragment=${scriptJson(fragment('available'))};mountReview(${scriptJson(fragment(state, outcome))});
</script><script>${itemScript}</script></body></html>`);
            return;
        }
        if (url.pathname === '/VNZ-TECH/backend/submission_face_photo.php') {
            assert.equal(url.searchParams.get('submission_id'), '1');
            const outcome = url.searchParams.get('fixture') || 'ready'; imageRequests.push(outcome);
            res.setHeader('Cache-Control', 'no-store');
            if (outcome === 'slow') await delay(650);
            if (outcome === 'error') { res.writeHead(410); res.end('Unavailable'); return; }
            res.setHeader('Content-Type', 'image/jpeg'); res.end(image.stdout); return;
        }
        if (url.pathname === '/fixture-item.jpg') { res.setHeader('Content-Type', 'image/jpeg'); res.end(image.stdout); return; }
        const rel = url.pathname.replace(/^\/VNZ-TECH\//, '/').slice(1);
        if (!/^assets\/(css|js)\/[a-z0-9_-]+\.(css|js)$/.test(rel)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', rel.endsWith('.css') ? 'text/css' : 'text/javascript');
        res.end(await readFile(join(root, rel)));
    } catch (error) { serverErrors.push(String(error)); res.writeHead(500); res.end('Fixture error'); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { const s = v / 255; return s <= .04045 ? s / 12.92 : ((s + .055) / 1.055) ** 2.4; }).reduce((sum, value, i) => sum + value * [.2126, .7152, .0722][i], 0);
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + .05) / (Math.min(luminance(a), luminance(b)) + .05);
const sel = name => `document.querySelector('#reviewHost [${name === 'viewer' ? 'data-face-viewer' : 'data-face-view-' + name}]')`;
let client, checks = 0;
const pass = text => { console.log('PASS ' + text); checks++; };
async function wait(expression) {
    for (let i = 0; i < 100; i++) { if (await client.evaluate(expression)) return; await delay(40); }
    throw new Error('Browser condition timed out: ' + expression);
}
async function load(query = '', width = 1200, height = 900) {
    await client.call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width === 375 });
    await client.navigate(`${base}/?${query}`);
}
async function open(ready = true) {
    await wait("document.querySelector('#reviewHost [data-submission-face]')?.dataset.selfieViewerBound==='1'");
    await client.evaluate(`${sel('open')}.focus({preventScroll:true});${sel('open')}.click()`);
    await wait(`${sel('viewer')}.open`);
    if (ready) await wait(`${sel('viewer')}.dataset.state==='ready'`);
}
async function assertClosed() {
    await wait(`!${sel('viewer')}.open && !${sel('image')}.getAttribute('src') && document.body.style.overflow==='clip' && viewerUrls.size===0`);
    const state = await client.evaluate(`({src:${sel('image')}.getAttribute('src'),overflow:document.body.style.overflow,focus:document.activeElement===${sel('open')},outer:document.querySelector('#adminSubmissionModal').classList.contains('is-open'),escapes:outerEscapes,urls:viewerUrls.size})`);
    assert.ok(!state.src && state.overflow === 'clip' && state.focus && state.outer && state.escapes === 0 && state.urls === 0, JSON.stringify(state));
}
async function clickOutsideDialog() {
    const point = await client.evaluate(`(()=>{const r=${sel('viewer')}.getBoundingClientRect();return{x:r.left>4?r.left-3:innerWidth-2,y:2};})()`);
    await client.call('Input.dispatchMouseEvent', { type: 'mousePressed', ...point, button: 'left', clickCount: 1 });
    await client.call('Input.dispatchMouseEvent', { type: 'mouseReleased', ...point, button: 'left', clickCount: 1 });
}
async function panState() {
    return client.evaluate(`(()=>{const d=${sel('viewer')},s=${sel('stage')},i=${sel('image-button')},r=s.getBoundingClientRect();return{x:s.scrollLeft,y:s.scrollTop,maxX:s.scrollWidth-s.clientWidth,maxY:s.scrollHeight-s.clientHeight,zoom:d.classList.contains('is-zoomed'),dragging:d.classList.contains('is-dragging'),cursor:getComputedStyle(i).cursor,overflowX:getComputedStyle(s).overflowX,overflowY:getComputedStyle(s).overflowY,touchStage:getComputedStyle(s).touchAction,touchImage:getComputedStyle(i).touchAction,userSelect:getComputedStyle(i).userSelect,center:{x:r.left+r.width/2,y:r.top+r.height/2},capture:window.lastPointerId!==undefined&&i.hasPointerCapture(lastPointerId)};})()`);
}
async function zoom() {
    await client.evaluate(`${sel('zoom')}.click()`);
    await wait(`${sel('viewer')}.classList.contains('is-zoomed')`);
    await client.evaluate('new Promise(requestAnimationFrame)');
    return panState();
}
async function mouse(type, point, pressed = false) {
    await client.call('Input.dispatchMouseEvent', { type, ...point, button: type === 'mouseMoved' && !pressed ? 'none' : 'left', buttons: pressed ? 1 : 0, clickCount: type === 'mouseMoved' ? 0 : 1, pointerType: 'mouse' });
}
async function mouseStart(dx, dy) {
    const { center } = await panState();
    await client.evaluate(`${sel('image-button')}.addEventListener('pointerdown',e=>window.lastPointerId=e.pointerId,{once:true})`);
    await mouse('mouseMoved', center);
    await mouse('mousePressed', center, true);
    const end = { x: center.x + dx, y: center.y + dy };
    await mouse('mouseMoved', end, true);
    return end;
}
async function mouseDrag(dx, dy) {
    const end = await mouseStart(dx, dy);
    const during = await panState();
    await mouse('mouseReleased', end);
    return { during, after: await panState() };
}
async function key(key, code = key, modifiers = 0) {
    const codes = { ArrowLeft: 37, ArrowUp: 38, ArrowRight: 39, ArrowDown: 40, Enter: 13 };
    await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key, code, windowsVirtualKeyCode: codes[key], modifiers, ...(key === 'Enter' ? { text: '\r', unmodifiedText: '\r' } : {}) });
    await client.call('Input.dispatchKeyEvent', { type: 'keyUp', key, code, windowsVirtualKeyCode: codes[key], modifiers });
}
const near = (actual, expected, label) => assert.ok(Math.abs(actual - expected) <= 2, `${label}: expected ${expected}, got ${actual}`);
try {
    client = await browser();
    for (const theme of ['light', 'dark']) for (const width of [1200, 375]) {
        const requestStart = imageRequests.length;
        await load(`theme=${theme}`, width);
        await wait(`Boolean(${sel('open')})`);
        const initial = await client.evaluate(`(()=>{const p=document.querySelector('#reviewHost .submission-face'),s=getComputedStyle(p),r=p.getBoundingClientRect();return{surface:s.backgroundColor,text:getComputedStyle(p.querySelector('h3')).color,muted:getComputedStyle(p.querySelector('.submission-face-note')).color,badge:getComputedStyle(p.querySelector('.submission-face-badge')).color,fit:r.left>=0&&r.right<=innerWidth+1&&document.documentElement.scrollWidth<=innerWidth+1,src:${sel('image')}.getAttribute('src'),open:${sel('viewer')}.open,label:${sel('open')}.textContent,caption:p.textContent};})()`);
        assert.ok(initial.fit && !initial.src && !initial.open && initial.label.includes('View submission selfie'), JSON.stringify(initial));
        assert.equal(imageRequests.length, requestStart, 'Private photo is not requested before opening');
        assert.ok(contrast(initial.surface, initial.text) >= 7 && contrast(initial.surface, initial.muted) >= 4.5 && contrast(initial.surface, initial.badge) >= 4.5, JSON.stringify(initial));
        assert.ok(theme === 'light' ? luminance(initial.surface) > .8 : luminance(initial.surface) < .05, 'Active admin theme wins');
        assert.ok(initial.caption.includes('does not confirm identity'));
        await open();
        const opened = await client.evaluate(`(()=>{const d=${sel('viewer')},s=getComputedStyle(d),r=d.getBoundingClientRect(),img=${sel('image')},ir=img.getBoundingClientRect(),b=${sel('zoom')},bs=getComputedStyle(b);return{surface:s.backgroundColor,text:s.color,muted:getComputedStyle(${sel('hint')}).color,button:bs.backgroundColor,buttonText:bs.color,fit:r.left>=0&&r.right<=innerWidth+1&&r.top>=0&&r.bottom<=innerHeight+1&&ir.width<=r.width&&ir.height<=r.height,focus:d.contains(document.activeElement),loaded:img.complete&&img.naturalWidth===800,width:ir.width,ratio:ir.width/ir.height,objectFit:getComputedStyle(img).objectFit,topLayer:d.matches(':modal'),overflow:document.body.style.overflow,backdrop:getComputedStyle(d,'::backdrop').backgroundColor,controlHeight:b.getBoundingClientRect().height};})()`);
        assert.ok(opened.fit && opened.focus && opened.loaded && opened.topLayer && opened.overflow === 'hidden', JSON.stringify(opened));
        assert.ok(Math.abs(opened.ratio - 800 / 600) < .03, 'Natural image proportions');
        assert.equal(opened.objectFit, 'contain');
        assert.ok(contrast(opened.surface, opened.text) >= 7 && contrast(opened.surface, opened.muted) >= 4.5 && contrast(opened.button, opened.buttonText) >= 4.5, JSON.stringify(opened));
        assert.ok(theme === 'light' ? luminance(opened.surface) > .8 : luminance(opened.surface) < .05);
        assert.ok(opened.backdrop !== 'rgba(0, 0, 0, 0)' && opened.controlHeight >= 40, JSON.stringify(opened));
        await client.evaluate(`${sel('image-button')}.click()`);
        const zoomed = await client.evaluate(`(()=>{const img=${sel('image')},stage=${sel('stage')};return{zoom:${sel('viewer')}.classList.contains('is-zoomed'),pressed:${sel('zoom')}.getAttribute('aria-pressed'),width:img.getBoundingClientRect().width,stage:stage.clientWidth};})()`);
        assert.ok(zoomed.zoom && zoomed.pressed === 'true' && zoomed.width >= opened.width * 1.95, JSON.stringify({zoomed,opened}));
        await client.evaluate(`${sel('image-button')}.click()`);
        assert.equal(await client.evaluate(`${sel('zoom')}.getAttribute('aria-pressed')`), 'false');
        await client.evaluate(`${sel('zoom')}.focus();${sel('zoom')}.click()`);
        assert.equal(await client.evaluate(`${sel('zoom')}.getAttribute('aria-pressed')`), 'true');
        await client.evaluate(`${sel('zoom')}.click()`);
        if (screenshots) {
            const shot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshots, `admin-${theme}-${width}.png`), Buffer.from(shot.data, 'base64'));
        }
        await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        await client.call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        await assertClosed();
        pass(`selfie viewer ${theme} ${width}px: lazy request, contrast, top layer, proportions, zoom/reset, nested Escape/focus/scroll`);
    }
    // Trusted mouse events exercise Pointer Events, capture, click synthesis and both bounds.
    for (const theme of ['light', 'dark']) {
        await load('theme=' + theme); await open();
        const initialPan = await zoom();
        assert.ok(initialPan.maxX > 100 && initialPan.maxY > 100, JSON.stringify(initialPan));
        near(initialPan.x, initialPan.maxX / 2, 'Zoom initially centers horizontal overflow');
        near(initialPan.y, initialPan.maxY / 2, 'Zoom initially centers vertical overflow');
        assert.ok(initialPan.overflowX === 'hidden' && initialPan.overflowY === 'hidden' && initialPan.cursor === 'grab', JSON.stringify(initialPan));
        assert.equal(initialPan.touchStage, 'none'); assert.equal(initialPan.touchImage, 'none');
        const { during, after } = await mouseDrag(-70, -55);
        assert.ok(during.dragging && during.capture && during.cursor === 'grabbing', JSON.stringify(during));
        assert.ok(after.zoom && !after.dragging && !after.capture, JSON.stringify(after));
        near(after.x, initialPan.x + 70, 'Mouse drag moves horizontal photo offset');
        near(after.y, initialPan.y + 55, 'Mouse drag moves vertical photo offset');
        const min = (await mouseDrag(5000, 5000)).after;
        near(min.x, 0, 'Mouse drag clamps left edge'); near(min.y, 0, 'Mouse drag clamps top edge');
        const max = (await mouseDrag(-5000, -5000)).after;
        near(max.x, max.maxX, 'Mouse drag clamps right edge'); near(max.y, max.maxY, 'Mouse drag clamps bottom edge');
        assert.ok(max.zoom && !max.dragging && !max.capture);
        if (screenshots) {
            const shot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshots, `admin-pan-${theme}-1200.png`), Buffer.from(shot.data, 'base64'));
        }
        const point = max.center;
        await mouse('mouseMoved', point); await mouse('mousePressed', point, true); await mouse('mouseReleased', point);
        const reset = await panState();
        assert.ok(!reset.zoom && !reset.dragging && reset.x === 0 && reset.y === 0, JSON.stringify(reset));
        await client.evaluate(`${sel('close')}.click()`); await assertClosed();
        pass(`mouse drag ${theme}: capture, grab/grabbing, two axes, exact bounds, drag retains zoom, simple click resets`);
    }
    await load(); await open(); await zoom();
    await client.evaluate(`${sel('image-button')}.focus({preventScroll:true})`);
    const keyboardStart = await panState();
    await key('ArrowRight'); await key('ArrowDown');
    let keyboardPan = await panState();
    near(keyboardPan.x, Math.min(keyboardStart.maxX, keyboardStart.x + 48), 'ArrowRight pans 48px');
    near(keyboardPan.y, Math.min(keyboardStart.maxY, keyboardStart.y + 48), 'ArrowDown pans 48px');
    await key('ArrowLeft', 'ArrowLeft', 8); await key('ArrowUp', 'ArrowUp', 8);
    const shifted = await panState();
    near(shifted.x, Math.max(0, keyboardPan.x - 144), 'Shift ArrowLeft pans 144px');
    near(shifted.y, Math.max(0, keyboardPan.y - 144), 'Shift ArrowUp pans 144px');
    for (let n = 0; n < 12; n++) { await key('ArrowLeft', 'ArrowLeft', 8); await key('ArrowUp', 'ArrowUp', 8); }
    keyboardPan = await panState(); near(keyboardPan.x, 0, 'Keyboard left clamp'); near(keyboardPan.y, 0, 'Keyboard top clamp');
    await key('Enter');
    assert.equal((await panState()).zoom, false, 'Enter keeps native photo-button reset behavior');
    await zoom();
    const nativeDrag = await client.evaluate(`(()=>{const e=new Event('dragstart',{cancelable:true,bubbles:true});${sel('image')}.dispatchEvent(e);return{prevented:e.defaultPrevented,draggable:${sel('image')}.draggable,select:getComputedStyle(${sel('image-button')}).userSelect};})()`);
    assert.ok(nativeDrag.prevented && !nativeDrag.draggable && nativeDrag.select === 'none', JSON.stringify(nativeDrag));
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('bounded keyboard 48/144px pan and Enter reset; native image dragging/selection prevented');
    // Real touch sequences use a shorter emulated phone viewport so both image axes overflow.
    for (const theme of ['light']) {
        assert.deepEqual(client.errors, []);
        await client.close(); client = await browser();
        await client.call('Emulation.setTouchEmulationEnabled', { enabled: true, maxTouchPoints: 2 });
        await load('theme=' + theme, 375, 640); await open();
        const touchStart = await zoom();
        assert.ok(touchStart.maxX > 40 && touchStart.maxY > 40, JSON.stringify(touchStart));
        const point = { ...touchStart.center, id: 1, radiusX: 2, radiusY: 2, force: 1 };
        await client.evaluate(`${sel('image-button')}.addEventListener('pointerdown',e=>window.lastPointerId=e.pointerId,{once:true})`);
        await client.call('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [point] });
        await client.call('Input.dispatchTouchEvent', { type: 'touchMove', touchPoints: [{ ...point, x: point.x - 50, y: point.y - 40 }] });
        await wait(`${sel('viewer')}.classList.contains('is-dragging')`);
        const touching = await panState();
        assert.ok(touching.dragging && touching.capture, JSON.stringify(touching));
        await client.call('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await wait(`!${sel('viewer')}.classList.contains('is-dragging') && !${sel('image-button')}.hasPointerCapture(lastPointerId)`);
        const touched = await panState();
        assert.ok(touched.zoom && !touched.dragging && !touched.capture, JSON.stringify(touched));
        near(touched.x, Math.min(touched.maxX, touchStart.x + 50), 'Touch horizontal pan');
        near(touched.y, Math.min(touched.maxY, touchStart.y + 40), 'Touch vertical pan');
        if (screenshots) {
            const shot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshots, `admin-pan-${theme}-375.png`), Buffer.from(shot.data, 'base64'));
        }
        await client.evaluate(`${sel('image-button')}.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,detail:1}))`);
        assert.ok((await panState()).zoom, 'Generated click after a drag is suppressed');
        const tap = { ...point, x: touched.center.x, y: touched.center.y };
        await client.evaluate(`window.tapEnded=false;window.tapPointer=null;${sel('image-button')}.addEventListener('pointerdown',e=>window.tapPointer=e.pointerId,{once:true});document.addEventListener('pointerup',e=>{if(e.pointerId===window.tapPointer)window.tapEnded=true})`);
        await client.call('Input.dispatchTouchEvent', { type: 'touchStart', touchPoints: [tap] });
        await client.call('Input.dispatchTouchEvent', { type: 'touchEnd', touchPoints: [] });
        await wait('window.tapEnded');
        // This Windows headless Chrome emits touch Pointer Events but no compatibility
        // click even on an isolated native button. Exercise that separate click path
        // explicitly; this is not evidence of a physical device's synthesized tap.
        await client.evaluate(`${sel('image-button')}.dispatchEvent(new MouseEvent('click',{bubbles:true,cancelable:true,detail:1}))`);
        await wait(`!${sel('viewer')}.classList.contains('is-zoomed')`);
        await client.evaluate(`${sel('close')}.click()`); await assertClosed();
        pass(`emulated touch ${theme} 375x640: primary captured two-axis drag; explicit compatibility clicks verify drag suppression and tap reset`);
    }
    assert.deepEqual(client.errors, []);
    await client.close(); client = await browser();
    await load(); await open(); await zoom();
    let endpoint = await mouseStart(-35, -30);
    await client.evaluate(`${sel('image-button')}.dispatchEvent(new PointerEvent('pointercancel',{pointerId:lastPointerId,pointerType:'mouse',isPrimary:true,bubbles:true}))`);
    await wait(`!${sel('viewer')}.classList.contains('is-dragging')`);
    assert.equal((await panState()).capture, false, 'Pointer cancel releases capture');
    await mouse('mouseReleased', endpoint);
    assert.ok((await panState()).zoom, 'Pointer cancel cannot turn the drag into a reset click');
    endpoint = await mouseStart(-35, -30);
    await client.evaluate(`${sel('image-button')}.releasePointerCapture(lastPointerId)`);
    await mouse('mouseMoved', endpoint, true);
    await wait(`!${sel('viewer')}.classList.contains('is-dragging')`);
    assert.equal((await panState()).capture, false, 'Lost pointer capture clears drag');
    await mouse('mouseReleased', endpoint);
    assert.ok((await panState()).zoom, 'Lost capture cannot turn the drag into a reset click');
    const ignoredStart = await panState();
    await client.evaluate(`${sel('image-button')}.dispatchEvent(new PointerEvent('pointerdown',{pointerId:999,pointerType:'touch',isPrimary:false,clientX:100,clientY:100,bubbles:true}));${sel('image-button')}.dispatchEvent(new PointerEvent('pointermove',{pointerId:999,pointerType:'touch',isPrimary:false,clientX:300,clientY:300,bubbles:true}));${sel('image-button')}.dispatchEvent(new PointerEvent('pointerdown',{pointerId:998,pointerType:'mouse',isPrimary:true,button:2,clientX:100,clientY:100,bubbles:true}))`);
    const ignoredEnd = await panState();
    near(ignoredEnd.x, ignoredStart.x, 'Secondary touch/right mouse cannot pan horizontally');
    near(ignoredEnd.y, ignoredStart.y, 'Secondary touch/right mouse cannot pan vertically');
    assert.ok(!ignoredEnd.dragging && !ignoredEnd.capture);
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('pointer cancel/lost capture release drag; secondary touch and right mouse ignored');
    await load(); await open(); await zoom();
    endpoint = await mouseStart(2, 2);
    assert.equal((await panState()).dragging, false, 'Tiny movement stays below drag threshold');
    await mouse('mouseReleased', endpoint);
    assert.equal((await panState()).zoom, false, 'Tiny mouse movement remains a simple click');
    await zoom(); endpoint = await mouseStart(-40, -30);
    await client.evaluate(`${sel('close')}.click()`); await mouse('mouseReleased', endpoint); await assertClosed();
    await open();
    let fit = await panState(); assert.ok(!fit.zoom && !fit.dragging && fit.x === 0 && fit.y === 0, JSON.stringify(fit));
    await zoom(); endpoint = await mouseStart(-40, -30);
    await client.call('Emulation.setDeviceMetricsOverride', { width: 1100, height: 820, deviceScaleFactor: 1, mobile: false });
    await wait(`!${sel('viewer')}.classList.contains('is-zoomed') && !${sel('viewer')}.classList.contains('is-dragging')`);
    await client.call('Emulation.setDeviceMetricsOverride', { width: 1000, height: 760, deviceScaleFactor: 1, mobile: false });
    await client.evaluate('new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))');
    await mouse('mouseReleased', endpoint);
    fit = await panState(); assert.ok(!fit.zoom && fit.x === 0 && fit.y === 0 && !fit.capture, JSON.stringify(fit));
    await mouse('mouseMoved', fit.center); await mouse('mousePressed', fit.center, true); await mouse('mouseReleased', fit.center);
    assert.ok((await panState()).zoom, 'First normal fit click after interrupted drag still zooms');
    await client.evaluate(`${sel('zoom')}.click()`);
    await zoom(); endpoint = await mouseStart(-40, -30);
    await key('Enter');
    assert.equal((await panState()).zoom, false, 'Keyboard resets zoom during a captured drag');
    await mouse('mouseReleased', endpoint);
    assert.equal((await panState()).zoom, false, 'Late pointer release cannot re-zoom after keyboard reset');
    fit = await panState();
    await mouse('mouseMoved', fit.center); await mouse('mousePressed', fit.center, true); await mouse('mouseReleased', fit.center);
    assert.ok((await panState()).zoom, 'Next normal click after keyboard interruption still zooms');
    await client.evaluate(`${sel('zoom')}.click()`);
    await zoom(); endpoint = await mouseStart(-40, -30);
    await client.evaluate(`window.dragDetached=${sel('viewer')};window.dragImage=${sel('image-button')};document.querySelector('#reviewHost').replaceChildren()`);
    await wait("!dragDetached.open && !dragDetached.classList.contains('is-dragging') && !dragImage.hasPointerCapture(lastPointerId) && viewerUrls.size===0 && document.body.style.overflow==='clip'");
    await mouse('mouseReleased', endpoint);
    await client.evaluate('mountReview(nextFragment)'); await open();
    fit = await panState(); assert.ok(!fit.zoom && !fit.dragging && fit.x === 0 && fit.y === 0, JSON.stringify(fit));
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('drag threshold, close/reopen, repeated resize, keyboard reset and AJAX removal mid-drag clean capture/offsets and suppress late reset clicks');
    await load(); await open();
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    await open(); await clickOutsideDialog(); await assertClosed();
    await open(); await client.evaluate(`${sel('viewer')}.close()`); await assertClosed();
    await open(); await client.evaluate(`${sel('viewer')}.dispatchEvent(new Event('cancel',{cancelable:true,bubbles:true}))`); await assertClosed();
    pass('close button, backdrop, native close/cancel, repeated opening, private src/blob cleared');
    await load();
    await client.evaluate("document.querySelector('#reviewHost').scrollTop=64");
    assert.equal(await client.evaluate("document.querySelector('#reviewHost').scrollTop"), 64);
    await open();
    await client.evaluate(`${sel('zoom')}.focus()`);
    await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13, text: '\r', unmodifiedText: '\r' });
    await client.call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Enter', code: 'Enter', windowsVirtualKeyCode: 13 });
    assert.equal(await client.evaluate(`${sel('zoom')}.getAttribute('aria-pressed')`), 'true');
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    assert.equal(await client.evaluate("document.querySelector('#reviewHost').scrollTop"), 64);
    pass('native keyboard zoom control and prior nested review scroll position preserved');
    await load('outcome=slow'); await open(false);
    assert.equal(await client.evaluate(`${sel('viewer')}.dataset.state`), 'loading');
    assert.ok(await client.evaluate(`${sel('status')}.textContent.length>0`));
    await wait(`${sel('viewer')}.dataset.state==='ready'`);
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('delayed protected-photo loading state resolves to ready');
    await load('outcome=error'); await open(false);
    await wait(`${sel('viewer')}.dataset.state==='error'`);
    const failed = await client.evaluate(`({text:${sel('status')}.textContent,hidden:${sel('image-button')}.hidden,disabled:${sel('zoom')}.disabled})`);
    assert.ok(failed.text.length > 10 && failed.hidden && failed.disabled, JSON.stringify(failed));
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('expired/unavailable photo response produces an accessible non-zoomable error');
    await load('outcome=slow'); await open(false);
    await client.evaluate(`window.detachedViewer=${sel('viewer')};window.detachedImage=${sel('image')};document.querySelector('#reviewHost').replaceChildren();`);
    await wait("!detachedViewer.open && !detachedImage.getAttribute('src') && document.body.style.overflow==='clip'");
    await delay(700);
    assert.equal(await client.evaluate('detachedViewer.open'), false);
    await client.evaluate('mountReview(nextFragment)'); await open();
    await client.evaluate(`window.detachedViewer=${sel('viewer')};window.detachedImage=${sel('image')};document.querySelector('#reviewHost').replaceChildren();`);
    await wait("!detachedViewer.open && !detachedImage.getAttribute('src') && document.body.style.overflow==='clip' && viewerUrls.size===0");
    await client.evaluate('mountReview(nextFragment)'); await open();
    await client.evaluate(`${sel('close')}.click()`); await assertClosed();
    pass('AJAX removal during loading and ready cancels viewer, clears source/blob/unlocks, fresh fragment binds');
    for (const outcome of ['ready', 'slow']) {
        await load('outcome=' + outcome);
        await client.evaluate("mountReview(nextFragment,'hiddenReview')");
        await open(outcome === 'ready');
        assert.equal(await client.evaluate("document.querySelector('#hiddenReview [data-face-viewer]').open"), false);
        await client.evaluate("window.hiddenImage=document.querySelector('#hiddenReview [data-face-view-image]');window.activeViewer=document.querySelector('#reviewHost [data-face-viewer]');document.querySelector('#adminSubmissionModal').classList.remove('is-open');document.querySelector('#adminSubmissionModal').setAttribute('aria-hidden','true')");
        await wait("!activeViewer.open && !activeViewer.querySelector('img').getAttribute('src') && document.body.style.overflow==='clip' && viewerUrls.size===0");
        if (outcome === 'slow') await delay(700);
        assert.equal(await client.evaluate("hiddenImage.getAttribute('src')"), null);
        assert.equal(await client.evaluate('activeViewer.open'), false);
    }
    pass('ancestor review close during loading/ready cleans active viewer without opening hidden fragment');
    for (const state of ['missing', 'expired', 'unavailable']) {
        const requestStart = imageRequests.length;
        await load(`theme=light&state=${state}`);
        assert.equal(await client.evaluate("document.querySelector('#reviewHost .submission-face').querySelectorAll('img,button,dialog').length"), 0);
        assert.ok(await client.evaluate("document.querySelector('#reviewHost .submission-face-note').textContent.length>20"));
        assert.equal(await client.evaluate("document.querySelector('#reviewHost .submission-face').innerHTML.includes('submission_face_photo.php')"), false);
        assert.equal(imageRequests.length, requestStart);
        pass(`admin ${state} state excludes actionable private photo URL`);
    }
    await load();
    await client.evaluate("document.querySelector('#reviewHost .photo-card').click()");
    await wait("document.querySelector('#submissionPhotoLightbox').classList.contains('is-open') && document.querySelector('#submissionPhotoLightboxImg').naturalWidth===800");
    assert.equal(await client.evaluate("document.querySelector('#submissionPhotoLightboxCaption').textContent"), 'Synthetic item photo');
    assert.equal(await client.evaluate(`${sel('viewer')}.open`), false);
    await client.evaluate("document.querySelector('#submissionPhotoLightboxClose').click()");
    assert.equal(await client.evaluate("document.querySelector('#submissionPhotoLightbox').classList.contains('is-open')"), false);
    pass('unchanged actual item-photo lightbox still opens and closes independently');
    assert.deepEqual(client.errors, []);
    assert.deepEqual(serverErrors, []);
    console.log(`${checks} admin selfie-viewer browser checks passed; ${imageRequests.length} synthetic private-photo requests`);
} finally {
    await client?.close();
    await new Promise(resolve => server.close(resolve));
    const tempRoot = resolve(tmpdir()) + sep;
    if (!resolve(fixture).startsWith(tempRoot) || !fixture.includes('vnz-face-admin-ui-')) throw new Error('Refusing unsafe fixture cleanup');
    await rm(fixture, { recursive: true, force: true });
}
