(function () {
    'use strict';

    const root = document.documentElement;
    const preferenceId = 'sellerThemePreference';

    function currentTheme() {
        return root.dataset.sellerTheme === 'light' ? 'light' : 'dark';
    }

    function updateTheme(theme) {
        root.dataset.sellerTheme = theme === 'light' ? 'light' : 'dark';
    }

    function syncPreference(control) {
        if (control instanceof HTMLSelectElement) {
            control.value = currentTheme();
        }
    }

    function syncPreferencesIn(node) {
        if (node instanceof Element && node.matches(`#${preferenceId}`)) {
            syncPreference(node);
        }
        node.querySelectorAll?.(`#${preferenceId}`).forEach(syncPreference);
    }

    syncPreferencesIn(document);

    document.addEventListener('change', function (event) {
        if (!(event.target instanceof HTMLSelectElement) || event.target.id !== preferenceId) {
            return;
        }

        const theme = event.target.value === 'light' ? 'light' : 'dark';
        updateTheme(theme);
        event.target.value = theme;

        try {
            localStorage.setItem('vnz-seller-theme', theme);
        } catch (error) {
            console.warn('Unable to save the Seller portal theme preference.', error);
        }
    });

    const observer = new MutationObserver((records) => {
        records.forEach((record) => {
            record.addedNodes.forEach(syncPreferencesIn);
        });
    });
    observer.observe(document.body, { childList: true, subtree: true });
})();
