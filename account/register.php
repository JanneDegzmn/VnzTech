<?php

require_once __DIR__ . '/../backend/session.php';

$success = isset($_GET['success']) && $_GET['success'] === '1';
$verification = $_GET['verification'] ?? '';
if ($success) {
    $verificationQuery = in_array($verification, ['sent', 'pending'], true)
        ? '&verification=' . $verification
        : '';
    header('Location: /VNZ-TECH/account/login.php?success=1' . $verificationQuery);
    exit;
}

$error = $_GET['error'] ?? '';
$guestRegistrationPrefill = $_SESSION['guest_registration_prefill'] ?? [];
if (!is_array($guestRegistrationPrefill)) {
    $guestRegistrationPrefill = [];
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <?php include __DIR__ . '/../includes/loader-head.php'; ?>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>Create Account | VNZ TECH</title>

    <script>
        (function () {
            try {
                document.documentElement.setAttribute(
                    'data-public-theme',
                    localStorage.getItem('vnz-public-theme') === 'light' ? 'light' : 'dark'
                );
            } catch (error) {
                document.documentElement.setAttribute('data-public-theme', 'dark');
                console.error('Unable to load the public theme preference.', error);
            }
        })();
    </script>
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_register.css?v=<?= filemtime(__DIR__ . '/../assets/css/seller_register.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/public_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/public_theme.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/social_auth.css?v=<?= filemtime(__DIR__ . '/../assets/css/social_auth.css') ?>"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/terms_modal.css?v=<?= filemtime(__DIR__ . '/../assets/css/terms_modal.css') ?>"
    >
    <script
        src="/VNZ-TECH/assets/js/public-theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/public-theme.js') ?>"
        defer
    ></script>

</head>

<body>

<?php include __DIR__ . '/../includes/loader-body.php'; ?>

<div class="register-wrapper">

    <div class="page-back">
        <a href="/VNZ-TECH/sell.php">
            ← Back to Home
        </a>
    </div>

    <div class="brand">

        <h1>VNZ TECH</h1>

        <p>
            WE BUY. WE RECOVER. WE REVALUE.
        </p>

    </div>

    <div class="register-card">

        <h2>Create Your Account</h2>

        <p class="subtitle">
            Keep track of your submissions, offers, transactions, and future inquiries in one place.
        </p>

        <?php if ($error !== ''): ?>

            <div class="message error">
                <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
            </div>

        <?php endif; ?>

        <form
            action="/VNZ-TECH/backend/register.php"
            method="POST"
            enctype="multipart/form-data"
            autocomplete="off"
            data-loader
            data-password-strength-form
        >

            <div class="profile-upload">

                <div
                    class="avatar-preview"
                    id="avatarPreview"
                >
                    V
                </div>

                <div class="upload-content">

                    <label for="profile_picture">
                        Profile Picture
                    </label>

                    <p>
                        Optional. JPG, PNG, or WEBP. Max 5MB.
                    </p>

                    <input
                        type="file"
                        id="profile_picture"
                        name="profile_picture"
                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                    >

                </div>

            </div>

            <div class="field">

                <label for="full_name">
                    Full Name *
                </label>

                <input
                    type="text"
                    id="full_name"
                    name="full_name"
                    maxlength="150"
                    autocomplete="name"
                    value="<?= htmlspecialchars((string) ($guestRegistrationPrefill['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

            </div>

            <div class="field">

                <label for="mobile">
                    Mobile Number *
                </label>

                <input
                    type="text"
                    id="mobile"
                    name="mobile"
                    maxlength="30"
                    autocomplete="tel"
                    value="<?= htmlspecialchars((string) ($guestRegistrationPrefill['mobile'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

            </div>

            <div class="field">

                <label for="email">
                    Email Address *
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    maxlength="190"
                    autocomplete="email"
                    value="<?= htmlspecialchars((string) ($guestRegistrationPrefill['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                    required
                >

                <div class="hint">
                    We’ll use this email for account verification and future notifications.
                </div>

            </div>

            <div class="password-row">

                <div class="field">

                    <label for="password">
                        Password *
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        data-password-strength-input
                        aria-describedby="registerPasswordPolicy"
                        minlength="8"
                        required
                    >

                </div>

                <div class="field">

                    <label for="confirm_password">
                        Confirm Password *
                    </label>

                    <input
                        type="password"
                        id="confirm_password"
                        name="confirm_password"
                        data-password-confirmation
                        minlength="8"
                        required
                    >

                </div>

            </div>

            <div class="field password-strength-field">
                <div
                    class="password-strength-track"
                    data-password-strength-track
                    data-strength="empty"
                    role="progressbar"
                    aria-label="Password strength"
                    aria-valuemin="0"
                    aria-valuemax="3"
                    aria-valuenow="0"
                >
                    <span class="password-strength-fill"></span>
                </div>
                <div
                    class="password-strength-label"
                    data-password-strength-label
                    data-strength="empty"
                    aria-live="polite"
                >Start typing to check strength</div>
                <div class="password-policy" id="registerPasswordPolicy">
                    Medium requires at least 8 characters and 3 character types. Strong requires at least 16 characters, including uppercase, lowercase, a number, and a symbol.
                </div>
                <div class="password-criteria">
                    <span data-password-rule="length">8+ characters</span>
                    <span data-password-rule="lowercase">Lowercase</span>
                    <span data-password-rule="uppercase">Uppercase</span>
                    <span data-password-rule="number">Number</span>
                    <span data-password-rule="symbol">Symbol</span>
                </div>
            </div>
            <div class="password-form-error" data-password-error role="alert" hidden></div>

            <div class="field">

                <label for="language">
                    Preferred Language
                </label>

                <select
                    id="language"
                    name="language"
                >

                    <option value="en">
                        English
                    </option>

                    <option value="fil">
                        Filipino (Taglish)
                    </option>

                </select>

            </div>

            <div class="terms">

                <input
                    type="checkbox"
                    id="terms"
                    name="terms"
                    value="1"
                    required
                >

                <label for="terms">

                    I agree to the VNZ TECH

                    <a
                        href="#termsModal"
                        data-open-terms
                        aria-haspopup="dialog"
                        aria-controls="termsModal"
                    >
                        Terms & Conditions
                    </a>

                    and understand that my account information will be stored securely.

                </label>

            </div>

            <button
                type="submit"
                class="submit-button"
            >
                CREATE ACCOUNT
            </button>

        </form>

        <?php include __DIR__ . '/../includes/social_auth_buttons.php'; ?>

        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Log in
            </a>

        </div>

    </div>

</div>

<?php include __DIR__ . '/../includes/terms_modal.php'; ?>

<script src="/VNZ-TECH/assets/js/password-strength.js?v=<?= filemtime(__DIR__ . '/../assets/js/password-strength.js') ?>" defer></script>
<script src="/VNZ-TECH/assets/js/terms-modal.js?v=<?= filemtime(__DIR__ . '/../assets/js/terms-modal.js') ?>" defer></script>

<script>
const profileInput = document.getElementById('profile_picture');
const avatarPreview = document.getElementById('avatarPreview');

profileInput.addEventListener('change', function () {

    const file = this.files[0];

    if (!file) {
        avatarPreview.innerHTML = 'V';
        return;
    }

    const reader = new FileReader();

    reader.onload = function (event) {

        avatarPreview.innerHTML = `
            <img
                src="${event.target.result}"
                alt="Profile Preview"
            >
        `;

    };

    reader.readAsDataURL(file);

});
</script>

</body>
</html>
