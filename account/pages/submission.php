<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';


/*
|--------------------------------------------------------------------------
| AUTH CHECK
|--------------------------------------------------------------------------
*/

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


$userId =
    (int) $_SESSION['user_id'];


if (
    $userId <= 0
) {

    http_response_code(401);

    echo '
        <div class="portal-error">
            <h2>Invalid session</h2>
            <p>Please log in again.</p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| USER ACCOUNT
|--------------------------------------------------------------------------
*/

try {

    $userStmt =
        $pdo->prepare("
            SELECT
                id,
                full_name,
                mobile,
                email,
                is_active
            FROM users
            WHERE id = ?
            LIMIT 1
        ");

    $userStmt->execute([
        $userId
    ]);

    $user =
        $userStmt->fetch();


    if (
        !$user ||
        (int) $user['is_active'] !== 1
    ) {

        http_response_code(404);

        echo '
            <div class="portal-error">
                <h2>Account not found</h2>
                <p>Please contact VNZ TECH.</p>
            </div>
        ';

        exit;
    }

} catch (Throwable $e) {

    http_response_code(500);

    echo '
        <div class="portal-error">
            <h2>Unable to load submission</h2>
            <p>Something went wrong while loading your submission.</p>
        </div>
    ';

    exit;
}


/*
|--------------------------------------------------------------------------
| REFERENCE
|--------------------------------------------------------------------------
*/

$reference =
    trim(
        $_GET['ref'] ?? ''
    );


if (
    $reference === ''
) {

    echo '
        <section class="submission-detail-page">

            <div class="portal-error">

                <h2>
                    Submission not specified
                </h2>

                <p>
                    Please open a submission from My Submissions.
                </p>

                <button
                    type="button"
                    class="submission-back-button"
                    data-back-to-submissions
                >
                    ← BACK TO MY SUBMISSIONS
                </button>

            </div>

        </section>
    ';

    exit;
}


$mobile =
    trim(
        (string) (
            $user['mobile'] ?? ''
        )
    );


function submissionEscape($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}


/*
|--------------------------------------------------------------------------
| INITIAL PAGE DATA
|--------------------------------------------------------------------------
*/

$initialReference =
    submissionEscape(
        $reference
    );

$initialMobile =
    submissionEscape(
        $mobile
    );

?>

<section class="seller-submission-detail-page">


    <!-- =====================================================
         BACK TO MY SUBMISSIONS
    ====================================================== -->

    <button
        type="button"
        class="submission-back-button"
        data-back-to-submissions
    >

        <span>
            ←
        </span>

        BACK TO MY SUBMISSIONS

    </button>


    <!-- =====================================================
         PAGE HEADER
    ====================================================== -->

    <div class="seller-submission-detail-header">

        <div>

            <span class="eyebrow">
                MY SUBMISSIONS
            </span>

            <h1>
                Submission Details
            </h1>

            <p>
                Review your submitted item,
                VNZ TECH offers, and negotiation status.
            </p>

        </div>


        <div class="seller-submission-reference">

            <?= $initialReference ?>

        </div>

    </div>


    <!-- =====================================================
         STATUS MESSAGE
    ====================================================== -->

    <div
        id="submissionTrackMessage"
        class="submission-track-message"
        hidden
    ></div>


    <!-- =====================================================
         RESULT AREA
    ====================================================== -->

    <section
        id="submissionResultArea"
        class="submission-result-area"
    >

        <div class="submission-loading-card">

            <span class="submission-loading-spinner"></span>

            <strong>
                Loading submission...
            </strong>

            <small>
                Please wait while we retrieve your submission.
            </small>

        </div>

    </section>


</section>


<script>

(function () {

    /*
    |--------------------------------------------------------------------------
    | DATA
    |--------------------------------------------------------------------------
    */

    const reference =
        <?= json_encode(
            $reference,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>;


    const mobile =
        <?= json_encode(
            $mobile,
            JSON_UNESCAPED_UNICODE |
            JSON_UNESCAPED_SLASHES
        ) ?>;


    const resultArea =
        document.getElementById(
            'submissionResultArea'
        );


    const messageArea =
        document.getElementById(
            'submissionTrackMessage'
        );


    if (
        !resultArea
    ) {
        return;
    }


    /*
    |--------------------------------------------------------------------------
    | BACK TO MY SUBMISSIONS
    |--------------------------------------------------------------------------
    */

    const backButton =
        document.querySelector(
            '[data-back-to-submissions]'
        );


    if (
        backButton
    ) {

        backButton.addEventListener(
            'click',
            function () {

                /*
                |--------------------------------------------------------------------------
                | USE SELLER PORTAL ROUTER
                |--------------------------------------------------------------------------
                |
                | This keeps the user inside:
                |
                | account/dashboard.php
                |
                | and simply loads:
                |
                | account/pages/submissions.php
                |
                */

                if (
                    typeof window.loadSellerPage ===
                    'function'
                ) {

                    window.loadSellerPage(
                        'submissions'
                    );

                    return;

                }


                /*
                |--------------------------------------------------------------------------
                | FALLBACK
                |--------------------------------------------------------------------------
                */

                window.location.href =
                    '/VNZ-TECH/account/dashboard.php?page=submissions';

            }
        );

    }


    /*
    |--------------------------------------------------------------------------
    | ESCAPE HTML
    |--------------------------------------------------------------------------
    */

    function escapeHtml(value) {

        const div =
            document.createElement('div');

        div.textContent =
            value ?? '';

        return div.innerHTML;
    }


    /*
    |--------------------------------------------------------------------------
    | MONEY
    |--------------------------------------------------------------------------
    */

    function money(amount) {

        return '₱' +
            Number(amount || 0)
                .toLocaleString(
                    'en-PH',
                    {
                        minimumFractionDigits: 2,
                        maximumFractionDigits: 2
                    }
                );

    }


    /*
    |--------------------------------------------------------------------------
    | STATUS CLASS
    |--------------------------------------------------------------------------
    */

    function statusClass(status) {

        return {

            new_submission:
                'status-new',

            under_review:
                'status-review',

            offer_pending:
                'status-pending',

            offer_sent:
                'status-offer',

            accepted:
                'status-accepted',

            declined:
                'status-declined',

            cancelled:
                'status-cancelled',

            completed:
                'status-completed'

        }[
            status
        ] || 'status-default';

    }


    /*
    |--------------------------------------------------------------------------
    | STATUS LABEL
    |--------------------------------------------------------------------------
    */

    function statusLabel(status) {

        return {

            new_submission:
                'New Submission',

            under_review:
                'Under Review',

            offer_pending:
                'Offer Pending',

            offer_sent:
                'Offer Sent',

            accepted:
                'Accepted',

            declined:
                'Declined',

            cancelled:
                'Cancelled',

            completed:
                'Completed'

        }[
            status
        ] || status;

    }


    /*
    |--------------------------------------------------------------------------
    | NEGOTIATION FINISHED
    |--------------------------------------------------------------------------
    */

    function isNegotiationFinished(
        status
    ) {

        return [

            'accepted',
            'declined',
            'cancelled',
            'completed'

        ].includes(
            String(
                status || ''
            ).toLowerCase()
        );

    }


    /*
    |--------------------------------------------------------------------------
    | SHOW MESSAGE
    |--------------------------------------------------------------------------
    */

    function showMessage(
        message,
        type = 'error'
    ) {

        if (!messageArea) {
            return;
        }

        messageArea.hidden =
            false;

        messageArea.className =
            'submission-track-message ' +
            type;

        messageArea.textContent =
            message;

    }


    function isWholePhone(itemType) {
        return String(itemType || '').toLowerCase() === 'whole_phone';
    }


    /*
    |--------------------------------------------------------------------------
    | RENDER ITEMS
    |--------------------------------------------------------------------------
    */

    function renderItems(
        items
    ) {

        let itemsHtml =
            '';


        items.forEach(
            function(item, index) {

                const itemName =
                    isWholePhone(item.item_type)

                        ? (
                            (
                                item.brand || ''
                            )
                            + ' ' +
                            (
                                item.model || ''
                            )
                        ).trim()
                        ||
                        'Unknown Phone'

                        : 'Scrap Board';


                itemsHtml += `

                    <article class="result-item">

                        <div class="result-item-top">

                            <div>

                                <span class="result-item-number">

                                    ${String(
                                        index + 1
                                    ).padStart(
                                        2,
                                        '0'
                                    )}

                                </span>


                                <div>

                                    <small>
                                        ${escapeHtml(
                                            item.item_type
                                        )}
                                    </small>


                                    <strong>
                                        ${escapeHtml(
                                            itemName
                                        )}
                                    </strong>

                                </div>

                            </div>


                            <span class="result-quantity">

                                QTY
                                ${Number(
                                    item.quantity || 0
                                ).toLocaleString()}

                            </span>

                        </div>


                        <div class="result-item-grid">


                            ${
                                isWholePhone(item.item_type)

                                ? `

                                    <div>

                                        <small>
                                            STORAGE
                                        </small>

                                        <strong>
                                            ${escapeHtml(
                                                item.storage
                                                || 'Unknown'
                                            )}
                                        </strong>

                                    </div>


                                    <div>

                                        <small>
                                            ISSUE
                                        </small>

                                        <strong>
                                            ${escapeHtml(
                                                item.issue
                                                || '—'
                                            )}
                                        </strong>

                                    </div>

                                `

                                : `

                                    <div>

                                        <small>
                                            BOARD CODE
                                        </small>

                                        <strong
                                            class="board-code"
                                        >
                                            ${escapeHtml(
                                                item.board_code
                                                || '—'
                                            )}
                                        </strong>

                                    </div>


                                    <div>

                                        <small>
                                            CONDITION
                                        </small>

                                        <strong>
                                            ${
                                                item.damage_level
                                                    ? 'Level ' +
                                                        item.damage_level
                                                    : '—'
                                            }
                                        </strong>

                                    </div>

                                `
                            }


                            <div>

                                <small>
                                    ASKING PRICE
                                </small>

                                <strong>
                                    ${money(
                                        item.seller_asking_price
                                    )}
                                </strong>

                            </div>


                        </div>


                    </article>

                `;

            }
        );


        return itemsHtml;

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER FINISHED OFFER
    |--------------------------------------------------------------------------
    */

    function renderFinishedOffer(
        submission,
        offer
    ) {

        const submissionStatus =
            String(
                submission.status || ''
            ).toLowerCase();


        const isAccepted =
            submissionStatus ===
            'accepted';


        const isDeclined =
            submissionStatus ===
            'declined';


        const isCancelled =
            submissionStatus ===
            'cancelled';


        const isCompleted =
            submissionStatus ===
            'completed';


        let title =
            'Negotiation Closed';


        let eyebrow =
            'NEGOTIATION CLOSED';


        let noteTitle =
            'This negotiation is closed.';


        let noteText =
            'No further offer responses or counter-offers are available.';


        let cardClass =
            'negotiation-finished-card';


        if (isAccepted) {

            title =
                'Offer Accepted';


            noteTitle =
                'Your offer has been accepted.';


            noteText =
                'The negotiation is complete. VNZ TECH will proceed with the next transaction step.';


            cardClass +=
                ' finished-accepted';

        }


        if (isDeclined) {

            title =
                'Offer Declined';


            noteTitle =
                'The offer was declined.';


            noteText =
                'This negotiation has ended and no further counter-offers are available.';


            cardClass +=
                ' finished-declined';

        }


        if (isCancelled) {

            title =
                'Submission Cancelled';


            noteTitle =
                'This submission has been cancelled.';


            noteText =
                'No further negotiation is available for this submission.';


            cardClass +=
                ' finished-cancelled';

        }


        if (isCompleted) {

            eyebrow =
                'TRANSACTION COMPLETED';


            title =
                'Transaction Completed';


            noteTitle =
                'This transaction has been completed.';


            noteText =
                'The submission is already closed and no further negotiation is available.';


            cardClass +=
                ' finished-completed';

        }


        const senderType =
            offer.sender_type ||
            'admin';


        return `

            <section
                class="
                    offer-result-card
                    ${cardClass}
                "
            >

                <div class="finished-offer-header">

                    <div>

                        <span class="section-label">
                            ${eyebrow}
                        </span>


                        <h2>
                            ${title}
                        </h2>

                    </div>


                    <span class="
                        result-status
                        ${statusClass(
                            submission.status
                        )}
                    ">
                        ${escapeHtml(
                            statusLabel(
                                submission.status
                            )
                        )}
                    </span>

                </div>


                <div class="finished-offer-amount">

                    ${money(
                        offer.offer_amount
                    )}

                </div>


                ${
                    offer.feedback
                    ? `

                        <div class="offer-message">

                            <span>
                                ${
                                    senderType ===
                                    'seller'
                                        ? 'YOUR MESSAGE'
                                        : 'MESSAGE FROM VNZ TECH'
                                }
                            </span>


                            <p>
                                ${escapeHtml(
                                    offer.feedback
                                )}
                            </p>

                        </div>

                    `
                    : ''
                }


                <div class="finished-offer-note">

                    <strong>
                        ${noteTitle}
                    </strong>

                    <span>
                        ${noteText}
                    </span>

                </div>

            </section>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER ACTIVE ADMIN OFFER
    |--------------------------------------------------------------------------
    */

    function renderAdminOffer(
        submission,
        offer
    ) {

        const offerStatus =
            String(
                offer.status || ''
            ).toLowerCase();


        const canRespond =
            submission.status ===
                'offer_sent'
            &&
            offerStatus !==
                'accepted'
            &&
            offerStatus !==
                'declined';


        return `

            <section class="offer-result-card">

                <div class="offer-header">

                    <div>

                        <span class="section-label">
                            VNZ TECH OFFER
                        </span>


                        <h2>
                            ${money(
                                offer.offer_amount
                            )}
                        </h2>

                    </div>


                    <span class="
                        result-status
                        ${statusClass(
                            submission.status
                        )}
                    ">

                        ${escapeHtml(
                            statusLabel(
                                submission.status
                            )
                        )}

                    </span>

                </div>


                ${
                    offer.feedback

                    ? `

                        <div class="offer-message">

                            <span>
                                MESSAGE FROM VNZ TECH
                            </span>

                            <p>
                                ${escapeHtml(
                                    offer.feedback
                                )}
                            </p>

                        </div>

                    `

                    : ''
                }


                ${
                    canRespond

                    ? `

                        <div class="response-warning">

                            Review the offer below.
                            You can accept it, decline it,
                            or send a counter-offer.

                        </div>


                        <div class="offer-actions">

                            <button
                                type="button"
                                class="decline-offer-btn"
                                data-response="decline"
                                data-offer-id="${offer.id}"
                            >
                                DECLINE
                            </button>


                            <button
                                type="button"
                                class="counter-offer-btn"
                                data-counter-offer
                                data-offer-id="${offer.id}"
                            >
                                COUNTER OFFER
                            </button>


                            <button
                                type="button"
                                class="accept-offer-btn"
                                data-response="accept"
                                data-offer-id="${offer.id}"
                            >
                                ACCEPT
                                ${money(
                                    offer.offer_amount
                                )}
                                →
                            </button>

                        </div>


                        <div
                            class="counter-form"
                            data-counter-form
                            hidden
                        >

                            <div class="counter-form-heading">

                                <div>

                                    <span>
                                        NEGOTIATION
                                    </span>


                                    <strong>
                                        Make your counter-offer
                                    </strong>

                                </div>


                                <div class="counter-reference">

                                    ${escapeHtml(
                                        reference
                                    )}

                                </div>

                            </div>


                            <div class="counter-form-description">

                                Tell VNZ TECH the amount you are
                                willing to accept. You can also
                                add a short message or reason
                                for your counter-offer.

                            </div>


                            <div class="counter-field">

                                <label>
                                    YOUR COUNTER-OFFER
                                </label>


                                <div class="counter-money">

                                    <span>
                                        ₱
                                    </span>


                                    <input
                                        type="number"
                                        min="1"
                                        max="999999999"
                                        step="0.01"
                                        placeholder="0.00"
                                        data-counter-amount
                                        inputmode="decimal"
                                    >

                                </div>

                            </div>


                            <div class="counter-field">

                                <label>

                                    MESSAGE

                                    <span>
                                        OPTIONAL
                                    </span>

                                </label>


                                <textarea
                                    rows="5"
                                    maxlength="3000"
                                    placeholder="Example: I can let this go for ₱1,500 because of the condition."
                                    data-counter-feedback
                                ></textarea>


                                <div class="counter-character-note">
                                    Up to 3,000 characters
                                </div>

                            </div>


                            <div class="counter-form-actions">

                                <button
                                    type="button"
                                    class="cancel-counter-btn"
                                    data-cancel-counter
                                >
                                    CANCEL
                                </button>


                                <button
                                    type="button"
                                    class="send-counter-btn"
                                    data-send-counter
                                    data-offer-id="${offer.id}"
                                >
                                    SEND COUNTER-OFFER
                                    <span>→</span>
                                </button>

                            </div>

                        </div>

                    `

                    : ''

                }

            </section>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER SELLER COUNTER
    |--------------------------------------------------------------------------
    */

    function renderSellerCounter(
        submission,
        offer
    ) {

        return `

            <section
                class="
                    offer-result-card
                    seller-counter-card
                "
            >

                <div class="offer-header">

                    <div>

                        <span class="section-label">
                            YOUR COUNTER-OFFER
                        </span>


                        <h2>
                            ${money(
                                offer.offer_amount
                            )}
                        </h2>

                    </div>


                    <span class="
                        result-status
                        status-pending
                    ">
                        WAITING FOR VNZ TECH
                    </span>

                </div>


                ${
                    offer.feedback

                    ? `

                        <div class="offer-message">

                            <span>
                                YOUR MESSAGE
                            </span>


                            <p>
                                ${escapeHtml(
                                    offer.feedback
                                )}
                            </p>

                        </div>

                    `

                    : ''
                }


                <div class="response-warning">

                    Your counter-offer has been sent to
                    VNZ TECH. Please wait for their response
                    before making another move.

                </div>

            </section>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER OFFER
    |--------------------------------------------------------------------------
    */

    function renderOffer(
        submission,
        offer
    ) {

        if (
            isNegotiationFinished(
                submission.status
            )
        ) {

            return renderFinishedOffer(
                submission,
                offer
            );

        }


        const senderType =
            String(
                offer.sender_type ||
                'admin'
            ).toLowerCase();


        if (
            senderType ===
            'seller'
        ) {

            return renderSellerCounter(
                submission,
                offer
            );

        }


        return renderAdminOffer(
            submission,
            offer
        );

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER NO OFFER
    |--------------------------------------------------------------------------
    */

    function renderNoOffer(
        submission
    ) {

        return `

            <section class="no-offer-card">

                <span>
                    CURRENT STATUS
                </span>


                <strong>
                    ${escapeHtml(
                        statusLabel(
                            submission.status
                        )
                    )}
                </strong>


                <p>
                    Wala pang offer from VNZ TECH.
                    We will update this submission once
                    your item has been reviewed.
                </p>

            </section>

        `;

    }


    /*
    |--------------------------------------------------------------------------
    | RENDER RESULT
    |--------------------------------------------------------------------------
    */

    function renderResult(
        data
    ) {

        const submission =
            data.submission;


        const items =
            submission.items ||
            [];


        const offer =
            submission.offer ||
            null;


        let offerHtml =
            '';


        if (
            offer
        ) {

            offerHtml =
                renderOffer(
                    submission,
                    offer
                );

        } else {

            offerHtml =
                renderNoOffer(
                    submission
                );

        }


        resultArea.innerHTML = `

            <div class="result-header">

                <div>

                    <span class="section-label">
                        SUBMISSION FOUND
                    </span>


                    <h2>
                        ${escapeHtml(
                            submission.reference_number
                        )}
                    </h2>

                </div>


                <span class="
                    result-status
                    ${statusClass(
                        submission.status
                    )}
                ">

                    ${escapeHtml(
                        statusLabel(
                            submission.status
                        )
                    )}

                </span>

            </div>


            <section class="seller-result-card">

                <div class="section-label">
                    SELLER
                </div>


                <div class="seller-result-grid">


                    <div>

                        <small>
                            NAME
                        </small>

                        <strong>
                            ${escapeHtml(
                                submission.seller_name
                            )}
                        </strong>

                    </div>


                    <div>

                        <small>
                            MOBILE
                        </small>

                        <strong>
                            ${escapeHtml(
                                submission.seller_mobile
                            )}
                        </strong>

                    </div>


                    <div>

                        <small>
                            SELLING METHOD
                        </small>

                        <strong>

                            ${
                                submission.selling_method ===
                                'wholesale'
                                    ? 'Wholesale'
                                    : 'Per Piece'
                            }

                        </strong>

                    </div>


                    <div>

                        <small>
                            SUBMITTED
                        </small>

                        <strong>
                            ${escapeHtml(
                                submission.created_at
                            )}
                        </strong>

                    </div>


                </div>

            </section>


            <section class="items-result-card">

                <div class="section-label">
                    SUBMITTED ITEMS
                </div>


                <div class="result-items">

                    ${renderItems(items)}

                </div>

            </section>


            ${offerHtml}

        `;


        attachOfferEvents();

    }


    /*
    |--------------------------------------------------------------------------
    | OFFER EVENTS
    |--------------------------------------------------------------------------
    */

    function attachOfferEvents() {


        resultArea
            .querySelectorAll(
                '[data-response]'
            )
            .forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            respondToOffer(
                                this.dataset.response,
                                this.dataset.offerId
                            );

                        }
                    );

                }
            );


        resultArea
            .querySelectorAll(
                '[data-counter-offer]'
            )
            .forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            const counterForm =
                                resultArea.querySelector(
                                    '[data-counter-form]'
                                );


                            if (
                                !counterForm
                            ) {
                                return;
                            }


                            counterForm.hidden =
                                false;


                            this.classList.add(
                                'counter-open'
                            );


                            const amountInput =
                                counterForm.querySelector(
                                    '[data-counter-amount]'
                                );


                            if (
                                amountInput
                            ) {

                                setTimeout(
                                    function() {

                                        amountInput.focus();

                                    },
                                    80
                                );

                            }

                        }
                    );

                }
            );


        resultArea
            .querySelectorAll(
                '[data-cancel-counter]'
            )
            .forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            const counterForm =
                                resultArea.querySelector(
                                    '[data-counter-form]'
                                );


                            if (
                                counterForm
                            ) {

                                counterForm.hidden =
                                    true;

                            }

                        }
                    );

                }
            );


        resultArea
            .querySelectorAll(
                '[data-send-counter]'
            )
            .forEach(
                function(button) {

                    button.addEventListener(
                        'click',
                        function() {

                            sendCounterOffer(
                                this.dataset.offerId
                            );

                        }
                    );

                }
            );

    }


    /*
    |--------------------------------------------------------------------------
    | RESPOND TO OFFER
    |--------------------------------------------------------------------------
    */

    async function respondToOffer(
        responseType,
        offerId
    ) {

        const actionText =
            responseType === 'accept'
                ? 'accept this offer'
                : 'decline this offer';


        const confirmed =
            window.confirm(
                `Are you sure you want to ${actionText}?`
            );


        if (
            !confirmed
        ) {
            return;
        }


        const buttons =
            resultArea.querySelectorAll(
                '[data-response]'
            );


        buttons.forEach(
            function(button) {

                button.disabled =
                    true;

            }
        );


        const body =
            new FormData();


        body.append(
            'offer_id',
            offerId
        );


        body.append(
            'response',
            responseType
        );


        body.append(
            'reference',
            reference
        );


        body.append(
            'mobile',
            mobile
        );


        try {

            const response =
                await fetch(
                    '/VNZ-TECH/backend/respond_offer.php',
                    {
                        method: 'POST',
                        body
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'Unable to update offer.'
                );

            }


            showMessage(
                data.message,
                'success'
            );


            await refreshSubmission();


        } catch (
            error
        ) {

            showMessage(
                error.message,
                'error'
            );


            buttons.forEach(
                function(button) {

                    button.disabled =
                        false;

                }
            );

        }

    }


    /*
    |--------------------------------------------------------------------------
    | SEND COUNTER OFFER
    |--------------------------------------------------------------------------
    */

    async function sendCounterOffer(
        offerId
    ) {

        const counterForm =
            resultArea.querySelector(
                '[data-counter-form]'
            );


        if (
            !counterForm
        ) {

            showMessage(
                'Counter-offer form is unavailable.',
                'error'
            );

            return;

        }


        const amountInput =
            counterForm.querySelector(
                '[data-counter-amount]'
            );


        const feedbackInput =
            counterForm.querySelector(
                '[data-counter-feedback]'
            );


        const counterAmount =
            amountInput
                ? amountInput.value.trim()
                : '';


        const feedback =
            feedbackInput
                ? feedbackInput.value.trim()
                : '';


        if (
            counterAmount === ''
            ||
            !Number.isFinite(
                Number(counterAmount)
            )
        ) {

            showMessage(
                'Please enter your counter-offer amount.',
                'error'
            );


            if (
                amountInput
            ) {

                amountInput.focus();

            }


            return;

        }


        const numericAmount =
            Number(
                counterAmount
            );


        if (
            numericAmount <= 0
        ) {

            showMessage(
                'Counter-offer amount must be greater than ₱0.00.',
                'error'
            );


            if (
                amountInput
            ) {

                amountInput.focus();

            }


            return;

        }


        if (
            numericAmount >
            999999999
        ) {

            showMessage(
                'Counter-offer amount is too large.',
                'error'
            );

            return;

        }


        const confirmed =
            window.confirm(
                `Send counter-offer of ${money(
                    numericAmount
                )}?`
            );


        if (
            !confirmed
        ) {
            return;
        }


        const button =
            counterForm.querySelector(
                '[data-send-counter]'
            );


        const cancelButton =
            counterForm.querySelector(
                '[data-cancel-counter]'
            );


        if (
            button
        ) {

            button.disabled =
                true;


            button.innerHTML =
                'SENDING...';

        }


        if (
            cancelButton
        ) {

            cancelButton.disabled =
                true;

        }


        const body =
            new FormData();


        body.append(
            'offer_id',
            offerId
        );


        body.append(
            'reference',
            reference
        );


        body.append(
            'mobile',
            mobile
        );


        body.append(
            'counter_amount',
            numericAmount.toFixed(2)
        );


        body.append(
            'feedback',
            feedback
        );


        try {

            const response =
                await fetch(
                    '/VNZ-TECH/backend/counter_offer.php',
                    {
                        method: 'POST',
                        body
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok ||
                !data.success
            ) {

                throw new Error(
                    data.message ||
                    'Unable to send counter-offer.'
                );

            }


            showMessage(
                data.message,
                'success'
            );


            await refreshSubmission();


        } catch (
            error
        ) {

            showMessage(
                error.message,
                'error'
            );


            if (
                button
            ) {

                button.disabled =
                    false;


                button.innerHTML =
                    'SEND COUNTER-OFFER <span>→</span>';

            }


            if (
                cancelButton
            ) {

                cancelButton.disabled =
                    false;

            }

        }

    }


    /*
    |--------------------------------------------------------------------------
    | REFRESH SUBMISSION
    |--------------------------------------------------------------------------
    */

    async function refreshSubmission() {

        const body =
            new FormData();


        body.append(
            'reference',
            reference
        );


        body.append(
            'mobile',
            mobile
        );


        const response =
            await fetch(
                '/VNZ-TECH/backend/track_submission.php',
                {
                    method: 'POST',
                    body
                }
            );


        const data =
            await response.json();


        if (
            !response.ok ||
            !data.success
        ) {

            throw new Error(
                data.message ||
                'Unable to refresh submission.'
            );

        }


        renderResult(
            data
        );

    }


    /*
    |--------------------------------------------------------------------------
    | INITIAL LOAD
    |--------------------------------------------------------------------------
    */

    refreshSubmission()
        .catch(
            function(error) {

                resultArea.innerHTML = `

                    <div class="portal-error">

                        <h2>
                            Unable to load submission
                        </h2>

                        <p>
                            ${escapeHtml(
                                error.message
                            )}
                        </p>


                        <button
                            type="button"
                            class="submission-back-button"
                            data-back-to-submissions
                        >

                            ← BACK TO MY SUBMISSIONS

                        </button>

                    </div>

                `;


                /*
                |--------------------------------------------------------------------------
                | BACK BUTTON INSIDE ERROR STATE
                |--------------------------------------------------------------------------
                */

                const fallbackBackButton =
                    resultArea.querySelector(
                        '[data-back-to-submissions]'
                    );


                if (
                    fallbackBackButton
                ) {

                    fallbackBackButton.addEventListener(
                        'click',
                        function() {

                            if (
                                typeof window.loadSellerPage ===
                                'function'
                            ) {

                                window.loadSellerPage(
                                    'submissions'
                                );

                                return;

                            }


                            window.location.href =
                                '/VNZ-TECH/account/dashboard.php?page=submissions';

                        }
                    );

                }

            }
        );

})();

</script>