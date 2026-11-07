const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), vm = require('node:vm'), path = require('node:path');
const source = fs.readFileSync(path.join(__dirname, '../../resources/js/buyer-cart-selection.js'), 'utf8');
const context = {module: {exports: {}}};
vm.runInNewContext(source, context);
const api = context.module.exports;
test('store/global state covers empty, none, some, all and retained invalid selections', () => {
    assert.equal(api.state([]).disabled, true);
    assert.equal(api.state([{eligible:true,checked:false}]).checked, false);
    assert.equal(api.state([{eligible:true,checked:true},{eligible:true,checked:false}]).indeterminate, true);
    assert.equal(api.state([{eligible:true,checked:true},{eligible:false,checked:false}]).checked, true);
    assert.equal(api.state([{eligible:false,checked:true},{eligible:true,checked:false}]).indeterminate, true);
    assert.equal(api.state([{eligible:false,checked:true}]).disabled, false);
});
test('Select All selects eligible lines and retains selected invalid lines; Deselect All clears both', () => {
    const rows = [{eligible:true,checked:false},{eligible:false,checked:false},{eligible:false,checked:true}];
    assert.deepEqual(Array.from(api.toggle(rows,true), row=>row.checked), [true,false,true]);
    assert.deepEqual(Array.from(api.toggle(rows,false), row=>row.checked), [false,false,false]);
    assert.equal(rows[0].checked, false);
});

