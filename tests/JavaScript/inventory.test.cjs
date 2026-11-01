const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/inventory-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness(disabled=false){
    const el=()=>({handlers:{},disabled:false,hidden:false,textContent:'',dataset:{},attributes:{},addEventListener(type,fn){this.handlers[type]=fn;},setAttribute(k,v){this.attributes[k]=v;},removeAttribute(k){delete this.attributes[k];},focus(){this.focused=true;}});
    const forms=[1,2].map(()=>{
        const form=el(),input=el(),snapshot=el(),save=el(),feedback=el(),minus=el(),plus=el();input.value='10';input.max='2147483647';input.disabled=disabled;snapshot.value='10';save.disabled=disabled;minus.dataset.stockStep='-1';plus.dataset.stockStep='1';
        form.querySelector=s=>s==='[name="stock"]'?input:s==='[data-stock-feedback]'?feedback:save;
        form.querySelectorAll=s=>s==='[data-stock-step]'?[minus,plus]:[minus,save,plus];form.reportValidity=()=>true;
        return Object.assign(form,{input,snapshot,save,feedback,minus,plus});
    });
    const windowHandlers={},image=el(),placeholder=el();image.complete=true;image.naturalWidth=0;placeholder.hidden=true;image.parentElement={querySelector:()=>placeholder};
    vm.runInNewContext(source,{document:{querySelectorAll:s=>s==='[data-inventory-stock]'?forms:[image],addEventListener(type,fn){fn();}},window:{addEventListener(type,fn){(windowHandlers[type]??=[]).push(fn);}}});
    const fire=(element,type,extra={})=>{const event={preventDefault(){this.prevented=true;},...extra};element.handlers[type]?.(event);return event;};
    return {forms,form:forms[0],windowHandlers,image,placeholder,fire};
}
test('plus and minus edit the intended quantity without submitting or changing snapshot',()=>{
    const h=harness();h.fire(h.form.plus,'click');assert.equal(h.form.input.value,'11');assert.equal(h.form.snapshot.value,'10');h.fire(h.form.minus,'click');assert.equal(h.form.input.value,'10');assert.match(h.form.feedback.textContent,/Unsaved/);assert.equal(h.forms[1].input.value,'10');
});
test('quantity boundaries disable stepping and invalid input cannot submit',()=>{
    const h=harness();h.form.input.value='0';h.fire(h.form.input,'input');assert.equal(h.form.minus.disabled,true);h.fire(h.form.minus,'click');assert.equal(h.form.input.value,'0');
    h.form.input.value=h.form.input.max;h.fire(h.form.input,'input');assert.equal(h.form.plus.disabled,true);
    for(const invalid of ['-1','1.5','bad','2147483648','']){h.form.input.value=invalid;assert.equal(h.fire(h.form,'submit').prevented,true);assert.match(h.form.feedback.textContent,/whole number/);}
});
test('save keeps payload inputs enabled, blocks duplicates across responsive forms, and shows pending only',()=>{
    const h=harness();assert.equal(h.fire(h.form,'submit').prevented,undefined);assert.equal(h.form.input.disabled,false);assert.equal(h.form.snapshot.disabled,false);assert.equal(h.form.save.disabled,true);assert.match(h.form.feedback.textContent,/Saving/);assert.equal(h.fire(h.forms[1],'submit').prevented,true);
    h.windowHandlers.pageshow.forEach(fn=>fn());assert.equal(h.form.save.disabled,false);assert.equal(h.form.feedback.textContent,'');assert.equal(h.forms[1].plus.disabled,false);
});
test('Enter in quantity moves focus to Save rather than submitting unexpectedly',()=>{
    const h=harness();assert.equal(h.fire(h.form.input,'keydown',{key:'Enter'}).prevented,true);assert.equal(h.form.save.focused,true);
});
test('read-only legacy controls stay disabled and never submit',()=>{
    const h=harness(true);assert.equal(h.form.plus.disabled,true);h.fire(h.form.plus,'click');assert.equal(h.form.input.value,'10');assert.equal(h.fire(h.form,'submit').prevented,true);h.windowHandlers.pageshow.forEach(fn=>fn());assert.equal(h.form.save.disabled,true);
});
test('broken and cached missing images reveal the existing bounded placeholder',()=>{
    const h=harness();assert.equal(h.image.hidden,true);assert.equal(h.placeholder.hidden,false);h.image.hidden=false;h.fire(h.image,'error');assert.equal(h.image.hidden,true);
});
