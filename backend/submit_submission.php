<?php

require_once __DIR__ . '/database.php';
require_once __DIR__ . '/session.php';


/*
|--------------------------------------------------------------------------
| LOGGED-IN STATE
|--------------------------------------------------------------------------
*/

$isLoggedInSeller =
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true &&
    !empty($_SESSION['user_id']);


/*
|--------------------------------------------------------------------------
| SUBMISSION CONTEXT (public sell.php vs authenticated account portal)
|--------------------------------------------------------------------------
|
| A valid session alone does NOT mean the submission should be
| attached to the account. The entry point matters.
|
| The form itself must explicitly declare which context it is:
|
|   sell.php (public)                -> submission_source = "guest"
|   account/pages/submission.php     -> submission_source = "account"
|
| A request is only ever treated as an account-portal submission
| when BOTH are true:
|
|   1. There is a valid, active seller session
|   2. The form explicitly identifies itself as the account-portal form
|
| Spoofing submission_source=account without a real session changes
| nothing (still requires #1), so this only narrows who can be
| attached to an account — it never widens it.
|
*/

$submissionSource =
    isset($_POST['submission_source'])
        ? trim((string) $_POST['submission_source'])
        : '';


$isAccountPortalSubmission =
    $isLoggedInSeller &&
    $submissionSource === 'account';

