const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname,'../../resources/views/buyer/tracking.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({hidden = false, initial = {}, url = '/tracking-data'} = {}) {
    const requests = [], timers = new Map(), handlers = {}, documentHandlers = {};
    let nextTimer = 0;
    const nodes = Object.fromEntries(['tracking-map','tracking-updated','tracking-error','tracking-status','tracking-location-state','package-tracking'].map(id => [id, {
        textContent:'', hidden:true, innerHTML:'', children:[], details:[{open:false,dataset:{trackingActivity:'TRACK-A'}}], contains:()=>false,
        replaceChildren(){this.children=[];}, append(...items){this.children.push(...items);}, querySelectorAll(){return this.details;}
    }]));
    const document = {hidden, activeElement:null, getElementById:id=>nodes[id], createElement:tag=>({tag}), addEventListener:(type,fn)=>documentHandlers[type]=fn};
    const window = {addEventListener:(type,fn)=>handlers[type]=fn, matchMedia:()=>({matches:true})};
    const sandbox = {document,window,Intl,Date,console,fetch:()=>new Promise((resolve,reject)=>requests.push({resolve,reject})),
        setTimeout(fn,delay){timers.set(++nextTimer,{fn,delay});return nextTimer;},clearTimeout:id=>timers.delete(id)};
    const initialData = {status:'OUT_FOR_DELIVERY',status_label:'Out for delivery',terminal:false,latitude:null,longitude:null,updated_at:null,...initial};
    const script = source.replace(/const trackingUrl = .*?;/,`const trackingUrl = ${JSON.stringify(url)};`)
        .replace(/const initialTracking = .*?;/,`const initialTracking = ${JSON.stringify(initialData)};`)
        .replace(/const mapStyleUrl = .*?;/,'const mapStyleUrl = null;');
    vm.runInNewContext(script,sandbox);
    const settle = ()=>new Promise(setImmediate);
    async function respond(data = {}, status = 200) {
        requests.at(-1).resolve({status,ok:status>=200&&status<300,json:async()=>({status:'OUT_FOR_DELIVERY',status_label:'Out for delivery',trackable:true,latitude:null,longitude:null,updated_at:null,...data})});await settle();
    }
    async function tick() { const timer = [...timers.values()][0];assert.ok(timer);timers.clear();timer.fn();await settle(); }
    return {nodes,document,window,handlers,documentHandlers,requests,timers,respond,tick,settle};
}

test('terminal responses stop polling without coordinates, including focus and visibility resumes', async()=>{
    for (const status of ['DELIVERED','COMPLETED','RTS_COMPLETED','CANCELLED']) {
        const h=harness();await h.respond({status,terminal:true,trackable:false});
        assert.equal(h.timers.size,0);assert.match(h.nodes['tracking-location-state'].textContent,/Tracking has ended/);
        h.handlers.focus();h.documentHandlers.visibilitychange();h.handlers.pageshow();assert.equal(h.requests.length,1);
    }
    const terminal=harness({initial:{status:'DELIVERED',terminal:true}});assert.equal(terminal.requests.length,0);
});

test('hidden tabs pause; visibility and focus resume without overlapping requests',async()=>{
    const h=harness({hidden:true});assert.equal(h.requests.length,0);
    h.document.hidden=false;h.documentHandlers.visibilitychange();h.handlers.focus();assert.equal(h.requests.length,1);
    await h.respond();assert.equal(h.timers.size,1);
    h.document.hidden=true;h.documentHandlers.visibilitychange();assert.equal(h.timers.size,0);
    h.handlers.focus();assert.equal(h.requests.length,1);
    h.document.hidden=false;h.documentHandlers.visibilitychange();assert.equal(h.requests.length,2);
    h.handlers.focus();assert.equal(h.requests.length,2);
    await h.respond();h.handlers.pagehide();assert.equal(h.timers.size,0);
});

test('network and server errors show safe feedback and bounded backoff resets on success',async()=>{
    const h=harness();h.requests[0].reject(new Error('PRIVATE EXCEPTION'));await h.settle();
    assert.equal(h.nodes['tracking-error'].hidden,false);assert.doesNotMatch(h.nodes['tracking-error'].textContent,/PRIVATE/);
    assert.equal([...h.timers.values()][0].delay,30000);
    await h.tick();await h.respond({},500);assert.equal([...h.timers.values()][0].delay,60000);
    await h.tick();await h.respond({},503);assert.equal([...h.timers.values()][0].delay,60000);
    assert.match(h.nodes['tracking-location-state'].textContent,/last known location/);
    await h.tick();await h.respond();assert.equal([...h.timers.values()][0].delay,15000);assert.equal(h.nodes['tracking-error'].hidden,true);
});

test('401 and 419 stop with exact session-expired feedback; 403/404 do not poll indefinitely',async()=>{
    for (const status of [401,419,403,404]) {
        const h=harness();await h.respond({},status);
        assert.equal(h.nodes['tracking-error'].hidden,false);assert.equal(h.timers.size,0);
        if ([401,419].includes(status)) assert.equal(h.nodes['tracking-error'].textContent,'Your session expired. Please refresh the page or log in again.');
        h.handlers.focus();assert.equal(h.requests.length,1);
    }
});

test('locations use semantic Manila time and always identify a reported, possibly stale position',async()=>{
    const h=harness();await h.respond({latitude:14.5,longitude:121.2,updated_at:'2026-11-06T16:05:00Z'});
    assert.match(h.nodes['tracking-map'].textContent,/Last reported rider location/);
    const time=h.nodes['tracking-updated'].children[1];
    assert.equal(time.tag,'time');assert.equal(time.dateTime,'2026-11-06T16:05:00.000Z');
    assert.match(time.textContent,/Nov 7, 2026/);assert.match(time.textContent,/12:05 AM PHT/);
    await h.tick();await h.respond({latitude:null,longitude:null,updated_at:null});
    assert.equal(h.nodes['tracking-updated'].textContent,'Location time not recorded.');
    assert.match(h.nodes['tracking-location-state'].textContent,/last reported positions/);
});

test('missing shipments do not fetch and malformed polling data is handled as a recoverable error',async()=>{
    const missing=harness({url:null});assert.equal(missing.requests.length,0);assert.equal(missing.timers.size,0);
    const h=harness();h.requests[0].resolve({ok:true,status:200,json:async()=>({unexpected:'PRIVATE'})});await h.settle();
    assert.equal(h.nodes['tracking-error'].hidden,false);assert.doesNotMatch(h.nodes['tracking-error'].textContent,/PRIVATE/);
    assert.equal([...h.timers.values()][0].delay,30000);
});

test('timeline refresh preserves More activity expansion and avoids replacing focused content',async()=>{
    const h=harness();h.nodes['package-tracking'].details[0].open=true;
    await h.respond({tracking_html:'safe server-rendered timeline'});
    assert.equal(h.nodes['package-tracking'].innerHTML,'safe server-rendered timeline');
    assert.equal(h.nodes['package-tracking'].details[0].open,true);
    h.nodes['package-tracking'].contains=()=>true;
    await h.tick();await h.respond({tracking_html:'newer timeline'});
    assert.equal(h.nodes['package-tracking'].innerHTML,'safe server-rendered timeline');
});
