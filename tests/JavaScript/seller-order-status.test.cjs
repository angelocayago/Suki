const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/dashboard-partials/order-status-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness(empty=false) {
    function node(dataset={}) {
        const classes=new Set(), properties={};
        return {dataset,handlers:{},attributes:{},textContent:'',classList:{add:(...names)=>names.forEach(n=>classes.add(n)),remove:(...names)=>names.forEach(n=>classes.delete(n)),toggle:(n,on)=>on?classes.add(n):classes.delete(n),contains:n=>classes.has(n)},
            style:{setProperty:(k,v)=>properties[k]=v,getPropertyValue:k=>properties[k]||''},
            setAttribute(k,v){this.attributes[k]=v;},addEventListener(k,fn){(this.handlers[k]??=[]).push(fn);}};
    }
    const data=[['completed','Completed',4,'40.0','#15803D'],['progress','In Progress',6,'60.0','#D97706'],['unknown','Unknown workflow',0,'0.0','#64748B']];
    const choices=data.map(([key,label,count,percent,color])=>{
        const item=node({statusChoice:key,statusLabel:label,statusCount:String(empty?0:count),statusPercent:empty?'0.0':percent});
        item.inheritedColor=color; return item;
    });
    const segments=empty?[]:choices.filter(c=>c.dataset.statusCount!=='0').map(c=>{
        const item=node({...c.dataset,statusChoice:undefined,statusSegment:c.dataset.statusChoice});item.style.setProperty('--status-color',c.inheritedColor);return item;
    });
    const detail=node(); detail.textContent=empty?'No orders yet.':'Explore orders.';
    const widget=node();widget.querySelectorAll=s=>s==='[data-status-segment]'?segments:choices;widget.querySelector=()=>detail;
    const context={document:{querySelector:()=>widget},getComputedStyle:item=>({getPropertyValue:()=>item.inheritedColor})};
    const run=()=>vm.runInNewContext(source,context);run();
    const fire=(target,event,payload={})=>(target.handlers[event]||[]).forEach(fn=>fn(payload));
    return {choices,segments,detail,widget,run,fire};
}
test('hover and keyboard focus synchronize exact details, semantic color and the matching segment',()=>{
    const h=harness();h.fire(h.choices[1],'focus');
    assert.equal(h.detail.textContent,'In Progress: 6 orders · 60.0% of all Seller Orders');
    assert.equal(h.detail.style.getPropertyValue('--status-color'),'#D97706');
    assert.equal(h.segments[1].classList.contains('is-highlighted'),true);
    assert.equal(h.segments[0].classList.contains('is-dimmed'),true);
    assert.equal(h.choices[1].attributes['aria-pressed'],'true');
    h.fire(h.segments[0],'mouseenter');assert.match(h.detail.textContent,/Completed: 4 orders · 40.0%/);
    assert.equal(h.choices[1].attributes['aria-pressed'],'false');
});
test('touch click selects zero categories honestly without inventing a segment or request',()=>{
    const h=harness();h.fire(h.choices[2],'click');
    assert.equal(h.detail.textContent,'Unknown workflow: 0 orders · 0.0% of all Seller Orders');
    assert.equal(h.segments.length,2);assert.ok(h.segments.every(s=>s.classList.contains('is-dimmed')));
    assert.equal(h.detail.innerHTML,undefined);
});
test('Escape clears details and highlights without moving focus or triggering navigation',()=>{
    const h=harness();h.fire(h.choices[0],'focus');let stopped=false;
    h.fire(h.widget,'keydown',{key:'Escape',stopPropagation:()=>stopped=true});
    assert.equal(stopped,true);assert.equal(h.detail.textContent,'Explore orders.');
    assert.ok(h.choices.every(c=>c.attributes['aria-pressed']==='false'));
    assert.ok(h.segments.every(s=>!s.classList.contains('is-dimmed')&&!s.classList.contains('is-highlighted')));
});
test('zero total remains usable and initialization never duplicates handlers',()=>{
    const h=harness(true);h.run();
    assert.equal(h.choices[0].handlers.click.length,1);
    h.fire(h.choices[0],'click');assert.match(h.detail.textContent,/0 orders · 0.0%/);
    assert.equal(h.segments.length,0);
});
