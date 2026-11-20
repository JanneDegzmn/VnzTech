/* Camera-only evidence for one item submission. A browser result never grants access. */
(() => {
    'use strict';
    const endpoint = '/VNZ-TECH/backend/identity_api.php';
    function initialize(root) {
        if (root.dataset.faceInitialized) return;
        root.dataset.faceInitialized = '1';
        const form = root.closest('form');
        const dialog = root.querySelector('[data-face-dialog]');
        if (!form || !dialog || typeof dialog.showModal !== 'function') return;
        const $ = name => root.querySelector(`[data-face-${name}]`) || dialog.querySelector(`[data-face-${name}]`);
        const video = $('video'), preview = $('preview'), placeholder = $('placeholder');
        const status = $('status'), consent = $('consent'), consentValue = $('consent-value');
        const camera = $('camera'), capture = $('capture'), retake = $('retake'), send = $('submit');
        const file = $('file'), nonce = $('nonce');
        const text = (en, fil) => root.dataset.language === 'fil' ? fil : en;
        let stream = null, previewUrl = '', captured = null, submitter = null, opener = null;
        let generation = 0, tokenAt = 0, busy = false, submitting = false, controller = null;
        let submitMarkup = '';
        form.dataset.faceCaptureRequired = '1';
        document.body.append(dialog);
        function message(en, fil, tone = '') {
            status.textContent = text(en, fil);
            status.dataset.tone = tone;
        }
        function stopCamera() {
            generation++;
            if (stream) stream.getTracks().forEach(track => track.stop());
            stream = null;
            video.srcObject = null;
            video.hidden = true;
            capture.hidden = true;
        }
        function clearPhoto() {
            captured = null;
            file.value = '';
            consentValue.disabled = true;
            delete form.dataset.faceCaptureReady;
            if (previewUrl) URL.revokeObjectURL(previewUrl);
            previewUrl = '';
            preview.removeAttribute('src');
            preview.hidden = true;
            retake.hidden = true;
            send.hidden = true;
            send.disabled = true;
            placeholder.hidden = false;
        }
        function close() {
            controller?.abort();
            controller = null;
            stopCamera();
            clearPhoto();
            consent.checked = false;
            busy = false;
            camera.disabled = false;
            camera.hidden = false;
            if (dialog.open) dialog.close();
            if (opener?.isConnected) opener.focus();
        }
        function restoreSubmitButton() {
            if (!submitter?.isConnected) return;
            submitter.disabled = false;
            if (submitMarkup) submitter.innerHTML = submitMarkup;
        }
        async function submitForm() {
            const request = new AbortController();
            const timer = setTimeout(() => request.abort(), 90000);
            try {
                // Keep the filled item form in this document if the server asks for a retake.
                const response = await fetch(form.action, { method: 'POST', body: new FormData(form), credentials: 'same-origin', redirect: 'follow', cache: 'no-store', signal: request.signal });
                const destination = new URL(response.url, window.location.href);
                const sameOrigin = destination.origin === window.location.origin;
                const publicReceipt = destination.pathname === '/VNZ-TECH/sell.php' && destination.searchParams.get('submitted') === '1';
                const accountReceipt = destination.pathname === '/VNZ-TECH/account/dashboard.php' && destination.searchParams.get('page') === 'submission';
                const hasReference = Boolean(destination.searchParams.get('ref'));
                const error = sameOrigin && ['/VNZ-TECH/sell.php', '/VNZ-TECH/account/dashboard.php'].includes(destination.pathname) ? destination.searchParams.get('error') : '';
                await response.body?.cancel();
                if (response.ok && sameOrigin && hasReference && (publicReceipt || accountReceipt)) {
                    window.location.assign(destination.href);
                    return;
                }
                if (error) throw Object.assign(new Error('server-retry'), { safeMessage: error.slice(0, 1000) });
                throw new Error('uncertain-submit');
            } catch (error) {
                submitting = false;
                tokenAt = 0;
                clearPhoto();
                consent.checked = false;
                camera.hidden = false;
                camera.disabled = false;
                restoreSubmitButton();
                if (!dialog.open && root.isConnected) dialog.showModal();
                if (error.safeMessage) {
                    status.textContent = error.safeMessage + ' ' + text('Your item details are kept. Check them or take another selfie, then try again.', 'Nandito pa rin ang item details mo. I-check o kunan ulit ang selfie, tapos subukan ulit.');
                    status.dataset.tone = 'error';
                } else {
                    message('We could not confirm whether your submission went through. Check your submissions or tracking first before trying again. Your item details are kept.', 'Hindi ma-confirm kung natuloy ang submission. I-check muna ang submissions o tracking bago subukan ulit. Nandito pa rin ang item details mo.', 'error');
                }
                status.focus();
            } finally { clearTimeout(timer); }
        }
        async function refreshToken() {
            if (tokenAt && Date.now() - tokenAt < 9 * 60 * 1000) return;
            controller?.abort();
            controller = new AbortController();
            const timer = setTimeout(() => controller?.abort(), 15000);
            try {
                const body = new URLSearchParams({ action: 'face_token', context: root.dataset.context, csrf_token: form.querySelector('[name="csrf_token"]').value });
                const response = await fetch(endpoint, { method: 'POST', body, credentials: 'same-origin', cache: 'no-store', signal: controller.signal });
                const result = await response.json();
                if (!response.ok || !result.success || result.mode !== 'face_submission_v6' || typeof result.face_nonce !== 'string' || !result.face_nonce) {
                    throw new Error('capture-unavailable');
                }
                nonce.value = result.face_nonce;
                if (typeof result.csrf_token === 'string' && result.csrf_token) form.querySelector('[name="csrf_token"]').value = result.csrf_token;
                tokenAt = Date.now();
            } finally { clearTimeout(timer); controller = null; }
        }
        async function openCamera() {
            if (busy || submitting || !dialog.open) return;
            stopCamera();
            clearPhoto();
            const ticket = generation;
            busy = true;
            camera.disabled = true;
            message('Getting your camera ready...', 'Inihahanda ang camera...');
            try {
                await refreshToken();
                if (!dialog.open || ticket !== generation) return;
                if (!window.isSecureContext || !navigator.mediaDevices?.getUserMedia) throw new Error('secure-context');
                const next = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 960 } }, audio: false });
                if (!dialog.open || ticket !== generation || document.hidden) { next.getTracks().forEach(track => track.stop()); return; }
                stream = next;
                video.srcObject = stream;
                await video.play();
                if (!dialog.open || ticket !== generation) { stopCamera(); return; }
                video.hidden = false;
                placeholder.hidden = true;
                camera.hidden = true;
                capture.hidden = false;
                message('Keep one face in the frame, then take your selfie.', 'Isang mukha lang sa frame, tapos kunan ng selfie.');
                capture.focus();
            } catch (error) {
                if (!dialog.open || ticket !== generation) return;
                stopCamera();
                camera.hidden = false;
                const denied = error.name === 'NotAllowedError' || error.name === 'PermissionDeniedError';
                const noDevice = error.name === 'NotFoundError' || error.name === 'NotReadableError';
                if (denied) message('Camera access was blocked. Allow camera permission in your browser, then try again.', 'Naka-block ang camera. I-allow ang camera sa browser, tapos subukan ulit.', 'error');
                else if (noDevice) message('The camera is unavailable or being used by another app. Check it and try again.', 'Hindi available ang camera o may ibang app na gumagamit. I-check, tapos subukan ulit.', 'error');
                else if (error.message === 'secure-context') message('Open this page through HTTPS (or localhost on this device) to use the camera.', 'Buksan ito sa HTTPS (o localhost sa device na ito) para gumana ang camera.', 'error');
                else message('The face capture service is unavailable. Try opening the camera again. Your item details are still here.', 'Hindi available ang face capture. Subukang buksan ulit ang camera. Nandito pa rin ang item details mo.', 'error');
                status.focus();
            } finally { busy = false; camera.disabled = false; }
        }
        camera.addEventListener('click', openCamera);
        retake.addEventListener('click', openCamera);
        capture.addEventListener('click', async () => {
            if (busy || !stream || !video.videoWidth || !video.videoHeight) return;
            busy = true;
            capture.disabled = true;
            const ticket = generation;
            const canvas = document.createElement('canvas');
            const ratio = Math.min(1, 960 / Math.max(video.videoWidth, video.videoHeight));
            canvas.width = Math.max(1, Math.round(video.videoWidth * ratio));
            canvas.height = Math.max(1, Math.round(video.videoHeight * ratio));
            try {
                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
                const blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', .9));
                if (!dialog.open || ticket !== generation) return;
                if (!blob || !blob.size) throw new Error('empty-capture');
                clearPhoto();
                captured = new File([blob], 'submission-selfie.jpg', { type: 'image/jpeg' });
                previewUrl = URL.createObjectURL(blob);
                preview.src = previewUrl;
                preview.hidden = false;
                placeholder.hidden = true;
                stopCamera();
                camera.hidden = true;
                retake.hidden = false;
                send.hidden = false;
                send.disabled = !consent.checked;
                message('Selfie captured. The server checks the photo when you submit.', 'Nakuha na ang selfie. Sa server iche-check ang photo pag nag-submit ka.');
                (consent.checked ? send : consent).focus();
            } catch {
                message('The photo could not be captured. Please try again.', 'Hindi nakuha ang photo. Subukan ulit.', 'error');
            } finally { busy = false; capture.disabled = false; canvas.width = canvas.height = 0; }
        });
        consent.addEventListener('change', () => { send.disabled = !captured || !consent.checked || busy; });
        send.addEventListener('click', async () => {
            if (busy || submitting || !captured || !consent.checked) return;
            busy = true;
            send.disabled = true;
            try {
                await refreshToken();
                if (!dialog.open || !captured || !consent.checked) return;
                const transfer = new DataTransfer();
                transfer.items.add(captured);
                file.files = transfer.files;
                consentValue.disabled = false;
                form.dataset.faceCaptureReady = '1';
                stopCamera();
                dialog.close();
                // Preserve browser validation and the form's existing submit handlers.
                form.requestSubmit(submitter?.isConnected && !submitter.disabled ? submitter : undefined);
                if (!submitting) {
                    delete form.dataset.faceCaptureReady;
                    consentValue.disabled = true;
                    file.value = '';
                    opener?.focus();
                }
            } catch {
                message('Unable to prepare your submission. Open the camera and try again. Your item details are kept.', 'Hindi maihanda ang submission. Buksan ulit ang camera. Nandito pa rin ang item details mo.', 'error');
                status.focus();
            } finally { busy = false; send.disabled = !captured || !consent.checked; }
        });
        document.addEventListener('submit', event => {
            if (event.target !== form || event.defaultPrevented) return;
            if (submitting) { event.preventDefault(); return; }
            if (form.dataset.faceCaptureReady === '1' && file.files.length === 1 && !consentValue.disabled) {
                event.preventDefault();
                submitting = true;
                submitForm();
                return;
            }
            event.preventDefault();
            if (dialog.open) return;
            if (!form.checkValidity()) { form.reportValidity(); return; }
            clearPhoto();
            consent.checked = false;
            submitter = event.submitter || form.querySelector('[type="submit"]');
            submitMarkup = submitter?.innerHTML || '';
            opener = event.submitter || document.activeElement;
            camera.hidden = false;
            message('Your item details are ready. Open the camera to take your selfie.', 'Ready na ang item details mo. Buksan ang camera para sa selfie.');
            dialog.showModal();
            camera.focus();
        });
        $('close').addEventListener('click', close);
        $('cancel').addEventListener('click', close);
        dialog.addEventListener('cancel', event => { event.preventDefault(); close(); });
        dialog.addEventListener('close', () => { if (!submitting) stopCamera(); });
        form.addEventListener('reset', close);
        document.addEventListener('visibilitychange', () => { if (document.hidden && !submitting) close(); });
        window.addEventListener('pagehide', () => { stopCamera(); clearPhoto(); controller?.abort(); });
        window.addEventListener('pageshow', event => {
            if (!event.persisted) return;
            submitting = false;
            tokenAt = 0;
            close();
            restoreSubmitButton();
        });
        const observer = new MutationObserver(() => {
            if (root.isConnected) return;
            close();
            dialog.remove();
            observer.disconnect();
        });
        observer.observe(document.documentElement, { childList: true, subtree: true });
    }
    document.querySelectorAll('[data-submission-face-capture]').forEach(initialize);
})();
