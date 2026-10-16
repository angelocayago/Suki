import { initAddressCascade } from '../shared/address-cascade.js';
import { initAgeFromBirthday } from '../shared/age-from-birthday.js';
import { initSubmitLock } from '../shared/submit-lock.js';

const readPayload = (id) => {
    const element = document.getElementById(id);
    if (!element) return null;

    try {
        return JSON.parse(element.textContent || '');
    } catch {
        return null;
    }
};

const initRoleSections = () => {
    const roleSelect = document.getElementById('role');
    const sellerSection = document.getElementById('seller-section');
    const logisticSection = document.getElementById('logistic-section');
    const businessPermitField = document.getElementById('business-permit-field');

    // Buyer Terms and Privacy Policy
    const buyerTermsSection = document.getElementById('buyerTermsSection');
    const termsCheckbox = document.getElementById('terms');

    if (!roleSelect) return;

    const updateSections = () => {
        const isBuyer = roleSelect.value === 'buyer';

        // Existing role sections
        sellerSection?.classList.toggle('d-none', roleSelect.value !== 'seller');
        logisticSection?.classList.toggle('d-none', roleSelect.value !== 'logistic');
        businessPermitField?.classList.toggle('d-none', roleSelect.value !== 'seller');

        // Buyer-only Terms and Privacy Policy
        if (buyerTermsSection) {
            buyerTermsSection.style.display = isBuyer ? '' : 'none';
        }

        if (termsCheckbox) {
            termsCheckbox.required = isBuyer;

            if (!isBuyer) {
                termsCheckbox.checked = false;
            }
        }
    };

    roleSelect.addEventListener('change', updateSections);
    updateSections();
};

const initGeocoding = (geocodeKey) => {
    const addressInput = document.getElementById('api_address');
    const regionSelect = document.getElementById('region');
    const regionNameInput = document.getElementById('region_name');
    if (!addressInput || !regionSelect || !geocodeKey) return;

    addressInput.addEventListener('blur', async () => {
        const address = addressInput.value.trim();
        if (!address) return;

        try {
            const response = await fetch(`https://maps.googleapis.com/maps/api/geocode/json?address=${encodeURIComponent(address)}&key=${encodeURIComponent(geocodeKey)}`);
            const data = await response.json();
            if (data.status !== 'OK' || !data.results?.length) return;

            const regionName = data.results[0].address_components
                .find((component) => component.types.includes('administrative_area_level_1'))
                ?.long_name;
            if (!regionName) return;

            const regions = await fetch('/api/addresses/regions').then((result) => result.json());
            const match = regions.find((region) => region.name.toLowerCase().includes(regionName.toLowerCase()) || regionName.toLowerCase().includes(region.name.toLowerCase()));
            if (!match) return;

            regionSelect.value = match.code;
            if (regionNameInput) regionNameInput.value = match.name;
            regionSelect.dispatchEvent(new Event('change'));
        } catch {
        }
    });
};

const initPasswordValidation = () => {
    const passwordInput = document.getElementById('password');
    const passwordError = document.getElementById('password-error');
    const confirmPasswordInput = document.getElementById('password_confirmation');
    const confirmPasswordError = document.getElementById('password-confirmation-error');

    if (!passwordInput || !passwordError) return;

    const ruleLabels = {
        length: 'At least 8 characters',
        upper: 'At least 1 uppercase letter',
        lower: 'At least 1 lowercase letter',
        number: 'At least 1 number',
        symbol: 'At least 1 special character',
    };

    const validateConfirmation = () => {
        if (!confirmPasswordInput || !confirmPasswordError) return;

        const confirmation = confirmPasswordInput.value;

        const mismatch =
            confirmation.length > 0 &&
            passwordInput.value !== confirmation;

        confirmPasswordError.textContent = mismatch
            ? 'Passwords do not match.'
            : '';

        confirmPasswordInput.classList.toggle('is-invalid', mismatch);

        return !mismatch;
    };

    const validatePassword = () => {
        const password = passwordInput.value;

        const rules = {
            length: password.length >= 8,
            upper: /[A-Z]/.test(password),
            lower: /[a-z]/.test(password),
            number: /[0-9]/.test(password),
            symbol: /[^A-Za-z0-9]/.test(password),
        };

        Object.entries(rules).forEach(([rule, passed]) => {
            const element = document.getElementById(`password-rule-${rule}`);

            if (!element) return;

            element.classList.toggle('text-success', passed);
            element.classList.toggle('text-muted', !passed);

            element.textContent =
                `${passed ? '✓' : '○'} ${ruleLabels[rule]}`;
        });

        let message = '';

        if (password.length > 0 && !rules.length) {
            message = 'Password must be at least 8 characters.';
        } else if (
            password.length > 0 &&
            !Object.values(rules).every(Boolean)
        ) {
            message = 'Password must meet all the requirements above.';
        }

        passwordError.textContent = message;
        passwordInput.classList.toggle('is-invalid', Boolean(message));

        validateConfirmation();

        return !message;
    };

    passwordInput.addEventListener('input', validatePassword);
    passwordInput.addEventListener('blur', validatePassword);

    passwordInput.addEventListener('invalid', (event) => {
        event.preventDefault();
        validatePassword();
    });

    if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', validateConfirmation);
        confirmPasswordInput.addEventListener('blur', validateConfirmation);
    }

    validatePassword();
};

