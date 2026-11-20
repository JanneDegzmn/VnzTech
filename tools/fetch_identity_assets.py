"""Fetch only the approved pinned browser distribution/models; never execute downloads."""
from pathlib import Path
import hashlib
import json
import urllib.request

ROOT = Path(__file__).resolve().parents[1] / 'assets' / 'vendor' / 'face-api'
BASE = 'https://raw.githubusercontent.com/justadudewhohacks/face-api.js/0.22.2/'
FILES = {
    'face-api.min.js': 'dist/face-api.min.js',
    'LICENSE': 'LICENSE',
    'models/tiny_face_detector_model-weights_manifest.json': 'weights/tiny_face_detector_model-weights_manifest.json',
    'models/tiny_face_detector_model-shard1': 'weights/tiny_face_detector_model-shard1',
    'models/face_landmark_68_model-weights_manifest.json': 'weights/face_landmark_68_model-weights_manifest.json',
    'models/face_landmark_68_model-shard1': 'weights/face_landmark_68_model-shard1',
    'models/face_recognition_model-weights_manifest.json': 'weights/face_recognition_model-weights_manifest.json',
    'models/face_recognition_model-shard1': 'weights/face_recognition_model-shard1',
    'models/face_recognition_model-shard2': 'weights/face_recognition_model-shard2',
}

def main():
    manifest = {'package': 'face-api.js', 'version': '0.22.2', 'license': 'MIT', 'files': {}}
    for target, remote in FILES.items():
        url = BASE + remote
        with urllib.request.urlopen(url, timeout=60) as response:
            data = response.read(12 * 1024 * 1024 + 1)
        if not data or len(data) > 12 * 1024 * 1024:
            raise RuntimeError('Unexpected asset size: ' + target)
        if target.endswith('.json'):
            json.loads(data)
        path = ROOT / target
        path.parent.mkdir(parents=True, exist_ok=True)
        if path.exists() and path.read_bytes() != data:
            raise RuntimeError('Existing asset differs; review before replacement: ' + target)
        path.write_bytes(data)
        manifest['files'][target] = {'url': url, 'bytes': len(data), 'sha256': hashlib.sha256(data).hexdigest()}
    (ROOT / 'manifest.json').write_text(json.dumps(manifest, indent=2) + '\n', encoding='utf-8')
    print(json.dumps({'version': manifest['version'], 'files': len(FILES), 'bytes': sum(item['bytes'] for item in manifest['files'].values())}))

if __name__ == '__main__':
    main()
