const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/store-profile-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness() {
    const fields={},clear={},windowHandlers={},revoked=[],created=[];let ready;
    function element(){return{value:'',textContent:'',dataset:{},handlers:{},hidden:false,disabled:false,attributes:{},files:[],
        addEventListener(type,fn){(this.handlers[type]??=[]).push(fn);},setAttribute(k,v){this.attributes[k]=v;},removeAttribute(k){delete this.attributes[k];},getAttribute(k){return this.attributes[k];},focus(){this.focused=true;}};}
    for(const id of ['description','descriptionCount','profile_photo','shopPhotoPreview','shopPhotoPlaceholder','photoFileName','banner','shopBannerPreview','shopBannerPlaceholder','bannerFileName','storeProfileForm','storeProfileSave','storeProfileSaveStatus'])fields[id]=element();
    for(const id of ['profile_photo','banner'])clear[id]=element();
    fields.shopPhotoPreview.dataset.originalSrc='/storage/saved-logo.webp';fields.shopBannerPreview.dataset.originalSrc='';
    fields.storeProfileForm.valid=true;fields.storeProfileForm.reportValidity=()=>fields.storeProfileForm.valid;
    fields.description.value='Saved description';
    vm.runInNewContext(source,{document:{getElementById:id=>fields[id],querySelector:selector=>clear[selector.match(/"([^"]+)"/)[1]],addEventListener(type,fn){ready=fn;}},
        URL:{createObjectURL(file){const url='blob:'+created.length;created.push({file,url});return url;},revokeObjectURL(url){revoked.push(url);}},
        window:{addEventListener(type,fn){(windowHandlers[type]??=[]).push(fn);}},
    });ready();
    function fire(node,type,extra={}){const event={preventDefault(){this.prevented=true;},...extra};for(const fn of node.handlers[type]??[])fn(event);return event;}
    return{fields,clear,created,revoked,windowHandlers,fire};
}
const file=(name='logo.png',size=100,type='image/png')=>({name,size,type});

test('description counter initializes and counts Unicode characters as the server does',()=>{
    const h=harness();assert.equal(h.fields.descriptionCount.textContent,'17/500');
    h.fields.description.value='Shop \u{1F34E}';h.fire(h.fields.description,'input');assert.equal(h.fields.descriptionCount.textContent,'6/500');
});

test('logo/banner previews remain independent, revoke replaced URLs, and cancel restores saved images',()=>{
    const h=harness();h.fields.profile_photo.files=[file()];h.fire(h.fields.profile_photo,'change');
    h.fields.banner.files=[file('banner.webp',100,'image/webp')];h.fire(h.fields.banner,'change');
    assert.equal(h.fields.shopPhotoPreview.src,'blob:0');assert.equal(h.fields.shopBannerPreview.src,'blob:1');assert.equal(h.fields.shopBannerPlaceholder.hidden,true);
    h.fields.profile_photo.files=[file('next.jpg',100,'image/jpeg')];h.fire(h.fields.profile_photo,'change');assert.deepEqual(h.revoked,['blob:0']);
    h.fire(h.clear.profile_photo,'click');assert.equal(h.fields.shopPhotoPreview.src,'/storage/saved-logo.webp');assert.equal(h.fields.shopBannerPreview.src,'blob:1');assert.equal(h.fields.profile_photo.focused,true);
    h.fire(h.clear.banner,'click');assert.equal(h.fields.shopBannerPreview.hidden,true);assert.equal(h.fields.shopBannerPlaceholder.hidden,false);assert.equal(h.clear.banner.hidden,true);assert.match(h.fields.bannerFileName.textContent,/Saved image unchanged/);
});

test('client format and size checks use the existing two/four MiB limits and leave saved images intact',()=>{
    const h=harness();for(const candidate of [file('bad.svg',100,'image/svg+xml'),file('fake.jpg',100,'image/png'),file('big.png',2*1024*1024+1)]){
        h.fields.profile_photo.files=[candidate];h.fire(h.fields.profile_photo,'change');assert.equal(h.fields.profile_photo.value,'');assert.equal(h.fields.shopPhotoPreview.src,'/storage/saved-logo.webp');assert.match(h.fields.photoFileName.textContent,/up to 2 MB/);
    }
    h.fields.banner.files=[file('edge.webp',4*1024*1024,'image/webp')];h.fire(h.fields.banner,'change');assert.equal(h.created.length,1);
    h.fields.banner.files=[file('large.webp',4*1024*1024+1,'image/webp')];h.fire(h.fields.banner,'change');assert.match(h.fields.bannerFileName.textContent,/up to 4 MB/);assert.equal(h.fields.shopBannerPreview.hidden,true);
});

test('missing image previews show a fallback and blob URLs are released on page exit',()=>{
    const h=harness();h.fields.banner.files=[file()];h.fire(h.fields.banner,'change');h.fire(h.fields.shopBannerPreview,'error');assert.equal(h.fields.shopBannerPlaceholder.hidden,false);assert.match(h.fields.bannerFileName.textContent,/unavailable/);
    h.windowHandlers.pagehide[0]({persisted:true});assert.equal(h.revoked.length,0);h.windowHandlers.pagehide[0]({persisted:false});assert.deepEqual(h.revoked,['blob:0']);
});

test('profile save preserves normal form transport, blocks duplicates, and restores controls after back navigation',()=>{
    const h=harness();h.fields.storeProfileForm.valid=false;assert.equal(h.fire(h.fields.storeProfileForm,'submit').prevented,true);
    h.fields.storeProfileForm.valid=true;assert.equal(h.fire(h.fields.storeProfileForm,'submit').prevented,undefined);assert.equal(h.fields.storeProfileSave.disabled,true);assert.equal(h.fields.storeProfileForm.attributes['aria-busy'],'true');
    assert.equal(h.fire(h.fields.storeProfileForm,'submit').prevented,true);h.windowHandlers.pageshow[0]({persisted:true});assert.equal(h.fields.storeProfileSave.disabled,false);assert.equal(h.fields.storeProfileSave.textContent,'Save Store Profile');
});
