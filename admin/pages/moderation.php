<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', true);
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

$currentAdmin = getCurrentAdmin();
if (!$currentAdmin) { http_response_code(401); exit('Your admin account is no longer active.'); }
$csrfToken = generateCsrfToken();
if (!hasPermission('manage_settings')) {
    http_response_code(403);
    ?>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
    <div class="admin-settings-page">
        <div class="admin-report-empty">
            <h2>Access denied</h2>
            <p>You do not have permission to access this administrative module.</p>
        </div>
    </div>
    <?php
    exit;
}

?>
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
<div class="admin-settings-page"><header class="operations-header"><div><span class="operations-eyebrow">ADMINISTRATION</span><h1>Chat Moderation</h1><p>Manage Global Chat rules and flagged messages.</p></div></header>
<div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>
<div class="operations-settings-grid admin-moderation-page-grid">
    <section
        class="operations-settings-card admin-global-chat-moderation"
        data-global-chat-moderation
        data-csrf-token="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
        aria-labelledby="globalChatModerationTitle"
    >
        <div class="admin-global-chat-moderation-heading">
            <div>
                <h2 id="globalChatModerationTitle">Global Chat moderation</h2>
                <p>Manage Global Chat rules and review flagged messages. Private and transaction chats are not moderated by these rules.</p>
            </div>
            <button type="button" class="operations-button" data-moderation-refresh>Refresh</button>
        </div>
        <div class="operations-form-message" data-moderation-message hidden role="status"></div>

        <form class="admin-global-chat-rule-form" data-moderation-rule-form>
            <input type="hidden" name="rule_id" value="">
            <div class="operations-form-grid">
                <div class="operations-field">
                    <label for="moderationRuleText">Prohibited word or phrase</label>
                    <input id="moderationRuleText" name="rule_text" type="text" maxlength="191" required>
                </div>
                <div class="operations-field">
                    <label for="moderationMatchType">Match type</label>
                    <select id="moderationMatchType" name="match_type">
                        <option value="word">Whole word</option>
                        <option value="phrase">Complete phrase</option>
                    </select>
                </div>
                <div class="operations-field">
                    <label for="moderationCategory">Category</label>
                    <select id="moderationCategory" name="category" required>
                        <option value="" selected disabled>Choose a category</option>
                        <option value="Profanity and harassment">Profanity and harassment</option>
                        <option value="Potential business accusations">Potential business accusations</option>
                        <option value="Credential theft and suspicious requests">Credential theft and suspicious requests</option>
                        <option value="Spam and customer diversion">Spam and customer diversion</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <div class="operations-field">
                    <label for="moderationSeverity">Severity</label>
                    <select id="moderationSeverity" name="severity">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                        <option value="critical">Critical</option>
                    </select>
                </div>
                <div class="operations-field">
                    <label for="moderationRuleAction">Action</label>
                    <select id="moderationRuleAction" name="rule_action">
                        <option value="warn">Warn and allow</option>
                        <option value="block">Block message</option>
                        <option value="review" selected>Flag for Admin review</option>
                    </select>
                </div>
                <div class="operations-field admin-global-chat-rule-enabled">
                    <label for="moderationRuleEnabled">Rule status</label>
                    <select id="moderationRuleEnabled" name="enabled">
                        <option value="1" selected>Enabled</option>
                        <option value="0">Disabled</option>
                    </select>
                </div>
            </div>
            <div class="operations-form-actions">
                <button class="operations-button" type="submit" data-moderation-save-rule>Save rule</button>
                <button class="operations-button admin-global-chat-cancel-edit" type="button" data-moderation-cancel-edit hidden>Cancel edit</button>
            </div>
        </form>

        <div class="admin-global-chat-moderation-columns">
            <section class="admin-global-chat-moderation-column" aria-labelledby="globalChatRulesHeading">
                <h3 id="globalChatRulesHeading">Configured rules</h3>
                <label class="admin-global-chat-search">
                    <input
                        type="search"
                        data-moderation-rule-search
                        aria-label="Search configured rules"
                        placeholder="Search words, categories, actions…"
                        autocomplete="off"
                    >
                </label>
                <div class="admin-global-chat-rules" data-moderation-rules>
                    <p class="admin-activity-empty">Loading moderation rules…</p>
                </div>
                <nav class="admin-global-chat-rule-pagination" data-moderation-pagination aria-label="Configured rule pages" hidden></nav>
            </section>
            <section class="admin-global-chat-moderation-column" aria-labelledby="globalChatReviewHeading">
                <h3 id="globalChatReviewHeading">Flagged messages</h3>
                <div class="admin-global-chat-cases" data-moderation-cases>
                    <p class="admin-activity-empty">Loading review queue…</p>
                </div>
            </section>
        </div>
    </section>


