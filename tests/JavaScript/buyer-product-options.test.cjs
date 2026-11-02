const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const path = require('node:path');
const source=fs.readFileSync(path.join(__dirname,'../../resources/views/buyer/product-details.blade.php'),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
function page(variants,controls=[],choice=null) {
    const ids=Object.fromEntries(['selectedVariant','quantity','quantityInput','variantStock','variantAvailability','variantPrice','mainProductImage'].map(id=>[id,{value:'',textContent:''}]));
    ids.productVariantChoice=choice;const submit={};
    const sandbox={document:{getElementById:id=>ids[id]??null,querySelector:()=>submit,querySelectorAll:()=>controls},alert(){}};
    vm.runInNewContext(source.replace(/const purchasableVariants = .*?;/,`const purchasableVariants = ${JSON.stringify(variants)};`).replace(/const mediaByColor = .*?;/,'const mediaByColor = {};'),sandbox);
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
test('simple products, sold-out options and legacy named variants keep exact identity',()=>{
    const simple=page([{id:1,options:{},price:50,stock:100}]);
    assert.equal(simple.ids.selectedVariant.value,1);assert.equal(simple.submit.disabled,false);
    const sold=page([{id:2,options:{},price:50,stock:0}]);
    assert.equal(sold.submit.disabled,true);assert.match(sold.ids.variantAvailability.textContent,/out of stock/);
    const legacy=page([{id:1,options:{},price:50,stock:100},{id:2,options:{Color:'Blue'},price:60,stock:10}],[],{value:'2'});
    assert.equal(legacy.ids.selectedVariant.value,2);assert.equal(legacy.ids.variantPrice.textContent,'₱60.00');
});
