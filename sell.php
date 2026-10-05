<?php

require_once __DIR__ . '/backend/database.php';
require_once __DIR__ . '/backend/session.php';
require_once __DIR__ . '/backend/csrf.php';

$tradeCsrfToken = generateCsrfToken();

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

    <?php include __DIR__ . '/includes/loader-head.php'; ?>

    <title>
        Sell Your Broken Phone or Scrap | VNZ TECH
    </title>

    <link
        rel="stylesheet"
        href="/VNZ-TECH/assets/css/sell.css"
    >



</head>


<body>

<?php include __DIR__ . '/includes/loader-body.php'; ?>

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

        <div class="site-header-inner">

            <a
                href="/VNZ-TECH/index.php"
                class="brand-mark"
                aria-label="VNZ TECH home"
            >
                <img
                    src="/VNZ-TECH/assets/images/vnz.png"
                    alt="VNZ TECH logo"
                    class="brand-logo"
                >
                <span class="brand-name">VNZ TECH</span>
            </a>

            <div class="nav-menu" id="siteMenu">

                <nav class="nav-links" aria-label="Primary">
                    <a class="nav-link" href="#hero" data-section="hero">Home</a>
                    <a class="nav-link" href="#scrap" data-section="scrap">Why Sell</a>
                    <a class="nav-link" href="#process" data-section="process">How It Works</a>
                    <a class="nav-link" href="#trade-items" data-section="trade-items">Trade</a>
                    <button type="button" class="nav-link" id="openRatesBtnNav">Buying Rates</button>
                    <button type="button" class="nav-link" id="openTrackModalBtn">Track Submission</button>
                </nav>

                <div class="nav-actions">
                    <a href="/VNZ-TECH/account/login.php" class="nav-btn nav-btn-ghost" data-loader>Login</a>
                    <a href="/VNZ-TECH/account/register.php" class="nav-btn nav-btn-primary" data-loader>Create Account</a>
                </div>

            </div>

            <button
                type="button"
                class="nav-toggle"
                id="navToggle"
                aria-label="Toggle navigation menu"
                aria-expanded="false"
                aria-controls="siteMenu"
            >
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
                <span class="nav-toggle-bar"></span>
            </button>

        </div>

    </header>

    <nav
        class="section-dot-nav"
        aria-label="Section navigation"
    >
        <a
            class="dot-link is-active"
            href="#hero"
            data-section="hero"
            data-label="Home"
            aria-label="Go to Home section"
        ></a>

        <a
            class="dot-link"
            href="#scrap"
            data-section="scrap"
            data-label="Why Sell"
            aria-label="Go to Why Sell section"
        ></a>

        <a
            class="dot-link"
            href="#process"
            data-section="process"
            data-label="How It Works"
            aria-label="Go to How It Works section"
        ></a>

        <a
            class="dot-link"
            href="#trade-items"
            data-section="trade-items"
            data-label="Trade Items"
            aria-label="Go to Trade Items section"
        ></a>
    </nav>


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

    <section class="marketing-section hero-section" id="hero">

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
                Ang mga sirang phone, kahit hindi na umaandar, 
                ay maaari pa ring bilhin bilang scrap. Tumatanggap 
                kami ng iba't ibang uri ng defective phones, 
                kabilang ang mga may cracked screens, dead units, 
                water damage, at iba pang hardware issues.
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

    <section class="marketing-section education-section" id="scrap">

        <div class="section-intro">

            <span class="section-kicker">
                02 / UNDERSTANDING SCRAP
            </span>

            <h2>
                Hindi porket sira,
                <span>wala nang value.</span>
            </h2>

            <p>
            Sa electronics, hindi lahat ng value ay nakadepende 
            sa working condition ng isang device. 
            Ang mga defective phones at scrap boards ay 
            maaari pa ring magkaroon ng value depende sa 
            kanilang type, condition, at quantity.
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
                        BUYING
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
                Ang scrap board ay mga electronic boards gaya ng phone
                motherboards at logic boards na defective, 
                damaged, dead, o tinanggal na mula sa mga lumang devices.
                </p>

                <p>
                Bumibili kami ng iba't ibang klase ng scrap boards, 
                depende sa board type, condition, identification, 
                at quantity. Kahit hindi na gumagana,
                maaari pa rin itong magkaroon ng scrap value.
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
                    Turn your old electronics
                    <span>into value.</span>
                </h3>

                <p>
                Sa halip na nakatambak lang ang mga defective 
                phones at scrap boards, maaari mo itong ibenta sa VNZ TECH.
                </p>

                <div class="mini-points">

                    <span>
                        ✓ Clear out old and unwanted electronics.
                    </span>

                    <span>
                        ✓ Get value from broken and defective phones.
                    </span>

                    <span>
                        ✓ Sell unused and scrap motherboards.
                    </span>

                    <span>
                        ✓ Turn unwanted electronic items into extra income.
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

    <section class="marketing-section process-section" id="process">

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

            <div style="display:flex; align-items:flex-start; gap:10px;">

                <button
                    type="button"
                    class="form-rates-btn"
                    id="openRatesBtnForm"
                >
                    BUYING RATES
                </button>

                <button
                    type="button"
                    class="close-form-btn"
                    id="closeFormBtn"
                >
                    ×
                </button>

            </div>

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
         BUYING RATES MODAL

         Shared by the top-nav button and the in-form button.
         Fetches live from backend/get_public_rates.php on every
         open, so any Admin edit is reflected immediately —
         no caching, no stale data.
    ========================================================== -->

    <div
        class="seller-modal"
        id="buyingRatesModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="buyingRatesModalTitle"
        hidden
    >
        <div class="seller-modal-card rates-modal-card">

            <div class="rates-modal-header">

                <div>
                    <span class="seller-modal-kicker">
                        VNZ TECH
                    </span>

                    <h2 id="buyingRatesModalTitle" style="font-size: 26px;">
                        Current Buying Rates
                    </h2>
                </div>

                <button
                    type="button"
                    class="rates-modal-close"
                    id="closeRatesModalBtn"
                    aria-label="Close"
                >
                    ×
                </button>

            </div>

            <div class="rates-modal-body" id="ratesModalBody">

                <div class="rates-modal-state" id="ratesModalLoading">
                    Loading current rates...
                </div>

            </div>

            <div class="rates-modal-footnote">
                Rates are subject to change without prior notice and
                are always subject to actual inspection and verification
                upon submission.
            </div>

        </div>
    </div>


    <div
        class="seller-modal"
        id="trackSubmissionModal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="trackSubmissionModalTitle"
        hidden
    >
        <div class="seller-modal-card tracking-modal-card">

            <div class="tracking-modal-header">

                <div>
                    <span class="seller-modal-kicker">
                        TRACK STATUS
                    </span>

                    <h2 id="trackSubmissionModalTitle">
                        Check your item status
                    </h2>
                </div>

                <button
                    type="button"
                    class="rates-modal-close"
                    id="closeTrackSubmissionModalBtn"
                    aria-label="Close track submission modal"
                >
                    ×
                </button>

            </div>

            <form id="trackSubmissionForm" class="tracking-modal-form">

                <div class="tracking-modal-grid">

                    <div class="tracking-modal-field">
                        <label for="trackReferenceNumber">
                            Reference Number
                        </label>
                        <input
                            type="text"
                            id="trackReferenceNumber"
                            name="reference"
                            placeholder="#SP-260911-XXXX"
                            autocomplete="off"
                            required
                        >
                    </div>

                    <div class="tracking-modal-field">
                        <label for="trackMobileNumber">
                            Mobile Number
                        </label>
                        <input
                            type="tel"
                            id="trackMobileNumber"
                            name="mobile"
                            placeholder="09XXXXXXXXX"
                            autocomplete="tel"
                            required
                        >
                    </div>

                </div>

                <button type="submit" class="track-modal-submit">
                    Track Submission
                </button>

            </form>

            <div
                id="trackSubmissionMessage"
                class="track-modal-message"
                hidden
            ></div>

            <div
                id="trackSubmissionResult"
                class="track-modal-result"
                hidden
            ></div>

        </div>
    </div>


    <?php
        $tradeItemsStmt = $pdo->prepare("
            SELECT
                ti.*
            FROM trade_items ti
            WHERE ti.is_active = 1
            ORDER BY ti.sort_order ASC, ti.id DESC
        ");
        $tradeItemsStmt->execute();
        $activeTradeItems = $tradeItemsStmt->fetchAll();

        $featuredTradeItems = [];
        $allActiveTradeItems = [];

        foreach ($activeTradeItems as $tradeItem) {
            $requirementsStmt = $pdo->prepare("
                SELECT *
                FROM trade_requirements
                WHERE trade_item_id = ?
                ORDER BY sort_order ASC, id ASC
            ");
            $requirementsStmt->execute([$tradeItem['id']]);
            $tradeItem['requirements'] = $requirementsStmt->fetchAll();

            $allActiveTradeItems[] = $tradeItem;

            if ((int) $tradeItem['is_featured'] === 1) {
                $featuredTradeItems[] = $tradeItem;
            }
        }

        $featuredTradeItems = array_slice($featuredTradeItems, 0, 3);
        $featuredTradeItemCount = count($featuredTradeItems);
    ?>

    <?php if (!empty($featuredTradeItems)): ?>
        <section id="trade-items" class="trade-featured-section" aria-label="Featured trade items">
            <div class="trade-section-header">
                <div>
                    <span class="trade-section-kicker">TRADE YOUR SCRAP</span>
                    <h2>Trade your scrap for something useful</h2>
                </div>
                <button type="button" class="trade-view-all-btn" data-open-trade-modal>
                    VIEW ALL TRADE ITEMS
                </button>
            </div>

            <div class="trade-carousel" data-trade-carousel aria-label="Featured trade items carousel">
                <button type="button" class="trade-carousel-arrow trade-carousel-prev" data-trade-prev aria-label="Previous featured trade item" <?= $featuredTradeItemCount < 2 ? 'disabled' : '' ?>>‹</button>

                <div class="trade-carousel-stage" aria-live="polite">
                <?php foreach ($featuredTradeItems as $featureIndex => $featureItem): ?>
                    <?php
                        $featureImage = !empty($featureItem['image_path'])
                            ? '/VNZ-TECH/' . $featureItem['image_path']
                            : '/VNZ-TECH/assets/images/vnz.png';
                        $featurePosition = 'is-hidden';
                        if ($featureIndex === 0) {
                            $featurePosition = 'is-active';
                        } elseif ($featuredTradeItemCount === 2 || $featureIndex === 1) {
                            $featurePosition = 'is-next';
                        } elseif ($featureIndex === 2) {
                            $featurePosition = 'is-prev';
                        }
                        $featureRequestData = [
                            'id' => (int) $featureItem['id'],
                            'name' => (string) $featureItem['name'],
                            'condition' => (string) $featureItem['condition'],
                            'description' => (string) ($featureItem['description'] ?? ''),
                            'image_url' => $featureImage,
                            'requirements' => array_map(static function ($requirement) {
                                return [
                                    'requirement_type' => (string) $requirement['requirement_type'],
                                    'quantity' => (int) $requirement['quantity'],
                                    'storage' => (string) $requirement['storage'],
                                    'description' => (string) ($requirement['description'] ?? ''),
                                ];
                            }, $featureItem['requirements']),
                        ];
                        $featureRequestDataAttribute = htmlspecialchars(
                            json_encode($featureRequestData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    ?>
                    <article class="trade-feature-card <?= $featurePosition ?>" data-trade-slide="<?= (int) $featureIndex ?>" aria-hidden="<?= $featureIndex === 0 ? 'false' : 'true' ?>">
                        <div class="trade-feature-image">
                            <img src="<?= htmlspecialchars($featureImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($featureItem['name'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.src='/VNZ-TECH/assets/images/vnz.png';">
                        </div>

                        <div class="trade-feature-content">
                            <div class="trade-feature-meta">
                                <span class="trade-feature-name"><?= htmlspecialchars($featureItem['name'], ENT_QUOTES, 'UTF-8') ?></span>
                                <span class="trade-feature-condition"><?= htmlspecialchars($featureItem['condition'], ENT_QUOTES, 'UTF-8') ?></span>
                            </div>

                            <?php if (!empty($featureItem['description'])): ?>
                                <p class="trade-feature-description"><?= htmlspecialchars($featureItem['description'], ENT_QUOTES, 'UTF-8') ?></p>
                            <?php endif; ?>

                            <?php if (!empty($featureItem['requirements'])): ?>
                                <div class="trade-feature-requirements">
                                    <?php foreach ($featureItem['requirements'] as $requirement): ?>
                                        <div class="trade-feature-requirement">
                                            <strong><?= (int) $requirement['quantity'] ?> × <?= htmlspecialchars(($requirement['requirement_type'] === 'scrap_board') ? 'Scrap Board' : 'Phone', ENT_QUOTES, 'UTF-8') ?></strong>
                                            <span><?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>

                            <button type="button" class="hero-btn hero-btn-primary trade-feature-request-btn trade-item-request-btn" data-trade-item="<?= $featureRequestDataAttribute ?>">
                                <span>TRADE THIS ITEM</span>
                                <b aria-hidden="true">→</b>
                            </button>
                        </div>
                    </article>
                <?php endforeach; ?>
                </div>

                <button type="button" class="trade-carousel-arrow trade-carousel-next" data-trade-next aria-label="Next featured trade item" <?= $featuredTradeItemCount < 2 ? 'disabled' : '' ?>>›</button>

                <div class="trade-carousel-dots" role="group" aria-label="Choose featured trade item">
                    <?php foreach ($featuredTradeItems as $featureIndex => $featureItem): ?>
                        <button type="button" class="trade-carousel-dot<?= $featureIndex === 0 ? ' is-active' : '' ?>" data-trade-dot="<?= (int) $featureIndex ?>" aria-label="Show featured trade item <?= (int) $featureIndex + 1 ?>" aria-current="<?= $featureIndex === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
        <script>
            (function () {
                const carousel = document.querySelector('[data-trade-carousel]');
                if (!carousel) {
                    return;
                }

                const cards = Array.from(carousel.querySelectorAll('[data-trade-slide]'));
                const dots = Array.from(carousel.querySelectorAll('[data-trade-dot]'));
                const previousButton = carousel.querySelector('[data-trade-prev]');
                const nextButton = carousel.querySelector('[data-trade-next]');
                const positionClasses = ['is-prev', 'is-active', 'is-next', 'is-hidden'];
                let activeIndex = 0;

                function renderCarousel() {
                    const count = cards.length;
                    cards.forEach(function (card, index) {
                        card.classList.remove(...positionClasses);
                        if (count === 1) {
                            card.classList.add('is-active');
                        } else if (index === activeIndex) {
                            card.classList.add('is-active');
                        } else if (count === 2 || index === (activeIndex + 1) % count) {
                            card.classList.add('is-next');
                        } else if (index === (activeIndex - 1 + count) % count) {
                            card.classList.add('is-prev');
                        } else {
                            card.classList.add('is-hidden');
                        }
                        card.setAttribute('aria-hidden', index === activeIndex ? 'false' : 'true');
                    });

                    dots.forEach(function (dot, index) {
                        const isActive = index === activeIndex;
                        dot.classList.toggle('is-active', isActive);
                        dot.setAttribute('aria-current', isActive ? 'true' : 'false');
                    });
                }

                function moveCarousel(offset) {
                    activeIndex = (activeIndex + offset + cards.length) % cards.length;
                    renderCarousel();
                }

                previousButton?.addEventListener('click', function () {
                    moveCarousel(-1);
                });
                nextButton?.addEventListener('click', function () {
                    moveCarousel(1);
                });
                dots.forEach(function (dot) {
                    dot.addEventListener('click', function () {
                        activeIndex = Number(dot.dataset.tradeDot || 0);
                        renderCarousel();
                    });
                });
                carousel.addEventListener('keydown', function (event) {
                    if (event.key === 'ArrowLeft') {
                        moveCarousel(-1);
                    } else if (event.key === 'ArrowRight') {
                        moveCarousel(1);
                    }
                });

                renderCarousel();
            })();
        </script>
    <?php endif; ?>

    <div class="seller-modal" id="tradeItemsModal" role="dialog" aria-modal="true" aria-labelledby="tradeItemsModalTitle" hidden>
        <div class="seller-modal-card trade-modal-card">
            <div class="rates-modal-header">
                <div>
                    <span class="seller-modal-kicker">TRADE ITEMS</span>
                    <h2 id="tradeItemsModalTitle">Trade your scrap for something useful</h2>
                </div>
                <button type="button" class="rates-modal-close" id="closeTradeModalBtn" aria-label="Close trade items modal">×</button>
            </div>

            <div class="trade-modal-body">
                <?php if (empty($allActiveTradeItems)): ?>
                    <div class="trade-modal-empty">No trade items are currently available.</div>
                <?php else: ?>
                    <div class="trade-grid">
                        <?php foreach ($allActiveTradeItems as $tradeItem): ?>
                            <?php
                                $tradeImage = !empty($tradeItem['image_path'])
                                    ? '/VNZ-TECH/' . $tradeItem['image_path']
                                    : '/VNZ-TECH/assets/images/vnz.png';
                                $tradeRequestData = [
                                    'id' => (int) $tradeItem['id'],
                                    'name' => (string) $tradeItem['name'],
                                    'condition' => (string) $tradeItem['condition'],
                                    'description' => (string) ($tradeItem['description'] ?? ''),
                                    'image_url' => $tradeImage,
                                    'requirements' => array_map(static function ($requirement) {
                                        return [
                                            'requirement_type' => (string) $requirement['requirement_type'],
                                            'quantity' => (int) $requirement['quantity'],
                                            'storage' => (string) $requirement['storage'],
                                            'description' => (string) ($requirement['description'] ?? ''),
                                        ];
                                    }, $tradeItem['requirements']),
                                ];
                                $tradeRequestDataAttribute = htmlspecialchars(
                                    json_encode($tradeRequestData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT),
                                    ENT_QUOTES,
                                    'UTF-8'
                                );
                            ?>
                            <article class="trade-modal-card-item">
                                <img src="<?= htmlspecialchars($tradeImage, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?>" onerror="this.src='/VNZ-TECH/assets/images/vnz.png';">
                                <div class="trade-modal-card-content">
                                    <div class="trade-modal-card-top">
                                        <h3><?= htmlspecialchars($tradeItem['name'], ENT_QUOTES, 'UTF-8') ?></h3>
                                        <span><?= htmlspecialchars($tradeItem['condition'], ENT_QUOTES, 'UTF-8') ?></span>
                                    </div>

                                    <?php if (!empty($tradeItem['description'])): ?>
                                        <p><?= htmlspecialchars($tradeItem['description'], ENT_QUOTES, 'UTF-8') ?></p>
                                    <?php endif; ?>

                                    <?php if (!empty($tradeItem['requirements'])): ?>
                                        <div class="trade-modal-requirements">
                                            <h4>Requirements</h4>
                                            <?php foreach ($tradeItem['requirements'] as $requirement): ?>
                                                <div class="trade-modal-requirement">
                                                    <strong><?= (int) $requirement['quantity'] ?> × <?= htmlspecialchars(($requirement['requirement_type'] === 'scrap_board') ? 'Scrap Board' : 'Phone', ENT_QUOTES, 'UTF-8') ?></strong>
                                                    <span><?= htmlspecialchars($requirement['storage'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if (!empty($requirement['description'])): ?>
                                                        <small><?= htmlspecialchars($requirement['description'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    <?php endif; ?>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>

                                    <div class="trade-action-row">
                                        <button type="button" class="trade-primary-btn trade-item-request-btn" data-trade-item="<?= $tradeRequestDataAttribute ?>">TRADE THIS ITEM</button>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="seller-modal" id="tradeRequestModal" role="dialog" aria-modal="true" aria-labelledby="tradeRequestModalTitle" hidden>
        <div class="seller-modal-card trade-request-modal-card">
            <div class="rates-modal-header">
                <div>
                    <span class="seller-modal-kicker">TRADE REQUEST</span>
                    <h2 id="tradeRequestModalTitle">Request Trade</h2>
                </div>
                <button type="button" class="rates-modal-close" id="closeTradeRequestModalBtn" aria-label="Close trade request modal">×</button>
            </div>
            <div id="tradeRequestModalBody" class="trade-request-modal-body"></div>
        </div>
    </div>


    <!-- =========================================================
         FOOTER
    ========================================================== -->

    <footer class="site-footer">

        <div class="footer-cta">

            <div class="footer-cta-copy">

                <span class="footer-cta-kicker">
                    BROKEN PHONE?
                </span>

                <h2 class="footer-cta-title">
                    <span class="footer-cta-title-main">
                        Baka pera
                    </span>
                    <span class="footer-cta-title-accent">
                        pa 'yan.
                    </span>
                </h2>

            </div>

            <div class="footer-cta-actions">

                <button
                    type="button"
                    class="hero-btn hero-btn-primary"
                    data-open-form="phone"
                >
                    <span>SELL A PHONE</span>
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

        </div>

        <div class="footer-grid">

            <div class="footer-column footer-column-brand">
                <div class="footer-brand">VNZ TECH</div>
                <p class="footer-tagline">
                    We buy. We recover. We revalue.
                </p>
                <p class="footer-description">
                    Broken phones and scrap boards, reviewed and revalued properly.
                </p>
            </div>

            <div class="footer-column">
                <h3 class="footer-title">EXPLORE</h3>
                <nav class="footer-nav" aria-label="Footer navigation">
                    <a href="#hero" class="footer-link">Home</a>
                    <a href="#scrap" class="footer-link">Why Sell</a>
                    <a href="#process" class="footer-link">How It Works</a>
                </nav>
            </div>

            <div class="footer-column">
                <h3 class="footer-title">ACCOUNT</h3>
                <nav class="footer-nav" aria-label="Account links">
                    <a href="/VNZ-TECH/account/login.php" class="footer-link" data-loader>Login</a>
                    <a href="/VNZ-TECH/account/register.php" class="footer-link" data-loader>Create Account</a>
                </nav>
            </div>

            <div class="footer-column">
                <h3 class="footer-title">CONTACT</h3>
                <ul class="footer-contact-list">
                    <li class="footer-contact-item">
                        <a href="https://www.facebook.com/profile.php?id=61594929359273" target="_blank" rel="noopener noreferrer">Facebook</a>
                    </li>
                    <li class="footer-contact-item">
                        <a href="[MESSENGER_URL]" target="_blank" rel="noopener noreferrer">Messenger</a>
                    </li>
                    <li class="footer-contact-item">
                        <a href="tel:09948334118">0994 833 4118</a>
                    </li>
                    <li class="footer-contact-item">
                        Dasmariñas, Cavite
                    </li>
                </ul>
            </div>

        </div>

        <div class="footer-bottom">

            <span>
                © <?= date('Y') ?> VNZ TECH
            </span>

            <span class="footer-bottom-center">
                Broken Phones · Scrap Boards · Recovery
            </span>

            <span>
                Prices are subject to inspection and verification.
            </span>

        </div>

    </footer>


<?php endif; ?>


</div>

<script>
    (function () {
        const sectionLinks = document.querySelectorAll(
            '.nav-link[data-section], .dot-link'
        );

        const sections = document.querySelectorAll(
            '.marketing-section[id]'
        );

        if (!sectionLinks.length || !sections.length) {
            return;
        }

        function setActiveSection(sectionId) {
            const activeId = sectionId || 'hero';

            sectionLinks.forEach(function (link) {
                const isActive = link.dataset.section === activeId;

                link.classList.toggle('is-active', isActive);
                link.setAttribute(
                    'aria-current',
                    isActive ? 'true' : 'false'
                );
            });
        }

        const observer = new IntersectionObserver(
            function (entries) {
                const visibleEntries = entries
                    .filter(function (entry) {
                        return entry.isIntersecting;
                    })
                    .sort(function (a, b) {
                        return b.intersectionRatio - a.intersectionRatio;
                    });

                if (!visibleEntries.length) {
                    return;
                }

                setActiveSection(visibleEntries[0].target.id);
            },
            {
                threshold: [0.3, 0.5, 0.8],
                rootMargin: '-10% 0px -30% 0px'
            }
        );

        sections.forEach(function (section) {
            observer.observe(section);
        });

        setActiveSection('hero');
    })();
</script>

<script>
    function syncBodyScrollLock() {
        /*
         * Keep the page as a normal page behind the modal.
         * The modal is an overlay only; it should not freeze the
         * entire website layout or make the whole page feel stuck.
         */
        document.body.style.overflow = '';
        document.documentElement.style.overflow = '';
    }

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
            syncBodyScrollLock();
        }

        function hideSellerModal(modal) {
            if (!modal) {
                return;
            }

            modal.hidden = true;
            syncBodyScrollLock();
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
    /*
    |--------------------------------------------------------------------------
    | BUYING RATES MODAL
    |--------------------------------------------------------------------------
    |
    | Shared between the top-nav "BUYING RATES" button and the
    | in-form "BUYING RATES" button. Always fetches fresh from
    | backend/get_public_rates.php when opened — no caching —
    | so an Admin price edit shows up immediately for viewers.
    |
    */

    (function () {

        const ratesModal =
            document.getElementById('buyingRatesModal');

        const ratesModalBody =
            document.getElementById('ratesModalBody');

        const closeRatesModalBtn =
            document.getElementById('closeRatesModalBtn');

        const openRatesBtnNav =
            document.getElementById('openRatesBtnNav');

        const openRatesBtnForm =
            document.getElementById('openRatesBtnForm');

        if (!ratesModal || !ratesModalBody) {
            return;
        }

        const rateTypeLabels = {
            phone: 'Phone Rates',
            bagsakan: 'Board Code Rates',
            non_code_board: 'Non-Code Board Rates'
        };

        const rateTypeOrder = ['phone', 'bagsakan', 'non_code_board'];

        function pesoAmount(amount) {
            const number = Number(amount) || 0;
            return '₱' + number.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function renderRates(rates) {

            if (!rates.length) {
                ratesModalBody.innerHTML =
                    '<div class="rates-modal-state">No buying rates are currently published.</div>';
                return;
            }

            const grouped = {};

            rates.forEach(function (rate) {
                if (!grouped[rate.rate_type]) {
                    grouped[rate.rate_type] = [];
                }
                grouped[rate.rate_type].push(rate);
            });

            let html = '';

            rateTypeOrder.forEach(function (type) {

                const items = grouped[type];

                if (!items || !items.length) {
                    return;
                }

                html += '<div class="rates-group">';
                html += '<h3 class="rates-group-title">' +
                    (rateTypeLabels[type] || type) +
                    '</h3>';
                html += '<div class="rates-group-list">';

                items.forEach(function (rate) {

                    const description = rate.description
                        ? '<span>' + escapeHtml(rate.description) + '</span>'
                        : '';

                    html += `
                        <div class="rates-row">
                            <div class="rates-row-label">
                                <strong>${escapeHtml(rate.category)}</strong>
                                ${description}
                            </div>
                            <div class="rates-row-price">
                                ${pesoAmount(rate.price)}
                                <small>per ${escapeHtml(rate.unit)}</small>
                            </div>
                        </div>
                    `;

                });

                html += '</div></div>';

            });

            ratesModalBody.innerHTML = html;

        }

        function escapeHtml(value) {
            const div = document.createElement('div');
            div.textContent = value == null ? '' : String(value);
            return div.innerHTML;
        }

        async function loadRates() {

            ratesModalBody.innerHTML =
                '<div class="rates-modal-state" id="ratesModalLoading">Loading current rates...</div>';

            try {

                const response = await fetch(
                    '/VNZ-TECH/backend/get_public_rates.php',
                    {
                        method: 'GET',
                        cache: 'no-store'
                    }
                );

                if (!response.ok) {
                    throw new Error('Unable to load rates.');
                }

                const data = await response.json();

                if (!data.success) {
                    throw new Error(data.message || 'Unable to load rates.');
                }

                renderRates(data.rates || []);

            } catch (error) {

                ratesModalBody.innerHTML =
                    '<div class="rates-modal-state">Unable to load current rates. Please try again.</div>';

            }

        }

        function openRatesModal() {
            ratesModal.hidden = false;
            syncBodyScrollLock();
            loadRates();
        }

        function closeRatesModal() {
            ratesModal.hidden = true;
            syncBodyScrollLock();
        }

        if (openRatesBtnNav) {
            openRatesBtnNav.addEventListener('click', openRatesModal);
        }

        if (openRatesBtnForm) {
            openRatesBtnForm.addEventListener('click', openRatesModal);
        }

        if (closeRatesModalBtn) {
            closeRatesModalBtn.addEventListener('click', closeRatesModal);
        }

        ratesModal.addEventListener('click', function (event) {
            if (event.target === ratesModal) {
                closeRatesModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !ratesModal.hidden) {
                closeRatesModal();
            }
        });

    })();
</script>

<script>
    (function () {
        const tradeModal = document.getElementById('tradeItemsModal');
        const closeTradeModalBtn = document.getElementById('closeTradeModalBtn');
        const viewAllTradeButtons = document.querySelectorAll('[data-open-trade-modal]');

        if (!tradeModal) {
            return;
        }

        function openTradeModal() {
            tradeModal.hidden = false;
            syncBodyScrollLock();
        }

        function closeTradeModal() {
            tradeModal.hidden = true;
            syncBodyScrollLock();
        }

        viewAllTradeButtons.forEach(function (button) {
            button.addEventListener('click', openTradeModal);
        });

        if (closeTradeModalBtn) {
            closeTradeModalBtn.addEventListener('click', closeTradeModal);
        }

        tradeModal.addEventListener('click', function (event) {
            if (event.target === tradeModal) {
                closeTradeModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !tradeModal.hidden) {
                closeTradeModal();
            }
        });
    })();
</script>


<script>
    (function () {
        const trackModal = document.getElementById('trackSubmissionModal');
        const openTrackModalBtn = document.getElementById('openTrackModalBtn');
        const closeTrackSubmissionModalBtn = document.getElementById('closeTrackSubmissionModalBtn');
        const trackSubmissionForm = document.getElementById('trackSubmissionForm');
        const trackSubmissionMessage = document.getElementById('trackSubmissionMessage');
        const trackSubmissionResult = document.getElementById('trackSubmissionResult');

        if (!trackModal || !trackSubmissionForm) {
            return;
        }

        function showTrackModal() {
            trackModal.hidden = false;
            syncBodyScrollLock();
            const firstInput = trackSubmissionForm.querySelector('input');
            if (firstInput) {
                setTimeout(() => firstInput.focus(), 50);
            }
        }

        function hideTrackModal() {
            trackModal.hidden = true;
            trackSubmissionMessage.hidden = true;
            trackSubmissionMessage.className = 'track-modal-message';
            trackSubmissionMessage.textContent = '';
            trackSubmissionResult.hidden = true;
            trackSubmissionResult.innerHTML = '';
            syncBodyScrollLock();
        }

        function formatDate(dateString) {
            if (!dateString) {
                return '—';
            }

            const date = new Date(dateString);
            if (Number.isNaN(date.getTime())) {
                return dateString;
            }

            return date.toLocaleString('en-PH', {
                dateStyle: 'medium',
                timeStyle: 'short'
            });
        }

        function renderSubmissionResult(submission) {
            const items = submission.items || [];
            const offer = submission.offer;

            const itemSummary = items.length
                ? items.map((item, index) => `
                    <div class="track-result-row">
                        <strong>Item ${index + 1}</strong>
                        <span>${item.brand || 'Item'} ${item.model || ''} · ${item.issue || 'N/A'}</span>
                    </div>
                `).join('')
                : '<div class="track-result-row"><strong>Items</strong><span>No item details available.</span></div>';

            const offerSummary = offer
                ? `
                    <div class="track-result-row">
                        <strong>Latest Offer</strong>
                        <span>₱${Number(offer.offer_amount || 0).toLocaleString('en-PH', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}</span>
                    </div>
                    <div class="track-result-row">
                        <strong>Status</strong>
                        <span>${offer.status || 'Pending'}</span>
                    </div>
                `
                : '<div class="track-result-row"><strong>Latest Offer</strong><span>No offer yet</span></div>';

            trackSubmissionResult.innerHTML = `
                <h3>${submission.reference_number}</h3>
                <div class="track-result-row">
                    <strong>Seller</strong>
                    <span>${submission.seller_name || 'N/A'}</span>
                </div>
                <div class="track-result-row">
                    <strong>Status</strong>
                    <span>${submission.status || 'Pending'}</span>
                </div>
                <div class="track-result-row">
                    <strong>Submitted</strong>
                    <span>${formatDate(submission.created_at)}</span>
                </div>
                ${itemSummary}
                ${offerSummary}
            `;
            trackSubmissionResult.hidden = false;
        }

        if (openTrackModalBtn) {
            openTrackModalBtn.addEventListener('click', showTrackModal);
        }

        if (closeTrackSubmissionModalBtn) {
            closeTrackSubmissionModalBtn.addEventListener('click', hideTrackModal);
        }

        trackModal.addEventListener('click', function (event) {
            if (event.target === trackModal) {
                hideTrackModal();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !trackModal.hidden) {
                hideTrackModal();
            }
        });

        trackSubmissionForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const formData = new FormData(trackSubmissionForm);
            const submitBtn = trackSubmissionForm.querySelector('button[type="submit"]');

            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Checking...';
            }

            trackSubmissionMessage.hidden = true;
            trackSubmissionMessage.className = 'track-modal-message';
            trackSubmissionMessage.textContent = '';
            trackSubmissionResult.hidden = true;
            trackSubmissionResult.innerHTML = '';

            try {
                const response = await fetch('/VNZ-TECH/backend/track_submission.php', {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const data = await response.json();

                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to track this submission.');
                }

                trackSubmissionMessage.className = 'track-modal-message success';
                trackSubmissionMessage.textContent = 'Submission found. Here are the latest details.';
                trackSubmissionMessage.hidden = false;
                renderSubmissionResult(data.submission);

            } catch (error) {
                trackSubmissionMessage.className = 'track-modal-message error';
                trackSubmissionMessage.textContent = error.message || 'Something went wrong. Please try again.';
                trackSubmissionMessage.hidden = false;
            } finally {
                if (submitBtn) {
                    submitBtn.disabled = false;
                    submitBtn.textContent = 'Track Submission';
                }
            }
        });
    })();
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

<script>
    const tradeCsrfToken = <?= json_encode($tradeCsrfToken, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function openTradeRequestModal() {
        const modal = document.getElementById('tradeRequestModal');
        if (modal) {
            modal.hidden = false;
        }
    }

    function closeTradeRequestModal() {
        const modal = document.getElementById('tradeRequestModal');
        if (modal) {
            modal.hidden = true;
        }
    }

    function escapeTradeText(value) {
        return String(value ?? '').replace(/[&<>"']/g, function (character) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[character];
        });
    }

    function openTradeRequestProcess(tradeItem) {
        const modalBody = document.getElementById('tradeRequestModalBody');
        const modal = document.getElementById('tradeRequestModal');
        if (!modalBody || !modal) {
            return;
        }

        openTradeRequestModal();

        const requirements = Array.isArray(tradeItem.requirements) ? tradeItem.requirements : [];
        const imageHtml = tradeItem.image_url
            ? `<img class="trade-request-item-image" src="${escapeTradeText(tradeItem.image_url)}" alt="${escapeTradeText(tradeItem.name)}">`
            : '';
        const requirementsHtml = requirements.map(function (requirement) {
            const isBoard = requirement.requirement_type === 'scrap_board';
            const itemLabel = isBoard ? 'Scrap Board' : 'Damaged Phone';
            const bringLabel = isBoard ? 'qualifying boards' : 'qualifying phones';
            const detailLabel = isBoard ? 'Classification' : 'Storage';

            return `
                <article class="trade-request-requirement">
                    <strong>${Number(requirement.quantity)} × ${itemLabel}</strong>
                    <span>${detailLabel}: ${escapeTradeText(requirement.storage)}</span>
                    ${requirement.description ? `<small>${escapeTradeText(requirement.description)}</small>` : ''}
                    <p>Bring ${Number(requirement.quantity)} ${bringLabel} to VNZ TECH.</p>
                </article>
            `;
        }).join('');

        modalBody.innerHTML = `
            <div class="trade-request-intro">
                ${imageHtml}
                <div>
                    <h3>${escapeTradeText(tradeItem.name)}</h3>
                    <p><strong>Condition:</strong> ${escapeTradeText(tradeItem.condition)}</p>
                    ${tradeItem.description ? `<p>${escapeTradeText(tradeItem.description)}</p>` : ''}
                </div>
            </div>
            <section class="trade-request-requirements">
                <h3>Required Trade Items</h3>
                ${requirementsHtml || '<p>No requirements are configured for this item.</p>'}
            </section>
            <div class="trade-request-actions">
                <button type="button" class="trade-primary-btn" id="submitTradeRequestBtn" ${requirements.length ? '' : 'disabled'}>Submit Trade Request</button>
            </div>
        `;

        const submitButton = document.getElementById('submitTradeRequestBtn');
        submitButton?.addEventListener('click', async function () {
            const formBody = new URLSearchParams();
            formBody.set('action', 'create_request');
            formBody.set('trade_item_id', String(tradeItem.id));
            formBody.set('csrf_token', tradeCsrfToken);

            submitButton.disabled = true;
            submitButton.textContent = 'Submitting...';

            try {
                const response = await fetch('/VNZ-TECH/backend/trade_requests.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    credentials: 'same-origin',
                    body: formBody.toString(),
                    cache: 'no-store'
                });
                const data = await response.json();
                if (!response.ok || !data.success) {
                    throw new Error(data.message || 'Unable to submit trade request.');
                }

                modalBody.innerHTML = `
                    <div class="trade-request-success">
                        <h3>Trade request submitted successfully.</h3>
                        <p>Reference: <strong>${escapeTradeText(data.reference)}</strong></p>
                        <p>Status: <strong>PENDING</strong></p>
                        <a href="/VNZ-TECH/account/dashboard.php?page=trade_history">View Trade History</a>
                    </div>
                `;
            } catch (error) {
                modalBody.insertAdjacentHTML('afterbegin', `<div class="trade-request-error">${escapeTradeText(error.message || 'Unable to submit trade request.')}</div>`);
                submitButton.disabled = false;
                submitButton.textContent = 'Submit Trade Request';
            }
        });
    }

    document.addEventListener('click', function (event) {
        const triggerButton = event.target.closest('.trade-item-request-btn');
        if (!triggerButton) {
            return;
        }

        let tradeItem;
        try {
            tradeItem = JSON.parse(triggerButton.dataset.tradeItem || '{}');
        } catch (error) {
            return;
        }
        if (!tradeItem.id) {
            return;
        }

        const isLoggedIn = <?= isset($_SESSION['user_logged_in']) && $_SESSION['user_logged_in'] ? 'true' : 'false' ?>;
        if (!isLoggedIn) {
            window.location.href = '/VNZ-TECH/account/login.php';
            return;
        }

        openTradeRequestProcess(tradeItem);
    });

    document.getElementById('closeTradeRequestModalBtn')?.addEventListener('click', closeTradeRequestModal);
</script>

<script>
    (function () {
        const header = document.querySelector('.site-header');
        const siteMenu = document.getElementById('siteMenu');
        const navToggle = document.getElementById('navToggle');

        if (!header || !siteMenu || !navToggle) {
            return;
        }

        const sectionLinks = Array.from(
            document.querySelectorAll('a.nav-link[data-section]')
        );

        function setMenuState(isOpen) {
            siteMenu.classList.toggle('is-open', isOpen);
            navToggle.classList.toggle('is-open', isOpen);
            navToggle.setAttribute('aria-expanded', String(isOpen));
        }

        navToggle.addEventListener('click', function () {
            const isOpen = !siteMenu.classList.contains('is-open');
            setMenuState(isOpen);
        });

        siteMenu.addEventListener('click', function (event) {
            const target = event.target;

            if (!target) {
                return;
            }

            if (target.closest('a') || target.closest('button')) {
                setMenuState(false);
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && siteMenu.classList.contains('is-open')) {
                setMenuState(false);
            }
        });

        if (!sectionLinks.length) {
            return;
        }

        const observer = new IntersectionObserver(function (entries) {
            const visibleEntries = entries
                .filter(function (entry) {
                    return entry.isIntersecting;
                })
                .sort(function (a, b) {
                    return b.intersectionRatio - a.intersectionRatio;
                });

            if (!visibleEntries.length) {
                return;
            }

            const activeSectionId = visibleEntries[0].target.id;

            sectionLinks.forEach(function (link) {
                const isActive = link.dataset.section === activeSectionId;
                link.classList.toggle('is-active', isActive);
            });
        }, {
            rootMargin: '-45% 0px -50% 0px'
        });

        sectionLinks.forEach(function (link) {
            const section = document.getElementById(link.dataset.section);

            if (section) {
                observer.observe(section);
            }
        });
    })();
</script>

</body>

</html>