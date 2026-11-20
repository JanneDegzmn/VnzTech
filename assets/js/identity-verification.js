/* Only trusted server decisions grant access; browser comparison is manual-mode assistance. */
(function () {
    'use strict';
    if (window.VNZIdentity) {
        window.VNZIdentity.init(document);
        return;
    }
    const api = '/VNZ-TECH/backend/identity_api.php';
    const vendor = '/VNZ-TECH/assets/vendor/face-api/';
    const wizards = new Set();
    let scriptPromise;
    let modelPromise;
    const selector = (root, name) => root.querySelector('[data-identity-' + name + ']');
    const fil = root => root.dataset.language === 'fil';
    const say = (root, en, tl) => fil(root) ? tl : en;
    function message(node, text, tone = '') {
        node.textContent = text;
        node.dataset.tone = tone;
    }
    function timeout(promise, milliseconds, text) {
        let timer;
        return Promise.race([promise, new Promise((_, reject) => {
            timer = setTimeout(() => reject(new Error(text)), milliseconds);
        })]).finally(() => clearTimeout(timer));
    }
    async function request(url, options = {}) {
        const controller = new AbortController();
        const timer = setTimeout(() => controller.abort(), 90000);
        try {
            const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store', ...options, signal: controller.signal });
            let data;
            try { data = await response.json(); } catch (_) { throw new Error('Unable to read verification status. Please try again.'); }
            if (!response.ok || !data.success) {
                const error = new Error(data.message || 'Verification is unavailable. Please try again.');
                error.data = data;
                throw error;
            }
            return data;
        } catch (error) {
            if (error.name === 'AbortError') throw new Error('The request took too long. Refresh the status before trying again.');
            throw error;
        } finally { clearTimeout(timer); }
    }
    function loadLibrary() {
        if (window.faceapi) return Promise.resolve(window.faceapi);
        if (!scriptPromise) {
            scriptPromise = timeout(new Promise((resolve, reject) => {
                const script = document.createElement('script');
                script.src = vendor + 'face-api.min.js';
                script.async = true;
                script.onload = () => window.faceapi ? resolve(window.faceapi) : reject(new Error('Face comparison did not load. Please retry.'));
                script.onerror = () => { script.remove(); reject(new Error('Face comparison could not load. Check your connection and retry.')); };
                document.head.append(script);
            }), 45000, 'Face comparison did not finish loading. Please retry.').catch(error => {
                scriptPromise = null;
                throw error;
            });
        }
        return scriptPromise;
    }
    async function loadModels() {
        if (!modelPromise) {
            modelPromise = loadLibrary().then(async faceapi => {
                await timeout(Promise.all([
                    faceapi.nets.tinyFaceDetector.loadFromUri(vendor + 'models/'),
                    faceapi.nets.faceLandmark68Net.loadFromUri(vendor + 'models/'),
                    faceapi.nets.faceRecognitionNet.loadFromUri(vendor + 'models/')
                ]), 90000, 'Face models took too long to load. Please retry.');
                return faceapi;
            }).catch(error => { modelPromise = null; throw error; });
        }
        return modelPromise;
    }
    async function compareImages(first, second) {
        const faceapi = await loadModels();
        const options = new faceapi.TinyFaceDetectorOptions({ inputSize: 416 });
        const results = await Promise.all([first, second].map(image =>
            faceapi.detectAllFaces(image, options).withFaceLandmarks().withFaceDescriptors()
        ));
        if (results[0].length !== 1 || results[1].length !== 1) {
            const error = new Error('Use an ID and selfie with exactly one clear face in each image. Adjust the light or retake the photo.');
            error.code = 'FACE_COUNT';
            throw error;
        }
        const a = results[0][0].descriptor;
        const b = results[1][0].descriptor;
        if (!a || !b || a.length !== b.length || !a.length) throw new Error('Unable to compare these photos. Please try again.');
        let squared = 0;
        for (let i = 0; i < a.length; i++) squared += (a[i] - b[i]) ** 2;
        const distance = Math.sqrt(squared);
        if (!Number.isFinite(distance)) throw new Error('Unable to compare these photos. Please try again.');
        return distance;
    }
    function imageReady(img) {
        if (img.complete && img.naturalWidth > 0) return Promise.resolve();
        return new Promise((resolve, reject) => {
            img.onload = () => resolve();
            img.onerror = () => {
                const error = new Error('This image could not be opened. Choose a different photo.');
                error.code = 'IMAGE_LOAD';
                reject(error);
            };
        });
    }
    function initializeWizard(root) {
        if (root.dataset.identityInitialized) return;
        root.dataset.identityInitialized = '1';
        const form = root.closest('form');
        if (!form) return;
        const state = {
            root, enabled: root.dataset.enabled === '1', available: false, approved: false,
            stream: null, selfie: null, idUrl: null, selfieUrl: null, ready: false,
            version: 0, busy: false, submitting: false, status: 'not_started', timer: null, disposed: false,
            mode: root.dataset.mode || 'manual_v2', polls: 0, modeChanged: false
        };
        wizards.add(state);
        const status = selector(root, 'status');
        const panel = selector(root, 'enrollment');
        const open = selector(root, 'open');
        const refresh = selector(root, 'refresh');
        const email = selector(root, 'email');
        const idFile = selector(root, 'file');
        const idPreview = selector(root, 'id-preview');
        const selfiePreview = selector(root, 'selfie-preview');
        const video = selector(root, 'video');
        const camera = selector(root, 'camera');
        const capture = selector(root, 'capture');
        const stop = selector(root, 'stop');
        const consent = selector(root, 'consent');
        const send = selector(root, 'send');
        const comparison = selector(root, 'comparison');
        const retryComparison = selector(root, 'retry-comparison');
        const draft = form.querySelector('[data-identity-draft]');
        const finals = Array.from(form.querySelectorAll('[data-identity-final]'));
        const finalLabels = finals.map(button => button.innerHTML);
        const sellerEmail = form.querySelector('[name="seller_email"]');
        const automatic = () => state.mode === 'auto_dual_v4';
        const sendLabel = send.textContent;
        const cancel = selector(root, 'cancel');
        function updateGate() {
            finals.forEach(button => {
                button.disabled = state.submitting || (state.enabled && !state.approved);
                if (state.enabled) button.setAttribute('aria-describedby', status.id || (status.id = root.id ? root.id + '-status' : 'identity-' + root.dataset.context + '-status'));
            });
        }
        function updateSend() {
            send.disabled = !state.available || state.busy || state.status === 'processing' || state.modeChanged || !state.ready || !state.selfie || !idFile.files?.length || !consent.checked || !email.value.trim() || !email.validity.valid;
            panel.setAttribute('aria-busy', state.busy ? 'true' : 'false');
            idFile.disabled = email.disabled = consent.disabled = cancel.disabled = state.busy;
            camera.disabled = capture.disabled = state.busy;
            send.textContent = state.busy && automatic() ? say(root, 'Checking both matches\u2026', 'Chine-check ang dalawang match\u2026') : sendLabel;
        }
        function stopCamera() {
            state.stream?.getTracks().forEach(track => track.stop());
            state.stream = null;
            video.srcObject = null;
            video.hidden = true;
            capture.hidden = true;
            stop.hidden = true;
            camera.disabled = false;
            selector(root, 'selfie-placeholder').hidden = !!state.selfie;
            selfiePreview.hidden = !state.selfie;
        }
        state.stopCamera = stopCamera;
        function clearPhotos() {
            stopCamera();
            state.version++;
            state.ready = false;
            state.selfie = null;
            idFile.value = '';
            if (state.idUrl) URL.revokeObjectURL(state.idUrl);
            if (state.selfieUrl) URL.revokeObjectURL(state.selfieUrl);
            state.idUrl = state.selfieUrl = null;
            retryComparison.hidden = true;
            idPreview.removeAttribute('src');
            selfiePreview.removeAttribute('src');
            idPreview.hidden = selfiePreview.hidden = true;
            selector(root, 'id-placeholder').hidden = false;
            selector(root, 'selfie-placeholder').hidden = false;
            consent.checked = false;
            updateSend();
        }
        state.dispose = () => {
            state.disposed = true;
            clearTimeout(state.timer);
            clearPhotos();
            wizards.delete(state);
        };
        state.suspend = () => {
            clearPhotos();
            clearTimeout(state.timer);
            state.approved = false;
            if (draft) draft.value = '';
            updateGate();
        };
        state.resume = () => {
            state.submitting = false;
            state.polls = 0;
            finals.forEach((button, index) => { button.innerHTML = finalLabels[index]; });
            state.approved = false;
            updateGate();
            if (state.enabled) refreshStatus();
        };
        function acceptStatus(data) {
            if (state.disposed) return;
            if (data.mode && data.mode !== state.mode) {
                state.modeChanged = true;
                state.available = state.approved = false;
                clearPhotos();
                panel.hidden = true;
                open.hidden = true;
                message(status, say(root, 'Verification has changed. Keep your item details, then reload this page to read the current consent and start a fresh check.', 'May bagong verification flow. I-save muna ang item details mo, tapos i-reload ang page para makita ang bagong consent at makapag-check ulit.'), 'warning');
                clearTimeout(state.timer);
                updateGate();
                updateSend();
                return;
            }
            if (typeof data.enabled === 'boolean') state.enabled = data.enabled;
            state.available = !!data.available;
            state.approved = state.available && !state.modeChanged && data.approved === true;
            state.status = data.status || 'not_started';
            root.hidden = !state.enabled;
            if (data.csrf_token) {
                form.querySelector('[name="csrf_token"]').value = data.csrf_token;
            }
            if (draft) draft.value = data.draft_id || '';
            if (data.email && (root.dataset.context === 'account' || panel.hidden || state.approved || state.status === 'pending_review')) {
                email.value = data.email;
                if (sellerEmail && root.dataset.context === 'guest' && (!sellerEmail.value || state.approved || state.status === 'pending_review')) sellerEmail.value = data.email;
            }
            if (sellerEmail && root.dataset.context === 'guest') {
                sellerEmail.readOnly = state.enabled && (state.approved || state.status === 'pending_review');
                if (sellerEmail.readOnly) sellerEmail.title = say(root, 'This email is bound to your identity review.', 'Naka-link ang email na ito sa identity review mo.');
                else sellerEmail.removeAttribute('title');
            }
            let text = data.message;
            let tone = '';
            if (state.approved) {
                tone = 'success';
                text = root.dataset.context === 'account'
                    ? (automatic() ? say(root, 'Both face checks passed. You can now submit from your account.', 'Pumasa ang dalawang face check. Puwede ka nang mag-submit sa account mo.') : say(root, 'Identity approved. You can now submit from your account.', 'Approved na ang identity mo. Puwede ka nang mag-submit sa account mo.'))
                    : (automatic() ? say(root, 'Both face checks passed for this guest submission. Complete your item details before the check expires.', 'Pumasa ang dalawang face check para sa guest submission na ito. Kumpletuhin ang item details bago mag-expire.') : say(root, 'Identity approved for this guest submission. Complete the item details before the approval expires.', 'Approved na para sa guest submission na ito. Kumpletuhin ang item details bago mag-expire.'));
            } else if (automatic() && state.status === 'processing') {
                tone = 'warning';
                text = say(root, 'Checking the ID portrait and camera selfie with both services. Keep your item details here; do not send another attempt yet.', 'Chine-check ang mukha sa ID at camera selfie gamit ang dalawang service. Dito lang ang item details mo; huwag munang mag-send ulit.');
                if (state.polls >= 8) text += ' ' + say(root, 'Automatic refresh has paused. Choose Refresh status to check again.', 'Naka-pause na ang automatic refresh. Pindutin ang I-check ulit para makita ang update.');
            } else if (automatic() && state.status === 'retry') {
                tone = 'error';
                text = fil(root)
                    ? (data.outcome_code === 'capture_quality'
                        ? 'Hindi ma-check nang maayos ang mukha sa ID o selfie. Dapat isang malinaw na mukha lang sa bawat photo. Ayusin ang ilaw at kunan ulit.'
                        : 'Hindi parehong pumasa ang face check. Gumamit ng malinaw na ID photo at kunan ulit ang selfie, tapos subukan ulit.')
                    : (data.message || 'The face checks did not both pass. Use a clear ID photo and retake your camera selfie, then try again.');
            } else if (automatic() && (!state.available || state.status === 'unavailable')) {
                tone = 'warning';
                text = fil(root)
                    ? 'Hindi available ang isang face-check service. Hindi ibig sabihin nito na hindi match ang mukha. I-check muna ulit ang status bago sumubok.'
                    : (data.message || 'A face-check service is unavailable. This does not mean the faces differ. Refresh the status before trying again.');
            } else if (state.status === 'pending_review') {
                tone = 'warning';
                text = say(root, 'ID and selfie sent. An admin needs to review them before you can submit your item. You can keep filling in this form; refreshing the status keeps your item details here.', 'Na-send na ang ID at selfie. Hintayin muna ang admin review bago ma-submit ang item. Puwede mong ituloy ang form; hindi mawawala ang details kapag nag-refresh ng status.');
            } else if (['rejected', 'revoked', 'expired'].includes(state.status)) {
                tone = 'error';
                const lead = state.status === 'rejected'
                    ? say(root, 'Your identity review was not approved.', 'Hindi na-approve ang identity review mo.')
                    : state.status === 'revoked'
                        ? say(root, 'Your identity approval was revoked.', 'Na-revoke ang identity approval mo.')
                        : say(root, 'This verification attempt or guest approval has expired.', 'Expired na ang verification attempt o guest approval na ito.');
                text = lead + (data.message ? ' ' + data.message : '') + ' ' + say(root, 'Start a new verification to try again.', 'Ulitin ang verification para makapag-submit.');
            }
            if (!text) text = say(root, 'Complete ID and selfie verification before submitting your item.', 'I-verify muna ang ID at selfie bago mag-submit ng item.');
            if (data.expires_at && state.approved) {
                const rawExpiry = String(data.expires_at).replace(' ', 'T');
                const expiry = new Date(/[zZ]$|[+-]\d\d:\d\d$/.test(rawExpiry) ? rawExpiry : rawExpiry + 'Z');
                text += ' ' + say(root, 'Valid until: ', 'Valid hanggang: ') + (Number.isNaN(expiry.getTime()) ? data.expires_at : expiry.toLocaleString());
            }
            message(status, text, tone);
            open.hidden = !state.available || state.approved || state.modeChanged || ['pending_review', 'processing'].includes(state.status);
            open.textContent = ['rejected', 'revoked', 'expired', 'retry'].includes(state.status)
                ? say(root, 'Try verification again', 'Ulitin ang verification')
                : say(root, 'Start verification', 'Simulan ang verification');
            if (state.approved || ['pending_review', 'processing'].includes(state.status) || !state.available || (automatic() && state.status === 'retry')) {
                panel.hidden = true;
                clearPhotos();
            }
            updateGate();
            updateSend();
            clearTimeout(state.timer);
            if (state.enabled && state.status === 'pending_review') {
                state.timer = setTimeout(() => { if (!document.hidden) refreshStatus(); }, 15000);
            } else if (state.enabled && automatic() && state.status === 'processing' && state.polls < 8) {
                state.polls++;
                state.timer = setTimeout(() => { if (!document.hidden) refreshStatus(); }, 3000);
            } else if (automatic() && state.status !== 'processing') {
                state.polls = 0;
            }
        }
        let refreshing;
        async function refreshStatus() {
            if (refreshing || state.disposed) return refreshing;
            refresh.disabled = true;
            refreshing = request(api + '?action=status&context=' + encodeURIComponent(root.dataset.context))
                .then(acceptStatus)
                .catch(error => {
                    state.approved = false;
                    state.available = false;
                    if (error.data?.status) acceptStatus(error.data);
                    else {
                        message(status, error.message, 'error');
                        open.hidden = true;
                        stopCamera();
                        updateSend();
                    }
                    updateGate();
                    clearTimeout(state.timer);
                    if (state.status === 'pending_review') state.timer = setTimeout(refreshStatus, 30000);
                }).finally(() => { refresh.disabled = false; refreshing = null; });
            return refreshing;
        }
        state.refresh = refreshStatus;
        async function compare() {
            const version = ++state.version;
            state.ready = false;
            updateSend();
            if (!idFile.files?.length || !state.selfie) return;
            if (automatic()) {
                retryComparison.hidden = true;
                try {
                    await Promise.all([imageReady(idPreview), imageReady(selfiePreview)]);
                    if (version !== state.version || state.disposed) return;
                    state.ready = true;
                    message(comparison, say(root, 'Photos are ready. Read the consent, then start both server face checks. No face data is sent until you choose Check my face.', 'Ready na ang photos. Basahin ang consent, tapos simulan ang dalawang server face check. Walang face data na ise-send hangga\u2019t hindi mo pinipindot ang I-check ang face match.'));
                } catch (error) { if (version === state.version && !state.disposed) message(comparison, error.message, 'error'); }
                updateSend();
                return;
            }
            retryComparison.hidden = false;
            retryComparison.disabled = true;
            message(comparison, say(root, 'Loading face comparison… Keep this page open.', 'Nilo-load ang face comparison… Huwag munang isara ang page.'));
            try {
                await Promise.all([imageReady(idPreview), imageReady(selfiePreview)]);
                const distance = await compareImages(idPreview, selfiePreview);
                if (version !== state.version || state.disposed) return;
                state.ready = true;
                message(comparison, say(root,
                    'One face found in each photo. Comparison distance: ' + distance.toFixed(3) + ' (lower means more similar). An admin will review both photos; this number does not approve your identity.',
                    'May isang mukha sa bawat photo. Comparison distance: ' + distance.toFixed(3) + ' (mas mababa, mas magkahawig). Admin pa rin ang magre-review; hindi approval ang number na ito.'
                ));
            } catch (error) {
                if (version !== state.version || state.disposed) return;
                const text = error.code === 'FACE_COUNT' && fil(root)
                    ? 'Dapat isang malinaw na mukha lang sa ID at selfie. Ayusin ang ilaw o kunan ulit.'
                    : error.message;
                message(comparison, text, 'error');
            }
            retryComparison.disabled = false;
            updateSend();
        }
        retryComparison.addEventListener('click', compare);
        open.addEventListener('click', () => {
            if (state.busy || !state.available || state.modeChanged || state.status === 'processing') return;
            panel.hidden = false;
            if (sellerEmail?.value && !email.value) email.value = sellerEmail.value;
            email.focus();
        });
        refresh.addEventListener('click', refreshStatus);
        cancel.addEventListener('click', () => {
            if (state.busy) return;
            clearPhotos();
            panel.hidden = true;
            open.focus();
        });
        email.addEventListener('input', () => {
            if (sellerEmail && root.dataset.context === 'guest') sellerEmail.value = email.value;
            updateSend();
        });
        sellerEmail?.addEventListener('input', () => {
            if (root.dataset.context === 'guest' && !state.approved && state.status !== 'pending_review') {
                email.value = sellerEmail.value;
                updateSend();
            }
        });
        consent.addEventListener('change', updateSend);
        idFile.addEventListener('change', async () => {
            if (automatic()) consent.checked = false;
            state.version++;
            state.ready = false;
            if (state.idUrl) URL.revokeObjectURL(state.idUrl);
            state.idUrl = null;
            idPreview.hidden = true;
            selector(root, 'id-placeholder').hidden = false;
            const file = idFile.files?.[0];
            if (!file) { updateSend(); return; }
            if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024 || !file.size) {
                idFile.value = '';
                message(comparison, say(root, 'Choose a JPG, PNG, or WEBP image up to 5 MB.', 'Pumili ng JPG, PNG, o WEBP na hanggang 5 MB lang.'), 'error');
                updateSend();
                return;
            }
            state.idUrl = URL.createObjectURL(file);
            idPreview.src = state.idUrl;
            idPreview.hidden = false;
            selector(root, 'id-placeholder').hidden = true;
            compare();
        });
        camera.addEventListener('click', async () => {
            if (state.busy) return;
            stopCamera();
            camera.disabled = true;
            try {
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) {
                    throw new Error(say(root, 'Camera access needs HTTPS or localhost and a supported browser.', 'Kailangan ng HTTPS o localhost at supported browser para sa camera.'));
                }
                const stream = await navigator.mediaDevices.getUserMedia({ audio: false, video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } } });
                if (state.disposed || panel.hidden || document.hidden) { stream.getTracks().forEach(track => track.stop()); camera.disabled = false; return; }
                state.stream = stream;
                video.srcObject = stream;
                video.hidden = false;
                selfiePreview.hidden = true;
                selector(root, 'selfie-placeholder').hidden = true;
                await video.play();
                capture.hidden = stop.hidden = false;
            } catch (error) {
                stopCamera();
                message(comparison, ['NotAllowedError', 'PermissionDeniedError'].includes(error.name)
                    ? say(root, 'Camera permission was denied. Allow it in your browser settings, then try again.', 'Hindi pinayagan ang camera. I-allow sa browser settings, tapos subukan ulit.')
                    : error.message, 'error');
            }
        });
        stop.addEventListener('click', stopCamera);
        capture.addEventListener('click', async () => {
            if (!video.videoWidth || !video.videoHeight) {
                message(comparison, say(root, 'Wait for the camera preview, then capture again.', 'Hintaying lumabas ang camera preview, tapos kunan ulit.'), 'error');
                return;
            }
            capture.disabled = true;
            try {
                const canvas = document.createElement('canvas');
                const scale = Math.min(1, 1280 / Math.max(video.videoWidth, video.videoHeight));
                canvas.width = Math.round(video.videoWidth * scale);
                canvas.height = Math.round(video.videoHeight * scale);
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', 0.9));
                canvas.width = canvas.height = 0;
                if (!blob) throw new Error('Unable to capture your selfie. Please try again.');
                if (state.disposed || panel.hidden) return;
                if (state.selfieUrl) URL.revokeObjectURL(state.selfieUrl);
                state.selfie = blob;
                if (automatic()) consent.checked = false;
                state.selfieUrl = URL.createObjectURL(blob);
                selfiePreview.src = state.selfieUrl;
                camera.textContent = say(root, 'Retake selfie', 'Kunan ulit');
                stopCamera();
                compare();
            } catch (error) { message(comparison, error.message, 'error'); }
            finally { capture.disabled = false; }
        });
        send.addEventListener('click', async () => {
            if (send.disabled || state.busy) return;
            if (!email.reportValidity()) return;
            state.busy = true;
            updateSend();
            const data = new FormData();
            data.set('action', 'submit');
            data.set('context', root.dataset.context);
            data.set('email', email.value.trim());
            data.set('consent', consent.checked ? '1' : '0');
            if (automatic()) data.set('consent_version', 'auto_dual_v4');
            if (draft?.value) data.set('draft_id', draft.value);
            data.set('csrf_token', form.querySelector('[name="csrf_token"]').value);
            data.set('id_image', idFile.files[0]);
            data.set('selfie_image', state.selfie, 'selfie.jpg');
            message(comparison, automatic()
                ? say(root, 'Checking the ID portrait and selfie on the server. Both local matching and Face++ must pass\u2026', 'Chine-check sa server ang mukha sa ID at selfie. Kailangang pumasa sa local matching at Face++\u2026')
                : say(root, 'Sending privately for admin review\u2026', 'Sine-send nang private para sa admin review\u2026'));
            try {
                acceptStatus(await request(api, { method: 'POST', body: data }));
                status.focus();
            } catch (error) {
                if (error.data?.status) acceptStatus(error.data);
                else {
                    state.approved = false;
                    message(comparison, error.message, 'error');
                    updateGate();
                    if (automatic()) await refreshStatus();
                }
            }
            finally { state.busy = false; updateSend(); }
        });
        form.addEventListener('submit', event => {
            if (!state.enabled || state.approved) return;
            event.preventDefault();
            event.stopImmediatePropagation();
            status.focus();
            root.scrollIntoView({ block: 'center', behavior: matchMedia('(prefers-reduced-motion: reduce)').matches ? 'auto' : 'smooth' });
        }, true);
        form.addEventListener('submit', event => {
            if (!event.defaultPrevented) state.submitting = true;
        });
        form.addEventListener('reset', () => {
            state.suspend();
            queueMicrotask(state.resume);
        });
        updateGate();
        if (state.enabled) refreshStatus();
    }
    function initializeAdmin(root) {
        if (root.dataset.identityInitialized) return;
        root.dataset.identityInitialized = '1';
        selector(root, 'queue-refresh')?.addEventListener('click', () => {
            if (window.loadAdminPage) window.loadAdminPage('identity_verifications');
            else window.location.reload();
        });
        root.querySelectorAll('[data-identity-review]').forEach(card => {
            const status = selector(card, 'review-message');
            const check = selector(card, 'review-confirm');
            const reason = selector(card, 'review-reason');
            const compareButton = selector(card, 'review-compare');
            const images = Array.from(card.querySelectorAll('[data-identity-evidence]'));
            const buttons = Array.from(card.querySelectorAll('[data-identity-decision]'));
            let compared = false;
            let busy = false;
            let version = 0;
            function update() {
                buttons.forEach(button => {
                    button.disabled = busy || !check?.checked || (button.dataset.identityDecision === 'approve' && !compared);
                });
            }
            async function compare() {
                const current = ++version;
                compared = false;
                compareButton.disabled = true;
                update();
                message(status, 'Loading private evidence and comparing faces…');
                try {
                    images.forEach(img => {
                        if (!img.hasAttribute('src')) img.src = api + '?action=evidence&attempt_id=' + encodeURIComponent(card.dataset.attemptId) + '&kind=' + encodeURIComponent(img.dataset.identityEvidence);
                    });
                    await timeout(Promise.all(images.map(imageReady)), 45000, 'The private photos could not be loaded. Refresh the queue and retry.');
                    if (images.length !== 2) throw new Error('Both original photos are needed for review.');
                    const distance = await compareImages(images[0], images[1]);
                    if (!card.isConnected || current !== version) return;
                    compared = true;
                    message(status, 'Face comparison distance: ' + distance.toFixed(3) + ' (lower means more similar). This is assistance only. Inspect the original ID and selfie yourself before making a decision.');
                } catch (error) {
                    const text = error.code === 'IMAGE_LOAD'
                        ? 'The private evidence could not be opened. Refresh the queue and try again.'
                        : error.message;
                    if (card.isConnected && current === version) message(status, text + ' Approval stays unavailable; you can reject with a reason.', 'error');
                } finally {
                    compareButton.disabled = false;
                    update();
                }
            }
            check?.addEventListener('change', update);
            compareButton?.addEventListener('click', compare);
            card.addEventListener('toggle', () => {
                if (card.open && compareButton && !compared && !busy) compare();
            });
            buttons.forEach(button => button.addEventListener('click', async () => {
                if (button.disabled || busy) return;
                const decision = button.dataset.identityDecision;
                if (decision !== 'approve' && !reason?.value.trim()) {
                    message(status, 'Add a reason before rejecting or revoking this verification.', 'error');
                    reason?.focus();
                    return;
                }
                busy = true;
                update();
                const data = new URLSearchParams({
                    action: 'review', decision, attempt_id: card.dataset.attemptId,
                    evidence_hash: card.dataset.evidenceHash, reason: reason?.value.trim() || '',
                    review_confirm: '1', csrf_token: root.dataset.csrf
                });
                try {
                    const result = await request(api, { method: 'POST', body: data });
                    message(status, result.message || 'Decision saved. Refresh the queue to view the current status.', 'success');
                    card.dataset.decided = '1';
                    buttons.forEach(item => { item.hidden = true; });
                    if (check) check.disabled = true;
                    const badge = selector(card, 'review-badge');
                    badge.dataset.status = decision === 'approve' ? 'approved' : decision === 'reject' ? 'rejected' : 'revoked';
                    badge.textContent = badge.dataset.status;
                } catch (error) { message(status, error.message, 'error'); }
                finally { busy = false; update(); }
            }));
            update();
        });
    }
    function init(scope = document) {
        if (scope.matches?.('[data-identity-wizard]')) initializeWizard(scope);
        scope.querySelectorAll?.('[data-identity-wizard]').forEach(initializeWizard);
        if (scope.matches?.('[data-identity-admin]')) initializeAdmin(scope);
        scope.querySelectorAll?.('[data-identity-admin]').forEach(initializeAdmin);
    }
    window.VNZIdentity = { init, compareImages };
    const start = () => {
        init();
        new MutationObserver(records => {
            records.forEach(record => {
                record.addedNodes.forEach(node => { if (node.nodeType === 1) init(node); });
            });
            wizards.forEach(state => {
                if (!state.root.isConnected) state.dispose();
                else if (state.stream && (state.root.closest('[aria-hidden="true"], [inert], [hidden]') || state.root.getClientRects().length === 0)) state.stopCamera();
            });
        }).observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['aria-hidden', 'hidden', 'inert'] });
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start, { once: true });
    else start();
    window.addEventListener('pagehide', () => wizards.forEach(state => state.suspend()));
    window.addEventListener('pageshow', event => {
        if (event.persisted) wizards.forEach(state => state.resume());
    });
    window.addEventListener('focus', () => wizards.forEach(state => { if (state.enabled && !state.busy) state.refresh(); }));
    document.addEventListener('visibilitychange', () => wizards.forEach(state => {
        if (document.hidden) state.stopCamera();
        else if (state.enabled && !state.busy) state.refresh();
    }));
})();
