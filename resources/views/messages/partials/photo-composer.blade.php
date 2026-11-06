<div data-photo-composer class="min-w-0 space-y-2">
    @foreach($errors->getMessages() as $field=>$messages)@if(str_starts_with($field,'images'))@foreach($messages as $message)<p role="alert" class="text-sm text-red-700">{{ $message }}</p>@endforeach @endif @endforeach
    @if($canAttach ?? false)
        <label class="inline-flex cursor-pointer rounded-lg border px-3 py-2 focus-within:ring-2 focus-within:ring-[#1F6F5B]">📷 Add Photo / Change Photo<input data-photo-input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" class="sr-only"></label>
        <p class="text-xs">One JPG, PNG or WebP, up to 5 MiB. You may send a photo without text.</p>
        <div data-photo-selection hidden><img data-photo-preview alt="Selected photo preview" class="max-h-32 max-w-full rounded"><button data-photo-remove type="button" class="underline">Remove Photo</button></div>
        <p data-photo-error class="text-sm text-red-700" role="alert" hidden></p>
    @else
        <button type="button" disabled aria-disabled="true" class="rounded-lg border px-3 py-2">📷 Add Photo (unavailable)</button><p class="text-xs">{{ $photoUnavailableReason ?? 'Photo sharing is unavailable. You can still send text messages.' }}</p>
    @endif
</div>
@once
<script>
(() => {
    const input=form=>form?.querySelector('[data-photo-input]');
    const hasPhoto=form=>Boolean(input(form)?.files?.length);
    const clear=form=>{const file=input(form);if(file){file.value='';file.dispatchEvent(new Event('change'));}};
    window.SukiChatPhoto={hasPhoto,clear,payload(form,body){const payload=new FormData(form);payload.set('body',body);return payload;},busy(form,busy){form?.querySelectorAll('[data-photo-input],[data-photo-remove]').forEach(node=>node.disabled=busy);}};
    document.addEventListener('DOMContentLoaded',()=>document.querySelectorAll('[data-photo-composer]').forEach(root=>{
        const file=root.querySelector('[data-photo-input]');if(!file)return;
        const form=root.closest('form'),preview=root.querySelector('[data-photo-preview]'),selection=root.querySelector('[data-photo-selection]'),error=root.querySelector('[data-photo-error]');let url=null;
        function refresh(){
            if(url){URL.revokeObjectURL(url);url=null;}error.hidden=true;preview.removeAttribute('src');selection.hidden=true;
            const photo=file.files[0];
            if(photo && (!['image/jpeg','image/png','image/webp'].includes(photo.type) || photo.size>5242880)){file.value='';error.textContent='Choose a JPG, PNG or WebP up to 5 MiB.';error.hidden=false;}
            else if(photo){url=URL.createObjectURL(photo);preview.src=url;selection.hidden=false;}
            const body=form.querySelector('textarea[name="body"]');body.required=!hasPhoto(form);body.dispatchEvent(new Event('input',{bubbles:true}));
        }
        file.addEventListener('change',refresh);root.querySelector('[data-photo-remove]').addEventListener('click',()=>clear(form));
        refresh();
        window.addEventListener('pagehide',()=>{if(url){URL.revokeObjectURL(url);url=null;}});
        window.addEventListener('pageshow',event=>{if(event.persisted)refresh();});
    }));
})();
</script>
@endonce
