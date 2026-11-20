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
?>
<link rel="stylesheet" href="/VNZ-TECH/assets/css/admin_operations.css?v=<?= filemtime(__DIR__ . '/../../assets/css/admin_operations.css') ?>">
<div class="admin-settings-page"><header class="operations-header"><div><span class="operations-eyebrow">ACCOUNT</span><h1>Profile</h1><p>Manage your own administrator details. Your role is assigned by an authorized administrator.</p></div></header>
<div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>
<div class="operations-settings-grid admin-profile-page-grid">
        <section class="operations-settings-card admin-profile-card">
            <h2>Admin profile</h2>
            <p>These details identify your account in the VNZ TECH admin panel.</p>

<div class="operations-settings-meta"><span>Role</span><strong><?= htmlspecialchars($currentAdmin['role_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8') ?></strong></div>
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
</div></div>
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
                    const userLabel = document.querySelector('.seller-topbar-profile-name');
                    if (userLabel) {
                        userLabel.textContent = result.admin_full_name || result.admin_name;
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
