const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/dashboard-partials/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({large=false, zero=false, firstPeriod='Period 0'}={}) {
    const element = name => ({name, dataset:{}, children:[], handlers:{}, attributes:{}, textContent:'', hidden:false,
        setAttribute(key,value){this.attributes[key]=value;},
        addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},
        appendChild(child){this.children.push(child);}, replaceChildren(){this.children=[];}});
    const data={};
    for(const [key,count] of [['7d',7],['30d',30],['12m',12]]) {
        const points=Array.from({length:count},(_,index)=>({label:`Label ${index}`,period:`Period ${index}`,raw_minor:zero?'0':large?'9007199254740993':String((index+1)*10001)}));
        points[0].period=firstPeriod;
        data[key]={points,total_minor:points.reduce((n,p)=>n+BigInt(p.raw_minor),0n).toString(),from:'2025-12-01',to:'2026-11-06',granularity:key==='12m'?'month':'day'};
    }
    const selectors=['[data-sales-line]','[data-sales-area]','[data-sales-points]','[data-sales-labels]','[data-sales-table]','[data-sales-detail]','[data-sales-total]','[data-sales-period]','[data-sales-empty]','[data-sales-axis="max"]','[data-sales-axis="mid"]'];
    const fields=Object.fromEntries(selectors.map(selector=>[selector,element(selector)]));
    const svg=element('svg'), description=element('desc'); svg.querySelector=()=>description;
    const buttons=['7d','30d','12m'].map(range=>Object.assign(element('button'),{dataset:{salesRange:range}}));
    const panel=element('panel'); panel.querySelector=selector=>selector==='svg'?svg:fields[selector]; panel.querySelectorAll=()=>buttons;
    const broken=Object.assign(element('img'),{complete:true,naturalWidth:0});
    const context={document:{querySelector:()=>panel,querySelectorAll:()=>[broken],getElementById:()=>({textContent:JSON.stringify(data)}),createElementNS:(_,name)=>element(name),createElement:element}};
    const run=()=>vm.runInNewContext(source,context); run();
    const fire=(target,type)=>{for(const fn of target.handlers[type]??[])fn({});};
    return {fields,buttons,data,description,broken,run,fire};
}

test('initial seven-day chart has exact data table, accessible points, a smooth line and one active range',()=>{
    const h=harness();
    assert.equal(h.fields['[data-sales-points]'].children.length,7);
    assert.equal(h.fields['[data-sales-table]'].children.length,7);
    assert.match(h.fields['[data-sales-line]'].attributes.d,/^M.*C/);
    assert.equal(h.buttons[0].attributes['aria-pressed'],'true');
    assert.equal(h.buttons[1].attributes['aria-pressed'],'false');
    const point=h.fields['[data-sales-points]'].children[2];
    assert.equal(point.attributes.tabindex,'0'); assert.equal(point.attributes['aria-label'],'Period 2: ₱300.03');
    h.fire(point,'focus'); assert.equal(h.fields['[data-sales-detail]'].textContent,'Period 2: ₱300.03');
});

test('range switches render real thirty-day and twelve-month datasets without any request or unrelated page replacement',()=>{
    const h=harness(); h.fire(h.buttons[1],'click');
    assert.equal(h.fields['[data-sales-points]'].children.length,30);
    assert.equal(h.fields['[data-sales-table]'].children.length,30);
    assert.ok(h.fields['[data-sales-labels]'].children.length<=6);
    assert.equal(h.buttons[1].attributes['aria-pressed'],'true');
    h.fire(h.buttons[2],'click');
    assert.equal(h.fields['[data-sales-points]'].children.length,12);
    assert.equal(h.fields['[data-sales-table]'].children.length,12);
    assert.match(h.description.textContent,/monthly.*Asia\/Manila/);
    assert.match(h.fields['[data-sales-period]'].textContent,/Monthly/);
});

test('mouse hover and keyboard focus reveal the same exact peso amount without HTML injection',()=>{
    const label='<img src=x onerror=bad>';
    const h=harness({firstPeriod:label});
    const point=h.fields['[data-sales-points]'].children[0];
    h.fire(point,'mouseenter'); assert.equal(h.fields['[data-sales-detail]'].textContent,label+': ₱100.01');
    h.fire(point,'focus'); assert.equal(h.fields['[data-sales-detail]'].textContent,label+': ₱100.01');
    assert.equal(h.fields['[data-sales-detail]'].innerHTML,undefined);
});

test('zero sales stays finite, includes all periods and shows an honest empty state',()=>{
    const h=harness({zero:true});
    assert.equal(h.fields['[data-sales-total]'].textContent,'₱0.00');
    assert.equal(h.fields['[data-sales-empty]'].hidden,false);
    assert.doesNotMatch(h.fields['[data-sales-line]'].attributes.d,/NaN|Infinity/);
    assert.equal(h.fields['[data-sales-table]'].children.length,7);
});

test('exact money labels preserve integer centavos beyond floating-point safe range',()=>{
    const h=harness({large:true});
    const point=h.fields['[data-sales-points]'].children[0];
    h.fire(point,'focus'); assert.equal(h.fields['[data-sales-detail]'].textContent,'Period 0: ₱90,071,992,547,409.93');
    assert.equal(h.fields['[data-sales-total]'].textContent,'₱630,503,947,831,869.51');
});

test('chart initialization is idempotent and missing thumbnails reveal the existing fallback',()=>{
    const h=harness(); h.run();
    assert.equal(h.buttons[0].handlers.click.length,1);
    assert.equal(h.broken.hidden,true);
});
