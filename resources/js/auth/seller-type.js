export function initSellerType() {
    const roleSelect = document.getElementById('role');
    const sellerType = document.getElementById('seller_type');

    if (!roleSelect || !sellerType) return;

    const fields = [
        {
            container: 'seller-dti-number-field',
            input: 'dti_registration_number',
            types: ['sole_proprietorship'],
        },
        {
            container: 'seller-dti-certificate-field',
            input: 'dti_certificate',
            types: ['sole_proprietorship'],
        },
        {
            container: 'seller-sec-number-field',
            input: 'seller_sec_registration_number',
            types: ['partnership', 'corporation'],
        },
        {
            container: 'seller-sec-certificate-field',
            input: 'seller_sec_certificate',
            types: ['partnership', 'corporation'],
        },
        {
            container: 'seller-bir-field',
            input: 'seller_bir_form_2303',
            types: ['corporation'],
        },
    ];

    function updateFields() {
        const isSeller = roleSelect.value === 'seller';
        const selectedType = sellerType.value;

        fields.forEach(({ container, input, types }) => {
            const wrapper = document.getElementById(container);
            const element = document.getElementById(input);

            if (!wrapper || !element) return;

            const active = isSeller && types.includes(selectedType);

            wrapper.classList.toggle('d-none', !active);
            element.required = active;
            element.disabled = !active;
        });
    }

    roleSelect.addEventListener('change', updateFields);
    sellerType.addEventListener('change', updateFields);

    updateFields();
}