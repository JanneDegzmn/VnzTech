<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';

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
            email
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
            <h2>Unable to load settings</h2>
            <p>Something went wrong while loading your settings.</p>
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
                Manage your account security for
                <?= settingsEscape($user['email']) ?>.
            </p>
        </div>

    </div>


    <form
        id="sellerPasswordForm"
        class="seller-profile-form"
        action="/VNZ-TECH/backend/update_password.php"
        method="POST"
    >

        <div
            id="passwordMessage"
            class="profile-message"
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

    </form>

</section>

<script>
(function () {

    const form =
        document.getElementById(
            'sellerPasswordForm'
        );

    const message =
        document.getElementById(
            'passwordMessage'
        );

    const submitButton =
        document.getElementById(
            'sellerPasswordSubmit'
        );


    if (!form) {
        return;
    }


    form.addEventListener(
        'submit',
        async function (event) {

            event.preventDefault();


            message.className =
                'profile-message';

            message.textContent =
                '';


            const newPassword =
                form.querySelector(
                    '#new_password'
                ).value;

            const confirmPassword =
                form.querySelector(
                    '#confirm_password'
                ).value;


            if (newPassword !== confirmPassword) {

                message.classList.add(
                    'show',
                    'error'
                );

                message.textContent =
                    'New password and confirmation do not match.';

                return;

            }


            if (submitButton) {

                submitButton.disabled = true;

                submitButton.textContent =
                    'UPDATING...';

            }


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
                        'Unable to update your password.'
                    );
                }


                message.classList.add(
                    'show',
                    'success'
                );

                message.textContent =
                    result.message ||
                    'Password updated successfully.';

                form.reset();


            } catch (error) {

                message.classList.add(
                    'show',
                    'error'
                );

                message.textContent =
                    error.message ||
                    'Something went wrong.';

            } finally {

                if (submitButton) {

                    submitButton.disabled = false;

                    submitButton.textContent =
                        'UPDATE PASSWORD';

                }

            }

        }
    );

})();
</script>