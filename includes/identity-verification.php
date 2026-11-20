<?php
/** Shared in-form enrollment. This markup never grants submission permission. */
require_once __DIR__ . '/../backend/csrf.php';

if (!function_exists('vnzRenderIdentityVerification')) {
    function vnzRenderIdentityVerification(string $context, string $email = '', string $language = 'en', bool $enabled = false): void
    {
        $context = $context === 'account' ? 'account' : 'guest';
        $area = $context === 'account' ? 'seller' : 'public';
        $fil = $language === 'fil';
        $mode = function_exists('vnzIdentityMode') ? vnzIdentityMode() : 'manual_v2';
        $automatic = $mode === 'auto_dual_v4';
        $escape = static fn(string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
        $id = 'identity-' . $context;
        ?>
        <input type="hidden" name="csrf_token" value="<?= $escape(generateCsrfToken()) ?>">
        <input type="hidden" name="identity_draft" value="" data-identity-draft>
        <section class="vnz-identity" data-identity-wizard data-identity-area="<?= $area ?>"
                 data-context="<?= $context ?>" data-language="<?= $fil ? 'fil' : 'en' ?>"
                 data-mode="<?= $escape($mode) ?>"
                 data-enabled="<?= $enabled ? '1' : '0' ?>" aria-labelledby="<?= $id ?>-title" <?= $enabled ? '' : 'hidden' ?>>
            <div class="identity-heading">
                <span class="identity-emblem" aria-hidden="true">
                    <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6"><path d="m12 2 8 3v6c0 5-5 9-8 11-3-2-8-6-8-11V5l8-3Z"/><path d="m8 12 3 3 5-6"/></svg>
                </span>
                <div>
                    <p class="identity-eyebrow"><?= $fil ? 'Para sa safe na bentahan' : 'Safer selling' ?></p>
                    <h3 id="<?= $id ?>-title"><?= $fil ? 'I-verify muna ang identity mo' : 'Verify your identity first' ?></h3>
                    <p><?= $automatic
                        ? ($context === 'account'
                            ? ($fil ? 'Isang beses lang: i-match ang mukha sa ID at camera selfie mo gamit ang dalawang check.' : 'One-time face matching between your ID portrait and camera selfie, using two checks.')
                            : ($fil ? 'Bawat guest submission, kailangan ulit ng ID at camera selfie face check.' : 'Each guest submission needs a fresh ID and camera selfie face check.'))
                        : ($context === 'account'
                        ? ($fil ? 'Isang beses lang: ID at selfie, tapos review ng admin.' : 'One-time ID and selfie verification, reviewed by an admin.')
                        : ($fil ? 'Kailangan ng ID at selfie review para sa bawat guest submission.' : 'Each guest submission needs a separate ID and selfie review.')) ?></p>
                </div>
            </div>
            <?php if ($context === 'guest'): ?>
                <p class="identity-account-link"><?= $fil ? 'May seller account ka na?' : 'Already have a seller account?' ?>
                    <a href="/VNZ-TECH/account/dashboard.php"><?= $fil ? 'Doon mag-submit para once lang ang verification.' : 'Submit from your account for one-time verification.' ?></a>
                </p>
            <?php endif; ?>
            <div class="identity-status" data-identity-status role="status" aria-live="polite" tabindex="-1">
                <?= $fil ? 'Chine-check ang verification mo…' : 'Checking your verification…' ?>
            </div>
            <div class="identity-actions" data-identity-start-actions>
                <button type="button" class="identity-button identity-primary" data-identity-open hidden><?= $fil ? 'Simulan ang verification' : 'Start verification' ?></button>
                <button type="button" class="identity-button" data-identity-refresh><?= $fil ? 'I-check ulit' : 'Refresh status' ?></button>
            </div>
            <div class="identity-enrollment" data-identity-enrollment hidden>
                <ol class="identity-steps" aria-label="<?= $fil ? 'Mga step ng verification' : 'Verification steps' ?>">
                    <li><span>1</span> <?= $fil ? 'Upload ng ID' : 'Upload ID' ?></li>
                    <li><span>2</span> <?= $fil ? 'Camera selfie' : 'Camera selfie' ?></li>
                    <li><span>3</span> <?= $automatic ? ($fil ? 'Automatic face check' : 'Automatic face check') : 'Admin review' ?></li>
                </ol>
                <p class="identity-notice"><?= $automatic
                    ? ($fil
                        ? 'Ang mukha sa mismong ID mo at camera selfie ang iko-compare sa server gamit ang local face-api.js at Face++. Parehong kailangang pumasa. Cropped face images lang ang ipapadala sa Face++ para sa external processing; hindi ito lalabas sa item listing. Face match lang ito: hindi nito napapatunayang tunay ang ID o may certified liveness check.'
                        : 'The server compares the portrait from your actual ID with your camera selfie using local face-api.js and Face++. Both checks must pass. Only cropped face images are sent to Face++ for external processing; they do not appear in your item listing. Face matching does not prove document authenticity or certified liveness.')
                    : ($fil
                    ? 'Private ang ID at selfie mo. Hindi ito ilalagay sa item listing. Face comparison lang ang tool; admin pa rin ang magche-check ng ID at mag-aapprove.'
                    : 'Your ID and selfie stay private and do not appear in your item listing. Face comparison assists an admin; it does not prove that an ID is genuine or replace their review.') ?></p>
                <div class="identity-field">
                    <label for="<?= $id ?>-email"><?= $fil ? 'Email na gagamitin mo' : 'Your email address' ?></label>
                    <input id="<?= $id ?>-email" type="email" inputmode="email" autocomplete="email" maxlength="254"
                           value="<?= $escape($email) ?>" data-identity-email <?= $context === 'account' ? 'readonly' : '' ?>
                           aria-describedby="<?= $id ?>-email-help">
                    <small id="<?= $id ?>-email-help"><?= $fil
                        ? 'Parehong email ang gamitin sa submission at sa account na gagawin mo.'
                        : 'Use this same email for your submission and any seller account you create.' ?></small>
                </div>
                <div class="identity-evidence-grid">
                    <div class="identity-field">
                        <label for="<?= $id ?>-file"><?= $fil ? 'Malinaw na valid ID na may photo' : 'Clear photo ID' ?></label>
                        <input id="<?= $id ?>-file" class="identity-file" type="file" accept="image/jpeg,image/png,image/webp"
                               data-identity-file aria-describedby="<?= $id ?>-file-help">
                        <small id="<?= $id ?>-file-help"><?= $fil ? 'JPG, PNG, o WEBP. Max 5 MB. Dapat kita ang mukha at detalye ng ID.' : 'JPG, PNG, or WEBP. Max 5 MB. Keep the face and ID details readable.' ?></small>
                        <div class="identity-preview"><img data-identity-id-preview alt="<?= $fil ? 'Preview ng napiling ID' : 'Selected ID preview' ?>" hidden><span data-identity-id-placeholder><?= $fil ? 'Dito makikita ang ID preview' : 'Your ID preview appears here' ?></span></div>
                    </div>
                    <div class="identity-field">
                        <span class="identity-label"><?= $fil ? 'Selfie gamit ang camera' : 'Camera selfie' ?></span>
                        <div class="identity-preview identity-camera">
                            <video data-identity-video autoplay playsinline muted aria-label="<?= $fil ? 'Live camera preview' : 'Live camera preview' ?>" hidden></video>
                            <img data-identity-selfie-preview alt="<?= $fil ? 'Preview ng selfie' : 'Captured selfie preview' ?>" hidden>
                            <span data-identity-selfie-placeholder><?= $fil ? 'Harap sa camera, maliwanag, walang takip ang mukha.' : 'Face the camera in good light with your face uncovered.' ?></span>
                        </div>
                        <div class="identity-actions">
                            <button type="button" class="identity-button" data-identity-camera><?= $fil ? 'Buksan ang camera' : 'Open camera' ?></button>
                            <button type="button" class="identity-button identity-primary" data-identity-capture hidden><?= $fil ? 'Kunan ng selfie' : 'Capture selfie' ?></button>
                            <button type="button" class="identity-button" data-identity-stop hidden><?= $fil ? 'I-stop ang camera' : 'Stop camera' ?></button>
                        </div>
                        <small><?= $fil ? 'Kailangan ng camera permission. Sa phone, buksan sa HTTPS.' : 'Camera permission is needed. Phone access through a network address requires HTTPS.' ?></small>
                    </div>
                </div>
                <div class="identity-status" data-identity-comparison role="status" aria-live="polite"><?= $automatic
                    ? ($fil ? 'I-upload ang ID at kunan ng selfie. Sa server gagawin ang dalawang face check.' : 'Add your ID and capture a selfie. Both face checks run on the server.')
                    : ($fil ? 'I-upload ang ID at kunan ng selfie para ma-compare.' : 'Add your ID and capture a selfie to compare the faces.') ?></div>
                <button type="button" class="identity-button" data-identity-retry-comparison hidden><?= $fil ? 'I-compare ulit' : 'Retry face comparison' ?></button>
                <label class="identity-consent" for="<?= $id ?>-consent">
                    <input id="<?= $id ?>-consent" type="checkbox" data-identity-consent>
                    <span><?= $automatic
                        ? ($fil
                            ? 'Pumapayag akong i-process ang ID at camera selfie ko para sa local face matching at ipadala ang cropped face images sa Face++ para sa pangalawang face check. Hindi ito ID-authenticity o certified liveness verification. Sa VNZ TECH, expired ang hindi natuloy na attempt pagkalipas ng 24 oras; buburahin ang ID/selfie 7 araw matapos ang decision, sa susunod na daily cleanup. May minimal verification/audit record na maiiwan. Hiwalay ang processing at retention policy ng Face++.'
                            : 'I consent to local processing of my ID and camera selfie and to sending their cropped face images to Face++ for a second face comparison. This does not verify ID authenticity or certified liveness. At VNZ TECH, unfinished attempts expire after 24 hours; ID/selfie images are deleted 7 days after a decision at the next daily cleanup. A minimal verification/audit record remains. Face++ processing and retention follow its separate policy.')
                        : ($fil
                        ? 'Pumapayag akong gamitin ang ID at selfie ko para sa identity review. Expired ang hindi natuloy na attempt pagkalipas ng 24 oras, at ang pending review pagkalipas ng 7 araw. Buburahin ang ID/selfie 7 araw matapos ang decision; may minimal verification/audit record na maiiwan.'
                        : 'I consent to using my ID and selfie for identity review. Unfinished attempts expire after 24 hours; pending reviews expire after 7 days. ID/selfie images are deleted 7 days after a decision. A minimal verification and audit record remains.') ?>
                        <?php if ($automatic): ?><a href="https://www.faceplusplus.com/privacy-policy/" target="_blank" rel="noopener noreferrer"><?= $fil ? 'Face++ privacy policy' : 'Face++ privacy policy' ?></a><?php endif; ?>
                    </span>
                </label>
                <div class="identity-actions">
                    <button type="button" class="identity-button identity-primary" data-identity-send disabled><?= $automatic ? ($fil ? 'I-check ang face match' : 'Check my face') : ($fil ? 'I-send para sa review' : 'Send for identity review') ?></button>
                    <button type="button" class="identity-button" data-identity-cancel><?= $fil ? 'Mamaya na' : 'Cancel for now' ?></button>
                </div>
            </div>
            <noscript><p class="identity-notice"><?= $fil ? 'I-enable ang JavaScript para sa ID at camera verification.' : 'Enable JavaScript for ID and camera verification.' ?></p></noscript>
        </section>
        <?php
    }
}
