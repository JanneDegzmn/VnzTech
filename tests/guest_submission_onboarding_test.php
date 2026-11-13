<?php

require_once __DIR__ . '/../backend/guest_submission_onboarding.php';

function onboardingAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$seller = ['id' => 7, 'email' => 'seller@example.test', 'is_active' => 1, 'email_verified_at' => null];
$session = [];
vnzRememberGuestSubmissionOnboarding($session, 7, $seller['email']);
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, $seller) === null, 'Ordinary signup must not trigger the guest prompt.');

$session = ['guest_registration_prefill' => ['submission_id' => 101, 'email' => ' SELLER@EXAMPLE.TEST ']];
vnzRememberGuestSubmissionOnboarding($session, 7, $seller['email']);
unset($session['guest_registration_prefill']);
$state = vnzGuestSubmissionOnboardingForSeller($session, $seller);
onboardingAssert($state !== null && $state['phase'] === 'verify' && $state['auto_show'], 'Same-session guest signup must preserve a seller-bound verification prompt after clearing prefill.');
onboardingAssert($state['submission_id'] === 101, 'The last server-recorded guest submission must be tracked.');
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, [...$seller, 'id' => 8]) === null, 'The marker must never appear for another seller.');
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, [...$seller, 'email' => 'other@example.test']) === null, 'A changed account email must not reuse another email marker.');
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, [...$seller, 'is_active' => 0]) === null, 'Inactive accounts cannot use the prompt.');

$wrongEmail = ['guest_registration_prefill' => ['email' => 'other@example.test']];
vnzRememberGuestSubmissionOnboarding($wrongEmail, 7, $seller['email']);
onboardingAssert(!isset($wrongEmail['guest_submission_onboarding']), 'A different registration email must not trigger a claim prompt.');

vnzRecordGuestSubmissionVerificationSent($session, 8);
onboardingAssert($session['guest_submission_onboarding']['last_sent_at'] === 0, 'Another seller cannot change resend timing.');
vnzRecordGuestSubmissionVerificationSent($session, 7);
onboardingAssert($session['guest_submission_onboarding']['last_sent_at'] > 0, 'Successful verification sends must update the cooldown.');
$session['guest_submission_onboarding']['auto_show'] = false;
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, $seller)['auto_show'] === false, 'Dismissal must retain the banner without reopening automatically.');

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE submissions (id INTEGER PRIMARY KEY, user_id INTEGER NULL)');
$pdo->exec('CREATE TABLE guest_submission_claims (submission_id INTEGER PRIMARY KEY, seller_id INTEGER NOT NULL)');
$pdo->exec('INSERT INTO submissions VALUES (101, NULL)');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $session, $seller)['phase'] === 'verify', 'An unverified account must remain in verification, regardless of marker data.');
$verifiedSeller = [...$seller, 'email_verified_at' => '2026-11-14 03:00:00'];
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($session, $verifiedSeller)['auto_show'], 'Verification must trigger its own popup after the original claim prompt was dismissed.');
$session['guest_submission_onboarding']['verified_notice_seen'] = true;
onboardingAssert(!vnzGuestSubmissionOnboardingForSeller($session, $verifiedSeller)['auto_show'], 'A presented verification notice must not repeat on every dashboard visit.');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $session, $verifiedSeller)['phase'] === 'verified', 'Email verification alone must not be reported as a successful claim.');
$pdo->exec('UPDATE submissions SET user_id = 8 WHERE id = 101');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $session, $verifiedSeller)['phase'] === 'verified', 'Another seller owning the submission is never a claim success.');
$pdo->exec('UPDATE submissions SET user_id = 7 WHERE id = 101');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $session, $verifiedSeller)['phase'] === 'linked', 'Actual ownership must produce linked status, including verification in another tab/device.');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $session, $seller)['phase'] === 'verify', 'Even existing ownership must not bypass email verification in this onboarding flow.');

$legacy = ['guest_registration_prefill' => ['email' => $seller['email']]];
vnzRememberGuestSubmissionOnboarding($legacy, 7, $seller['email']);
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $legacy, $verifiedSeller)['phase'] === 'verified', 'Legacy session markers cannot confirm ownership of the last submission.');
$pdo->exec('INSERT INTO guest_submission_claims VALUES (101, 7)');
onboardingAssert(vnzGuestSubmissionOnboardingStatus($pdo, $legacy, $verifiedSeller)['phase'] === 'verified', 'An unrelated audited claim must not imply the last guest submission was linked.');

$feedbackSession = [];
vnzRememberEmailVerificationFeedback($feedbackSession, 7, 'success');
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($feedbackSession, $seller) === null, 'Success presentation must require actual email verification.');
onboardingAssert(vnzGuestSubmissionOnboardingForSeller($feedbackSession, [...$verifiedSeller, 'id' => 8]) === null, 'Verification feedback must never appear for a different seller.');
$feedbackState = vnzGuestSubmissionOnboardingForSeller($feedbackSession, $verifiedSeller);
onboardingAssert($feedbackState['phase'] === 'verified' && $feedbackState['auto_show'] && !$feedbackState['guest_flow'], 'Verified sellers without a guest marker still need a verification confirmation, without claiming a submission.');
$oldEvent = $feedbackState['feedback_id'];
vnzRememberEmailVerificationFeedback($feedbackSession, 7, 'invalid');
vnzDismissEmailVerificationFeedback($feedbackSession, 7, $oldEvent);
onboardingAssert(isset($feedbackSession['email_verification_feedback']), 'An older tab must not dismiss a newer verification result.');
$invalidState = vnzGuestSubmissionOnboardingForSeller($feedbackSession, $seller);
onboardingAssert($invalidState['verification_result'] === 'invalid' && $invalidState['auto_show'], 'An invalid link must produce visible feedback for an unverified seller.');
vnzDismissEmailVerificationFeedback($feedbackSession, 8, $invalidState['feedback_id']);
onboardingAssert(isset($feedbackSession['email_verification_feedback']), 'Another seller must not dismiss verification feedback.');
vnzDismissEmailVerificationFeedback($feedbackSession, 7, $invalidState['feedback_id']);
onboardingAssert(!isset($feedbackSession['email_verification_feedback']), 'Presented matching feedback may be dismissed once.');

echo "Guest submission onboarding tests passed.\n";
