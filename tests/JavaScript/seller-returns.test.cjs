const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/returns/script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const ids={},handlers={},calls=[],replies=[];let focused=null,reloads=0;
    for(const id of ['rrRecommendation','rrConfirm','rrSubmit','rrConfirmSubmit','rrCancel','rrRecommendationValue','rrNote','rrSubmitError','rrSubmitStatus','rrConfirmChoice']){
        ids[id]={handlers:{},value:'',hidden:true,disabled:false,textContent:'',attributes:{},open:false,
            addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},
            setAttribute(key,value){this.attributes[key]=value;},focus(){focused=this;},
        };
    }
    const form=ids.rrRecommendation,dialog=ids.rrConfirm;
    form.action='https://shop.test/seller/return-refunds/1';form.valid=true;form.reportValidity=()=>form.valid;
    form.querySelector=()=>({value:'csrf-test'});ids.rrRecommendationValue.value='APPROVE';ids.rrNote.value='Evidence reviewed';
    dialog.showModal=()=>dialog.open=true;dialog.close=()=>{dialog.open=false;for(const fn of dialog.handlers.close??[])fn();};
    vm.runInNewContext(source,{document:{getElementById:id=>ids[id],addEventListener(type,fn){handlers[type]=fn;}},
        window:{location:{reload(){reloads++;}}},async fetch(url,options){calls.push({url,options});const reply=replies.shift();if(reply instanceof Error)throw reply;return await reply;},
    });
    handlers.DOMContentLoaded();
    const fire=async(node,type)=>{const event={preventDefault(){this.prevented=true;}};for(const fn of node.handlers[type]??[])await fn(event);return event;};
    return{ids,form,dialog,calls,replies,fire,focused:()=>focused,reloads:()=>reloads};
}

test('recommendation requires valid fields and confirmation; cancel restores focus without submitting',async()=>{
    const h=harness();h.form.valid=false;await h.fire(h.form,'submit');assert.equal(h.dialog.open,false);assert.equal(h.calls.length,0);
    h.form.valid=true;await h.fire(h.form,'submit');assert.equal(h.dialog.open,true);assert.equal(h.focused(),h.ids.rrCancel);
    assert.equal(h.ids.rrConfirmChoice.textContent,'Recommend approval');assert.equal(h.calls.length,0);
    await h.fire(h.ids.rrCancel,'click');assert.equal(h.dialog.open,false);assert.equal(h.focused(),h.ids.rrSubmit);
    h.ids.rrRecommendationValue.value='REJECT';await h.fire(h.form,'submit');assert.equal(h.ids.rrConfirmChoice.textContent,'Recommend rejection');
});

test('confirmed recommendation uses canonical PATCH with CSRF and blocks duplicate or interrupted submissions',async()=>{
    const h=harness();let release;h.replies.push(new Promise(resolve=>release=resolve));await h.fire(h.form,'submit');
    const sending=h.fire(h.ids.rrConfirmSubmit,'click');await new Promise(resolve=>setImmediate(resolve));
    assert.equal(h.ids.rrSubmit.disabled,true);assert.equal(h.form.attributes['aria-busy'],'true');
    await h.fire(h.ids.rrConfirmSubmit,'click');await h.fire(h.form,'submit');await h.fire(h.ids.rrCancel,'click');
    assert.equal(h.calls.length,1);assert.equal(h.dialog.open,true);assert.equal((await h.fire(h.dialog,'cancel')).prevented,true);
    const {url,options}=h.calls[0];assert.equal(url,h.form.action);assert.equal(options.method,'PATCH');assert.equal(options.credentials,'same-origin');
    assert.equal(options.headers['X-CSRF-TOKEN'],'csrf-test');assert.deepEqual(JSON.parse(options.body),{recommendation:'APPROVE',note:'Evidence reviewed'});
    release({ok:true,status:200});await sending;assert.equal(h.reloads(),1);assert.match(h.ids.rrSubmitStatus.textContent,/Recommendation submitted/);
    await h.fire(h.ids.rrConfirmSubmit,'click');assert.equal(h.calls.length,1);
});

test('validation feedback uses text, preserves explanation and permits correction',async()=>{
    const h=harness();h.replies.push({ok:false,status:422,json:async()=>({errors:{note:['<b>Explanation required</b>']}})});
    await h.fire(h.form,'submit');await h.fire(h.ids.rrConfirmSubmit,'click');
    assert.equal(h.ids.rrSubmitError.hidden,false);assert.equal(h.ids.rrSubmitError.textContent,'<b>Explanation required</b>');
    assert.equal(h.ids.rrNote.value,'Evidence reviewed');assert.equal(h.ids.rrNote.disabled,false);assert.equal(h.dialog.open,false);assert.equal(h.reloads(),0);
});

test('ambiguous failures preserve draft and do not retry automatically; expired access remains visible',async()=>{
    const h=harness();h.replies.push(new Error('Disconnected'));await h.fire(h.form,'submit');await h.fire(h.ids.rrConfirmSubmit,'click');
    assert.equal(h.calls.length,1);assert.equal(h.ids.rrNote.value,'Evidence reviewed');assert.match(h.ids.rrSubmitError.textContent,/Refresh to check/);
    h.replies.push({ok:false,status:419});await h.fire(h.form,'submit');await h.fire(h.ids.rrConfirmSubmit,'click');
    assert.match(h.ids.rrSubmitError.textContent,/session or access changed/);assert.equal(h.reloads(),0);
});
