(() => {
    'use strict';
    if (window.VNZSiteStatus) return;
    const script = document.currentScript || document.querySelector('script[data-site-status-area]');
    const area = script?.dataset.siteStatusArea || 'public';
    const pageScope = script?.dataset.siteStatusScope || (area === 'seller' ? 'seller_portal' : area);
    const maintenancePage = script?.dataset.maintenancePage === 'true';
    const syncTheme = () => {
        let saved = null;
        try { saved = localStorage.getItem('vnz-' + area + '-theme'); } catch (_) {}
        const active = document.documentElement.getAttribute('data-' + area + '-theme') || saved || 'dark';
        document.documentElement.dataset.siteStatusTheme = active === 'light' ? 'light' : 'dark';
    };
    syncTheme();
    new MutationObserver(syncTheme).observe(document.documentElement, { attributes: true, attributeFilter: ['data-public-theme', 'data-seller-theme', 'data-admin-theme'] });

    const originalFetch = window.fetch.bind(window);
    const api = '/VNZ-TECH/backend/site_status_updates.php';
    const state = window.VNZSiteStatus = { suspended: false, refresh: null };
    let polling = false, timer = null, banner, dialog, overlay, focusBefore, listPage = 1;
    const inertBefore = new Map();
    const button = (text, action) => { const node = document.createElement('button'); node.type = 'button'; node.textContent = text; node.addEventListener('click', action); return node; };
    const maintenanceAffectsPage = maintenance => {
        if (!maintenance.active) return false;
        const mode = maintenance.service_mode || 'seller_portal';
        if (maintenancePage) {
            if (area === 'public') return pageScope === 'sell_page' && ['sell_page', 'all_seller_services'].includes(mode);
            if (area === 'seller') return mode === 'all_seller_services' || mode === 'seller_portal' || mode === pageScope;
            return false;
        }
        if (area !== 'seller') return false;
        if (mode === 'all_seller_services') return true;
        if (mode === 'seller_portal') return pageScope !== 'sell_page' && pageScope !== 'public' && pageScope !== 'admin';
        return mode === pageScope;
    };
    const timeText = maintenance => {
        if (!maintenance.estimated_end_at) return 'An updated estimate will be provided.';
        if (maintenance.overdue || new Date(maintenance.estimated_end_at).getTime() <= Date.now()) return 'Maintenance is taking longer than estimated. Please check again shortly.';
        const duration = Number(maintenance.duration_minutes) || 0;
        return 'Estimate: ' + Math.floor(duration / 60) + 'h ' + duration % 60 + 'm. Estimated finish: ' + new Date(maintenance.estimated_end_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) + ' PHT (estimate).';
    };
    const renderMaintenance = maintenance => {
        if (area === 'admin') return;
        if (area !== 'seller') return;
        if (maintenancePage) return;
        const affected = maintenanceAffectsPage(maintenance);
        const becomingActive = affected && !state.suspended;
        state.suspended = affected;
        if (affected) {
            if (!overlay) {
                focusBefore = document.activeElement;
                document.querySelectorAll('dialog[open]').forEach(node => node.close());
                overlay = document.createElement('section'); overlay.className = 'site-runtime-maintenance'; overlay.setAttribute('role', 'alertdialog'); overlay.setAttribute('aria-modal', 'true'); overlay.setAttribute('aria-labelledby', 'siteRuntimeTitle');
                const card = document.createElement('div'); card.className = 'site-maintenance-card';
                const content = document.createElement('section'); content.className = 'site-maintenance-content';
                const brand = document.createElement('a'); brand.className = 'site-maintenance-brand'; brand.href = '/VNZ-TECH/index.php'; brand.setAttribute('aria-label', 'VNZ TECH public site');
                const logo = document.createElement('img'); logo.src = '/VNZ-TECH/assets/images/vnz.png'; logo.alt = '';
                const brandText = document.createElement('span');
                const brandName = document.createElement('strong'); brandName.textContent = 'VNZ TECH';
                const tagline = document.createElement('small'); tagline.textContent = 'We buy. We recover. We revalue.';
                brandText.append(brandName, tagline); brand.append(logo, brandText);
                const status = document.createElement('p'); status.className = 'site-maintenance-status';
                const statusDot = document.createElement('span'); statusDot.setAttribute('aria-hidden', 'true');
                status.append(statusDot, document.createTextNode('VNZ TECH service update'));
                const title = document.createElement('h1'); title.id = 'siteRuntimeTitle'; title.textContent = 'We’re making things better.'; title.tabIndex = -1;
                const intro = document.createElement('p'); intro.className = 'site-maintenance-intro'; intro.textContent = 'This service is taking a short break while we improve VNZ TECH.';
                const details = document.createElement('section'); details.className = 'site-maintenance-details'; details.setAttribute('aria-label', 'Maintenance details');
                const reasonBox = document.createElement('div');
                const reasonLabel = document.createElement('span'); reasonLabel.textContent = 'What’s happening';
                const reason = document.createElement('p'); reason.dataset.maintenanceReason = ''; reason.className = 'site-status-copy';
                reasonBox.append(reasonLabel, reason);
                const estimateBox = document.createElement('div');
                const estimateLabel = document.createElement('span'); estimateLabel.textContent = 'Estimated return';
                const estimate = document.createElement('strong'); estimate.dataset.maintenanceEstimate = '';
                estimateBox.append(estimateLabel, estimate); details.append(reasonBox, estimateBox);
                const help = document.createElement('p'); help.className = 'site-maintenance-public-note'; help.textContent = 'Other parts of VNZ TECH remain available during this update.';
                const actions = document.createElement('div'); actions.className = 'site-maintenance-actions';
                actions.append(button('Check status', () => refresh(true)));
                const feedback = document.createElement('p'); feedback.dataset.maintenanceFeedback = ''; feedback.className = 'site-maintenance-feedback'; feedback.setAttribute('role', 'status'); feedback.setAttribute('aria-live', 'polite');
                content.append(brand, status, title, intro, details, help, actions, feedback);
                const art = document.createElement('aside'); art.className = 'site-maintenance-art'; art.setAttribute('aria-hidden', 'true');
                const artLabel = document.createElement('span'); artLabel.textContent = 'VNZ TECH';
                const icon = document.createElementNS('http://www.w3.org/2000/svg', 'svg'); icon.setAttribute('viewBox', '0 0 160 160'); icon.setAttribute('focusable', 'false');
                const ring = document.createElementNS('http://www.w3.org/2000/svg', 'circle'); ring.setAttribute('cx', '80'); ring.setAttribute('cy', '80'); ring.setAttribute('r', '58');
                const tool = document.createElementNS('http://www.w3.org/2000/svg', 'path'); tool.setAttribute('d', 'M103 47a29 29 0 0 0-35 36L45 106a12 12 0 0 0 17 17l23-23a29 29 0 0 0 36-35l-17 17-17-5-5-17z');
                icon.append(ring, tool);
                const artCopy = document.createElement('small'); artCopy.textContent = 'Thoughtful updates. Better tools.';
                art.append(artLabel, icon, artCopy); card.append(content, art); overlay.append(card);
                document.body.append(overlay);
                [...document.body.children].filter(node => node !== overlay && !['SCRIPT', 'STYLE', 'LINK'].includes(node.tagName)).forEach(node => { inertBefore.set(node, node.inert); node.inert = true; });
                overlay.addEventListener('keydown', event => {
                    if (event.key !== 'Tab') return;
                    const controls = [...overlay.querySelectorAll('button:not([disabled]), a[href]')].filter(node => node.getClientRects().length);
                    if (!controls.length) { event.preventDefault(); title.focus(); return; }
                    const first = controls[0], last = controls[controls.length - 1], active = document.activeElement;
                    if (event.shiftKey && (active === first || active === title || !overlay.contains(active))) {
                        event.preventDefault(); last.focus();
                    } else if (!event.shiftKey && (active === last || active === title || !overlay.contains(active))) {
                        event.preventDefault(); first.focus();
                    }
                });
                title.focus();
            }
            overlay.hidden = false;
            overlay.querySelector('[data-maintenance-reason]').textContent = maintenance.reason;
            overlay.querySelector('[data-maintenance-estimate]').textContent = timeText(maintenance);
            if (becomingActive) window.dispatchEvent(new CustomEvent('vnz:maintenance', { detail: maintenance }));
        } else if (overlay) {
            overlay.remove(); overlay = null;
            inertBefore.forEach((wasInert, node) => { if (node.isConnected) node.inert = wasInert; }); inertBefore.clear();
            if (focusBefore?.isConnected) focusBefore.focus();
            window.dispatchEvent(new Event('vnz:maintenance-ended'));
        }
    };
    const renderAnnouncements = items => {
        if (maintenancePage) return;
        if (!banner) {
            banner = document.createElement('section'); banner.className = 'site-announcement-banner'; banner.setAttribute('aria-label', 'Site announcement'); banner.hidden = true;
            const text = document.createElement('p'); text.dataset.announcementSummary = ''; banner.append(text, button('View all announcements', () => openNotices(1)));
            const header = document.querySelector('.dashboard-main > .seller-topbar, .admin-layout .admin-topbar');
            if (header) header.insertAdjacentElement('afterend', banner); else document.body.prepend(banner);
        }
        banner.hidden = !items.length;
        if (items[0]) { banner.querySelector('p').textContent = items[0].title; banner.querySelector('p').title = items[0].title; }
    };
    const openNotices = async page => {
        listPage = page;
        if (!dialog) {
            dialog = document.createElement('dialog'); dialog.className = 'site-notices-dialog'; dialog.setAttribute('aria-labelledby', 'siteNoticesTitle');
            const heading = document.createElement('h2'); heading.id = 'siteNoticesTitle'; heading.textContent = 'Announcements';
            const content = document.createElement('div'); content.dataset.noticesList = '';
            dialog.append(heading, button('Close', () => dialog.close()), content);
            document.body.append(dialog);
        }
        if (!dialog.open) dialog.showModal();
        const content = dialog.querySelector('[data-notices-list]'); content.textContent = 'Loading announcements…';
        try {
            const response = await originalFetch(api + '?page=' + page, { cache: 'no-store', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Announcements are temporarily unavailable.');
            if (page !== listPage) return;
            renderMaintenance(data.maintenance);
            content.replaceChildren();
            if (!data.announcements.items.length) content.textContent = 'No active announcements.';
            data.announcements.items.forEach(item => {
                const article = document.createElement('article'); article.className = 'site-status-card';
                const title = document.createElement('h3'); title.textContent = item.title;
                const message = document.createElement('p'); message.className = 'site-status-copy'; message.textContent = item.message;
                article.append(title, message); content.append(article);
            });
            const controls = document.createElement('nav'); controls.className = 'site-status-actions'; controls.setAttribute('aria-label', 'Announcement pages');
            const prev = button('Previous', () => openNotices(page - 1)); prev.disabled = page <= 1;
            const next = button('Next', () => openNotices(page + 1)); next.disabled = page >= data.announcements.pages;
            const range = document.createElement('span'); range.textContent = 'Page ' + data.announcements.page + ' of ' + data.announcements.pages;
            controls.append(prev, range, next); content.append(controls);
        } catch (error) { content.textContent = error.message; }
    };
    const refresh = async (explicit = false) => {
        if (polling || (!explicit && document.hidden)) return;
        polling = true;
        const feedback = overlay?.querySelector('[data-maintenance-feedback]') || document.querySelector('[data-maintenance-feedback]');
        try {
            const response = await originalFetch(api, { cache: 'no-store', credentials: 'same-origin', headers: { Accept: 'application/json' } });
            const data = await response.json();
            if (!response.ok || !data.success) throw new Error(data.message || 'Could not check service status.');
            if (maintenancePage) {
                const affected = maintenanceAffectsPage(data.maintenance);
                if (!affected && explicit) { location.reload(); return; }
                if (explicit && feedback) feedback.textContent = affected ? 'Maintenance is still in progress. ' + timeText(data.maintenance) : 'Service is available. Select Check again to continue.';
            }
            renderMaintenance(data.maintenance); renderAnnouncements(data.announcements.items);
        } catch (error) { if (explicit && feedback) feedback.textContent = error.message; }
        finally { polling = false; }
    };
    state.refresh = refresh;
    // Preserve existing response contracts while centrally recognizing maintenance responses.
    if (area === 'seller' && !maintenancePage) {
        window.fetch = async (...args) => {
            const requestUrl = new URL(args[0] instanceof Request ? args[0].url : String(args[0]), location.href);
            if (state.suspended && requestUrl.origin === location.origin && requestUrl.pathname.startsWith('/VNZ-TECH/') && requestUrl.pathname !== api) {
                return new Response(JSON.stringify({ success: false, code: 'maintenance', message: 'The site is under maintenance. Please check again shortly.' }), { status: 503, headers: { 'Content-Type': 'application/json' } });
            }
            const response = await originalFetch(...args);
            if (area === 'seller' && requestUrl.origin === location.origin && response.status === 503 && response.headers.get('Content-Type')?.includes('application/json')) {
                try { const data = await response.clone().json(); if (data.code === 'maintenance' && data.maintenance) renderMaintenance(data.maintenance); } catch (_) { /* Existing caller handles non-JSON errors. */ }
            }
            return response;
        };
    }
    document.addEventListener('click', event => {
        if (event.target.closest?.('[data-maintenance-recheck]')) refresh(true);
    });
    const start = () => { if (timer === null) timer = window.setInterval(refresh, 30000); refresh(); };
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
    window.addEventListener('vnz:site-status-refresh', () => refresh(true));
    window.addEventListener('pagehide', () => { clearInterval(timer); timer = null; });
    window.addEventListener('pageshow', start);
    start();
})();
