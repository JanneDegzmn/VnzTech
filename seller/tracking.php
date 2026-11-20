<?php


require_once __DIR__ . '/../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false);
require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';

$csrfToken = generateCsrfToken();

$reference = trim(
    $_GET['ref'] ?? ''
);

$mobile = trim(
    $_GET['mobile'] ?? ''
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Track Submission | VNZ TECH
    </title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_tracking.css"
    >
    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/vnz_dialog.css?v=<?= filemtime(__DIR__ . '/../assets/css/vnz_dialog.css') ?>"
    >
    <script
        src="/VNZ-TECH/assets/js/vnz-dialog.js"
        defer
    ></script>

<?php $siteStatusArea = 'public'; require __DIR__ . '/../includes/site-status-head.php'; ?>
</head>


<body>

<div class="tracking-page">


    <!-- =====================================================
         HEADER
    ====================================================== -->

    <header class="tracking-header">

        <div class="tracking-header-left">

            <a
                href="/VNZ-TECH/index.php"
                class="brand"
            >
                VNZ <span>TECH</span>
            </a>

        </div>


        <div class="tracking-header-actions">

            <a
                href="/VNZ-TECH/sell.php"
                class="back-home-link"
            >
                ← Back to Home
            </a>


            <a
                href="/VNZ-TECH/sell.php"
                class="sell-link"
            >
                Sell an Item →
            </a>

        </div>

    </header>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <main class="tracking-main">


        <!-- =================================================
             INTRO
        ================================================== -->

        <section class="tracking-intro">

            <span class="eyebrow">
                VNZ TECH SELLER PORTAL
            </span>


            <h1>
                Track your
                <span>submission.</span>
            </h1>


            <p>
                Enter your reference number and mobile number
                to check your submission status, review VNZ TECH
                offers, and continue your negotiation.
            </p>

        </section>


        <!-- =================================================
             SEARCH
        ================================================== -->

        <section class="tracking-search-card">

            <form id="trackingForm">

                <div class="tracking-form-grid">


                    <div class="form-field">

                        <label for="reference">
                            REFERENCE NUMBER
                        </label>


                        <input
                            type="text"
                            id="reference"
                            name="reference"
                            value="<?= htmlspecialchars(
                                $reference,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="#SP-260911-XXXX"
                            autocomplete="off"
                            required
                        >

                    </div>


                    <div class="form-field">

                        <label for="mobile">
                            MOBILE NUMBER
                        </label>


                        <input
                            type="tel"
                            id="mobile"
                            name="mobile"
                            value="<?= htmlspecialchars(
                                $mobile,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                            placeholder="09XXXXXXXXX"
                            autocomplete="tel"
                            required
                        >

                    </div>


                </div>


                <button
                    type="submit"
                    class="track-button"
                    id="trackButton"
                >
                    Track Submission
                    <span>→</span>
                </button>

            </form>


            <div
                id="trackMessage"
                class="track-message"
                hidden
            ></div>

        </section>


        <!-- =================================================
             RESULT
        ================================================== -->

        <section
            id="resultArea"
            class="result-area"
            hidden
        ></section>


    </main>


    <!-- =====================================================
         FOOTER
    ====================================================== -->

    <footer class="tracking-footer">

        <div>

            <strong>
                VNZ TECH
            </strong>

            <span>
                We buy. We recover. We revalue.
            </span>

        </div>


        <span>
            © <?= date('Y') ?> VNZ TECH
        </span>

    </footer>


</div>


<script>

/*
|--------------------------------------------------------------------------
| Elements
|--------------------------------------------------------------------------
*/

const trackingForm =
    document.getElementById('trackingForm');

const trackButton =
    document.getElementById('trackButton');

const trackMessage =
    document.getElementById('trackMessage');

const resultArea =
    document.getElementById('resultArea');


/*
|--------------------------------------------------------------------------
| Escape HTML
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
| Show Message
|--------------------------------------------------------------------------
*/

function showMessage(
    message,
    type = 'error'
) {

    trackMessage.hidden = false;

    trackMessage.className =
        'track-message ' + type;

    trackMessage.textContent =
        message;

}


/*
|--------------------------------------------------------------------------
| Hide Message
|--------------------------------------------------------------------------
*/

function hideMessage() {

    trackMessage.hidden = true;

}


/*
|--------------------------------------------------------------------------
| Money
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
| Status Class
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

    }[status] || 'status-default';

}


/*
|--------------------------------------------------------------------------
| Status Label
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

    }[status] || status;

}


/*
|--------------------------------------------------------------------------
| Is Negotiation Finished
|--------------------------------------------------------------------------
*/

function isNegotiationFinished(status) {

    return [
        'accepted',
        'declined',
        'cancelled',
        'completed'
    ].includes(
        String(status || '').toLowerCase()
    );

}


/*
|--------------------------------------------------------------------------
| Render Items
|--------------------------------------------------------------------------
*/

function renderItems(items) {

    let itemsHtml = '';


    items.forEach(
        function(item, index) {

            const isBulkLot =
                Boolean(submission.bulk_format);

            const isWholePhone =
                item.item_type === 'Whole Phone'
                || item.item_type === 'whole_phone';

            const itemName =
                isBulkLot
                    ? (isWholePhone ? 'Phone bulk' : 'Scrap board bulk')
                    : isWholePhone

                    ? (
                        (
                            item.brand || ''
                        )
                        + ' '
                        +
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
                                ${String(index + 1).padStart(2, '0')}
                            </span>


                            <div>

                                <small>
                                    ${isBulkLot
                                        ? (isWholePhone ? 'Bulk · Phone' : 'Bulk · Scrap board')
                                        : escapeHtml(item.item_type)
                                    }
                                </small>


                                <strong>
                                    ${escapeHtml(
                                        itemName
                                    )}
                                </strong>

                            </div>

                        </div>


                        <span class="result-quantity">

                            ${isBulkLot && Number(item.quantity_is_estimated) === 1 ? 'Estimated QTY' : 'QTY'}
                            ${Number(
                                item.quantity || 0
                            ).toLocaleString()}

                        </span>

                    </div>


                    <div class="result-item-grid">


                        ${
                            isBulkLot

                            ? `

                                <div>
                                    <small>QUANTITY TYPE</small>
                                    <strong>${Number(item.quantity_is_estimated) === 1 ? 'Estimated' : 'Exact'}</strong>
                                </div>

                            `

                            : isWholePhone

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
                                                ? 'Level '
                                                  + item.damage_level
                                                : '—'
                                        }

                                    </strong>

                                </div>

                            `
                        }


                        <div>

                            <small>
                                ${isBulkLot ? 'TOTAL ASKING PRICE FOR THIS LOT' : 'ASKING PRICE'}
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
| Render Finished Negotiation
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
        submissionStatus === 'accepted';


    const isDeclined =
        submissionStatus === 'declined';


    const isCancelled =
        submissionStatus === 'cancelled';


    const isCompleted =
        submissionStatus === 'completed';


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
        offer.sender_type || 'admin';


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
                                senderType === 'seller'
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
| Render Active Admin Offer
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
        String(submission.status || '').toLowerCase() === 'offer_sent'
        &&
        String(offer.sender_type || '').toLowerCase() === 'admin'
        &&
        ['sent', 'offer_sent', 'pending', 'active'].includes(offerStatus);


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
                            data-offer-amount="${Number(offer.offer_amount)}"
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
                            data-offer-amount="${Number(offer.offer_amount)}"
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


                            <div
                                class="counter-reference"
                            >
                                ${escapeHtml(
                                    document.getElementById(
                                        'reference'
                                    )?.value.trim()
                                    || '---'
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
| Render Seller Counter
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
| Render Offer
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
            offer.sender_type || 'admin'
        ).toLowerCase();


    if (senderType === 'seller') {

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
| Render No Offer
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
| Render Result
|--------------------------------------------------------------------------
*/

function renderResult(data) {

    const submission =
        data.submission;


    const items =
        submission.items || [];


    const offer =
        submission.offer || null;


    const itemsHtml =
        renderItems(items);


    let offerHtml =
        '';


    if (offer) {

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


        <!-- =====================================================
             SELLER
        ====================================================== -->

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
                            submission.bulk_format
                                ? 'Bulk'
                                : submission.selling_method === 'wholesale'
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


        <!-- =====================================================
             ITEMS
        ====================================================== -->

        <section class="items-result-card">

            <div class="section-label">
                SUBMITTED ITEMS
            </div>


            <div class="result-items">

                ${itemsHtml}

            </div>

        </section>


        <!-- =====================================================
             OFFER
        ====================================================== -->

        ${offerHtml}

    `;


    resultArea.hidden =
        false;


    /*
    |--------------------------------------------------------------------------
    | Seller Accept / Decline
    |--------------------------------------------------------------------------
    */

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
                            this.dataset.offerId,
                            this
                        );

                    }
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Open Counter Form
    |--------------------------------------------------------------------------
    */

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


                        if (!counterForm) {
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


                        if (amountInput) {

                            setTimeout(
                                function() {

                                    amountInput.focus();

                                },
                                80
                            );

                        }


                        counterForm.scrollIntoView({
                            behavior: 'smooth',
                            block: 'center'
                        });

                    }
                );

            }
        );


    /*
    |--------------------------------------------------------------------------
    | Cancel Counter
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Send Counter
    |--------------------------------------------------------------------------
    */

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


    /*
    |--------------------------------------------------------------------------
    | Scroll
    |--------------------------------------------------------------------------
    */

    resultArea.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });

}


