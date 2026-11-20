/* Private submission photo viewer; scoped to the current AJAX review fragment. */
(() => {
    'use strict';
    const panel = document.currentScript?.closest('[data-submission-face]');
    if (!panel || panel.dataset.selfieViewerBound) return;
    const find = name => panel.querySelector(`[data-face-view-${name}]`);
    const opener = find('open'), dialog = panel.querySelector('[data-face-viewer]');
    const closeButton = find('close'), zoomButton = find('zoom'), stage = find('stage');
    const imageButton = find('image-button'), image = find('image');
    const status = find('status'), hint = find('hint');
    if (!opener || !dialog || !image || typeof dialog.showModal !== 'function') return;
    panel.dataset.selfieViewerBound = '1';

    const events = new AbortController();
    let request = null, objectUrl = '', timer = 0, generation = 0;
    let previousOverflow = '', scrollLocked = false, disposed = false;
    let drag = null, suppressPointerClick = false;

    function stopDrag(suppressClick = false) {
        const previous = drag;
        drag = null;
        dialog.classList.remove('is-dragging');
        if (suppressClick && previous?.moved) suppressPointerClick = true;
        if (previous && imageButton.hasPointerCapture(previous.id)) {
            imageButton.releasePointerCapture(previous.id);
        }
    }

    function panTo(left, top) {
        stage.scrollLeft = Math.max(0, Math.min(left, stage.scrollWidth - stage.clientWidth));
        stage.scrollTop = Math.max(0, Math.min(top, stage.scrollHeight - stage.clientHeight));
    }

    function resetZoom() {
        const interruptedPointer = Boolean(drag);
        stopDrag();
        suppressPointerClick = suppressPointerClick || interruptedPointer;
        dialog.classList.remove('is-zoomed');
        imageButton.style.removeProperty('width');
        imageButton.style.removeProperty('height');
        zoomButton.textContent = 'Zoom in';
        zoomButton.setAttribute('aria-pressed', 'false');
        imageButton.setAttribute('aria-label', 'Zoom in on submission selfie');
        hint.textContent = 'Click photo to zoom';
        stage.scrollTo(0, 0);
    }

    function clearPhoto() {
        generation++;
        request?.abort();
        request = null;
        clearTimeout(timer);
        image.removeAttribute('src');
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = '';
        imageButton.hidden = true;
        zoomButton.disabled = true;
        resetZoom();
        suppressPointerClick = false;
    }

    function releaseViewer() {
        const restoreFocus = scrollLocked;
        clearPhoto();
        if (scrollLocked) {
            document.body.style.overflow = previousOverflow;
            scrollLocked = false;
        }
        if (restoreFocus && panel.isConnected && opener.getClientRects().length) opener.focus({ preventScroll: true });
    }

    function closeViewer() {
        if (dialog.open) dialog.close();
        releaseViewer();
    }

    function showError() {
        clearPhoto();
        dialog.dataset.state = 'error';
        status.hidden = false;
        status.textContent = 'The selfie could not be loaded. It may have expired or your access changed. Close this viewer and try again.';
        hint.textContent = 'Photo unavailable';
    }

    async function openViewer() {
        if (dialog.open || disposed) return;
        clearPhoto();
        dialog.dataset.state = 'loading';
        status.hidden = false;
        status.textContent = 'Loading selfie...';
        hint.textContent = 'Loading photo';
        previousOverflow = document.body.style.overflow;
        dialog.showModal();
        document.body.style.overflow = 'hidden';
        scrollLocked = true;
        closeButton.focus({ preventScroll: true });
        const version = generation;
        const controller = new AbortController();
        request = controller;
        timer = setTimeout(() => controller.abort(), 20000);
        try {
            const src = new URL(opener.dataset.faceViewSrc, location.href);
            if (src.origin !== location.origin || src.pathname !== '/VNZ-TECH/backend/submission_face_photo.php') throw new Error('Invalid photo source');
            const response = await fetch(src, {
                credentials: 'same-origin', cache: 'no-store', redirect: 'error',
                referrerPolicy: 'no-referrer', signal: controller.signal
            });
            if (!response.ok || !/^image\/jpeg(?:;|$)/i.test(response.headers.get('Content-Type') || '')) throw new Error('Photo unavailable');
            const blob = await response.blob();
            if (!blob.size || blob.size > 8 * 1024 * 1024) throw new Error('Invalid photo');
            if (version !== generation || !dialog.open || disposed) return;
            objectUrl = URL.createObjectURL(blob);
            image.src = objectUrl;
            await image.decode();
            if (version !== generation || !dialog.open || disposed) return;
            clearTimeout(timer);
            request = null;
            dialog.dataset.state = 'ready';
            status.hidden = true;
            imageButton.hidden = false;
            zoomButton.disabled = false;
            hint.textContent = 'Click photo to zoom';
        } catch {
            if (version === generation && dialog.open && !disposed) showError();
        }
    }

    function toggleZoom() {
        if (!dialog.open || dialog.dataset.state !== 'ready') return;
        if (dialog.classList.contains('is-zoomed')) {
            resetZoom();
            return;
        }
        const rect = image.getBoundingClientRect();
        imageButton.style.width = `${Math.round(rect.width * 2)}px`;
        imageButton.style.height = `${Math.round(rect.height * 2)}px`;
        dialog.classList.add('is-zoomed');
        zoomButton.textContent = 'Reset zoom';
        zoomButton.setAttribute('aria-pressed', 'true');
        imageButton.setAttribute('aria-label', 'Reset selfie zoom; drag or use arrow keys to move photo');
        hint.textContent = 'Drag photo to look around. Arrow keys also work.';
        panTo((stage.scrollWidth - stage.clientWidth) / 2, (stage.scrollHeight - stage.clientHeight) / 2);
    }

    function on(target, name, callback) {
        target.addEventListener(name, callback, { signal: events.signal });
    }
    on(opener, 'click', openViewer);
    on(closeButton, 'click', closeViewer);
    on(zoomButton, 'click', toggleZoom);
    on(imageButton, 'click', event => {
        // A pointer drag ends with a native click; keyboard activation has detail 0.
        if (suppressPointerClick && event.detail !== 0) {
            suppressPointerClick = false;
            event.preventDefault();
            event.stopPropagation();
            return;
        }
        suppressPointerClick = false;
        toggleZoom();
    });
    image.draggable = false;
    on(imageButton, 'dragstart', event => event.preventDefault());
    on(imageButton, 'pointerdown', event => {
        if (drag || !event.isPrimary || event.button !== 0) return;
        suppressPointerClick = false;
        if (!dialog.classList.contains('is-zoomed')) return;
        drag = {
            id: event.pointerId, x: event.clientX, y: event.clientY,
            left: stage.scrollLeft, top: stage.scrollTop, moved: false
        };
        // Capture on the image button so a simple click/tap still activates it.
        imageButton.setPointerCapture(event.pointerId);
        imageButton.focus({ preventScroll: true });
    });
    on(imageButton, 'pointermove', event => {
        if (!drag || drag.id !== event.pointerId) return;
        const dx = event.clientX - drag.x, dy = event.clientY - drag.y;
        if (!drag.moved && Math.hypot(dx, dy) < 6) return;
        drag.moved = true;
        dialog.classList.add('is-dragging');
        panTo(drag.left - dx, drag.top - dy);
        event.preventDefault();
    });
    for (const name of ['pointerup', 'pointercancel', 'lostpointercapture']) {
        on(imageButton, name, event => {
            if (drag?.id === event.pointerId) stopDrag(true);
        });
    }
    on(imageButton, 'keydown', event => {
        if (!dialog.classList.contains('is-zoomed') || event.altKey || event.ctrlKey || event.metaKey) return;
        const movement = {
            ArrowLeft: [-1, 0], ArrowRight: [1, 0],
            ArrowUp: [0, -1], ArrowDown: [0, 1]
        }[event.key];
        if (!movement) return;
        event.preventDefault();
        const step = event.shiftKey ? 144 : 48;
        panTo(stage.scrollLeft + movement[0] * step, stage.scrollTop + movement[1] * step);
    });
    on(dialog, 'close', () => { if (!dialog.open) releaseViewer(); });
    on(dialog, 'cancel', event => {
        event.preventDefault();
        event.stopPropagation();
        closeViewer();
    });
    on(dialog, 'keydown', event => {
        if (event.key === 'Escape') {
            event.preventDefault();
            event.stopPropagation();
            closeViewer();
        }
    });
    let backdropDown = false;
    const outside = event => {
        const rect = dialog.getBoundingClientRect();
        return event.clientX < rect.left || event.clientX > rect.right || event.clientY < rect.top || event.clientY > rect.bottom;
    };
    on(dialog, 'pointerdown', event => { backdropDown = event.target === dialog && outside(event); });
    on(dialog, 'click', event => {
        if (backdropDown && event.target === dialog && outside(event)) closeViewer();
        backdropDown = false;
    });
    on(window, 'resize', () => { if (dialog.open) resetZoom(); });
    on(window, 'pagehide', closeViewer);

    const observer = new MutationObserver(() => {
        if (!panel.isConnected) {
            disposed = true;
            closeViewer();
            events.abort();
            observer.disconnect();
            return;
        }
        const modal = panel.closest('.admin-submission-modal');
        if (dialog.open && (panel.getClientRects().length === 0 || (modal && (!modal.classList.contains('is-open') || modal.getAttribute('aria-hidden') === 'true')))) closeViewer();
    });
    // Keep subtree removal observation separate from ancestor attribute targets:
    // observing body again below would otherwise replace its removal options.
    observer.observe(document, { childList: true, subtree: true });
    for (let parent = panel.parentElement; parent; parent = parent.parentElement) {
        observer.observe(parent, { attributes: true, attributeFilter: ['class', 'hidden', 'aria-hidden', 'style'] });
    }
})();
