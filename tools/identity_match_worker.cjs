'use strict';
// Fixed local CPU worker. No image paths, URLs, secrets, or descriptors leave this process.
const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const MAX_INPUT = 4 * 1024 * 1024;
const THRESHOLD = 0.45;
const ENGINE = 'face-api.js-0.22.2';
const MODEL_FILES = [
    'face-api.min.js', 'LICENSE',
    'models/tiny_face_detector_model-weights_manifest.json', 'models/tiny_face_detector_model-shard1',
    'models/face_landmark_68_model-weights_manifest.json', 'models/face_landmark_68_model-shard1',
    'models/face_recognition_model-weights_manifest.json', 'models/face_recognition_model-shard1', 'models/face_recognition_model-shard2'
];

function validImage(image) {
    if (!image || !Number.isInteger(image.width) || !Number.isInteger(image.height) || image.width < 128 || image.height < 128 || image.width > 640 || image.height > 640 || typeof image.rgb_base64 !== 'string' || !/^[A-Za-z0-9+/]*={0,2}$/.test(image.rgb_base64)) return null;
    const bytes = Buffer.from(image.rgb_base64, 'base64');
    return bytes.length === image.width * image.height * 3 && bytes.toString('base64') === image.rgb_base64 ? bytes : null;
}

function verifyAssets(root) {
    const manifest = JSON.parse(fs.readFileSync(path.join(root, 'manifest.json'), 'utf8'));
    if (manifest.package !== 'face-api.js' || manifest.version !== '0.22.2' || manifest.license !== 'MIT') throw new Error('assets');
    for (const file of MODEL_FILES) {
        const metadata = manifest.files[file];
        const bytes = fs.readFileSync(path.join(root, file));
        if (!metadata || bytes.length !== metadata.bytes || crypto.createHash('sha256').update(bytes).digest('hex') !== metadata.sha256) throw new Error('assets');
    }
}

function distanceResult(distance) {
    if (!Number.isFinite(distance) || distance < 0) throw new Error('distance');
    return { outcome: distance <= THRESHOLD ? 'pass' : 'retry', code: distance <= THRESHOLD ? 'matched' : 'not_matching', face_counts: [1, 1], distance, threshold: THRESHOLD };
}

function presenceResult(faces, image) {
    if (!Array.isArray(faces)) throw new Error('quality');
    if (faces.length !== 1) return { outcome: 'retry', code: faces.length ? 'multiple_faces' : 'no_face', face_counts: [faces.length] };
    const { box, score } = faces[0];
    if (!box || ![box.x, box.y, box.width, box.height, score].every(Number.isFinite)
        || score < 0.5 || score > 1 || box.width < 48 || box.height < 48
        || box.x < -image.width * 0.05 || box.y < -image.height * 0.05
        || box.x + box.width > image.width * 1.05 || box.y + box.height > image.height * 1.05) throw new Error('quality');
    return { outcome: 'pass', code: 'face_present', face_counts: [1] };
}

async function processRequest(request) {
    if (!request || request.schema_version !== 1 || !/^[a-f0-9]{32}$/.test(request.nonce || '') || !['detect', 'compare', 'presence'].includes(request.action) || !Array.isArray(request.images) || request.images.length !== (request.action === 'presence' ? 1 : 2)) throw new Error('request');
    const bytes = request.images.map(validImage);
    if (bytes.some(item => !item)) throw new Error('request');
    const root = path.resolve(__dirname, '../assets/vendor/face-api');
    verifyAssets(root);
    // The approved browser bundle also exports CPU/tensor/disk APIs in Node.
    const faceapi = require(path.join(root, 'face-api.min.js'));
    await faceapi.tf.setBackend('cpu');
    await faceapi.nets.tinyFaceDetector.loadFromDisk(path.join(root, 'models'));
    if (request.action === 'compare') {
        await faceapi.nets.faceLandmark68Net.loadFromDisk(path.join(root, 'models'));
        await faceapi.nets.faceRecognitionNet.loadFromDisk(path.join(root, 'models'));
    }
    const tensors = request.images.map((image, index) => faceapi.tf.tensor3d(new Uint8Array(bytes[index]), [image.height, image.width, 3], 'int32'));
    try {
        const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
        const results = [];
        for (const tensor of tensors) {
            let task = faceapi.detectAllFaces(tensor, options);
            if (request.action === 'compare') task = task.withFaceLandmarks().withFaceDescriptors();
            results.push(await task);
        }
        if (request.action === 'presence') return presenceResult(results[0], request.images[0]);
        if (results.some(faces => faces.length !== 1)) {
            return { outcome: 'retry', code: results.some(faces => faces.length > 1) ? 'multiple_faces' : 'no_face', face_counts: results.map(faces => faces.length) };
        }
        if (request.action === 'detect') {
            const boxes = results.map((faces, index) => {
                const detection = faces[0];
                const box = detection.box;
                if (![box.x, box.y, box.width, box.height, detection.score].every(Number.isFinite) || box.width < 32 || box.height < 32 || detection.score < 0.5) throw new Error('quality');
                return { x: box.x / request.images[index].width, y: box.y / request.images[index].height, width: box.width / request.images[index].width, height: box.height / request.images[index].height, score: detection.score };
            });
            return { outcome: 'pass', code: 'faces_detected', face_counts: [1, 1], boxes };
        }
        const descriptors = results.map(faces => faces[0].descriptor);
        if (descriptors.some(value => value.length !== 128 || !Array.from(value).every(Number.isFinite))) throw new Error('descriptor');
        const distance = faceapi.euclideanDistance(descriptors[0], descriptors[1]);
        return distanceResult(distance);
    } finally {
        tensors.forEach(tensor => tensor.dispose());
    }
}

async function main() {
    // Suppress third-party console output: stdout has exactly one bounded JSON response.
    console.log = console.warn = console.error = () => {};
    let length = 0;
    const chunks = [];
    for await (const chunk of process.stdin) {
        length += chunk.length;
        if (length > MAX_INPUT) throw new Error('request');
        chunks.push(chunk);
    }
    let request;
    try {
        request = JSON.parse(Buffer.concat(chunks).toString('utf8'));
        const result = await processRequest(request);
        process.stdout.write(JSON.stringify({ schema_version: 1, nonce: request.nonce, action: request.action, engine: ENGINE, ...result }));
    } catch (error) {
        const code = error.message === 'quality' ? 'image_quality' : error.message === 'request' || error instanceof SyntaxError ? 'invalid_request' : 'worker_unavailable';
        process.stdout.write(JSON.stringify({ schema_version: 1, nonce: typeof request?.nonce === 'string' ? request.nonce : null, action: request?.action ?? null, engine: ENGINE, outcome: code === 'image_quality' ? 'retry' : 'unavailable', code }));
        process.exitCode = 1;
    }
}

if (require.main === module) {
    main().catch(() => { process.stdout.write(JSON.stringify({ schema_version: 1, outcome: 'unavailable', code: 'invalid_request' })); process.exitCode = 1; });
}
module.exports = { validImage, distanceResult, presenceResult };