const initPasswordToggles = () => {
    document.querySelectorAll('.register-password-toggle').forEach((button) => {
        const input = document.getElementById(button.dataset.passwordTarget);
        const icon = button.querySelector('i');

        if (!input || !icon) return;

        button.addEventListener('click', () => {
            const isVisible = input.type === 'password';

            input.type = isVisible ? 'text' : 'password';

            icon.classList.toggle('bi-eye', !isVisible);
            icon.classList.toggle('bi-eye-slash', isVisible);

            button.setAttribute('aria-pressed', String(isVisible));

            const fieldName = input.id === 'password'
                ? 'password'
                : 'confirm password';

            button.setAttribute(
                'aria-label',
                `${isVisible ? 'Hide' : 'Show'} ${fieldName}`
            );
        });
    });
};

const initSubmitLockForRegister = () => initSubmitLock('registerForm', 'Submitting...');

const initRegister = () => {
    const data = readPayload('auth-page-data') || {};
    initSubmitLockForRegister();
    initRoleSections();
    initAgeFromBirthday();
    initPasswordValidation();
    initPasswordToggles();
    initAddressCascade({
        regionSelect: document.getElementById('region'),
        provinceSelect: document.getElementById('province'),
        municipalitySelect: document.getElementById('municipality'),
        barangaySelect: document.getElementById('barangay'),
        municipalityField: document.getElementById('municipality-field'),
        regionNameInput: document.getElementById('region_name'),
        provinceNameInput: document.getElementById('province_name'),
        municipalityNameInput: document.getElementById('municipality_name'),
        barangayNameInput: document.getElementById('barangay_name'),
        savedRegion: data.region || '',
        savedProvince: data.province || '',
        savedMunicipality: data.municipality || '',
        savedBarangay: data.barangay || '',
    });
    initGeocoding(data.geocodeKey || '');
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initRegister, { once: true });
} else {
    initRegister();
}

export { initRegister };

// Middle Initial: allow only one letter, including Ñ/ñ.
const middleInitialInput = document.getElementById('middle_name');

if (middleInitialInput) {
    middleInitialInput.addEventListener('input', function () {
        this.value = Array.from(this.value)
            .filter(char => /^\p{L}$/u.test(char))
            .slice(0, 1)
            .join('');
    });
}

// Contact Number: allow digits only, maximum 11.
// Contact Number: normalize Philippine mobile numbers to 09XXXXXXXXX.
const mobileNumberInput = document.getElementById('mobile_number');

if (mobileNumberInput) {
    const normalizeMobileNumber = (value) => {
        let digits = value.replace(/\D/g, '');

        if (digits.startsWith('63') && digits.length >= 12) {
            digits = '0' + digits.slice(2);
        }

        return digits.slice(0, 11);
    };

    mobileNumberInput.addEventListener('input', function () {
        this.value = normalizeMobileNumber(this.value);
    });

    mobileNumberInput.addEventListener('paste', function (event) {
        const pastedText = event.clipboardData.getData('text');

        event.preventDefault();
        this.value = normalizeMobileNumber(pastedText);
        this.dispatchEvent(new Event('input', { bubbles: true }));
    });
}

// Show a clear email validation message.
const registrationEmail = document.getElementById('email');
const registrationEmailError = document.getElementById('email-error');

if (registrationEmail && registrationEmailError) {
    const validateRegistrationEmail = () => {
        const email = registrationEmail.value.trim();

        let message = '';

        if (!email) {
            message = 'Email address is required.';
        } else if (
            !registrationEmail.validity.valid ||
            !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)
        ) {
            message = 'Please enter a valid email address.';
        }

        registrationEmailError.textContent = message;
        registrationEmail.classList.toggle('is-invalid', Boolean(message));

        return !message;
    };

    registrationEmail.addEventListener('input', validateRegistrationEmail);
    registrationEmail.addEventListener('blur', () => {
        registrationEmail.value = registrationEmail.value.trim();
        validateRegistrationEmail();
    });

    registrationEmail.addEventListener('invalid', (event) => {
        event.preventDefault();
        validateRegistrationEmail();
    });
}