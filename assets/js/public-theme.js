(function () {
    'use strict';

    const storageKey = 'vnz-public-theme';
    const root = document.documentElement;

    function currentTheme() {
        return root.getAttribute('data-public-theme') === 'light' ? 'light' : 'dark';
    }

    function updateButton(button) {
        const lightMode = currentTheme() === 'light';
        button.setAttribute('aria-label', `Switch to ${lightMode ? 'dark' : 'light'} mode`);
        button.setAttribute('aria-pressed', String(lightMode));
        button.querySelector('[data-theme-icon]').textContent = lightMode ? '☾' : '☼';
        button.querySelector('[data-theme-label]').textContent = lightMode ? 'Dark mode' : 'Light mode';
    }

    function setTheme(theme, button) {
        const nextTheme = theme === 'light' ? 'light' : 'dark';
        root.setAttribute('data-public-theme', nextTheme);
        updateButton(button);

        try {
            localStorage.setItem(storageKey, nextTheme);
        } catch (error) {
            console.error('Unable to save the public theme preference.', error);
        }
    }

    function initialize() {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'public-theme-toggle';
        button.innerHTML = '<span aria-hidden="true" data-theme-icon></span><span data-theme-label></span>';
        document.body.append(button);

        if (document.querySelector('.seller-page')) {
            document.body.classList.add('public-theme-has-chat');
        }

        updateButton(button);
        button.addEventListener('click', function () {
            setTheme(currentTheme() === 'light' ? 'dark' : 'light', button);
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize, { once: true });
    } else {
        initialize();
    }
})();
