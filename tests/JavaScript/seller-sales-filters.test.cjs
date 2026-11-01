const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/partials/sales-filter-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

// Interaction checks run the actual script against a small DOM double.
// They do not substitute for a visual browser check.
function harness(isMobile = false) {
    const document = { activeElement:null, handlers:{}, addEventListener(type, callback) {
        if (type === 'DOMContentLoaded') callback(); else this.handlers[type] = callback;
    } };
    function element() {
        return {
            value:'', name:'', type:'', disabled:false, hidden:false, inert:false,
            dataset:{}, style:{}, children:[], handlers:{}, attributes:{},
            addEventListener(type, callback) { (this.handlers[type] ??= []).push(callback); },
            emit(type, data = {}) {
                const event = {preventDefault(){ this.prevented = true; }, ...data};
                (this.handlers[type] ?? []).forEach(callback => callback(event)); return event;
            },
            setAttribute(key, value) { this.attributes[key] = value; },
            getAttribute(key) { return this.attributes[key] ?? null; },
            removeAttribute(key) { delete this.attributes[key]; },
            focus() { document.activeElement = this; },
            getClientRects() { return this.hidden ? [] : [{}]; },
            appendChild(child) { child.parentElement = this; this.children.push(child); },
            replaceChildren() { this.children = []; },
            setCustomValidity(message) { this.validationMessage = message; },
        };
    }
    const ids = Object.fromEntries(['salesFilterForm','salesFilterPanel','salesFilterToggle','salesFilterClose','salesFilterBackdrop','salesCustomDates','salesFrom','salesTo','salesSearch','salesApply','salesMin','salesMax','salesProductSearch','salesProductId','salesProductResults','salesProductStatus','salesProductClear'].map(id => [id,element()]));
    const form = ids.salesFilterForm, panel = ids.salesFilterPanel;
    document.body = element(); document.body.style.overflow = 'auto';
    const sidebar = element(); document.body.appendChild(sidebar); document.body.appendChild(form);
    form.appendChild(ids.salesSearch); form.appendChild(ids.salesFilterToggle); form.appendChild(ids.salesFilterBackdrop); form.appendChild(panel);
    panel.hidden = true;
    const radios = ['any','today','7d','30d','custom'].map(value => Object.assign(element(),{name:'date',type:'radio',value,checked:value==='any'}));
    const defaults = ['status','payment','fulfillment','voucher'].map(name => Object.assign(element(),{name,value:'all'}));
    const sort = Object.assign(element(),{name:'sort',value:'newest'});
    const inputs = [ids.salesSearch, ids.salesFrom, ids.salesTo, ids.salesMin, ids.salesMax, ids.salesProductId];
    ['search','from','to','min','max','product'].forEach((name,index) => inputs[index].name = name);
    ids.salesProductSearch.dataset.url = '/seller/sales/products';
    const fields = [...radios,...defaults,sort,...inputs];
    const focusable = [ids.salesFilterClose,...radios,...defaults,sort,...inputs,ids.salesProductSearch,ids.salesProductClear,ids.salesApply];
    form.querySelector = () => radios.find(radio => radio.checked);
    form.querySelectorAll = selector => selector === '[name=date]' ? radios : fields;
    panel.querySelectorAll = () => focusable;
    form.reportValidity = () => !fields.some(input => input.validationMessage);
    const submissions = [];
    form.requestSubmit = () => {
        const event = form.emit('submit');
        if (!event.prevented) submissions.push(Object.fromEntries(fields.filter(input => !input.disabled && (input.type !== 'radio' || input.checked)).map(input => [input.name,input.value])));
    };
    document.getElementById = id => ids[id]; document.createElement = element;
    const mobile = {matches:isMobile, addEventListener(type, callback) { this[type] = callback; }};
    const windowHandlers = {};
    const window = {matchMedia:()=>mobile, location:{href:'http://localhost/seller/sales'}, addEventListener(type, callback){ windowHandlers[type] = callback; }};
    const timers = new Map(); let nextTimer = 0;
    const fetches = [];
    vm.runInNewContext(source, {
        document, window, URL, AbortController,
        setTimeout(callback,delay) { timers.set(++nextTimer,{callback,delay}); return nextTimer; },
        clearTimeout(id) { timers.delete(id); },
        async fetch(url) { fetches.push(url.toString()); return {ok:true,json:async()=>[{id:1,name:'Blue Widget'}]}; },
    });
    async function flush(delay) {
        for (const [id,timer] of [...timers]) if (timer.delay === delay) { timers.delete(id); await timer.callback(); }
    }
    return {ids,document,mobile,sidebar,radios,defaults,sort,submissions,fetches,flush,windowHandlers};
}

