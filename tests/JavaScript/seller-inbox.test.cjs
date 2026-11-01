const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/messages/script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const message=(id,body='Incoming',sender='22',day='2026-11-01')=>({id:Number(id),body,sent_at:day+'T01:00:00+08:00',sender:{id:Number(sender),name:'Buyer Gagsti',role_context:sender==='11'?'SELLER':'BUYER'},display:{id:String(id),sender_id:sender,day,day_label:'Today',time:'1:00 AM',relative:'1h ago',full_time:'Nov 1, 2026 1:00 AM Manila'}});

function harness({hidden=false,initial=[1,2,3,4,5,6,7,8,9,10],cursor=String(initial.at(-1)??0),imageApi=null}={}) {
    const document={hidden,handlers:{}};
    function element(tag='div') {
        const node={tag,children:[],parentElement:null,dataset:{},handlers:{},className:'',style:{setProperty(){}},hidden:false,disabled:false,textContent:'',value:'',removed:false,
            addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},
            appendChild(child){if(child.parentElement)child.remove();child.removed=false;child.parentElement=this;this.children.push(child);return child;},
            append(...children){children.forEach(child=>this.appendChild(child));},
            insertBefore(child,before){if(child.parentElement)child.remove();child.removed=false;child.parentElement=this;const index=before?this.children.indexOf(before):-1;if(index<0)this.children.push(child);else this.children.splice(index,0,child);},
            remove(){if(this.parentElement){const i=this.parentElement.children.indexOf(this);if(i>=0)this.parentElement.children.splice(i,1);}this.parentElement=null;this.removed=true;},
            querySelectorAll(selector){const results=[];const matches=child=>selector.startsWith('[')?Object.hasOwn(child.dataset,selector.slice(6,-1).replace(/-([a-z])/g,(_,c)=>c.toUpperCase())):selector.startsWith('.')?selector.slice(1).split('.').every(c=>child.classList.contains(c)):child.tag===selector;const walk=parent=>{for(const child of parent.children){if(matches(child))results.push(child);walk(child);}};walk(this);return results;},
            querySelector(selector){return this.querySelectorAll(selector)[0]??null;},
            getBoundingClientRect(){return{top:100};},getClientRects(){return[1];},setAttribute(key,value){(this.attributes??={})[key]=value;},
        };
        node.classList={contains:c=>node.className.split(' ').includes(c),add(c){if(!this.contains(c))node.className+=' '+c;},remove(c){node.className=node.className.split(' ').filter(x=>x!==c).join(' ');},toggle(c,force){const yes=force??!this.contains(c);if(yes)this.add(c);else this.remove(c);return yes;}};
        return node;
    }
    const ids={};for(const id of ['sellerInbox','inboxThread','inboxHistory','inboxMessages','inboxLoadOlder','inboxOlderStatus','inboxNewMessages','inboxComposer','inboxBody','inboxSend','inboxSendError','inboxPollNotice','inboxPollError','inboxRetry','inboxClosed','inboxConversationStatus'])ids[id]=element();
    const {sellerInbox:inbox,inboxThread:thread,inboxHistory:history,inboxMessages:messages,inboxComposer:form,inboxBody:body,inboxSend:send}=ids;
    inbox.append(thread);thread.append(history,form);history.append(messages);form.append(body,send);send.append(element('span'));
    Object.assign(thread.dataset,{lastId:cursor,userId:'11',status:'ACTIVE',pollUrl:'https://shop.test/seller/messages/owned/poll',readUrl:'https://shop.test/seller/messages/owned/read',csrf:'csrf-fixture'});form.action='https://shop.test/seller/messages/owned/send';body.scrollHeight=42;
    const selected=element('a'),badge=element('span'),preview=element('span');selected.className='inbox-row is-selected is-unread';badge.dataset.inboxUnread='';preview.className='inbox-row-preview';selected.append(badge,preview);inbox.append(selected);
    ids.inboxNewMessages.hidden=true;ids.inboxPollNotice.hidden=true;
    for(const id of initial){const row=element('article');row.className='inbox-message';Object.assign(row.dataset,{messageId:String(id),senderId:'22',day:'2026-11-01',dayLabel:'Today'});messages.append(row);}
    history.clientHeight=100;let top=0;
    Object.defineProperty(history,'scrollHeight',{get:()=>messages.querySelectorAll('[data-message-id]').length*50+messages.querySelectorAll('[data-date]').length*20});
    Object.defineProperty(history,'scrollTop',{get:()=>top,set:value=>top=Math.max(0,Math.min(value,Math.max(0,history.scrollHeight-history.clientHeight)))});
    document.getElementById=id=>ids[id]?.removed?null:ids[id];document.createElement=element;
    document.addEventListener=(type,fn)=>{if(type==='DOMContentLoaded')fn();else(document.handlers[type]??=[]).push(fn);};
    const timers=new Map();let timerId=0;const calls=[],replies={poll:[],send:[],read:[]},windowHandlers={};
    const response=(data={},status=200)=>({ok:status>=200&&status<300,status,json:async()=>data});
    const fire=async(node,type,extra={})=>{const event={preventDefault(){this.prevented=true;},...extra};for(const fn of node.handlers[type]??[])await fn(event);return event;};
    let submits=0;form.requestSubmit=()=>{submits++;return fire(form,'submit');};
    vm.runInNewContext(source,{document,URL,window:{SukiMessageImages:imageApi,innerHeight:900,visualViewport:null,location:{origin:'https://shop.test'},addEventListener(type,fn){(windowHandlers[type]??=[]).push(fn);}},
        setTimeout(fn,delay){const id=++timerId;timers.set(id,{fn,delay});return id;},clearTimeout(id){timers.delete(id);},
        async fetch(url,options){const kind=new URL(url).pathname.split('/').at(-1);calls.push({url:String(url),options,kind});const next=replies[kind].shift();if(next instanceof Error)throw next;return next??response(kind==='poll'?{messages:[],conversation_status:'ACTIVE'}:{},kind==='read'?204:200);},
    });
    return{ids,document,inbox,thread,history,messages,form,body,send,selected,badge,preview,calls,replies,timers,fire,response,windowHandlers,
        rows:()=>messages.querySelectorAll('[data-message-id]'),flush:()=>new Promise(resolve=>setImmediate(resolve)),submits:()=>submits,
        async poll(data,status=200){replies.poll.push(response(data,status));await fire(ids.inboxRetry,'click');},
        async sendText(text,reply){body.value=text;await fire(body,'input');if(reply)replies.send.push(reply);await fire(form,'submit');},
        async visibility(value){document.hidden=value;await fire(document,'visibilitychange');},
    };
}

