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
            u.language,
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

$csrfToken = generateCsrfToken();

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
                Manage appearance, language and email notification preferences.
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
                    class="profile-save-button seller-preference-save-button"
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


})();
</script>
