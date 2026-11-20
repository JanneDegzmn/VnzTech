(() => {
    'use strict';
    const root = document.querySelector('[data-site-updates]');
    if (!root || root.dataset.initialized) return;
    root.dataset.initialized = 'true';
    const feedback = root.querySelector('[data-site-feedback]');
    const api = '/VNZ-TECH/backend/admin_site_status.php';
    let page = 1, pages = 1, records = [], generation = 0, busy = false, confirming = false;
    const say = message => { feedback.textContent = message; };
    const request = async (query, data) => {
        const response = await fetch(api + query, { method: data ? 'POST' : 'GET', body: data, credentials: 'same-origin', headers: { Accept: 'application/json' }, cache: 'no-store' });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(result.message || 'Site Updates is unavailable.');
        return result;
    };
    const confirmMaintenance = (serviceLabel, mode) => new Promise(resolve => {
        const dialog = document.createElement('dialog'); dialog.className = 'site-notices-dialog'; dialog.setAttribute('aria-labelledby', 'siteMaintenanceConfirm');
        const title = document.createElement('h2'); title.id = 'siteMaintenanceConfirm'; title.textContent = 'Enable maintenance?';
        const message = mode === 'all_seller_services'
            ? 'All seller services, including the Sell page, will be paused. Admin and unrelated public pages will remain available.'
            : mode === 'seller_portal'
                ? 'Seller portal and workflows will be paused. Admin and public browsing will remain available.'
                : serviceLabel + ' will be paused. Admin access and other services will remain available.';
        const text = document.createElement('p'); text.textContent = message;
        const cancel = document.createElement('button'); cancel.type = 'button'; cancel.textContent = 'Cancel';
        const approve = document.createElement('button'); approve.type = 'button'; approve.textContent = 'Enable maintenance';
        let approved = false;
        cancel.addEventListener('click', () => dialog.close()); approve.addEventListener('click', () => { approved = true; dialog.close(); });
        dialog.addEventListener('close', () => { dialog.remove(); resolve(approved); }, { once: true });
        dialog.append(title, text, cancel, approve); root.append(dialog); dialog.showModal(); cancel.focus();
    });
    const tabs = [...root.querySelectorAll('[data-site-tab]')];
    const choose = tab => {
        tabs.forEach(button => { const selected = button === tab; button.setAttribute('aria-selected', String(selected)); button.tabIndex = selected ? 0 : -1; });
        root.querySelectorAll('[data-site-panel]').forEach(panel => { panel.hidden = panel.dataset.sitePanel !== tab.dataset.siteTab; });
    };
    tabs.forEach((tab, index) => {
        tab.addEventListener('click', () => choose(tab));
        tab.addEventListener('keydown', event => {
            if (!['ArrowLeft', 'ArrowRight', 'Home', 'End'].includes(event.key)) return;
            event.preventDefault();
            const next = event.key === 'Home' ? 0 : event.key === 'End' ? tabs.length - 1 : (index + (event.key === 'ArrowRight' ? 1 : -1) + tabs.length) % tabs.length;
            choose(tabs[next]); tabs[next].focus();
        });
    });
    if (tabs[0]) choose(tabs[0]);
    const form = root.querySelector('[data-announcement-form]');
    const list = root.querySelector('[data-announcement-list]');
    const filter = root.querySelector('[data-announcement-filter]');
    const clear = () => { form.reset(); form.elements.id.value = '0'; form.elements.version.value = '1'; root.querySelector('[data-announcement-heading]').textContent = 'New announcement'; root.querySelector('[data-announcement-preview-box]').hidden = true; };
    const load = async () => {
        if (!list) return;
        const token = ++generation;
        try {
            const result = await request('?action=announcements&page=' + page + '&status=' + encodeURIComponent(filter.value));
            if (token !== generation || !root.isConnected) return;
            records = result.announcements.items; page = result.announcements.page; pages = result.announcements.pages;
            list.replaceChildren();
            if (!records.length) { const p = document.createElement('p'); p.textContent = 'No announcements yet.'; list.append(p); }
            records.forEach(item => {
                const article = document.createElement('article'); article.className = 'site-status-card';
                const title = document.createElement('h3'); title.textContent = item.title;
                const status = document.createElement('p'); status.textContent = item.status + (item.expires_at ? ' · Expires ' + new Date(item.expires_at.replace(' ', 'T') + 'Z').toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) + ' PHT' : '');
                const text = document.createElement('p'); text.className = 'site-status-copy'; text.textContent = item.message;
                const edit = document.createElement('button'); edit.type = 'button'; edit.textContent = 'Edit announcement';
                edit.addEventListener('click', () => {
                    ['id', 'version', 'title', 'message', 'status'].forEach(key => { form.elements[key].value = String(item[key]); });
                    form.elements.expires_at.value = item.expires_at ? new Date(new Date(item.expires_at.replace(' ', 'T') + 'Z').getTime() + 8 * 3600000).toISOString().slice(0, 16) : '';
                    root.querySelector('[data-announcement-heading]').textContent = 'Edit announcement'; form.elements.title.focus();
                });
                article.append(title, status, text, edit); list.append(article);
            });
            root.querySelector('[data-announcement-range]').textContent = 'Page ' + page + ' of ' + pages + ' · ' + result.announcements.total + ' total';
            root.querySelector('[data-announcement-prev]').disabled = page <= 1;
            root.querySelector('[data-announcement-next]').disabled = page >= pages;
        } catch (error) { say(error.message); }
    };
    if (form) {
        root.querySelector('[data-announcement-new]').addEventListener('click', clear);
        root.querySelector('[data-announcement-preview]').addEventListener('click', () => {
            const preview = root.querySelector('[data-announcement-preview-box]'); preview.querySelector('h3').textContent = form.elements.title.value; preview.querySelector('p').textContent = form.elements.message.value; preview.hidden = false;
        });
        form.addEventListener('submit', async event => {
            event.preventDefault(); if (busy) return;
            busy = true; const button = form.querySelector('[type=submit]'); button.disabled = true;
            try { const result = await request('', new FormData(form)); say(result.message); clear(); await load(); window.dispatchEvent(new Event('vnz:site-status-refresh')); }
            catch (error) { say(error.message); }
            finally { busy = false; button.disabled = false; }
        });
        filter.addEventListener('change', () => { page = 1; load(); });
        root.querySelector('[data-announcement-prev]').addEventListener('click', () => { if (page > 1) { page--; load(); } });
        root.querySelector('[data-announcement-next]').addEventListener('click', () => { if (page < pages) { page++; load(); } });
        load();
    }
    const maintenance = root.querySelector('[data-maintenance-form]');
    if (maintenance) {
        const serviceMode = maintenance.elements.service_mode;
        const reason = maintenance.elements.reason;
        const hours = maintenance.elements.hours;
        const minutes = maintenance.elements.minutes;
        const stateMessage = root.querySelector('[data-maintenance-state]');
        const toggle = root.querySelector('[data-maintenance-toggle]');
        let currentState = null;
        const syncControls = () => {
            const active = !!currentState?.active;
            const locked = busy || active || !currentState?.configured || !!currentState?.state_error;
            serviceMode.disabled = locked;
            reason.disabled = locked;
            hours.disabled = locked;
            minutes.disabled = locked;
            root.querySelector('[data-maintenance-refresh]').disabled = busy;
            toggle.textContent = active ? 'Disable maintenance' : 'Enable maintenance';
            toggle.dataset.active = String(active);
            toggle.removeAttribute('data-maintenance-enable');
            toggle.removeAttribute('data-maintenance-disable');
            toggle.setAttribute(active ? 'data-maintenance-disable' : 'data-maintenance-enable', '');
            toggle.disabled = busy || !currentState?.configured || !!currentState?.state_error;
        };
        const refresh = async () => {
            try {
                const result = await request('?action=maintenance'); const state = result.maintenance;
                currentState = state;
                maintenance.elements.version.value = state.version;
                maintenance.elements.active.value = state.active ? '1' : '0';
                serviceMode.value = state.service_mode || 'seller_portal';
                reason.value = state.reason || '';
                hours.value = Math.floor((Number(state.duration_minutes) || 0) / 60);
                minutes.value = (Number(state.duration_minutes) || 0) % 60;
                const label = serviceMode.selectedOptions[0]?.textContent.trim() || 'selected service';
                stateMessage.dataset.active = String(!!state.active);
                stateMessage.textContent = state.state_error
                    ? 'Maintenance state needs recovery. Controls are unavailable until it is repaired.'
                    : state.active
                        ? 'Maintenance is ON for ' + label + '. Estimated finish: ' + (state.estimated_end_at ? new Date(state.estimated_end_at).toLocaleString('en-PH', { timeZone: 'Asia/Manila' }) + ' PHT' : 'unavailable')
                        : 'Maintenance is OFF. All services are available.';
                reason.required = false;
                syncControls();
                if (!state.configured) say('Complete the local Site Updates setup before using maintenance controls.');
            } catch (error) { say(error.message); }
        };
        root.querySelector('[data-maintenance-refresh]').addEventListener('click', refresh);
        maintenance.addEventListener('submit', event => event.preventDefault());
        const saveMaintenance = async active => {
            if (busy || confirming || !currentState?.configured || currentState?.state_error) return;
            if (active) {
                reason.required = true;
                if (!maintenance.reportValidity()) { reason.required = false; return; }
                confirming = true;
                const serviceLabel = serviceMode.selectedOptions[0]?.textContent.trim() || 'The selected service';
                const approved = await confirmMaintenance(serviceLabel, serviceMode.value);
                confirming = false;
                if (!approved) { reason.required = false; return; }
            } else {
                reason.required = false;
            }
            maintenance.elements.active.value = active ? '1' : '0';
            busy = true;
            syncControls();
            const data = new FormData(maintenance);
            data.set('active', active ? '1' : '0');
            data.set('service_mode', serviceMode.value);
            data.set('reason', active ? reason.value : '');
            data.set('hours', active ? hours.value : '0');
            data.set('minutes', active ? minutes.value : '0');
            try { const result = await request('', data); say(result.message); await refresh(); window.dispatchEvent(new Event('vnz:site-status-refresh')); }
            catch (error) { say(error.message); }
            finally { busy = false; reason.required = false; syncControls(); }
        };
        toggle.addEventListener('click', () => saveMaintenance(!currentState?.active));
        refresh();
    }
})();
