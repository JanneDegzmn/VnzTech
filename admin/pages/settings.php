<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';
require_once __DIR__ . '/../../backend/csrf.php';

requireAdminLogin();

if (!hasPermission('manage_settings')) {
    http_response_code(403);
    ?>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css">
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

?>

<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css">

<div class="admin-settings-page">
    <header class="operations-header">
        <div>
            <span class="operations-eyebrow">ACCOUNT & SECURITY</span>
            <h1>Settings</h1>
            <p>Manage your administrator profile and sign-in password.</p>
        </div>
    </header>

    <div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>

    <div class="operations-settings-grid">
        <section class="operations-settings-card">
            <h2>Admin profile</h2>
            <p>These details identify your account in the VNZ TECH admin panel.</p>

            <form data-admin-settings-form>
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

        <section class="operations-settings-card">
            <h2>Change password</h2>
            <p>Confirm your current password before choosing a new one.</p>

            <form data-admin-settings-form>
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
})();
</script>