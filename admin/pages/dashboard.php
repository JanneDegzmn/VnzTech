<?php

require_once __DIR__ . '/../../backend/database.php';
require_once __DIR__ . '/../../backend/session.php';
require_once __DIR__ . '/../../backend/auth.php';

requireAdminLogin();


/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$submissionCount = 0;
$pendingReviewCount = 0;
$pendingOfferCount = 0;
$acceptedCount = 0;


/*
|--------------------------------------------------------------------------
| Total Submissions
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
");

$submissionCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Review
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status = 'new_submission'
");

$pendingReviewCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Pending Offers
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status IN ('offer_pending', 'offer_sent')
");

$pendingOfferCount = (int) $stmt->fetchColumn();


/*
|--------------------------------------------------------------------------
| Accepted
|--------------------------------------------------------------------------
*/

$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM submissions
    WHERE status = 'accepted'
");

$acceptedCount = (int) $stmt->fetchColumn();

?>

<div class="dashboard-page">


    <!-- =====================================================
         WELCOME
    ====================================================== -->

    <div class="dashboard-welcome">

        <div>

            <div class="dashboard-eyebrow">
                VNZ TECH OVERVIEW
            </div>

            <h1>
                Dashboard
            </h1>

            <p>
                Monitor submissions, offers, and transactions
                from one place.
            </p>

        </div>

    </div>


    <!-- =====================================================
         STATISTICS
    ====================================================== -->

    <div class="stats-grid">


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    TOTAL SUBMISSIONS
                </span>

                <span class="stat-icon">
                    ▤
                </span>

            </div>

            <div class="stat-number">
                <?= $submissionCount ?>
            </div>

            <div class="stat-description">
                All seller submissions
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    PENDING REVIEW
                </span>

                <span class="stat-icon">
                    ◷
                </span>

            </div>

            <div class="stat-number">
                <?= $pendingReviewCount ?>
            </div>

            <div class="stat-description">
                Waiting for admin review
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    PENDING OFFERS
                </span>

                <span class="stat-icon">
                    ₱
                </span>

            </div>

            <div class="stat-number">
                <?= $pendingOfferCount ?>
            </div>

            <div class="stat-description">
                Offers awaiting action
            </div>

        </div>


        <div class="stat-card">

            <div class="stat-top">

                <span class="stat-label">
                    ACCEPTED
                </span>

                <span class="stat-icon">
                    ✓
                </span>

            </div>

            <div class="stat-number">
                <?= $acceptedCount ?>
            </div>

            <div class="stat-description">
                Accepted submissions
            </div>

        </div>

    </div>


    <!-- =====================================================
         QUICK OVERVIEW
    ====================================================== -->

    <div class="dashboard-grid">


        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        Recent Activity
                    </h2>

                    <p>
                        Latest activity will appear here.
                    </p>

                </div>

            </div>


            <div class="empty-state">

                <div class="empty-icon">
                    —
                </div>

                <h3>
                    No activity yet
                </h3>

                <p>
                    New seller submissions and admin activity
                    will appear here.
                </p>

            </div>

        </div>


        <div class="dashboard-panel">

            <div class="panel-header">

                <div>

                    <h2>
                        System Status
                    </h2>

                    <p>
                        Current platform status.
                    </p>

                </div>

            </div>


            <div class="system-status-list">

                <div class="system-status-item">

                    <div class="status-indicator online"></div>

                    <div>

                        <strong>
                            Database
                        </strong>

                        <span>
                            Connected
                        </span>

                    </div>

                </div>


                <div class="system-status-item">

                    <div class="status-indicator online"></div>

                    <div>

                        <strong>
                            Authentication
                        </strong>

                        <span>
                            Active
                        </span>

                    </div>

                </div>


                <div class="system-status-item">

                    <div class="status-indicator online"></div>

                    <div>

                        <strong>
                            Admin Session
                        </strong>

                        <span>
                            Secure
                        </span>

                    </div>

                </div>

            </div>

        </div>


    </div>

</div>