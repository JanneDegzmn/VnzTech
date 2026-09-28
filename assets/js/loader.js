(function () {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | HOW TO USE
    |--------------------------------------------------------------------------
    | 1. Include /VNZ-TECH/includes/loader-head.php in the page <head>.
    | 2. Include /VNZ-TECH/includes/loader-body.php immediately after <body>.
    | 3. Add data-loader to any link or form that should trigger the loader.
    | 4. Use VNZLoader.show() / VNZLoader.hide() for custom JS navigation or AJAX.
    |--------------------------------------------------------------------------
    */

    var root = document.documentElement;
    var loader = document.getElementById('vnzLoader');
    var startedAt = Date.now();
    var failSafeTimer = null;

    function applyVariantFromHtml() {
        if (!loader || !root) {
            return;
        }

        var variant = (root.getAttribute('data-loader-variant') || 'dots').toLowerCase();
        var validVariants = {
            dots: 'dots',
            bar: 'bar',
            ring: 'ring'
        };

        loader.classList.remove('vnz-loader--dots', 'vnz-loader--bar', 'vnz-loader--ring');

        if (validVariants[variant]) {
            loader.classList.add('vnz-loader--' + validVariants[variant]);
        } else {
            loader.classList.add('vnz-loader--dots');
        }
    }

    function clearFailSafe() {
        if (failSafeTimer) {
            clearTimeout(failSafeTimer);
            failSafeTimer = null;
        }
    }

    function show() {
        if (!root) {
            return;
        }

        root.classList.add('vnz-loading');
        clearFailSafe();
        failSafeTimer = setTimeout(function () {
            hide();
        }, 12000);
    }

    function hide() {
        if (!root) {
            return;
        }

        root.classList.remove('vnz-loading');
        clearFailSafe();
    }

    window.VNZLoader = {
        show: show,
        hide: hide
    };

    applyVariantFromHtml();

    function hideAfterMinimumDelay() {
        var elapsed = Date.now() - startedAt;
        var delay = Math.max(0, 1000 - elapsed);

        setTimeout(function () {
            hide();
        }, delay);
    }

    document.addEventListener('DOMContentLoaded', function () {
        hideAfterMinimumDelay();
    });

    window.addEventListener('load', function () {
        hideAfterMinimumDelay();
    });

    window.addEventListener('pageshow', function (event) {
        if (event.persisted) {
            hide();
        }
    });

    document.addEventListener('click', function (event) {
        var target = event.target.closest('a[data-loader]');

        if (!target) {
            return;
        }

        if (event.defaultPrevented || event.button !== 0) {
            return;
        }

        if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
            return;
        }

        if (target.target === '_blank') {
            return;
        }

        if (target.hasAttribute('download')) {
            return;
        }

        var href = target.getAttribute('href');

        if (!href || href === '#' || href.indexOf('#') === 0) {
            return;
        }

        if (
            href.indexOf('javascript:') === 0 ||
            href.indexOf('mailto:') === 0 ||
            href.indexOf('tel:') === 0
        ) {
            return;
        }

        var url = new URL(href, window.location.href);

        if (url.origin !== window.location.origin) {
            return;
        }

        show();
    }, true);

    document.addEventListener('submit', function (event) {
        var form = event.target;

        if (!(form instanceof HTMLFormElement)) {
            return;
        }

        if (!form.matches('form[data-loader]')) {
            return;
        }

        setTimeout(function () {
            if (!event.defaultPrevented) {
                show();
            }
        }, 0);
    }, true);
});
