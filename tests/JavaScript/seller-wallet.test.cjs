const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname,'../../resources/views/seller/wallet/script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness(available = '123456') {
    const document = {activeElement:null};
    function element() { return {handlers:{},disabled:false,addEventListener(type,fn){this.handlers[type]=fn;},focus(){document.activeElement=this;}}; }
    const form=element(), input=element(), dialog=element(), cancel=element(), confirm=element(), submit=element(), amount=element();
    form.dataset={availableMinor:available};form.method='POST';form.action='/seller/wallet/payouts';form.csrf='existing-token';
    input.value='10.01';input.error='';input.setCustomValidity=value=>input.error=value;
    form.reportValidity=()=>!input.error;
    dialog.open=false;dialog.showModal=()=>dialog.open=true;dialog.close=()=>dialog.open=false;
    dialog.getBoundingClientRect=()=>({left:100,top:100,right:520,bottom:300});
    const ids={walletPayoutForm:form,walletPayoutAmount:input,walletPayoutDialog:dialog,walletPayoutCancel:cancel,walletPayoutConfirm:confirm,walletPayoutSubmit:submit,walletConfirmAmount:amount};
    document.getElementById=id=>ids[id];document.addEventListener=(type,fn)=>fn();
    const requests=[];
    vm.runInNewContext(source,{document,HTMLFormElement:{prototype:{submit(){requests.push(this);}}}});
    function event(extra={}){return{prevented:false,stopped:false,preventDefault(){this.prevented=true;},stopPropagation(){this.stopped=true;},...extra};}
    function open(value='10.01'){input.value=value;const e=event();form.handlers.submit(e);assert.equal(e.prevented,true);}
    return {document,form,input,dialog,cancel,confirm,submit,amount,requests,event,open};
}

test('confirmation displays exact pesos and reserves submission until confirmed once',()=>{
    const h=harness();h.open('1234.56');assert.equal(h.dialog.open,true);assert.equal(h.amount.textContent,'₱1,234.56');assert.equal(h.document.activeElement,h.cancel);assert.equal(h.requests.length,0);
    h.confirm.handlers.click();h.confirm.handlers.click();h.open();
    assert.deepEqual(h.requests,[h.form]);assert.equal(h.form.csrf,'existing-token');assert.equal(h.form.method,'POST');assert.equal(h.form.action,'/seller/wallet/payouts');
    assert.equal(h.submit.disabled,true);assert.equal(h.confirm.disabled,true);assert.equal(h.cancel.disabled,true);
});

test('invalid amounts, unsupported precision and overdrafts cannot open confirmation',()=>{
    const h=harness('10001');
    for(const value of ['', '0','-1','1.001','1e2','1,000','.01','100.02','999999999999999999999999.99']) {h.open(value);assert.equal(h.dialog.open,false);assert.ok(h.input.error);assert.equal(h.requests.length,0);}
    h.open('100.01');assert.equal(h.dialog.open,true);assert.equal(h.amount.textContent,'₱100.01');
});

test('large centavo amounts stay exact beyond floating-point precision',()=>{
    const h=harness('9223372036854775807');h.open('92233720368547758.07');assert.equal(h.amount.textContent,'₱92,233,720,368,547,758.07');
});

test('Cancel, Escape and backdrop restore focus and never submit; content clicks stay open',()=>{
    const h=harness();h.open();h.cancel.handlers.click();assert.equal(h.dialog.open,false);assert.equal(h.document.activeElement,h.submit);
    h.open();const escape=h.event();h.dialog.handlers.cancel(escape);assert.equal(escape.prevented,true);assert.equal(h.dialog.open,false);
    h.open();h.dialog.handlers.click(h.event({target:h.cancel}));assert.equal(h.dialog.open,true);
    h.dialog.handlers.click(h.event({target:h.dialog,clientX:150,clientY:150}));assert.equal(h.dialog.open,true);
    h.dialog.handlers.click(h.event({target:h.dialog,clientX:10,clientY:10}));assert.equal(h.dialog.open,false);assert.equal(h.requests.length,0);
});

test('keyboard focus wraps without reaching the mobile sidebar; changed amount requires reconfirmation',()=>{
    const h=harness();h.open();const reverse=h.event({key:'Tab',shiftKey:true});h.dialog.handlers.keydown(reverse);assert.equal(h.document.activeElement,h.confirm);assert.equal(reverse.stopped,true);
    h.dialog.handlers.keydown(h.event({key:'Tab'}));assert.equal(h.document.activeElement,h.cancel);
    h.input.value='11.00';h.confirm.handlers.click();assert.equal(h.dialog.open,false);assert.equal(h.requests.length,0);
});

test('zero or negative available balance blocks payout and validation updates after editing',()=>{
    for(const available of ['0','-100']) {const h=harness(available);h.open('0.01');assert.equal(h.dialog.open,false);assert.match(h.input.error,/exceeds/);}
    const h=harness();h.open('bad');h.input.value='10.10';h.input.handlers.input();assert.equal(h.input.error,'');h.open('10.10');assert.equal(h.amount.textContent,'₱10.10');
});
