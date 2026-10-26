<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/email_verification.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /VNZ-TECH/account/register.php');
    exit;
}

function redirectWithError(string $message): void
{
    header(
        'Location: /VNZ-TECH/account/register.php?error=' .
        urlencode($message)
    );
    exit;
}

$fullName = trim($_POST['full_name'] ?? '');
$mobile = trim($_POST['mobile'] ?? '');
$email = strtolower(trim($_POST['email'] ?? ''));
$password = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$language = $_POST['language'] ?? 'en';
$terms = isset($_POST['terms']);

if ($fullName === '') {
    redirectWithError('Please enter your full name.');
}

if ($mobile === '') {
    redirectWithError('Please enter your mobile number.');
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError('Please enter a valid email address.');
}

if (strlen($password) < 8) {
    redirectWithError('Password must be at least 8 characters.');
}

if ($password !== $confirmPassword) {
    redirectWithError('Passwords do not match.');
}

if (!in_array($language, ['en', 'fil'], true)) {
    $language = 'en';
}

if (!$terms) {
    redirectWithError('You must agree to the Terms & Conditions.');
}

try {

    $check = $pdo->prepare("
        SELECT id
        FROM users
        WHERE email = ?
        LIMIT 1
    ");

    $check->execute([$email]);

    if ($check->fetch()) {
        redirectWithError(
            'An account with this email address already exists.'
        );
    }

    $profilePicturePath = null;

    if (
        isset($_FILES['profile_picture']) &&
        $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if ($_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK) {
            redirectWithError('The profile picture upload failed.');
        }

        if ($_FILES['profile_picture']['size'] > 5 * 1024 * 1024) {
            redirectWithError(
                'Profile picture must not exceed 5MB.'
            );
        }

        $tmpFile = $_FILES['profile_picture']['tmp_name'];

        if (!is_uploaded_file($tmpFile)) {
            redirectWithError('Invalid profile picture upload.');
        }

        $mimeType = mime_content_type($tmpFile);

        $allowedMimeTypes = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (!isset($allowedMimeTypes[$mimeType])) {
            redirectWithError(
                'Only JPG, PNG, and WEBP images are allowed.'
            );
        }

        if (@getimagesize($tmpFile) === false) {
            redirectWithError(
                'The uploaded file is not a valid image.'
            );
        }

        $uploadDirectory =
            __DIR__ . '/../uploads/profile_pictures';

        if (!is_dir($uploadDirectory)) {

            if (!mkdir($uploadDirectory, 0755, true)) {
                redirectWithError(
                    'Unable to create the profile picture folder.'
                );
            }
        }

        $extension = $allowedMimeTypes[$mimeType];

        $randomName =
            bin2hex(random_bytes(16)) . '.' . $extension;

        $destination =
            $uploadDirectory . '/' . $randomName;

        if (!move_uploaded_file($tmpFile, $destination)) {
            redirectWithError(
                'Unable to save the profile picture.'
            );
        }

        $profilePicturePath =
            'uploads/profile_pictures/' . $randomName;
    }

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    $insert = $pdo->prepare("
        INSERT INTO users (
            full_name,
            mobile,
            email,
            password_hash,
            profile_picture,
            language,
            email_verified_at,
            is_active
        )
        VALUES (?, ?, ?, ?, ?, ?, NULL, 1)
    ");

    $insert->execute([
        $fullName,
        $mobile,
        $email,
        $passwordHash,
        $profilePicturePath,
        $language
    ]);

    unset($_SESSION['guest_registration_prefill']);

    $userId = (int) $pdo->lastInsertId();
    $verificationEmailSent = false;

    try {
        sendEmailVerification(
            $pdo,
            $userId,
            $email
        );

        $verificationEmailSent = true;
    } catch (Throwable $mailError) {
        error_log(
            'Registration verification email could not be sent for user ' .
            $userId .
            ': ' .
            $mailError->getMessage()
        );
    }

    header(
        'Location: /VNZ-TECH/account/register.php?success=1&verification=' .
        ($verificationEmailSent ? 'sent' : 'pending')
    );
    exit;

} catch (Throwable $e) {

    if (
        isset($profilePicturePath) &&
        $profilePicturePath !== null
    ) {

        $absolutePath =
            __DIR__ . '/../' . $profilePicturePath;

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    redirectWithError(
        'Something went wrong while creating your account.'
    );
}