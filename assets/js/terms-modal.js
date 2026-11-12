(() => {
    'use strict';

    const dialog = document.getElementById('termsModal');
    if (!dialog) {
        return;
    }

    let activeTrigger = null;
    let closeTimer = null;

    function prefersReducedMotion() {
        return window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    }

    function closeDialog() {
        if (!dialog.open || closeTimer !== null) {
            return;
        }

        if (prefersReducedMotion()) {
            dialog.close();
            return;
        }

        dialog.classList.add('is-closing');
        closeTimer = window.setTimeout(() => {
            if (dialog.open) {
                dialog.close();
            }
            dialog.classList.remove('is-closing');
            closeTimer = null;
        }, 170);
    }

    document.querySelectorAll('[data-open-terms]').forEach((trigger) => {
        trigger.addEventListener('click', (event) => {
            event.preventDefault();
            if (dialog.open) {
                return;
            }
            activeTrigger = trigger;
            dialog.querySelector('.terms-modal-content').scrollTop = 0;
            dialog.showModal();
        });
    });

    dialog.querySelectorAll('[data-close-terms]').forEach((button) => {
        button.addEventListener('click', closeDialog);
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog) {
            closeDialog();
        }
    });

    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        closeDialog();
    });

    dialog.addEventListener('close', () => {
        dialog.classList.remove('is-closing');
        if (closeTimer !== null) {
            window.clearTimeout(closeTimer);
            closeTimer = null;
        }
        if (activeTrigger && activeTrigger.isConnected) {
            activeTrigger.focus();
        }
    });
})();
