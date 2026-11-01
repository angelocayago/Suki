const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/store-profile-address-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const deferred=()=>{let resolve;const promise=new Promise(done=>resolve=done);return{promise,resolve};};

function harness({initial={},savedPostal='2922',overrides={}}={}) {
    const fields={},callbacks=[],calls=[];
    function element(){return{dataset:{},handlers:{},options:[],attributes:{},value:'',textContent:'',disabled:false,hidden:false,
        addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},setAttribute(key,value){this.attributes[key]=value;},appendChild(option){this.options.push(option);}};}
    for(const id of ['storeAddressForm','profileRegion','profileProvince','profileMunicipality','profileBarangay','profilePostalCode','pickupLocationError','pickupLocationErrorText','pickupLocationRetry','pickupLocationStatus','storeAddressSave'])fields[id]=element();
    const selected={region:'01',province:'01028',city:'0102801000',barangay:'0102801001',...initial};
    Object.assign(fields.storeAddressForm.dataset,{initialRegion:selected.region,initialProvince:selected.province,initialCity:selected.city,initialBarangay:selected.barangay});
    for(const [key,id] of Object.entries({region:'profileRegion',province:'profileProvince',city:'profileMunicipality',barangay:'profileBarangay'})){
        const node=fields[id];delete node.value;
        Object.defineProperty(node,'value',{get(){return this.options.find(option=>option.selected)?.value??'';},set(value){for(const option of this.options)option.selected=option.value===value;}});
        Object.defineProperty(node,'innerHTML',{set(){this.options=[];}});
        node.options=[{value:selected[key],selected:true}];
    }
    fields.profilePostalCode.value=savedPostal;
    const rows=(code,name)=>({code,name});
    const routes={
        '/address/regions':[rows('01','Region A'),rows('06','Region B'),rows('13','Province-free Region')],
        '/address/regions/01/provinces':[rows('01028','Province A')],
        '/address/regions/06/provinces':[rows('0601','Province B')],
        '/address/regions/13/provinces':[],
        '/address/provinces/01028/cities-municipalities':[rows('0102801000','City A'),rows('0102802000','City A2')],
        '/address/provinces/0601/cities-municipalities':[rows('060101','City B')],
        '/address/regions/13/cities-municipalities':[rows('130001','NCR-style City')],
        '/address/cities-municipalities/0102801000/barangays':[rows('0102801001','Barangay A')],
        '/address/cities-municipalities/0102802000/barangays':[rows('0102802001','Barangay A2')],
        '/address/cities-municipalities/060101/barangays':[rows('060101001','Barangay B')],
        '/address/cities-municipalities/130001/barangays':[rows('130001001','NCR-style Barangay')],
        '/address/cities-municipalities/0102801000/postal-code':{postal_code:'2922'},
        '/address/cities-municipalities/0102802000/postal-code':{postal_code:'2916'},
        '/address/cities-municipalities/060101/postal-code':{postal_code:null},
        '/address/cities-municipalities/130001/postal-code':{postal_code:null},
    };
    const context={document:{getElementById:id=>fields[id],createElement(){return{dataset:{}};},addEventListener(type,fn){callbacks.push(fn);}},AbortController,setTimeout,clearTimeout,
        async fetch(url,options){calls.push({url,options});assert.ok(Object.hasOwn(routes,url),url);let payload=Object.hasOwn(overrides,url)?overrides[url]:routes[url];if(typeof payload==='function')payload=await payload(options);else payload=await payload;if(payload instanceof Error)throw payload;if(payload?.response)return payload.response;return{ok:true,json:async()=>payload};},
    };
    vm.runInNewContext(source,context);callbacks.forEach(fn=>fn());
    async function fire(node,type){const event={preventDefault(){this.prevented=true;}};for(const fn of node.handlers[type]??[])await fn(event);return event;}
    return{fields,calls,overrides,routes,callbacks,fire,flush:()=>new Promise(resolve=>setImmediate(resolve)),
        async select(id,value){fields[id].value=value;await fire(fields[id],'change');await new Promise(resolve=>setImmediate(resolve));},
    };
}

test('saved initialization restores canonical hierarchy and postal code without a save request',async()=>{
    const h=harness();await h.flush();
    for(const [id,value] of Object.entries({profileRegion:'01',profileProvince:'01028',profileMunicipality:'0102801000',profileBarangay:'0102801001',profilePostalCode:'2922'}))assert.equal(h.fields[id].value,value);
    assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.storeAddressSave.disabled,false);
    assert.equal(h.calls.length,5);assert.ok(h.calls.every(call=>call.options.headers.Accept==='application/json' && !call.options.method));
});

