// Actual shared PHP/CSS/JS; all images, camera tracks and token responses are synthetic.
import { createServer } from 'node:http';
import { readFile, writeFile, mkdtemp, rm, mkdir } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const fixture = await mkdtemp(join(tmpdir(), 'vnz-submission-face-ui-'));
const screenshotDir = process.env.VNZ_FACE_SCREENSHOTS;
if (screenshotDir) {
    assert.ok(resolve(screenshotDir).startsWith(root + sep + '.qa-face-'));
    await mkdir(screenshotDir, { recursive: true });
}
const quote = value => "'" + value.replaceAll('\\', '/').replaceAll("'", "\\'") + "'";
const templates = {};
for (const context of ['guest', 'account']) for (const lang of ['en', 'fil']) {
    const result = spawnSync(process.env.PHP_BINARY || 'C:/xampp/php/php.exe', ['-r', `session_save_path(${quote(fixture)}); function vnzSubmissionFaceToken($context){return str_repeat('a',64);} require ${quote(join(root, 'includes/submission-face-capture.php'))}; vnzRenderSubmissionFaceCapture('${context}','${lang}');`], { encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, result.stderr);
    assert.ok(!/Warning:|Fatal error:/.test(result.stdout));
    templates[context + lang] = result.stdout;
}
for (const sourceFile of ['sell.php', 'account/dashboard.php']) {
    const source = await readFile(join(root, sourceFile), 'utf8');
    assert.ok(source.includes('if (vnzSubmissionFaceMode())'), 'Both actual forms resolve the active mode');
    assert.ok(source.includes('faceCaptureReady'), 'Existing disable handler waits for the camera step');
}
let tokenRequests = 0;
const receivedPayloads = [];
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            const context = url.searchParams.get('context') || 'guest';
            const theme = url.searchParams.get('theme') || 'light';
            const lang = url.searchParams.get('lang') || 'en';
            const area = context === 'guest' ? 'public' : 'seller';
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(`<!doctype html><html data-${area}-theme="${theme}" data-${area === 'public' ? 'seller' : 'public'}-theme="${theme === 'light' ? 'dark' : 'light'}"><head><meta name="viewport" content="width=device-width,initial-scale=1"><link rel="stylesheet" href="/VNZ-TECH/assets/css/${context === 'guest' ? 'sell' : 'seller_dashboard'}.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/${area}_theme.css"><link rel="stylesheet" href="/VNZ-TECH/assets/css/submission_face_capture.css"></head><body><main style="padding:20px"><form id="sellerSubmissionForm" action="/VNZ-TECH/backend/submit_submission.php" method="POST" enctype="multipart/form-data"><label>Item<input name="item_title" value="Keep this draft" required></label>${templates[context + lang]}<button type="submit" id="submitBtn">Submit item</button></form></main><script>
window.testState={denied:false,late:false,streams:[],payloads:[],customInvalid:false,postOutcome:'success',holdPost:false,tokenUnavailable:false};
const nativeFetch=window.fetch.bind(window);window.fetch=async(url,options)=>{if(String(url).endsWith('/identity_api.php')&&testState.tokenUnavailable)return new Response(JSON.stringify({success:false}),{status:503,headers:{'Content-Type':'application/json'}});if(!String(url).endsWith('/backend/submit_submission.php'))return nativeFetch(url,options);const data=options.body;const payload={title:data.get('item_title'),nonce:data.get('face_nonce'),consent:data.get('face_consent'),files:data.getAll('submission_face').map(f=>({type:f.type,size:f.size})),keys:[...data.keys()]};testState.payloads.push(payload);await nativeFetch('/test-report',{method:'POST',body:JSON.stringify(payload)});if(testState.holdPost)await new Promise(resolve=>testState.resolvePost=resolve);const response=new Response('',{status:200});Object.defineProperty(response,'url',{value:location.origin+(testState.postOutcome==='uncertain'?'/VNZ-TECH/account/login.php':testState.postOutcome==='retry'?'/VNZ-TECH/sell.php?error=Please%20take%20a%20clear%20selfie%20with%20one%20face.':'/VNZ-TECH/sell.php?submitted=1&ref=SYNTHETIC-RECEIPT')});return response;};
const originalCreate=URL.createObjectURL.bind(URL),originalRevoke=URL.revokeObjectURL.bind(URL);window.testState.urls=new Set();URL.createObjectURL=blob=>{const value=originalCreate(blob);testState.urls.add(value);return value;};URL.revokeObjectURL=value=>{testState.urls.delete(value);originalRevoke(value);};
Object.defineProperty(navigator,'mediaDevices',{configurable:true,value:{getUserMedia:async()=>{if(testState.denied)throw new DOMException('Denied','NotAllowedError');const canvas=document.createElement('canvas');canvas.width=640;canvas.height=480;const ctx=canvas.getContext('2d');const draw=()=>{ctx.fillStyle='#335a73';ctx.fillRect(0,0,640,480);ctx.fillStyle='#dcecf3';ctx.fillRect(190,90,260,300);};draw();const timer=setInterval(draw,80);const stream=canvas.captureStream(15);testState.streams.push(stream);const stop=stream.getTracks()[0].stop.bind(stream.getTracks()[0]);stream.getTracks()[0].stop=()=>{clearInterval(timer);stop();};if(testState.late)await new Promise(resolve=>testState.resolveCamera=resolve);return stream;}}});
const form=document.querySelector('form');form.addEventListener('submit',event=>{if(testState.customInvalid){event.preventDefault();return;}if(form.dataset.faceCaptureRequired==='1'&&form.dataset.faceCaptureReady!=='1')return;document.querySelector('#submitBtn').disabled=true;});
</script><script src="/VNZ-TECH/assets/js/submission-face-capture.js"></script><script>document.addEventListener('submit',event=>{if(event.defaultPrevented)return;const data=new FormData(event.target);testState.payloads.push({title:data.get('item_title'),nonce:data.get('face_nonce'),consent:data.get('face_consent'),files:data.getAll('submission_face').map(f=>({type:f.type,size:f.size})),keys:[...data.keys()]});event.preventDefault();});</script></body></html>`);
            return;
        }
        if (url.pathname === '/test-report') {
            let body = ''; for await (const chunk of req) body += chunk;
            receivedPayloads.push(JSON.parse(body)); res.end('ok'); return;
        }
        if (url.pathname === '/VNZ-TECH/sell.php' && url.searchParams.get('submitted') === '1') {
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end('<!doctype html><html><body><p id="receipt">Synthetic submission received</p></body></html>'); return;
        }
        if (url.pathname.endsWith('/identity_api.php')) {
            let body = ''; for await (const chunk of req) body += chunk;
            const params = new URLSearchParams(body);
            assert.equal(params.get('action'), 'face_token');
            assert.ok(params.get('csrf_token'));
            assert.ok(['guest', 'account'].includes(params.get('context')));
            tokenRequests++;
            res.setHeader('Content-Type', 'application/json');
            res.setHeader('Cache-Control', 'no-store');
            res.end(JSON.stringify({ success: true, mode: 'face_submission_v6', face_nonce: 'synthetic-fresh-nonce', csrf_token: params.get('csrf_token'), expires_in: 600 }));
            return;
        }
        const rel = url.pathname.replace(/^\/VNZ-TECH\//, '');
        if (!/^assets\/(css|js)\/[a-z0-9_-]+\.(css|js)$/.test(rel)) { res.writeHead(404); res.end(); return; }
        res.setHeader('Content-Type', rel.endsWith('.css') ? 'text/css' : 'text/javascript');
        res.end(await readFile(join(root, rel)));
    } catch (error) { res.writeHead(500); res.end(String(error)); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
const base = `http://127.0.0.1:${server.address().port}`;
let client;
let checks = 0;
async function wait(expression) {
    for (let i = 0; i < 100; i++) { if (await client.evaluate(expression)) return; await delay(50); }
    throw new Error('Browser condition timed out: ' + expression);
}
async function load(context = 'guest', theme = 'light', lang = 'en', width = 1200, height = 900) {
    await client.call('Emulation.setDeviceMetricsOverride', { width, height, deviceScaleFactor: 1, mobile: width < 500 });
    await client.navigate(`${base}/?context=${context}&theme=${theme}&lang=${lang}`);
    await wait("document.querySelector('form').dataset.faceCaptureRequired==='1'");
}
async function open() { await client.evaluate("document.querySelector('#submitBtn').click()"); await wait("document.querySelector('dialog').open"); }
async function camera() {
    await client.evaluate("document.querySelector('[data-face-camera]').click()");
    await wait("!document.querySelector('[data-face-video]').hidden && document.querySelector('[data-face-video]').videoWidth>0");
}
async function capture() {
    await client.evaluate("document.querySelector('[data-face-capture]').click()");
    await wait("!document.querySelector('[data-face-preview]').hidden");
}
const luminance = color => color.match(/[\d.]+/g).slice(0, 3).map(Number).map(v => { const s = v / 255; return s <= .04045 ? s / 12.92 : ((s + .055) / 1.055) ** 2.4; }).reduce((sum, v, i) => sum + v * [.2126, .7152, .0722][i], 0);
const contrast = (a, b) => (Math.max(luminance(a), luminance(b)) + .05) / (Math.min(luminance(a), luminance(b)) + .05);
try {
    client = await browser();
    for (const context of ['guest', 'account']) for (const theme of ['light', 'dark']) for (const width of [1200, 375]) {
        await load(context, theme, context === 'account' ? 'fil' : 'en', width);
        await open();
        const layout = await client.evaluate(`(()=>{const d=document.querySelector('dialog'),s=getComputedStyle(d),r=d.getBoundingClientRect(),p=d.querySelector('p'),c=d.querySelector('[data-face-camera]');return {surface:s.backgroundColor,text:s.color,muted:getComputedStyle(p).color,button:getComputedStyle(c).backgroundColor,buttonText:getComputedStyle(c).color,fit:r.left>=0&&r.right<=innerWidth+1&&r.top>=0&&r.bottom<=innerHeight+1,overflow:d.scrollWidth>d.clientWidth+1,focus:d.contains(document.activeElement),title:d.querySelector('h2').textContent};})()`);
        assert.ok(layout.fit && !layout.overflow && layout.focus, JSON.stringify(layout));
        assert.ok(contrast(layout.surface, layout.text) >= 7, JSON.stringify(layout));
        assert.ok(contrast(layout.surface, layout.muted) >= 4.5, JSON.stringify(layout));
        assert.ok(contrast(layout.button, layout.buttonText) >= 4.5, JSON.stringify(layout));
        assert.ok(theme === 'light' ? luminance(layout.surface) > .8 : luminance(layout.surface) < .05, 'Active area theme wins over a conflicting inactive theme');
        if (screenshotDir && width === 375) {
            const shot = await client.call('Page.captureScreenshot', { format: 'png' });
            await writeFile(join(screenshotDir, `${context}-${theme}-375.png`), Buffer.from(shot.data, 'base64'));
        }
        for (let i = 0; i < 10; i++) await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
        assert.ok(await client.evaluate("document.querySelector('dialog').contains(document.activeElement)"), 'Native dialog keeps keyboard focus inside');
        await client.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Escape', code: 'Escape', windowsVirtualKeyCode: 27 });
        await wait("!document.querySelector('dialog').open");
        assert.equal(await client.evaluate("document.querySelector('[name=item_title]').value"), 'Keep this draft');
        checks++;
    }
    await load();
    await client.evaluate("document.querySelector('[name=item_title]').value='';document.querySelector('#submitBtn').click()");
    assert.equal(await client.evaluate("document.querySelector('dialog').open"), false, 'Native invalid form never opens the capture step'); checks++;
    await client.evaluate("document.querySelector('[name=item_title]').value='Keep this draft';testState.customInvalid=true;document.querySelector('#submitBtn').click()");
    assert.equal(await client.evaluate("document.querySelector('dialog').open"), false, 'Existing custom validation keeps priority'); checks++;
    await client.evaluate('testState.customInvalid=false;testState.tokenUnavailable=true'); await open();
    await client.evaluate("document.querySelector('[data-face-camera]').click()");
    await wait("document.querySelector('[data-face-status]').dataset.tone==='error'");
    assert.equal(await client.evaluate('testState.streams.length'), 0, 'No photo is captured when the server policy is unavailable');
    assert.equal(await client.evaluate('testState.payloads.length'), 0); checks++;
    await client.evaluate('testState.tokenUnavailable=false;testState.denied=true');
    await client.evaluate("document.querySelector('[data-face-camera]').click()");
    await wait("document.querySelector('[data-face-status]').dataset.tone==='error'");
    assert.ok(await client.evaluate("document.querySelector('[data-face-status]').textContent.includes('blocked')"));
    assert.equal(await client.evaluate('testState.payloads.length'), 0); checks++;
    await client.evaluate('testState.denied=false'); await camera(); await capture();
    assert.ok(await client.evaluate("testState.streams.every(s=>s.getTracks().every(t=>t.readyState==='ended'))"), 'Capture stops all camera tracks');
    assert.equal(await client.evaluate("document.querySelector('[data-face-submit]').disabled"), true, 'Explicit consent is required');
    assert.equal(await client.evaluate("document.querySelector('[data-face-file]').files.length"), 0, 'Preview is not included in the item payload'); checks++;
    await client.evaluate("document.querySelector('[data-face-retake]').click()");
    await wait("!document.querySelector('[data-face-video]').hidden");
    assert.equal(await client.evaluate('testState.urls.size'), 0, 'Retake revokes the previous preview URL');
    await capture();
    const tokensBeforeFinal = tokenRequests;
    await client.evaluate("const realDateNow=Date.now.bind(Date);Date.now=()=>realDateNow()+10*60*1000;testState.holdPost=true;document.querySelector('[data-face-consent]').click();document.querySelector('[data-face-submit]').click();document.querySelector('[data-face-submit]').click()");
    await wait('testState.payloads.length===1');
    assert.ok(tokenRequests > tokensBeforeFinal, 'A nonce is refreshed before the final POST when capture has aged past nine minutes'); checks++;
    const payload = await client.evaluate('testState.payloads[0]');
    assert.equal(payload.title, 'Keep this draft'); assert.equal(payload.nonce, 'synthetic-fresh-nonce'); assert.equal(payload.consent, '1');
    assert.equal(payload.files.length, 1); assert.equal(payload.files[0].type, 'image/jpeg'); assert.ok(payload.files[0].size > 100);
    assert.ok(!payload.keys.some(key => /identity|id_image|score|approved|descriptor/.test(key)), 'No old identity authority or ID payload');
    await client.evaluate("document.querySelector('form').requestSubmit()");
    assert.equal(await client.evaluate('testState.payloads.length'), 1, 'Repeated submit cannot send another request'); checks++;
    await wait('Boolean(testState.resolvePost)');
    await client.evaluate('testState.resolvePost()');
    await wait("Boolean(document.querySelector('#receipt'))");
    assert.equal(new URL(await client.evaluate('location.href')).searchParams.get('ref'), 'SYNTHETIC-RECEIPT'); checks++;
    await load(); await open(); await camera(); await capture();
    await client.evaluate("testState.postOutcome='retry';document.querySelector('[data-face-consent]').click();document.querySelector('[data-face-submit]').click()");
    await wait("document.querySelector('dialog').open && document.querySelector('[data-face-status]').dataset.tone==='error'");
    assert.equal(await client.evaluate("document.querySelector('[name=item_title]').value"), 'Keep this draft', 'Server face failure keeps the exact item form in this document');
    assert.ok(await client.evaluate("document.querySelector('[data-face-status]').textContent.includes('one face')"));
    assert.equal(await client.evaluate("document.querySelector('#submitBtn').disabled"), false, 'Retake restores the original submit control');
    assert.equal(await client.evaluate("document.querySelector('[data-face-file]').files.length"), 0, 'Failed evidence is not replayed');
    await camera(); await capture();
    await client.evaluate("testState.postOutcome='success';document.querySelector('[data-face-consent]').click();document.querySelector('[data-face-submit]').click()");
    await wait("Boolean(document.querySelector('#receipt'))"); checks++;
    await load(); await open(); await camera(); await capture();
    await client.evaluate("testState.postOutcome='uncertain';document.querySelector('[data-face-consent]').click();document.querySelector('[data-face-submit]').click()");
    await wait("document.querySelector('dialog').open && document.querySelector('[data-face-status]').dataset.tone==='error'");
    assert.ok(await client.evaluate("document.querySelector('[data-face-status]').textContent.includes('tracking first')"), 'An uncertain response asks the seller to check receipt status before another attempt');
    assert.equal(await client.evaluate("document.querySelector('[name=item_title]').value"), 'Keep this draft');
    assert.equal(await client.evaluate('testState.payloads.length'), 1, 'An uncertain response is never automatically retried'); checks++;
    await load('guest', 'light', 'en', 375, 667); await open();
    assert.ok(await client.evaluate("(()=>{const d=document.querySelector('dialog'),r=d.getBoundingClientRect();return r.top>=0&&r.bottom<=innerHeight+1&&d.scrollHeight>d.clientHeight&&getComputedStyle(d).overflowY==='auto';})()"), 'Short mobile screens keep all controls accessible through the dialog scroll area'); checks++;
    await load('account', 'dark', 'fil', 375); await open(); await camera();
    await client.evaluate("document.querySelector('[data-face-cancel]').click()");
    assert.ok(await client.evaluate("testState.streams.every(s=>s.getTracks().every(t=>t.readyState==='ended'))"));
    assert.equal(await client.evaluate("document.querySelector('[name=item_title]').value"), 'Keep this draft');
    assert.equal(await client.evaluate('testState.urls.size'), 0); checks++;
    await load(); await open(); await client.evaluate("testState.late=true;document.querySelector('[data-face-camera]').click()");
    await wait('Boolean(testState.resolveCamera)');
    await client.evaluate("document.querySelector('[data-face-close]').click();testState.resolveCamera()");
    await wait("testState.streams.every(s=>s.getTracks().every(t=>t.readyState==='ended'))");
    assert.equal(await client.evaluate("document.querySelector('dialog').open"), false); checks++;
    await load(); await open(); await camera();
    await client.evaluate("Object.defineProperty(document,'hidden',{configurable:true,value:true});document.dispatchEvent(new Event('visibilitychange'))");
    assert.ok(await client.evaluate("testState.streams.every(s=>s.getTracks().every(t=>t.readyState==='ended'))")); checks++;
    await load(); await open(); await camera(); await capture();
    await client.evaluate("window.dispatchEvent(new PageTransitionEvent('pagehide'))");
    assert.equal(await client.evaluate('testState.urls.size'), 0); checks++;
    await load(); await open(); await camera();
    await client.evaluate("document.querySelector('[data-submission-face-capture]').remove()");
    await wait("!document.querySelector('dialog')");
    assert.ok(await client.evaluate("testState.streams.every(s=>s.getTracks().every(t=>t.readyState==='ended'))")); checks++;
    assert.ok(tokenRequests > 0);
    assert.deepEqual(client.errors, []);
    assert.equal(receivedPayloads.length, 4);
    console.log(JSON.stringify({ checks, theme_viewports: 8, token_requests: tokenRequests, synthetic_form_posts: receivedPayloads.length, screenshots: Boolean(screenshotDir), camera: 'synthetic canvas stream only', real_faces_or_devices: false }));
} finally {
    await client?.close();
    await new Promise(resolve => server.close(resolve));
    assert.ok(resolve(fixture).startsWith(resolve(tmpdir()) + sep) && fixture.includes('vnz-submission-face-ui-'));
    await rm(fixture, { recursive: true, force: true });
}
