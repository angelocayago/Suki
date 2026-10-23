const clean = value => String(value).trim().replace(/\s+/gu, ' ');
export const parseOptionValues = value => String(value).split(',').map(clean);
export function combinationKey(options) {
    return JSON.stringify(Object.entries(options).map(([name, value]) => [clean(name).toLowerCase(), clean(value).toLowerCase()])
        .sort(([a], [b]) => a < b ? -1 : a > b ? 1 : 0));
}
export function combinations(groups) {
    if (!Array.isArray(groups) || !groups.length || groups.length > 3) throw new Error('Use 1 to 3 option groups.');
    const names = new Set();
    let result = [{}];
    groups.forEach(group => {
        const name = clean(group.name);
        const values = group.values.map(clean);
        if (!name || name.length > 50 || names.has(name.toLowerCase()) || !values.length || values.length > 10 || values.some(value => !value || value.length > 50) || new Set(values.map(v => v.toLowerCase())).size !== values.length) {
            throw new Error('Use unique, nonempty names and values, up to 50 characters each and 10 values per group.');
        }
        names.add(name.toLowerCase());
        if (result.length * values.length > 50) throw new Error('Use at most 50 sellable combinations.');
        result = result.flatMap(options => values.map(value => Object.fromEntries([...Object.entries(options), [name, value]])));
    });
    return result;
}
export function reconcileRows(options, rows) {
    const saved = new Map(rows.map(row => [combinationKey(row.options), row]));
    return options.map(selection => ({ ...(saved.get(combinationKey(selection)) || { price: '', stock: 0, is_active: true }), options: selection }));
}

