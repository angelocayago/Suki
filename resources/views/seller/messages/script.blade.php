<script>
document.addEventListener('DOMContentLoaded', () => {
    const inbox=document.getElementById('sellerInbox');
    if (!inbox) return;
    function fitViewport() {
        const viewport=window.visualViewport;
        const height=(viewport?.height ?? window.innerHeight)+(viewport?.offsetTop ?? 0)-inbox.getBoundingClientRect().top-16;
        inbox.style.setProperty('--inbox-height',Math.max(window.innerWidth<=800 ? 160 : 240,height)+'px');
    }
    fitViewport(); window.addEventListener('resize',fitViewport);
    window.visualViewport?.addEventListener('resize',fitViewport);
    window.visualViewport?.addEventListener('scroll',fitViewport);
    const thread=document.getElementById('inboxThread');
    if (!thread) return;
    const history=document.getElementById('inboxHistory'), messages=document.getElementById('inboxMessages');
    const older=document.getElementById('inboxLoadOlder'), olderStatus=document.getElementById('inboxOlderStatus');
    const newMessages=document.getElementById('inboxNewMessages');
    const form=document.getElementById('inboxComposer'), body=document.getElementById('inboxBody'), send=document.getElementById('inboxSend');
    const sendError=document.getElementById('inboxSendError'), notice=document.getElementById('inboxPollNotice'), pollError=document.getElementById('inboxPollError');
    const retry=document.getElementById('inboxRetry'), closed=document.getElementById('inboxClosed'), status=document.getElementById('inboxConversationStatus');
    let cursor=BigInt(thread.dataset.lastId), readThrough=BigInt(thread.dataset.readId ?? '0'), polling=false, reading=false, loadingOlder=false, sending=false, stopped=false, paused=false, timer=null;
    const headers={Accept:'application/json','X-CSRF-TOKEN':thread.dataset.csrf};
    const rows=()=>Array.from(messages.querySelectorAll('[data-message-id]'));
    const idOf=message=>String(message.display?.id ?? message.id);
    const nearBottom=()=>history.scrollHeight-history.scrollTop-history.clientHeight<70;
    const visible=()=>!paused && !document.hidden && thread.getClientRects().length>0;
    function updatePreview(message) {
        const selected=inbox.querySelector('.inbox-row.is-selected');
        if (!selected || BigInt(idOf(message))<BigInt(selected.dataset.previewId ?? '0')) return;
        selected.dataset.previewId=idOf(message);
        selected.querySelector('.inbox-row-preview').textContent=message.body;
        const time=selected.querySelector('time');
        if (time) {time.textContent=message.display.relative;time.dateTime=message.sent_at;time.title=message.display.full_time;}
    }
    function setUnread(count) {
        const selected=inbox.querySelector('.inbox-row.is-selected');
        if (!selected) return;
        const badge=selected.querySelector('[data-inbox-unread]');
        selected.classList.toggle('is-unread',count>0);badge.hidden=count===0;
        badge.dataset.count=String(count);badge.textContent=count>99 ? '99+' : String(count);
        badge.setAttribute('aria-label',count+' unread messages');
    }
    function feedback(text) { sendError.textContent=text; sendError.hidden=!text; }
    function syncComposer() {
        const active=thread.dataset.status==='ACTIVE' && !stopped;
        form.hidden=thread.dataset.status!=='ACTIVE'; closed.hidden=thread.dataset.status==='ACTIVE';
        body.disabled=sending || !active; send.disabled=sending || !active || !body.value.trim();
        send.querySelector('span').textContent=sending ? 'Sending…' : 'Send';
        const textareaScroll=body.scrollTop;
        body.style.overflowY='hidden'; body.style.height='auto';
        // scrollHeight includes padding but excludes the two 1px borders.
        // Five 20px lines + 22px padding + 2px border = 124px.
        const naturalHeight=Math.max(44,body.scrollHeight+2);
        body.style.height=Math.min(naturalHeight,124)+'px';
        body.style.overflowY=naturalHeight>124 ? 'auto' : 'hidden';
        body.scrollTop=naturalHeight>124 ? textareaScroll : 0;
    }
    function applyStatus(value) {
        if (!['ACTIVE','CLOSED','ARCHIVED'].includes(value)) return;
        thread.dataset.status=value; status.textContent=value.charAt(0)+value.slice(1).toLowerCase();
        closed.textContent='This conversation is '+value.toLowerCase()+' and cannot receive new messages.';
        syncComposer();
    }
    function stopForAccess(response) {
        if (![401,403,404,419].includes(response.status)) return false;
        stopped=true; clearTimeout(timer); notice.hidden=false; retry.hidden=true;
        pollError.textContent='Conversation access changed or your session expired. Refresh or sign in again.';
        syncComposer(); return true;
    }
    function groupMessages() {
        messages.querySelectorAll('[data-date]').forEach(node=>node.remove());
        let previous=null;
        rows().forEach(row=>{
            const sameDay=previous?.dataset.day===row.dataset.day;
            row.classList.toggle('is-grouped',Boolean(sameDay && previous.dataset.senderId===row.dataset.senderId));
            if (!sameDay) {
                const date=document.createElement('div');date.className='inbox-date';date.dataset.date=row.dataset.day;date.textContent=row.dataset.dayLabel;
                messages.insertBefore(date,row);
            }
            previous=row;
        });
    }
    function merge(batch) {
        let added=0;
        for (const message of batch) {
            const id=idOf(message);
            if (!/^[0-9]+$/.test(id) || !message.display || !message.sender) throw new Error('Invalid message response');
            const existing=rows();
            if (existing.some(row=>row.dataset.messageId===id)) continue;
            const row=document.createElement('article');const senderId=message.display.sender_id;
            row.className='inbox-message'+(senderId===thread.dataset.userId ? ' is-mine' : '');
            Object.assign(row.dataset,{messageId:id,senderId,day:message.display.day,dayLabel:message.display.day_label});
            const bubble=document.createElement('div');bubble.className='inbox-bubble';
            const meta=document.createElement('div');meta.className='inbox-sender';
            const name=document.createElement('strong');name.textContent=senderId===thread.dataset.userId ? 'You' : message.sender.name;
            const role=document.createElement('span');role.textContent=message.sender.role_context.toLowerCase().replaceAll('_',' ');
            meta.append(name,role);
            const text=document.createElement('p');text.textContent=message.body;
            const time=document.createElement('time');time.textContent=message.display.time;time.dateTime=message.sent_at;time.title='Asia/Manila';
            bubble.append(meta,text,time);row.appendChild(bubble);
            messages.insertBefore(row,existing.find(node=>BigInt(node.dataset.messageId)>BigInt(id)) ?? null);
            document.getElementById('inboxEmptyThread')?.remove();added++;
        }
        if (added) groupMessages();
        return added;
    }
    async function markRead() {
        if (reading || stopped || !visible() || !nearBottom() || cursor<=readThrough || !cursor) return;
        reading=true;const through=cursor;
        try {
            const response=await fetch(thread.dataset.readUrl,{method:'POST',credentials:'same-origin',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({message_id:through.toString()})});
            if (stopForAccess(response) || !response.ok) return;
            readThrough=through;
            setUnread(rows().filter(row=>BigInt(row.dataset.messageId)>readThrough && row.dataset.senderId!==thread.dataset.userId).length);
        } catch (_) { /* Keep the badge until the server acknowledges read state. */ }
        finally {reading=false;}
    }
    function followLatest() {history.scrollTop=history.scrollHeight;newMessages.hidden=true;markRead();}
    function schedule(delay=5000) {clearTimeout(timer);if (!stopped && !paused && !document.hidden) timer=setTimeout(poll,delay);}
    async function poll() {
        if (polling || stopped || paused || document.hidden) return;
        polling=true;let delay=5000;
        try {
            const url=new URL(thread.dataset.pollUrl,window.location.origin);url.searchParams.set('after_id',cursor.toString());
            const response=await fetch(url,{credentials:'same-origin',headers,cache:'no-store'});
            if (stopForAccess(response)) return;
            if (!response.ok) throw new Error('Polling failed');
            const data=await response.json();const follow=nearBottom();
            const existing=new Set(rows().map(row=>row.dataset.messageId));
            const incoming=data.messages.filter(message=>!existing.has(idOf(message)) && BigInt(idOf(message))>readThrough && message.display?.sender_id!==thread.dataset.userId).length;
            const added=merge(data.messages);
            if (incoming) {
                const badge=inbox.querySelector('.inbox-row.is-selected')?.querySelector('[data-inbox-unread]');
                setUnread(Number(badge?.dataset.count ?? 0)+incoming);
            }
            // Only a fetched contiguous batch advances polling. A send response
            // may have a larger ID than an incoming message not fetched yet.
            for (const message of data.messages) {const id=BigInt(idOf(message));if (id>cursor) cursor=id;}
            if (data.messages.length) updatePreview(data.messages[data.messages.length-1]);
            applyStatus(data.conversation_status);notice.hidden=true;
            if (added) {if (follow) followLatest();else newMessages.hidden=false;}
            await markRead();
            if (data.messages.length===100) delay=0; // Drain backlog without skipping IDs.
        } catch (_) {
            notice.hidden=false;retry.hidden=false;pollError.textContent='Live updates paused. Retrying shortly.';delay=15000;
        } finally {polling=false;schedule(delay);}
    }
    older.addEventListener('click',async()=>{
        const oldest=rows()[0]?.dataset.messageId;
        if (!oldest || loadingOlder || stopped) return;
        loadingOlder=true;older.disabled=true;olderStatus.textContent='Loading older messages…';
        try {
            const url=new URL(thread.dataset.pollUrl,window.location.origin);url.searchParams.set('before_id',oldest);
            const response=await fetch(url,{credentials:'same-origin',headers,cache:'no-store'});
            if (stopForAccess(response)) return;
            if (!response.ok) throw new Error('Older history unavailable');
            const data=await response.json();const height=history.scrollHeight,top=history.scrollTop;
            merge(data.messages);history.scrollTop=top+history.scrollHeight-height;
            older.hidden=!data.has_older;olderStatus.textContent='';applyStatus(data.conversation_status);
        } catch (_) {olderStatus.textContent='Could not load older messages. Try again.';}
        finally {loadingOlder=false;older.disabled=false;}
    });
    body.addEventListener('input',syncComposer);
    body.addEventListener('keydown',event=>{
        if (event.key==='Enter' && !event.shiftKey && !event.isComposing) {
            event.preventDefault();if (!send.disabled) form.requestSubmit();
        }
    });
    form.addEventListener('submit',async event=>{
        event.preventDefault();if (sending || stopped || !body.value.trim() || thread.dataset.status!=='ACTIVE') return;
        const value=body.value;sending=true;feedback('');syncComposer();
        try {
            const response=await fetch(form.action,{method:'POST',credentials:'same-origin',headers:{...headers,'Content-Type':'application/json'},body:JSON.stringify({body:value})});
            if (stopForAccess(response)) return;
            if (response.status===422) {
                const data=await response.json();feedback(Object.values(data.errors ?? {}).flat().join(' ') || 'Please review your message.');return;
            }
            if (response.status===429) {feedback('Too many messages. Wait before sending again.');return;}
            if (!response.ok) throw new Error('Unknown send result');
            const data=await response.json();merge([data.message]);updatePreview(data.message);body.value='';followLatest();
            await poll();
        } catch (_) {feedback('Sending could not be confirmed. Your message may have been sent. Check the conversation before retrying.');}
        finally {sending=false;syncComposer();}
    });
    history.addEventListener('scroll',()=>{if (nearBottom()) {newMessages.hidden=true;markRead();}},{passive:true});
    newMessages.addEventListener('click',followLatest);retry.addEventListener('click',poll);
    document.addEventListener('visibilitychange',()=>{
        clearTimeout(timer);if (!document.hidden) {fitViewport();poll();markRead();}
    });
    window.addEventListener('pagehide',()=>{paused=true;clearTimeout(timer);});
    window.addEventListener('pageshow',event=>{if (event.persisted) {paused=false;fitViewport();poll();markRead();}});
    syncComposer();history.scrollTop=history.scrollHeight;markRead();schedule();
});
</script>
