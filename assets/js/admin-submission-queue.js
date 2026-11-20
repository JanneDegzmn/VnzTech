(() => {
    'use strict';
    window.vnzAdminSubmissionQueueCleanup?.();
    const root = document.querySelector('[data-admin-submission-queue]');
    if (!root) return;
    let stopped = false;
    let busy = false;
    const controller = new AbortController();
    const banner = root.querySelector('[data-queue-update-banner]');
    const message = root.querySelector('[data-queue-update-message]');
    const refresh = root.querySelector('[data-queue-refresh]');
    const currentParams = (page = 1) => ({
        search: document.getElementById('submissionSearch')?.value.trim() || '',
        status: document.getElementById('submissionStatusFilter')?.value || '',
        item_type: document.getElementById('submissionItemTypeFilter')?.value || '',
        queue: root.dataset.queue || '',
        assignment: document.getElementById('submissionAssignmentFilter')?.value || 'all',
        sort: document.getElementById('submissionSortFilter')?.value || 'newest',
        list_page: page
    });
    const apply = (page = 1) => window.loadAdminPage?.('submissions', currentParams(page));
    root.querySelectorAll('[data-queue-filter]').forEach(button => button.addEventListener('click', () => {
        root.dataset.queue = button.dataset.queueFilter;
        // Group tabs replace the exact-status filter rather than silently intersecting it.
        document.getElementById('submissionStatusFilter').value = '';
        apply();
    }, {signal: controller.signal}));
    ['submissionAssignmentFilter', 'submissionSortFilter'].forEach(id => {
        document.getElementById(id)?.addEventListener('change', () => apply(), {signal: controller.signal});
    });
    const reviewOpen = () => !!document.querySelector('#adminSubmissionModal.is-open');
    refresh.addEventListener('click', () => {
        if (reviewOpen()) {
            message.textContent = 'Finish or close the current review before refreshing. Your draft stays open.';
            return;
        }
        apply(Number(root.dataset.listPage) || 1);
    }, {signal: controller.signal});
    async function poll() {
        if (stopped || busy || document.hidden) return;
        if (!root.isConnected) { cleanup(); return; }
        busy = true;
        try {
            const response = await fetch('/VNZ-TECH/backend/admin_submission_queue_updates.php?after=' + encodeURIComponent(root.dataset.after) + '&opening_total=' + encodeURIComponent(root.dataset.openingTotal), {
                credentials: 'same-origin', cache: 'no-store', signal: controller.signal
            });
            if ([401, 403].includes(response.status)) { cleanup(); return; }
            if (!response.ok) throw new Error('Queue update unavailable');
            const data = await response.json();
            if (stopped || !root.isConnected || !data.success) return;
            const newerCount = Number(data.newer_count), total = Number(data.current_total), openingTotal = Number(root.dataset.openingTotal);
            if (![newerCount, total, openingTotal].every(value => Number.isSafeInteger(value) && value >= 0)) return;
            const count = Math.max(newerCount, Math.max(0, total - openingTotal));
            banner.hidden = count === 0;
            // Some existing flex rules override the native hidden attribute.
            banner.style.display = count === 0 ? 'none' : '';
            message.textContent = count + ' new submission' + (count === 1 ? '' : 's') + ' detected since this list opened. Refresh when ready; current filters stay applied.';
        } catch (error) {
            if (error.name !== 'AbortError' && root.isConnected) {
                banner.hidden = false;
                banner.style.display = '';
                message.textContent = 'Live queue updates unavailable. You can refresh the list when ready.';
            }
        } finally { busy = false; }
    }
    const timer = window.setInterval(poll, 30000);
    function cleanup() {
        stopped = true;
        window.clearInterval(timer);
        controller.abort();
    }
    window.vnzAdminSubmissionQueueCleanup = cleanup;
    document.addEventListener('visibilitychange', poll, {signal: controller.signal});
    // bfcache keeps this document alive; visibilitychange resumes its existing poller.
    window.addEventListener('pagehide', event => { if (!event.persisted) cleanup(); }, {signal: controller.signal});
    poll();
})();
