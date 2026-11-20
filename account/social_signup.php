<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/site_status.php';
vnzEnforceSiteMaintenance('auto', false);


require_once __DIR__ . '/../backend/session.php';
require_once __DIR__ . '/../backend/csrf.php';

$profile = $_SESSION['social_signup_profile'] ?? null;
if (
    !is_array($profile)
    || !isset($_SESSION['social_signup_started_at'])
    || time() - (int) $_SESSION['social_signup_started_at'] > 600
) {
    unset($_SESSION['social_signup_profile'], $_SESSION['social_signup_started_at']);
    header('Location: /VNZ-TECH/account/register.php?error=' . urlencode('Your social sign-up expired. Please start again.'));
    exit;
}

$error = (string) ($_GET['error'] ?? '');
$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Account | VNZ TECH</title>
    <script>
        (function () {
            try {
                document.documentElement.setAttribute(
                    'data-public-theme',
                    localStorage.getItem('vnz-public-theme') === 'light' ? 'light' : 'dark'
                );
            } catch (error) {
                document.documentElement.setAttribute('data-public-theme', 'dark');
                console.error('Unable to load the public theme preference.', error);
            }
        })();
    </script>
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/seller_register.css?v=<?= filemtime(__DIR__ . '/../assets/css/seller_register.css') ?>">
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/public_theme.css?v=<?= filemtime(__DIR__ . '/../assets/css/public_theme.css') ?>">
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/social_auth.css?v=<?= filemtime(__DIR__ . '/../assets/css/social_auth.css') ?>">
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/terms_modal.css?v=<?= filemtime(__DIR__ . '/../assets/css/terms_modal.css') ?>">
    <link rel="stylesheet" href="/VNZ-TECH/assets/css/password_visibility.css?v=<?= filemtime(__DIR__ . '/../assets/css/password_visibility.css') ?>">
    <script src="/VNZ-TECH/assets/js/public-theme.js?v=<?= filemtime(__DIR__ . '/../assets/js/public-theme.js') ?>" defer></script>
