<?php

require_once __DIR__ . '/backend/session.php';

$submissionResult = isset($_GET['submitted']) && $_GET['submitted'] === '1';

$referenceNumber = isset($_GET['ref'])
    ? trim($_GET['ref'])
    : '';

$errorMessage = isset($_GET['error'])
    ? trim($_GET['error'])
    : '';

/*
|--------------------------------------------------------------------------
| LOGGED-IN STATE
|--------------------------------------------------------------------------
|
| A logged-in seller can still land on this page after submitting
| via the PUBLIC form (that submission is intentionally treated as
| guest at the backend level — see backend/submit_submission.php).
|
| However, we should NOT show the "create an account" onboarding
| prompt to someone who already has an account. This flag only
| controls that UI decision; it has no effect on submission
| ownership, which is decided entirely server-side.
|
*/

$isLoggedInSeller =
    isset($_SESSION['user_logged_in']) &&
    $_SESSION['user_logged_in'] === true &&
    !empty($_SESSION['user_id']);

$showGuestOnboarding =
    $submissionResult && !$isLoggedInSeller;

/*
|--------------------------------------------------------------------------
| DIRECT-TO-FORM REQUEST
|--------------------------------------------------------------------------
|
| Set when the visitor clicked "Submit Another Item" on the success
| screen. Used to auto-open the submission form on page load instead
| of just landing on the marketing homepage.
|
*/

$openFormRequested =
    isset($_GET['open']) &&
    $_GET['open'] === 'form';

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
        Sell Your Broken Phone or Scrap | VNZ TECH
    </title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/seller_submission.css"
    >


    <style>
        /* =========================================================
           GUEST ACCOUNT ONBOARDING MODALS
        ========================================================== */

        .seller-modal[hidden] {
            display: none !important;
        }

        .seller-modal {
            position: fixed;
            inset: 0;
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: rgba(8, 11, 15, 0.78);
            backdrop-filter: blur(8px);
        }

        .seller-modal-card {
            width: min(100%, 520px);
            padding: 34px;
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 20px;
            background: #11161d;
            color: #ffffff;
            box-shadow: 0 24px 80px rgba(0, 0, 0, 0.42);
        }

        .seller-modal-kicker {
            display: block;
            margin-bottom: 10px;
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.14em;
            text-transform: uppercase;
            opacity: 0.62;
        }

        .seller-modal-card h2 {
            margin: 0 0 12px;
            font-size: clamp(28px, 4vw, 38px);
            line-height: 1.05;
        }

        .seller-modal-card p {
            margin: 0;
            color: rgba(255, 255, 255, 0.78);
            line-height: 1.65;
        }

        .seller-modal-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 26px;
        }

        .seller-modal-btn {
            flex: 1 1 180px;
            min-height: 48px;
            padding: 12px 18px;
            border: 1px solid rgba(255, 255, 255, 0.14);
            border-radius: 12px;
            font: inherit;
            font-weight: 800;
            cursor: pointer;
            transition:
                transform 0.18s ease,
                opacity 0.18s ease,
                background 0.18s ease;
        }

        .seller-modal-btn:hover {
            transform: translateY(-1px);
        }

        .seller-modal-btn-primary {
            background: #ffffff;
            color: #11161d;
        }

        .seller-modal-btn-secondary {
            background: transparent;
            color: #ffffff;
        }

        .seller-modal-btn-secondary:hover {
            background: rgba(255, 255, 255, 0.06);
        }

        .seller-modal-btn-danger {
            background: #2a1717;
            color: #ffffff;
            border-color: rgba(255, 120, 120, 0.18);
        }

        @media (max-width: 560px) {
            .seller-modal {
                padding: 16px;
            }

            .seller-modal-card {
                padding: 26px 20px;
                border-radius: 16px;
            }

            .seller-modal-actions {
                flex-direction: column;
            }

            .seller-modal-btn {
                width: 100%;
            }
        }
    </style>

</head>


<body>

<div class="seller-page">


