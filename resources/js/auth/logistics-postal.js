const normalize = (value) => String(value || '')
    .toLowerCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\bsta\.?\b/g, 'santa')
    .replace(/\bsto\.?\b/g, 'santo')
    .replace(/\bcity of\b/g, '')
    .replace(/\bcity\b/g, '')
    .replace(/[^a-z0-9]/g, '');

function findPostalCodes(data, province, city) {
    const provinceKey = normalize(province);
    const cityKey = normalize(city);

    if (!provinceKey || !cityKey) return [];

    for (const provinces of Object.values(data)) {
        for (const [name, locations] of Object.entries(provinces)) {
            if (normalize(name) !== provinceKey) continue;

            for (const [location, codes] of Object.entries(locations)) {
                if (normalize(location) === cityKey) {
                    return [...new Set(codes.map(String))];
                }
            }
        }
    }

    return [];
}

export async function initLogisticsPostal() {
    const province = document.getElementById('business_province');
    const city = document.getElementById('business_city');
    const provinceSelect = document.getElementById('business_province_select');
    const citySelect = document.getElementById('business_city_select');
    const regionSelect = document.getElementById('business_region_select');
    const postal = document.getElementById('business_postal_code');

    if (!province || !city || !postal || !citySelect) return;

    let data;

    try {
        const response = await fetch('/data/ph-zipcodes.json');
        if (!response.ok) throw new Error('Postal dataset unavailable');
        data = await response.json();
    } catch (error) {
        console.warn('Postal lookup unavailable:', error);
        return;
    }

    let lastAutoValue = '';

    const updatePostal = () => {
        const codes = findPostalCodes(data, province.value, city.value);

        if (codes.length === 1) {
            if (!postal.value || postal.value === lastAutoValue) {
                postal.value = codes[0];
                lastAutoValue = codes[0];
            }
        } else if (postal.value === lastAutoValue) {
            postal.value = '';
            lastAutoValue = '';
        }
    };

    regionSelect?.addEventListener('change', () => {
        if (postal.value === lastAutoValue) {
            postal.value = '';
            lastAutoValue = '';
        }
    });

    provinceSelect?.addEventListener('change', () => {
        if (postal.value === lastAutoValue) {
            postal.value = '';
            lastAutoValue = '';
        }
        queueMicrotask(updatePostal);
    });

    citySelect.addEventListener('change', () => {
        queueMicrotask(updatePostal);
    });

    updatePostal();
}