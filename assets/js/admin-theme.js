(function () {
    'use strict';

    const storageKey = 'vnz-admin-theme';

    function getTheme() {
        return document.documentElement.getAttribute('data-admin-theme') === 'light'
            ? 'light'
            : 'dark';
    }

    function setTheme(theme) {
        const safeTheme = theme === 'light' ? 'light' : 'dark';
        document.documentElement.setAttribute('data-admin-theme', safeTheme);

        try {
            localStorage.setItem(storageKey, safeTheme);
        } catch (error) {
            console.error('Unable to save the admin theme preference.', error);
        }
    }

    function syncSelector(root) {
        const selector = root.querySelector('[data-admin-theme-select]');
        if (selector) {
            selector.value = getTheme();
        }
    }

    document.addEventListener('change', function (event) {
        if (event.target.matches('[data-admin-theme-select]')) {
            setTheme(event.target.value);
        }
    });

    const contentArea = document.getElementById('contentArea');
    if (contentArea) {
        new MutationObserver(function () {
            syncSelector(contentArea);
        }).observe(contentArea, { childList: true, subtree: true });
    }

    document.addEventListener('DOMContentLoaded', function () {
        syncSelector(document);
    });
})();