test('read state is posted only for the selected visible bottom; hidden pages pause polling',async()=>{
    const hidden=harness({hidden:true});await hidden.flush();assert.equal(hidden.calls.length,0);assert.equal(hidden.timers.size,0);
    const h=harness();await h.flush();assert.equal(h.calls.filter(c=>c.kind==='read').length,1);assert.equal(h.badge.hidden,true);
    h.history.scrollTop=0;await h.poll({messages:[message(11)],conversation_status:'ACTIVE'});
    assert.equal(h.history.scrollTop,0);assert.equal(h.ids.inboxNewMessages.hidden,false);assert.equal(h.calls.filter(c=>c.kind==='read').length,1);
    assert.equal(h.badge.hidden,false);assert.equal(h.badge.textContent,'1');assert.equal(h.selected.classList.contains('is-unread'),true);
    await h.fire(h.ids.inboxNewMessages,'click');await h.flush();assert.equal(h.calls.filter(c=>c.kind==='read').length,2);assert.equal(h.ids.inboxNewMessages.hidden,true);
    await h.visibility(true);assert.equal(h.timers.size,0);await h.poll({messages:[message(12)],conversation_status:'ACTIVE'});assert.equal(h.rows().length,11);
});

test('incoming batches append once, maintain chronological IDs, group senders and safely insert text',async()=>{
    const h=harness();await h.flush();const payload={messages:[message(11,'<img src=x onerror=alert(1)>'),message(12,'Line 1\nLine 2')],conversation_status:'ACTIVE'};
    await h.poll(payload);await h.poll(payload);
    assert.deepEqual(h.rows().map(n=>n.dataset.messageId),Array.from({length:12},(_,i)=>String(i+1)));
    assert.equal(h.rows()[10].querySelector('p').textContent,'<img src=x onerror=alert(1)>');assert.equal(h.rows()[10].querySelectorAll('img').length,0);
    assert.equal(h.rows()[11].classList.contains('is-grouped'),true);assert.equal(h.messages.querySelectorAll('[data-date]').length,1);
    assert.equal(h.preview.textContent,'Line 1\nLine 2');
    assert.equal(new URL(h.calls.filter(c=>c.kind==='poll').at(-1).url).searchParams.get('after_id'),'12');
});

