
const ncrRegionCode = '1300000000';

async function fetchJson(url) {
    const response = await fetch(url, {
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
    }

    return response.json();
}

function populateSelect(select, items, savedValue, placeholder) {
    if (!select) return;

    select.innerHTML = '';
    select.add(new Option(placeholder, ''));

    items.forEach((item) => {
        const option = new Option(item.name, item.code);
        option.dataset.name = item.name;

        if (
            String(item.code) === String(savedValue || '') ||
            item.name === savedValue
        ) {
            option.selected = true;
        }

        select.add(option);
    });
}

function syncSelectedName(select, input) {
    if (!input) return;

    const option = select?.options[select.selectedIndex];

    input.value = option?.value
        ? option.dataset.name || option.textContent
        : '';
}

export async function initAddressCascade(config) {
    const {
        regionSelect,
        provinceSelect,
        municipalitySelect,
        barangaySelect,
        municipalityField,
        regionHidden,
        provinceHidden,
        municipalityHidden,
        barangayHidden,
        regionNameInput,
        provinceNameInput,
        municipalityNameInput,
        barangayNameInput,
        savedRegion = '',
        savedProvince = '',
        savedMunicipality = '',
        savedBarangay = '',
    } = config;

    if (
        !regionSelect ||
        !provinceSelect ||
        !municipalitySelect ||
        !barangaySelect
    ) {
        return;
    }

    let regionRequestId = 0;
    let provinceRequestId = 0;
    let barangayRequestId = 0;

    // Saved selections are used only during initial restoration.
    let restoring = Boolean(savedRegion);

    function clearField(hidden, nameInput) {
        if (hidden) hidden.value = '';
        if (nameInput) nameInput.value = '';
    }

    function resetBarangay() {
        barangayRequestId++;

        populateSelect(barangaySelect, [], '', 'Select Barangay');
        barangaySelect.disabled = true;

        clearField(barangayHidden, barangayNameInput);
    }

    function resetMunicipality() {
        populateSelect(
            municipalitySelect,
            [],
            '',
            'Select Municipality'
        );

        municipalitySelect.disabled = true;

        clearField(municipalityHidden, municipalityNameInput);
        resetBarangay();
    }

    function resetProvince() {
        provinceRequestId++;

        populateSelect(
            provinceSelect,
            [],
            '',
            'Select Province/City'
        );

        provinceSelect.disabled = true;

        clearField(provinceHidden, provinceNameInput);
        resetMunicipality();
    }

    regionSelect.addEventListener('change', () => {
        const requestId = ++regionRequestId;
        const regionCode = regionSelect.value;

        const shouldRestore = restoring;
        restoring = false;

        if (regionHidden) {
            regionHidden.value = regionCode;
        }

        syncSelectedName(regionSelect, regionNameInput);
        resetProvince();

        if (municipalityField) {
            municipalityField.style.display =
                regionCode === ncrRegionCode ? 'none' : 'block';
        }

        if (!regionCode) return;

        const isNcr = regionCode === ncrRegionCode;

        const url = isNcr
            ? `/api/addresses/regions/${regionCode}/cities`
            : `/api/addresses/regions/${regionCode}/provinces`;

        fetchJson(url)
            .then((items) => {
                if (requestId !== regionRequestId) return;

                populateSelect(
                    provinceSelect,
                    items,
                    shouldRestore ? savedProvince : '',
                    'Select Province/City'
                );

                provinceSelect.disabled = false;

                if (shouldRestore && provinceSelect.value) {
                    provinceSelect.dispatchEvent(
                        new Event('change', { bubbles: true })
                    );
                }
            })
            .catch((error) => {
                if (requestId === regionRequestId) {
                    provinceSelect.disabled = true;
                    console.error('Unable to load provinces:', error);
                }
            });
    });

    provinceSelect.addEventListener('change', () => {
        const requestId = ++provinceRequestId;
        const provinceCode = provinceSelect.value;
        const regionCode = regionSelect.value;

        const shouldRestore =
            Boolean(savedProvince) &&
            (
                provinceCode === savedProvince ||
                provinceSelect.options[
                    provinceSelect.selectedIndex
                ]?.dataset.name === savedProvince
            ) &&
            Boolean(savedRegion) &&
            (
                regionCode === savedRegion ||
                regionSelect.options[
                    regionSelect.selectedIndex
                ]?.dataset.name === savedRegion
            );

        if (provinceHidden) {
            provinceHidden.value = provinceCode;
        }

        syncSelectedName(provinceSelect, provinceNameInput);
        resetMunicipality();

        if (!provinceCode) return;

        if (regionCode === ncrRegionCode) {
            if (municipalityField) {
                municipalityField.style.display = 'none';
            }

            const selectedProvince =
                provinceSelect.options[provinceSelect.selectedIndex];

            if (selectedProvince) {
                populateSelect(
                    municipalitySelect,
                    [{
                        code: selectedProvince.value,
                        name: selectedProvince.dataset.name ||
                            selectedProvince.textContent,
                    }],
                    selectedProvince.value,
                    'Select Municipality'
                );

                municipalitySelect.disabled = false;

                if (municipalityHidden) {
                    municipalityHidden.value = selectedProvince.value;
                }

                syncSelectedName(
                    municipalitySelect,
                    municipalityNameInput
                );
            }

            const barangayId = ++barangayRequestId;

            fetchJson(
                `/api/addresses/cities/${provinceCode}/barangays`
            )
                .then((items) => {
                    if (
                        requestId !== provinceRequestId ||
                        barangayId !== barangayRequestId
                    ) {
                        return;
                    }

                    populateSelect(
                        barangaySelect,
                        items,
                        shouldRestore ? savedBarangay : '',
                        'Select Barangay'
                    );

                    barangaySelect.disabled = false;

                    if (barangaySelect.value) {
                        barangaySelect.dispatchEvent(
                            new Event('change', { bubbles: true })
                        );
                    }
                })
                .catch((error) => {
                    if (barangayId === barangayRequestId) {
                        barangaySelect.disabled = true;
                        console.error('Unable to load barangays:', error);
                    }
                });

            return;
        }

        if (municipalityField) {
            municipalityField.style.display = 'block';
        }

        fetchJson(
            `/api/addresses/provinces/${provinceCode}/municipalities`
        )
            .then((items) => {
                if (requestId !== provinceRequestId) return;

                populateSelect(
                    municipalitySelect,
                    items,
                    shouldRestore ? savedMunicipality : '',
                    'Select Municipality'
                );

                municipalitySelect.disabled = false;

                if (shouldRestore && municipalitySelect.value) {
                    municipalitySelect.dispatchEvent(
                        new Event('change', { bubbles: true })
                    );
                }
            })
            .catch((error) => {
                if (requestId === provinceRequestId) {
                    municipalitySelect.disabled = true;
                    console.error('Unable to load municipalities:', error);
                }
            });
    });

    municipalitySelect.addEventListener('change', () => {
        const municipalityCode = municipalitySelect.value;
        const requestId = ++barangayRequestId;

        const selectedMunicipality =
            municipalitySelect.options[municipalitySelect.selectedIndex];

        const selectedProvince =
            provinceSelect.options[provinceSelect.selectedIndex];

        const selectedRegion =
            regionSelect.options[regionSelect.selectedIndex];

        const shouldRestore =
            Boolean(savedRegion) &&
            Boolean(savedProvince) &&
            Boolean(savedMunicipality) &&
            (
                regionSelect.value === savedRegion ||
                selectedRegion?.dataset.name === savedRegion
            ) &&
            (
                provinceSelect.value === savedProvince ||
                selectedProvince?.dataset.name === savedProvince
            ) &&
            (
                municipalityCode === savedMunicipality ||
                selectedMunicipality?.dataset.name === savedMunicipality
            );

        if (municipalityHidden) {
            municipalityHidden.value = municipalityCode;
        }

        syncSelectedName(
            municipalitySelect,
            municipalityNameInput
        );

        populateSelect(barangaySelect, [], '', 'Select Barangay');
        barangaySelect.disabled = true;

        clearField(barangayHidden, barangayNameInput);

        if (!municipalityCode) return;

        fetchJson(
            `/api/addresses/municipalities/${municipalityCode}/barangays`
        )
            .then((items) => {
                if (requestId !== barangayRequestId) return;

                populateSelect(
                    barangaySelect,
                    items,
                    shouldRestore ? savedBarangay : '',
                    'Select Barangay'
                );

                barangaySelect.disabled = false;

                if (barangaySelect.value) {
                    barangaySelect.dispatchEvent(
                        new Event('change', { bubbles: true })
                    );
                }
            })
            .catch((error) => {
                if (requestId === barangayRequestId) {
                    barangaySelect.disabled = true;
                    console.error('Unable to load barangays:', error);
                }
            });
    });

    barangaySelect.addEventListener('change', () => {
        if (barangayHidden) {
            barangayHidden.value = barangaySelect.value;
        }

        syncSelectedName(barangaySelect, barangayNameInput);
    });

    try {
        const regions = await fetchJson('/api/addresses/regions');

        populateSelect(
            regionSelect,
            regions,
            savedRegion,
            'Select Region'
        );

        syncSelectedName(regionSelect, regionNameInput);

        if (regionHidden) {
            regionHidden.value = regionSelect.value;
        }

        if (savedRegion && regionSelect.value) {
            regionSelect.dispatchEvent(
                new Event('change', { bubbles: true })
            );
        }
    } catch (error) {
        populateSelect(regionSelect, [], '', 'Select Region');
        console.error('Unable to load regions:', error);
    }
}
