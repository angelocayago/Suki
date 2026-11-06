const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),vm=require('node:vm'),path=require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/messages/partials/photo-composer.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness(){
    const handlers={},revoked=[],activeUrls=new Set(),file={files:[],disabled:false,addEventListener(t,fn){handlers[t]=fn;},dispatchEvent(e){handlers[e.type]?.();}};
    Object.defineProperty(file,'value',{set(value){if(value==='')file.files=[];}});
    const body={required:true,dispatchEvent(){this.updates=(this.updates??0)+1;}},preview={removeAttribute(){delete this.src;}},selection={},error={},remove={addEventListener(t,fn){this.click=fn;}};
    const form={querySelector:()=>file,querySelectorAll:()=>[file,remove]};
    const root={closest:()=>form,querySelector:s=>({'[data-photo-input]':file,'[data-photo-preview]':preview,'[data-photo-selection]':selection,'[data-photo-error]':error,'[data-photo-remove]':remove}[s])};
    form.querySelector=s=>s==='textarea[name="body"]'?body:file;
    const ready=[],lifecycle={},window={addEventListener(type,fn){lifecycle[type]=fn;}};
    class Data {constructor(){this.data={};}set(k,v){this.data[k]=v;}}
    vm.runInNewContext(source,{window,document:{addEventListener(t,fn){ready.push(fn);},querySelectorAll:()=>[root]},URL:{createObjectURL(){activeUrls.add('blob:preview');return 'blob:preview';},revokeObjectURL(u){revoked.push(u);activeUrls.delete(u);}},Event:class{constructor(type){this.type=type;}},FormData:Data});
    ready.forEach(fn=>fn());return {api:window.SukiChatPhoto,file,body,preview,selection,error,remove,form,revoked,lifecycle,previewValid:()=>activeUrls.has(preview.src),change(photo){file.files=[photo];handlers.change();}};
}
test('photo selection previews and permits photo-only messages; removal restores text requirement',()=>{
    const h=harness();h.change({type:'image/png',size:100});assert.equal(h.preview.src,'blob:preview');assert.equal(h.body.required,false);assert.equal(h.api.hasPhoto(h.form),true);
    h.remove.click();assert.equal(h.api.hasPhoto(h.form),false);assert.equal(h.body.required,true);assert.deepEqual(h.revoked,['blob:preview']);
});
test('invalid MIME and oversized selections are rejected visibly before send',()=>{
    for(const photo of [{type:'image/svg+xml',size:100},{type:'image/jpeg',size:5242881}]){const h=harness();h.change(photo);assert.equal(h.api.hasPhoto(h.form),false);assert.equal(h.error.hidden,false);assert.equal(h.body.required,true);}
});
test('multipart payload preserves empty text and busy controls prevent file changes',()=>{
    const h=harness();const payload=h.api.payload(h.form,'');assert.equal(payload.data.body,'');h.api.busy(h.form,true);assert.equal(h.file.disabled,true);h.api.busy(h.form,false);assert.equal(h.file.disabled,false);
});

test('replacing a selection releases its old preview and restoring a cached page rebuilds the preview',()=>{
    const h=harness();h.change({type:'image/png',size:100});h.change({type:'image/jpeg',size:200});
    assert.deepEqual(h.revoked,['blob:preview']);assert.equal(h.body.required,false);
    h.lifecycle.pagehide();assert.deepEqual(h.revoked,['blob:preview','blob:preview']);
    h.lifecycle.pageshow?.({persisted:true});assert.equal(h.previewValid(),true);assert.equal(h.selection.hidden,false);assert.equal(h.api.hasPhoto(h.form),true);
    assert.deepEqual(h.revoked,['blob:preview','blob:preview']);
});

test('both portals place the full-width photo panel in a shared responsive composer grid',()=>{
    const read=file=>fs.readFileSync(path.join(__dirname,'../../resources/views/',file),'utf8');
    for(const file of ['messages/show.blade.php','seller/messages/partials/thread.blade.php']){
        const template=read(file);assert.match(template,/class="suki-chat-composer"/);assert.match(template,/@include\('messages.partials.photo-composer'\)/);
        assert.ok(template.indexOf("@include('messages.partials.photo-composer')")<template.indexOf('name="body"'));
    }
    const css=read('messages/partials/attachment-style.blade.php');
    assert.match(css,/form\.suki-chat-composer\{display:grid;grid-template-columns:minmax\(0,1fr\) auto/);
    assert.match(css,/>\[data-photo-composer\]\{grid-column:1\/-1;min-width:0/);
    assert.match(css,/@media\(max-width:540px\)\{form\.suki-chat-composer\{grid-template-columns:minmax\(0,1fr\)\}/);
    assert.match(css,/\[data-photo-preview\]\{[^}]*max-width:100%;max-height:96px/);
    assert.doesNotMatch(css,/#messageComposer\[data-buyer-composer\]\{flex-direction:row/);
    assert.doesNotMatch(read('seller/messages/style.blade.php'),/\.inbox-composer form\{display:flex/);
});