<?php if ($submissionResult): ?>


    <!-- =========================================================
         SUCCESS
    ========================================================== -->

    <section class="success-screen">

        <div class="success-icon">
            ✓
        </div>

        <span class="success-kicker">
            SUBMISSION RECEIVED
        </span>

        <h1>
            Your item is now
            <span>under review.</span>
        </h1>

        <p class="success-description">
            Salamat sa pagtitiwala sa VNZ TECH.
            Na-receive na namin ang submission mo at
            rereviewhin namin ang item details at photos.
        </p>

        <div class="reference-box">

            <span>
                YOUR REFERENCE NUMBER
            </span>

            <strong>
                <?= htmlspecialchars(
                    $referenceNumber,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </strong>

            <small>
                Keep this reference number for tracking.
            </small>

        </div>

        <div class="success-actions">

            <a
                href="/VNZ-TECH/sell.php?open=form"
                class="secondary-btn"
            >
                Submit Another Item
            </a>

            <a
                href="/VNZ-TECH/sell.php"
                class="primary-btn"
            >
                Back to VNZ TECH
            </a>

        </div>

    </section>



    <?php if ($showGuestOnboarding): ?>


    <!-- =========================================================
         GUEST ACCOUNT ONBOARDING

         Only rendered for a true guest submission. A logged-in
         seller who submitted via this public form already has
         an account, so this prompt is skipped for them.
    ========================================================== -->

    <div
        class="seller-modal"
        id="guestAccountPromptModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="guestAccountPromptTitle"
        aria-describedby="guestAccountPromptDescription"
        hidden
    >
        <div class="seller-modal-card">
            <span class="seller-modal-kicker">
                OPTIONAL — CREATE YOUR ACCOUNT
            </span>

            <h2 id="guestAccountPromptTitle">
                Mas convenient kapag may account.
            </h2>

            <p id="guestAccountPromptDescription">
                Gumawa ng VNZ TECH account para makita mo ang history,
                submissions, at transactions mo sa isang lugar.
            </p>

            <div class="seller-modal-actions">
                <button
                    type="button"
                    class="seller-modal-btn seller-modal-btn-primary"
                    id="guestCreateAccountBtn"
                >
                    Create an Account
                </button>

                <button
                    type="button"
                    class="seller-modal-btn seller-modal-btn-secondary"
                    id="guestCancelBtn"
                >
                    Cancel
                </button>
            </div>
        </div>
    </div>


    <div
        class="seller-modal"
        id="guestCancelConfirmModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="guestCancelConfirmTitle"
        aria-describedby="guestCancelConfirmDescription"
        hidden
    >
        <div class="seller-modal-card">
            <span class="seller-modal-kicker">
                CONTINUE AS GUEST?
            </span>

            <h2 id="guestCancelConfirmTitle">
                Are you sure?
            </h2>

            <p id="guestCancelConfirmDescription">
                Hindi mo kailangang gumawa ng account ngayon.
                Maaari kang magpatuloy bilang guest gamit ang
                reference number mo.
            </p>

            <div class="seller-modal-actions">
                <button
                    type="button"
                    class="seller-modal-btn seller-modal-btn-danger"
                    id="guestConfirmCancelBtn"
                >
                    Yes
                </button>

                <button
                    type="button"
                    class="seller-modal-btn seller-modal-btn-secondary"
                    id="guestBackToPromptBtn"
                >
                    No
                </button>
            </div>
        </div>
    </div>


    <?php endif; ?>


<?php else: ?>


    <!-- =========================================================
         HEADER
    ========================================================== -->

    <header class="site-header">

        <a
            href="/VNZ-TECH/index.php"
            class="brand-mark"
        >
            VNZ <span>TECH</span>
        </a>

        <div class="header-actions">

            <a
                href="/VNZ-TECH/account/register.php"
                class="header-register-btn"
            >
                CREATE ACCOUNT
            </a>

            <a
                href="/VNZ-TECH/account/login.php"
                class="header-login-btn"
            >
                LOGIN
            </a>

            <a
                href="/VNZ-TECH/seller/tracking.php"
                class="header-track-btn"
            >
                TRACK SUBMISSION
            </a>

        </div>

    </header>


    <?php if ($errorMessage): ?>

        <div class="error-message">

            <strong>
                Submission Error
            </strong>

            <span>
                <?= htmlspecialchars(
                    $errorMessage,
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>
            </span>

        </div>

    <?php endif; ?>


    <!-- =========================================================
         SECTION 1 — HERO
    ========================================================== -->

    <section class="marketing-section hero-section">

        <div class="hero-grid">

            <div class="hero-copy">

                <span class="eyebrow">
                    VNZ TECH SELLER PORTAL
                </span>

                <h1>
                    YOUR BROKEN PHONE
                    <span>STILL HAS VALUE.</span>
                </h1>

                <p class="hero-description">
                    Dead phone. No display. Bootloop.
                    Water damage. Sirang board.
                    Kahit hindi na gumagana ang phone,
                    may parts at components pa rin itong
                    maaaring magkaroon ng value.
                </p>


                <div class="hero-actions">

                    <button
                        type="button"
                        class="hero-btn hero-btn-primary"
                        data-open-form="phone"
                    >
                        <span>
                            SELL A PHONE
                        </span>

                        <b>→</b>
                    </button>


                    <button
                        type="button"
                        class="hero-btn hero-btn-secondary"
                        data-open-form="scrap"
                    >
                        SELL SCRAP
                    </button>

                    

                </div>


                <div class="hero-meta">

                    <span>
                        BROKEN PHONES
                    </span>

                    <span>
                        SCRAP BOARDS
                    </span>

                    <span>
                        PER PIECE / WHOLESALE
                    </span>

                </div>

            </div>


            <div class="hero-statement">

                <span>
                    WE BUY.
                </span>

                <span>
                    WE RECOVER.
                </span>

                <span>
                    WE REVALUE.
                </span>

            </div>

        </div>

    </section>


    <!-- =========================================================
         SECTION 2 — WHAT IS SCRAP
    ========================================================== -->

    <section class="marketing-section education-section">

        <div class="section-intro">

            <span class="section-kicker">
                02 / UNDERSTANDING SCRAP
            </span>

            <h2>
                Hindi porket sira,
                <span>wala nang value.</span>
            </h2>

            <p>
                Sa electronics, hindi lahat ng value ay nasa
                working condition ng buong device.
                May components, boards, parts, at materials
                na maaaring i-recover at i-sort para sa
                downstream processing.
            </p>

        </div>


        <div class="education-grid">

            <article class="education-card featured-card">

                <div class="card-index">
                    01
                </div>

                <span class="card-label">
                    WHOLE PHONE
                </span>

                <h3>
                    Sirang phone
                    <span>doesn't always mean worthless.</span>
                </h3>

                <p>
                    Ang isang phone na hindi na umaandar ay
                    maaari pa ring magkaroon ng useful parts.
                    Depende sa kondisyon, maaaring may
                    recoverable board, components, housing,
                    camera modules, display assemblies,
                    connectors, o ibang parts.
                </p>

                <div class="card-flow">

                    <span>
                        BROKEN PHONE
                    </span>

                    <b>→</b>

                    <span>
                        INSPECTION
                    </span>

                    <b>→</b>

                    <span>
                        SORTING
                    </span>

                    <b>→</b>

                    <span>
                        RECOVERY
                    </span>

                </div>

            </article>


            <article class="education-card">

                <div class="card-index">
                    02
                </div>

                <span class="card-label">
                    SCRAP BOARD
                </span>

                <h3>
                    What exactly
                    <span>is a scrap board?</span>
                </h3>

                <p>
                    Ang scrap board ay electronic board,
                    gaya ng motherboard o logic board,
                    na maaaring defective, damaged, dead,
                    removed from a phone, o hindi na intended
                    para sa normal repair use.
                </p>

                <p>
                    Kahit sira ang board, may recoverable
                    components at material value pa rin
                    depende sa board type, marking, kondisyon,
                    at quantity.
                </p>

            </article>


            <article class="education-card">

                <div class="card-index">
                    03
                </div>

                <span class="card-label">
                    WHY SELL?
                </span>

                <h3>
                    From dead stock
                    <span>to recoverable value.</span>
                </h3>

                <p>
                    Sa halip na nakatambak lang ang lumang,
                    sirang, o defective phones at boards,
                    puwede itong ipasuri at ipasok sa proper
                    buying and recovery process.
                </p>

                <div class="mini-points">

                    <span>
                        ✓ Clear out old stock
                    </span>

                    <span>
                        ✓ Turn damaged inventory into value
                    </span>

                    <span>
                        ✓ Avoid simply throwing electronics away
                    </span>

                </div>

            </article>

        </div>


        <div class="process-banner">

            <div>

                <span>
                    WHAT HAPPENS AFTER YOU SELL?
                </span>

                <strong>
                    Inspect. Verify. Sort. Revalue.
                </strong>

            </div>

            <p>
                Hindi automatic na mataas ang presyo dahil
                scrap siya. Ang value ay nakadepende sa actual
                item, condition, board identification,
                quantity, at inspection.
            </p>

        </div>

    </section>


    <!-- =========================================================
         SECTION 3 — HOW IT WORKS
    ========================================================== -->

    <section class="marketing-section process-section">

        <div class="section-intro">

            <span class="section-kicker">
                03 / HOW VNZ TECH WORKS
            </span>

            <h2>
                Simple for you.
                <span>Systematic for us.</span>
            </h2>

            <p>
                Submit the details. Let VNZ TECH review the item.
                Kapag may verified valuation na, saka tayo
                magbibigay ng offer.
            </p>

        </div>


        <div class="steps-grid">

            <article class="step-card">

                <span class="step-number">
                    01
                </span>

                <h3>
                    You Submit
                </h3>

                <p>
                    Tell us what you're selling and upload
                    clear photos of the item.
                </p>

            </article>


            <article class="step-card">

                <span class="step-number">
                    02
                </span>

                <h3>
                    We Review
                </h3>

                <p>
                    Susuriin namin ang item type, condition,
                    board details, quantity, at photos.
                </p>

            </article>


            <article class="step-card">

                <span class="step-number">
                    03
                </span>

                <h3>
                    We Make an Offer
                </h3>

                <p>
                    Based sa review at verification,
                    gagawa ang VNZ TECH ng buying offer.
                </p>

            </article>


            <article class="step-card">

                <span class="step-number">
                    04
                </span>

                <h3>
                    You Decide
                </h3>

                <p>
                    Review the offer. Accept or decline.
                    Walang automatic acceptance.
                </p>

            </article>

        </div>


        <div class="final-cta">

            <span>
                READY TO TURN YOUR OLD STOCK INTO VALUE?
            </span>

            <h3>
                Start your submission.
            </h3>

            <button
                type="button"
                class="hero-btn hero-btn-primary"
                data-open-form="phone"
            >
                <span>
                    START YOUR SUBMISSION
                </span>

                <b>→</b>
            </button>

        </div>

    </section>


    <!-- =========================================================
         SELLER FORM — HIDDEN UNTIL CTA
    ========================================================== -->

    <section
        class="submission-form-section"
        id="submissionFormSection"
        hidden
    >

        <div class="form-section-header">

            <div>

                <span class="section-kicker">
                    VNZ TECH SELLER FORM
                </span>

                <h2>
                    Tell us what you're selling.
                </h2>

                <p>
                    Complete the information below so we can
                    properly review your submission.
                </p>

            </div>

            <button
                type="button"
                class="close-form-btn"
                id="closeFormBtn"
            >
                ×
            </button>

        </div>


        <!-- =====================================================
             FORM
        ====================================================== -->

        <form
            id="sellerSubmissionForm"
            action="/VNZ-TECH/backend/submit_submission.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <!--
                Marks this as a PUBLIC / GUEST submission regardless
                of whether the current browser also has an active
                seller session. The backend only ever treats a
                submission as belonging to a logged-in account when
                this value is "account" AND a valid session exists.
                See backend/submit_submission.php.
            -->
            <input
                type="hidden"
                name="submission_source"
                value="guest"
            >


            <!-- =================================================
                 SELLER
            ================================================== -->

            <div class="form-block">

                <div class="form-block-title">

                    <span>
                        01
                    </span>

                    <div>

                        <strong>
                            Seller Information
                        </strong>

                        <small>
                            Tell us who is submitting the item.
                        </small>

                    </div>

                </div>


                <div class="form-grid">

                    <div class="form-group">

                        <label for="seller_name">
                            Full Name <b>*</b>
                        </label>

                        <input
                            type="text"
                            id="seller_name"
                            name="seller_name"
                            maxlength="150"
                            placeholder="Juan Dela Cruz"
                            required
                        >

                    </div>


                    <div class="form-group">

                        <label for="seller_mobile">
                            Mobile Number <b>*</b>
                        </label>

                        <input
                            type="tel"
                            id="seller_mobile"
                            name="seller_mobile"
                            maxlength="30"
                            placeholder="09XXXXXXXXX"
                            required
                        >

                    </div>


                    <div class="form-group full-width">

                        <label for="seller_address">
                            Full Address / Location <b>*</b>
                        </label>

                        <textarea
                            id="seller_address"
                            name="seller_address"
                            rows="3"
                            maxlength="500"
                            placeholder="Barangay, City / Municipality, Province"
                            required
                        ></textarea>

                    </div>

                </div>


                <div class="form-group selling-method">

                    <label>
                        Selling Method <b>*</b>
                    </label>

                    <div class="choice-grid">

                        <label class="choice-card">

                            <input
                                type="radio"
                                name="selling_method"
                                value="per_piece"
                                checked
                            >

                            <span>
                                <strong>
                                    Per Piece
                                </strong>

                                <small>
                                    Individual units
                                </small>
                            </span>

                        </label>


                        <label class="choice-card">

                            <input
                                type="radio"
                                name="selling_method"
                                value="wholesale"
                            >

                            <span>
                                <strong>
                                    Wholesale
                                </strong>

                                <small>
                                    Multiple items / bulk
                                </small>
                            </span>

                        </label>

                    </div>

                </div>

            </div>


            <!-- =================================================
                 ITEMS
            ================================================== -->

            <div class="form-block">

                <div class="form-block-title">

                    <span>
                        02
                    </span>

                    <div>

                        <strong>
                            Item Details
                        </strong>

                        <small>
                            Add every phone or board you want us to review.
                        </small>

                    </div>

                </div>


                <div id="itemsContainer"></div>


                <button
                    type="button"
                    id="addItemBtn"
                    class="add-item-btn"
                >
                    + Add Another Item
                </button>

            </div>


            <!-- =================================================
                 SELLER NOTES
            ================================================== -->

            <div class="form-block">

                <div class="form-block-title">

                    <span>
                        03
                    </span>

                    <div>

                        <strong>
                            Additional Information
                        </strong>

                        <small>
                            Optional notes about your items.
                        </small>

                    </div>

                </div>


                <div class="form-group">

                    <label for="seller_notes">
                        Seller Notes
                    </label>

                    <textarea
                        id="seller_notes"
                        name="seller_notes"
                        rows="5"
                        maxlength="1500"
                        placeholder="Repair history, missing parts, source of the items, etc."
                    ></textarea>

                </div>

            </div>


            <div class="form-disclaimer">

                <strong>
                    VNZ TECH REVIEW NOTICE
                </strong>

                <span>
                    Seller-declared condition and information are subject
                    to inspection and verification. Submission does not
                    guarantee a specific buying price.
                </span>

            </div>


            <div class="submit-area">

                <button
                    type="submit"
                    class="submit-btn"
                    id="submitBtn"
                >
                    Submit for Review
                    <span>→</span>
                </button>

                <p>
                    By submitting this form, you confirm that
                    the information provided is accurate to the
                    best of your knowledge.
                </p>

            </div>


        </form>

    </section>


    <!-- =========================================================
         FOOTER
    ========================================================== -->

    <footer class="site-footer">

        <div class="footer-main">

            <div>

                <a
                    href="/VNZ-TECH/index.php"
                    class="footer-brand"
                >
                    VNZ TECH
                </a>

                <p>
                    We buy. We recover. We revalue.
                </p>

            </div>


            <div class="footer-message">

                <span>
                    BROKEN PHONE?
                </span>

                <strong>
                    BAKA PERA PA ’YAN.
                </strong>

            </div>

        </div>


        <div class="footer-bottom">

            <span>
                © <?= date('Y') ?> VNZ TECH
            </span>

            <span>
                Broken Phones · Scrap Boards · Recovery
            </span>

        </div>

    </footer>


<?php endif; ?>


</div>



<script>
    /*
     * Guest post-submission onboarding.
     * This runs only when the public form has reached the success
     * state AND the current visitor is not already a logged-in
     * seller (see $showGuestOnboarding in PHP above).
     */
    const guestSubmissionSucceeded =
        <?= $showGuestOnboarding ? 'true' : 'false' ?>;

    if (guestSubmissionSucceeded) {
        const promptModal =
            document.getElementById('guestAccountPromptModal');

        const confirmModal =
            document.getElementById('guestCancelConfirmModal');

        const createAccountBtn =
            document.getElementById('guestCreateAccountBtn');

        const cancelBtn =
            document.getElementById('guestCancelBtn');

        const confirmCancelBtn =
            document.getElementById('guestConfirmCancelBtn');

        const backToPromptBtn =
            document.getElementById('guestBackToPromptBtn');

        const registrationUrl =
            '/VNZ-TECH/account/register.php';

        function showSellerModal(modal) {
            if (!modal) {
                return;
            }

            modal.hidden = false;
            document.body.style.overflow = 'hidden';
        }

        function hideSellerModal(modal) {
            if (!modal) {
                return;
            }

            modal.hidden = true;

            const anySellerModalOpen =
                document.querySelector(
                    '.seller-modal:not([hidden])'
                );

            if (!anySellerModalOpen) {
                document.body.style.overflow = '';
            }
        }

        if (createAccountBtn) {
            createAccountBtn.addEventListener(
                'click',
                function () {
                    window.location.href = registrationUrl;
                }
            );
        }

        if (cancelBtn) {
            cancelBtn.addEventListener(
                'click',
                function () {
                    hideSellerModal(promptModal);
                    showSellerModal(confirmModal);
                }
            );
        }

        if (confirmCancelBtn) {
            confirmCancelBtn.addEventListener(
                'click',
                function () {
                    hideSellerModal(confirmModal);
                }
            );
        }

        if (backToPromptBtn) {
            backToPromptBtn.addEventListener(
                'click',
                function () {
                    hideSellerModal(confirmModal);
                    showSellerModal(promptModal);
                }
            );
        }

        /*
         * Small delay keeps the normal success screen visible first,
         * then presents the onboarding recommendation.
         */
        window.setTimeout(
            function () {
                showSellerModal(promptModal);
            },
            450
        );
    }
</script>

<script>

let itemIndex = 0;

const itemsContainer =
    document.getElementById('itemsContainer');

const addItemBtn =
    document.getElementById('addItemBtn');

const form =
    document.getElementById('sellerSubmissionForm');

const formSection =
    document.getElementById('submissionFormSection');

const closeFormBtn =
    document.getElementById('closeFormBtn');


const issueList = [
    'No Power',
    'No Display',
    'Bootloop',
    'LCD Damaged',
    'Touch Problem',
    'Battery Problem',
    'Charging Problem',
    'Camera Problem',
    'Speaker Problem',
    'Microphone Problem',
    'Water Damage',
    'Board Problem',
    'Multiple Issues',
    'Other'
];


const damageLevels = [
    ['1', 'Minor'],
    ['2', 'Moderate'],
    ['3', 'Major'],
    ['4', 'Dead / Critical'],
    ['5', 'For Scrap']
];


function openSubmissionForm(type = 'phone') {

    if (!formSection) {
        return;
    }

    formSection.hidden = false;

    formSection.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
    });


    setTimeout(() => {

        const cards =
            itemsContainer.querySelectorAll(
                '.item-form-card'
            );

        const firstCard = cards[0];

        if (!firstCard) {
            return;
        }


        const typeValue =
            type === 'scrap'
                ? 'Scrap Board'
                : 'Whole Phone';


        const radio =
            firstCard.querySelector(
                `input[value="${typeValue}"]`
            );


        if (radio) {

            radio.checked = true;

            radio.dispatchEvent(
                new Event('change')
            );

        }

    }, 100);

}


