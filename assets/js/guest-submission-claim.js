(function () {
    'use strict';
    const root = document.querySelector('[data-guest-claim]');
    if (!root) return;

    const dialog = root.querySelector('[data-guest-claim-dialog]');
    const primary = root.querySelector('[data-guest-claim-primary]');
    const resend = root.querySelector('[data-guest-claim-resend]');
    const feedback = root.querySelector('[data-guest-claim-feedback]');
    const later = root.querySelector('[data-guest-claim-later]');
    const openButton = root.querySelector('[data-guest-claim-open]');
    const isFilipino = document.documentElement.dataset.sellerLanguage === 'fil';
    const t = (english, filipino) => isFilipino ? filipino : english;
    let phase = root.dataset.phase;
    let guestFlow = root.dataset.guestFlow !== '0';
    let verificationResult = root.dataset.verificationResult || '';
    let feedbackId = root.dataset.feedbackId || '';
    const shownNotices = new Set();
    let verifying = false;
    let busy = false;
    let autoOpen = root.dataset.autoOpen === '1';
    let lastSent = Number(root.dataset.lastSent) || 0;
    let lastFocus = null;
    let pollTimer = null;
    let cooldownTimer = null;
    let statusRequest = null;

    const setText = (selector, value) => { root.querySelector(selector).textContent = value; };
    setText('[data-guest-claim-eyebrow]', t('ONE ACCOUNT, ALL YOUR UPDATES', 'LAHAT NG UPDATES MO, NASA ISANG ACCOUNT'));
    [t('Confirm', 'Confirm'), t('Verify email', 'I-verify'), t('Linked', 'Naka-link')].forEach((label, index) => {
        setText(`[data-guest-claim-step-label="${index + 1}"]`, label);
    });

    function message(text, error = false) {
        feedback.textContent = text;
        feedback.classList.toggle('is-error', error);
        feedback.setAttribute('role', error ? 'alert' : 'status');
    }

    function updateCooldown() {
        const seconds = Math.max(0, 60 - Math.floor(Date.now() / 1000 - lastSent));
        resend.disabled = busy || seconds > 0;
        resend.textContent = seconds > 0
            ? t(`Resend available in ${seconds}s`, `Resend ulit after ${seconds}s`)
            : t('Resend verification email', 'I-send ulit yung verification email');
        if (seconds === 0 && cooldownTimer !== null) {
            window.clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
    }

    function startCooldown() {
        if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
        cooldownTimer = window.setInterval(updateCooldown, 1000);
        updateCooldown();
    }

    function render() {
        const linked = phase === 'linked';
        const verified = phase === 'verified';
        const waiting = phase === 'verify' && verifying;
        const invalid = verificationResult === 'invalid';
        let title;
        let copy;
        if (linked || verified) {
            title = invalid
                ? t('Your email is already verified', 'Verified na yung email mo')
                : t('Email verified successfully', 'Verified na yung email mo!');
            copy = linked
                ? t('Your email is verified, and your guest submission is now linked to this seller account. You can follow its updates from My Submissions.', 'Verified na yung email mo, at naka-link na rin yung guest submission mo sa account na to. Check mo sa Submissions ko yung updates.')
                : guestFlow
                    ? t('Your email is verified. Open My Submissions to see any submissions linked to this account. If one is still missing, you can keep using its guest tracking reference.', 'Verified na yung email mo. Check mo sa Submissions ko yung mga naka-link sa account mo. Kung may kulang pa, pwede mo pa ring gamitin yung guest tracking reference nun.')
                    : t('Your email address has been confirmed. You can continue using your seller account.', 'Verified na yung email mo. Okay na, tuloy ka na sa seller account mo.');
        } else if (waiting) {
            title = t('One last step: verify your email', 'Verify mo na lang yung email mo');
            copy = t('Open the latest VNZ TECH email, then click Verify my email on the confirmation page. Return here to see your verification and submission status.', 'Buksan mo yung latest email ng VNZ TECH, tapos click Verify my email sa confirmation page. Balik ka dito para ma-check yung email at submission status mo.');
        } else if (invalid) {
            title = t('This verification link is no longer valid', 'Hindi na valid yung verification link');
            copy = t('The link may have expired, already been used, or been replaced by a newer email. Open the latest verification email or request a new link below.', 'Baka expired, nagamit na, o napalitan na yung link. Check mo yung latest verification email o mag-request ng bagong link dito.');
        } else {
            title = t('Claim your guest submission', 'I-claim mo yung guest submission mo');
            copy = t('Submitted before signing up? Verify the same email you used as a guest so we can securely link eligible submissions to this account.', 'Nag-submit ka nung guest ka pa? I-verify mo yung same email na ginamit mo nun para ma-link sa account mo yung mga submission na pwede mong i-claim.');
        }
        setText('[data-guest-claim-title]', title);
        setText('[data-guest-claim-copy]', copy);
        setText('[data-guest-claim-banner-title]', linked
            ? t('Your guest submission is linked', 'Naka-link na yung guest submission mo')
            : verified ? t('Your email is verified', 'Verified na yung email mo')
                : guestFlow ? t('Bring your guest submission with you', 'I-link mo yung guest submission sa account mo')
                    : t('Verify your email address', 'I-verify mo yung email mo'));
        setText('[data-guest-claim-banner-copy]', linked
            ? t('You can now follow its updates from your seller account.', 'Pwede mo nang i-check yung updates sa seller account mo.')
            : verified ? t('View your submissions or continue tracking your guest submission.', 'Check mo yung submissions mo o gamitin yung guest tracking.')
                : guestFlow ? t('Verify your email to link eligible guest submissions to your account.', 'I-verify mo yung email para ma-link yung mga guest submission na pwede mong i-claim.')
                    : t('Open the latest verification email or request a new link.', 'Check mo yung latest verification email o mag-request ng bagong link.'));
        primary.textContent = linked || verified
            ? guestFlow ? t('View my submissions', 'Check submissions ko') : t('Continue to dashboard', 'Balik sa dashboard')
            : waiting ? t("I've verified my email", 'Na-verify ko na yung email ko')
                : invalid ? t('Send a new verification email', 'I-send ulit yung verification email')
                    : t('Yes, claim it', 'Oo, i-claim ko');
        later.textContent = linked || verified ? t('Close', 'Close') : t('Maybe later', 'Mamaya na');
        openButton.textContent = linked || verified ? t('View update', 'Check update') : t('Continue', 'Tuloy');
        resend.hidden = !waiting;
        root.querySelector('[data-guest-claim-steps]').hidden = !guestFlow;
        root.querySelectorAll('[data-guest-claim-step]').forEach(step => {
            step.classList.toggle('is-active', Number(step.dataset.guestClaimStep) <= (linked ? 3 : waiting || verified ? 2 : 1));
        });
        primary.disabled = busy;
        updateCooldown();
    }

    async function request(action = null) {
        const options = {credentials: 'same-origin', cache: 'no-store', headers: {'Accept': 'application/json'}};
        if (action !== null) {
            options.method = 'POST';
            options.body = new FormData();
            options.body.set('action', action);
            options.body.set('csrf_token', root.dataset.csrf);
            options.body.set('feedback_id', feedbackId);
            options.body.set('presented_phase', phase);
        }
        const response = await fetch('/VNZ-TECH/backend/guest_submission_onboarding_status.php', options);
        const data = await response.json();
        if (!response.ok || !data.success) throw new Error(data.message || t('Unable to check your submission.', 'Hindi ma-check yung submission ngayon. Try ulit.'));
        return data;
    }

    async function refreshStatus(showWaiting = false) {
        if (statusRequest !== null) return statusRequest;
        statusRequest = (async () => {
            try {
                const data = await request();
                if (!data.available) { closeDialog(); root.hidden = true; return; }
                const previousPhase = phase;
                const previousFeedbackId = feedbackId;
                phase = data.phase;
                guestFlow = data.guest_flow ?? guestFlow;
                verificationResult = data.verification_result ?? verificationResult;
                feedbackId = data.feedback_id || feedbackId;
                if (verificationResult === 'invalid' && feedbackId !== previousFeedbackId) {
                    verifying = false;
                    message('');
                }
                lastSent = Number(data.last_sent_at) || lastSent;
                render();
                if (phase !== 'verify') {
                    message('');
                    stopPolling();
                } else if (showWaiting) {
                    message(t('Not verified yet. Open the latest verification email, then return here.', 'Hindi pa verified. Check mo yung latest verification email, tapos balik ka dito.'));
                }
                if ((previousPhase === 'verify' && phase !== 'verify') || (phase === 'linked' && previousPhase !== 'linked')) {
                    window.dispatchEvent(new Event('vnz:guest-claim-updated'));
                    queueAutoOpen();
                } else if (data.auto_show && !shownNotices.has(noticeKey())) {
                    queueAutoOpen();
                }
                maybeAutoOpen();
            } catch (error) {
                if (dialog.open || showWaiting) message(error.message, true);
            }
        })();
        try { await statusRequest; } finally { statusRequest = null; }
    }

    function stopPolling() {
        if (pollTimer !== null) window.clearInterval(pollTimer);
        pollTimer = null;
    }

    function startPolling() {
        stopPolling();
        pollTimer = window.setInterval(() => {
            if (dialog.open && phase === 'verify' && !document.hidden) refreshStatus();
        }, 10000);
    }

    function blocked() {
        return Boolean(document.querySelector('[data-seller-2fa-reminder], .seller-alert-modal.is-open'))
            || [...document.querySelectorAll('dialog[open]')].some(element => element !== dialog);
    }

    function openDialog() {
        if (root.hidden || dialog.open || blocked()) return;
        autoOpen = false;
        shownNotices.add(noticeKey());
        lastFocus = document.activeElement;
        dialog.showModal();
        primary.focus();
        request('dismiss').catch(() => {});
        observer.disconnect();
        if (phase === 'verify') startPolling();
    }

    function closeDialog() {
        const wasOpen = dialog.open;
        if (dialog.open) dialog.close();
        stopPolling();
        if (cooldownTimer !== null) window.clearInterval(cooldownTimer);
        cooldownTimer = null;
        if (wasOpen && lastFocus?.isConnected) lastFocus.focus();
    }

    function maybeAutoOpen() {
        const content = document.querySelector('#contentArea');
        if (autoOpen && content?.children.length && !content.querySelector('.content-loading')) openDialog();
    }

    function noticeKey() {
        return feedbackId + ':' + (phase === 'verify' ? 'verify' : 'verified');
    }

    function queueAutoOpen() {
        if (shownNotices.has(noticeKey())) return;
        autoOpen = true;
        observer.observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'open']});
        maybeAutoOpen();
    }

    async function sendVerification(force = false) {
        if (busy) return;
        verifying = true;
        render();
        startPolling();
        if (!force && Date.now() / 1000 - lastSent < 60) {
            message(t('A verification email was already sent. Check your inbox and spam folder.', 'Na-send na yung verification email. Check mo yung inbox at spam folder.'));
            startCooldown();
            return;
        }
        busy = true;
        render();
        message(t('Sending verification email…', 'Sine-send yung verification email…'));
        try {
            const body = new FormData();
            body.set('csrf_token', root.dataset.csrf);
            const response = await fetch('/VNZ-TECH/backend/request_email_verification.php', {
                method: 'POST', body, credentials: 'same-origin', headers: {'Accept': 'application/json'}
            });
            const data = await response.json();
            if (!response.ok || !data.success) {
                if (response.status === 429) { lastSent = Math.floor(Date.now() / 1000); startCooldown(); }
                throw new Error(data.message || t('Unable to send verification email.', 'Hindi ma-send yung verification email. Try ulit.'));
            }
            if (data.email_verified === true) {
                await refreshStatus();
                return;
            }
            lastSent = Math.floor(Date.now() / 1000);
            message(t('Verification email sent. Check your inbox or spam folder, then come back here.', 'Na-send na yung verification email. Check mo yung inbox o spam folder, tapos balik ka dito.'));
            startCooldown();
            await refreshStatus();
        } catch (error) {
            message(error.message, true);
        } finally {
            busy = false;
            render();
        }
    }

    primary.addEventListener('click', async () => {
        if (busy) return;
        if (phase === 'linked' || phase === 'verified') {
            busy = true;
            render();
            try {
                await request('acknowledge');
                window.location.assign('/VNZ-TECH/account/dashboard.php?page=' + (guestFlow ? 'submissions' : 'dashboard'));
            } catch (error) { busy = false; render(); message(error.message, true); }
        } else if (verifying) {
            await refreshStatus(true);
        } else {
            await sendVerification();
        }
    });
    resend.addEventListener('click', () => sendVerification(true));
    openButton.addEventListener('click', () => { render(); openDialog(); if (verifying) startCooldown(); refreshStatus(); });
    later.addEventListener('click', closeDialog);
    root.querySelector('[data-guest-claim-close]').addEventListener('click', closeDialog);
    dialog.addEventListener('cancel', event => { event.preventDefault(); closeDialog(); });
    dialog.addEventListener('click', event => { if (event.target === dialog) closeDialog(); });
    window.addEventListener('focus', () => { if (!root.hidden) refreshStatus(); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden && !root.hidden) refreshStatus(); });

    const observer = new MutationObserver(maybeAutoOpen);
    if (autoOpen) observer.observe(document.body, {childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'open']});
    render();
    refreshStatus();
})();
