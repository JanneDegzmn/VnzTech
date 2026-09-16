<?php

/*
|--------------------------------------------------------------------------
| Prevent browser from caching the admin login page
|--------------------------------------------------------------------------
*/

header(
    'Cache-Control: no-store, no-cache, must-revalidate, max-age=0'
);

header(
    'Cache-Control: post-check=0, pre-check=0',
    false
);

header(
    'Pragma: no-cache'
);

header(
    'Expires: 0'
);


/*
|--------------------------------------------------------------------------
| Dependencies
|--------------------------------------------------------------------------
*/

require_once __DIR__ . '/../backend/database.php';
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/auth.php';


/*
|--------------------------------------------------------------------------
| Already Logged In
|--------------------------------------------------------------------------
|
| If an admin is already authenticated, do not allow the login
| page to remain visible when the browser Back button is used.
|
|--------------------------------------------------------------------------
*/

if (isAdminLoggedIn()) {

    header(
        'Location: /VNZ-TECH/admin/dashboard.php'
    );

    exit;
}


$error = '';


/*
|--------------------------------------------------------------------------
| Handle Login
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $login = trim(
        $_POST['login'] ?? ''
    );

    $password =
        $_POST['password'] ?? '';


    /*
    |--------------------------------------------------------------------------
    | Basic Validation
    |--------------------------------------------------------------------------
    */

    if (
        $login === ''
        ||
        $password === ''
    ) {

        $error =
            'Please enter your username/email and password.';

    } else {

        /*
        |--------------------------------------------------------------------------
        | Find Admin
        |--------------------------------------------------------------------------
        | Login supports:
        | - Username
        | - Email
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT
                a.id,
                a.role_id,
                a.first_name,
                a.last_name,
                a.username,
                a.email,
                a.password_hash,
                a.is_active,
                r.name AS role_name
            FROM admins a
            INNER JOIN roles r
                ON r.id = a.role_id
            WHERE
                a.username = ?
                OR a.email = ?
            LIMIT 1
        ");


        $stmt->execute([
            $login,
            $login
        ]);


        $admin =
            $stmt->fetch();


        /*
        |--------------------------------------------------------------------------
        | Verify Admin Account
        |--------------------------------------------------------------------------
        */

        if (!$admin) {

            $error =
                'Invalid username/email or password.';

        } elseif (
            (int) $admin['is_active'] !== 1
        ) {

            $error =
                'This admin account is currently inactive.';

        } elseif (
            !password_verify(
                $password,
                $admin['password_hash']
            )
        ) {

            $error =
                'Invalid username/email or password.';

        } else {

            /*
            |--------------------------------------------------------------------------
            | Login Successful
            |--------------------------------------------------------------------------
            */

            session_regenerate_id(true);


            $_SESSION['admin_id'] =
                (int) $admin['id'];

            $_SESSION['admin_role_id'] =
                (int) $admin['role_id'];

            $_SESSION['admin_role'] =
                $admin['role_name'];

            $_SESSION['admin_username'] =
                $admin['username'];

            $_SESSION['admin_first_name'] =
                $admin['first_name'];

            $_SESSION['admin_last_name'] =
                $admin['last_name'];

            $_SESSION['admin_email'] =
                $admin['email'];


            /*
            |--------------------------------------------------------------------------
            | Redirect to Dashboard
            |--------------------------------------------------------------------------
            */

            header(
                'Location: /VNZ-TECH/admin/dashboard.php'
            );

            exit;
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

    <meta
        http-equiv="Cache-Control"
        content="no-store, no-cache, must-revalidate"
    >

    <meta
        http-equiv="Pragma"
        content="no-cache"
    >

    <meta
        http-equiv="Expires"
        content="0"
    >

    <title>
        VNZ TECH - Admin Login
    </title>


    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/admin_login.css"
    >

</head>


<body>


<div class="login-page">


    <div class="login-card">


        <!-- =====================================================
             BRAND
        ====================================================== -->

        <div class="brand">


            <div class="brand-name">
                VNZ TECH
            </div>


            <div class="brand-line"></div>


            <div class="brand-subtitle">
                ADMIN PANEL
            </div>


        </div>


        <!-- =====================================================
             LOGIN HEADING
        ====================================================== -->

        <div class="login-heading">
            Welcome Back
        </div>


        <div class="login-description">
            Sign in to access the VNZ TECH management system.
        </div>


        <!-- =====================================================
             ERROR
        ====================================================== -->

        <?php if ($error !== ''): ?>

            <div class="login-error">

                <?= htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </div>

        <?php endif; ?>


        <!-- =====================================================
             LOGIN FORM
        ====================================================== -->

        <form
            method="POST"
            autocomplete="on"
        >


            <!-- =================================================
                 USERNAME / EMAIL
            ================================================== -->

            <div class="form-group">


                <label for="login">
                    Username or Email
                </label>


                <input
                    type="text"
                    id="login"
                    name="login"
                    placeholder="Enter your username or email"
                    value="<?= htmlspecialchars(
                        $_POST['login'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>"
                    autocomplete="username"
                    required
                    autofocus
                >


            </div>


            <!-- =================================================
                 PASSWORD
            ================================================== -->

            <div class="form-group">


                <label for="password">
                    Password
                </label>


                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Enter your password"
                    autocomplete="current-password"
                    required
                >


            </div>


            <!-- =================================================
                 SIGN IN
            ================================================== -->

            <button
                type="submit"
                class="login-button"
            >
                SIGN IN
            </button>


        </form>


        <!-- =====================================================
             FOOTER
        ====================================================== -->

        <div class="login-footer">


            <span>
                VNZ TECH
            </span>


            <span class="footer-dot">
                •
            </span>


            <span>
                Management System
            </span>


        </div>


    </div>


</div>


</body>

</html>