document
    .querySelectorAll('[data-open-form]')
    .forEach(button => {

        button.addEventListener(
            'click',
            () => {

                openSubmissionForm(
                    button.dataset.openForm
                );

            }
        );

    });


if (closeFormBtn) {

    closeFormBtn.addEventListener(
        'click',
        () => {

            formSection.hidden = true;

            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });

        }
    );

}


function createDamageOptions() {

    let options =
        '<option value="">Select condition</option>';


    damageLevels.forEach(level => {

        options += `
            <option value="${level[0]}">
                ${level[0]} — ${level[1]}
            </option>
        `;

    });


    return options;

}


function createIssueOptions() {

    let options =
        '<option value="">Select issue</option>';


    issueList.forEach(issue => {

        options += `
            <option value="${issue}">
                ${issue}
            </option>
        `;

    });


    return options;

}


function addItem() {

    const index = itemIndex++;


    const article =
        document.createElement('article');


    article.className =
        'item-form-card';


    article.dataset.index =
        index;


    article.innerHTML = `

        <div class="item-form-header">

            <div>

                <span>
                    ITEM ${String(index + 1).padStart(2, '0')}
                </span>

                <h3>
                    Item Information
                </h3>

            </div>


            <button
                type="button"
                class="remove-item-btn"
                data-remove-item
            >
                Remove
            </button>

        </div>


        <div class="item-type-selector">

            <label class="item-type-choice">

                <input
                    type="radio"
                    name="items[${index}][item_type]"
                    value="Whole Phone"
                    checked
                >

                <span>

                    <strong>
                        Whole Phone
                    </strong>

                    <small>
                        Broken / defective handset
                    </small>

                </span>

            </label>


            <label class="item-type-choice">

                <input
                    type="radio"
                    name="items[${index}][item_type]"
                    value="Scrap Board"
                >

                <span>

                    <strong>
                        Scrap Board
                    </strong>

                    <small>
                        Loose / defective board
                    </small>

                </span>

            </label>

        </div>


        <!-- PHONE FIELDS -->

        <div
            class="phone-fields"
            data-phone-fields
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Brand
                    </label>

                    <input
                        type="text"
                        name="items[${index}][brand]"
                        maxlength="100"
                        placeholder="Samsung / Apple / Xiaomi..."
                    >

                </div>


                <div class="form-group">

                    <label>
                        Model
                        <small>Optional</small>
                    </label>

                    <input
                        type="text"
                        name="items[${index}][model]"
                        maxlength="150"
                        placeholder="A52 / iPhone 11 / Redmi Note..."
                    >

                </div>


                <div class="form-group">

                    <label>
                        Storage
                        <small>Optional</small>
                    </label>

                    <select
                        name="items[${index}][storage]"
                    >

                        <option value="">
                            Unknown / N/A
                        </option>

                        <option value="16GB">16GB</option>
                        <option value="32GB">32GB</option>
                        <option value="64GB">64GB</option>
                        <option value="128GB">128GB</option>
                        <option value="256GB">256GB</option>
                        <option value="512GB">512GB</option>
                        <option value="1TB">1TB</option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Quantity <b>*</b>
                    </label>

                    <input
                        type="number"
                        name="items[${index}][quantity]"
                        min="1"
                        max="100000"
                        value="1"
                        required
                    >

                </div>


                <div class="form-group full-width">

                    <label>
                        Main Issue <b>*</b>
                    </label>

                    <select
                        name="items[${index}][issue]"
                        data-issue
                        required
                    >

                        ${createIssueOptions()}

                    </select>

                </div>


                <div class="form-group full-width">

                    <label>
                        Issue Details
                        <small>Optional</small>
                    </label>

                    <textarea
                        name="items[${index}][issue_details]"
                        rows="3"
                        maxlength="1000"
                        placeholder="Describe the issue or what happened to the phone..."
                    ></textarea>

                </div>


                <div class="form-group">

                    <label>
                        Damage / Condition <b>*</b>
                    </label>

                    <select
                        name="items[${index}][damage_level]"
                        data-phone-damage
                        required
                    >

                        ${createDamageOptions()}

                    </select>

                </div>

            </div>

        </div>


        <!-- BOARD FIELDS -->

        <div
            class="board-fields"
            data-board-fields
            hidden
        >

            <div class="form-grid">

                <div class="form-group">

                    <label>
                        Board Code / Marking <b>*</b>
                    </label>

                    <input
                        type="text"
                        name="items[${index}][board_code]"
                        maxlength="150"
                        placeholder="Board code / marking"
                        data-board-code
                    >

                </div>


                <div class="form-group">

                    <label>
                        Brand
                        <small>Optional</small>
                    </label>

                    <input
                        type="text"
                        name="items[${index}][brand]"
                        maxlength="100"
                        placeholder="Apple / Samsung..."
                    >

                </div>


                <div class="form-group">

                    <label>
                        Board / Model
                        <small>Optional</small>
                    </label>

                    <input
                        type="text"
                        name="items[${index}][model]"
                        maxlength="150"
                        placeholder="Board model if known"
                    >

                </div>


                <div class="form-group">

                    <label>
                        Storage
                        <small>If applicable</small>
                    </label>

                    <select
                        name="items[${index}][storage]"
                    >

                        <option value="">
                            N/A / Unknown
                        </option>

                        <option value="16GB">16GB</option>
                        <option value="32GB">32GB</option>
                        <option value="64GB">64GB</option>
                        <option value="128GB">128GB</option>
                        <option value="256GB">256GB</option>
                        <option value="512GB">512GB</option>
                        <option value="1TB">1TB</option>

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Condition <b>*</b>
                    </label>

                    <select
                        name="items[${index}][damage_level]"
                        data-board-damage
                    >

                        ${createDamageOptions()}

                    </select>

                </div>


                <div class="form-group">

                    <label>
                        Quantity <b>*</b>
                    </label>

                <input
                    type="number"
                    name="items[${index}][quantity]"
                    min="1"
                    max="100000"
                    value="1"
                    data-board-quantity
                >

                </div>

            </div>

        </div>


        <div class="form-group asking-price-group">

            <label>
                Seller Asking Price <b>*</b>
            </label>

            <div class="price-input">

                <span>
                    ₱
                </span>

                <input
                    type="number"
                    name="items[${index}][seller_asking_price]"
                    min="0"
                    max="999999999"
                    step="0.01"
                    placeholder="0.00"
                    required
                >

            </div>

            <small>
                Your requested price. Final VNZ TECH offer
                is subject to inspection and verification.
            </small>

        </div>


        <div class="form-group">

            <label>
                Additional Information
                <small>Optional</small>
            </label>

            <textarea
                name="items[${index}][additional_information]"
                rows="3"
                maxlength="1500"
                placeholder="Missing parts, repair history, accessories, etc."
            ></textarea>

        </div>


        <div class="photo-upload-block">

            <div class="photo-heading">

                <strong>
                    Photos <b>*</b>
                </strong>

                <small>
                    Clear photos help us evaluate your item.
                </small>

            </div>


            <label class="upload-box">

                <input
                    type="file"
                    name="photos[${index}][]"
                    accept="image/jpeg,image/png,image/webp"
                    multiple
                    required
                    data-photo-input
                >

                <span>
                    +
                </span>

                <strong>
                    Add Photos
                </strong>

                <small>
                    JPG, PNG, WEBP · Max 10 photos · 5MB each
                </small>

            </label>


            <div
                class="photo-preview-grid"
                data-photo-preview
            ></div>

        </div>

    `;


    itemsContainer.appendChild(article);

    setupItem(article);

    updateRemoveButtons();

}


