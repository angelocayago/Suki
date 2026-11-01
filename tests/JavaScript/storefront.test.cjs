const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/buyer/store-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness(navigator={}) {
    const element=()=>({hidden:false,disabled:false,textContent:'',dataset:{},handlers:{},attributes:{},addEventListener(type,fn){this.handlers[type]=fn;},getAttribute(name){return this.attributes[name];},setAttribute(name,value){this.attributes[name]=value;},removeAttribute(name){delete this.attributes[name];}});
    const share=element(),feedback=element(),image=element(),links=['#products','#about','#reviews'].map(href=>{const el=element();el.attributes.href=href;return el;});
    share.hidden=true;share.dataset={url:'https://suki.test/stores/real-shop',title:'Real shop'};image.complete=true;image.naturalWidth=0;
    const location={hash:''},window={handlers:{},addEventListener(type,fn){this.handlers[type]=fn;}};
    const root={querySelector:s=>s==='[data-sf-share]'?share:feedback,querySelectorAll:s=>s==='[data-sf-image]'?[image]:links};
    vm.runInNewContext(source,{document:{querySelector:()=>root,addEventListener(type,fn){fn();}},navigator,location,window});
    return {share,feedback,image,links,location,window};
}
test('native share receives only the canonical store URL and reports success',async()=>{
    let sent;const h=harness({share:async data=>{sent=data;}});assert.equal(h.share.hidden,false);await h.share.handlers.click();assert.equal(sent.url,h.share.dataset.url);assert.equal(sent.title,'Real shop');assert.equal(h.feedback.textContent,'Store shared.');assert.equal(h.share.disabled,false);
});
test('clipboard fallback copies the canonical URL',async()=>{
    let copied;const h=harness({clipboard:{writeText:async url=>{copied=url;}}});await h.share.handlers.click();assert.equal(copied,h.share.dataset.url);assert.equal(h.feedback.textContent,'Store link copied.');
});
test('share cancellation stays quiet and failures explain manual copying',async()=>{
    let h=harness({share:async()=>{throw Object.assign(new Error(),{name:'AbortError'});}});await h.share.handlers.click();assert.equal(h.feedback.textContent,'');assert.equal(h.share.disabled,false);
    h=harness();await h.share.handlers.click();assert.match(h.feedback.textContent,/Copy the store URL/);assert.equal(h.share.disabled,false);
});
test('pending share prevents duplicate interactions',async()=>{
    let complete,count=0;const h=harness({share:()=>{count++;return new Promise(resolve=>complete=resolve);}});const first=h.share.handlers.click();assert.equal(h.share.disabled,true);await h.share.handlers.click();assert.equal(count,1);complete();await first;assert.equal(h.share.disabled,false);
});
test('broken or cached missing images reveal existing placeholders',()=>{
    const h=harness();assert.equal(h.image.hidden,true);h.image.hidden=false;h.image.handlers.error();assert.equal(h.image.hidden,true);
});
test('anchor navigation keeps exactly one active location and tolerates unknown hashes',()=>{
    const h=harness();assert.equal(h.links[0].attributes['aria-current'],'location');h.location.hash='#reviews';h.window.handlers.hashchange();assert.equal(h.links[2].attributes['aria-current'],'location');assert.equal(h.links[0].attributes['aria-current'],undefined);h.location.hash='#unknown';h.window.handlers.hashchange();assert.equal(h.links[0].attributes['aria-current'],'location');
});
