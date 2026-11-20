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
            id,
            full_name,
            mobile,
            address,
            email,
            profile_picture,
            language,
            email_verified_at,
            created_at
        FROM users
        WHERE id = ?
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

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load profile</h2>
            <p>Something went wrong while loading your profile.</p>
        </div>
    ';

    exit;
}

function profileEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}

function profilePicturePath($profilePicture): string
{
    if (
        !empty($profilePicture) &&
        is_string($profilePicture)
    ) {

        $profilePicture =
            trim($profilePicture);

        if ($profilePicture !== '') {

            $normalized =
                str_replace(
                    '\\',
                    '/',
                    $profilePicture
                );

            if (
                str_starts_with($normalized, 'http://') ||
                str_starts_with($normalized, 'https://') ||
                str_starts_with($normalized, '/')
            ) {
                return $normalized;
            }

            return '/VNZ-TECH/' .
                ltrim($normalized, '/');
        }
    }

    return '';
}

$fullName =
    trim((string) ($user['full_name'] ?? ''));

$initial =
    $fullName !== ''
        ? strtoupper(substr($fullName, 0, 1))
        : 'V';

$profilePicture =
    profilePicturePath(
        $user['profile_picture'] ?? ''
    );
$csrfToken = generateCsrfToken();
$emailVerified = !empty($user['email_verified_at']);
$verificationNotice = $emailVerified && ($_GET['verification'] ?? '') === 'success' ? 'Your email address has been verified.' : '';

?>

<section class="seller-profile-page">

    <div class="seller-profile-header">

        <div>
            <span class="eyebrow">
                ACCOUNT
            </span>

            <h1>
                Your Profile
            </h1>

            <p>
                Manage the information connected
                to your VNZ TECH seller account.
            </p>
        </div>

    </div>


    <div class="seller-profile-card">

        <div class="seller-profile-avatar">

            <?php if ($profilePicture !== ''): ?>

                <img
                    src="<?= profileEscape($profilePicture) ?>"
                    alt="<?= profileEscape($fullName) ?>"
                >

            <?php else: ?>

                <?= profileEscape($initial) ?>

            <?php endif; ?>

        </div>


        <div>

            <h2>
                <?= profileEscape($fullName ?: 'Seller') ?>
            </h2>

            <p>
                <?= profileEscape($user['email']) ?>
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
                    <?= profileEscape($user['email']) ?>
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
                <?= profileEscape($verificationNotice) ?>
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
                    value="<?= profileEscape($csrfToken) ?>"
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
        id="sellerProfileForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_profile.php"
        method="POST"
        enctype="multipart/form-data"
    >

        <input type="hidden" name="csrf_token" value="<?= profileEscape($csrfToken) ?>">
        <div
            id="profileMessage"
            class="profile-message"
        ></div>


        <div class="profile-form-section">

            <div class="profile-section-title">
                Personal Information
            </div>


            <div class="profile-form-grid">

                <div class="profile-form-group">

                    <label for="profile_full_name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="profile_full_name"
                        name="full_name"
                        maxlength="150"
                        value="<?= profileEscape($user['full_name']) ?>"
                        required
                    >

                </div>


                <div class="profile-form-group">

                    <label for="profile_email">
                        Email Address
                    </label>

                    <input
                        type="email"
                        id="profile_email"
                        value="<?= profileEscape($user['email']) ?>"
                        readonly
                    >

                    <small>
                        Your email is tied to your account.
                    </small>

                </div>


                <div class="profile-form-group">

                    <label for="profile_mobile">
                        Mobile Number
                    </label>

                    <input
                        type="text"
                        id="profile_mobile"
                        name="mobile"
                        maxlength="30"
                        value="<?= profileEscape($user['mobile']) ?>"
                        required
                    >

                </div>


                <div class="profile-form-group profile-form-full">

                    <label for="profile_address">
                        Address
                    </label>

                    <input
                        type="text"
                        id="profile_address"
                        name="address"
                        maxlength="255"
                        value="<?= profileEscape($user['address']) ?>"
                        placeholder="Enter your complete address"
                    >

                </div>


                <div class="profile-form-group">

                    <label for="profile_picture">
                        Change Profile Picture
                    </label>

                    <input
                        type="file"
                        id="profile_picture"
                        name="profile_picture"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                </div>

            </div>

        </div>


        <div class="profile-form-actions">

            <button
                type="submit"
                class="profile-save-button"
            >
                SAVE CHANGES
            </button>

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
        'sellerEmailVerificationForm',
        'verificationMessage',
        'sellerVerificationSubmit',
        'SENDING...',
        'SEND VERIFICATION EMAIL',
        'Verification email sent. Check your inbox and spam folder.'
    );



    const form =
        document.getElementById(
            'sellerProfileForm'
        );

    const message =
        document.getElementById(
            'profileMessage'
        );


    if (!form) {
        return;
    }


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            const button =
                form.querySelector(
                    'button[type="submit"]'
                );


            if (button) {

                button.disabled = true;

                button.textContent =
                    'SAVING...';

            }


            message.className =
                'profile-message';

            message.textContent =
                '';


            try {

                const formData =
                    new FormData(form);


                const response =
                    await fetch(
                        form.action,
                        {
                            method: 'POST',
                            body: formData,
                            credentials: 'same-origin'
                        }
                    );


                const result =
                    await response.json();


                if (!response.ok || !result.success) {
                    throw new Error(
                        result.message ||
                        'Unable to save your profile.'
                    );
                }


                message.classList.add(
                    'show',
                    'success'
                );

                message.textContent =
                    result.message ||
                    'Profile updated successfully.';
                window.dispatchEvent(new Event('vnz:seller-profile-updated'));


            } catch (error) {

                message.classList.add(
                    'show',
                    'error'
                );

                message.textContent =
                    error.message ||
                    'Something went wrong.';

            } finally {

                if (button) {

                    button.disabled = false;

                    button.textContent =
                        'SAVE CHANGES';

                }

            }

        }
    );


})();
</script>
