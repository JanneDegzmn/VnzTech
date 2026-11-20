(() => {
    'use strict';
    const init = () => {
        const root = document.querySelector('[data-admin-topbar]');
        if (!root || root.dataset.adminToolsReady) return;
        root.dataset.adminToolsReady = 'true';
        const panel = root.querySelector('#adminTopbarUpdates');
        const toggle = root.querySelector('[data-admin-updates-toggle]');
        const state = root.querySelector('[data-admin-updates-state]');
        const list = root.querySelector('[data-admin-updates-list]');
        const badge = root.querySelector('[data-admin-updates-badge]');
        const retry = root.querySelector('[data-admin-updates-retry]');
        const chat = root.querySelector('[data-floating-messages]');
        let busy = false, expired = false;
        const position = () => {
            root.style.setProperty('--seller-topbar-bottom', Math.max(0,root.getBoundingClientRect().bottom)+'px');
            root.style.setProperty('--seller-panel-right','12px');
        };
        const close = (focus = false) => { panel.hidden = true; toggle.setAttribute('aria-expanded','false'); if(focus) toggle.focus(); };
        async function refresh() {
            if (busy || expired || document.hidden) return;
            busy = true;
            try {
                const response = await fetch('/VNZ-TECH/backend/admin_topbar_updates.php', {credentials:'same-origin',cache:'no-store'});
                if (response.status === 401) {expired = true; throw new Error('Please sign in again.');}
                if (!response.ok) throw new Error('Updates are temporarily unavailable.');
                const data = await response.json(); if (!data.success) throw new Error('Unable to load updates.');
                list.replaceChildren();
                let total = 0;
                for (const item of Array.isArray(data.items) ? data.items : []) {
                    if (!['submissions','transactions','trade_requests'].includes(item.page)) continue;
                    const count = Number(item.count); if (!Number.isSafeInteger(count) || count < 0) continue;
                    total += count;
                    const a = document.createElement('a'); a.href = '?page='+encodeURIComponent(item.page);
                    a.textContent = String(item.label || 'Updates') + ': ' + count;
                    a.addEventListener('click', event => {
                        if (event.button || event.ctrlKey || event.metaKey || event.shiftKey || !window.loadAdminPage) return;
                        // An explicit navigation must not discard an open review draft.
                        if (document.querySelector('#adminSubmissionModal.is-open')) {event.preventDefault();state.textContent='Close or finish your current review before navigating.';return;}
                        event.preventDefault(); close(); window.loadAdminPage(item.page,{});
                    });
                    list.append(a);
                }
                badge.hidden = total === 0; badge.textContent = total > 99 ? '99+' : String(total);
                state.textContent = total ? 'Updates that need your attention.' : 'You are all caught up.';
                retry.hidden = true;
            } catch(error) {
                list.replaceChildren(); badge.hidden = true;
                state.textContent = error.message || 'Unable to load updates.'; retry.hidden = expired;
            } finally {busy = false;}
        }
        toggle.addEventListener('click', () => {
            const open = panel.hidden;close();
            if(open) {chat?.dispatchEvent(new Event('vnz:seller-chat-hide'));position();panel.hidden=false;toggle.setAttribute('aria-expanded','true');root.querySelector('[data-admin-updates-close]').focus();refresh();}
        });
        root.querySelector('[data-admin-updates-close]').addEventListener('click',()=>close(true));
        retry.addEventListener('click',refresh);
        document.addEventListener('click',event=>{if(!event.target.closest('.seller-topbar-notifications')) close();});
        document.addEventListener('keydown',event=>{if(event.key==='Escape'&&!panel.hidden)close(true);});
        window.addEventListener('vnz:seller-chat-state',event=>{if(event.detail.open)close();});
        window.addEventListener('resize',position,{passive:true});window.addEventListener('scroll',position,{passive:true});
        const sidebarClose = document.getElementById('sidebarClose');
        const sidebarTop = document.querySelector('#sidebar .sidebar-top');
        if(sidebarClose && sidebarTop) {sidebarTop.prepend(sidebarClose); sidebarClose.textContent='\u2630';sidebarClose.setAttribute('aria-label','Close navigation menu');}
        document.getElementById('menuButton')?.setAttribute('aria-label','Open navigation menu');
        let timer = setInterval(refresh,30000);
        window.addEventListener('pagehide',()=>clearInterval(timer));
        window.addEventListener('pageshow',event=>{if(event.persisted){clearInterval(timer);timer=setInterval(refresh,30000);refresh();}});
        document.addEventListener('visibilitychange',refresh);
        position();refresh();
    };
    if(document.readyState==='loading')document.addEventListener('DOMContentLoaded',init,{once:true});else init();
})();
