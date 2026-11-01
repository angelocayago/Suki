@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const description=document.getElementById('description'),count=document.getElementById('descriptionCount');
    const updateCount=()=>{if(description && count)count.textContent=Array.from(description.value).length+'/500';};
    description?.addEventListener('input',updateCount);updateCount();
    const urls=new Map();
    function preview(inputId,imageId,placeholderId,statusId,maxMiB) {
        const input=document.getElementById(inputId),image=document.getElementById(imageId),placeholder=document.getElementById(placeholderId),status=document.getElementById(statusId);
        const clear=document.querySelector('[data-clear-image="'+inputId+'"]');
        if(!input || !image)return;
        const revoke=()=>{if(urls.has(inputId)){URL.revokeObjectURL(urls.get(inputId));urls.delete(inputId);}};
        function restore() {
            revoke();const original=image.dataset.originalSrc;
            if(original)image.src=original;else image.removeAttribute('src');
            image.hidden=!original;if(placeholder)placeholder.hidden=!!original;
            if(clear)clear.hidden=true;
        }
        input.addEventListener('change',()=>{
            const file=input.files?.[0];if(!file){restore();status.textContent='';return;}
            const types={jpg:'image/jpeg',jpeg:'image/jpeg',png:'image/png',webp:'image/webp'},extension=file.name.split('.').pop().toLowerCase();
            if(!types[extension] || (file.type && types[extension]!==file.type) || !file.size || file.size>maxMiB*1024*1024){
                input.value='';restore();status.textContent='Choose a JPG, PNG or WEBP image up to '+maxMiB+' MB.';return;
            }
            revoke();const url=URL.createObjectURL(file);urls.set(inputId,url);image.src=url;image.hidden=false;
            if(placeholder)placeholder.hidden=true;if(clear)clear.hidden=false;status.textContent=file.name+' — ready to save';
        });
        clear?.addEventListener('click',()=>{input.value='';restore();status.textContent='Selection cancelled. Saved image unchanged.';input.focus();});
        const failed=()=>{image.hidden=true;if(placeholder)placeholder.hidden=false;status.textContent=image.dataset.originalSrc && !urls.has(inputId)?'Saved image could not be loaded. Please refresh or check storage availability.':'Image preview unavailable.';};
        image.addEventListener('error',failed);
        image.addEventListener('load',()=>{image.hidden=false;if(placeholder)placeholder.hidden=true;});
        if(image.getAttribute('src') && image.complete && !image.naturalWidth)failed();
    }
    preview('profile_photo','shopPhotoPreview','shopPhotoPlaceholder','photoFileName',2);
    preview('banner','shopBannerPreview','shopBannerPlaceholder','bannerFileName',4);
    window.addEventListener('pagehide',event=>{if(!event.persisted){urls.forEach(url=>URL.revokeObjectURL(url));urls.clear();}});
    const form=document.getElementById('storeProfileForm'),button=document.getElementById('storeProfileSave'),status=document.getElementById('storeProfileSaveStatus');
    let saving=false;
    form?.addEventListener('submit',event=>{
        if(saving){event.preventDefault();return;}
        if(!form.reportValidity()){event.preventDefault();return;}
        saving=true;button.disabled=true;button.textContent='Saving…';form.setAttribute('aria-busy','true');status.textContent='Saving store profile…';
    });
    window.addEventListener('pageshow',event=>{if(event.persisted && form){saving=false;button.disabled=false;button.textContent='Save Store Profile';form.removeAttribute('aria-busy');status.textContent='Profile changes are saved separately from your address.';}});
});
</script>
@endpush
