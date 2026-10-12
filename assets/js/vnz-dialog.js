(function () {
    if (window.VNZDialog) {
        return;
    }

    const queue = [];
    let activeRequest = null;
    let dialog = null;

    function createDialog() {
        if (dialog) {
            return;
        }

        dialog = document.createElement('dialog');
        dialog.className = 'vnz-dialog';
        dialog.setAttribute('aria-labelledby', 'vnzDialogTitle');
        dialog.setAttribute('aria-describedby', 'vnzDialogMessage');
        dialog.innerHTML = `
            <section class="vnz-dialog-panel">
                <span class="vnz-dialog-mark" aria-hidden="true">!</span>
                <h2 id="vnzDialogTitle"></h2>
                <p id="vnzDialogMessage"></p>
                <div class="vnz-dialog-actions"></div>
            </section>
        `;
        document.body.append(dialog);

        dialog.addEventListener('cancel', event => {
            event.preventDefault();
            finishRequest(false);
        });
        dialog.addEventListener('click', event => {
            if (event.target === dialog) {
                finishRequest(false);
            }
        });
    }

    function finishRequest(result) {
        if (!activeRequest) {
            return;
        }

        const request = activeRequest;
        activeRequest = null;
        if (dialog.open) {
            dialog.close();
        }
        request.resolve(result);
        showNextRequest();
    }

    function showNextRequest() {
        if (activeRequest || queue.length === 0) {
            return;
        }

        createDialog();
        activeRequest = queue.shift();
        const request = activeRequest;
        const title = dialog.querySelector('#vnzDialogTitle');
        const message = dialog.querySelector('#vnzDialogMessage');
        const actions = dialog.querySelector('.vnz-dialog-actions');

        dialog.classList.toggle('is-danger', request.options.danger === true);
        dialog.setAttribute('role', request.type === 'alert' ? 'alertdialog' : 'dialog');
        title.textContent = request.options.title
            || (request.type === 'confirm' ? 'Please Confirm' : 'Message');
        message.textContent = request.options.message || '';
        actions.replaceChildren();

        if (request.type === 'confirm') {
            const cancelButton = document.createElement('button');
            cancelButton.className = 'vnz-dialog-cancel';
            cancelButton.type = 'button';
            cancelButton.textContent = request.options.cancelLabel || 'Cancel';
            cancelButton.addEventListener('click', () => finishRequest(false), {once: true});
            actions.append(cancelButton);
        }

        const confirmButton = document.createElement('button');
        confirmButton.className = 'vnz-dialog-confirm';
        confirmButton.type = 'button';
        confirmButton.textContent = request.options.confirmLabel
            || (request.type === 'confirm' ? 'Confirm' : 'Close');
        confirmButton.addEventListener(
            'click',
            () => finishRequest(request.type === 'confirm'),
            {once: true}
        );
        actions.append(confirmButton);

        if (!dialog.open) {
            dialog.showModal();
        }
        (request.type === 'confirm'
            ? actions.querySelector('.vnz-dialog-cancel')
            : confirmButton
        ).focus();
    }

    function enqueue(type, options) {
        return new Promise(resolve => {
            queue.push({type, options: options || {}, resolve});
            showNextRequest();
        });
    }

    window.VNZDialog = {
        confirm(options) {
            return enqueue('confirm', options);
        },
        alert(options) {
            return enqueue('alert', options);
        }
    };

    const pendingLinks = new WeakSet();
    document.addEventListener('click', async event => {
        const link = event.target.closest('[data-vnz-confirm]');
        if (!link || pendingLinks.has(link)) {
            return;
        }

        event.preventDefault();
        pendingLinks.add(link);
        link.setAttribute('aria-disabled', 'true');
        const confirmed = await window.VNZDialog.confirm({
            title: link.dataset.vnzConfirmTitle || 'Confirm Action?',
            message: link.dataset.vnzConfirm,
            confirmLabel: link.dataset.vnzConfirmLabel || 'Continue',
            danger: link.dataset.vnzConfirmDanger === 'true'
        });
        pendingLinks.delete(link);
        link.removeAttribute('aria-disabled');

        if (confirmed && link.href) {
            window.location.assign(link.href);
        }
    });
})();
