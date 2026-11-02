const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/seller/partials/product-video-script.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function harness(){
    const element=()=>({children:[],style:{},handlers:{},addEventListener(type,fn){this.handlers[type]=fn;},appendChild(child){this.children.push(child);},replaceChildren(){this.children=[];},setCustomValidity(message){this.validationMessage=message;}});
    const ids={video:element(),videoPreview:element(),videoUploadStatus:element()};const revoked=[];
    vm.runInNewContext(source,{document:{getElementById:id=>ids[id],createElement:element,addEventListener:(_,fn)=>fn()},window:{addEventListener(){}},URL:{createObjectURL:file=>'blob:'+file.name,revokeObjectURL:url=>revoked.push(url)}});
    return {ids,revoked};
}
test('shared Create/Edit video preview accepts MP4/WebM and releases replaced selections',()=>{
    const {ids,revoked}=harness();
    ids.video.files=[{name:'one.mp4',type:'video/mp4',size:100}];ids.video.handlers.change();
    assert.equal(ids.videoPreview.children[0].src,'blob:one.mp4');assert.equal(ids.videoPreview.children[0].controls,true);
    ids.video.files=[{name:'two.webm',type:'video/webm',size:25*1024*1024}];ids.video.handlers.change();
    assert.deepEqual(revoked,['blob:one.mp4']);assert.equal(ids.video.validationMessage,'');
    ids.video.files=[];ids.video.handlers.change();assert.equal(ids.videoPreview.children.length,0);
    assert.deepEqual(revoked,['blob:one.mp4','blob:two.webm']);
});
test('shared video preview rejects unsupported media and files over 25 MB',()=>{
    const {ids}=harness();
    for(const file of [{name:'x.mov',type:'video/quicktime',size:100},{name:'huge.mp4',type:'video/mp4',size:25*1024*1024+1}]){
        ids.video.files=[file];ids.video.handlers.change();assert.match(ids.video.validationMessage,/MP4 or WebM.*25 MB/);assert.equal(ids.videoPreview.children.length,0);
    }
});
