const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/buyer/product-details.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function node() {
    const classes=new Set();
    return {value:'',textContent:'',dataset:{},handlers:{},classList:{add(...names){names.forEach(name=>classes.add(name));},remove(...names){names.forEach(name=>classes.delete(name));},contains(name){return classes.has(name);}},
        addEventListener(type,fn){this.handlers[type]=fn;},setAttribute(name,value){this[name]=value;},scrollIntoView(){},pause(){this.paused=true;},load(){this.loaded=true;}};
}
function page(variants,controls=[],choice=null,mediaByColor={},media=[]) {
    const ids=Object.fromEntries(['selectedVariant','quantity','quantityInput','variantStock','variantAvailability','variantPrice','mainProductImage','mainProductVideo','productMediaPrevious','productMediaNext'].map(id=>[id,node()]));
    ids.productVariantChoice=choice;const submit={};
    const sandbox={document:{getElementById:id=>ids[id]??null,querySelector:()=>submit,querySelectorAll:selector=>selector==='[data-product-option]'?controls:selector==='[data-product-media]'?media:[]},alert(){}};
    vm.runInNewContext(source.replace(/const purchasableVariants = .*?;/,`const purchasableVariants = ${JSON.stringify(variants)};`).replace(/const mediaByColor = .*?;/,`const mediaByColor = ${JSON.stringify(mediaByColor)};`),sandbox);
    return {ids,submit,sandbox};
}
test('arbitrary option selections resolve the exact variant ID price stock and quantity limit',()=>{
    const controls=[{dataset:{productOption:'Flavor'},value:'Chocolate'},{dataset:{productOption:'Pack Size'},value:'Single'}];
    const variants=[{id:11,options:{Flavor:'Chocolate','Pack Size':'Single'},price:35,stock:9},{id:22,options:{Flavor:'Vanilla','Pack Size':'Pack of 6'},price:190,stock:2}];
    const {ids,submit,sandbox}=page(variants,controls);
    assert.equal(ids.selectedVariant.value,11);assert.equal(ids.variantPrice.textContent,'₱35.00');
    for(let i=0;i<6;i++)sandbox.increaseQuantity();
    controls[0].value='Vanilla';controls[1].value='Pack of 6';sandbox.resolveVariant();
    assert.equal(ids.selectedVariant.value,22);assert.equal(ids.variantPrice.textContent,'₱190.00');
    assert.equal(ids.quantityInput.value,2);assert.equal(submit.disabled,false);
    sandbox.increaseQuantity();assert.equal(ids.quantityInput.value,2);
    controls[0].value='Unknown';sandbox.resolveVariant();
    assert.equal(ids.selectedVariant.value,'');assert.equal(submit.disabled,true);
    assert.match(ids.variantAvailability.textContent,/unavailable/);
});
test('video carousel and color media follow exact custom variants without duplicate state',()=>{
    const controls=[{dataset:{productOption:'Color'},value:'Blue'},{dataset:{productOption:'Flavor'},value:'Vanilla'}];
    const media=['blue.png','red.png','demo.mp4'].map((src,index)=>Object.assign(node(),{dataset:{mediaType:index===2?'video':'image',mediaSrc:src}}));
    const {ids,sandbox}=page([{id:1,options:{Color:'Blue',Flavor:'Vanilla'},price:35,stock:10},{id:2,options:{Color:'Red',Flavor:'Vanilla'},price:45,stock:3}],controls,null,{Blue:['blue.png'],Red:['red.png']},media);
    assert.equal(ids.mainProductImage.src,'blue.png');
    media[2].handlers.click();
    assert.equal(ids.mainProductVideo.src,'demo.mp4');assert.equal(ids.mainProductImage.classList.contains('hidden'),true);
    controls[0].value='Red';sandbox.resolveVariant();
    assert.equal(ids.selectedVariant.value,2);assert.equal(ids.mainProductImage.src,'red.png');
    assert.equal(ids.mainProductVideo.paused,true);assert.equal(ids.mainProductVideo.classList.contains('hidden'),true);
    assert.equal(media[1]['aria-current'],'true');
    ids.productMediaNext.handlers.click();assert.equal(ids.mainProductVideo.classList.contains('hidden'),false);
    ids.productMediaPrevious.handlers.click();assert.equal(ids.mainProductImage.src,'red.png');
});
test('simple products, sold-out options and legacy named variants keep exact identity',()=>{
    const simple=page([{id:1,options:{},price:50,stock:100}]);
    assert.equal(simple.ids.selectedVariant.value,1);assert.equal(simple.submit.disabled,false);
    const sold=page([{id:2,options:{},price:50,stock:0}]);
    assert.equal(sold.submit.disabled,true);assert.match(sold.ids.variantAvailability.textContent,/out of stock/);
    const legacy=page([{id:1,options:{},price:50,stock:100},{id:2,options:{Color:'Blue'},price:60,stock:10}],[],{value:'2'});
    assert.equal(legacy.ids.selectedVariant.value,2);assert.equal(legacy.ids.variantPrice.textContent,'₱60.00');
});
