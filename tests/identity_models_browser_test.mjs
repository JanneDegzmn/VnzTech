import { createServer } from 'node:http';
import { readFile } from 'node:fs/promises';
import { resolve, dirname, sep } from 'node:path';
import { fileURLToPath } from 'node:url';
import { createHash } from 'node:crypto';
import assert from 'node:assert/strict';
import { browser } from './browser_cdp.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..', 'assets/vendor/face-api');
const manifest = JSON.parse(await readFile(resolve(root, 'manifest.json'), 'utf8'));
for (const [file, entry] of Object.entries(manifest.files)) {
    const bytes = await readFile(resolve(root, file));
    assert.equal(createHash('sha256').update(bytes).digest('hex'), entry.sha256, `Integrity: ${file}`);
    assert.equal(bytes.length, entry.bytes);
}
const server = createServer(async (req, res) => {
    try {
        if (req.url === '/') {
            res.setHeader('Content-Type', 'text/html');
            res.end('<!doctype html><html><body><script src="/face-api.min.js"></script></body></html>'); return;
        }
        const path = resolve(root, '.' + new URL(req.url, 'http://localhost').pathname);
        if (!path.startsWith(root + sep)) { res.writeHead(403); res.end(); return; }
        res.setHeader('Content-Type', path.endsWith('.js') ? 'text/javascript' : path.endsWith('.json') ? 'application/json' : 'application/octet-stream');
        res.end(await readFile(path));
    } catch { res.writeHead(404); res.end(); }
});
await new Promise(resolve => server.listen(0, '127.0.0.1', resolve));
let chrome;
try {
    chrome = await browser();
    await chrome.navigate(`http://127.0.0.1:${server.address().port}/`);
    const result = await chrome.evaluate(`(async () => {
        faceapi.tf.setBackend('cpu');
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri('/models'),
            faceapi.nets.faceLandmark68Net.loadFromUri('/models'),
            faceapi.nets.faceRecognitionNet.loadFromUri('/models')
        ]);
        const canvas = document.createElement('canvas'); canvas.width=256; canvas.height=256;
        canvas.getContext('2d').fillRect(0,0,256,256);
        const faces = await faceapi.detectAllFaces(canvas, new faceapi.TinyFaceDetectorOptions()).withFaceLandmarks().withFaceDescriptors();
        const distance = faceapi.euclideanDistance(new Float32Array(128), new Float32Array(128));
        return { loaded: [faceapi.nets.tinyFaceDetector.isLoaded, faceapi.nets.faceLandmark68Net.isLoaded, faceapi.nets.faceRecognitionNet.isLoaded], faces: faces.length, distance, backend: faceapi.tf.getBackend() };
    })()`);
    assert.deepEqual(result.loaded, [true, true, true]); assert.equal(result.faces, 0); assert.equal(result.distance, 0);
    assert.deepEqual(chrome.errors, []);
    console.log(JSON.stringify({ pass: true, integrity_assets: Object.keys(manifest.files).length, model_runtime: result, limits: 'Synthetic blank canvas only; no human ID/camera, matching accuracy or liveness established.' }));
} finally { await chrome?.close(); await new Promise(resolve => server.close(resolve)); }