</div></div>
<script>
(function () {
    const root = document.querySelector('[data-global-chat-moderation]');
    if (!root) {
        return;
    }

    const endpoint = '/VNZ-TECH/backend/global_chat_moderation.php';
    const ruleForm = root.querySelector('[data-moderation-rule-form]');
    const rulesContainer = root.querySelector('[data-moderation-rules]');
    const rulesSearch = root.querySelector('[data-moderation-rule-search]');
    const paginationContainer = root.querySelector('[data-moderation-pagination]');
    const casesContainer = root.querySelector('[data-moderation-cases]');
    const message = root.querySelector('[data-moderation-message]');
    const saveButton = root.querySelector('[data-moderation-save-rule]');
    const cancelButton = root.querySelector('[data-moderation-cancel-edit]');
    const categorySelect = ruleForm.elements.category;
    const rulesPerPage = 3;
    let configuredRules = [];
    let currentRulePage = 1;
    let filteredRuleCount = 0;

    function showMessage(text, kind = 'success') {
        message.textContent = text;
        message.dataset.kind = kind;
        message.hidden = false;
    }

    async function request(fields = null) {
        const options = fields
            ? {
                method: 'POST',
                credentials: 'same-origin',
                headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
                body: new URLSearchParams({
                    csrf_token: root.dataset.csrfToken || '',
                    ...fields
                })
            }
            : {method: 'GET', credentials: 'same-origin', cache: 'no-store'};
        const response = await fetch(endpoint, options);
        let payload;
        try {
            payload = await response.json();
        } catch {
            throw new Error('The server returned an invalid moderation response.');
        }
        if (!response.ok || payload.success !== true) {
            throw new Error(payload.message || 'Unable to complete the moderation request.');
        }
        return payload.data || {};
    }

    function makeButton(label, action, id, extraClass = '') {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = `operations-button admin-global-chat-small-button ${extraClass}`.trim();
        button.dataset.moderationAction = action;
        button.dataset.moderationId = String(id);
        button.textContent = label;
        return button;
    }

    function ensureCategoryOption(category) {
        const value = String(category || '').trim();
        if (!value || Array.from(categorySelect.options).some(option => option.value === value)) {
            return;
        }
        const option = document.createElement('option');
        option.value = value;
        option.textContent = value;
        categorySelect.add(option);
    }

    function renderRulePagination(total) {
        paginationContainer.replaceChildren();
        const totalPages = Math.ceil(total / rulesPerPage);
        if (totalPages <= 1) {
            paginationContainer.hidden = true;
            return;
        }

        paginationContainer.hidden = false;
        const firstItem = ((currentRulePage - 1) * rulesPerPage) + 1;
        const lastItem = Math.min(firstItem + rulesPerPage - 1, total);
        const count = document.createElement('span');
        const controls = document.createElement('div');
        count.className = 'vnz-pagination-count';
        count.textContent = `Showing ${firstItem}–${lastItem} of ${total} rules`;
        controls.className = 'vnz-pagination-controls';

        function addStep(label, targetPage, disabled) {
            const button = document.createElement('button');
            button.type = 'button';
            button.className = `vnz-pagination-link${disabled ? ' vnz-pagination-disabled' : ''}`;
            button.textContent = label;
            button.setAttribute(
                'aria-label',
                label.includes('Previous') ? 'Previous rules page' : 'Next rules page'
            );
            button.disabled = disabled;
            button.dataset.rulePage = String(targetPage);
            controls.append(button);
        }

        function addPage(page) {
            if (page === currentRulePage) {
                const current = document.createElement('span');
                current.className = 'vnz-pagination-current';
                current.setAttribute('aria-current', 'page');
                current.textContent = String(page);
                controls.append(current);
                return;
            }

            const button = document.createElement('button');
            button.type = 'button';
            button.className = 'vnz-pagination-link';
            button.textContent = String(page);
            button.setAttribute('aria-label', `Page ${page}`);
            button.dataset.rulePage = String(page);
            controls.append(button);
        }

        addStep('‹ Previous', currentRulePage - 1, currentRulePage <= 1);
        const visiblePages = new Set([1, totalPages]);
        for (
            let page = Math.max(2, currentRulePage - 1);
            page <= Math.min(totalPages - 1, currentRulePage + 1);
            page += 1
        ) {
            visiblePages.add(page);
        }
        const pages = Array.from(visiblePages).sort((left, right) => left - right);
        pages.forEach((page, index) => {
            if (index > 0 && page - pages[index - 1] > 1) {
                const ellipsis = document.createElement('span');
                ellipsis.className = 'vnz-pagination-ellipsis';
                ellipsis.setAttribute('aria-hidden', 'true');
                ellipsis.textContent = '…';
                controls.append(ellipsis);
            }
            addPage(page);
        });
        addStep('Next ›', currentRulePage + 1, currentRulePage >= totalPages);
        paginationContainer.append(count, controls);
    }

    function renderRules(rules) {
        rules.forEach(rule => ensureCategoryOption(rule.category));
        rulesContainer.replaceChildren();
        filteredRuleCount = rules.length;
        if (!rules.length) {
            currentRulePage = 1;
            const empty = document.createElement('p');
            empty.className = 'admin-activity-empty';
            empty.textContent = configuredRules.length
                ? 'No rules match your search.'
                : 'No moderation rules are configured.';
            rulesContainer.append(empty);
            renderRulePagination(0);
            return;
        }

        const totalPages = Math.ceil(rules.length / rulesPerPage);
        currentRulePage = Math.min(currentRulePage, totalPages);
        const firstRule = (currentRulePage - 1) * rulesPerPage;
        const visibleRules = rules.slice(firstRule, firstRule + rulesPerPage);
        renderRulePagination(rules.length);

        visibleRules.forEach(rule => {
            const card = document.createElement('article');
            const heading = document.createElement('div');
            const term = document.createElement('strong');
            const state = document.createElement('span');
            const metadata = document.createElement('p');
            const actions = document.createElement('div');
            card.className = 'admin-global-chat-rule';
            heading.className = 'admin-global-chat-rule-heading';
            term.textContent = rule.rule_text;
            state.className = `admin-global-chat-rule-state${Number(rule.enabled) === 1 ? ' is-enabled' : ''}`;
            state.textContent = Number(rule.enabled) === 1 ? 'Enabled' : 'Disabled';
            heading.append(term, state);
            metadata.className = 'admin-global-chat-rule-meta';
            metadata.textContent = `${rule.category} · ${rule.match_type} · ${rule.severity} · ${rule.action}`;
            actions.className = 'admin-global-chat-actions';
            actions.append(
                makeButton('Edit', 'edit-rule', rule.id),
                makeButton(Number(rule.enabled) === 1 ? 'Disable' : 'Enable', 'toggle-rule', rule.id),
                makeButton('Delete', 'delete-rule', rule.id, 'is-danger')
            );
            card.append(heading, metadata, actions);
            rulesContainer.append(card);
        });
    }

    function renderCases(cases) {
        casesContainer.replaceChildren();
        if (!cases.length) {
            const empty = document.createElement('p');
            empty.className = 'admin-activity-empty';
            empty.textContent = 'No messages have been flagged.';
            casesContainer.append(empty);
            return;
        }

        cases.forEach(item => {
            const card = document.createElement('article');
            const heading = document.createElement('div');
            const rule = document.createElement('strong');
            const state = document.createElement('span');
            const context = document.createElement('p');
            const content = document.createElement('blockquote');
            const metadata = document.createElement('p');
            card.className = 'admin-global-chat-case';
            heading.className = 'admin-global-chat-case-heading';
            rule.textContent = item.matched_rule;
            state.className = `admin-global-chat-case-state is-${item.status}`;
            state.textContent = item.status.replaceAll('_', ' ');
            heading.append(rule, state);
            context.className = 'admin-global-chat-case-meta';
            context.textContent = `${item.category} · ${item.severity} · ${item.action}`;
            content.textContent = item.message_snapshot;
            metadata.className = 'admin-global-chat-case-meta';
            const date = new Date(String(item.created_at).replace(' ', 'T'));
            metadata.textContent = `${item.sender_name || 'Unknown sender'} · ${
                Number.isNaN(date.getTime()) ? item.created_at : date.toLocaleString()
            }`;
            card.append(heading, context, content, metadata);
            if (item.current_content !== item.message_snapshot) {
                const editedNotice = document.createElement('p');
                const currentContent = document.createElement('blockquote');
                editedNotice.className = 'admin-global-chat-case-meta';
                editedNotice.textContent = 'Current message content (edited after flag):';
                currentContent.textContent = item.current_content || '[message unavailable]';
                card.append(editedNotice, currentContent);
            }

            if (item.status === 'pending') {
                const reason = document.createElement('textarea');
                const actions = document.createElement('div');
                reason.className = 'admin-global-chat-decision-reason';
                reason.maxLength = 500;
                reason.placeholder = 'Decision reason (required)';
                reason.setAttribute('aria-label', `Decision reason for case ${item.id}`);
                actions.className = 'admin-global-chat-actions';
                actions.append(
                    makeButton('Mark reviewed', 'reviewed', item.id),
                    makeButton('Dismiss flag', 'dismissed', item.id),
                    makeButton('Restrict message', 'restricted', item.id, 'is-danger')
                );
                card.append(reason, actions);
            } else {
                const decision = document.createElement('p');
                decision.className = 'admin-global-chat-case-meta';
                decision.textContent = `${item.reviewer_name || 'Admin'}: ${item.decision_reason || 'No reason recorded'}`;
                card.append(decision);
            }
            casesContainer.append(card);
        });
    }

    async function load() {
        rulesContainer.innerHTML = '<p class="admin-activity-empty">Loading moderation rules…</p>';
        paginationContainer.replaceChildren();
        paginationContainer.hidden = true;
        casesContainer.innerHTML = '<p class="admin-activity-empty">Loading review queue…</p>';
        try {
            const data = await request();
            configuredRules = Array.isArray(data.rules) ? data.rules : [];
            filterRules();
            renderCases(Array.isArray(data.cases) ? data.cases : []);
            message.hidden = true;
        } catch (error) {
            rulesContainer.replaceChildren();
            casesContainer.replaceChildren();
            showMessage(error.message, 'error');
        }
    }

    function filterRules() {
        const query = rulesSearch.value.trim().toLocaleLowerCase();
        const filteredRules = query
            ? configuredRules.filter(rule => [
                rule.rule_text,
                rule.category,
                rule.match_type,
                rule.severity,
                rule.action,
                Number(rule.enabled) === 1 ? 'enabled' : 'disabled'
            ].some(value => String(value || '').toLocaleLowerCase().includes(query)))
            : configuredRules;
        renderRules(filteredRules);
    }

    function resetRuleForm() {
        ruleForm.reset();
        ruleForm.elements.rule_id.value = '';
        ruleForm.elements.match_type.value = 'word';
        ruleForm.elements.category.value = '';
        ruleForm.elements.severity.value = 'medium';
        ruleForm.elements.rule_action.value = 'review';
        ruleForm.elements.enabled.value = '1';
        saveButton.textContent = 'Save rule';
        cancelButton.hidden = true;
    }

    ruleForm.addEventListener('submit', async event => {
        event.preventDefault();
        const originalText = saveButton.textContent;
        saveButton.disabled = true;
        saveButton.textContent = 'Saving…';
        try {
            const fields = Object.fromEntries(new FormData(ruleForm).entries());
            fields.action = 'save_rule';
            await request(fields);
            showMessage('Global Chat rule saved.');
            resetRuleForm();
            await load();
        } catch (error) {
            showMessage(error.message, 'error');
        } finally {
            saveButton.disabled = false;
            if (!cancelButton.hidden) {
                saveButton.textContent = 'Save changes';
            } else {
                saveButton.textContent = originalText === 'Save changes'
                    ? 'Save rule'
                    : originalText;
            }
        }
    });

    cancelButton.addEventListener('click', resetRuleForm);
    rulesSearch.addEventListener('input', () => {
        currentRulePage = 1;
        filterRules();
    });
    root.querySelector('[data-moderation-refresh]').addEventListener('click', load);
    paginationContainer.addEventListener('click', event => {
        const button = event.target.closest('button[data-rule-page]');
        if (!button || button.disabled) {
            return;
        }
        const nextPage = Number(button.dataset.rulePage);
        const totalPages = Math.ceil(filteredRuleCount / rulesPerPage);
        if (Number.isInteger(nextPage) && nextPage >= 1 && nextPage <= totalPages) {
            currentRulePage = nextPage;
            filterRules();
        }
    });
    root.addEventListener('click', async event => {
        const button = event.target.closest('[data-moderation-action]');
        if (!button) {
            return;
        }
        const action = button.dataset.moderationAction;
        const id = button.dataset.moderationId;
        try {
            if (action === 'edit-rule' || action === 'toggle-rule') {
                const data = await request();
                const rule = (data.rules || []).find(item => String(item.id) === id);
                if (!rule) {
                    throw new Error('Moderation rule not found.');
                }
                if (action === 'edit-rule') {
                    ruleForm.elements.rule_id.value = rule.id;
                    ruleForm.elements.rule_text.value = rule.rule_text;
                    ruleForm.elements.match_type.value = rule.match_type;
                    ensureCategoryOption(rule.category);
                    ruleForm.elements.category.value = rule.category;
                    ruleForm.elements.severity.value = rule.severity;
                    ruleForm.elements.rule_action.value = rule.action;
                    ruleForm.elements.enabled.value = Number(rule.enabled) === 1 ? '1' : '0';
                    saveButton.textContent = 'Save changes';
                    cancelButton.hidden = false;
                    ruleForm.elements.rule_text.focus();
                    return;
                }
                await request({
                    action: 'save_rule',
                    rule_id: rule.id,
                    rule_text: rule.rule_text,
                    match_type: rule.match_type,
                    category: rule.category,
                    severity: rule.severity,
                    rule_action: rule.action,
                    enabled: Number(rule.enabled) === 1 ? '0' : '1'
                });
                showMessage(`Rule ${Number(rule.enabled) === 1 ? 'disabled' : 'enabled'}.`);
                await load();
                return;
            }

            if (action === 'delete-rule') {
                if (!window.confirm('Delete this Global Chat moderation rule?')) {
                    return;
                }
                await request({action: 'delete_rule', rule_id: id});
                showMessage('Global Chat rule deleted.');
                await load();
                return;
            }

            if (['reviewed', 'dismissed', 'restricted'].includes(action)) {
                const card = button.closest('.admin-global-chat-case');
                const reason = card.querySelector('.admin-global-chat-decision-reason')?.value.trim() || '';
                if (!reason) {
                    showMessage('Enter a reason before recording this decision.', 'error');
                    return;
                }
                if (
                    action === 'restricted'
                    && !window.confirm('Restrict this Global Chat message? It will be hidden from the conversation.')
                ) {
                    return;
                }
                await request({action: 'review_case', case_id: id, decision: action, reason});
                showMessage('Moderation decision recorded.');
                await load();
            }
        } catch (error) {
            showMessage(error.message, 'error');
        }
    });

    load();
})();

</script>
