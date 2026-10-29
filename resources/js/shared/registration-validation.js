export function initRegistrationValidation(formId) {
    const form = document.getElementById(formId);
    if (!form) return;

    const fields = [...form.querySelectorAll('input, select, textarea')];

    const isActive = (field) => {
        if (field.disabled || field.type === 'hidden') return false;
        if (field.closest('.d-none, [hidden]')) return false;
        if (field.closest('[style*="display: none"]')) return false;
        return true;
    };

    const getMessage = (field) => {
        if (field.validity.valueMissing) {
            if (field.type === 'checkbox') {
                return 'You must agree to continue.';
            }

            if (field.type === 'file') {
                return 'Please upload the required document.';
            }

            if (field.tagName === 'SELECT') {
                return 'Please select an option.';
            }

            return 'This field is required.';
        }

        if (field.validity.typeMismatch) {
            return 'Please enter a valid value.';
        }

        if (field.validity.tooShort) {
            return `Minimum ${field.minLength} characters required.`;
        }

        if (field.validity.patternMismatch) {
            return 'Please use the required format.';
        }

        return field.validationMessage || 'Please check this field.';
    };

    const validateField = (field) => {
        if (!field.id || !isActive(field) || !field.willValidate) {
            return true;
        }

        const valid = field.checkValidity();
        const errorId = `${field.id}-required-error`;
        let error = document.getElementById(errorId);

        if (!valid && !error) {
            error = document.createElement('div');
            error.id = errorId;
            error.className = 'invalid-feedback d-block';
            field.insertAdjacentElement('afterend', error);
        }

        field.classList.toggle('is-invalid', !valid);

        if (error) {
            error.textContent = valid ? '' : getMessage(field);
            error.style.display = valid ? 'none' : 'block';
        }

        return valid;
    };

    form.addEventListener('invalid', (event) => {
        event.preventDefault();
        validateField(event.target);
    }, true);

    form.addEventListener('submit', (event) => {
        let firstInvalid = null;

        fields.forEach((field) => {
            if (!isActive(field)) return;

            if (!validateField(field) && !firstInvalid) {
                firstInvalid = field;
            }
        });

        if (firstInvalid) {
            event.preventDefault();
            event.stopImmediatePropagation();
            firstInvalid.focus();
            firstInvalid.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });
        }
    }, true);

    fields.forEach((field) => {
        ['input', 'change', 'blur'].forEach((eventName) => {
            field.addEventListener(eventName, () => {
                if (document.getElementById(`${field.id}-required-error`)) {
                    validateField(field);
                }
            });
        });
    });
}