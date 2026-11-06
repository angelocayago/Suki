const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/buyer/partials/cancellation-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness({retry='',valid=true}={}){
    const document={activeElement:null};
    function node(dataset={}){return{dataset,handlers:{},disabled:false,value:'',textContent:'',checked:false,
        addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},focus(){document.activeElement=this;}};}
    const dialog=node({errorReference:retry,validationMessage:'Choose a valid reason.'}),form=node(),keep=node(),confirm=node(),reference=node(),feedback=node(),hidden=node(),errors=node();
    const radios=Array.from({length:6},(_,i)=>Object.assign(node(),{value:String(i),checked:retry!==''&&i===2}));
    const triggers=['ORDER-ALPHA','ORDER-BETA'].map(number=>node({cancelOrder:number,cancelAction:'/my-orders/'+number+'/cancel'}));
    const window=node();
    form.querySelector=s=>s==='[type="submit"]'?confirm:hidden;form.querySelectorAll=()=>radios;
    form.reportValidity=()=>valid;
    dialog.querySelector=s=>({'[data-cancellation-form]':form,'[data-cancellation-close]':keep,'[data-cancellation-reference]':reference,'[data-cancellation-feedback]':feedback})[s];
    dialog.querySelectorAll=()=>[...radios,keep,confirm].filter(n=>!n.disabled);
    dialog.open=false;dialog.showModal=()=>dialog.open=true;
    dialog.close=()=>{dialog.open=false;fire(dialog,'close');};dialog.getBoundingClientRect=()=>({left:100,right:500,top:100,bottom:600});
    document.getElementById=()=>dialog;document.querySelectorAll=()=>triggers;document.querySelector=()=>retry?errors:null;
    const fire=(target,type,extra={})=>{const event={prevented:false,preventDefault(){this.prevented=true;},...extra};for(const fn of target.handlers[type]??[])fn(event);return event;};
    const run=()=>vm.runInNewContext(source,{document,window});run();
    return{document,dialog,form,keep,confirm,reference,feedback,hidden,radios,triggers,window,errors,fire,run};
}
test('opening shows the exact order number and canonical action, focuses Keep and performs no submission',()=>{
    const h=harness();h.fire(h.triggers[0],'click');
    assert.equal(h.dialog.open,true);assert.equal(h.document.activeElement,h.keep);
    assert.equal(h.form.action,'/my-orders/ORDER-ALPHA/cancel');assert.equal(h.hidden.value,'ORDER-ALPHA');assert.equal(h.reference.textContent,'ORDER-ALPHA');
    assert.ok(h.radios.every(r=>!r.checked));assert.equal(h.confirm.disabled,false);
});
test('Keep, Escape and backdrop close without submitting and return focus; clicks inside do not dismiss',()=>{
    const h=harness();h.fire(h.triggers[0],'click');
    h.fire(h.dialog,'click',{target:h.dialog,clientX:120,clientY:120});assert.equal(h.dialog.open,true);
    h.fire(h.keep,'click');assert.equal(h.dialog.open,false);assert.equal(h.document.activeElement,h.triggers[0]);
    h.fire(h.triggers[0],'click');assert.equal(h.fire(h.dialog,'cancel').prevented,true);assert.equal(h.dialog.open,false);
    h.fire(h.triggers[0],'click');h.fire(h.dialog,'click',{target:h.dialog,clientX:20,clientY:20});assert.equal(h.dialog.open,false);
    assert.equal(h.confirm.disabled,false);
});
test('keyboard focus wraps inside the native modal',()=>{
    const h=harness();h.fire(h.triggers[0],'click');h.confirm.focus();
    assert.equal(h.fire(h.dialog,'keydown',{key:'Tab',shiftKey:false}).prevented,true);assert.equal(h.document.activeElement,h.radios[0]);
    assert.equal(h.fire(h.dialog,'keydown',{key:'Tab',shiftKey:true}).prevented,true);assert.equal(h.document.activeElement,h.confirm);
});
test('valid submission preserves selected fields and permits exactly one native request',()=>{
    const h=harness();h.fire(h.triggers[0],'click');h.radios[3].checked=true;
    assert.equal(h.fire(h.form,'submit').prevented,false);assert.equal(h.fire(h.form,'submit').prevented,true);
    assert.equal(h.confirm.disabled,true);assert.equal(h.keep.disabled,true);assert.equal(h.radios[3].checked,true);
    h.fire(h.dialog,'cancel');assert.equal(h.dialog.open,true);assert.match(h.feedback.textContent,/Submitting cancellation/);
    h.fire(h.window,'pageshow');assert.equal(h.confirm.disabled,false);assert.equal(h.keep.disabled,false);
});
test('invalid forms and programmatic submits without an eligible opener cannot submit',()=>{
    const h=harness({valid:false});assert.equal(h.fire(h.form,'submit').prevented,true);
    h.fire(h.triggers[0],'click');assert.equal(h.fire(h.form,'submit').prevented,true);assert.equal(h.confirm.disabled,false);
});
test('reason stays on dismissal but does not transfer to another order',()=>{
    const h=harness();h.fire(h.triggers[0],'click');h.radios[1].checked=true;h.fire(h.keep,'click');
    h.fire(h.triggers[0],'click');assert.equal(h.radios[1].checked,true);h.fire(h.keep,'click');
    h.fire(h.triggers[1],'click');assert.ok(h.radios.every(r=>!r.checked));assert.equal(h.reference.textContent,'ORDER-BETA');
});
test('validation retry preserves the selected reason only for the matching eligible order',()=>{
    const h=harness({retry:'ORDER-ALPHA'});assert.equal(h.dialog.open,true);assert.equal(h.radios[2].checked,true);assert.equal(h.feedback.textContent,'Choose a valid reason.');
    h.fire(h.window,'pageshow',{persisted:false});assert.equal(h.feedback.textContent,'Choose a valid reason.');
    const unavailable=harness({retry:'ORDER-NO-LONGER-ELIGIBLE'});assert.equal(unavailable.dialog.open,false);assert.equal(unavailable.document.activeElement,unavailable.errors);
});
test('initialization is idempotent and no native confirmation or fetch is required',()=>{
    const h=harness();h.run();assert.equal(h.triggers[0].handlers.click.length,1);
    assert.doesNotMatch(source,/\bconfirm\(|\bfetch\(/);
});
