(() => {
    'use strict';
    const init = () => {
        const root = document.querySelector('[data-seller-topbar]');
        if (!root || root.dataset.initialized) return;
        root.dataset.initialized = 'true';
        const find = selector => root.querySelector(selector);
        const toggle = find('[data-topbar-notifications-toggle]');
        const panel = find('#sellerTopbarNotifications');
        const chat = find('[data-floating-messages]');
        const state = find('[data-topbar-state]');
        const list = find('[data-topbar-updates]');
        const badge = find('[data-topbar-notifications-badge]');
        const retry = find('[data-topbar-retry]');
        const avatar = find('[data-topbar-avatar]');
        const menu = document.getElementById('sellerMobileMenuButton');
        if (menu) find('[data-seller-menu-slot]').append(menu);
        const sidebar = document.getElementById('sellerDashboardSidebar');
        if (menu && sidebar) {
            const sidebarClose = document.createElement('button');
            sidebarClose.type = 'button'; sidebarClose.className = 'seller-sidebar-close';
            sidebarClose.dataset.sellerSidebarClose = '';
            sidebarClose.setAttribute('aria-label', 'Close navigation menu');
            sidebarClose.setAttribute('aria-controls', 'sellerDashboardSidebar');
            sidebarClose.textContent = '\u2630'; sidebarClose.hidden = true;
            (sidebar.querySelector('.sidebar-brand') || sidebar).append(sidebarClose);
            sidebarClose.addEventListener('click', () => { if (sidebar.classList.contains('mobile-open')) menu.click(); menu.focus(); });
            let wasOpen = false;
            const syncSidebar = () => {
                const open = sidebar.classList.contains('mobile-open') && window.innerWidth <= 760;
                sidebarClose.hidden = !open;
                sidebarClose.setAttribute('aria-expanded', String(open));
                if (open && !wasOpen) sidebarClose.focus();
                if (!open && wasOpen && document.activeElement === sidebarClose) menu.focus();
                wasOpen = open;
            };
            new MutationObserver(syncSidebar).observe(sidebar, {attributes:true, attributeFilter:['class']});
            window.addEventListener('resize', syncSidebar, {passive:true});
            syncSidebar();
        }

        let busy = false, expired = false, timer;
        const text = (english, filipino) => root.dataset.language === 'fil' ? filipino : english;
        const position = () => {
            root.style.setProperty('--seller-topbar-bottom', `${Math.max(0, root.getBoundingClientRect().bottom) + 8}px`);
            root.style.setProperty('--seller-panel-right', `${Math.max(12, window.innerWidth - root.getBoundingClientRect().right)}px`);
        };
        position();
        new ResizeObserver(position).observe(root);
        window.addEventListener('resize', position, {passive:true});
        window.addEventListener('scroll', position, {passive:true});
        const close = (focus = false) => {
            panel.hidden = true;
            toggle.setAttribute('aria-expanded', 'false');
            if (focus) toggle.focus();
        };
        const render = data => {
            root.dataset.language = data.language;
            const profile = data.profile;
            find('[data-topbar-name]').textContent = profile.full_name;
            find('[data-topbar-initial]').textContent = Array.from(profile.full_name)[0]?.toUpperCase() || 'S';
            if (profile.avatar_url) {
                if (avatar.getAttribute('src') !== profile.avatar_url) avatar.src = profile.avatar_url;
                avatar.hidden = false;
            } else { avatar.hidden = true; avatar.removeAttribute('src'); }
            find('[data-topbar-notifications-title]').textContent = text('Notifications', 'Mga update');
            find('[data-topbar-description]').textContent = text('Offers and results that need your attention.', 'Mga offer at results na kailangan mong tingnan.');
            retry.textContent = text('Try again', 'Subukan ulit');
            const total = Math.max(0, Number(data.updates.total) || 0);
            badge.hidden = !total;
            badge.textContent = total > 99 ? '99+' : String(total);
            toggle.setAttribute('aria-label', text('Notifications', 'Mga update') + `: ${total} ` + text('outstanding updates', 'update na kailangan tingnan'));
            list.replaceChildren();
            data.updates.items.forEach(item => {
                const id = Number(item.submission_id);
                if (!Number.isSafeInteger(id) || id <= 0) return;
                const link = document.createElement('a');
                link.href = `?page=submission&id=${id}`;
                link.dataset.submissionId = id;
                const title = document.createElement('strong');
                title.textContent = item.kind === 'offer' ? text('Offer ready for review', 'May offer na para sa iyo') : item.kind === 'accepted' ? text('Submission accepted', 'Accepted ang submission mo') : text('Submission declined', 'Declined ang submission mo');
                const reference = document.createElement('span');
                reference.textContent = item.reference;
                const date = document.createElement('small');
                // Preserve server wall-clock time rather than assume its timezone.
                date.textContent = item.updated_at;
                link.append(title, reference, date);
                list.append(link);
            });
            state.textContent = total ? (total > data.updates.items.length ? text('Showing the latest 20 updates.', 'Latest 20 updates ang nandito.') : '') : text('You are all caught up.', 'Wala pang bagong update.');
        };
        const refresh = async () => {
            if (busy || expired || document.hidden) return;
            busy = true;
            retry.hidden = true;
            if (!list.children.length) state.textContent = text('Loading updates...', 'Kinukuha ang updates...');
            const controller = new AbortController();
            const timeout = setTimeout(() => controller.abort(), 12000);
            try {
                const response = await fetch('/VNZ-TECH/backend/seller_topbar_api.php', {credentials:'same-origin', cache:'no-store', signal:controller.signal});
                if (response.status === 401) { expired = true; throw new Error('session'); }
                const data = await response.json();
                if (!response.ok || !data.success) throw new Error('unavailable');
                render(data);
            } catch (error) {
                list.replaceChildren(); badge.hidden = true;
                state.textContent = expired ? text('Your session expired. Please sign in again.', 'Expired na ang session mo. Mag-login ulit.') : text('Updates are unavailable. Please try again.', 'Hindi makuha ang updates. Subukan ulit.');
                retry.hidden = expired;
            } finally { clearTimeout(timeout); busy = false; }
        };
        avatar.addEventListener('error', () => { avatar.hidden = true; });
        if (avatar.getAttribute('src') && avatar.complete && !avatar.naturalWidth) avatar.hidden = true;
        toggle.addEventListener('click', () => {
            const opening = panel.hidden;
            close();
            if (opening) {
                chat?.dispatchEvent(new Event('vnz:seller-chat-hide'));
                position(); panel.hidden = false; toggle.setAttribute('aria-expanded', 'true');
                refresh(); find('[data-topbar-notifications-close]').focus();
            }
        });
        find('[data-topbar-notifications-close]').addEventListener('click', () => close(true));
        retry.addEventListener('click', refresh);
        document.addEventListener('click', event => {
            if (!event.target.closest('.seller-topbar-notifications')) close();
        });
        document.addEventListener('keydown', event => { if (event.key === 'Escape' && !panel.hidden) close(true); });
        window.addEventListener('vnz:seller-chat-state', event => { if (event.detail.open) close(); });
        const navigate = (event, page, params = {}) => {
            if (event.button !== 0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey || !window.loadSellerPage) return;
            event.preventDefault(); close();
            window.loadSellerPage(page, true, params);
        };
        find('[data-topbar-profile]').addEventListener('click', event => navigate(event, 'profile'));
        list.addEventListener('click', event => {
            const link = event.target.closest('[data-submission-id]');
            if (link) navigate(event, 'submission', {id:Number(link.dataset.submissionId)});
        });
        window.addEventListener('vnz:seller-profile-updated', refresh);
        document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(); });
        window.addEventListener('focus', refresh);
        // One shell-owned timer; AJAX page loads do not create another instance.
        timer = setInterval(refresh, 30000);
        window.addEventListener('pagehide', () => clearInterval(timer));
        window.addEventListener('pageshow', event => { if (event.persisted) { clearInterval(timer); timer = setInterval(refresh, 30000); refresh(); } });
        refresh();
    };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init, {once:true});
    else init();
})();