<?php $siteStatusArea = 'public'; require __DIR__ . '/../includes/site-status-head.php'; ?>
</head>
<body>
<div class="register-wrapper">
    <div class="brand">
        <h1>VNZ TECH</h1>
        <p>WE BUY. WE RECOVER. WE REVALUE.</p>
    </div>
    <div class="register-card">
        <h2>Complete Your Account</h2>
        <p class="subtitle">Add your mobile number and confirm the terms to finish your account. You can optionally add a password for email sign-in.</p>
        <?php if ($error !== ''): ?>
            <div class="message error"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>
        <form id="socialSignupForm" action="/VNZ-TECH/backend/social_signup_complete.php" method="POST" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
            <div class="field">
                <label for="full_name">Full Name *</label>
                <input id="full_name" name="full_name" maxlength="150" value="<?= htmlspecialchars((string) ($profile['full_name'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required>
            </div>
            <div class="field">
                <label for="mobile">Mobile Number *</label>
                <input id="mobile" name="mobile" maxlength="30" autocomplete="tel" required>
            </div>
            <div class="field">
                <label for="email">Email Address *</label>
                <input id="email" name="email" type="email" maxlength="190" autocomplete="email" value="<?= htmlspecialchars((string) ($profile['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" <?= ($profile['provider'] ?? '') === 'google' ? 'readonly' : '' ?> required>
                <?php if (($profile['provider'] ?? '') === 'facebook'): ?>
                    <div class="hint">We’ll send a verification link to this address. Make sure you can access it.</div>
                <?php elseif (($profile['provider'] ?? '') === 'google'): ?>
                    <div class="hint">Use the verified Google email address for this account.</div>
                <?php endif; ?>
            </div>
            <details class="password-disclosure" <?= $error !== '' ? 'open' : '' ?>>
                <summary>
                    <span class="password-disclosure-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none">
                            <path d="M7.5 10V7.5a4.5 4.5 0 0 1 9 0V10m-9 0h9.5A2.5 2.5 0 0 1 19 12.5v6A2.5 2.5 0 0 1 16.5 21h-9A2.5 2.5 0 0 1 5 18.5v-6A2.5 2.5 0 0 1 7.5 10Zm4.5 4v3" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </span>
                    <span class="password-disclosure-copy">
                        <span class="password-disclosure-title">Add a password</span>
                        <span class="password-disclosure-subtitle">Use your email to sign in, too.</span>
                    </span>
                    <span class="password-optional-tag">OPTIONAL</span>
                    <span class="password-disclosure-chevron" aria-hidden="true"></span>
                </summary>
                <div class="password-disclosure-content" id="passwordDisclosureContent">
                    <p class="password-disclosure-note">You can skip this and keep signing in with your social account. Leave both fields blank to continue without a password.</p>
                    <div class="field">
                        <label for="password">Create Password</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" aria-describedby="passwordPolicy">
                        <div class="password-strength-track" data-strength="empty" role="progressbar" aria-label="Password strength" aria-valuemin="0" aria-valuemax="3" aria-valuenow="0">
                            <span class="password-strength-fill" id="passwordStrengthFill"></span>
                        </div>
                        <div class="password-strength-label" id="passwordStrengthLabel" data-strength="empty" aria-live="polite">Start typing to check strength</div>
                        <div class="password-policy" id="passwordPolicy">
                            Use at least 8 characters and 3 character types. For a Strong password, use 16+ characters with uppercase, lowercase, a number, and a symbol.
                        </div>
                        <div class="password-criteria">
                            <span data-password-rule="length">8+ characters</span>
                            <span data-password-rule="lowercase">Lowercase</span>
                            <span data-password-rule="uppercase">Uppercase</span>
                            <span data-password-rule="number">Number</span>
                            <span data-password-rule="symbol">Symbol</span>
                        </div>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Confirm Password</label>
                        <input id="confirm_password" name="confirm_password" type="password" autocomplete="new-password">
                    </div>
                </div>
            </details>
            <div class="password-form-error" id="passwordFormError" role="alert" hidden></div>
            <div class="terms">
                <input type="checkbox" id="terms" name="terms" value="1" required>
                <label for="terms">I agree to the VNZ TECH <a href="#termsModal" data-open-terms aria-haspopup="dialog" aria-controls="termsModal">Terms &amp; Conditions</a> and understand that my account information will be stored securely.</label>
            </div>
            <button type="submit" class="submit-button">CREATE ACCOUNT</button>
        </form>
        <div class="login-link"><a href="/VNZ-TECH/account/login.php">Cancel and return to login</a></div>
    </div>
