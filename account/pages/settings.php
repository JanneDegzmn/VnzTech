<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/csrf.php';
require_once __DIR__ . '/../../backend/email_verification.php';

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
            u.totp_login_required,
            COALESCE(p.submission_updates, 0) AS submission_updates,
            COALESCE(p.offer_updates, 0) AS offer_updates,
            COALESCE(p.transaction_updates, 0) AS transaction_updates
        FROM users u
        LEFT JOIN seller_notification_preferences p
            ON p.user_id = u.id
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
        'Unable to load seller settings: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load settings</h2>
            <p>Settings could not be loaded. Please try again later.</p>
        </div>
    ';

    exit;
}

function settingsEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

$emailVerified =
    !empty($user['email_verified_at']);

$csrfToken =
    generateCsrfToken();

$verificationNotice =
    ($_GET['verification'] ?? '') === 'success'
        ? 'Your email address has been verified.'
        : '';

?>

<section class="seller-profile-page">

    <div class="seller-profile-header">

        <div>
            <span class="eyebrow">
                ACCOUNT
            </span>

            <h1>
                Settings
            </h1>

            <p>
                Manage your account security and notification preferences.
            </p>
        </div>

    </div>


    <form
        id="sellerLanguageForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_seller_language.php"
        method="POST"
    >
        <input type="hidden" name="csrf_token" value="<?= settingsEscape($csrfToken) ?>">
        <section class="profile-form-section">
            <div class="profile-section-title">Language</div>
            <p class="settings-supporting-text">
                Choose English or casual Taglish. Your choice is saved automatically.
            </p>
            <div class="profile-form-grid">
                <div class="profile-form-group">
                    <label for="sellerLanguage">Preferred Language</label>
                    <select id="sellerLanguage" name="language">
                        <option value="en" <?= ($user['language'] ?? 'en') === 'en' ? 'selected' : '' ?>>English</option>
                        <option value="fil" <?= ($user['language'] ?? '') === 'fil' ? 'selected' : '' ?>>Filipino (Taglish)</option>
                    </select>
                </div>
            </div>
            <div id="sellerLanguageMessage" class="profile-message" role="status" aria-live="polite"></div>
            <div class="profile-form-actions">
                <button type="submit" class="profile-save-button" id="sellerLanguageSubmit">
                    SAVE LANGUAGE
                </button>
            </div>
        </section>
    </form>

    <section class="profile-form-section">
        <div class="profile-section-title">Appearance</div>
        <p class="settings-supporting-text">
            Choose how the Seller portal looks. This setting is saved in this browser.
        </p>
        <div class="profile-form-grid">
            <div class="profile-form-group">
                <label for="sellerThemePreference">Theme</label>
                <select id="sellerThemePreference" name="theme">
                    <option value="dark">Dark mode</option>
                    <option value="light">Light mode</option>
                </select>
            </div>
        </div>
    </section>

    <section class="profile-form-section settings-email-card">

        <div class="profile-section-title">
            Email Address
        </div>

        <div class="settings-email-row">

            <div>
                <strong class="settings-email-address">
                    <?= settingsEscape($user['email']) ?>
                </strong>

                <p class="settings-supporting-text">
                    Your email is used for account-related communication.
                </p>
            </div>

            <span class="settings-verification-badge <?= $emailVerified ? 'is-verified' : 'is-unverified' ?>">
                <?= $emailVerified ? 'Verified' : 'Not verified' ?>
            </span>

        </div>

        <?php if ($verificationNotice !== ''): ?>
            <div class="profile-message show success" role="status">
                <?= settingsEscape($verificationNotice) ?>
            </div>
        <?php endif; ?>

        <?php if (!$emailVerified): ?>
            <p class="settings-inline-notice">
                This email address has not been verified. The verification link
                expires after 30 minutes and can only be used once.
            </p>

            <form
                id="sellerEmailVerificationForm"
                class="settings-verification-form"
                action="/VNZ-TECH/backend/request_email_verification.php"
                method="POST"
            >
                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= settingsEscape($csrfToken) ?>"
                >

                <div
                    id="verificationMessage"
                    class="profile-message"
                    role="status"
                    aria-live="polite"
                ></div>

                <button
                    type="submit"
                    class="profile-save-button"
                    id="sellerVerificationSubmit"
                >
                    SEND VERIFICATION EMAIL
                </button>
            </form>
        <?php endif; ?>

    </section>

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
                <input type="hidden" name="csrf_token" value="<?= settingsEscape($csrfToken) ?>">
                <input type="hidden" name="action" value="set_login_requirement">
                <div class="seller-2fa-controls">
                    <div class="seller-2fa-login-choice">
                        <label for="seller2faLoginRequired">Authenticator code at every login</label>
                        <select id="seller2faLoginRequired" name="login_required">
                            <option value="1" <?= (int) $user['totp_login_required'] === 1 ? 'selected' : '' ?>>Yes, require the 6-digit code</option>
                            <option value="0" <?= (int) $user['totp_login_required'] !== 1 ? 'selected' : '' ?>>No, use password-only login</option>
                        </select>
                        <small>Your authenticator stays set up for password recovery when login codes are off.</small>
                    </div>
                    <button class="profile-save-button" type="submit">SAVE LOGIN PREFERENCE</button>
                </div>
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
                <input type="hidden" name="csrf_token" value="<?= settingsEscape($csrfToken) ?>">
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
                <input type="hidden" name="csrf_token" value="<?= settingsEscape($csrfToken) ?>">
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


    <form
        id="sellerNotificationForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_seller_notification_preferences.php"
        method="POST"
    >

        <input
            type="hidden"
            name="csrf_token"
            value="<?= settingsEscape($csrfToken) ?>"
        >

        <div
            id="notificationMessage"
            class="profile-message"
            role="status"
            aria-live="polite"
        ></div>

        <div class="profile-form-section">

            <div class="profile-section-title">
                Email Notifications
            </div>

            <p class="settings-supporting-text settings-section-description">
                Save which updates you want by email. Email updates require a
                verified email address and configured mail delivery.
                In-app updates will continue to appear in your seller portal.
            </p>

            <div class="settings-preference-list">

                <label class="settings-preference">
                    <span>
                        <strong>Submission updates</strong>
                        <small>When a submission is reviewed or its status changes.</small>
                    </span>
                    <input
                        type="checkbox"
                        name="submission_updates"
                        value="1"
                        <?= (int) $user['submission_updates'] === 1 ? 'checked' : '' ?>
                    >
                </label>

                <label class="settings-preference">
                    <span>
                        <strong>Offer updates</strong>
                        <small>When you receive an offer or a response to a counter-offer.</small>
                    </span>
                    <input
                        type="checkbox"
                        name="offer_updates"
                        value="1"
                        <?= (int) $user['offer_updates'] === 1 ? 'checked' : '' ?>
                    >
                </label>

                <label class="settings-preference">
                    <span>
                        <strong>Transaction updates</strong>
                        <small>When a transaction or payment status changes.</small>
                    </span>
                    <input
                        type="checkbox"
                        name="transaction_updates"
                        value="1"
                        <?= (int) $user['transaction_updates'] === 1 ? 'checked' : '' ?>
                    >
                </label>

            </div>

            <div class="profile-form-actions">
                <button
                    type="submit"
                    class="profile-save-button"
                    id="sellerNotificationSubmit"
                >
                    SAVE PREFERENCES
                </button>
            </div>

        </div>

    </form>


