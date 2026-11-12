const test = require('node:test');
const assert = require('node:assert/strict');

class FakeOption {
  constructor(text, value) { this.text = text; this.value = value; }
}

class FakeElement {
  constructor() {
    this.options = [];
    this.listeners = {};
    this.dataset = {};
    this.disabled = false;
    this.checked = false;
    this.required = false;
    this.hidden = false;
    this.attributes = {};
    this.textContent = '';
    this._value = '';
  }
  set value(value) { this._value = this.options.some((option) => option.value === value) ? value : ''; }
  get value() { return this._value; }
  add(option) { this.options.push(option); }
  replaceChildren(...options) { this.options = options; this._value = ''; }
  addEventListener(type, callback) { this.listeners[type] = callback; }
  setAttribute(name, value) { this.attributes[name] = value; }
  async fire(type) { await this.listeners[type]?.(); }
}

test('dependent PSGC dropdowns load names in hierarchy and explicit city-wide selection unlocks save', async () => {
  global.Option = FakeOption;
  const region = new FakeElement();
  const province = new FakeElement();
  const city = new FakeElement();
  const barangay = new FakeElement();
  const cityWide = new FakeElement();
  const status = new FakeElement();
  const error = new FakeElement();
  const save = new FakeElement();
  const barangayWrap = new FakeElement();
  const form = new FakeElement();
  form.querySelector = (selector) => ({
    '[data-address-region]': region,
    '[data-address-province]': province,
    '[data-address-city]': city,
    '[data-address-barangay]': barangay,
    '[data-address-city-wide]': cityWide,
    '[data-address-barangay-wrap]': barangayWrap,
    '[data-address-status]': status,
    '[data-address-error]': error,
    '[data-address-save]': save,
    '[data-address-retry]': null,
  })[selector] ?? null;
  const root = { querySelectorAll: () => [form] };
  const responses = {
    '/address/regions': [{code: '0400000000', name: 'Region IV-A / CALABARZON'}],
    '/address/regions/0400000000/provinces': [{code: '0403400000', name: 'Laguna'}],
    '/address/provinces/0403400000/cities-municipalities': [{code: '0403426000', name: 'Santa Cruz'}],
    '/address/cities-municipalities/0403426000/barangays': [{code: '0403426005', name: 'Duhat'}],
  };
  const requested = [];
  global.fetch = async (url) => {
    requested.push(url);
    return {ok: true, json: async () => responses[url] ?? []};
  };
  const {initializeSortingZoneAddressForms} = await import('../../resources/js/sorting-zone-address.js');
  initializeSortingZoneAddressForms(root);
  const settle = async () => { for (let i = 0; i < 10; i++) await new Promise((resolve) => setImmediate(resolve)); };
  await settle();
  assert.equal(region.options[1].text, 'Region IV-A / CALABARZON');

  region.value = '0400000000';
  await region.fire('change');
  await settle();
  assert.equal(province.options[1].text, 'Laguna');
  province.value = '0403400000';
  await province.fire('change');
  await settle();
  assert.equal(city.options[1].text, 'Santa Cruz');
  city.value = '0403426000';
  await city.fire('change');
  await settle();
  assert.equal(barangay.options[1].text, 'Duhat');
  assert.equal(save.disabled, true);

  cityWide.checked = true;
  await cityWide.fire('change');
  assert.equal(barangay.disabled, true);
  assert.equal(barangayWrap.hidden, true);
  assert.equal(save.disabled, false);
  assert.deepEqual(requested, [
    '/address/regions',
    '/address/regions/0400000000/provinces',
    '/address/provinces/0403400000/cities-municipalities',
    '/address/cities-municipalities/0403426000/barangays',
  ]);
});
