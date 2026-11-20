<?php


require_once __DIR__ . '/site_status.php';
vnzEnforceSiteMaintenance('auto', false, true, 'account_settings');
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';
require_once __DIR__ . '/csrf.php';

header('Content-Type: application/json; charset=UTF-8');

if (
    empty($_SESSION['user_logged_in']) ||
    empty($_SESSION['user_id'])
) {
    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Your session has expired.'
    ]);

    exit;
}

$userId = (int) $_SESSION['user_id'];

if ($userId <= 0) {

    http_response_code(401);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid user session.'
    ]);

    exit;
}


if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method.'
    ]);

    exit;
}


if (!verifyCsrfToken($_POST['csrf_token'] ?? null)) {
    http_response_code(419);
    echo json_encode(['success' => false, 'message' => 'Your session has expired. Please refresh and try again.']);
    exit;
}

$fullName =
    trim($_POST['full_name'] ?? '');

$mobile =
    trim($_POST['mobile'] ?? '');

$address =
    trim($_POST['address'] ?? '');

$language =
    isset($_POST['language']) ? trim((string) $_POST['language']) : null;


if ($fullName === '') {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Full name is required.'
    ]);

    exit;
}


if ($mobile === '') {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Mobile number is required.'
    ]);

    exit;
}


if ($language !== null && !in_array(
    $language,
    ['en', 'fil'],
    true
)) {

    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a supported language.']);
    exit;

}


$uploadedProfilePath = null;


try {

    $pdo->beginTransaction();


    /*
    |----------------------------------------------------------------------
    | OPTIONAL PROFILE PICTURE
    |----------------------------------------------------------------------
    */

    if (
        isset($_FILES['profile_picture']) &&
        $_FILES['profile_picture']['error'] !== UPLOAD_ERR_NO_FILE
    ) {

        if (
            $_FILES['profile_picture']['error'] !== UPLOAD_ERR_OK
        ) {

            throw new RuntimeException(
                'Profile picture upload failed.'
            );

        }


        if (
            $_FILES['profile_picture']['size'] >
            5 * 1024 * 1024
        ) {

            throw new RuntimeException(
                'Profile picture must not exceed 5MB.'
            );

        }


        $tmpName =
            $_FILES['profile_picture']['tmp_name'];

        $originalName =
            $_FILES['profile_picture']['name'];


        $extension =
            strtolower(
                pathinfo(
                    $originalName,
                    PATHINFO_EXTENSION
                )
            );


        $allowedExtensions = [
            'jpg',
            'jpeg',
            'png',
            'webp'
        ];


        if (
            !in_array(
                $extension,
                $allowedExtensions,
                true
            )
        ) {

            throw new RuntimeException(
                'Invalid profile picture format.'
            );

        }


        $uploadDirectory =
            dirname(__DIR__) .
            DIRECTORY_SEPARATOR .
            'uploads' .
            DIRECTORY_SEPARATOR .
            'profiles';


        if (!is_dir($uploadDirectory)) {

            if (
                !mkdir(
                    $uploadDirectory,
                    0755,
                    true
                )
            ) {

                throw new RuntimeException(
                    'Unable to create profile upload folder.'
                );

            }

        }


        $fileName =
            'profile_' .
            $userId .
            '_' .
            bin2hex(
                random_bytes(6)
            ) .
            '.' .
            $extension;


        $destination =
            $uploadDirectory .
            DIRECTORY_SEPARATOR .
            $fileName;


        if (
            !move_uploaded_file(
                $tmpName,
                $destination
            )
        ) {

            throw new RuntimeException(
                'Unable to save profile picture.'
            );

        }


        $uploadedProfilePath =
            'uploads/profiles/' .
            $fileName;

    }


    /*
    |----------------------------------------------------------------------
    | UPDATE USER
    |----------------------------------------------------------------------
    */

    if ($uploadedProfilePath !== null) {

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                full_name = ?,
                mobile = ?,
                address = ?,
                language = COALESCE(?, language),
                profile_picture = ?,
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $fullName,
            $mobile,
            $address !== '' ? $address : null,
            $language,
            $uploadedProfilePath,
            $userId
        ]);

    } else {

        $stmt = $pdo->prepare("
            UPDATE users
            SET
                full_name = ?,
                mobile = ?,
                address = ?,
                language = COALESCE(?, language),
                updated_at = NOW()
            WHERE id = ?
        ");

        $stmt->execute([
            $fullName,
            $mobile,
            $address !== '' ? $address : null,
            $language,
            $userId
        ]);

    }


    /*
    |----------------------------------------------------------------------
    | REFRESH SESSION
    |----------------------------------------------------------------------
    */

    $_SESSION['user_name'] =
        $fullName;

    $_SESSION['user_mobile'] =
        $mobile;

    if ($language !== null) {
        $_SESSION['user_language'] = $language;
    }


    if ($uploadedProfilePath !== null) {

        $_SESSION['user_profile_picture'] =
            $uploadedProfilePath;

    }


    $pdo->commit();


    echo json_encode([
        'success' => true,
        'message' => 'Profile updated successfully.'
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);

}