</section>

<script>
(function () {

    const languageForm = document.getElementById('sellerLanguageForm');
    if (languageForm) {
        const languageSelect = document.getElementById('sellerLanguage');
        let savingLanguage = false;
        languageSelect.addEventListener('change', () => languageForm.requestSubmit());
        languageForm.addEventListener('submit', async function (event) {
            event.preventDefault();
            if (savingLanguage) return;
            savingLanguage = true;
            const body = new FormData(languageForm);
            const button = document.getElementById('sellerLanguageSubmit');
            const message = document.getElementById('sellerLanguageMessage');
            const originalLabel = button.textContent;
            button.disabled = true;
            languageSelect.disabled = true;
            button.textContent = 'SAVING...';
            message.className = 'profile-message';
            message.textContent = '';

            try {
                const response = await fetch(languageForm.action, {
                    method: 'POST',
                    body,
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' }
                });
                const result = await response.json();
                if (!response.ok || !result.success) {
                    throw new Error(result.message || 'Unable to save language preference.');
                }
                message.classList.add('show', 'success');
                message.textContent = result.message;
                window.setTimeout(() => window.location.reload(), 350);
            } catch (error) {
                message.classList.add('show', 'error');
                message.textContent = error.message || 'Unable to save language preference.';
                button.disabled = false;
                languageSelect.disabled = false;
                button.textContent = originalLabel;
                savingLanguage = false;
            }
        });
    }

    function bindForm(formId, messageId, submitId, pendingText, defaultText, successFallback) {
        const form = document.getElementById(formId);
        const message = document.getElementById(messageId);
        const submitButton = document.getElementById(submitId);

        if (!form || !message) {
            return;
        }

        form.addEventListener('submit', async function (event) {
            event.preventDefault();

            message.className = 'profile-message';
            message.textContent = '';

            if (submitButton) {
                submitButton.disabled = true;
                submitButton.textContent = pendingText;
            }

            try {
                const response = await fetch(form.action, {
                    method: 'POST',
                    body: new FormData(form),
                    credentials: 'same-origin'
                });

                const result = await response.json();

                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message || 'Unable to save your settings.'
                    );
                }

                message.classList.add('show', 'success');
                message.textContent = result.message || successFallback;

            } catch (error) {
                message.classList.add('show', 'error');
                message.textContent =
                    error.message || 'Something went wrong.';
            } finally {
                if (submitButton) {
                    submitButton.disabled = false;
                    submitButton.textContent = defaultText;
                }
            }
        });
    }

    bindForm(
        'sellerNotificationForm',
        'notificationMessage',
        'sellerNotificationSubmit',
        'SAVING...',
        'SAVE PREFERENCES',
        'Notification preferences saved.'
    );

    bindForm(
        'sellerEmailVerificationForm',
        'verificationMessage',
        'sellerVerificationSubmit',
        'SENDING...',
        'SEND VERIFICATION EMAIL',
        'Verification email sent. Check your inbox and spam folder.'
    );

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
                        : `The server returned an unexpected response (HTTP ${response.status}). Refresh Settings and try again.`
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
            window.location.href = '/VNZ-TECH/account/dashboard.php?page=settings';
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
