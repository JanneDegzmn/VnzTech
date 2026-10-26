<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/pagination.php';

requireAdminLogin();

if (!hasPermission('manage_settings')) {
    http_response_code(403);
    ?>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
    <div class="admin-settings-page">
        <div class="admin-report-empty">
            <h2>Access denied</h2>
            <p>You do not have permission to manage admin settings.</p>
        </div>
    </div>
    <?php
    exit;
}

$currentAdmin = getCurrentAdmin();
$csrfToken = generateCsrfToken();
$activityPagination = vnzPaginationClamp(
    vnzPaginationState(12),
    (int) $pdo->query('SELECT COUNT(*) FROM admin_activity_logs')->fetchColumn()
);
$activityStmt = $pdo->query("
    SELECT
        l.action,
        l.details,
        l.ip_address,
        l.created_at,
        a.first_name,
        a.last_name,
        a.username
    FROM admin_activity_logs l
    LEFT JOIN admins a ON a.id = l.admin_id
    ORDER BY l.created_at DESC, l.id DESC
    LIMIT {$activityPagination['per_page']} OFFSET {$activityPagination['offset']}
");
$recentActivity = $activityStmt->fetchAll();
$activityPaginationHtml = vnzRenderPagination(
    $activityPagination,
    [],
    'settings',
    'admin',
    '/VNZ-TECH/admin/dashboard.php'
);

?>

<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">

<div class="admin-settings-page">
    <header class="operations-header">
        <div>
            <span class="operations-eyebrow">ACCOUNT & SECURITY</span>
            <h1>Settings</h1>
            <p>Manage your administrator profile, sign-in security, and review recent admin activity.</p>
        </div>
    </header>

    <div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>

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
            </section>
            <section class="admin-global-chat-moderation-column" aria-labelledby="globalChatReviewHeading">
                <h3 id="globalChatReviewHeading">Flagged messages</h3>
                <div class="admin-global-chat-cases" data-moderation-cases>
                    <p class="admin-activity-empty">Loading review queue…</p>
                </div>
            </section>
        </div>
    </section>

    <div class="operations-settings-grid">
        <section class="operations-settings-card admin-appearance-card">
            <div class="admin-appearance-copy">
                <h2>Appearance</h2>
                <p>Choose the display theme for your admin panel. This preference is saved in this browser.</p>
            </div>
            <div class="operations-field">
                <label for="adminThemePreference">Admin theme</label>
                <select id="adminThemePreference" data-admin-theme-select>
                    <option value="dark">Dark mode</option>
                    <option value="light">Light mode</option>
                </select>
            </div>
        </section>

        <section class="operations-settings-card admin-profile-card">
            <h2>Admin profile</h2>
            <p>These details identify your account in the VNZ TECH admin panel.</p>

            <form class="admin-settings-form" data-admin-settings-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="profile">

                <div class="operations-form-grid">
                    <div class="operations-field">
                        <label for="adminFirstName">First name</label>
                        <input id="adminFirstName" name="first_name" type="text" maxlength="80" required value="<?= htmlspecialchars($currentAdmin['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="operations-field">
                        <label for="adminLastName">Last name</label>
                        <input id="adminLastName" name="last_name" type="text" maxlength="80" required value="<?= htmlspecialchars($currentAdmin['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="operations-field">
                        <label for="adminUsername">Username</label>
                        <input id="adminUsername" name="username" type="text" maxlength="60" autocomplete="username" required value="<?= htmlspecialchars($currentAdmin['username'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="operations-field">
                        <label for="adminEmail">Email</label>
                        <input id="adminEmail" name="email" type="email" maxlength="150" autocomplete="email" required value="<?= htmlspecialchars($currentAdmin['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>

                <div class="operations-form-actions">
                    <button class="operations-button" type="submit">Save profile</button>
                </div>
            </form>
        </section>

        <section class="operations-settings-card admin-password-card">
            <h2>Change password</h2>
            <p>Confirm your current password before choosing a new one.</p>

            <form class="admin-settings-form" data-admin-settings-form>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" value="password">

                <div class="operations-field">
                    <label for="adminCurrentPassword">Current password</label>
                    <input id="adminCurrentPassword" name="current_password" type="password" autocomplete="current-password" required>
                </div>
                <div class="operations-form-grid" style="margin-top: 13px;">
                    <div class="operations-field">
                        <label for="adminNewPassword">New password</label>
                        <input id="adminNewPassword" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                    <div class="operations-field">
                        <label for="adminConfirmPassword">Confirm new password</label>
                        <input id="adminConfirmPassword" name="confirm_password" type="password" minlength="8" autocomplete="new-password" required>
                    </div>
                </div>

                <div class="operations-form-actions">
                    <button class="operations-button" type="submit">Update password</button>
                </div>
            </form>

            <div class="operations-settings-meta">
                <span>Role</span>
                <strong><?= htmlspecialchars($currentAdmin['role_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
        </section>

        <section class="operations-settings-card admin-2fa-card">
            <h2>Two-factor authentication</h2>
            <p>An authenticator can be used for password recovery. Requiring its code at every sign-in is your choice.</p>

            <div class="admin-2fa-layout">
            <?php if (!empty($currentAdmin['totp_enabled_at'])): ?>
                <div class="admin-2fa-status is-enabled">
                    <span class="admin-2fa-status-indicator" aria-hidden="true"></span>
                    <span><strong>Authenticator enabled.</strong> You can keep it for account recovery without requiring a code at every sign-in.</span>
                </div>
                <form class="admin-2fa-form admin-2fa-login-preference" data-admin-2fa-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="set_2fa_login_requirement">
                    <div class="admin-2fa-preference-field">
                        <label for="admin2faLoginRequired">Authenticator code at every login</label>
                        <select id="admin2faLoginRequired" name="login_required">
                            <option value="1" <?= (int) ($currentAdmin['totp_login_required'] ?? 0) === 1 ? 'selected' : '' ?>>Yes, require the 6-digit code</option>
                            <option value="0" <?= (int) ($currentAdmin['totp_login_required'] ?? 0) !== 1 ? 'selected' : '' ?>>No, use password-only login</option>
                        </select>
                        <small>Your authenticator remains set up for account recovery when login codes are off.</small>
                    </div>
                    <div class="operations-form-actions">
                        <button class="operations-button" type="submit">Save login preference</button>
                    </div>
                </form>
                <form class="admin-2fa-form" data-admin-2fa-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="disable_2fa">
                    <div class="operations-form-grid">
                        <div class="operations-field">
                            <label for="disable2faPassword">Current password</label>
                            <input id="disable2faPassword" name="current_password" type="password" autocomplete="current-password" required>
                        </div>
                        <div class="operations-field">
                            <label for="disable2faCode">Authenticator or recovery code</label>
                            <input id="disable2faCode" name="two_factor_code" type="text" pattern="([0-9]{6}|[A-Fa-f0-9]{4}(-[A-Fa-f0-9]{4}){3})" maxlength="19" autocomplete="one-time-code" required>
                        </div>
                    </div>
                    <div class="operations-form-actions">
                        <button class="operations-button operations-button-secondary" type="submit">Disable 2FA</button>
                    </div>
                </form>
            <?php else: ?>
                <div class="admin-2fa-status is-disabled">
                    <span class="admin-2fa-status-indicator" aria-hidden="true"></span>
                    <span><strong>Authenticator not set up.</strong> Set up an authenticator for password recovery. You can separately choose whether a code is required at every sign-in.</span>
                </div>
                <form class="admin-2fa-form" data-admin-2fa-form>
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="action" value="setup_2fa">
                    <div class="operations-field">
                        <label for="setup2faPassword">Confirm current password</label>
                        <input id="setup2faPassword" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div class="operations-form-actions">
                        <button class="operations-button" type="submit">Set up authenticator</button>
                    </div>
                </form>
            <?php endif; ?>
                <div id="admin2faSetupStep" hidden></div>
            </div>
        </section>

        <section class="operations-settings-card admin-activity-card">
            <h2>Recent admin activity</h2>
            <p>Latest sign-ins and security or settings changes across admin accounts.</p>

            <?php if ($activityPagination['last_page'] > 1): ?>
                <?= $activityPaginationHtml ?>
            <?php endif; ?>

            <?php if (!$recentActivity): ?>
                <div class="admin-activity-empty">No admin activity has been recorded yet.</div>
            <?php else: ?>
                <div class="admin-activity-table-wrap">
                    <table class="admin-activity-table">
                        <thead>
                            <tr>
                                <th scope="col">Admin</th>
                                <th scope="col">Activity</th>
                                <th scope="col">Details</th>
                                <th scope="col">IP address</th>
                                <th scope="col">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentActivity as $activity): ?>
                                <tr>
                                    <td><?= htmlspecialchars(trim(($activity['first_name'] ?? '') . ' ' . ($activity['last_name'] ?? '')) ?: ($activity['username'] ?? 'Unknown admin'), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['action'], ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['details'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($activity['ip_address'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(date('M d, Y h:i A', strtotime($activity['created_at'])), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </div>
</div>

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
    const casesContainer = root.querySelector('[data-moderation-cases]');
    const message = root.querySelector('[data-moderation-message]');
    const saveButton = root.querySelector('[data-moderation-save-rule]');
    const cancelButton = root.querySelector('[data-moderation-cancel-edit]');
    const categorySelect = ruleForm.elements.category;
    let configuredRules = [];

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

    function renderRules(rules) {
        rules.forEach(rule => ensureCategoryOption(rule.category));
        rulesContainer.replaceChildren();
        if (!rules.length) {
            const empty = document.createElement('p');
            empty.className = 'admin-activity-empty';
            empty.textContent = configuredRules.length
                ? 'No rules match your search.'
                : 'No moderation rules are configured.';
            rulesContainer.append(empty);
            return;
        }

        rules.forEach(rule => {
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
    rulesSearch.addEventListener('input', filterRules);
    root.querySelector('[data-moderation-refresh]').addEventListener('click', load);
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

(function () {
    const forms = document.querySelectorAll('[data-admin-settings-form]');
    const message = document.getElementById('adminSettingsMessage');

    if (!forms.length || !message) {
        return;
    }

    forms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const submitButton = form.querySelector('button[type="submit"]');
            const originalText = submitButton.textContent;
            submitButton.disabled = true;
            submitButton.textContent = 'Saving...';
            message.hidden = true;

            try {
                const response = await fetch('/VNZ-TECH/backend/update_admin_settings.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams(new FormData(form)).toString()
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to save settings.');
                }

                message.textContent = result.message;
                message.dataset.kind = 'success';
                message.hidden = false;

                if (result.admin_name) {
                    const userLabel = document.querySelector('.topbar-user');
                    if (userLabel) {
                        userLabel.textContent = result.admin_name;
                    }
                }

                if (result.admin_full_name) {
                    const sidebarName = document.querySelector('.user-name');
                    if (sidebarName) {
                        sidebarName.textContent = result.admin_full_name;
                    }
                }

                if (form.elements.action.value === 'password') {
                    form.reset();
                }
            } catch (error) {
                message.textContent = error.message;
                message.dataset.kind = 'error';
                message.hidden = false;
            } finally {
                submitButton.disabled = false;
                submitButton.textContent = originalText;
            }
        });
    });

    const twoFactorForms = document.querySelectorAll('[data-admin-2fa-form]');
    const setupStep = document.getElementById('admin2faSetupStep');

    twoFactorForms.forEach((form) => {
        form.addEventListener('submit', async (event) => {
            event.preventDefault();

            const button = form.querySelector('button[type="submit"]');
            const originalText = button.textContent;
            button.disabled = true;
            button.textContent = 'Please wait...';
            message.hidden = true;

            try {
                const response = await fetch('/VNZ-TECH/backend/update_admin_settings.php', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                    },
                    body: new URLSearchParams(new FormData(form)).toString()
                });
                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to update two-factor authentication.');
                }

                if (result.action === 'setup_started') {
                    if (!setupStep) {
                        throw new Error('The authenticator setup panel is unavailable. Reload Settings and try again.');
                    }

                    setupStep.hidden = false;
                    setupStep.className = 'admin-2fa-setup-step';
                    setupStep.innerHTML = `
                        <h3>Connect your authenticator app</h3>
                        <p>Scan this QR code with your authenticator app. Then enter the current 6-digit code to confirm setup.</p>
                        <div class="admin-2fa-qr" data-admin-2fa-qr role="img" aria-label="Authenticator setup QR code"></div>
                        <details class="admin-2fa-manual-key">
                            <summary>Can't scan? Enter the setup key manually</summary>
                            <code id="admin2faSecret"></code>
                        </details>
                        <form data-admin-2fa-confirm>
                            <input type="hidden" name="csrf_token" value="${form.elements.csrf_token.value}">
                            <input type="hidden" name="action" value="confirm_2fa">
                            <div class="operations-field">
                                <label for="confirm2faCode">Authenticator code</label>
                                <input id="confirm2faCode" name="two_factor_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                            </div>
                            <div class="operations-form-actions">
                                <button class="operations-button" type="submit">Verify and enable</button>
                            </div>
                        </form>
                    `;
                    setupStep.querySelector('#admin2faSecret').textContent = result.secret;
                    try {
                        const { qrcode } = await import('/VNZ-TECH/assets/js/qrcode-generator.mjs');
                        const qrCode = qrcode(0, 'M');
                        qrCode.addData(result.otpauth_uri);
                        qrCode.make();
                        setupStep.querySelector('[data-admin-2fa-qr]').innerHTML =
                            qrCode.createSvgTag(5, 4, 'Authenticator setup QR code');
                    } catch (error) {
                        const qrContainer = setupStep.querySelector('[data-admin-2fa-qr]');
                        qrContainer.textContent = 'The QR code could not be generated. Use the manual setup key below.';
                        qrContainer.setAttribute('role', 'alert');
                    }
                    setupStep.querySelector('[data-admin-2fa-confirm]').addEventListener('submit', confirmEvent => {
                        confirmEvent.preventDefault();
                        submitTwoFactorConfirmation(confirmEvent.currentTarget);
                    });
                    form.hidden = true;
                    return;
                }

                message.textContent = result.message;
                message.dataset.kind = 'success';
                message.hidden = false;
                window.setTimeout(() => window.loadAdminPage('settings'), 700);
            } catch (error) {
                message.textContent = error.message;
                message.dataset.kind = 'error';
                message.hidden = false;
            } finally {
                button.disabled = false;
                button.textContent = originalText;
            }
        });
    });

    async function submitTwoFactorConfirmation(form) {
        const button = form.querySelector('button[type="submit"]');
        const originalText = button.textContent;
        button.disabled = true;
        button.textContent = 'Verifying...';
        message.hidden = true;

        try {
            const response = await fetch('/VNZ-TECH/backend/update_admin_settings.php', {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
                },
                body: new URLSearchParams(new FormData(form)).toString()
            });
            const result = await response.json();

            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to verify the authenticator code.');
            }

            if (Array.isArray(result.recovery_codes) && setupStep) {
                setupStep.innerHTML = `
                    <h3>Save your recovery codes</h3>
                    <p>Each code can be used once if you cannot access your authenticator. These codes will not be shown again.</p>
                    <ul class="admin-2fa-recovery-codes"></ul>
                    <button type="button" class="operations-button" data-finish-2fa-setup>I saved these codes</button>
                `;
                const codeList = setupStep.querySelector('.admin-2fa-recovery-codes');
                result.recovery_codes.forEach(code => {
                    const item = document.createElement('li');
                    const codeElement = document.createElement('code');
                    codeElement.textContent = code;
                    item.append(codeElement);
                    codeList.append(item);
                });
                setupStep.querySelector('[data-finish-2fa-setup]').addEventListener('click', () => {
                    message.textContent = result.message;
                    message.dataset.kind = 'success';
                    message.hidden = false;
                    window.loadAdminPage('settings');
                });
                return;
            }

            message.textContent = result.message;
            message.dataset.kind = 'success';
            message.hidden = false;
            window.setTimeout(() => window.loadAdminPage('settings'), 700);
        } catch (error) {
            message.textContent = error.message;
            message.dataset.kind = 'error';
            message.hidden = false;
        } finally {
            button.disabled = false;
            button.textContent = originalText;
        }
    }
})();
</script>