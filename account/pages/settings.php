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


    <form
        id="sellerPasswordForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_password.php"
        method="POST"
    >

        <div
            id="passwordMessage"
            class="profile-message"
            role="status"
            aria-live="polite"
        ></div>

        <div class="profile-form-section">

            <div class="profile-section-title">
                Change Password
            </div>

            <div class="profile-form-grid">

                <div class="profile-form-group profile-form-full">

                    <label for="current_password">
                        Current Password
                    </label>

                    <input
                        type="password"
                        id="current_password"
                        name="current_password"
                        autocomplete="current-password"
                        required
                    >

                </div>

                <div class="profile-form-group">

                    <label for="new_password">
                        New Password
                    </label>

                    <input
                        type="password"
                        id="new_password"
                        name="new_password"
                        minlength="8"
                        autocomplete="new-password"
                        required
                    >

                    <small>
                        At least 8 characters.
                    </small>

                </div>

                <div class="profile-form-group">

                    <label for="confirm_password">
                        Confirm New Password
                    </label>

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

        </div>

    </form>

</section>

<script>
(function () {

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

            if (
                formId === 'sellerPasswordForm' &&
                form.querySelector('#new_password').value !==
                    form.querySelector('#confirm_password').value
            ) {
                message.classList.add('show', 'error');
                message.textContent =
                    'New password and confirmation do not match.';
                return;
            }

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

                if (formId === 'sellerPasswordForm') {
                    form.reset();
                }
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
        'sellerPasswordForm',
        'passwordMessage',
        'sellerPasswordSubmit',
        'UPDATING...',
        'UPDATE PASSWORD',
        'Password updated successfully.'
    );

    bindForm(
        'sellerEmailVerificationForm',
        'verificationMessage',
        'sellerVerificationSubmit',
        'SENDING...',
        'SEND VERIFICATION EMAIL',
        'Verification email sent. Check your inbox and spam folder.'
    );

})();
</script>
