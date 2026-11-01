@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', () => {
    const form=document.getElementById('storeAddressForm');
    if(!form || form.dataset.cascadeInitialized)return;
    const region=document.getElementById('profileRegion'),province=document.getElementById('profileProvince'),city=document.getElementById('profileMunicipality'),barangay=document.getElementById('profileBarangay'),postal=document.getElementById('profilePostalCode');
    const error=document.getElementById('pickupLocationError'),errorText=document.getElementById('pickupLocationErrorText'),retry=document.getElementById('pickupLocationRetry'),status=document.getElementById('pickupLocationStatus'),save=document.getElementById('storeAddressSave');
    if(!region || !province || !city || !barangay || !postal || !error || !retry || !status || !save)return;
    form.dataset.cascadeInitialized='true';
    const initial={region:form.dataset.initialRegion || '',province:form.dataset.initialProvince || '',city:form.dataset.initialCity || '',barangay:form.dataset.initialBarangay || ''};
    const pending=new Map(),cache=new Map(),busy=new Map(),errors=new Map();
    let version=0,needsProvince=true,warning='',retrying=false;
    const labels={regions:'regions',provinces:'provinces',cities:'cities',barangays:'barangays',postal:'postal code'};
    const valid=select=>!select.disabled && /^\d+$/.test(select.value) && Array.from(select.options).some(option=>option.value===select.value);
    const complete=()=>valid(region) && (!needsProvince || valid(province)) && valid(city) && valid(barangay);
    function sync() {
        save.disabled=busy.size>0 || !complete();
        form.setAttribute('aria-busy',String(busy.size>0));
        error.hidden=errors.size===0;
        errorText.textContent=Array.from(errors.values(),item=>item.message).join(' ');
        retry.disabled=retrying || busy.size>0;
        status.textContent=busy.size?'Loading '+labels[Array.from(busy.keys()).at(-1)]+'...':(warning || (complete()?'Location selected. Save Address to apply changes.':'Select the remaining locations to continue.'));
    }
    function reset(select,text) {
        select.innerHTML='';const option=document.createElement('option');option.value='';option.textContent=text;select.appendChild(option);select.value='';select.disabled=true;
    }
    function populate(select,items,text,selected='') {
        reset(select,text);
        items.forEach(item=>{const option=document.createElement('option');option.value=item.code;option.textContent=item.name;select.appendChild(option);});
        select.disabled=false;select.value=items.some(item=>item.code===selected)?selected:'';
        return !!select.value;
    }
    function inconsistent() {warning='The saved location is incomplete or no longer matches the available PSGC locations. Please select the remaining fields.';postal.value='';}
    function start() {version++;busy.clear();errors.clear();warning='';sync();return version;}
    async function json(path,isPostal=false) {
        if(cache.has(path))return cache.get(path);
        if(!pending.has(path)) {
            pending.set(path,(async()=>{
                const controller=new AbortController(),timeout=setTimeout(()=>controller.abort(),10000);
                let data;
                try {
                    const response=await fetch('/address/'+path,{credentials:'same-origin',headers:{Accept:'application/json'},signal:controller.signal});
                    if(!response.ok)throw new Error('Location request failed');
                    data=await response.json();
                } finally {clearTimeout(timeout);}
                if(isPostal) {
                    if(!data || !Object.hasOwn(data,'postal_code') || (data.postal_code!==null && !/^\d{4}$/.test(data.postal_code)))throw new Error('Invalid postal response');
                } else if(!Array.isArray(data) || !data.every(item=>item && typeof item.code==='string' && /^\d+$/.test(item.code) && typeof item.name==='string'))throw new Error('Invalid PSGC response');
                cache.set(path,data);return data;
            })().finally(()=>pending.delete(path)));
        }
        return pending.get(path);
    }
    async function run(key,token,action) {
        if(token!==version)return;
        busy.set(key,token);errors.delete(key);sync();
        try {await action();}
        catch (_) {if(token===version){
            const target={regions:region,provinces:province,cities:city,barangays:barangay}[key];
            if(target)reset(target,'Unable to load '+labels[key]);
            errors.set(key,{message:'Unable to load '+labels[key]+'. Please retry.',retry:()=>run(key,token,action)});
        }}
        finally {if(token===version){busy.delete(key);sync();}}
    }
    function loadPostal(code,token,preserveSaved=false) {
        if(!preserveSaved)postal.value='';
        return run('postal',token,async()=>{const data=await json('cities-municipalities/'+encodeURIComponent(code)+'/postal-code',true);if(token===version && city.value===code)postal.value=data.postal_code ?? '';});
    }
    function loadBarangays(code,token,selected='') {
        reset(barangay,'Loading barangays...');
        return run('barangays',token,async()=>{
            const items=await json('cities-municipalities/'+encodeURIComponent(code)+'/barangays');if(token!==version)return;
            const restored=populate(barangay,items,'Select barangay',selected);
            if(selected && !restored)inconsistent();
        });
    }
    function loadCities(path,token,selected={},preserveSaved=false) {
        reset(city,'Loading cities...');reset(barangay,'Select municipality/city first');
        return run('cities',token,async()=>{
            const items=await json(path);if(token!==version)return;
            const restored=populate(city,items,'Select municipality/city',selected.city);
            if(restored)await Promise.all([loadBarangays(city.value,token,selected.barangay),loadPostal(city.value,token,preserveSaved)]);
            else if(Object.values(selected).some(Boolean))inconsistent();
        });
    }
    function loadProvinces(code,token,selected={},preserveSaved=false) {
        reset(province,'Loading provinces...');reset(city,'Select province or region first');reset(barangay,'Select municipality/city first');
        return run('provinces',token,async()=>{
            const items=await json('regions/'+encodeURIComponent(code)+'/provinces');if(token!==version)return;
            needsProvince=items.length>0;province.required=needsProvince;
            if(!needsProvince) {
                reset(province,'No province required');
                if(selected.province){inconsistent();selected={};preserveSaved=false;}
                await loadCities('regions/'+encodeURIComponent(code)+'/cities-municipalities',token,selected,preserveSaved);
            } else {
                const restored=populate(province,items,'Select province',selected.province);
                if(restored)await loadCities('provinces/'+encodeURIComponent(province.value)+'/cities-municipalities',token,selected,preserveSaved);
                else if(Object.values(selected).some(Boolean))inconsistent();
            }
        });
    }
    const token=start();region.disabled=true;reset(province,'Select region first');reset(city,'Select province or region first');reset(barangay,'Select municipality/city first');
    run('regions',token,async()=>{
        const items=await json('regions');if(token!==version)return;
        const restored=populate(region,items,'Select region',initial.region);
        if(restored)await loadProvinces(region.value,token,initial,true);
        else if(Object.values(initial).some(Boolean))inconsistent();
    });
    region.addEventListener('change',()=>{
        const token=start();needsProvince=true;postal.value='';
        reset(province,'Select region first');reset(city,'Select province or region first');reset(barangay,'Select municipality/city first');
        if(valid(region))loadProvinces(region.value,token);else sync();
    });
    province.addEventListener('change',()=>{
        const token=start();postal.value='';reset(city,'Select province first');reset(barangay,'Select municipality/city first');
        if(valid(province))loadCities('provinces/'+encodeURIComponent(province.value)+'/cities-municipalities',token);else sync();
    });
    city.addEventListener('change',()=>{
        const token=start();postal.value='';reset(barangay,'Select municipality/city first');
        if(valid(city)){loadBarangays(city.value,token);loadPostal(city.value,token);}else sync();
    });
    barangay.addEventListener('change',sync);
    retry.addEventListener('click',async()=>{
        if(retrying || busy.size)return;
        retrying=true;const tasks=Array.from(errors.values(),item=>item.retry);sync();
        try{await Promise.all(tasks.map(action=>action()));}finally{retrying=false;sync();}
    });
    form.addEventListener('submit',event=>{
        if(busy.size || !complete()){event.preventDefault();warning='Select a valid Region, Province (when applicable), Municipality/City and Barangay before saving.';sync();}
    });
});
</script>
@endpush
