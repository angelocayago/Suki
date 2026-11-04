const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/js/app.js'),'utf8').replace(/^import .*;\r?\n/gm,'');
function page(answer){let handler,calls=0;vm.runInNewContext(source,{Alpine:{start(){}},window:{confirm(){calls++;return answer;}},document:{addEventListener(type,fn){handler=fn;}}});return {handler,get calls(){return calls;}};}
test('seller modal already handling submit does not also open the global confirmation',()=>{
    const p=page(true);p.handler({defaultPrevented:true,target:{closest:()=>({})}});assert.equal(p.calls,0);
});
test('ordinary Buyer logout keeps one cancelable confirmation and the original POST form',()=>{
    const p=page(false),form={method:'POST',action:'/logout',csrf:'original'};let canceled=false;
    p.handler({defaultPrevented:false,target:{closest:()=>form},preventDefault(){canceled=true;}});
    assert.equal(p.calls,1);assert.equal(canceled,true);assert.equal(form.method,'POST');assert.equal(form.csrf,'original');
});
