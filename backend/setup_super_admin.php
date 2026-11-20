<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false);
require_once __DIR__ . '/database.php';

/*
|--------------------------------------------------------------------------
| VNZ TECH - Initial Super Admin Setup
|--------------------------------------------------------------------------
| This file is intended to be used ONCE during local setup.
|--------------------------------------------------------------------------
*/


// Check if a Super Admin already exists
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM admins a
    INNER JOIN roles r
        ON r.id = a.role_id
    WHERE r.name = 'Super Admin'
");

$stmt->execute();

$superAdminExists = (int) $stmt->fetchColumn() > 0;


$message = '';
$messageType = '';


/*
|--------------------------------------------------------------------------
| Prevent duplicate setup
|--------------------------------------------------------------------------
*/

if ($superAdminExists) {

    die("
        <h1>Setup Already Completed</h1>
        <p>A Super Admin account already exists.</p>
        <p>Please delete <strong>setup_super_admin.php</strong>.</p>
    ");
}


/*
|--------------------------------------------------------------------------
| Handle Form Submission
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');

    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Validation
    |--------------------------------------------------------------------------
    */

    if (
        $firstName === '' ||
        $lastName === '' ||
        $username === '' ||
        $email === '' ||
        $password === ''
    ) {

        $message = 'Please complete all required fields.';
        $messageType = 'error';

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $message = 'Please enter a valid email address.';
        $messageType = 'error';

    } elseif (strlen($password) < 8) {

        $message = 'Password must be at least 8 characters.';
        $messageType = 'error';

    } elseif ($password !== $confirmPassword) {

        $message = 'Passwords do not match.';
        $messageType = 'error';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Check Existing Username / Email
        |--------------------------------------------------------------------------
        */

        $check = $pdo->prepare("
            SELECT id
            FROM admins
            WHERE username = ?
               OR email = ?
            LIMIT 1
        ");

        $check->execute([
            $username,
            $email
        ]);


        if ($check->fetch()) {

            $message = 'Username or email already exists.';
            $messageType = 'error';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Get Super Admin Role
            |--------------------------------------------------------------------------
            */

            $roleStmt = $pdo->prepare("
                SELECT id
                FROM roles
                WHERE name = 'Super Admin'
                  AND is_active = 1
                LIMIT 1
            ");

            $roleStmt->execute();

            $role = $roleStmt->fetch();


            if (!$role) {

                $message = 'Super Admin role was not found.';
                $messageType = 'error';

            } else {

                /*
                |--------------------------------------------------------------------------
                | Hash Password
                |--------------------------------------------------------------------------
                */

                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );


                /*
                |--------------------------------------------------------------------------
                | Create Super Admin
                |--------------------------------------------------------------------------
                */

                $insert = $pdo->prepare("
                    INSERT INTO admins (
                        role_id,
                        first_name,
                        last_name,
                        username,
                        email,
                        password_hash,
                        is_active
                    )
                    VALUES (?, ?, ?, ?, ?, ?, 1)
                ");

                $insert->execute([
                    $role['id'],
                    $firstName,
                    $lastName,
                    $username,
                    $email,
                    $passwordHash
                ]);


                $message = 'Super Admin account created successfully!';
                $messageType = 'success';

                $superAdminExists = true;
            }
        }
    }
}

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>VNZ TECH - Super Admin Setup</title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/setup_super_admin.css"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/password_visibility.css?v=<?= filemtime(__DIR__ . '/../assets/css/password_visibility.css') ?>"
    >

</head>

<body>

<div class="setup-container">

    <h1>VNZ TECH</h1>

    <div class="subtitle">
        Initial Super Admin Setup
    </div>


    <?php if ($message !== ''): ?>

        <div class="message <?= htmlspecialchars($messageType) ?>">
            <?= htmlspecialchars($message) ?>
        </div>

    <?php endif; ?>


    <?php if (!$superAdminExists && $messageType !== 'success'): ?>

        <form method="POST">

            <div class="form-group">

                <label for="first_name">
                    First Name
                </label>

                <input
                    type="text"
                    id="first_name"
                    name="first_name"
                    autocomplete="given-name"
                    required
                >

            </div>


            <div class="form-group">

                <label for="last_name">
                    Last Name
                </label>

                <input
                    type="text"
                    id="last_name"
                    name="last_name"
                    autocomplete="family-name"
                    required
                >

            </div>


            <div class="form-group">

                <label for="username">
                    Username
                </label>

                <input
                    type="text"
                    id="username"
                    name="username"
                    autocomplete="username"
                    required
                >

            </div>


            <div class="form-group">

                <label for="email">
                    Email
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    autocomplete="email"
                    required
                >

            </div>


            <div class="form-group">

                <label for="password">
                    Password
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    minlength="8"
                    autocomplete="new-password"
                    required
                >

            </div>


            <div class="form-group">

                <label for="confirm_password">
                    Confirm Password
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


            <button
                type="submit"
                class="setup-button"
            >
                Create Super Admin
            </button>

        </form>


        <div class="warning">
            This is a one-time setup page.
            Delete
            <strong>setup_super_admin.php</strong>
            after successfully creating the account.
        </div>

    <?php elseif ($messageType === 'success'): ?>

        <div class="warning">
            Your Super Admin account is ready.
            Delete
            <strong>setup_super_admin.php</strong>
            before continuing.
        </div>

    <?php endif; ?>

</div>

<script src="/VNZ-TECH/assets/js/password-visibility.js?v=<?= filemtime(__DIR__ . '/../assets/js/password-visibility.js') ?>" defer></script>

</body>

</html>