test('Region change enables new provinces and the entire interactive cascade works',async()=>{
    const h=harness();await h.flush();await h.select('profileRegion','06');
    assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.profileProvince.value,'');assert.equal(h.fields.profileMunicipality.disabled,true);assert.equal(h.fields.profileBarangay.value,'');assert.equal(h.fields.profilePostalCode.value,'');
    await h.select('profileProvince','0601');assert.equal(h.fields.profileMunicipality.disabled,false);assert.equal(h.fields.profileMunicipality.value,'');
    await h.select('profileMunicipality','060101');assert.equal(h.fields.profileBarangay.disabled,false);assert.equal(h.fields.profilePostalCode.value,'');
    await h.select('profileBarangay','060101001');assert.equal(h.fields.storeAddressSave.disabled,false);assert.equal((await h.fire(h.fields.storeAddressForm,'submit')).prevented,undefined);
});

test('province-free initialization and deliberate Region changes use region city routes',async()=>{
    const h=harness({initial:{region:'13',province:'',city:'130001',barangay:'130001001'}});await h.flush();
    assert.equal(h.fields.profileProvince.disabled,true);assert.equal(h.fields.profileProvince.required,false);assert.equal(h.fields.profileMunicipality.value,'130001');assert.equal(h.fields.storeAddressSave.disabled,false);assert.equal(h.fields.profilePostalCode.value,'');
    await h.select('profileRegion','01');assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.profileProvince.required,true);assert.equal(h.fields.profileMunicipality.value,'');
    await h.select('profileRegion','13');assert.equal(h.fields.profileMunicipality.disabled,false);assert.equal(h.fields.profileMunicipality.value,'');assert.ok(h.calls.some(call=>call.url==='/address/regions/13/cities-municipalities'));
});

test('clearing parent fields clears stale IDs and postal code and prevents incomplete submission',async()=>{
    const h=harness();await h.flush();await h.select('profileProvince','');
    assert.equal(h.fields.profileMunicipality.value,'');assert.equal(h.fields.profileBarangay.value,'');assert.equal(h.fields.profilePostalCode.value,'');
    assert.equal((await h.fire(h.fields.storeAddressForm,'submit')).prevented,true);
    await h.select('profileRegion','');assert.equal(h.fields.profileProvince.disabled,true);assert.equal(h.fields.storeAddressSave.disabled,true);
});

test('late Province responses cannot overwrite a newer Region selection',async()=>{
    const slow=deferred(),h=harness({overrides:{'/address/regions/06/provinces':slow.promise}});await h.flush();
    await h.select('profileRegion','06');assert.match(h.fields.pickupLocationStatus.textContent,/Loading provinces/);
    await h.select('profileRegion','13');assert.equal(h.fields.profileProvince.disabled,true);
    slow.resolve([{code:'0601',name:'Old response'}]);await h.flush();
    assert.equal(h.fields.profileRegion.value,'13');assert.equal(h.fields.profileProvince.disabled,true);assert.equal(h.fields.profileProvince.options.length,1);assert.equal(h.fields.profileMunicipality.options[1].value,'130001');
});

test('late barangay/postal responses cannot overwrite the current city',async()=>{
    const bars=deferred(),post=deferred(),h=harness({initial:{city:'',barangay:''},overrides:{'/address/cities-municipalities/0102801000/barangays':bars.promise,'/address/cities-municipalities/0102801000/postal-code':post.promise}});await h.flush();
    await h.select('profileMunicipality','0102801000');await h.select('profileMunicipality','0102802000');
    bars.resolve([{code:'0102801001',name:'Old barangay'}]);post.resolve({postal_code:'2922'});await h.flush();
    assert.equal(h.fields.profileMunicipality.value,'0102802000');assert.equal(h.fields.profileBarangay.options[1].value,'0102802001');assert.equal(h.fields.profilePostalCode.value,'2916');
});

test('failed Province request shows a working retry and never leaves the control permanently disabled',async()=>{
    const h=harness({overrides:{'/address/regions/06/provinces':new Error('Unavailable')}});await h.flush();await h.select('profileRegion','06');
    assert.equal(h.fields.pickupLocationError.hidden,false);assert.match(h.fields.pickupLocationErrorText.textContent,/Unable to load provinces/);assert.equal(h.fields.pickupLocationRetry.disabled,false);
    delete h.overrides['/address/regions/06/provinces'];await h.fire(h.fields.pickupLocationRetry,'click');
    assert.equal(h.fields.pickupLocationError.hidden,true);assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.profileProvince.options[1].value,'0601');
});

