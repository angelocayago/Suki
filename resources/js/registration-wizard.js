const wizardDefinitions = {
    buyer: {
        steps: [
            ['Personal', ['first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'age']],
            ['Contact', ['email', 'phone', 'region', 'province', 'municipality', 'barangay', 'street_address', 'postal_code', 'government_id']],
            ['Security', ['password', 'password_confirmation', 'terms']],
            ['Review', []],
        ],
    },
    seller: {
        steps: [
            ['Personal', ['first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'age']],
            ['Contact', ['email', 'phone', 'region', 'province', 'municipality', 'barangay', 'address', 'postal_code', 'password', 'password_confirmation']],
            ['Store', ['business_name', 'category_id', 'seller_type', 'tin']],
            ['Business / Verification', ['valid_id', 'business_permit', 'terms']],
            ['Review', []],
        ],
    },
    logistics: {
        steps: [
            ['Personal', ['first_name', 'last_name', 'middle_initial', 'sex', 'birthday', 'age']],
            ['Contact', ['email', 'phone', 'region', 'province', 'municipality', 'barangay', 'address', 'postal_code', 'password', 'password_confirmation']],
            ['Role', []],
            ['Details / Verification', ['business_name', 'government_id', 'business_permit', 'terms']],
            ['Review', []],
        ],
    },
    rider: {
        steps: [
            ['Personal', ['first_name', 'last_name']],
            ['Contact', ['email', 'phone', 'address', 'password', 'password_confirmation']],
            ['Role', ['vehicle_type', 'plate_number', 'license_number']],
            ['Details / Verification', ['government_id', 'drivers_license', 'vehicle_document', 'terms']],
            ['Review', []],
        ],
    },
};

function fieldBlock(control, form) {
    let block = control;
    while (block.parentElement && block.parentElement !== form) {
        const parent = block.parentElement;
        if (parent.querySelectorAll('[name]').length > 1) break;
        block = parent;
    }
    return block;
}

function readableLabel(control) {
    const label = control.id ? document.querySelector(`label[for="${CSS.escape(control.id)}"]`) : null;
    return (label?.textContent || control.name || '').replace(/\*/g, '').trim();
}

function initialiseWizard(form) {
    const definition = wizardDefinitions[form.dataset.wizardKind];
    if (!definition || form.dataset.wizardReady) return;
    form.dataset.wizardReady = 'true';
    // Reveal invalid steps before reporting errors on hidden required controls.
    form.noValidate = true;

    const panels = definition.steps.map(([label]) => {
        const panel = document.createElement('section');
        panel.className = 'registration-panel';
        panel.hidden = true;
        panel.innerHTML = `<h2 class="registration-panel__heading">${label}</h2><p class="registration-panel__hint">Complete this section, then continue to the next step.</p>`;
        return panel;
    });
    const mount = document.createElement('div');
    mount.className = 'registration-wizard-mount';
    form.prepend(mount);
    panels.forEach(panel => mount.append(panel));

    const fieldSteps = new Map();
    definition.steps.forEach(([, names], index) => names.forEach(name => fieldSteps.set(name, index)));
    const moved = new Set();
    form.querySelectorAll('[name]').forEach(control => {
        if (control.name === '_token' || !fieldSteps.has(control.name)) return;
        const block = fieldBlock(control, form);
        if (moved.has(block)) return;
        moved.add(block);
        panels[fieldSteps.get(control.name)].append(block);
    });

    const reviewIndex = definition.steps.length - 1;
    const review = document.createElement('div');
    review.className = 'registration-review';
    panels[reviewIndex].append(review);

    if (form.dataset.wizardKind === 'logistics') {
        const rolePanel = panels[2];
        rolePanel.querySelector('.registration-panel__hint').textContent = 'Choose how you want to participate in the SUKI delivery network.';
        const riderUrl = form.dataset.riderUrl;
        const choices = document.createElement('div');
        choices.className = 'registration-role-choice';
        choices.innerHTML = `
            <label><input type="radio" name="registration_role_choice" value="logistics" checked><strong>Logistics Partner</strong><span>Manage logistics operations and delivery coordination.</span></label>
            <label><input type="radio" name="registration_role_choice" value="rider"><strong>Apply as Rider</strong><span>Deliver orders as part of the SUKI rider network.</span></label>`;
        rolePanel.append(choices);
        form.dataset.riderUrl = riderUrl || '';
    }

    const stepper = document.createElement('ol');
    stepper.className = 'registration-stepper';
    stepper.setAttribute('aria-label', 'Registration progress');
    definition.steps.forEach(([label], index) => {
        const step = document.createElement('li');
        step.className = 'registration-step';
        step.innerHTML = `<span class="registration-step__number">${index + 1}</span><span>${label}</span>`;
        stepper.append(step);
    });
    form.prepend(stepper);

    const actions = document.createElement('div');
    actions.className = 'registration-actions';
    actions.innerHTML = '<button type="button" class="registration-actions__back">Back</button><button type="button" class="registration-actions__next">Next</button><button type="submit" class="registration-actions__submit">Submit application</button>';
    mount.append(actions);

    [...form.children].forEach(child => {
        if (
            child !== mount &&
            child !== stepper &&
            !child.matches('input[name="_token"]')
        ) {
            child.hidden = true;
        }
    });

    const back = actions.querySelector('.registration-actions__back');
    const next = actions.querySelector('.registration-actions__next');
    const submit = actions.querySelector('.registration-actions__submit');
    const errors = JSON.parse(form.dataset.errorFields || '[]');
    const errorStep = errors
        .map(name => fieldSteps.get(name))
        .find(Number.isInteger);
    let active = errorStep ?? 0;

    function buildReview() {
        review.replaceChildren();
        form.querySelectorAll('[name]').forEach(control => {
            if (!control.name || control.name === '_token' || /password|terms|role_choice/.test(control.name) || control.type === 'file') return;
            if ((control.type === 'radio' || control.type === 'checkbox') && !control.checked) return;
            const value = control.options ? control.options[control.selectedIndex]?.text : control.value;
            if (!value) return;
            const row = document.createElement('div');
            row.className = 'registration-review__row';
            row.innerHTML = `<span class="registration-review__label"></span><span></span>`;
            row.children[0].textContent = readableLabel(control);
            row.children[1].textContent = value;
            review.append(row);
        });
    }

    function show(index) {
        active = index;
        panels.forEach((panel, i) => { panel.hidden = i !== active; });
        stepper.querySelectorAll('.registration-step').forEach((step, i) => step.classList.toggle('is-active', i === active));
        stepper.querySelectorAll('.registration-step').forEach((step, i) => step.classList.toggle('is-complete', i < active));
        back.hidden = active === 0;
        next.hidden = active === reviewIndex;
        submit.hidden = active !== reviewIndex;
        if (active === reviewIndex) buildReview();
        panels[active].querySelector('input, select, textarea, button')?.focus({ preventScroll: true });
    }

    next.addEventListener('click', () => {
        if (form.dataset.wizardKind === 'logistics' && active === 2 && form.querySelector('[name="registration_role_choice"]:checked')?.value === 'rider') {
            window.location.assign(form.dataset.riderUrl);
            return;
        }
        const invalid = [...panels[active].querySelectorAll('input, select, textarea')].find(control => !control.disabled && !control.checkValidity());
        if (invalid) { invalid.reportValidity(); return; }
        show(Math.min(reviewIndex, active + 1));
    });
    back.addEventListener('click', () => show(Math.max(0, active - 1)));
    form.addEventListener('submit', event => {
        if (active !== reviewIndex) { event.preventDefault(); next.click(); return; }
        const invalid = [...form.querySelectorAll('input, select, textarea')]
            .find(control => !control.disabled && !control.checkValidity());
        if (invalid) {
            event.preventDefault();
            show(fieldSteps.get(invalid.name) ?? 0);
            invalid.reportValidity();
        }
    });
    show(active);
}

document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-registration-wizard]').forEach(initialiseWizard);
});
