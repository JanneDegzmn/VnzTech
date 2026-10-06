<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';

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
    LIMIT 50
");
$recentActivity = $activityStmt->fetchAll();

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

    <div class="operations-settings-grid">
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