export function initVariantEditor(root, doc = document) {
    if (root.dataset.initialized === '1') return;
    root.dataset.initialized = '1';
    const state = JSON.parse(root.querySelector('[data-variant-state]').textContent);
    const persisted = root.dataset.persisted === '1';
    const type = doc.getElementById('productType');
    const fields = root.querySelector('[data-variable-fields]');
    const groupContainer = root.querySelector('[data-option-groups]');
    const rowContainer = root.querySelector('[data-variant-rows]');
    const message = root.querySelector('[data-variant-status]');
    const generate = root.querySelector('[data-generate]');
    const addGroup = root.querySelector('[data-add-group]');
    const table = root.querySelector('[data-variant-table]');
    let groups = (Array.isArray(state.groups) ? state.groups : [])
        .filter(g => g && typeof g.name === 'string' && Array.isArray(g.values))
        .map(g => ({ name: g.name, values: g.values.filter(v => typeof v === 'string') }));
    let rows = (Array.isArray(state.rows) ? state.rows : [])
        .filter(r => r && (r.options || r.selections))
        .map(row => ({ ...row, options: row.options || Object.fromEntries(groups.map((g, i) => [g.name, row.selections[i]])) }));
    const cache = new Map(rows.map(row => [combinationKey(row.options), row]));
    const savedGroups = Array.isArray(state.savedGroups) ? state.savedGroups : [];
    let dirty = false;
    const inputClass = 'min-w-0 w-full rounded-xl border border-[#DDE6E1] px-3 py-2 text-sm';
    const buttonClass = 'rounded-lg border border-[#DDE6E1] px-3 py-2 text-xs font-semibold text-[#1F6F5B]';
    function element(tag, text, className) {
        const el = doc.createElement(tag);
        if (text !== null) el.textContent = text;
        if (className) el.className = className;
        return el;
    }
    function button(text, action) {
        const el = element('button', text, buttonClass);
        el.type = 'button'; el.addEventListener('click', action);
        return el;
    }
    function field(label, name, value, inputType = 'text', onInput = () => {}) {
        const wrapper = element('label', null, 'block min-w-0 space-y-1 text-xs text-[#34483F]');
        wrapper.append(element('span', label));
        const input = element('input', null, inputClass);
        input.type = inputType;
        if (name) input.name = name;
        input.value = value ?? '';
        input.addEventListener('input', () => onInput(input.value));
        wrapper.append(input);
        return { wrapper, input };
    }
    function hidden(parent, name, value) {
        const input = element('input', null);
        input.type = 'hidden'; input.name = name; input.value = value;
        parent.append(input);
        return input;
    }
    function changed() {
        dirty = groups.length > 0;
        message.textContent = groups.length ? 'Generate combinations after changing options. Unchanged combinations keep their entries; removed combinations disappear from the table.' : '';
        if (!groups.length && !persisted) {
            rows.forEach(row => cache.set(combinationKey(row.options), row));
            rows = []; renderRows();
        }
        syncMode();
    }
    function syncValues(container, group, index) {
        container.replaceChildren();
        group.values.forEach(value => hidden(container, `option_groups[${index}][values][]`, value));
    }
    function renderGroups() {
        groupContainer.replaceChildren();
        groups.forEach((group, index) => {
            const card = element('div', null, persisted
                ? 'min-w-0 space-y-3 rounded-xl border border-[#DDE6E1] p-3'
                : 'grid min-w-0 items-end gap-3 rounded-xl border border-[#DDE6E1] p-3 sm:grid-cols-[1fr_2fr_auto]');
            const name = field(`Option Name ${index + 1}`, `option_groups[${index}][name]`, group.name, 'text', value => { group.name = value; changed(); });
            name.input.maxLength = 50; name.input.required = true; name.input.readOnly = persisted;
            card.append(name.wrapper);
            if (!persisted) {
                const values = element('div', null);
                values.hidden = true;
                const control = field('Option Values (comma-separated)', null, group.values.join(', '), 'text', next => {
                    group.values = parseOptionValues(next); syncValues(values, group, index); changed();
                });
                control.input.maxLength = 518; control.input.required = true;
                control.input.placeholder = 'Small, Medium, Large';
                syncValues(values, group, index);
                card.append(control.wrapper, values);
                card.append(button('Remove Option', () => { groups.splice(index, 1); renderGroups(); changed(); }));
            } else {
                // Saved labels remain individually locked; only new values are removable.
                group.values.forEach((value, valueIndex) => {
                    const line = element('div', null, 'flex items-end gap-2');
                    const control = field(`Value ${valueIndex + 1}`, `option_groups[${index}][values][]`, value, 'text', next => { group.values[valueIndex] = next; changed(); });
                    control.input.maxLength = 50; control.input.required = true;
                    const locked = savedGroups.some(g => g.name === group.name && g.values.includes(value));
                    control.input.readOnly = locked; line.append(control.wrapper);
                    if (!locked) line.append(button('Remove', () => { group.values.splice(valueIndex, 1); renderGroups(); changed(); }));
                    card.append(line);
                });
                const addValue = button('Add value', () => {
                    if (group.values.length >= 10) return;
                    group.values.push(''); renderGroups(); changed();
                });
                addValue.dataset.addValue = '1';
                addValue.dataset.groupIndex = String(index);
                card.append(addValue);
            }
            groupContainer.append(card);
        });
        syncMode();
    }
    function renderRows() {
        rowContainer.replaceChildren();
        rows.forEach((row, index) => {
            const tr = element('tr', null, 'border-t border-[#DDE6E1] align-top');
            const identity = element('td', null, 'min-w-40 p-3');
            const label = Object.values(row.options).join(' / ');
            identity.append(element('span', label, 'break-words font-semibold text-[#173F35]'));
            if (row.id) hidden(identity, `variant_rows[${index}][id]`, row.id);
            // Indexed selections keep punctuation and special object keys out of request keys.
            Object.values(row.options).forEach(value => hidden(identity, `variant_rows[${index}][selections][]`, value));
            const price = field(`Price (₱) — ${label}`, `variant_rows[${index}][price]`, row.price, 'number', value => { row.price = value; });
            price.input.min = '0.01'; price.input.max = '42949672.95'; price.input.step = '0.01'; price.input.required = true;
            const stock = field(`Stock — ${label}`, `variant_rows[${index}][stock]`, row.stock, 'number', value => { row.stock = value; });
            price.wrapper.children[0].className = 'sr-only';
            stock.wrapper.children[0].className = 'sr-only';
            stock.input.min = '0'; stock.input.max = '4294967295'; stock.input.step = '1'; stock.input.required = true;
            const priceCell = element('td', null, 'min-w-36 p-3'); priceCell.append(price.wrapper);
            const stockCell = element('td', null, 'min-w-28 p-3'); stockCell.append(stock.wrapper);
            const sku = element('td', row.sku || 'Generated after saving', 'break-all p-3 text-xs text-[#728078]');
            const availability = element('td', null, 'min-w-40 p-3');
            const active = element('label', null, 'flex items-center gap-2 text-xs');
            const toggle = element('input', null);
            toggle.type = 'checkbox'; toggle.checked = row.is_active === true || row.is_active === 1 || row.is_active === '1';
            const stateInput = hidden(availability, `variant_rows[${index}][is_active]`, toggle.checked ? '1' : '0');
            toggle.addEventListener('change', () => { row.is_active = toggle.checked; stateInput.value = toggle.checked ? '1' : '0'; });
            active.append(toggle, element('span', 'Active when product is active and stock is above zero'));
            availability.append(active);
            tr.append(identity, priceCell, stockCell, sku, availability); rowContainer.append(tr);
        });
        syncMode();
    }
    function syncMode() {
        const configurable = !persisted || type.value === 'variable';
        const variable = configurable && rows.length > 0 && groups.length > 0;
        if (!persisted) type.value = variable ? 'variable' : 'simple';
        fields.hidden = !configurable;
        fields.querySelectorAll('input, select, button').forEach(el => { el.disabled = !configurable; });
        groupContainer.querySelectorAll('button').forEach(el => {
            if (el.dataset.addValue) el.disabled = !configurable || groups[Number(el.dataset.groupIndex)].values.length >= 10;
        });
        rowContainer.querySelectorAll('input').forEach(el => { el.disabled = !variable; });
        if (table) table.hidden = !variable;
        generate.hidden = !groups.length;
        try { combinations(groups); generate.disabled = !configurable; }
        catch (error) {
            generate.disabled = true;
            if (groups.length && configurable) message.textContent = error.message;
        }
        if (addGroup) addGroup.disabled = !configurable || groups.length >= 3;
        doc.querySelectorAll('[data-simple-config], [data-simple-sale]').forEach(wrapper => {
            wrapper.hidden = variable;
            wrapper.querySelectorAll('input').forEach(el => {
                const saleEnabled = Boolean(doc.getElementById('on_sale')?.checked);
                el.disabled = variable || (el.id === 'sale_price' && !saleEnabled);
                if (el.id === 'sale_price') el.required = !variable && saleEnabled;
            });
        });
    }
    addGroup?.addEventListener('click', () => {
        if (groups.length >= 3) return;
        groups.push({ name: '', values: [''] }); renderGroups(); changed();
        groupContainer.children[groups.length - 1]?.querySelector('input')?.focus();
    });
    generate.addEventListener('click', () => {
        try {
            const generated = combinations(groups);
            rows.forEach(row => cache.set(combinationKey(row.options), row));
            const nextKeys = new Set(generated.map(combinationKey));
            const removed = rows.filter(row => !nextKeys.has(combinationKey(row.options))).length;
            rows = reconcileRows(generated, [...cache.values()]);
            dirty = false; renderRows();
            message.textContent = `${rows.length} combinations. ${removed ? `${removed} removed from the table. ` : ''}Unchanged combinations keep their price and stock. Changes take effect when saved.`;
        } catch (error) { message.textContent = error.message; }
    });
    root.closest?.('form')?.addEventListener('submit', event => {
        if (dirty || (groups.length && !rows.length)) {
            event.preventDefault(); event.stopImmediatePropagation();
            message.textContent = 'Generate valid variant combinations before saving, or remove all options to save a simple product.';
            if (!generate.disabled) generate.focus();
        }
    }, true);
    renderGroups(); renderRows(); syncMode();
}

if (typeof document !== 'undefined') {
    document.addEventListener('DOMContentLoaded', () => document.querySelectorAll('[data-variant-editor]').forEach(root => initVariantEditor(root)));
}