test('disclosure preserves selections; mobile traps focus, locks scroll and restores background', () => {
    const h = harness(true), {ids,document} = h;
    assert.equal(ids.salesFilterPanel.hidden,true);
    ids.salesFilterToggle.emit('click');
    assert.equal(ids.salesFilterToggle.getAttribute('aria-expanded'),'true');
    assert.equal(ids.salesFilterPanel.getAttribute('aria-modal'),'true');
    assert.equal(document.body.style.overflow,'hidden'); assert.equal(h.sidebar.inert,true);
    ids.salesApply.focus();
    document.handlers.keydown({key:'Tab',shiftKey:false,preventDefault(){}});
    assert.equal(document.activeElement,ids.salesFilterClose);
    document.handlers.keydown({key:'Tab',shiftKey:true,preventDefault(){}});
    assert.equal(document.activeElement,ids.salesApply);
    ids.salesMin.value = '500';
    document.handlers.keydown({key:'Escape',preventDefault(){}});
    assert.equal(ids.salesFilterPanel.hidden,true); assert.equal(document.activeElement,ids.salesFilterToggle);
    assert.equal(document.body.style.overflow,'auto'); assert.equal(h.sidebar.inert,false);
    ids.salesFilterToggle.emit('click'); assert.equal(ids.salesMin.value,'500');
    h.mobile.matches = false; h.mobile.change();
    assert.equal(ids.salesFilterPanel.getAttribute('aria-modal'),null); assert.equal(h.sidebar.inert,false);
});

test('custom dates become enabled and required only for the custom preset', () => {
    const {ids,radios} = harness();
    assert.equal(ids.salesFrom.disabled,true); assert.equal(ids.salesTo.disabled,true);
    radios.forEach(radio => radio.checked = radio.value === 'custom'); radios[4].emit('change');
    assert.equal(ids.salesCustomDates.hidden,false); assert.equal(ids.salesFrom.required,true);
    ids.salesFrom.value = '2026-11-01'; ids.salesFrom.emit('change'); assert.equal(ids.salesTo.min,'2026-11-01');
    radios.forEach(radio => radio.checked = radio.value === 'today'); radios[1].emit('change');
    assert.equal(ids.salesFrom.disabled,true); assert.equal(ids.salesCustomDates.hidden,true);
    assert.equal(ids.salesFrom.value,'2026-11-01');
});

test('keyword debounce submits once with clean criteria and real loading feedback', async () => {
    const h = harness(), {ids} = h;
    ids.salesSearch.value = 'wi'; ids.salesSearch.emit('input');
    ids.salesSearch.value = 'widget'; ids.salesSearch.emit('input');
    assert.equal(h.submissions.length,0);
    await h.flush(300);
    assert.equal(h.submissions.length,1); assert.deepEqual(h.submissions[0],{search:'widget'});
    assert.equal(ids.salesFilterForm.getAttribute('aria-busy'),'true'); assert.equal(ids.salesApply.disabled,true);
    ids.salesSearch.emit('input'); await h.flush(300); assert.equal(h.submissions.length,1);
    await h.flush(0); h.windowHandlers.pageshow(); assert.equal(ids.salesApply.disabled,false);
});

test('amount validation stops submission and opens the panel', () => {
    const {ids,submissions} = harness();
    ids.salesMin.value = '100.01'; ids.salesMax.value = '100.00';
    ids.salesFilterForm.requestSubmit();
    assert.equal(submissions.length,0); assert.equal(ids.salesFilterPanel.hidden,false);
    assert.match(ids.salesMax.validationMessage,/at least/);
    ids.salesMax.value = '100.01'; ids.salesMax.emit('input'); ids.salesFilterForm.requestSubmit();
    assert.equal(submissions.length,1);
});

test('product lookup is debounced, selects the canonical id, and clears selection', async () => {
    const h = harness(), {ids} = h;
    ids.salesProductSearch.value = 'bl'; ids.salesProductSearch.emit('input');
    ids.salesProductSearch.value = 'blue'; ids.salesProductSearch.emit('input');
    await h.flush(300);
    assert.equal(h.fetches.length,1); assert.match(h.fetches[0],/q=blue/);
    assert.equal(ids.salesProductResults.hidden,false);
    ids.salesProductResults.children[0].children[0].emit('click');
    assert.equal(ids.salesProductId.value,1); assert.equal(ids.salesProductSearch.value,'Blue Widget');
    assert.equal(ids.salesProductResults.hidden,true);
    ids.salesProductClear.emit('click'); assert.equal(ids.salesProductId.value,''); assert.equal(ids.salesProductSearch.value,'');
});