test('failed initialization preserves the saved postal value until valid location data can be restored',async()=>{
    const h=harness({overrides:{'/address/regions':{response:{ok:false,status:502}}}});await h.flush();
    assert.equal(h.fields.profilePostalCode.value,'2922');assert.equal(h.fields.storeAddressSave.disabled,true);assert.equal(h.fields.pickupLocationRetry.disabled,false);
    delete h.overrides['/address/regions'];await h.fire(h.fields.pickupLocationRetry,'click');
    assert.equal(h.fields.profileRegion.value,'01');assert.equal(h.fields.profileBarangay.value,'0102801001');assert.equal(h.fields.storeAddressSave.disabled,false);
});

test('inconsistent saved IDs leave editable placeholders rather than selecting unrelated locations',async()=>{
    const h=harness({initial:{province:'0601',city:'060101',barangay:'060101001'}});await h.flush();
    assert.equal(h.fields.profileRegion.value,'01');assert.equal(h.fields.profileProvince.value,'');assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.profileMunicipality.disabled,true);assert.equal(h.fields.profilePostalCode.value,'');assert.match(h.fields.pickupLocationStatus.textContent,/saved location is incomplete/);
});

test('incomplete saved hierarchy clears an unverified postal value without auto-selecting a city',async()=>{
    const h=harness({initial:{city:'',barangay:''}});await h.flush();
    assert.equal(h.fields.profileMunicipality.value,'');assert.equal(h.fields.profileMunicipality.disabled,false);assert.equal(h.fields.profilePostalCode.value,'');assert.equal(h.fields.storeAddressSave.disabled,true);assert.match(h.fields.pickupLocationStatus.textContent,/saved location is incomplete/);
});

test('postal errors are retryable and saved data is preserved; a deliberate city change clears stale postal data',async()=>{
    const path='/address/cities-municipalities/0102801000/postal-code',h=harness({overrides:{[path]:new Error('Unavailable')}});await h.flush();
    assert.equal(h.fields.profilePostalCode.value,'2922');assert.equal(h.fields.pickupLocationError.hidden,false);
    delete h.overrides[path];await h.fire(h.fields.pickupLocationRetry,'click');assert.equal(h.fields.pickupLocationError.hidden,true);
    await h.select('profileMunicipality','');assert.equal(h.fields.profilePostalCode.value,'');assert.equal(h.fields.profileBarangay.disabled,true);
});

test('malformed location payloads are recoverable and duplicate initialization/request work is avoided',async()=>{
    const h=harness({overrides:{'/address/regions':{data:[]}}});await h.flush();assert.equal(h.fields.pickupLocationError.hidden,false);
    delete h.overrides['/address/regions'];await h.fire(h.fields.pickupLocationRetry,'click');const count=h.calls.length;
    h.callbacks.forEach(fn=>fn());await h.flush();assert.equal(h.calls.length,count);
    await h.select('profileRegion','06');await h.select('profileRegion','01');assert.equal(h.calls.filter(call=>call.url==='/address/regions/01/provinces').length,1);
});

for(const [label,path] of [['cities','/address/provinces/01028/cities-municipalities'],['barangays','/address/cities-municipalities/0102801000/barangays']])test(label+' failures recover through Retry with saved selections restored',async()=>{
    const h=harness({overrides:{[path]:new Error('Unavailable')}});await h.flush();
    assert.match(h.fields.pickupLocationErrorText.textContent,new RegExp('Unable to load '+label));assert.equal(h.fields.storeAddressSave.disabled,true);
    delete h.overrides[path];await h.fire(h.fields.pickupLocationRetry,'click');
    assert.equal(h.fields.profileBarangay.value,'0102801001');assert.equal(h.fields.storeAddressSave.disabled,false);
});

test('rapidly returning to a pending Region reuses its request and applies only the latest selection',async()=>{
    const slow=deferred(),h=harness({overrides:{'/address/regions/06/provinces':slow.promise}});await h.flush();
    await h.select('profileRegion','06');await h.select('profileRegion','13');await h.select('profileRegion','06');
    assert.equal(h.calls.filter(call=>call.url==='/address/regions/06/provinces').length,1);
    slow.resolve([{code:'0601',name:'Province B'}]);await h.flush();assert.equal(h.fields.profileRegion.value,'06');assert.equal(h.fields.profileProvince.disabled,false);assert.equal(h.fields.profileProvince.options[1].value,'0601');
});
