(() => {
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

    document.querySelectorAll('form[data-password-strength-form]').forEach((form) => {
        const password = form.querySelector('[data-password-strength-input]');
        const confirmation = form.querySelector('[data-password-confirmation]');
        const track = form.querySelector('[data-password-strength-track]');
        const label = form.querySelector('[data-password-strength-label]');
        const error = form.querySelector('[data-password-error]');
        const rules = Array.from(form.querySelectorAll('[data-password-rule]'));
        if (!password || !confirmation || !track || !label || !error) {
            return;
        }

        let currentStrength = 'empty';

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
            const strength = updateStrength();
            if (strength === 'empty' || strength === 'weak') {
                error.textContent = strength === 'empty'
                    ? 'Enter a password to continue.'
                    : 'Choose a Medium or Strong password to continue.';
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
    });
})();
