<?php
/** Per-submission camera capture. Only the server can accept a face check. */
require_once __DIR__ . '/../backend/csrf.php';

function vnzRenderSubmissionFaceCapture(string $context, string $language = 'en'): void
{
    $context = $context === 'account' ? 'account' : 'guest';
    $fil = $language === 'fil';
    $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $id = 'submission-face-' . $context;
    ?>
    <div data-submission-face-capture data-context="<?= $context ?>" data-language="<?= $fil ? 'fil' : 'en' ?>">
        <input type="hidden" name="csrf_token" value="<?= $escape(generateCsrfToken()) ?>">
        <input type="hidden" name="face_nonce" value="<?= $escape(vnzSubmissionFaceToken($context)) ?>" data-face-nonce>
        <input type="hidden" name="face_consent" value="1" data-face-consent-value disabled>
        <input type="file" name="submission_face" accept="image/jpeg" data-face-file hidden tabindex="-1" aria-hidden="true">
        <dialog class="submission-face-dialog" data-face-dialog data-face-area="<?= $context === 'account' ? 'seller' : 'public' ?>"
                aria-labelledby="<?= $id ?>-title" aria-describedby="<?= $id ?>-description">
            <div class="submission-face-header">
                <span class="submission-face-eyebrow"><?= $fil ? 'Bago i-submit' : 'Before you submit' ?></span>
                <button type="button" class="submission-face-close" data-face-close aria-label="<?= $fil ? 'Isara ang camera' : 'Close camera' ?>">&times;</button>
            </div>
            <h2 id="<?= $id ?>-title"><?= $fil ? 'Isang malinaw na selfie muna' : 'Take a clear selfie' ?></h2>
            <p id="<?= $id ?>-description"><?= $fil
                ? 'Isasama ang private selfie sa submission mo para makita ng authorized admin. Chine-check lang kung may isang mukha sa photo; hindi ito proof of identity o liveness.'
                : 'Your private selfie is attached to this submission for authorized admins to view. We check for one face in the photo; this does not prove identity or liveness.' ?></p>
            <div class="submission-face-preview">
                <video data-face-video autoplay playsinline muted hidden aria-label="<?= $fil ? 'Live camera preview' : 'Live camera preview' ?>"></video>
                <img data-face-preview hidden alt="<?= $fil ? 'Selfie na isasama sa submission' : 'Selfie to attach to your submission' ?>">
                <span data-face-placeholder><?= $fil ? 'Harap sa camera. Dapat maliwanag at walang takip ang mukha.' : 'Face the camera in good light, with your face uncovered.' ?></span>
            </div>
            <div class="submission-face-status" role="status" aria-live="polite" tabindex="-1" data-face-status></div>
            <label class="submission-face-consent" for="<?= $id ?>-consent">
                <input type="checkbox" id="<?= $id ?>-consent" data-face-consent>
                <span><?= $fil
                    ? 'Pumapayag akong kunan at i-check locally ang selfie ko at ipakita ito sa authorized admin sa submission na ito. Private at encrypted ang photo; buburahin ito 30 araw matapos tanggapin o tanggihan ang submission, sa susunod na daily cleanup. Hindi ito ipapadala sa external service.'
                    : 'I consent to local processing of my selfie and access by authorized admins for this submission. The photo is private and encrypted, and deleted 30 days after the submission is accepted or declined, at the next daily cleanup. It is not sent to an external service.' ?></span>
            </label>
            <div class="submission-face-actions">
                <button type="button" class="submission-face-button" data-face-camera><?= $fil ? 'Buksan ang camera' : 'Open camera' ?></button>
                <button type="button" class="submission-face-button submission-face-primary" data-face-capture hidden><?= $fil ? 'Kunan ng selfie' : 'Take selfie' ?></button>
                <button type="button" class="submission-face-button" data-face-retake hidden><?= $fil ? 'Kunan ulit' : 'Retake' ?></button>
                <button type="button" class="submission-face-button submission-face-primary" data-face-submit hidden disabled><?= $fil ? 'Gamitin at i-submit' : 'Use selfie & submit' ?></button>
            </div>
            <p class="submission-face-help"><?= $fil ? 'Kailangan ng camera permission. Sa phone gamit ang network address, kailangan ng HTTPS. Kapag isinara mo ito, nandito pa rin ang item details mo.' : 'Camera permission is required. Phone access through a network address requires HTTPS. Closing this step keeps your item details.' ?></p>
            <button type="button" class="submission-face-cancel" data-face-cancel><?= $fil ? 'Balik sa form' : 'Back to form' ?></button>
        </dialog>
        <noscript><p><?= $fil ? 'I-enable ang JavaScript para sa required camera selfie bago mag-submit.' : 'Enable JavaScript for the required camera selfie before submitting.' ?></p></noscript>
    </div>
    <?php
}
