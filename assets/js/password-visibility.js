(() => {
    const passwordSelector = 'input[type="password"]';
    const eyeIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle></svg>';
    const eyeOffIcon = '<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M3 3l18 18M10.6 10.6a2 2 0 0 0 2.8 2.8"></path><path d="M9.9 5.2A10.7 10.7 0 0 1 12 5c6.4 0 10 7 10 7a13.7 13.7 0 0 1-3.1 3.8M6.2 6.2C3.5 8.1 2 12 2 12s3.6 7 10 7c1.4 0 2.7-.3 3.8-.8"></path></svg>';

    function enhancePasswordInput(input) {
        if (input.dataset.passwordVisibilityReady === 'true' || !input.parentNode) {
            return;
        }

        input.dataset.passwordVisibilityReady = 'true';
        input.classList.add('password-visibility-input');

        const wrapper = document.createElement('span');
        wrapper.className = 'password-input-control';
        input.parentNode.insertBefore(wrapper, input);
        wrapper.appendChild(input);

        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'password-visibility-toggle';
        button.setAttribute('aria-label', 'Show password');
        button.setAttribute('aria-pressed', 'false');
        button.title = 'Show password';
        button.innerHTML = eyeIcon + '<span>Show</span>';
        wrapper.appendChild(button);

        function setVisibility(visible) {
            input.type = visible ? 'text' : 'password';
            button.setAttribute('aria-label', visible ? 'Hide password' : 'Show password');
            button.setAttribute('aria-pressed', visible ? 'true' : 'false');
            button.title = visible ? 'Hide password' : 'Show password';
            button.innerHTML = (visible ? eyeOffIcon : eyeIcon)
                + '<span>' + (visible ? 'Hide' : 'Show') + '</span>';
        }

        button.addEventListener('click', () => {
            setVisibility(input.type === 'password');
        });

        const form = input.closest('form');
        if (form) {
            form.addEventListener('reset', () => {
                window.setTimeout(() => setVisibility(false), 0);
            });
        }
    }

    function enhancePasswordInputs(root) {
        if (root.nodeType === Node.ELEMENT_NODE && root.matches(passwordSelector)) {
            enhancePasswordInput(root);
        }
        if (root.querySelectorAll) {
            root.querySelectorAll(passwordSelector).forEach(enhancePasswordInput);
        }
    }

    enhancePasswordInputs(document);

    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            mutation.addedNodes.forEach((node) => {
                if (node.nodeType === Node.ELEMENT_NODE) {
                    enhancePasswordInputs(node);
                }
            });
        });
    });
    observer.observe(document.documentElement, { childList: true, subtree: true });
})();
