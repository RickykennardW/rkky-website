const registrationForm = document.querySelector('#registration-form');

if (registrationForm) {
    const fields = [...registrationForm.querySelectorAll('input:not([type="hidden"])')];
    const password = registrationForm.elements.namedItem('password');
    const confirmation = registrationForm.elements.namedItem('password_confirmation');
    const matchMessage = document.querySelector('#password_confirmation-match');
    const submitButton = registrationForm.querySelector('[type="submit"]');
    const submitLabel = submitButton.querySelector('[data-submit-label]');
    const spinner = submitButton.querySelector('.button-spinner');
    const touchedFields = new Set();
    let submitting = false;

    function validationMessage(field) {
        if (!field.value.trim()) {
            const requiredMessages = {
                full_name: 'Enter your full name.',
                email: 'Enter your student email address.',
                password: 'Create a password.',
                password_confirmation: 'Re-enter your password.',
                birthdate: 'Select your birthdate.',
            };

            return requiredMessages[field.name];
        }

        if (field.name === 'full_name' && Array.from(field.value.trim()).length < 3) {
            return 'Your full name must be at least 3 characters.';
        }

        if (field.name === 'email' && field.validity.typeMismatch) {
            return 'Enter a valid email address, such as you@university.edu.';
        }

        if (field.name === 'password' && field.value.length < 8) {
            return 'Use at least 8 characters for your password.';
        }

        if (field.name === 'password_confirmation' && field.value !== password.value) {
            return 'Your passwords do not match.';
        }

        if (field.name === 'birthdate' && field.validity.rangeOverflow) {
            return 'Your birthdate must be in the past.';
        }

        if (!field.validity.valid) {
            return 'Please check this field and try again.';
        }

        return '';
    }

    function validateField(field) {
        const message = validationMessage(field);
        const error = document.getElementById(`${field.id}-error`);

        error.textContent = message;
        error.hidden = !message;
        field.setAttribute('aria-invalid', String(Boolean(message)));

        return !message;
    }

    function updatePasswordMatch() {
        const hasConfirmation = confirmation.value.length > 0;
        const matches = hasConfirmation && password.value === confirmation.value;

        matchMessage.hidden = !matches;
        confirmation.dataset.matched = String(matches);

        if (hasConfirmation || touchedFields.has(confirmation)) {
            validateField(confirmation);
        }
    }

    function setLoading(loading) {
        submitButton.disabled = loading;
        submitButton.dataset.loading = String(loading);
        registrationForm.setAttribute('aria-busy', String(loading));
        spinner.hidden = !loading;
        submitLabel.textContent = loading ? 'Submitting…' : 'Complete Registration';
    }

    fields.forEach((field) => {
        if (field.getAttribute('aria-invalid') === 'true') {
            touchedFields.add(field);
        }

        field.addEventListener('blur', () => {
            touchedFields.add(field);
            validateField(field);
        });

        field.addEventListener('input', () => {
            if (touchedFields.has(field)) {
                validateField(field);
            }

            if (field === password || field === confirmation) {
                updatePasswordMatch();
            }
        });
    });

    registrationForm.querySelectorAll('[data-password-toggle]').forEach((toggle) => {
        const input = document.getElementById(toggle.dataset.passwordToggle);
        const fieldName = input === password ? 'password' : 'confirm password';

        toggle.addEventListener('click', () => {
            const showPassword = input.type === 'password';

            input.type = showPassword ? 'text' : 'password';
            toggle.setAttribute('aria-pressed', String(showPassword));
            toggle.setAttribute('aria-label', `${showPassword ? 'Hide' : 'Show'} ${fieldName}`);
        });

        toggle.hidden = false;
    });

    registrationForm.addEventListener('submit', (event) => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        fields.forEach((field) => touchedFields.add(field));
        const validFields = fields.map(validateField);
        updatePasswordMatch();

        if (validFields.includes(false)) {
            event.preventDefault();
            fields[validFields.indexOf(false)].focus();

            return;
        }

        submitting = true;
        setLoading(true);
    });

    window.addEventListener('pageshow', (event) => {
        submitting = false;
        setLoading(false);

        if (event.persisted) {
            password.value = '';
            confirmation.value = '';
            updatePasswordMatch();
        }
    });

    fields.find((field) => field.getAttribute('aria-invalid') === 'true')?.focus();
}