/*
|--------------------------------------------------------------------------
| Track Submission
|--------------------------------------------------------------------------
*/

trackingForm.addEventListener(
    'submit',
    async function(event) {

        event.preventDefault();


        hideMessage();


        resultArea.hidden =
            true;


        trackButton.disabled =
            true;


        trackButton.innerHTML =
            'Checking... <span>...</span>';


        const formData =
            new FormData(
                trackingForm
            );


        try {

            const response =
                await fetch(
                    '/VNZ-TECH/backend/track_submission.php',
                    {
                        method: 'POST',
                        body: formData
                    }
                );


            const data =
                await response.json();


            if (
                !response.ok
                ||
                !data.success
            ) {

                throw new Error(
                    data.message
                    ||
                    'Unable to find submission.'
                );

            }


            renderResult(data);


        } catch (error) {

            showMessage(
                error.message,
                'error'
            );

        } finally {

            trackButton.disabled =
                false;


            trackButton.innerHTML =
                'Track Submission <span>→</span>';

        }

    }
);


/*
|--------------------------------------------------------------------------
| Seller Accept / Decline
|--------------------------------------------------------------------------
*/

let sellerOfferResponsePending = false;
let sellerCounterOfferPending = false;

async function respondToOffer(
    responseType,
    offerId,
    triggerButton
) {
    if (sellerOfferResponsePending) {
        return;
    }
    sellerOfferResponsePending = true;

    const referenceInput =
        document.getElementById(
            'reference'
        );


    const mobileInput =
        document.getElementById(
            'mobile'
        );


    const reference =
        referenceInput
            ? referenceInput.value.trim()
            : '';


    const mobile =
        mobileInput
            ? mobileInput.value.trim()
            : '';


    const offerAmount = Number(triggerButton?.dataset.offerAmount);
    const formattedOffer = Number.isFinite(offerAmount)
        ? money(offerAmount)
        : 'this offer';
    const confirmed = await window.VNZDialog.confirm({
        title: responseType === 'accept' ? 'Accept Offer?' : 'Decline Offer?',
        message: responseType === 'accept'
            ? `Are you sure you want to accept the offer of ${formattedOffer} for submission ${reference}?`
            : `Are you sure you want to decline the offer of ${formattedOffer} for submission ${reference}?`,
        confirmLabel: responseType === 'accept' ? 'Accept Offer' : 'Decline Offer',
        danger: responseType !== 'accept'
    });


    if (!confirmed) {
        sellerOfferResponsePending = false;
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

    body.append(
        'csrf_token',
        <?= json_encode($csrfToken) ?>
    );


    let responseSaved = false;
    try {

        const httpResponse =
            await fetch(
                '/VNZ-TECH/backend/respond_offer.php',
                {
                    method: 'POST',
                    body
                }
            );


        const data =
            await httpResponse.json();


        if (
            !httpResponse.ok
            ||
            !data.success
        ) {

            throw new Error(
                data.message
                ||
                'Unable to update offer.'
            );

        }

        responseSaved = true;

        showMessage(
            data.message,
            'success'
        );


        await refreshSubmission();
        sellerOfferResponsePending = false;

    } catch (error) {

        showMessage(
            responseSaved
                ? `Your response was saved, but the submission could not be refreshed: ${error.message}`
                : error.message,
            'error'
        );

        if (!responseSaved) {
            buttons.forEach(
                function(button) {
                    button.disabled = false;
                }
            );
            sellerOfferResponsePending = false;
        }

    }

}


/*
|--------------------------------------------------------------------------
| Send Seller Counter Offer
|--------------------------------------------------------------------------
*/

async function sendCounterOffer(
    offerId
) {
    if (sellerCounterOfferPending) {
        return;
    }

    const counterForm =
        resultArea.querySelector(
            '[data-counter-form]'
        );


    if (!counterForm) {

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


        if (amountInput) {
            amountInput.focus();
        }


        return;

    }


    const numericAmount =
        Number(counterAmount);


    if (numericAmount <= 0) {

        showMessage(
            'Counter-offer amount must be greater than ₱0.00.',
            'error'
        );


        if (amountInput) {
            amountInput.focus();
        }


        return;

    }


    if (numericAmount > 999999999) {

        showMessage(
            'Counter-offer amount is too large.',
            'error'
        );

        return;

    }


    sellerCounterOfferPending = true;
    const confirmed = await window.VNZDialog.confirm({
        title: 'Send Counter-Offer?',
        message: `Send your counter-offer of ${money(numericAmount)} for submission ${document.getElementById('reference')?.value.trim() || ''}?`,
        confirmLabel: 'Send Counter-Offer'
    });


    if (!confirmed) {
        sellerCounterOfferPending = false;
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


    if (button) {

        button.disabled =
            true;


        button.innerHTML =
            'SENDING...';

    }


    if (cancelButton) {

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
        document.getElementById(
            'reference'
        ).value.trim()
    );


    body.append(
        'mobile',
        document.getElementById(
            'mobile'
        ).value.trim()
    );


    body.append(
        'counter_amount',
        numericAmount.toFixed(2)
    );


    body.append(
        'feedback',
        feedback
    );

    body.append(
        'csrf_token',
        <?= json_encode($csrfToken) ?>
    );


    let counterOfferSaved = false;
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
            !response.ok
            ||
            !data.success
        ) {

            throw new Error(
                data.message
                ||
                'Unable to send counter-offer.'
            );

        }

        counterOfferSaved = true;

        showMessage(
            data.message,
            'success'
        );


        await refreshSubmission();
        sellerCounterOfferPending = false;

    } catch (error) {

        showMessage(
            counterOfferSaved
                ? `Your counter-offer was sent, but the submission could not be refreshed: ${error.message}`
                : error.message,
            'error'
        );


        if (button && !counterOfferSaved) {

            button.disabled =
                false;


            button.innerHTML =
                'SEND COUNTER-OFFER <span>→</span>';

        }


        if (cancelButton && !counterOfferSaved) {

            cancelButton.disabled =
                false;

        }
        if (!counterOfferSaved) {
            sellerCounterOfferPending = false;
        }

    }

}


/*
|--------------------------------------------------------------------------
| Refresh Submission
|--------------------------------------------------------------------------
*/

async function refreshSubmission() {

    const formData =
        new FormData(
            trackingForm
        );


    const response =
        await fetch(
            '/VNZ-TECH/backend/track_submission.php',
            {
                method: 'POST',
                body: formData
            }
        );


    const data =
        await response.json();


    if (
        !response.ok
        ||
        !data.success
    ) {

        throw new Error(
            data.message
            ||
            'Unable to refresh submission.'
        );

    }


    renderResult(data);

}


/*
|--------------------------------------------------------------------------
| Auto Track From URL
|--------------------------------------------------------------------------
*/

<?php if (
    $reference !== ''
    &&
    $mobile !== ''
): ?>

window.addEventListener(
    'DOMContentLoaded',
    function() {

        trackingForm.dispatchEvent(
            new Event(
                'submit',
                {
                    bubbles: true,
                    cancelable: true
                }
            )
        );

    }
);

<?php endif; ?>

</script>

</body>

</html>