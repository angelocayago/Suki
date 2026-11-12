function isCode(value) {
    return typeof value === 'string' && /^\d+$/.test(value);
}

export function initializeSortingZoneAddressForms(root = document) {
    const forms = root.querySelectorAll('[data-sorting-zone-area-form]');
    forms.forEach((form) => {
        const region = form.querySelector('[data-address-region]');
        const province = form.querySelector('[data-address-province]');
        const city = form.querySelector('[data-address-city]');
        const barangay = form.querySelector('[data-address-barangay]');
        const cityWide = form.querySelector('[data-address-city-wide]');
        const barangayWrap = form.querySelector('[data-address-barangay-wrap]');
        const status = form.querySelector('[data-address-status]');
        const error = form.querySelector('[data-address-error]');
        const retry = form.querySelector('[data-address-retry]');
        const save = form.querySelector('[data-address-save]');
        if (!region || !province || !city || !barangay || !cityWide || !status || !save || form.dataset.initialized) return;
        form.dataset.initialized = 'true';

        let version = 0;
        let busy = false;
        let needsProvince = true;
        const initial = {
            region: form.dataset.initialRegion || '',
            province: form.dataset.initialProvince || '',
            city: form.dataset.initialCity || '',
            barangay: form.dataset.initialBarangay || '',
            cityWide: form.dataset.initialCityWide === '1',
        };
        const valid = (select) => !select.disabled && isCode(select.value) && Array.from(select.options).some((option) => option.value === select.value);
        const complete = () => valid(region) && (!needsProvince || valid(province)) && valid(city) && (cityWide.checked || valid(barangay));
        const setBusy = (value) => {
            busy = value;
            form.setAttribute('aria-busy', String(value));
            save.disabled = value || !complete();
        };
        const reset = (select, label) => {
            select.replaceChildren(new Option(label, ''));
            select.disabled = true;
            select.value = '';
        };
        const fill = (select, rows, label, selected = '') => {
            reset(select, label);
            rows.forEach((row) => select.add(new Option(row.name, row.code)));
            select.disabled = rows.length === 0;
            select.value = rows.some((row) => row.code === selected) ? selected : '';
        };
        const updateCityWide = () => {
            const enabled = valid(city);
            cityWide.disabled = !enabled;
            barangay.disabled = !enabled || cityWide.checked;
            if (barangayWrap) barangayWrap.hidden = cityWide.checked;
            barangay.required = enabled && !cityWide.checked;
            if (cityWide.checked) barangay.value = '';
            save.disabled = busy || !complete();
        };
        const request = async (path) => {
            const response = await fetch(`/address/${path}`, {headers: {Accept: 'application/json'}});
            if (!response.ok) throw new Error('Address lookup failed');
            const rows = await response.json();
            if (!Array.isArray(rows) || rows.some((row) => !row || typeof row.name !== 'string' || !isCode(row.code))) throw new Error('Invalid address choices');
            return rows;
        };
        const run = async (action) => {
            const token = ++version;
            if (error) error.hidden = true;
            setBusy(true);
            status.textContent = 'Loading verified address choices…';
            try {
                await action(token);
                if (token === version) status.textContent = cityWide.checked ? 'Whole city/municipality selected.' : (complete() ? 'Location selected.' : 'Select the remaining locations.');
            } catch (_) {
                if (token === version) {
                    status.textContent = 'Location choices could not be loaded.';
                    if (error) error.hidden = false;
                }
            } finally {
                if (token === version) setBusy(false);
            }
        };
        const loadBarangays = async (token, selected = '') => {
            const rows = await request(`cities-municipalities/${encodeURIComponent(city.value)}/barangays`);
            if (token !== version) return;
            fill(barangay, rows, 'Select barangay', selected);
            updateCityWide();
        };
        const loadCities = async (path, token, selected = '', selectedBarangay = '') => {
            const rows = await request(path);
            if (token !== version) return;
            fill(city, rows, 'Select city / municipality', selected);
            if (valid(city)) await loadBarangays(token, selectedBarangay);
        };
        const loadProvinces = async (token, selectedProvince = '', selectedCity = '', selectedBarangay = '') => {
            const rows = await request(`regions/${encodeURIComponent(region.value)}/provinces`);
            if (token !== version) return;
            needsProvince = rows.length > 0;
            province.required = needsProvince;
            if (needsProvince) {
                fill(province, rows, 'Select province', selectedProvince);
                if (valid(province)) await loadCities(`provinces/${encodeURIComponent(province.value)}/cities-municipalities`, token, selectedCity, selectedBarangay);
            } else {
                fill(province, [], 'Not applicable', '');
                province.disabled = true;
                await loadCities(`regions/${encodeURIComponent(region.value)}/cities-municipalities`, token, selectedCity, selectedBarangay);
            }
        };
        const initialize = (values = initial) => run(async (token) => {
            reset(region, 'Loading regions…');
            reset(province, 'Select region first');
            reset(city, 'Select region or province first');
            reset(barangay, 'Select city first');
            cityWide.checked = false;
            cityWide.disabled = true;
            const rows = await request('regions');
            if (token !== version) return;
            fill(region, rows, 'Select region', values.region);
            if (valid(region)) await loadProvinces(token, values.province, values.city, values.barangay);
            if (token === version && valid(city) && values.cityWide) cityWide.checked = true;
            updateCityWide();
        });

        if (retry) retry.addEventListener('click', () => initialize());

        region.addEventListener('change', () => {
            reset(province, 'Select region first'); reset(city, 'Select region first'); reset(barangay, 'Select city first');
            cityWide.checked = false; updateCityWide();
            run(async (token) => { if (valid(region)) await loadProvinces(token); });
        });
        province.addEventListener('change', () => {
            reset(city, 'Select province first'); reset(barangay, 'Select city first'); updateCityWide();
            run(async (token) => { if (valid(province)) await loadCities(`provinces/${encodeURIComponent(province.value)}/cities-municipalities`, token); });
        });
        city.addEventListener('change', () => {
            reset(barangay, 'Select city first'); updateCityWide();
            run(async (token) => { if (valid(city)) await loadBarangays(token); });
        });
        cityWide.addEventListener('change', updateCityWide);
        barangay.addEventListener('change', () => { save.disabled = busy || !complete(); });
        initialize();
    });
}

if (typeof document !== 'undefined') initializeSortingZoneAddressForms();
