<script>
(() => {
    const form = document.getElementById('hubLocationForm');
    if (!form || form.dataset.initialized) return;
    form.dataset.initialized = 'true';
    const region = document.getElementById('hubRegion'), province = document.getElementById('hubProvince'), city = document.getElementById('hubCity'), barangay = document.getElementById('hubBarangay'), postal = document.getElementById('hubPostalCode');
    const save = document.getElementById('hubSave'), status = document.getElementById('hubLocationStatus'), error = document.getElementById('hubLocationError'), errorText = document.getElementById('hubLocationErrorText'), retry = document.getElementById('hubLocationRetry');
    let version = 0, busy = false, needsProvince = true;
    let restore = {region: form.dataset.initialRegion || '', province: form.dataset.initialProvince || '', city: form.dataset.initialCity || '', barangay: form.dataset.initialBarangay || ''};
    const selected = () => ({region: region.value, province: province.value, city: city.value, barangay: barangay.value});
    const valid = field => !field.disabled && /^\d+$/.test(field.value) && Array.from(field.options).some(option => option.value === field.value);
    const complete = () => valid(region) && (!needsProvince || valid(province)) && valid(city) && valid(barangay);
    function sync() { save.disabled = busy || !complete(); form.setAttribute('aria-busy', String(busy)); }
    function reset(field, label) { field.innerHTML = ''; field.add(new Option(label, '')); field.disabled = true; }
    function fill(field, rows, label, value = '') {
        reset(field, label);
        rows.forEach(row => field.add(new Option(row.name, row.code)));
        field.disabled = rows.length === 0;
        field.value = rows.some(row => row.code === value) ? value : '';
    }
    async function get(path, list = true) {
        const response = await fetch('/address/' + path, {headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Location request failed');
        const data = await response.json();
        if (list && (!Array.isArray(data) || data.some(row => !row || typeof row.name !== 'string' || typeof row.code !== 'string' || !/^\d+$/.test(row.code)))) throw new Error('Invalid location response');
        if (list && !data.length && !path.endsWith('/provinces')) throw new Error('No location choices available');
        return data;
    }
    async function loadBarangays(token, values) {
        const code = city.value;
        const [rows, zip] = await Promise.all([
            get('cities-municipalities/' + encodeURIComponent(code) + '/barangays'),
            get('cities-municipalities/' + encodeURIComponent(code) + '/postal-code', false).catch(() => null),
        ]);
        if (token !== version) return;
        fill(barangay, rows, 'Select barangay', values.barangay);
        postal.value = typeof zip?.postal_code === 'string' ? zip.postal_code : '';
        if (!postal.value) status.textContent = 'Postal code is unavailable. Select a valid barangay; the server will resolve the location again when saving.';
    }
    async function loadCities(path, token, values) {
        const rows = await get(path);
        if (token !== version) return;
        fill(city, rows, 'Select city / municipality', values.city);
        if (valid(city)) await loadBarangays(token, values);
    }
    async function loadProvinces(token, values) {
        const code = region.value, rows = await get('regions/' + encodeURIComponent(code) + '/provinces');
        if (token !== version) return;
        needsProvince = rows.length > 0; province.required = needsProvince;
        if (needsProvince) {
            fill(province, rows, 'Select province', values.province);
            if (valid(province)) await loadCities('provinces/' + encodeURIComponent(province.value) + '/cities-municipalities', token, values);
        } else {
            reset(province, 'No province required');
            await loadCities('regions/' + encodeURIComponent(code) + '/cities-municipalities', token, values);
        }
    }
    async function run(action) {
        const token = ++version;
        busy = true; error.hidden = true; status.textContent = 'Select the location from the verified dropdown choices.'; sync();
        try { await action(token); }
        catch (_) {
            if (token === version) { errorText.textContent = 'Location choices could not be loaded. Check your connection and retry; no hub has been saved.'; error.hidden = false; }
        } finally { if (token === version) { busy = false; sync(); } }
    }
    function initialize(values) {
        restore = {...values};
        reset(region, 'Loading regions...'); reset(province, 'Select region first'); reset(city, 'Select province or region first'); reset(barangay, 'Select city first'); postal.value = '';
        return run(async token => {
            const rows = await get('regions');
            if (token !== version) return;
            fill(region, rows, 'Select region', values.region);
            if (valid(region)) await loadProvinces(token, values);
        });
    }
    region.addEventListener('change', () => {
        reset(province, 'Select region first'); reset(city, 'Select province or region first'); reset(barangay, 'Select city first'); postal.value = ''; needsProvince = true;
        return run(async token => { if (valid(region)) await loadProvinces(token, {}); });
    });
    province.addEventListener('change', () => {
        reset(city, 'Select province first'); reset(barangay, 'Select city first'); postal.value = '';
        return run(async token => { if (valid(province)) await loadCities('provinces/' + encodeURIComponent(province.value) + '/cities-municipalities', token, {}); });
    });
    city.addEventListener('change', () => {
        reset(barangay, 'Select city first'); postal.value = '';
        return run(async token => { if (valid(city)) await loadBarangays(token, {}); });
    });
    barangay.addEventListener('change', sync);
    retry.addEventListener('click', () => initialize(valid(region) ? selected() : restore));
    form.addEventListener('submit', event => {
        if (busy || !complete()) { event.preventDefault(); errorText.textContent = 'Select a valid region, province when applicable, city and barangay before saving.'; error.hidden = false; }
    });
    initialize(restore);
})();
</script>