</div>
<?php include __DIR__ . '/../includes/terms_modal.php'; ?>
<script src="/VNZ-TECH/assets/js/terms-modal.js?v=<?= filemtime(__DIR__ . '/../assets/js/terms-modal.js') ?>" defer></script>
<script src="/VNZ-TECH/assets/js/password-visibility.js?v=<?= filemtime(__DIR__ . '/../assets/js/password-visibility.js') ?>" defer></script>
<script>
(() => {
    const form = document.getElementById('socialSignupForm');
    const passwordDisclosure = document.querySelector('.password-disclosure');
    const disclosureSummary = passwordDisclosure.querySelector('summary');
    const disclosurePanel = document.getElementById('passwordDisclosureContent');
    const password = document.getElementById('password');
    const confirmation = document.getElementById('confirm_password');
    const fill = document.getElementById('passwordStrengthFill');
    const label = document.getElementById('passwordStrengthLabel');
    const track = document.querySelector('.password-strength-track');
    const error = document.getElementById('passwordFormError');
    const rules = Array.from(document.querySelectorAll('[data-password-rule]'));
    let currentStrength = 'empty';
    let disclosureAnimating = false;

    disclosureSummary.addEventListener('click', (event) => {
        event.preventDefault();
        if (disclosureAnimating) {
            return;
        }

        const shouldOpen = !passwordDisclosure.open;
        const reducedMotion = window.matchMedia
            && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
        if (reducedMotion || typeof disclosurePanel.animate !== 'function') {
            passwordDisclosure.open = shouldOpen;
            return;
        }

        disclosureAnimating = true;
        if (shouldOpen) {
            passwordDisclosure.open = true;
            const targetHeight = disclosurePanel.scrollHeight;
            const animation = disclosurePanel.animate(
                [
                    { height: '0px', opacity: 0, transform: 'translateY(-8px)' },
                    { height: `${targetHeight}px`, opacity: 1, transform: 'translateY(0)' }
                ],
                { duration: 320, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
            );
            animation.onfinish = () => {
                animation.cancel();
                disclosureAnimating = false;
            };
            return;
        }

        const currentHeight = disclosurePanel.getBoundingClientRect().height;
        const animation = disclosurePanel.animate(
            [
                { height: `${currentHeight}px`, opacity: 1, transform: 'translateY(0)' },
                { height: '0px', opacity: 0, transform: 'translateY(-8px)' }
            ],
            { duration: 260, easing: 'cubic-bezier(0.4, 0, 0.2, 1)' }
        );
        animation.onfinish = () => {
            passwordDisclosure.open = false;
            animation.cancel();
            disclosureAnimating = false;
        };
    });

    if (
        passwordDisclosure.open
        && !(window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches)
        && typeof disclosurePanel.animate === 'function'
    ) {
        disclosureAnimating = true;
        const targetHeight = disclosurePanel.scrollHeight;
        const animation = disclosurePanel.animate(
            [
                { height: '0px', opacity: 0, transform: 'translateY(-8px)' },
                { height: `${targetHeight}px`, opacity: 1, transform: 'translateY(0)' }
            ],
            { duration: 320, easing: 'cubic-bezier(0.22, 1, 0.36, 1)' }
        );
        animation.onfinish = () => {
            animation.cancel();
            disclosureAnimating = false;
        };
    }

    function evaluatePassword(value) {
        const checks = {
            length: Array.from(value).length >= 8,
            lowercase: /[a-z]/.test(value),
            uppercase: /[A-Z]/.test(value),
            number: /[0-9]/.test(value),
            symbol: /[^A-Za-z0-9]/.test(value)
        };
        if (value.length === 0) {
            return { checks, strength: 'empty' };
        }

        const typeCount = Number(checks.lowercase)
            + Number(checks.uppercase)
            + Number(checks.number)
            + Number(checks.symbol);
        const characterCount = Array.from(value).length;
        const strength = characterCount >= 16 && typeCount === 4
            ? 'strong'
            : characterCount >= 8 && typeCount >= 3
                ? 'medium'
                : 'weak';

        return { checks, strength };
    }

    function updateStrength() {
        const result = evaluatePassword(password.value);
        const progress = { empty: 0, weak: 1, medium: 2, strong: 3 }[result.strength];
        track.dataset.strength = result.strength;
        track.setAttribute('aria-valuenow', String(progress));
        label.dataset.strength = result.strength;
        label.textContent = result.strength === 'empty'
            ? 'Start typing to check strength'
            : result.strength.charAt(0).toUpperCase() + result.strength.slice(1);
        if (result.strength !== currentStrength) {
            label.classList.remove('is-changing');
            window.requestAnimationFrame(() => label.classList.add('is-changing'));
            currentStrength = result.strength;
        }
        rules.forEach((rule) => {
            rule.classList.toggle('is-met', result.checks[rule.dataset.passwordRule]);
        });
        return result.strength;
    }

    password.addEventListener('input', () => {
        error.hidden = true;
        updateStrength();
    });
    confirmation.addEventListener('input', () => {
        error.hidden = true;
    });
    form.addEventListener('submit', (event) => {
        if (password.value === '' && confirmation.value === '') {
            error.hidden = true;
            return;
        }

        const strength = updateStrength();
        if (strength === 'empty') {
            error.textContent = 'Enter a password to continue.';
            error.hidden = false;
            password.focus();
            event.preventDefault();
            return;
        }
        if (strength === 'weak') {
            error.textContent = 'Choose a Medium or Strong password to continue.';
            error.hidden = false;
            event.preventDefault();
            password.focus();
            return;
        }
        if (password.value !== confirmation.value) {
            error.textContent = 'Passwords do not match.';
            error.hidden = false;
            event.preventDefault();
            confirmation.focus();
            return;
        }
        error.hidden = true;
    });
})();
</script>
</body>
</html>
