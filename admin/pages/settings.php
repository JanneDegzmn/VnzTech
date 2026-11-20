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
<div class="admin-settings-page"><header class="operations-header"><div><span class="operations-eyebrow">ACCOUNT</span><h1>Settings</h1><p>Choose the appearance of your admin panel.</p></div></header>
<div class="operations-form-message" id="adminSettingsMessage" hidden role="status"></div>
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
</div></div>
