@once
<script>
(() => {
    if (window.SukiMessageImages) return;
    function safeUrl(value,reference) {
        try {
            const url=new URL(value,window.location.origin);
            return url.origin===window.location.origin && url.pathname.includes('/messages/'+reference+'/attachments/') && !url.pathname.includes('/storage/') ? url.href : null;
        } catch (_) {return null;}
    }
    function cleanItems(items,reference) {
        return items.map(item=>({...item,url:safeUrl(item.url,reference),thumbnail_url:safeUrl(item.thumbnail_url,reference),download_url:safeUrl(item.download_url,reference)}))
            .filter(item=>item.url && item.thumbnail_url && item.download_url);
    }
    let lightbox=null,activeItems=[],activeIndex=0,returnFocus=null;
    function setupLightbox() {
        if (lightbox) return lightbox;
        lightbox=document.getElementById('messageImageLightbox');
        if (!lightbox) return null;
        const close=lightbox.querySelector('[data-lightbox-close]');
        close.addEventListener('click',()=>lightbox.close());
        lightbox.querySelector('[data-lightbox-prev]').addEventListener('click',()=>showImage(activeIndex-1));
        lightbox.querySelector('[data-lightbox-next]').addEventListener('click',()=>showImage(activeIndex+1));
        lightbox.addEventListener('keydown',event=>{
            event.stopPropagation();
            if (event.key==='ArrowLeft' || event.key==='ArrowRight') {event.preventDefault();showImage(activeIndex+(event.key==='ArrowRight'?1:-1));}
        });
        lightbox.addEventListener('close',()=>returnFocus?.focus());
        lightbox.querySelector('[data-lightbox-image]').addEventListener('error',()=>{
            lightbox.querySelector('[data-lightbox-image]').hidden=true;lightbox.querySelector('[data-lightbox-error]').hidden=false;
        });
        return lightbox;
    }
    function showImage(index) {
        if (!activeItems.length) return;
        activeIndex=(index+activeItems.length)%activeItems.length;
        const item=activeItems[activeIndex],image=lightbox.querySelector('[data-lightbox-image]');
        image.hidden=false;lightbox.querySelector('[data-lightbox-error]').hidden=true;
        image.alt=item.original_name || 'Message image';image.src=item.url;
        lightbox.querySelector('#messageImageTitle').textContent=item.original_name || 'Message image';
        lightbox.querySelector('[data-lightbox-count]').textContent=(activeIndex+1)+' / '+activeItems.length;
        lightbox.querySelector('[data-lightbox-download]').href=item.download_url;
        lightbox.querySelector('[data-lightbox-prev]').disabled=activeItems.length<2;
        lightbox.querySelector('[data-lightbox-next]').disabled=activeItems.length<2;
    }
    function bindGallery(root,items,reference) {
        if (root.dataset.initialized) return;
        root.dataset.initialized='true';const safe=cleanItems(items,reference);
        root.querySelectorAll('[data-image-index]').forEach(button=>button.addEventListener('click',()=>{
            if (!safe.length || !setupLightbox()) return;
            activeItems=safe;returnFocus=button;showImage(Number(button.dataset.imageIndex));
            lightbox.showModal();lightbox.querySelector('[data-lightbox-close]').focus();
        }));
        root.querySelectorAll('img').forEach(image=>{
            const fail=()=>{image.hidden=true;image.parentElement.querySelector('.message-image-fallback').hidden=false;};
            image.addEventListener('error',fail);if (image.complete && !image.naturalWidth) fail();
        });
    }
    function gallery(items,reference) {
        const safe=cleanItems(items,reference);
        if (!safe.length) return null;
        const root=document.createElement('div');root.className='message-image-gallery'+(safe.length===1?' is-single':'');
        safe.slice(0,4).forEach((item,index)=>{
            const button=document.createElement('button');button.type='button';button.dataset.imageIndex=String(index);
            button.setAttribute('aria-label','View image '+(index+1)+' of '+safe.length);
            const image=document.createElement('img');image.src=item.thumbnail_url;image.alt=item.original_name || 'Message image';
            image.loading='lazy';image.width=320;image.height=240;
            const fallback=document.createElement('span');fallback.className='message-image-fallback';fallback.hidden=true;fallback.textContent='Image unavailable';
            button.append(image,fallback);
            if (index===3 && safe.length>4) {const more=document.createElement('span');more.className='message-image-more';more.textContent='+'+(safe.length-4);button.appendChild(more);}
            root.appendChild(button);
        });
        bindGallery(root,safe,reference);return root;
    }
    window.SukiMessageImages={gallery};
    document.addEventListener('DOMContentLoaded',()=>{
        document.querySelectorAll('[data-message-gallery]').forEach(root=>{
            try {bindGallery(root,JSON.parse(root.dataset.items),root.dataset.conversation);}catch (_) {}
        });
    });
})();
</script>
@endonce
