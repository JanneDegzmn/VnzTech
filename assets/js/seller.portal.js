document.addEventListener('DOMContentLoaded', () => {
    const portalContent = document.querySelector('#portal-content');

    if (!portalContent) {
        console.error('VNZ TECH: #portal-content not found.');
        return;
    }

    const dashboardContent = portalContent.innerHTML;

    let currentPage = 'dashboard';

    function setActiveNav(page) {
        document
            .querySelectorAll('.sidebar-nav .nav-item[data-page]')
            .forEach((link) => {
                link.classList.toggle(
                    'active',
                    link.dataset.page === page
                );
            });
    }

    function updateUrl(url, page) {
        history.pushState(
            {
                page: page
            },
            '',
            url
        );
    }

    function scrollTop() {
        window.scrollTo({
            top: 0,
            behavior: 'smooth'
        });
    }

    async function loadSubmissions(url, updateHistory = true) {
        try {
            portalContent.classList.add('portal-loading');

            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (response.status === 401) {
                window.location.href =
                    '/VNZ-TECH/account/login.php';

                return;
            }

            if (!response.ok) {
                throw new Error(
                    `HTTP ${response.status}`
                );
            }

            const html = await response.text();

            if (
                html === 'SESSION_EXPIRED' ||
                html === 'ACCOUNT_UNAVAILABLE'
            ) {
                window.location.href =
                    '/VNZ-TECH/account/login.php';

                return;
            }

            if (
                html === 'LOAD_ERROR' ||
                html.trim() === ''
            ) {
                throw new Error(
                    'Empty submission response.'
                );
            }

            portalContent.innerHTML = html;

            currentPage = 'submissions';

            setActiveNav('submissions');

            if (updateHistory) {
                updateUrl(
                    url,
                    'submissions'
                );
            }

            scrollTop();

        } catch (error) {
            console.error(
                'VNZ TECH Seller Portal:',
                error
            );

            portalContent.innerHTML = `
                <div class="portal-error">

                    <div class="portal-error-icon">
                        !
                    </div>

                    <h2>
                        Unable to load this page.
                    </h2>

                    <p>
                        Something went wrong while loading
                        your submissions.
                    </p>

                    <button
                        type="button"
                        data-portal-retry="submissions"
                    >
                        TRY AGAIN
                    </button>

                </div>
            `;

        } finally {
            portalContent.classList.remove(
                'portal-loading'
            );
        }
    }

    function loadDashboard(updateHistory = true) {
        portalContent.innerHTML = dashboardContent;

        currentPage = 'dashboard';

        setActiveNav('dashboard');

        if (updateHistory) {
            updateUrl(
                '/VNZ-TECH/account/dashboard.php',
                'dashboard'
            );
        }

        scrollTop();
    }

    /*
    |--------------------------------------------------------------------------
    | GLOBAL CLICK HANDLER
    |--------------------------------------------------------------------------
    |
    | We intentionally listen on DOCUMENT instead of attaching
    | listeners individually to navigation links.
    |
    */

    document.addEventListener(
        'click',
        (event) => {

            const navLink =
                event.target.closest(
                    '.sidebar-nav .nav-item[data-page]'
                );

            if (!navLink) {
                return;
            }

            const page =
                navLink.dataset.page;

            const url =
                navLink.dataset.url;

            if (!page || !url) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            if (page === 'dashboard') {
                loadDashboard(true);
                return;
            }

            if (page === 'submissions') {
                loadSubmissions(
                    url,
                    true
                );
                return;
            }
        }
    );

    /*
    |--------------------------------------------------------------------------
    | SUBMISSION FILTERS
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {

            const filterLink =
                event.target.closest(
                    '[data-submission-filter]'
                );

            if (!filterLink) {
                return;
            }

            event.preventDefault();
            event.stopPropagation();

            const status =
                filterLink.dataset.submissionFilter;

            const url =
                '/VNZ-TECH/account/submissions.php' +
                '?status=' +
                encodeURIComponent(status);

            loadSubmissions(
                url,
                true
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | RETRY
    |--------------------------------------------------------------------------
    */

    document.addEventListener(
        'click',
        (event) => {

            const retryButton =
                event.target.closest(
                    '[data-portal-retry="submissions"]'
                );

            if (!retryButton) {
                return;
            }

            loadSubmissions(
                '/VNZ-TECH/account/submissions.php' +
                window.location.search,
                false
            );
        }
    );

    /*
    |--------------------------------------------------------------------------
    | BROWSER BACK / FORWARD
    |--------------------------------------------------------------------------
    */

    window.addEventListener(
        'popstate',
        (event) => {

            const page =
                event.state?.page;

            if (page === 'submissions') {

                loadSubmissions(
                    window.location.pathname +
                    window.location.search,
                    false
                );

                return;
            }

            loadDashboard(false);
        }
    );

    /*
    |--------------------------------------------------------------------------
    | INITIAL STATE
    |--------------------------------------------------------------------------
    */

    const path =
        window.location.pathname;

    if (
        path ===
        '/VNZ-TECH/account/submissions.php'
    ) {

        loadSubmissions(
            window.location.pathname +
            window.location.search,
            false
        );

    } else {

        currentPage = 'dashboard';

        setActiveNav(
            'dashboard'
        );

        history.replaceState(
            {
                page: 'dashboard'
            },
            '',
            '/VNZ-TECH/account/dashboard.php'
        );
    }
});