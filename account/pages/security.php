<?php


require_once __DIR__ . '/../../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'account_settings');
require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/csrf.php';

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Session expired</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {
    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Invalid session</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}

try {
    $stmt = $pdo->prepare("
        SELECT
            u.email,
            u.email_verified_at,
            u.language,
            u.totp_enabled_at,
            u.totp_login_required
        FROM users u
        WHERE u.id = ?
        LIMIT 1
    ");

    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) {
        http_response_code(404);

        echo '
            <div class="portal-error">
                <h2>User account not found</h2>
                <p>Please contact VNZ TECH.</p>
            </div>
        ';

        exit;
    }
} catch (Throwable $e) {
    error_log(
        'Unable to load seller security: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load security</h2>
            <p>Security could not be loaded. Please try again later.</p>
        </div>
    ';

    exit;
}

function securityEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$csrfToken = generateCsrfToken();

?>
<section class="seller-profile-page seller-security-page"><div class="seller-profile-header"><div><span class="eyebrow">ACCOUNT</span><h1>Security</h1><p>Manage your password, authenticator and login protection.</p></div></div>
<section class="profile-form-section"><div class="profile-section-title">Sign-in and recovery</div><p class="settings-supporting-text">Password changes and authenticator setup require your current local password. If you signed up using Google or Facebook without adding a password, continue using that provider. Use the existing <a href="/VNZ-TECH/account/forgot_password.php">password recovery</a> flow if you need a local password; available recovery methods depend on your verified email and authenticator setup.</p></section>
    <form
        id="sellerPasswordForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_password.php"
        method="POST"
    >
        <input
            type="hidden"
            name="csrf_token"
            value="<?= securityEscape($csrfToken) ?>"
        >

        <div
            id="passwordMessage"
            class="profile-message"
            role="status"
            aria-live="polite"
        ></div>

        <section class="profile-form-section">
            <div class="profile-section-title">
                Change Password
            </div>

            <div class="profile-form-grid">
                <div class="profile-form-group profile-form-full">
                    <label for="current_password">Current Password</label>
                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >
                </div>

                <div class="profile-form-group">
                    <label for="new_password">New Password</label>
                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                    <small>At least 8 characters.</small>
                </div>

                <div class="profile-form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >
                </div>
            </div>

            <div class="profile-form-actions">
                <button
                    type="submit"
                    class="profile-save-button"
                    id="sellerPasswordSubmit"
                >
                    UPDATE PASSWORD
                </button>
            </div>
        </section>
    </form>
    <section class="profile-form-section seller-2fa-section">
        <div class="profile-section-title">
            Two-Factor Authentication
        </div>
        <p class="settings-supporting-text">
            <?= !empty($user['totp_enabled_at'])
                ? 'Authenticator is set up and can be used for account recovery. Requiring its code at every login is optional.'
                : 'Set up an authenticator app for account recovery. Requiring its code at every login is a separate choice.' ?>
        </p>
        <div class="seller-2fa-status <?= !empty($user['totp_enabled_at']) ? 'is-enabled' : 'is-disabled' ?>">
            <?= !empty($user['totp_enabled_at']) ? 'Authenticator enabled' : 'Authenticator not set up' ?>
        </div>
        <?php if (!empty($user['totp_enabled_at'])): ?>
            <form id="seller2faLoginRequirementForm" class="seller-2fa-form" action="/VNZ-TECH/backend/seller_2fa.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="set_login_requirement">
                <div class="seller-2fa-controls">
                    <div class="seller-2fa-login-choice">
                        <label for="seller2faLoginRequired">Authenticator code at every login</label>
                        <select id="seller2faLoginRequired" name="login_required">
                            <option value="1" <?= (int) $user['totp_login_required'] === 1 ? 'selected' : '' ?>>Yes, require the 6-digit code</option>
                            <option value="0" <?= (int) $user['totp_login_required'] !== 1 ? 'selected' : '' ?>>No, use password-only login</option>
                        </select>
                    </div>
                    <button class="profile-save-button seller-preference-save-button" type="submit">SAVE LOGIN PREFERENCE</button>
                </div>
                <small class="seller-login-preference-help">Your authenticator stays set up for password recovery when login codes are off.</small>
            </form>
        <?php endif; ?>
        <div
            id="seller2faMessage"
            class="profile-message"
            role="status"
            aria-live="polite"
        ></div>
        <?php if (empty($user['totp_enabled_at'])): ?>
            <form id="seller2faSetupForm" class="seller-2fa-form" action="/VNZ-TECH/backend/seller_2fa.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="setup">
                <div class="seller-2fa-controls">
                    <label for="seller2faCurrentPassword">Confirm your current password</label>
                    <input id="seller2faCurrentPassword" name="current_password" type="password" autocomplete="current-password" required>
                    <button class="profile-save-button" type="submit">SET UP AUTHENTICATOR</button>
                </div>
            </form>
            <div id="seller2faSetupStep" class="seller-2fa-setup-step" hidden></div>
        <?php else: ?>
            <form id="seller2faDisableForm" class="seller-2fa-form" action="/VNZ-TECH/backend/seller_2fa.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= securityEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="disable">
                <div class="seller-2fa-controls seller-2fa-disable-controls">
                    <div>
                        <label for="seller2faDisablePassword">Current password</label>
                        <input id="seller2faDisablePassword" name="current_password" type="password" autocomplete="current-password" required>
                    </div>
                    <div>
                        <label for="seller2faDisableCode">Authenticator code</label>
                        <input id="seller2faDisableCode" name="two_factor_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                    </div>
                    <button class="profile-save-button seller-2fa-disable-button" type="submit">DISABLE 2FA</button>
                </div>
            </form>
        <?php endif; ?>
    </section>


</section>
<script>
(function () {
const passwordForm=document.getElementById("sellerPasswordForm");
const passwordMessage=document.getElementById("passwordMessage");
    if (passwordForm && passwordMessage) {
        passwordForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            passwordMessage.className = 'profile-message';
            passwordMessage.textContent = '';

            if (
                passwordForm.querySelector('#new_password').value !==
                passwordForm.querySelector('#confirm_password').value
            ) {
                passwordMessage.classList.add('show', 'error');
                passwordMessage.textContent =
                    'New password and confirmation do not match.';
                return;
            }

            const button = document.getElementById('sellerPasswordSubmit');
            button.disabled = true;
            button.textContent = 'UPDATING...';

            try {
                const response = await fetch(passwordForm.action, {
                    method: 'POST',
                    body: new FormData(passwordForm),
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to update your password.');
                }
                passwordMessage.classList.add('show', 'success');
                passwordMessage.textContent = result.message || 'Password updated successfully.';
                passwordForm.reset();
            } catch (error) {
                passwordMessage.classList.add('show', 'error');
                passwordMessage.textContent = error.message || 'Something went wrong.';
            } finally {
                button.disabled = false;
                button.textContent = 'UPDATE PASSWORD';
            }
        });
    }
    const twoFactorMessage = document.getElementById('seller2faMessage');
    const setupForm = document.getElementById('seller2faSetupForm');
    const disableForm = document.getElementById('seller2faDisableForm');
    const loginRequirementForm = document.getElementById('seller2faLoginRequirementForm');
    const setupStep = document.getElementById('seller2faSetupStep');

    async function submitTwoFactorForm(form, action) {
        const button = form.querySelector('button[type="submit"]');
        const initialText = button.textContent;
        button.disabled = true;
        button.textContent = 'PLEASE WAIT...';
        twoFactorMessage.className = 'profile-message';
        twoFactorMessage.textContent = '';

        try {
            const endpoint = form.getAttribute('action');
            if (!endpoint) {
                throw new Error('The 2FA settings endpoint is missing.');
            }

            const response = await fetch(endpoint, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'same-origin'
            });
            const responseText = await response.text();
            let result;
            try {
                result = JSON.parse(responseText);
            } catch (parseError) {
                console.error('Seller 2FA endpoint returned a non-JSON response.', {
                    status: response.status,
                    contentType: response.headers.get('content-type'),
                    response: responseText.slice(0, 1000)
                });
                throw new Error(
                    response.redirected
                        ? 'Your seller session expired. Sign in again, then retry 2FA setup.'
                        : `The server returned an unexpected response (HTTP ${response.status}). Refresh Security and try again.`
                );
            }
            if (!response.ok || !result.success) {
                throw new Error(result.message || 'Unable to update two-factor authentication.');
            }

            if (action === 'setup') {
                setupStep.innerHTML = `
                    <p class="settings-supporting-text">Scan this QR code with your authenticator app. Then enter its current 6-digit code to enable two-factor authentication.</p>
                    <div class="seller-2fa-qr" data-seller-2fa-qr></div>
                    <details class="seller-2fa-manual">
                        <summary>Can't scan? Enter the setup key manually</summary>
                        <code data-seller-2fa-secret></code>
                    </details>
                    <form data-seller-2fa-confirm action="/VNZ-TECH/backend/seller_2fa.php" method="POST">
                        <input type="hidden" name="csrf_token" value="${form.elements.csrf_token.value}">
                        <input type="hidden" name="action" value="confirm_setup">
                        <label for="seller2faConfirmCode">Authenticator code</label>
                        <input id="seller2faConfirmCode" name="two_factor_code" type="text" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" autocomplete="one-time-code" required>
                        <button class="profile-save-button" type="submit">VERIFY AND ENABLE</button>
                    </form>
                `;
                setupStep.querySelector('[data-seller-2fa-secret]').textContent = result.secret;
                try {
                    const { qrcode } = await import('/VNZ-TECH/assets/js/qrcode-generator.mjs');
                    const qrCode = qrcode(0, 'M');
                    qrCode.addData(result.otpauth_uri);
                    qrCode.make();
                    setupStep.querySelector('[data-seller-2fa-qr]').innerHTML =
                        qrCode.createSvgTag(5, 4, 'Authenticator setup QR code');
                } catch (error) {
                    const qrContainer = setupStep.querySelector('[data-seller-2fa-qr]');
                    qrContainer.textContent = 'The QR code could not be generated. Use the manual setup key below.';
                    qrContainer.setAttribute('role', 'alert');
                }
                setupStep.querySelector('[data-seller-2fa-confirm]').addEventListener('submit', event => {
                    event.preventDefault();
                    submitTwoFactorForm(event.currentTarget, 'confirm_setup');
                });
                form.hidden = true;
                setupStep.hidden = false;
                return;
            }

            twoFactorMessage.classList.add('show', 'success');
            twoFactorMessage.textContent = result.message;
            window.location.href = '/VNZ-TECH/account/dashboard.php?page=security';
        } catch (error) {
            twoFactorMessage.classList.add('show', 'error');
            twoFactorMessage.textContent = error.message || 'Unable to update two-factor authentication.';
        } finally {
            button.disabled = false;
            button.textContent = initialText;
        }
    }

    if (setupForm) {
        setupForm.addEventListener('submit', event => {
            event.preventDefault();
            submitTwoFactorForm(event.currentTarget, 'setup');
        });
    }
    if (disableForm) {
        disableForm.addEventListener('submit', event => {
            event.preventDefault();
            submitTwoFactorForm(event.currentTarget, 'disable');
        });
    }
    if (loginRequirementForm) {
        loginRequirementForm.addEventListener('submit', event => {
            event.preventDefault();
            submitTwoFactorForm(event.currentTarget, 'set_login_requirement');
        });
    }

})();
</script>