test('older history prepends without losing the visual scroll position or advancing the forward cursor',async()=>{
    const h=harness({initial:[51,52,53,54]});await h.flush();h.history.scrollTop=30;
    const oldHeight=h.history.scrollHeight,oldTop=h.history.scrollTop;
    h.replies.poll.push(h.response({messages:[message(49),message(50)],has_older:false,conversation_status:'ACTIVE'}));
    await h.fire(h.ids.inboxLoadOlder,'click');
    assert.deepEqual(h.rows().map(n=>n.dataset.messageId),['49','50','51','52','53','54']);
    assert.equal(h.history.scrollTop,oldTop+h.history.scrollHeight-oldHeight);assert.equal(h.ids.inboxLoadOlder.hidden,true);
    await h.poll({messages:[],conversation_status:'ACTIVE'});assert.equal(new URL(h.calls.filter(c=>c.kind==='poll').at(-1).url).searchParams.get('after_id'),'54');
});

test('send/poll race cannot skip incoming IDs between the previous cursor and the sent message',async()=>{
    const h=harness();await h.flush();h.replies.poll.push(h.response({messages:[message(11,'Incoming first'),message(12,'Sent','11')],conversation_status:'ACTIVE'}));
    await h.sendText('Sent',h.response({message:message(12,'Sent','11')},201));
    assert.deepEqual(h.rows().slice(-2).map(n=>n.dataset.messageId),['11','12']);assert.equal(h.rows().length,12);
    assert.equal(new URL(h.calls.find(c=>c.kind==='poll').url).searchParams.get('after_id'),'10');assert.equal(h.body.value,'');
    assert.equal(h.calls.filter(c=>c.kind==='send').length,1);assert.equal(h.calls.find(c=>c.kind==='send').options.headers['X-CSRF-TOKEN'],'csrf-fixture');
});

test('ambiguous failure retains draft without optimistic messages; validation and closed state remain functional',async()=>{
    const h=harness();await h.flush();h.replies.send.push(new Error('Disconnected'));
    await h.sendText('Keep draft');assert.equal(h.body.value,'Keep draft');assert.equal(h.rows().length,10);assert.match(h.ids.inboxSendError.textContent,/may have been sent/);
    await h.sendText('Invalid',h.response({errors:{body:['Validation failed']}},422));assert.equal(h.ids.inboxSendError.textContent,'Validation failed');assert.equal(h.body.value,'Invalid');
    await h.poll({messages:[],conversation_status:'ARCHIVED'});assert.equal(h.form.hidden,true);assert.equal(h.send.disabled,true);assert.match(h.ids.inboxClosed.textContent,/archived/);
});

test('authorization errors stop polling and sending; temporary failures use backoff',async()=>{
    const h=harness();await h.flush();await h.poll({},500);assert.equal(h.ids.inboxPollNotice.hidden,false);assert.ok([...h.timers.values()].some(t=>t.delay===15000));
    await h.poll({},403);assert.equal(h.send.disabled,true);assert.equal(h.ids.inboxRetry.hidden,true);assert.equal(h.timers.size,0);
    const count=h.calls.length;await h.poll({messages:[],conversation_status:'ACTIVE'});assert.equal(h.calls.length,count);
});

test('Enter sends once; Shift+Enter and composition preserve newlines; whitespace disables Send',async()=>{
    const h=harness();await h.flush();h.body.value='   ';await h.fire(h.body,'input');assert.equal(h.send.disabled,true);
    h.body.value='Text';await h.fire(h.body,'input');await h.fire(h.body,'keydown',{key:'Enter',shiftKey:true});await h.fire(h.body,'keydown',{key:'Enter',isComposing:true});assert.equal(h.submits(),0);
    h.replies.send.push(h.response({message:message(11,'Text','11')},201));const e=await h.fire(h.body,'keydown',{key:'Enter'});await h.flush();assert.equal(e.prevented,true);assert.equal(h.submits(),1);
});

