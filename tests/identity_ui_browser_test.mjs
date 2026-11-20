// Actual PHP templates/styles/scripts, synthetic API/camera/model boundaries.
import { createServer } from 'node:http';
import { readFile, writeFile, mkdtemp, rm } from 'node:fs/promises';
import { spawnSync } from 'node:child_process';
import { tmpdir } from 'node:os';
import { resolve, dirname, join, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import assert from 'node:assert/strict';
import { browser, delay } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const screenshots = process.env.VNZ_IDENTITY_SCREENSHOTS;
if (screenshots && !resolve(screenshots).startsWith(root + sep + '.qa-identity-')) throw new Error('Use a task-owned .qa-identity-* directory for previews');
if (screenshots) { const { mkdir } = await import('node:fs/promises'); await mkdir(screenshots, { recursive: true }); }
const fixture = await mkdtemp(join(tmpdir(), 'vnz-identity-ui-'));
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const quote = text => "'" + text.replaceAll('\\', '/').replaceAll("'", "\\'") + "'";
const compiled = {};
const areaStyles = {};
for (const [context, file] of Object.entries({ guest: 'sell.php', account: 'account/dashboard.php', admin: 'admin/dashboard.php' })) {
    const source = await readFile(join(root, file), 'utf8');
    areaStyles[context] = [...new Set([...source.matchAll(/href=["'](\/VNZ-TECH\/assets\/css\/[^?"'\s]+\.css)/g)].map(match => match[1]))]
        .map(href => `<link rel="stylesheet" href="${href}">`).join('');
}
function render(source) {
    const result = spawnSync(php, ['-r', `session_save_path(${quote(fixture)});` + source], { cwd: root, encoding: 'utf8', windowsHide: true });
    assert.equal(result.status, 0, 'PHP fixture rendering must succeed: ' + result.stderr);
    assert.ok(!result.stdout.includes('Warning:'), 'No PHP warnings: ' + result.stdout.slice(0, 500));
    return result.stdout;
}
for (const context of ['guest', 'account']) {
    for (const language of ['en', 'fil']) {
        for (const mode of ['manual_v2', 'auto_dual_v4']) {
            compiled[context + language + mode] = render(`function vnzIdentityMode(){return '${mode}';} require ${quote(root + '/includes/identity-verification.php')}; vnzRenderIdentityVerification('${context}', 'synthetic@example.test', '${language}', true);`);
        }
    }
}
const adminSource = await readFile(join(root, 'admin/pages/identity_verifications.php'), 'utf8');
const marker = '<section class="vnz-identity" data-identity-area="admin" data-identity-admin';
const adminTemplate = adminSource.slice(adminSource.indexOf(marker));
assert.ok(adminTemplate.startsWith(marker));
const adminFixturePath = join(fixture, 'admin.php');
await writeFile(adminFixturePath, `<?php
$identityEscape=static fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');
function generateCsrfToken(){return 'synthetic-csrf';}
$identityQueueError='';
$identityQueue=[['id'=>1,'email'=>'synthetic@example.test','context'=>'guest','status'=>'pending_review','evidence_hash'=>str_repeat('a',64),'evidence_available'=>true,'created_at'=>'2026-11-19 08:00:00','expires_at'=>'2026-11-26 08:00:00']];
?>` + adminTemplate);
compiled.admin = render(`require ${quote(adminFixturePath)};`);
const automaticGuard = adminSource.slice(adminSource.indexOf('if (vnzIdentityAutomatic())'), adminSource.indexOf('if (!vnzIdentityReviewer($pdo))'));
assert.ok(automaticGuard.includes('http_response_code(410)'));
const retiredFixturePath = join(fixture, 'admin-auto.php');
await writeFile(retiredFixturePath, `<?php function vnzIdentityAutomatic(){return true;} function vnzIdentityReviewer(){throw new Exception('Reviewer must not be queried');} function vnzIdentityQueue(){throw new Exception('Private evidence queue must not be queried');}\n` + automaticGuard);
compiled.adminAuto = render(`require ${quote(retiredFixturePath)};`);
assert.ok(!compiled.adminAuto.includes('data-identity-evidence'));
const dashboardSource = await readFile(join(root, 'admin/dashboard.php'), 'utf8');
assert.ok(dashboardSource.includes('if (!vnzSubmissionFaceMode() && !vnzIdentityAutomatic() && vnzIdentityReviewer($pdo))'), 'Face-only and automatic navigation retire manual review before permission query');
assert.ok(dashboardSource.includes("page === 'identity_verifications' && response.status === 410"), 'Retired review URL renders its themed explanation through the actual dashboard loader');
let status = 'not_started';
let unavailable = false;
let reviewPosts = 0;
let activeMode = 'manual_v2';
let automaticPosts = 0;
let automaticBody = '';
let automaticResult = 'approved';
let statusReads = 0;
const luminance = color => {
    const channels = color.match(/[\d.]+/g).slice(0, 3).map(Number).map(value => {
        const s = value / 255; return s <= .04045 ? s / 12.92 : ((s + .055) / 1.055) ** 2.4;
    });
    return channels[0] * .2126 + channels[1] * .7152 + channels[2] * .0722;
};
const contrast = (first, second) => (Math.max(luminance(first), luminance(second)) + .05) / (Math.min(luminance(first), luminance(second)) + .05);
const server = createServer(async (req, res) => {
    try {
        const url = new URL(req.url, 'http://localhost');
        if (url.pathname === '/') {
            const context = url.searchParams.get('context') || 'guest';
            const area = context === 'guest' ? 'public' : context === 'account' ? 'seller' : 'admin';
            const theme = url.searchParams.get('theme') || 'light';
            const language = url.searchParams.get('language') || 'en';
            activeMode = url.searchParams.get('mode') || 'manual_v2';
            const template = context === 'admin' ? (activeMode === 'auto_dual_v4' ? compiled.adminAuto : compiled.admin) : `<form>${compiled[context + language + activeMode]}<label>Item notes <input name="item_notes" value="Keep my item draft"></label><input name="seller_email" value="synthetic@example.test"><button data-identity-final type="submit">Submit item</button></form>`;
            res.setHeader('Content-Type', 'text/html; charset=utf-8');
            res.end(`<!doctype html><html data-${area}-theme="${theme}" data-${area === 'public' ? 'seller' : 'public'}-theme="${theme === 'light' ? 'dark' : 'light'}"><head><meta name="viewport" content="width=device-width,initial-scale=1">${areaStyles[context]}<link rel="stylesheet" href="/VNZ-TECH/assets/css/identity_verification.css"><style>body{margin:0;padding:16px;font-family:Arial;background:${theme === 'light' ? '#edf3f8' : '#080e14'}}main{max-width:1000px;margin:auto}input{max-width:100%}</style></head><body><main>${template}</main><script src="/VNZ-TECH/assets/js/identity-verification.js"></script></body></html>`); return;
        }
        if (url.pathname.endsWith('/identity_api.php')) {
            res.setHeader('Content-Type', 'application/json');
            if (url.searchParams.get('action') === 'evidence') { res.writeHead(403); res.end('{}'); return; }
            if (req.method === 'POST') {
                if (activeMode === 'auto_dual_v4') {
                    automaticPosts++;
                    const chunks = []; for await (const chunk of req) chunks.push(chunk);
                    automaticBody = Buffer.concat(chunks).toString('utf8');
                    await delay(100);
                    status = automaticResult;
                    if (status === 'unavailable') res.writeHead(503);
                    res.end(JSON.stringify({ success: status !== 'unavailable', enabled: true, available: status !== 'unavailable', approved: status === 'approved', status, mode: activeMode, policy_version: activeMode, draft_id: 'synthetic-draft', csrf_token: 'synthetic-csrf' })); return;
                }
                reviewPosts++; res.end(JSON.stringify({ success: true, message: 'Synthetic decision saved.' })); return;
            }
            statusReads++;
            if (unavailable) { res.writeHead(503); res.end(JSON.stringify({ success: false, enabled: true, available: false, ...(activeMode === 'auto_dual_v4' ? { status: 'unavailable', mode: activeMode } : {}), message: 'Synthetic face-check service unavailable. Try again later.' })); return; }
            res.end(JSON.stringify({ success: true, enabled: true, available: true, approved: status === 'approved', status, mode: activeMode, policy_version: activeMode, draft_id: 'synthetic-draft', email: 'synthetic@example.test', csrf_token: 'synthetic-csrf', expires_at: '2030-01-01 00:00:00' })); return;
        }
        const path = resolve(root, '.' + url.pathname.replace(/^\/VNZ-TECH/, ''));
        if (!path.startsWith(resolve(root, 'assets') + sep)) { res.writeHead(403); res.end(); return; }
        res.setHeader('Content-Type', path.endsWith('.css') ? 'text/css' : path.endsWith('.js') ? 'text/javascript' : 'application/octet-stream');
        res.end(await readFile(path));
    } catch { res.writeHead(404); res.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
let chrome;
let cases = 0;
try {
    chrome = await browser();
    const origin = `http://127.0.0.1:${server.address().port}`;
    for (const mode of ['manual_v2', 'auto_dual_v4']) {
    for (const context of ['guest', 'account', 'admin']) {
        for (const theme of ['light', 'dark']) {
            for (const width of [1200, 375]) {
                status = 'not_started'; unavailable = false;
                await chrome.call('Emulation.setDeviceMetricsOverride', { width, height: 900, deviceScaleFactor: 1, mobile: width === 375 });
                await chrome.navigate(`${origin}/?context=${context}&mode=${mode}&theme=${theme}&language=${width === 375 ? 'fil' : 'en'}`);
                await delay(150);
                if (context !== 'admin') {
                    await chrome.evaluate(`document.querySelector('[data-identity-open]').click()`);
                } else if (mode === 'manual_v2') {
                    // Keep evidence closed: rejected private reads are tested separately below.
                    await chrome.evaluate(`document.querySelector('[data-identity-review]').open=true`);
                    await delay(150);
                }
                await chrome.call('Input.dispatchKeyEvent', { type: 'keyDown', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                await chrome.call('Input.dispatchKeyEvent', { type: 'keyUp', key: 'Tab', code: 'Tab', windowsVirtualKeyCode: 9 });
                const layout = await chrome.evaluate(`(() => {
                    const card=document.querySelector('.vnz-identity');
                    const c=getComputedStyle(card);
                    const s=document.querySelector('[data-identity-status],[data-identity-review-message],[data-identity-retired] [role=status]');
                    const btn=card.querySelector('button:not([disabled]):not([hidden])'); btn?.focus();
                    return { overflow:document.documentElement.scrollWidth>innerWidth+1,background:c.backgroundColor,text:c.color,smallScreen:innerWidth,
                      focus:btn?getComputedStyle(btn).outlineStyle:'none',role:s.getAttribute('role'),disabled:document.querySelector('[data-identity-final]')?.disabled,
                      privateNames: [...card.querySelectorAll('[data-identity-file],[data-identity-email],[data-identity-consent]')].some(e=>e.hasAttribute('name')),
                      enrollmentVisible:!document.querySelector('[data-identity-enrollment]')?.hidden };
                })()`);
                assert.equal(layout.overflow, false, `Overflow ${context}/${theme}/${width}`);
                assert.equal(layout.smallScreen, width);
                assert.ok(theme === 'light' ? luminance(layout.background) > .8 : luminance(layout.background) < .1, 'Active area theme surface');
                assert.ok(contrast(layout.text, layout.background) >= 4.5, 'Readable text contrast with actual area tokens');
                assert.equal(layout.role, 'status'); assert.equal(layout.privateNames, false);
                if (context !== 'admin' || mode === 'manual_v2') assert.notEqual(layout.focus, 'none', 'Visible keyboard focus');
                if (screenshots) {
                    const shot=await chrome.call('Page.captureScreenshot',{format:'png',captureBeyondViewport:false});
                    await writeFile(join(screenshots,`${mode}-${context}-${theme}-${width}.png`),Buffer.from(shot.data,'base64'));
                }
                if (context !== 'admin') {
                    assert.equal(layout.disabled, true);
                    assert.equal(layout.enrollmentVisible, true);
                    await chrome.evaluate(`Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:async()=>{throw new DOMException('Synthetic denied','NotAllowedError')}});document.querySelector('[data-identity-camera]').click()`);
                    await delay(80);
                    assert.ok(await chrome.evaluate(`document.querySelector('[data-identity-comparison]').dataset.tone==='error'`));
                    status = mode === 'auto_dual_v4' ? 'processing' : 'pending_review';
                    await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(100);
                    assert.ok(await chrome.evaluate(`document.querySelector('[data-identity-final]').disabled && document.querySelector('[name=item_notes]').value==='Keep my item draft'`));
                    if (mode === 'auto_dual_v4') {
                        assert.ok(await chrome.evaluate(`document.querySelector('[data-identity-open]').hidden && document.querySelector('[data-identity-status]').dataset.tone==='warning'`), 'Processing blocks another enrollment');
                        status = 'retry';
                        await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(100);
                        assert.ok(await chrome.evaluate(`!document.querySelector('[data-identity-open]').hidden && document.querySelector('[data-identity-status]').dataset.tone==='error' && document.querySelector('[data-identity-final]').disabled`), 'No match requires a new check, never approval');
                    }
                    status = 'approved';
                    await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(100);
                    assert.ok(await chrome.evaluate(`!document.querySelector('[data-identity-final]').disabled && document.querySelector('[data-identity-status]').dataset.tone==='success'`));
                    unavailable = true;
                    await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(100);
                    const expectedTone = mode === 'auto_dual_v4' ? 'warning' : 'error';
                    assert.ok(await chrome.evaluate(`document.querySelector('[data-identity-final]').disabled && document.querySelector('[data-identity-status]').dataset.tone==='${expectedTone}' && document.querySelector('[data-identity-open]').hidden`));
                } else if (mode === 'manual_v2') {
                    const review = await chrome.evaluate(`(() => { const approve=document.querySelector('[data-identity-decision=approve]');const check=document.querySelector('[data-identity-review-confirm]');check.checked=true;check.dispatchEvent(new Event('change')); return {blocked:approve.disabled,error:document.querySelector('[data-identity-review-message]').dataset.tone}; })()`);
                    assert.equal(review.blocked, true, 'Failed evidence/model comparison cannot enable approve');
                    const refreshArgs = await chrome.evaluate(`(() => {window.loadAdminPage=(...args)=>window.__identityRefreshArgs=args;document.querySelector('[data-identity-queue-refresh]').click();return window.__identityRefreshArgs;})()`);
                    assert.deepEqual(refreshArgs, ['identity_verifications'], 'Queue refresh follows actual dashboard wrapper contract');
                } else {
                    assert.ok(await chrome.evaluate(`!!document.querySelector('[data-identity-retired]') && !document.querySelector('[data-identity-admin],[data-identity-evidence],[data-identity-decision]')`), 'Retired admin page has no evidence or manual approval controls');
                }
                cases++;
            }
        }
    }
    }
    // Assert no/multiple face quality failures and failed model load are recoverable.
    unavailable = false; status = 'not_started';
    await chrome.navigate(origin + '/');
    const quality = await chrome.evaluate(`(async()=>{
       let count=0,broken=true;
       window.faceapi={nets:Object.fromEntries(['tinyFaceDetector','faceLandmark68Net','faceRecognitionNet'].map(n=>[n,{loadFromUri:async()=>{if(broken)throw Error('Synthetic model failure')}}])),TinyFaceDetectorOptions:function(){},detectAllFaces:()=>({withFaceLandmarks:()=>({withFaceDescriptors:async()=>Array.from({length:count},()=>({descriptor:new Float32Array([0,0])}))})})};
       let loadError=false,noFace=false,multiple=false;
       try{await VNZIdentity.compareImages({},{});}catch(e){loadError=e.message.includes('Synthetic model failure');}
       broken=false;
       try{await VNZIdentity.compareImages({},{});}catch(e){noFace=e.code==='FACE_COUNT';}
       count=2;try{await VNZIdentity.compareImages({},{});}catch(e){multiple=e.code==='FACE_COUNT';}
       count=1;const retry=await VNZIdentity.compareImages({},{});
       return {loadError,noFace,multiple,retry};
    })()`);
    assert.deepEqual(quality, { loadError: true, noFace: true, multiple: true, retry: 0 });
    const cameraCleanup = await chrome.evaluate(`(async()=>{
       document.querySelector('[data-identity-open]').click();
       let stopped=0;
       Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:async()=>{
         const canvas=document.createElement('canvas');canvas.width=320;canvas.height=240;canvas.getContext('2d').fillRect(0,0,320,240);
         const stream=canvas.captureStream(1);stream.getTracks().forEach(track=>{const stop=track.stop.bind(track);track.stop=()=>{stopped++;stop();};});return stream;
       }});
       document.querySelector('[data-identity-camera]').click();await new Promise(r=>setTimeout(r,250));
       document.querySelector('[data-identity-capture]').click();await new Promise(r=>setTimeout(r,250));
       const captured=stopped===1&&!document.querySelector('[data-identity-video]').srcObject&&document.querySelector('[data-identity-selfie-preview]').src.startsWith('blob:');
       document.querySelector('[data-identity-cancel]').click();
       const cleared=!document.querySelector('[data-identity-selfie-preview]').hasAttribute('src');
       document.querySelector('[data-identity-open]').click();document.querySelector('[data-identity-camera]').click();await new Promise(r=>setTimeout(r,200));
       document.querySelector('[data-identity-cancel]').click();return {captured,cleared,stoppedOnCancel:stopped===2};
    })()`);
    assert.deepEqual(cameraCleanup, { captured: true, cleared: true, stoppedOnCancel: true });
    // Exercise the actual automatic upload handler with synthetic canvas photos and a mocked server decision.
    // The automatic UI must not require/download a browser face model or trust a browser distance.
    const prepareAutomatic = async () => {
        await chrome.evaluate(`(async()=>{
            document.querySelector('[data-identity-open]').click();
            const canvas=document.createElement('canvas');canvas.width=320;canvas.height=240;canvas.getContext('2d').fillRect(0,0,320,240);
            const blob=await new Promise(r=>canvas.toBlob(r,'image/jpeg'));
            const transfer=new DataTransfer();transfer.items.add(new File([blob],'synthetic-id.jpg',{type:'image/jpeg'}));
            const input=document.querySelector('[data-identity-file]');input.files=transfer.files;input.dispatchEvent(new Event('change'));
            Object.defineProperty(navigator.mediaDevices,'getUserMedia',{configurable:true,value:async()=>{
                const cameraCanvas=document.createElement('canvas');cameraCanvas.width=320;cameraCanvas.height=240;
                cameraCanvas.getContext('2d').fillRect(0,0,320,240);return cameraCanvas.captureStream(1);
            }});
            document.querySelector('[data-identity-camera]').click();await new Promise(r=>setTimeout(r,300));
            document.querySelector('[data-identity-capture]').click();await new Promise(r=>setTimeout(r,300));
            const consent=document.querySelector('[data-identity-consent]');consent.checked=true;consent.dispatchEvent(new Event('change'));
        })()`);
    };
    const automaticDecisions = [];
    for (const result of ['approved', 'retry', 'unavailable']) {
        unavailable = false; status = 'not_started'; automaticResult = result;
        await chrome.navigate(origin + '/?mode=auto_dual_v4'); await delay(100);
        await prepareAutomatic();
        const ready = await chrome.evaluate(`({disabled:document.querySelector('[data-identity-send]').disabled,message:document.querySelector('[data-identity-comparison]').textContent,video:document.querySelector('[data-identity-video]').videoWidth,selfie:document.querySelector('[data-identity-selfie-preview]').src,consent:document.querySelector('[data-identity-consent]').checked})`);
        assert.equal(ready.disabled, false, 'Automatic ready state uses images and fresh consent, not browser scoring: ' + JSON.stringify(ready));
        const before = automaticPosts;
        await chrome.evaluate(`document.querySelector('[data-identity-send]').click();document.querySelector('[data-identity-send]').click();`);
        assert.equal(await chrome.evaluate(`document.querySelector('[data-identity-send]').disabled && document.querySelector('[data-identity-file]').disabled && document.querySelector('[data-identity-consent]').disabled`), true, 'Busy state prevents changed evidence and duplicate submit');
        await delay(220);
        assert.equal(automaticPosts - before, 1, 'Double click sends one automatic attempt');
        assert.match(automaticBody, /name="consent_version"\r\n\r\nauto_dual_v4/);
        assert.match(automaticBody, /name="draft_id"\r\n\r\nsynthetic-draft/);
        assert.match(automaticBody, /name="id_image"; filename="synthetic-id.jpg"/);
        assert.match(automaticBody, /name="selfie_image"; filename="selfie.jpg"/);
        assert.ok(!/descriptor|face_token|api_secret|api_key|confidence/.test(automaticBody), 'No client face score, provider key or token submitted');
        const outcome = await chrome.evaluate(`({disabled:document.querySelector('[data-identity-final]').disabled,tone:document.querySelector('[data-identity-status]').dataset.tone,openHidden:document.querySelector('[data-identity-open]').hidden,consent:document.querySelector('[data-identity-consent]').checked,kept:document.querySelector('[name=item_notes]').value==='Keep my item draft',vendor:!!document.querySelector('script[src*="face-api"]')||!!window.faceapi})`);
        assert.equal(outcome.disabled, result !== 'approved');
        assert.equal(outcome.tone, result === 'approved' ? 'success' : result === 'retry' ? 'error' : 'warning');
        assert.equal(outcome.openHidden, result !== 'retry');
        assert.equal(outcome.consent, false, 'Each decided/failed enrollment requires fresh consent');
        assert.equal(outcome.kept, true); assert.equal(outcome.vendor, false, 'Automatic UI does not download client matcher');
        automaticDecisions.push(result);
    }
    // Switching the server policy cannot silently reuse the old consent displayed by an open tab.
    unavailable = false; status = 'not_started';
    await chrome.navigate(origin + '/?mode=manual_v2'); await delay(100);
    activeMode = 'auto_dual_v4'; status = 'approved';
    await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(100);
    assert.ok(await chrome.evaluate(`document.querySelector('[data-identity-final]').disabled && document.querySelector('[data-identity-open]').hidden && document.querySelector('[data-identity-status]').dataset.tone==='warning' && document.querySelector('[name=item_notes]').value==='Keep my item draft'`), 'Changed policy requires reload and fresh consent without dropping the item draft');
    // Compress only the 3s processing timer; prove the configured 8-poll bound without a slow test.
    status = 'not_started';
    await chrome.navigate(origin + '/?mode=auto_dual_v4'); await delay(100);
    await chrome.evaluate(`window.__nativeTimeout=window.setTimeout;window.setTimeout=(fn,ms,...args)=>window.__nativeTimeout(fn,ms===3000?2:ms,...args)`);
    status = 'processing'; const beforePolls = statusReads;
    await chrome.evaluate(`document.querySelector('[data-identity-refresh]').click()`); await delay(350);
    assert.equal(statusReads - beforePolls, 9, 'Processing refresh plus at most 8 automatic polls');
    const stoppedPolls = statusReads; await delay(150); assert.equal(statusReads, stoppedPolls, 'Polling stays stopped until another deliberate status check');
    assert.equal(reviewPosts, 0, 'No review submitted from merely checking confirm');
    assert.deepEqual(chrome.errors, []);
    console.log(JSON.stringify({ pass: true, cases, themes: 'manual+automatic public/seller/admin light+dark x1200/375, conflicting inactive theme included', quality, cameraCleanup, automaticDecisions, processingPolls: 8, policyChange: 'blocked with draft preserved', limits: 'Actual templates/JS/CSS with synthetic API/camera/models. Face++ responses are mocked. No personal evidence, authenticated live session or physical device used.' }));
} finally {
    await chrome?.close(); await new Promise(resolve => server.close(resolve));
    if (!resolve(fixture).startsWith(resolve(tmpdir()) + sep) || !fixture.includes('vnz-identity-ui-')) throw new Error('Unsafe fixture cleanup');
    await rm(fixture, { recursive: true, force: true });
}
