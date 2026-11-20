import assert from 'node:assert/strict';
import { spawn } from 'node:child_process';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';
const require = createRequire(import.meta.url);
const worker = fileURLToPath(new URL('../tools/identity_match_worker.cjs', import.meta.url));
const { validImage, distanceResult, presenceResult } = require(worker);
let checks = 0;
const check = (condition, description) => { assert.ok(condition, description); checks++; console.log('PASS ' + description); };
function run(input, deadline = 10000) {
    return new Promise((resolve, reject) => {
        const child = spawn(process.execPath, ['--max-old-space-size=256', worker], { windowsHide: true, stdio: ['pipe', 'pipe', 'pipe'] });
        let stdout = '', stderr = '';
        const timer = setTimeout(() => { child.kill(); reject(new Error('Worker test exceeded its deadline')); }, deadline);
        child.stdout.on('data', data => { stdout += data; });
        child.stderr.on('data', data => { stderr += data; });
        child.on('error', error => { clearTimeout(timer); reject(error); });
        child.on('exit', code => { clearTimeout(timer); try { resolve({ code, value: JSON.parse(stdout), stdout, stderr }); } catch (error) { reject(error); } });
        child.stdin.on('error', () => {});
        child.stdin.end(typeof input === 'string' ? input : JSON.stringify(input));
    });
}
const image = { width: 128, height: 128, rgb_base64: Buffer.alloc(128 * 128 * 3).toString('base64') };
const detection = { score: 0.9, box: { x: 30, y: 20, width: 60, height: 70 } };
check(presenceResult([], image).code === 'no_face', 'presence requires a detected face');
check(presenceResult([detection, detection], image).code === 'multiple_faces', 'presence refuses multiple faces');
check(presenceResult([detection], image).code === 'face_present', 'presence decision accepts one bounded clear detection without a reference comparison');
for (const changed of [
    { ...detection, score: 0.49 }, { ...detection, score: NaN },
    { ...detection, box: { ...detection.box, width: 47 } },
    { ...detection, box: { ...detection.box, x: -50 } },
    { ...detection, box: { ...detection.box, height: 400 } }
]) {
    assert.throws(() => presenceResult([changed], image));
    check(true, 'presence rejects low-confidence, tiny or clipped synthetic detection');
}
check(validImage(image)?.length === 128 * 128 * 3, 'bounded RGB tensor accepted');
check(validImage({ ...image, width: 641 }) === null, 'oversized dimension rejected');
check(validImage({ ...image, width: 127 }) === null, 'undersized dimension rejected');
check(validImage({ ...image, rgb_base64: image.rgb_base64.slice(4) }) === null, 'truncated RGB tensor rejected');
check(validImage({ ...image, rgb_base64: '?' + image.rgb_base64 }) === null, 'malformed base64 rejected');
check(distanceResult(0.45).outcome === 'pass' && distanceResult(0.45).threshold === 0.45, 'approved distance boundary passes exactly');
check(distanceResult(0.4500001).outcome === 'retry', 'distance immediately beyond cutoff cannot pass');
for (const value of [NaN, Infinity, -0.01]) {
    assert.throws(() => distanceResult(value));
    check(true, 'invalid distance cannot become approval: ' + String(value));
}
let result = await run('{bad json');
check(result.code === 1 && result.value.code === 'invalid_request', 'malformed request fails closed');
result = await run({ schema_version: 1, action: 'detect', nonce: 'browser-choice', images: [image, image] });
check(result.code === 1 && result.value.code === 'invalid_request', 'invalid nonce rejected');
result = await run({ schema_version: 1, action: 'detect', nonce: 'a'.repeat(32), images: [{ ...image, url: 'https://example.invalid', rgb_base64: '' }, image] });
check(result.code === 1 && result.value.code === 'invalid_request', 'URL cannot replace actual tensor bytes');
result = await run(' '.repeat(4 * 1024 * 1024 + 1));
check(result.code === 1 && result.value.code === 'invalid_request', 'oversized JSON input fails closed');
result = await run({ schema_version: 1, action: 'presence', nonce: 'a'.repeat(32), images: [image, image] });
check(result.code === 1 && result.value.code === 'invalid_request', 'presence accepts exactly one image, never a selfie-to-itself pair');
result = await run({ schema_version: 1, action: 'compare', nonce: 'a'.repeat(32), images: [image] });
check(result.code === 1 && result.value.code === 'invalid_request', 'two-image comparison contract stays separate from presence');
for (const action of ['presence', 'detect', 'compare']) {
    result = await run({ schema_version: 1, action, nonce: 'a'.repeat(32), images: action === 'presence' ? [image] : [image, image] });
    check(result.code === 0 && result.value.outcome === 'retry' && result.value.code === 'no_face', `real CPU ${action} models refuse blank synthetic images`);
    check(result.value.nonce === 'a'.repeat(32) && result.value.action === action && result.value.engine === 'face-api.js-0.22.2', `${action} output is bound to request and engine`);
    check(!('descriptor' in result.value) && !('images' in result.value) && result.stderr === '', `${action} output exposes no image, descriptor or diagnostic dump`);
}
console.log(`Completed ${checks} synthetic worker checks; no real face, ID or accuracy calibration.`);
