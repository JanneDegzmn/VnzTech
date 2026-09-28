<?php

session_start();

$success = isset($_GET['success']) && $_GET['success'] === '1';
$error = $_GET['error'] ?? '';

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

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_register.css"
    >

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

        <?php if ($success): ?>

            <div class="message success">
                Account created successfully. You may now log in.
            </div>

        <?php endif; ?>

        <form
            action="/VNZ-TECH/backend/register.php"
            method="POST"
            enctype="multipart/form-data"
            autocomplete="off"
            data-loader
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
                        minlength="8"
                        required
                    >

                </div>

            </div>

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
                        Filipino
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
                        href="#"
                        onclick="return false;"
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

        <div class="login-link">

            Already have an account?

            <a href="login.php">
                Log in
            </a>

        </div>

    </div>

</div>

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