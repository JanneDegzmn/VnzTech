<?php
$socialAuthRequestHost = parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
$showLocalSocialAuth = is_string($socialAuthRequestHost)
    && in_array(strtolower($socialAuthRequestHost), ['localhost', '127.0.0.1'], true);
?>
<?php if ($showLocalSocialAuth): ?>
<div class="social-auth-options" aria-label="Social sign-in options">
    <div class="social-auth-divider"><span>or continue with</span></div>
    <div class="social-auth-buttons">
        <a
            class="social-auth-button social-auth-google"
            href="/VNZ-TECH/backend/social_auth.php?action=start&amp;provider=google"
        >
            <span class="social-auth-mark social-auth-mark-google" aria-hidden="true">
                <svg viewBox="0 0 48 48" focusable="false">
                    <path fill="#EA4335" d="M24 9.5c3.54 0 6.71 1.22 9.21 3.6l6.85-6.85C35.9 2.38 30.47 0 24 0 14.62 0 6.51 5.38 2.56 13.22l7.98 6.19C12.43 13.72 17.74 9.5 24 9.5z"/>
                    <path fill="#4285F4" d="M46.98 24.55c0-1.57-.15-3.09-.38-4.55H24v9.02h12.94c-.58 2.96-2.26 5.48-4.73 7.18l7.65 5.93c4.47-4.13 7.12-10.2 7.12-17.58z"/>
                    <path fill="#FBBC05" d="M10.53 28.59A14.4 14.4 0 0 1 9.75 24c0-1.59.27-3.13.75-4.59l-7.98-6.19A23.92 23.92 0 0 0 0 24c0 3.87.93 7.52 2.56 10.78l7.97-6.19z"/>
                    <path fill="#34A853" d="M24 48c6.48 0 11.93-2.13 15.91-5.8l-7.65-5.93c-2.13 1.43-4.86 2.28-8.26 2.28-6.26 0-11.57-4.22-13.47-9.91l-7.98 6.19C6.51 42.62 14.62 48 24 48z"/>
                </svg>
            </span>
            Continue with Google
        </a>
        <a
            class="social-auth-button social-auth-facebook"
            href="/VNZ-TECH/backend/social_auth.php?action=start&amp;provider=facebook"
        >
            <span class="social-auth-mark social-auth-mark-facebook" aria-hidden="true">
                <svg viewBox="0 0 320 512" focusable="false">
                    <path fill="currentColor" d="M279.14 288l14.22-92.66h-88.91v-60.13c0-25.35 12.42-50.06 52.24-50.06H297V6.26S260.43 0 225.36 0C152.77 0 105.1 44.38 105.1 124.72v70.62H24.6V288h80.5v224h99.2V288z"/>
                </svg>
            </span>
            Continue with Facebook
        </a>
    </div>
    <?php endif; ?>
</div>