function setupItem(article) {

    const typeInputs =
        article.querySelectorAll(
            'input[name*="[item_type]"]'
        );


    const phoneFields =
        article.querySelector(
            '[data-phone-fields]'
        );


    const boardFields =
        article.querySelector(
            '[data-board-fields]'
        );


    const phoneDamage =
        article.querySelector(
            '[data-phone-damage]'
        );


    const boardDamage =
        article.querySelector(
            '[data-board-damage]'
        );


    const boardCode =
        article.querySelector(
            '[data-board-code]'
        );


    const issue =
        article.querySelector(
            '[data-issue]'
        );


    const phoneInputs =
        phoneFields.querySelectorAll(
            'input, select, textarea'
        );


    const boardInputs =
        boardFields.querySelectorAll(
            'input, select, textarea'
        );


    function updateType() {

        const selected =
            article.querySelector(
                'input[name*="[item_type]"]:checked'
            );


        if (!selected) {
            return;
        }


        const isPhone =
            selected.value === 'Whole Phone';


        /*
         * Show the correct section.
         */
        phoneFields.hidden =
            !isPhone;

        boardFields.hidden =
            isPhone;


        /*
         * CRITICAL:
         *
         * Hidden fields must also be disabled.
         * Otherwise the browser still submits them.
         */
        phoneInputs.forEach(
            input => {

                input.disabled =
                    !isPhone;

            }
        );


        boardInputs.forEach(
            input => {

                input.disabled =
                    isPhone;

            }
        );


        /*
         * Phone validation.
         */
        if (phoneDamage) {

            phoneDamage.required =
                isPhone;

        }


        if (issue) {

            issue.required =
                isPhone;

            if (!isPhone) {
                issue.value = '';
            }

        }


        /*
         * Scrap validation.
         */
        if (boardDamage) {

            boardDamage.required =
                !isPhone;

        }


        if (boardCode) {

            boardCode.required =
                !isPhone;

        }

    }


    typeInputs.forEach(
        input => {

            input.addEventListener(
                'change',
                updateType
            );

        }
    );


    /*
     * Photo preview.
     */
    const photoInput =
        article.querySelector(
            '[data-photo-input]'
        );


    const photoPreview =
        article.querySelector(
            '[data-photo-preview]'
        );


    if (
        photoInput &&
        photoPreview
    ) {

        photoInput.addEventListener(
            'change',
            function () {

                photoPreview.innerHTML = '';


                const files =
                    Array.from(
                        this.files
                    );


                if (files.length > 10) {

                    alert(
                        'You can upload a maximum of 10 photos per item.'
                    );

                }


                files
                    .slice(0, 10)
                    .forEach(
                        (
                            file,
                            fileIndex
                        ) => {

                            if (
                                !file.type.startsWith(
                                    'image/'
                                )
                            ) {

                                return;

                            }


                            const reader =
                                new FileReader();


                            reader.onload =
                                function (
                                    event
                                ) {

                                    const preview =
                                        document.createElement(
                                            'div'
                                        );


                                    preview.className =
                                        'photo-preview';


                                    preview.innerHTML = `

                                        <img
                                            src="${event.target.result}"
                                            alt="Selected photo"
                                        >

                                        <span>
                                            ${fileIndex + 1}
                                        </span>

                                    `;


                                    photoPreview.appendChild(
                                        preview
                                    );

                                };


                            reader.readAsDataURL(
                                file
                            );

                        }
                    );

            }
        );

    }


    /*
     * Set correct initial state.
     */
    updateType();

}


