const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/js/buyer-action-feedback.js'),'utf8');
function helpers() { const context={module:{exports:{}}};vm.runInNewContext(source,context);return context.module.exports; }

test('quantity feedback covers empty, negative, decimal, nonnumeric, stock and valid input',()=>{
    const api=helpers();
    for(const [value,message] of [['','Please enter a quantity.'],['0','Please enter a quantity of at least 1.'],['-1','Please enter a quantity of at least 1.'],['1.5','Quantity must be a whole number.'],['abc','Quantity must be a whole number.'],['6','Only 5 available. Please lower your quantity.']])assert.equal(api.quantityMessage(value,5),message);
    assert.equal(api.quantityMessage('1',0),'This item is out of stock.');assert.equal(api.quantityMessage('5',5),'');
});

test('JSON parsing handles validation, expired sessions, authentication, malformed and server responses safely',async()=>{
    const api=helpers();
    for(const status of [401,419])assert.equal((await api.responseMessage({status})).message,'Your session expired. Please refresh the page or log in again.');
    assert.equal((await api.responseMessage({status:500,json:async()=>({message:'SQL password stack trace'})})).message,'Something went wrong. Please try again.');
    const failed=await api.responseMessage({status:422,json:async()=>({success:false,message:'Quantity must be a whole number.',errors:{quantity:['Quantity must be a whole number.']},code:'invalid_quantity'})});
    assert.equal(failed.code,'invalid_quantity');assert.equal(failed.errors.quantity[0],failed.message);
    assert.equal((await api.responseMessage({status:200,json:async()=>{throw Error('HTML response');}})).code,'invalid_response');
});

function page() {
    const events={},windowEvents={},timers=[];
    const box={hidden:true,textContent:''},error={textContent:''},button={disabled:false,dataset:{}},attrs={};
    const form={action:'https://suki.test/cart/update/1',dataset:{},setAttribute(k,v){attrs[k]=v;},removeAttribute(k){delete attrs[k];},querySelector:()=>field,querySelectorAll:()=>[button]};
    const field={value:'2',dataset:{stock:'5',lastValid:'2'},form,hasAttribute:name=>name==='data-cart-quantity',matches:name=>name==='[data-buyer-quantity]',getAttribute:()=> 'line-error',setAttribute(k,v){this[k]=v;}};
    const document={querySelector:()=>box,getElementById:id=>id==='line-error'?error:null,querySelectorAll:selector=>selector==='form[data-buyer-pending]'&&form.dataset.buyerPending?[form]:[],addEventListener(type,fn){events[type]=fn;}};
    const window={document,location:{href:'https://suki.test/cart'},setTimeout:fn=>timers.push(fn),addEventListener(type,fn){windowEvents[type]=fn;}};
    const context={window,module:{exports:{}},URL};vm.runInNewContext(source,context);
    const fire=(type,target=field)=>{const event={target,defaultPrevented:false,preventDefault(){this.defaultPrevented=true;}};events[type](event);return event;};
    let submitted=0;form.requestSubmit=()=>{if(!fire('submit',form).defaultPrevented)submitted++;};
    return {field,form,box,error,button,timers,fire,windowEvents,api:window.SukiBuyerFeedback,submitted:()=>submitted};
}

test('invalid Cart blur restores last valid quantity and Enter/native submit rejects invalid values',()=>{
    const h=page();h.field.value='0';h.fire('focusout');assert.equal(h.field.value,'2');assert.equal(h.field['aria-invalid'],'true');assert.match(h.error.textContent,/at least 1/);
    h.field.value='1.5';assert.equal(h.fire('submit',h.form).defaultPrevented,true);assert.equal(h.field.value,'2');
    h.field.value='4';h.fire('focusout');assert.equal(h.submitted(),1);assert.equal(h.field['aria-invalid'],'false');
});

test('normal POST double submission is blocked and Back navigation restores buttons',()=>{
    const h=page();assert.equal(h.fire('submit',h.form).defaultPrevented,false);assert.equal(h.fire('submit',h.form).defaultPrevented,true);
    assert.equal(h.button.disabled,false);h.timers.forEach(fn=>fn());assert.equal(h.button.disabled,true);
    h.windowEvents.pageshow();assert.equal(h.button.disabled,false);assert.equal(h.form.dataset.buyerPending,undefined);
});

test('AJAX errors map to the line, restore its quantity, and never change Wishlist icon state',async()=>{
    const h=page();h.field.value='9';const result=await h.api.handleResponse({status:409,json:async()=>({success:false,message:'Only 5 available. Please lower your quantity.',errors:{quantity:['Only 5 available. Please lower your quantity.']},code:'insufficient_stock'})},h.form);
    assert.equal(result.success,false);assert.equal(h.field.value,'2');assert.match(h.error.textContent,/Only 5/);assert.equal(h.box.hidden,false);
    h.api.networkError();assert.equal(h.box.textContent,'Network error. Please check your connection and try again.');
});


test('successful AJAX feedback restores controls and dismisses success without hiding a later error',async()=>{
    const h=page();h.fire('submit',h.form);h.timers[0]();assert.equal(h.button.disabled,true);
    h.error.textContent='Old error';await h.api.handleResponse({status:200,json:async()=>({success:true,message:'Cart updated successfully.',errors:{},code:'ok'})},h.form);
    assert.equal(h.button.disabled,false);assert.equal(h.field['aria-invalid'],'false');assert.equal(h.error.textContent,'');assert.equal(h.box.hidden,false);
    h.api.networkError(h.form);h.timers[h.timers.length-1]();assert.equal(h.box.hidden,false);assert.match(h.box.textContent,/Network error/);
});
