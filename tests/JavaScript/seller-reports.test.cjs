const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/partials/reports-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    let focused;
    const element = () => ({
        hidden:false, value:'', disabled:false, handlers:{}, attributes:{}, dataset:{},
        addEventListener(type, callback) { this.handlers[type] = callback; },
        setAttribute(key, value) { this.attributes[key] = value; },
        removeAttribute(key) { delete this.attributes[key]; },
        focus() { focused = this; },
    });
    const ids = Object.fromEntries(['reportsFilterToggle','reportsFilterPanel','reportsCustomDates','reportsFrom','reportsTo','reportsApply','reportsChartTooltip'].map(id => [id,element()]));
    ids.reportsFilterPanel.hidden = true;
    const radios = ['any','today','7d','30d','custom'].map(value => Object.assign(element(), {value,checked:value==='any'}));
    ids.reportsFilterPanel.querySelector = () => radios.find(radio => radio.checked);
    ids.reportsFilterPanel.querySelectorAll = () => radios;
    const point = element(); point.dataset.tooltip = 'Nov 01, 2026 · PHP 80.00';
    const timers = [], windowEvents = {};
    vm.runInNewContext(source, {
        document: {
            addEventListener: (_, callback) => callback(),
            getElementById: id => ids[id],
            querySelectorAll: () => [point],
        },
        window: { addEventListener(type, callback) { windowEvents[type] = callback; } },
        setTimeout: callback => timers.push(callback),
    });
    return { ids, radios, point, timers, windowEvents, getFocused:()=>focused };
}

test('filters start collapsed, toggle without losing inputs, and close with Escape', () => {
    const {ids,getFocused} = harness();
    assert.equal(ids.reportsFilterPanel.hidden,true);
    ids.reportsFilterToggle.handlers.click();
    assert.equal(ids.reportsFilterPanel.hidden,false);
    assert.equal(ids.reportsFilterToggle.attributes['aria-expanded'],'true');
    ids.reportsFrom.value = '2026-11-01';
    ids.reportsFilterPanel.handlers.keydown({key:'Escape'});
    assert.equal(ids.reportsFilterPanel.hidden,true);
    assert.equal(getFocused(),ids.reportsFilterToggle);
    ids.reportsFilterToggle.handlers.click(); assert.equal(ids.reportsFrom.value,'2026-11-01');
});

test('custom dates are enabled and required only when selected, with a valid minimum end date', () => {
    const {ids,radios} = harness();
    assert.equal(ids.reportsFrom.disabled,true);
    radios.forEach(radio => radio.checked = radio.value === 'custom'); radios[4].handlers.change();
    assert.equal(ids.reportsCustomDates.hidden,false); assert.equal(ids.reportsFrom.required,true);
    ids.reportsFrom.value = '2026-11-01'; ids.reportsFrom.handlers.change();
    assert.equal(ids.reportsTo.min,'2026-11-01');
    radios.forEach(radio => radio.checked = radio.value === '7d'); radios[2].handlers.change();
    assert.equal(ids.reportsFrom.disabled,true); assert.equal(ids.reportsCustomDates.hidden,true);
});

test('normal GET submission shows loading once, omits defaults, and recovers from browser history', () => {
    const {ids,radios,timers,windowEvents} = harness();
    ids.reportsFilterPanel.handlers.submit({preventDefault(){ throw Error('First submission blocked'); }});
    assert.equal(ids.reportsApply.disabled,true);
    assert.equal(ids.reportsFilterPanel.attributes['aria-busy'],'true'); assert.equal(radios[0].disabled,true);
    let blocked = false; ids.reportsFilterPanel.handlers.submit({preventDefault(){ blocked = true; }});
    assert.equal(blocked,true);
    timers.forEach(callback => callback()); windowEvents.pageshow();
    assert.equal(radios[0].disabled,false); assert.equal(ids.reportsApply.disabled,false);
});

test('chart tooltip uses the rendered database value for hover, keyboard focus and touch click', () => {
    const {ids,point} = harness();
    for (const type of ['mouseenter','focus','click']) {
        ids.reportsChartTooltip.textContent = '';
        point.handlers[type]();
        assert.equal(ids.reportsChartTooltip.textContent,point.dataset.tooltip);
    }
});