function updateRemoveButtons() {

    const cards =
        itemsContainer.querySelectorAll(
            '.item-form-card'
        );


    cards.forEach(card => {

        const button =
            card.querySelector(
                '[data-remove-item]'
            );


        button.style.display =
            cards.length === 1
                ? 'none'
                : 'block';

    });

}


if (itemsContainer) {

    itemsContainer.addEventListener(
        'click',
        function(event) {

        const button =
            event.target.closest(
                '[data-remove-item]'
            );


        if (!button) {
            return;
        }


        const card =
            button.closest(
                '.item-form-card'
            );


        if (card) {

            card.remove();

            updateRemoveButtons();

        }

        }
    );


}


if (addItemBtn && form && itemsContainer) {

    addItemBtn.addEventListener(
        'click',
        addItem
    );


    form.addEventListener(
        'submit',
        function(event) {

        const cards =
            itemsContainer.querySelectorAll(
                '.item-form-card'
            );


        if (!cards.length) {

            event.preventDefault();

            alert(
                'Please add at least one item.'
            );

            return;

        }


        const submitBtn =
            document.getElementById(
                'submitBtn'
            );


        submitBtn.disabled = true;

        submitBtn.innerHTML =
            'Submitting... <span>...</span>';

        }
    );


    addItem();

}


/*
|--------------------------------------------------------------------------
| AUTO-OPEN FORM
|--------------------------------------------------------------------------
|
| Triggered when arriving here via "Submit Another Item" on the
| success screen (?open=form). Opens the form directly instead of
| leaving the visitor on the marketing homepage.
|
*/

const shouldAutoOpenForm =
    <?= $openFormRequested ? 'true' : 'false' ?>;

if (shouldAutoOpenForm) {

    openSubmissionForm('phone');

    if (window.history && window.history.replaceState) {

        window.history.replaceState(
            {},
            document.title,
            '/VNZ-TECH/sell.php'
        );

    }

}

</script>

</body>

</html>