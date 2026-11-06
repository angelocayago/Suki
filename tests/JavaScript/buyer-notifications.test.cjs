const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/buyer/partials/notification-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({data = {unread_count:1, notifications:[{title:'Order delivered',message:'Order TEST: Delivered.',reference:'TEST',unread:true,date:'Nov 7, 2026',iso_date:'2026-11-07T00:00:00Z',open_url:'/notifications/one/open'}]}, fail = false} = {}) {
    const node = () => ({dataset:{},handlers:{},childNodes:[],attributes:{},textContent:'',hidden:false,open:false,
        addEventListener(type, fn) {(this.handlers[type] ??= []).push(fn);},
        setAttribute(key, value) {this.attributes[key] = value;},
        appendChild(child) {this.childNodes.push(child);},
        replaceChildren(fragment) {this.childNodes = [...fragment.childNodes];},
        focus() {this.focused = true;}, contains(target) {return target === this || target === summary;}});
    const bell=node(),summary=node(),count=node(),list=node(),status=node(),document=node(),window=node();
    bell.dataset={previewUrl:'/notifications/preview',csrf:'original-csrf'};
    bell.querySelector = selector => ({summary,'[data-buyer-notification-count]':count,'[data-buyer-notification-items]':list,'[data-buyer-notification-status]':status})[selector];
    document.querySelector = () => bell; document.createElement = () => node(); document.createDocumentFragment = () => node();
    window.location={href:'https://suki.test/my-orders',origin:'https://suki.test'};
    const calls=[];
    const fetch = async (url, options) => {calls.push({url,options}); if(fail) throw Error('network'); return {ok:true,json:async()=>data};};
    const fire = async (target,type,extra={}) => {for(const fn of target.handlers[type]??[]) await fn(extra); await new Promise(resolve=>setImmediate(resolve));};
    const run = () => vm.runInNewContext(source,{document,window,fetch,URL}); run();
    return {bell,summary,count,list,status,document,window,calls,fire,run,data};
}

test('opening refreshes the existing bell count and creates clickable POST updates with CSRF', async () => {
    const h=harness(); h.bell.open=true; await h.fire(h.bell,'toggle');
    assert.equal(h.calls.length,1); assert.equal(h.calls[0].url,'https://suki.test/notifications/preview');
    assert.equal(h.calls[0].options.cache,'no-store'); assert.equal(h.calls[0].options.credentials,'same-origin');
    assert.equal(h.count.textContent,'1'); assert.equal(h.count.hidden,false); assert.equal(h.summary.attributes['aria-label'],'Notifications, 1 unread');
    const form=h.list.childNodes[0];
    assert.equal(form.method,'POST'); assert.equal(form.action,'https://suki.test/notifications/one/open');
    assert.equal(form.childNodes[0].value,'original-csrf');
    assert.equal(form.childNodes[1].childNodes[0].textContent,'Order delivered');
});

test('zero and large counts update accessibly and empty states remain useful', async () => {
    const h=harness({data:{unread_count:0,notifications:[]}}); await h.fire(h.window,'focus');
    assert.equal(h.count.hidden,true); assert.match(h.list.childNodes[0].textContent,/No notifications yet/);
    h.data.unread_count=105; await h.fire(h.window,'pageshow',{persisted:true});
    assert.equal(h.count.textContent,'99+'); assert.equal(h.summary.attributes['aria-label'],'Notifications, 105 unread');
});

test('stored text is never interpreted as HTML and external actions are discarded', async () => {
    const h=harness(); h.data.notifications[0].title='<img src=x onerror=alert(1)>';
    h.data.notifications.push({open_url:'https://foreign.test/steal'});
    await h.fire(h.window,'focus');
    assert.equal(h.list.childNodes.length,1); assert.equal(h.list.childNodes[0].childNodes[1].childNodes[0].textContent,'<img src=x onerror=alert(1)>');
    assert.doesNotMatch(source,/innerHTML/);
});

test('refresh failures preserve rendered updates and explain how to retry', async () => {
    const h=harness({fail:true}); const original={stored:'existing update'}; h.list.childNodes=[original];
    await h.fire(h.window,'focus'); assert.equal(h.list.childNodes[0],original); assert.match(h.status.textContent,/Could not refresh/);
});

test('Escape restores bell focus, outside clicks close it, and initialization is idempotent', async () => {
    const h=harness();h.run();assert.equal(h.bell.handlers.toggle.length,1);
    h.bell.open=true;await h.fire(h.bell,'keydown',{key:'Escape'});assert.equal(h.bell.open,false);assert.equal(h.summary.focused,true);
    h.bell.open=true;await h.fire(h.document,'click',{target:{}});assert.equal(h.bell.open,false);
    await h.fire(h.bell,'toggle'); assert.equal(h.calls.length,0);
});

test('simultaneous refresh triggers share one pending request and never mark notices read', async () => {
    const h=harness(); h.bell.open=true;
    await Promise.all([h.fire(h.bell,'toggle'),h.fire(h.window,'focus')]);
    assert.equal(h.calls.length,1); assert.equal(h.calls[0].options.method,undefined);
});
