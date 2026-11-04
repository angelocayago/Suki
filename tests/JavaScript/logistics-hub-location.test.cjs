const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/logistics/hubs/location-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({initial={region:'01',province:'0101',city:'010101',barangay:'010101001'},overrides={}}={}) {
    const fields={},calls=[];
    for(const id of ['hubLocationForm','hubRegion','hubProvince','hubCity','hubBarangay','hubPostalCode','hubSave','hubLocationStatus','hubLocationError','hubLocationErrorText','hubLocationRetry']) {
        fields[id]={dataset:{},handlers:{},options:[],disabled:false,hidden:true,value:'',textContent:'',attributes:{},
            addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},setAttribute(key,value){this.attributes[key]=value;},
            add(option){if(!this.options.length)option.selected=true;this.options.push(option);}};
    }
    for(const id of ['hubRegion','hubProvince','hubCity','hubBarangay']) {
        const field=fields[id];delete field.value;
        Object.defineProperty(field,'value',{get(){return this.options.find(option=>option.selected)?.value??'';},set(value){this.options.forEach(option=>option.selected=option.value===value);}});
        Object.defineProperty(field,'innerHTML',{set(){this.options=[];}});
    }
    Object.assign(fields.hubLocationForm.dataset,{initialRegion:initial.region||'',initialProvince:initial.province||'',initialCity:initial.city||'',initialBarangay:initial.barangay||''});
    const row=(code,name)=>({code,name});
    const routes={
        '/address/regions':[row('01','Region A'),row('02','Region B'),row('13','Province-free Region')],
        '/address/regions/01/provinces':[row('0101','Province A')],
        '/address/regions/02/provinces':[row('0201','Province B')],
        '/address/regions/13/provinces':[],
        '/address/provinces/0101/cities-municipalities':[row('010101','City A')],
        '/address/provinces/0201/cities-municipalities':[row('020101','City B')],
        '/address/regions/13/cities-municipalities':[row('130001','City C')],
        '/address/cities-municipalities/010101/barangays':[row('010101001','Barangay A')],
        '/address/cities-municipalities/020101/barangays':[row('020101001','Barangay B')],
        '/address/cities-municipalities/130001/barangays':[row('130001001','Barangay C')],
        '/address/cities-municipalities/010101/postal-code':{postal_code:'1234'},
        '/address/cities-municipalities/020101/postal-code':{postal_code:null},
        '/address/cities-municipalities/130001/postal-code':{postal_code:null},
    };
    const context={document:{getElementById:id=>fields[id]},Option:function(text,value){this.text=text;this.value=value;this.selected=false;},
        async fetch(url,options){calls.push({url,options});assert.ok(Object.hasOwn(routes,url),url);let result=Object.hasOwn(overrides,url)?overrides[url]:routes[url];if(typeof result==='function')result=await result();else result=await result;if(result instanceof Error)throw result;return{ok:true,json:async()=>result};}};
    vm.runInNewContext(source,context);
    async function fire(id,type){const event={preventDefault(){this.prevented=true;}};for(const fn of fields[id].handlers[type]??[])await fn(event);return event;}
    return{fields,calls,routes,overrides,fire,flush:()=>new Promise(resolve=>setImmediate(resolve)),rerun:()=>vm.runInNewContext(source,context),
        async select(id,value){fields[id].value=value;await fire(id,'change');}};
}

test('validation-error values restore from verified options and postal data without any save request',async()=>{
    const h=harness();await h.flush();
    for(const [id,value] of Object.entries({hubRegion:'01',hubProvince:'0101',hubCity:'010101',hubBarangay:'010101001',hubPostalCode:'1234'}))assert.equal(h.fields[id].value,value);
    assert.equal(h.fields.hubSave.disabled,false);assert.equal((await h.fire('hubLocationForm','submit')).prevented,undefined);
    assert.ok(h.calls.every(call=>!call.options.method&&call.options.headers.Accept==='application/json'));
    const count=h.calls.length;h.rerun();await h.flush();assert.equal(h.calls.length,count);
});

test('changing parents clears stale choices and supports the full location cascade',async()=>{
    const h=harness();await h.flush();await h.select('hubRegion','02');
    assert.equal(h.fields.hubCity.disabled,true);assert.equal(h.fields.hubBarangay.value,'');assert.equal(h.fields.hubPostalCode.value,'');assert.equal(h.fields.hubSave.disabled,true);
    await h.select('hubProvince','0201');await h.select('hubCity','020101');await h.select('hubBarangay','020101001');
    assert.equal(h.fields.hubSave.disabled,false);assert.equal(h.fields.hubPostalCode.value,'');
    assert.match(h.fields.hubLocationStatus.textContent,/Postal code is unavailable/);
});

test('province-free regions resolve city and barangay choices through existing endpoints',async()=>{
    const h=harness({initial:{region:'13',city:'130001',barangay:'130001001'}});await h.flush();
    assert.equal(h.fields.hubProvince.disabled,true);assert.equal(h.fields.hubProvince.required,false);
    assert.equal(h.fields.hubCity.value,'130001');assert.equal(h.fields.hubBarangay.value,'130001001');assert.equal(h.fields.hubSave.disabled,false);
});

test('loading failure is visible, blocks saving, and retry preserves validation-error choices',async()=>{
    const h=harness({overrides:{'/address/regions':new Error('offline')}});await h.flush();
    assert.equal(h.fields.hubLocationError.hidden,false);assert.equal(h.fields.hubSave.disabled,true);
    assert.equal((await h.fire('hubLocationForm','submit')).prevented,true);
    delete h.overrides['/address/regions'];await h.fire('hubLocationRetry','click');
    assert.equal(h.fields.hubLocationError.hidden,true);assert.equal(h.fields.hubBarangay.value,'010101001');assert.equal(h.fields.hubSave.disabled,false);
});

test('late responses cannot overwrite a newly selected region',async()=>{
    const h=harness();await h.flush();let resolve;
    h.overrides['/address/regions/02/provinces']=new Promise(done=>resolve=done);
    const pending=h.select('hubRegion','02');await h.select('hubRegion','01');resolve([{code:'0201',name:'Stale province'}]);await pending;
    assert.equal(h.fields.hubRegion.value,'01');assert.ok(h.fields.hubProvince.options.some(option=>option.value==='0101'));
    assert.ok(!h.fields.hubProvince.options.some(option=>option.value==='0201'));assert.equal(h.fields.hubSave.disabled,true);
});

test('postal lookup failure never fabricates a postal code or invalidates otherwise complete coverage',async()=>{
    const h=harness({overrides:{'/address/cities-municipalities/010101/postal-code':new Error('unavailable')}});await h.flush();
    assert.equal(h.fields.hubPostalCode.value,'');assert.equal(h.fields.hubSave.disabled,false);assert.match(h.fields.hubLocationStatus.textContent,/Postal code is unavailable/);
});

test('empty or malformed location responses cannot produce a savable hub',async()=>{
    for(const payload of [[],{unexpected:true},[{code:'invalid',name:'Not a code'}]]){
        const h=harness({overrides:{'/address/regions':payload}});await h.flush();
        assert.equal(h.fields.hubLocationError.hidden,false);assert.equal(h.fields.hubSave.disabled,true);
    }
});