function page() {
    const handlers = {}, requests = [], messages = [];
    const revision = {value:'0'}, status = {textContent:''}, total = {textContent:'₱0.00'}, mobileTotal = {}, mobileCount = {}, count = {}, subtotal = {}, shipping = {}, reason = {};
    const buttons = [{disabled:true,setAttribute(){},value:'checkout'},{disabled:true,setAttribute(){},value:'checkout'}];
    const form = {action:'https://suki.test/cart/selection',dataset:{},isConnected:true,querySelector:()=>revision};
    buttons.forEach(button=>button.form=form);
    let navigations = 0;
    const inputs = [1,2].map(id => {
        const attributes = new Set(['data-cart-select']);
        const row = {classList:{toggle(name,value){row.highlight=value;}},querySelector:selector => selector==='[data-cart-availability]' ? warning : selector==='[data-cart-quantity]' ? quantity : increase};
        const warning = {}, quantity = {dataset:{}}, increase = {};
        return {value:String(id),checked:false,disabled:false,dataset:{eligible:'1',store:'1',unitPriceMinor:String(id*10000),quantity:'1'},hasAttribute:key=>attributes.has(key),closest:()=>row,matches:()=>true};
    });
    const all = {checked:false,hasAttribute:()=>false,setAttribute(){}};
    const storeCount = {dataset:{storeCount:'1'}};
    const voucher = {replaceWith(){}};
    const summary = {set innerHTML(value){ total.textContent=value; }};
    const details = {};
    const cart = {
        setAttribute(){}, addEventListener:(type,fn)=>handlers[type]=fn,
        querySelector(selector) { return ({'[data-cart-selection-status]':status,'[data-cart-summary]':summary,'[data-selected-total]':total,'[data-mobile-total]':mobileTotal,'[data-mobile-count]':mobileCount,'[data-cart-summary-details]':details,'.cart-voucher-form':voucher,'[data-selected-subtotal]':subtotal,'[data-checkout-reason]':reason,'[data-cart-checkout]':buttons[0]})[selector]; },
        querySelectorAll(selector) {
            if(selector==='[data-cart-select]') return inputs;
            if(selector.includes('data-cart-all')) return [all];
            if(selector==='[data-store-count]') return [storeCount];
            if(selector==='[data-selected-count]') return [count];
            if(selector.includes('data-selected-shipping')) return [shipping,total];
            if(selector.includes('data-cart-checkout')) return buttons;
            return [];
        },
    };
    class Data {
        constructor(){this.ids=inputs.filter(input=>input.checked&&!input.disabled).map(input=>input.value);this.revision=revision.value;}
        set(key,value){this[key]=value;}
    }
    const window = {
        document:{readyState:'complete',querySelector:()=>cart,getElementById:()=>form},
        FormData:Data, matchMedia:()=>({matches:false}),addEventListener(){},setTimeout,
        SukiBuyerFeedback:{responseMessage:response=>response.json(),show:message=>messages.push(message)},
        fetch:(url,options)=>new Promise((resolve,reject)=>requests.push({url,options,resolve,reject})),
    };
    vm.runInNewContext(source,{window});
    function response(index,ids,success=true,rev=index+1,extra={}) {
        requests[index].resolve({json:async()=>({success,message:success?'Selection updated.':'Your selection changed in another tab.',data:{
            selection:{ids,revision:rev},cart:inputs.map((input,index)=>({id:index+1,available:true,stock:5,quantity:1,price_minor:(index+1)*10000})),
            summary_html:'₱777.00',selected_summary:{has_unavailable_items:false,voucher_error:null},
            ...extra,
        }})});
    }
    function change(index,checked){inputs[index].checked=checked;handlers.change({target:inputs[index]});}
    function submit(submitted=form,submitter=buttons[0]) { const event={target:submitted,submitter,preventDefault(){this.prevented=true;},stopImmediatePropagation(){}};handlers.submit(event);if(!event.prevented)navigations++;return event; }
    form.requestSubmit=button=>submit(form,button);
    return {inputs,requests,response,change,buttons,revision,status,mobileTotal,mobileCount,count,subtotal,shipping,messages,handlers,form,submit,all,storeCount,navigations:()=>navigations};
}
const tick = () => new Promise(resolve=>setImmediate(resolve));
test('rapid edits are serialized with the latest revision and never overwritten by an older response', async () => {
    const ui=page();ui.change(0,true);ui.change(1,true);
    assert.equal(ui.requests.length,1);
    assert.deepEqual(ui.requests[0].options.body.ids,['1']);
    assert.equal(ui.buttons[0].disabled,true);
    ui.response(0,[1]);await tick();
    assert.equal(ui.inputs[1].checked,true);
    assert.equal(ui.requests.length,2);
    assert.deepEqual(ui.requests[1].options.body.ids,['1','2']);
    assert.equal(ui.requests[1].options.body.revision,1);
    ui.response(1,[1,2]);await tick();
    assert.equal(ui.buttons[0].disabled,false);
    assert.equal(ui.mobileTotal.textContent,'₱777.00');
    assert.match(ui.status.textContent,/saved/);
});
test('stale selection restores the authoritative draft and requires review before Checkout', async () => {
    const ui=page();ui.change(1,true);ui.response(0,[1],false,7);await tick();
    assert.equal(ui.inputs[0].checked,true);assert.equal(ui.inputs[1].checked,false);
    assert.equal(ui.revision.value,7);assert.equal(ui.buttons[0].disabled,true);
    assert.match(ui.messages[0],/another tab/);
});
test('network errors do not enable Checkout or invent a saved selection', async () => {
    const ui=page();ui.change(0,true);ui.requests[0].reject(Error('offline'));await tick();
    assert.equal(ui.revision.value,'0');assert.equal(ui.buttons[0].disabled,true);
    assert.match(ui.status.textContent,/could not be saved/);
});
test('count and merchandise preview update synchronously with no page submission or invented final totals', () => {
    const ui=page();ui.change(0,true);
    assert.match(ui.count.textContent,/1 selected item/);
    assert.match(ui.mobileCount.textContent,/1 selected/);
    assert.equal(ui.subtotal.textContent,'₱100.00 (preview)');
    assert.equal(ui.shipping.textContent,'Confirming…');
    assert.equal(ui.mobileTotal.textContent,'Confirming…');
    assert.equal(ui.navigations(),0);
    assert.equal(ui.inputs[0].closest().highlight,true);
    assert.equal(ui.all.indeterminate,true);
    assert.equal(ui.storeCount.textContent,1);
    ui.change(1,true);assert.match(ui.count.textContent,/2 selected items/);
    assert.equal(ui.subtotal.textContent,'₱300.00 (preview)');
    ui.change(0,false);assert.equal(ui.subtotal.textContent,'₱200.00 (preview)');
    assert.equal(ui.requests.length,1);
});
test('preview only sums valid server row minor units; invalid or unsafe values await confirmation', () => {
    assert.equal(api.merchandisePreview([{checked:true,priceMinor:12345,quantity:2},{checked:false,priceMinor:999,quantity:1}]),24690);
    for(const row of [{priceMinor:-1,quantity:1},{priceMinor:1.5,quantity:1},{priceMinor:1,quantity:0},{priceMinor:1,quantity:1.5},{quantity:1},{priceMinor:Number.MAX_SAFE_INTEGER,quantity:2}]) assert.equal(api.merchandisePreview([{checked:true,...row}]),null);
});
test('repeated toggles returning to the in-flight selection do not send a redundant request', async () => {
    const ui=page();ui.change(0,true);ui.change(0,false);ui.change(0,true);
    ui.response(0,[1]);await tick();
    assert.equal(ui.requests.length,1);assert.equal(ui.inputs[0].checked,true);
    assert.equal(ui.buttons[0].disabled,false);
    assert.equal(ui.mobileTotal.textContent,'₱777.00');
});
test('Checkout waits for all pending edits, uses the Checkout submitter and permits only one native submission', async () => {
    const ui=page();ui.change(0,true);ui.change(1,true);ui.submit();ui.submit();
    assert.equal(ui.navigations(),0);
    ui.response(0,[1]);await tick();assert.equal(ui.navigations(),0);
    ui.response(1,[1,2]);await tick();
    assert.equal(ui.navigations(),1);assert.equal(ui.form.dataset.pending,'1');
    assert.equal(ui.buttons[0].value,'checkout');assert.equal(ui.revision.value,2);
});
test('failed saves block queued Checkout and Update Selection can retry with the latest checked IDs', async () => {
    const ui=page();ui.change(0,true);ui.submit();ui.requests[0].reject(Error('offline'));await tick();
    assert.equal(ui.navigations(),0);assert.equal(ui.mobileTotal.textContent,'Unconfirmed');
    ui.submit(ui.form,{value:'update'});
    assert.equal(ui.requests.length,2);assert.deepEqual(ui.requests[1].options.body.ids,['1']);
    ui.response(1,[1]);await tick();assert.equal(ui.buttons[0].disabled,false);
});
test('a repeated response cannot overtake another request because only one request is in flight', async () => {
    const ui=page();ui.change(0,true);ui.change(1,true);
    assert.equal(ui.requests.length,1);ui.response(0,[1]);await tick();
    assert.equal(ui.requests.length,2);ui.response(1,[1,2]);await tick();
    ui.response(0,[1]);await tick();
    assert.equal(ui.inputs[1].checked,true);assert.equal(ui.revision.value,2);
});
test('expired sessions keep pending amounts unconfirmed and cannot resume queued Checkout', async () => {
    const ui=page();ui.change(0,true);ui.submit();
    ui.requests[0].resolve({json:async()=>({success:false,message:'Your session expired. Please refresh the page or log in again.',code:'session_expired'})});
    await tick();assert.equal(ui.mobileTotal.textContent,'Unconfirmed');
    assert.equal(ui.buttons[0].disabled,true);assert.equal(ui.navigations(),0);
    assert.match(ui.messages[0],/session expired/);
});
test('server-confirmed prices replace preview data before the next selection edit', async () => {
    const ui=page();ui.change(0,true);
    ui.response(0,[1],true,1,{cart:[{id:1,available:true,stock:5,quantity:2,price_minor:13500},{id:2,available:true,stock:5,quantity:1,price_minor:20000}]});
    await tick();assert.equal(ui.mobileTotal.textContent,'₱777.00');
    ui.change(1,true);assert.equal(ui.subtotal.textContent,'₱470.00 (preview)');
});
test('a selected item that becomes invalid remains selected and blocks pending Checkout', async () => {
    const ui=page();ui.change(0,true);ui.submit();
    ui.response(0,[1],true,1,{cart:[{id:1,available:false,stock:0,quantity:1,price_minor:10000,availability_issue:'This item is out of stock.'},{id:2,available:true,stock:5,quantity:1,price_minor:20000}],selected_summary:{has_unavailable_items:true,voucher_error:null}});
    await tick();assert.equal(ui.inputs[0].checked,true);assert.equal(ui.inputs[0].disabled,false);
    assert.equal(ui.buttons[0].disabled,true);assert.equal(ui.navigations(),0);
});
test('an unconfirmed selection does not prevent an unrelated native Remove action', async () => {
    const ui=page();ui.change(0,true);ui.requests[0].reject(Error('offline'));await tick();
    const removal={action:'https://suki.test/cart/remove/1'};
    assert.equal(ui.submit(removal,{}).prevented,undefined);
    assert.equal(ui.navigations(),1);assert.equal(ui.requests.length,1);
});
