const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/messages/partials/attachment-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const document={activeElement:null,handlers:{}};
    function element(tag='div') {
        const node={tag,children:[],dataset:{},handlers:{},className:'',hidden:false,disabled:false,value:'',textContent:'',attributes:{},parentElement:null,style:{},scrollHeight:42,
            addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},setAttribute(k,v){this.attributes[k]=v;},
            focus(){document.activeElement=this;},click(){this.clicked=(this.clicked??0)+1;},
            appendChild(child){child.parentElement=this;this.children.push(child);return child;},append(...children){children.forEach(c=>this.appendChild(c));},
            insertBefore(child,before){child.parentElement=this;const index=before?this.children.indexOf(before):-1;if(index<0)this.children.push(child);else this.children.splice(index,0,child);},
            remove(){if(this.parentElement)this.parentElement.children=this.parentElement.children.filter(c=>c!==this);this.parentElement=null;},
            replaceChildren(){this.children=[];},contains(child){while(child){if(child===this)return true;child=child.parentElement;}return false;},
            querySelectorAll(selector){const matches=node=>{const attr=selector.match(/^\[data-([\w-]+)(?:="([^"]*)")?\]$/);if(attr){const key=attr[1].replace(/-([a-z])/g,(_,c)=>c.toUpperCase());return Object.hasOwn(node.dataset,key)&&(attr[2]===undefined||node.dataset[key]===attr[2]);}return selector.startsWith('.')?node.classList.contains(selector.slice(1)):selector.startsWith('#')?node.id===selector.slice(1):node.tag===selector;};const result=[];const walk=root=>{for(const child of root.children){if(matches(child))result.push(child);walk(child);}};walk(this);return result;},
            querySelector(selector){return this.querySelectorAll(selector)[0]??null;},
        };
        node.classList={contains:c=>node.className.split(' ').includes(c),add(c){if(!this.contains(c))node.className+=' '+c;},remove(c){node.className=node.className.split(' ').filter(v=>v!==c).join(' ');}};
        return node;
    }
    const form=element('form'),target=element();form.id='composer';form.action='https://shop.test/seller/messages/owned/send';
    const dialog=element('dialog');dialog.id='messageImageLightbox';dialog.open=false;dialog.showModal=()=>dialog.open=true;
    dialog.close=()=>{dialog.open=false;for(const fn of dialog.handlers.close??[])fn();};
    const light={};for(const key of ['lightboxClose','lightboxPrev','lightboxNext','lightboxImage','lightboxError','lightboxCount','lightboxDownload']){const node=element(key==='lightboxImage'?'img':key==='lightboxDownload'?'a':'button');node.dataset[key]='';dialog.append(node);light[key]=node;}
    const title=element('h2');title.id='messageImageTitle';dialog.append(title);
    document.getElementById=id=>id==='messageImageLightbox'?dialog:null;
    document.createElement=element;document.querySelector=()=>null;
    document.querySelectorAll=()=>[];document.addEventListener=(type,fn)=>(document.handlers[type]??=[]).push(fn);
    const windowHandlers={};
    const window={location:{origin:'https://shop.test'},addEventListener(type,fn){(windowHandlers[type]??=[]).push(fn);}};
    vm.runInNewContext(source,{document,window,URL});
    const api=window.SukiMessageImages;
    const fire=async(node,type,extra={})=>{const e={preventDefault(){this.prevented=true;},stopPropagation(){this.stopped=true;},...extra};for(const fn of node.handlers[type]??[])await fn(e);return e;};
    return {document,window,element,form,target,dialog,light,title,api,fire};
}
const items=(count=5,reference='owned')=>Array.from({length:count},(_,i)=>({id:i+1,original_name:'<script>photo '+i+'</script>',url:`https://shop.test/seller/messages/${reference}/attachments/${i+1}`,thumbnail_url:`https://shop.test/seller/messages/${reference}/attachments/${i+1}/thumbnail`,download_url:`https://shop.test/seller/messages/${reference}/attachments/${i+1}/download`}));

test('gallery shows four thumbnails and +1; lightbox navigates all images with protected download and focus restoration',async()=>{
    const h=harness();const gallery=h.api.gallery(items(),'owned');assert.equal(gallery.children.length,4);assert.equal(gallery.children[3].querySelector('.message-image-more').textContent,'+1');
    const trigger=gallery.children[3];await h.fire(trigger,'click');assert.equal(h.dialog.open,true);assert.equal(h.document.activeElement,h.light.lightboxClose);assert.equal(h.light.lightboxCount.textContent,'4 / 5');
    await h.fire(h.dialog,'keydown',{key:'ArrowRight'});assert.equal(h.light.lightboxCount.textContent,'5 / 5');assert.match(h.light.lightboxDownload.href,/\/5\/download$/);
    assert.equal(h.title.textContent,'<script>photo 4</script>');assert.equal(h.dialog.querySelectorAll('script').length,0);
    const escape=await h.fire(h.dialog,'keydown',{key:'Escape'});assert.equal(escape.stopped,true);h.dialog.close();assert.equal(h.document.activeElement,trigger);
});

test('foreign conversation or external image URLs are rejected and missing images have a fallback',async()=>{
    const h=harness();assert.equal(h.api.gallery(items(1,'foreign'),'owned'),null);
    const external=items(1);external[0].thumbnail_url='https://evil.test/tracker.png';assert.equal(h.api.gallery(external,'owned'),null);
    const gallery=h.api.gallery(items(1),'owned');const image=gallery.children[0].querySelector('img');await h.fire(image,'error');
    assert.equal(image.hidden,true);assert.equal(gallery.children[0].querySelector('.message-image-fallback').hidden,false);
});

function buyerHarness() {
    const h=harness(),thread=h.element(),input=h.element('textarea'),send=h.element('button'),error=h.element('p');
    h.form.id='messageComposer';h.form.action='https://shop.test/buyer/messages/owned/send';h.target.append(thread,h.form);h.form.append(input,send);
    Object.assign(thread.dataset,{pollUrl:'https://shop.test/buyer/messages/owned/poll',readUrl:'https://shop.test/buyer/messages/owned/read',reference:'owned',currentUser:'22',lastMessageId:'0'});
    const ids={'message-thread':thread,messageComposer:h.form,'message-body':input,messageSend:send,buyerMessageError:error,messageImageLightbox:h.dialog};
    h.document.getElementById=id=>ids[id]??null;h.document.hidden=false;
    // Select the Buyer branch for this JS unit harness. Real Blade compilation
    // and enabled/disabled Buyer markup are covered by isolated PHP tests.
    const template=fs.readFileSync(path.join(__dirname,'../../resources/views/messages/show.blade.php'),'utf8');
    const script=template.match(/<script>\s*document\.addEventListener[\s\S]*?<\/script>/)[0].replace(/^<script>|<\/script>$/g,'')
        .replace('@json(csrf_token())','"buyer-csrf"').replaceAll("@if($portal['name']==='buyer')",'').replaceAll('@endif','').replace("{{ $isActive ? 'false' : 'true' }}",'false');
    const calls=[],replies=[];let submits=0;h.form.requestSubmit=()=>{submits++;return h.fire(h.form,'submit');};vm.runInNewContext(script,{document:h.document,window:h.window,URL,setTimeout(){return 1;},clearTimeout(){},
        async fetch(url,options){calls.push({url:String(url),options});if(String(url).endsWith('/send')){const next=replies.shift();if(next instanceof Error)throw next;return await next;}return{ok:true,status:200,json:async()=>({messages:[],conversation_status:'ACTIVE'})};}});
    for(const callback of h.document.handlers.DOMContentLoaded)callback();
    return{...h,thread,input,send,error,calls,replies,submits:()=>submits};
}

const sentMessage={id:1,body:'Text message',sender:{id:22,name:'Buyer',role_context:'BUYER'},sent_at:'2026-11-01T01:00:00+08:00'};
const success=()=>({ok:true,status:201,json:async()=>({message:sentMessage})});

test('Buyer text send uses JSON, renders the response and preserves the polling cursor race fix',async()=>{
    const h=buyerHarness();assert.equal(h.send.disabled,true);assert.equal(h.api.attach,undefined);assert.equal(h.api.validate,undefined);
    h.input.value='Text message';await h.fire(h.input,'input');assert.equal(h.send.disabled,false);
    h.replies.push(success());await h.fire(h.form,'submit');
    const request=h.calls.find(c=>c.url.endsWith('/send'));assert.equal(request.options.headers['Content-Type'],'application/json');
    assert.equal(request.options.headers['X-CSRF-TOKEN'],'buyer-csrf');assert.deepEqual(JSON.parse(request.options.body),{body:'Text message'});
    assert.equal(h.thread.querySelectorAll('[data-message-id]').length,1);assert.equal(h.input.value,'');assert.equal(h.send.disabled,true);
    assert.equal(new URL(h.calls.find(c=>c.url.includes('/poll')).url).searchParams.get('after_id'),'0');
    for(const target of [h.target,h.thread,h.form,h.input])for(const event of ['paste','drop','dragover'])assert.equal(target.handlers[event],undefined);
});

test('Buyer text validation and ambiguous failures retain the draft',async()=>{
    const h=buyerHarness();h.input.value='Keep text';h.replies.push(new Error('Disconnected'));await h.fire(h.form,'submit');
    assert.equal(h.input.value,'Keep text');assert.match(h.error.textContent,/may have been sent/);
    h.replies.push({ok:false,status:422,json:async()=>({errors:{body:['Validation failed']}})});await h.fire(h.form,'submit');
    assert.equal(h.input.value,'Keep text');assert.equal(h.error.textContent,'Validation failed');
    h.input.value='  ';await h.fire(h.input,'input');assert.equal(h.send.disabled,true);const count=h.calls.length;await h.fire(h.form,'submit');assert.equal(h.calls.length,count);
});

test('Buyer Enter sends once, Shift+Enter and IME stay intact, pending sends block duplicates',async()=>{
    const h=buyerHarness();h.input.value='Text message';await h.fire(h.input,'input');
    await h.fire(h.input,'keydown',{key:'Enter',shiftKey:true});await h.fire(h.input,'keydown',{key:'Enter',isComposing:true});assert.equal(h.submits(),0);
    let release;h.replies.push(new Promise(resolve=>release=resolve));const sending=h.fire(h.input,'keydown',{key:'Enter'});await new Promise(resolve=>setImmediate(resolve));
    assert.equal(h.submits(),1);assert.equal(h.send.disabled,true);await h.fire(h.form,'submit');assert.equal(h.calls.filter(c=>c.url.endsWith('/send')).length,1);
    release(success());await sending;await new Promise(resolve=>setImmediate(resolve));assert.equal(h.input.value,'');
});

test('Buyer composer autogrows to five lines then scrolls and shrinks cleanly',async()=>{
    const h=buyerHarness();assert.equal(h.input.style.height,'44px');assert.equal(h.input.style.overflowY,'hidden');
    h.input.value='Lines';h.input.scrollHeight=122;await h.fire(h.input,'input');assert.equal(h.input.style.height,'124px');assert.equal(h.input.style.overflowY,'hidden');
    h.input.scrollHeight=182;await h.fire(h.input,'input');assert.equal(h.input.style.height,'124px');assert.equal(h.input.style.overflowY,'auto');
    h.input.value='';h.input.scrollHeight=42;await h.fire(h.input,'input');assert.equal(h.input.style.height,'44px');assert.equal(h.input.style.overflowY,'hidden');
});
