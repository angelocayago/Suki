import { initAddressCascade } from '../shared/address-cascade.js';

document.addEventListener('DOMContentLoaded', function () {
  function parseJsonElement(id) {
    const el = document.getElementById(id);
    if (!el) return null;
    try {
      return JSON.parse(el.textContent);
    } catch {
      return null;
    }
  }

  const data = parseJsonElement('seller-account-data');

  const birthdayInput = document.getElementById('birthday');
  const ageInput = document.getElementById('age');

  function updateAge() {
    if (!birthdayInput || !ageInput || !birthdayInput.value) {
      if (ageInput) ageInput.value = '';
      return;
    }

    const parts = birthdayInput.value.split('-').map(Number);
    if (parts.length !== 3 || parts.some(Number.isNaN)) {
      ageInput.value = '';
      return;
    }

    const [year, month, day] = parts;
    const today = new Date();
    let age = today.getFullYear() - year;
    const monthDiff = (today.getMonth() + 1) - month;

    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < day)) {
      age--;
    }

    ageInput.value = age >= 0 ? age : '';
  }

  if (birthdayInput && ageInput) {
    birthdayInput.addEventListener('change', updateAge);
    updateAge();
  }

  const regionSelect = document.getElementById('region');
  const provinceSelect = document.getElementById('province');
  const municipalitySelect = document.getElementById('municipality');
  const barangaySelect = document.getElementById('barangay');
  const municipalityField = document.getElementById('municipality-field');
  const regionHidden = document.getElementById('region-hidden');
  const provinceHidden = document.getElementById('province-hidden');
  const municipalityHidden = document.getElementById('municipality-hidden');
  const barangayHidden = document.getElementById('barangay-hidden');

  const saved = data?.saved ?? {
    region: regionSelect?.dataset.saved ?? '',
    province: provinceSelect?.dataset.saved ?? '',
    municipality: municipalitySelect?.dataset.saved ?? '',
    barangay: barangaySelect?.dataset.saved ?? '',
  };

  initAddressCascade({
    regionSelect,
    provinceSelect,
    municipalitySelect,
    barangaySelect,
    municipalityField,
    regionHidden,
    provinceHidden,
    municipalityHidden,
    barangayHidden,
    savedRegion: saved.region,
    savedProvince: saved.province,
    savedMunicipality: saved.municipality,
    savedBarangay: saved.barangay,
  });
});