/*
|--------------------------------------------------------------------------
| REQUEST METHOD
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] !== 'POST'
) {

    header(
        'Location: /VNZ-TECH/sell.php'
    );

    exit;
}


/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

function redirectWithError(
    $message
) {

    global $isAccountPortalSubmission;


    /*
    |--------------------------------------------------------------------------
    | Seller Portal Error
    |--------------------------------------------------------------------------
    |
    | Only redirect back into the account dashboard when this request
    | was actually made FROM the account portal submission flow.
    | A logged-in seller submitting via the public form on error
    | should stay on the public sell.php page.
    |
    */

    if (
        $isAccountPortalSubmission
    ) {

        header(
            'Location: /VNZ-TECH/account/dashboard.php?page=submissions&error=' .
            rawurlencode(
                $message
            )
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | Public / Guest Error
    |--------------------------------------------------------------------------
    */

    header(
        'Location: /VNZ-TECH/sell.php?error=' .
        rawurlencode(
            $message
        )
    );

    exit;
}


function cleanText(
    $value,
    $maxLength = 500
) {

    $value =
        trim(
            (string) $value
        );


    if (
        $value === ''
    ) {

        return null;

    }


    return mb_substr(
        $value,
        0,
        $maxLength
    );
}


function generateReferenceNumber(
    $pdo
) {

    for (
        $attempt = 0;
        $attempt < 10;
        $attempt++
    ) {

        $datePart =
            date('ymd');


        $randomPart =
            random_int(
                1000,
                9999
            );


        $reference =
            '#SP-' .
            $datePart .
            '-' .
            $randomPart;


        $stmt =
            $pdo->prepare("
                SELECT
                    id
                FROM submissions
                WHERE reference_number = ?
                LIMIT 1
            ");


        $stmt->execute([
            $reference
        ]);


        if (
            !$stmt->fetch()
        ) {

            return $reference;

        }

    }


    throw new RuntimeException(
        'Unable to generate submission reference number.'
    );

}


function getUploadedFiles(
    $files,
    $index
) {

    if (
        !isset(
            $files['name'][$index]
        ) ||
        !is_array(
            $files['name'][$index]
        )
    ) {

        return [];

    }


    $result =
        [];


    $count =
        count(
            $files['name'][$index]
        );


    for (
        $i = 0;
        $i < $count;
        $i++
    ) {

        $result[] = [

            'name' =>
                $files['name'][$index][$i]
                ?? '',

            'type' =>
                $files['type'][$index][$i]
                ?? '',

            'tmp_name' =>
                $files['tmp_name'][$index][$i]
                ?? '',

            'error' =>
                $files['error'][$index][$i]
                ?? UPLOAD_ERR_NO_FILE,

            'size' =>
                $files['size'][$index][$i]
                ?? 0

        ];

    }


    return $result;

}

/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
|
| If this is genuinely an account-portal submission, the account
| itself becomes the source of truth for seller information.
|
| We NEVER trust seller name, mobile, or address coming from
| hidden browser fields for authenticated account-portal submissions.
|
| IMPORTANT: this branch is keyed off $isAccountPortalSubmission,
| NOT just $isLoggedInSeller. A logged-in seller using the public
| sell.php form falls through to the guest branch below, exactly
| like a real guest.
|
*/

$userId =
    null;


$authenticatedUser =
    null;


if (
    $isAccountPortalSubmission
) {

    $sessionUserId =
        (int) (
            $_SESSION['user_id']
            ?? 0
        );


    if (
        $sessionUserId > 0
    ) {

        $userCheckStmt =
            $pdo->prepare("
                SELECT
                    id,
                    full_name,
                    mobile,
                    address,
                    is_active
                FROM users
                WHERE id = ?
                LIMIT 1
            ");


        $userCheckStmt->execute([
            $sessionUserId
        ]);


        $authenticatedUser =
            $userCheckStmt->fetch();


        if (
            $authenticatedUser &&
            (int) $authenticatedUser['is_active'] === 1
        ) {

            $userId =
                $sessionUserId;

        }

    }

}

/*
|--------------------------------------------------------------------------
| SELLER INFORMATION
|--------------------------------------------------------------------------
|
| Authenticated account-portal submission:
|   data comes directly from users table.
|
| Guest (including a logged-in seller using the PUBLIC form):
|   data comes from the public sell.php form.
|
*/

$sellerName =
    null;


$sellerMobile =
    null;


$sellerAddress =
    null;


if (
    $userId !== null
) {

    /*
    |--------------------------------------------------------------------------
    | Authenticated Seller Portal Submission
    |--------------------------------------------------------------------------
    */

    $sellerName =
        cleanText(
            $authenticatedUser['full_name'] ?? '',
            150
        );


    $sellerMobile =
        cleanText(
            $authenticatedUser['mobile'] ?? '',
            30
        );


    $sellerAddress =
        cleanText(
            $authenticatedUser['address'] ?? '',
            500
        );

} else {

    /*
    |--------------------------------------------------------------------------
    | Public / Guest Submission
    |--------------------------------------------------------------------------
    */

    $sellerName =
        cleanText(
            $_POST['seller_name'] ?? '',
            150
        );


    $sellerMobile =
        cleanText(
            $_POST['seller_mobile'] ?? '',
            30
        );


    $sellerAddress =
        cleanText(
            $_POST['seller_address'] ?? '',
            500
        );

}


/*
|--------------------------------------------------------------------------
| SELLING METHOD
|--------------------------------------------------------------------------
*/

$sellingMethod =
    $_POST['selling_method']
    ?? '';


$sellerNotes =
    cleanText(
        $_POST['seller_notes'] ?? '',
        1500
    );


/*
|--------------------------------------------------------------------------
| SELLER INFORMATION VALIDATION
|--------------------------------------------------------------------------
*/

if (
    !$sellerName
) {

    redirectWithError(
        'Please provide your full name.'
    );

}


if (
    !$sellerMobile
) {

    redirectWithError(
        'Please provide your mobile number.'
    );

}


if (
    !$sellerAddress
) {

    redirectWithError(
        'Please complete your address in your Profile before submitting an item.'
    );

}


/*
|--------------------------------------------------------------------------
| SELLING METHOD VALIDATION
|--------------------------------------------------------------------------
*/

$allowedSellingMethods = [

    'per_piece',
    'wholesale'

];


if (
    !in_array(
        $sellingMethod,
        $allowedSellingMethods,
        true
    )
) {

    redirectWithError(
        'Invalid selling method.'
    );

}


/*
|--------------------------------------------------------------------------
| ITEMS
|--------------------------------------------------------------------------
*/

$items =
    $_POST['items']
    ?? [];


if (
    !is_array($items) ||
    empty($items)
) {

    redirectWithError(
        'Please add at least one item.'
    );

}


/*
|--------------------------------------------------------------------------
| UPLOAD CONFIGURATION
|--------------------------------------------------------------------------
*/

$uploadDirectory =
    dirname(__DIR__) .
    DIRECTORY_SEPARATOR .
    'uploads' .
    DIRECTORY_SEPARATOR .
    'submissions';


if (
    !is_dir(
        $uploadDirectory
    )
) {

    if (
        !mkdir(
            $uploadDirectory,
            0755,
            true
        )
    ) {

        redirectWithError(
            'Unable to prepare image upload directory.'
        );

    }

}


$finfo =
    new finfo(
        FILEINFO_MIME_TYPE
    );


$allowedMimeTypes = [

    'image/jpeg' =>
        'jpg',

    'image/png' =>
        'png',

    'image/webp' =>
        'webp'

];


$maxFileSize =
    5 * 1024 * 1024;


/*
|--------------------------------------------------------------------------
| DATABASE TRANSACTION
|--------------------------------------------------------------------------
*/

$createdFiles =
    [];


try {

    $pdo->beginTransaction();


    /*
    |--------------------------------------------------------------------------
    | REFERENCE NUMBER
    |--------------------------------------------------------------------------
    */

    $referenceNumber =
        generateReferenceNumber(
            $pdo
        );


    /*
    |--------------------------------------------------------------------------
    | CREATE SUBMISSION
    |--------------------------------------------------------------------------
    */

    $submissionStmt =
        $pdo->prepare("
            INSERT INTO submissions
            (
                user_id,
                reference_number,
                seller_name,
                seller_mobile,
                seller_address,
                selling_method,
                status,
                seller_notes,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                'new_submission',
                ?,
                NOW(),
                NOW()
            )
        ");


    $submissionStmt->execute([

        $userId,

        $referenceNumber,

        $sellerName,

        $sellerMobile,

        $sellerAddress,

        $sellingMethod,

        $sellerNotes

    ]);


    $submissionId =
        (int) $pdo->lastInsertId();


    /*
    |--------------------------------------------------------------------------
    | ITEM STATEMENT
    |--------------------------------------------------------------------------
    */

    $itemStmt =
        $pdo->prepare("
            INSERT INTO submission_items
            (
                submission_id,
                item_type,
                brand,
                model,
                storage,
                issue,
                issue_details,
                damage_level,
                board_code,
                quantity,
                seller_asking_price,
                additional_information,
                created_at,
                updated_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW(),
                NOW()
            )
        ");


    /*
    |--------------------------------------------------------------------------
    | PHOTO STATEMENT
    |--------------------------------------------------------------------------
    */

    $photoStmt =
        $pdo->prepare("
            INSERT INTO submission_photos
            (
                submission_item_id,
                file_name,
                file_path,
                original_name,
                mime_type,
                file_size,
                photo_type,
                sort_order,
                created_at
            )
            VALUES
            (
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                ?,
                NOW()
            )
        ");


    /*
    |--------------------------------------------------------------------------
    | PROCESS ITEMS
    |--------------------------------------------------------------------------
    */

    $processedItemCount =
        0;


    foreach (
        $items as $index => $item
    ) {

        if (
            !is_array($item)
        ) {

            continue;

        }


        /*
        |--------------------------------------------------------------------------
        | ITEM TYPE
        |--------------------------------------------------------------------------
        */

        $itemType =
            trim(
                (string) (
                    $item['item_type']
                    ?? ''
                )
            );


        if (
            !in_array(
                $itemType,
                [
                    'Whole Phone',
                    'Scrap Board'
                ],
                true
            )
        ) {

            throw new RuntimeException(
                'Invalid item type.'
            );

        }


        $itemTypeDbValue =
            $itemType === 'Whole Phone'
                ? 'whole_phone'
                : 'scrap_board';


        /*
        |--------------------------------------------------------------------------
        | BASIC ITEM DATA
        |--------------------------------------------------------------------------
        */

        $brand =
            cleanText(
                $item['brand'] ?? '',
                100
            );


        $model =
            cleanText(
                $item['model'] ?? '',
                150
            );


        $storage =
            cleanText(
                $item['storage'] ?? '',
                50
            );


        $issue =
            cleanText(
                $item['issue'] ?? '',
                100
            );


        $issueDetails =
            cleanText(
                $item['issue_details'] ?? '',
                1000
            );


        /*
        |--------------------------------------------------------------------------
        | EXTRA ISSUE DETAILS
        |--------------------------------------------------------------------------
        */

        $extraIssueDetails =
            cleanText(
                $item['issue_details_extra'] ?? '',
                1000
            );


        if (
            $extraIssueDetails
        ) {

            if (
                $issueDetails
            ) {

                $issueDetails .=
                    "\n" .
                    $extraIssueDetails;

            } else {

                $issueDetails =
                    $extraIssueDetails;

            }

        }


        /*
        |--------------------------------------------------------------------------
        | BOARD CODE
        |--------------------------------------------------------------------------
        */

        $boardCode =
            cleanText(
                $item['board_code'] ?? '',
                150
            );


        /*
        |--------------------------------------------------------------------------
        | QUANTITY
        |--------------------------------------------------------------------------
        */

        $quantity =
            isset(
                $item['quantity']
            )
                ? (int) $item['quantity']
                : 0;


        if (
            $quantity < 1
        ) {

            throw new RuntimeException(
                'Every item must have a quantity of at least 1.'
            );

        }


        if (
            $quantity > 100000
        ) {

            throw new RuntimeException(
                'Item quantity is too large.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | CONDITION / DAMAGE LEVEL
        |--------------------------------------------------------------------------
        */

        $damageLevel =
            isset(
                $item['damage_level']
            )
                ? (int) $item['damage_level']
                : 0;


        if (
            $damageLevel < 1 ||
            $damageLevel > 5
        ) {

            throw new RuntimeException(
                'Every item must have a valid condition level.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | WHOLE PHONE VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $itemType === 'Whole Phone'
        ) {

            if (
                !$issue
            ) {

                throw new RuntimeException(
                    'Every whole phone must have a main issue.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | SCRAP BOARD VALIDATION
        |--------------------------------------------------------------------------
        */

        if (
            $itemType === 'Scrap Board'
        ) {

            if (
                !$boardCode
            ) {

                throw new RuntimeException(
                    'Every scrap board must have a board code or marking.'
                );

            }

        }


        /*
        |--------------------------------------------------------------------------
        | ASKING PRICE
        |--------------------------------------------------------------------------
        */

        $askingPriceRaw =
            $item['seller_asking_price']
            ?? '';


        if (
            $askingPriceRaw === '' ||
            !is_numeric(
                $askingPriceRaw
            )
        ) {

            throw new RuntimeException(
                'Every item must have a seller asking price.'
            );

        }


        $askingPrice =
            (float) $askingPriceRaw;


        if (
            $askingPrice < 0
        ) {

            throw new RuntimeException(
                'Seller asking price cannot be negative.'
            );

        }


        if (
            $askingPrice > 999999999
        ) {

            throw new RuntimeException(
                'Seller asking price is too large.'
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ADDITIONAL INFORMATION
        |--------------------------------------------------------------------------
        */

        $additionalInformation =
            cleanText(
                $item['additional_information']
                ?? '',
                1500
            );


        /*
        |--------------------------------------------------------------------------
        | INSERT ITEM
        |--------------------------------------------------------------------------
        */

        $itemStmt->execute([

            $submissionId,

            $itemTypeDbValue,

            $brand,

            $model,

            $storage,

            $issue,

            $issueDetails,

            $damageLevel,

            $boardCode,

            $quantity,

            $askingPrice,

            $additionalInformation

        ]);


        $submissionItemId =
            (int) $pdo->lastInsertId();


        $processedItemCount++;


        /*
        |--------------------------------------------------------------------------
        | PHOTOS
        |--------------------------------------------------------------------------
        */

        $uploadedFiles =
            getUploadedFiles(
                $_FILES['photos'] ?? [],
                $index
            );


        /*
        |--------------------------------------------------------------------------
        | PHOTO REQUIREMENT
        |--------------------------------------------------------------------------
        */

        if (
            count(
                $uploadedFiles
            ) === 0
        ) {

            throw new RuntimeException(
                'Every item must have at least one photo.'
            );

        }


        if (
            count(
                $uploadedFiles
            ) > 10
        ) {

            throw new RuntimeException(
                'You can upload a maximum of 10 photos per item.'
            );

        }


        $sortOrder =
            1;


        foreach (
            $uploadedFiles as $file
        ) {

            if (
                !isset(
                    $file['error']
                ) ||
                $file['error'] ===
                    UPLOAD_ERR_NO_FILE
            ) {

                continue;

            }


            if (
                $file['error'] !==
                    UPLOAD_ERR_OK
            ) {

                throw new RuntimeException(
                    'One of the uploaded photos failed.'
                );

            }


            if (
                !is_uploaded_file(
                    $file['tmp_name']
                )
            ) {

                throw new RuntimeException(
                    'Invalid photo upload detected.'
                );

            }


            if (
                $file['size'] <= 0 ||
                $file['size'] >
                    $maxFileSize
            ) {

                throw new RuntimeException(
                    'Each photo must be 5MB or smaller.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | DETECT MIME TYPE
            |--------------------------------------------------------------------------
            */

            $detectedMime =
                $finfo->file(
                    $file['tmp_name']
                );


            if (
                !isset(
                    $allowedMimeTypes[
                        $detectedMime
                    ]
                )
            ) {

                throw new RuntimeException(
                    'Only JPG, PNG, and WEBP images are allowed.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | VERIFY IMAGE DATA
            |--------------------------------------------------------------------------
            */

            if (
                @getimagesize(
                    $file['tmp_name']
                ) === false
            ) {

                throw new RuntimeException(
                    'One of the uploaded files is not a valid image.'
                );

            }


            /*
            |--------------------------------------------------------------------------
            | FILE EXTENSION
            |--------------------------------------------------------------------------
            */

            $extension =
                $allowedMimeTypes[
                    $detectedMime
                ];


            /*
            |--------------------------------------------------------------------------
            | SAFE FILE NAME
            |--------------------------------------------------------------------------
            */

            $safeFileName =
                'submission_' .
                $submissionId .
                '_item_' .
                $submissionItemId .
                '_' .
                bin2hex(
                    random_bytes(8)
                ) .
                '.' .
                $extension;


            $destination =
                $uploadDirectory .
                DIRECTORY_SEPARATOR .
                $safeFileName;


            /*
            |--------------------------------------------------------------------------
            | MOVE FILE
            |--------------------------------------------------------------------------
            */

            if (
                !move_uploaded_file(
                    $file['tmp_name'],
                    $destination
                )
            ) {

                throw new RuntimeException(
                    'Unable to save one of the uploaded photos.'
                );

            }


            $createdFiles[] =
                $destination;


            /*
            |--------------------------------------------------------------------------
            | PHOTO TYPE
            |--------------------------------------------------------------------------
            */

            $photoType =
                $sortOrder === 1
                    ? 'primary'
                    : 'additional';


            /*
            |--------------------------------------------------------------------------
            | PUBLIC FILE PATH
            |--------------------------------------------------------------------------
            */

            $filePath =
                '/VNZ-TECH/uploads/submissions/' .
                $safeFileName;


            /*
            |--------------------------------------------------------------------------
            | ORIGINAL FILE NAME
            |--------------------------------------------------------------------------
            */

            $originalName =
                mb_substr(
                    basename(
                        (string) (
                            $file['name']
                            ?? ''
                        )
                    ),
                    0,
                    255
                );


            /*
            |--------------------------------------------------------------------------
            | INSERT PHOTO
            |--------------------------------------------------------------------------
            */

            $photoStmt->execute([

                $submissionItemId,

                $safeFileName,

                $filePath,

                $originalName,

                $detectedMime,

                (int) $file['size'],

                $photoType,

                $sortOrder

            ]);


            $sortOrder++;

        }

    }


    /*
    |--------------------------------------------------------------------------
    | AT LEAST ONE ITEM
    |--------------------------------------------------------------------------
    */

    if (
        $processedItemCount < 1
    ) {

        throw new RuntimeException(
            'No valid items were submitted.'
        );

    }


    /*
    |--------------------------------------------------------------------------
    | COMMIT
    |--------------------------------------------------------------------------
    */

    $pdo->commit();


    /*
    |--------------------------------------------------------------------------
    | SUCCESS REDIRECT
    |--------------------------------------------------------------------------
    */

    if (
        $userId !== null
    ) {

        /*
        |--------------------------------------------------------------------------
        | SELLER PORTAL
        |--------------------------------------------------------------------------
        |
        | Stay inside:
        |
        | /account/dashboard.php
        |
        | and open the newly created submission directly.
        |
        */

        header(
            'Location: /VNZ-TECH/account/dashboard.php?page=submission&ref=' .
            rawurlencode(
                $referenceNumber
            )
        );

        exit;

    }


    /*
    |--------------------------------------------------------------------------
    | PUBLIC / GUEST
    |--------------------------------------------------------------------------
    |
    | This is now correctly reached for BOTH:
    |   - a real anonymous guest
    |   - a logged-in seller who submitted via the public sell.php form
    |
    */

    header(
        'Location: /VNZ-TECH/sell.php?submitted=1&ref=' .
        rawurlencode(
            $referenceNumber
        )
    );

    exit;


} catch (
    Throwable $e
) {


    /*
    |--------------------------------------------------------------------------
    | ROLLBACK
    |--------------------------------------------------------------------------
    */

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();

    }


    /*
    |--------------------------------------------------------------------------
    | DELETE FILES ALREADY CREATED
    |--------------------------------------------------------------------------
    */

    foreach (
        $createdFiles as $createdFile
    ) {

        if (
            is_file(
                $createdFile
            )
        ) {

            @unlink(
                $createdFile
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | ERROR
    |--------------------------------------------------------------------------
    */

    redirectWithError(
        $e->getMessage()
    );

}