test('Seller cursor metadata preserves large IDs without rounding',async()=>{
    const id='9007199254740993';const h=harness({initial:[id],cursor:id});await h.flush();
    await h.poll({messages:[message('9007199254740994')],conversation_status:'ACTIVE'});
    assert.deepEqual(h.rows().map(n=>n.dataset.messageId),[id,'9007199254740994']);
});

test('duplicate submit events stay blocked while a send is pending',async()=>{
    const h=harness();await h.flush();let release;
    h.replies.send.push(new Promise(resolve=>release=resolve));
    const sending=h.sendText('Once');await h.flush();
    assert.equal(h.send.disabled,true);await h.fire(h.form,'submit');
    assert.equal(h.calls.filter(c=>c.kind==='send').length,1);
    release(h.response({message:message(11,'Once','11')},201));await sending;
    assert.equal(h.rows().filter(r=>r.dataset.messageId==='11').length,1);
});

test('full polling batches schedule immediate catchup without jumping over messages',async()=>{
    const h=harness();await h.flush();await h.poll({messages:Array.from({length:100},(_,i)=>message(i+11)),conversation_status:'ACTIVE'});
    assert.equal(h.rows().length,110);assert.ok([...h.timers.values()].some(t=>t.delay===0));
    await h.poll({messages:[message(111)],conversation_status:'ACTIVE'});
    assert.equal(new URL(h.calls.filter(c=>c.kind==='poll').at(-1).url).searchParams.get('after_id'),'110');assert.equal(h.rows().length,111);
});

test('back/forward cache restores polling without resetting authorization failures',async()=>{
    const h=harness();await h.flush();h.windowHandlers.pagehide[0]();assert.equal(h.timers.size,0);
    const count=h.calls.length;await h.poll({messages:[],conversation_status:'ACTIVE'});assert.equal(h.calls.length,count);
    h.windowHandlers.pageshow[0]({persisted:true});await h.flush();assert.ok(h.calls.length>count);
    await h.poll({},403);const denied=h.calls.length;h.windowHandlers.pagehide[0]();h.windowHandlers.pageshow[0]({persisted:true});await h.flush();assert.equal(h.calls.length,denied);
});

test('composer accounts for borders, grows to five lines, then scrolls and shrinks cleanly',async()=>{
    const h=harness();await h.flush();
    assert.equal(h.body.style.height,'44px');assert.equal(h.body.style.overflowY,'hidden');
    h.body.value='Two lines\nSecond line';h.body.scrollHeight=62;await h.fire(h.body,'input');
    assert.equal(h.body.style.height,'64px');assert.equal(h.body.style.overflowY,'hidden');
    h.body.scrollHeight=122;await h.fire(h.body,'input');assert.equal(h.body.style.height,'124px');assert.equal(h.body.style.overflowY,'hidden');
    h.body.scrollHeight=182;h.body.scrollTop=20;await h.fire(h.body,'input');
    assert.equal(h.body.style.height,'124px');assert.equal(h.body.style.overflowY,'auto');assert.equal(h.body.scrollTop,20);
    h.body.value='';h.body.scrollHeight=42;await h.fire(h.body,'input');
    assert.equal(h.body.style.height,'44px');assert.equal(h.body.style.overflowY,'hidden');assert.equal(h.body.scrollTop,0);assert.equal(h.send.disabled,true);
});

test('Seller stays text-only even if an upload adapter is present',async()=>{
    let mounted=false;const imageApi={attach(){mounted=true;throw Error('Uploads must not mount');}};
    const h=harness({imageApi});await h.flush();assert.equal(mounted,false);assert.equal(h.send.disabled,true);
    await h.sendText('');assert.equal(h.calls.filter(c=>c.kind==='send').length,0);
    await h.sendText('Text only',h.response({message:message(11,'Text only','11')},201));
    const request=h.calls.find(c=>c.kind==='send');assert.equal(request.options.headers['Content-Type'],'application/json');
    assert.deepEqual(JSON.parse(request.options.body),{body:'Text only'});assert.equal(h.body.value,'');
    for(const event of ['paste','drop','dragover'])assert.equal(h.thread.handlers[event],undefined);
});
