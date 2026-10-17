    const fields = {
        region: document.getElementById('profileRegion'),
        province: document.getElementById('profileProvince'),
        municipality: document.getElementById('profileMunicipality'),
        barangay: document.getElementById('profileBarangay'),
    };
    const saved = @json($initialAddressValues);
    const addressStatus = document.getElementById('profileAddressStatus');
    const addressForm = fields.region.closest('form');
    const addressSubmit = addressForm.querySelector('button[type="submit"]');
    let addressVersion = 0;

    function populate(name, items, selected = '') {
        const select = fields[name];
        select.replaceChildren(new Option('Select ' + name, ''));
        items.forEach(item => select.add(new Option(item.name, item.code, false, item.code === selected || item.name === selected)));
        select.disabled = !items.length;
        if (name === 'province') select.required = items.length > 0;
    }
    async function locations(path) {
        const response = await fetch(@json(url('/api/psgc')) + '/' + path, {headers: {Accept: 'application/json'}});
        if (!response.ok) throw new Error('Location data is unavailable. Reload the page to try again.');
        return response.json();
    }
    async function loadAddress(from, initial = false) {
        const version = ++addressVersion;
        addressSubmit.disabled = true;
        addressStatus.textContent = 'Loading locations...';
        const order = ['region', 'province', 'municipality', 'barangay'];
        const start = order.indexOf(from);
        const selected = Object.fromEntries(order.map(name => [name, initial ? saved[name] : fields[name].value]));
        order.slice(start).forEach(name => populate(name, []));
        try {
            if (from === 'region') {
                const items = await locations('regions');
                if (version !== addressVersion) return;
                populate('region', items, selected.region);
            }
            if (start <= 1 && fields.region.value) {
                const items = await locations('regions/' + fields.region.value + '/provinces');
                if (version !== addressVersion) return;
                populate('province', items, initial ? selected.province : '');
            }
            if (start <= 2 && fields.region.value && (fields.province.disabled || fields.province.value)) {
                const path = fields.province.disabled ? 'regions/' + fields.region.value : 'provinces/' + fields.province.value;
                const items = await locations(path + '/cities-municipalities');
                if (version !== addressVersion) return;
                populate('municipality', items, initial ? selected.municipality : '');
            }
            if (fields.municipality.value) {
                const items = await locations('cities-municipalities/' + fields.municipality.value + '/barangays');
                if (version !== addressVersion) return;
                populate('barangay', items, initial ? selected.barangay : '');
            }
            addressStatus.textContent = '';
        } catch (error) {
            if (version !== addressVersion) return;
            addressStatus.textContent = error.message;
            return;
        }
        if (version === addressVersion) addressSubmit.disabled = false;
    }
    fields.region.addEventListener('change', () => loadAddress('province'));
    fields.province.addEventListener('change', () => loadAddress('municipality'));
    fields.municipality.addEventListener('change', () => loadAddress('barangay'));
    loadAddress('region', true);
