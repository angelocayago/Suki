const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const script = name => fs.readFileSync(path.join(__dirname,`../../resources/views/seller/partials/${name}.blade.php`),'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];
const clean = value => JSON.parse(JSON.stringify(value));
const context = {};
vm.runInNewContext(script('product-option-engine'),context);
const engine = context.SukiProductOptions;

test('custom names, whitespace, case-insensitive duplicates and Cartesian products', () => {
    const groups = engine.normalize([{name:' Flavor ',values:' Chocolate, ,Vanilla, chocolate, VANILLA '},{name:'Pack Size',values:'Single, Pack of 6'}]);
    assert.deepEqual(clean(engine.combinations(groups)),[
        {Flavor:'Chocolate','Pack Size':'Single'},{Flavor:'Chocolate','Pack Size':'Pack of 6'},
        {Flavor:'Vanilla','Pack Size':'Single'},{Flavor:'Vanilla','Pack Size':'Pack of 6'},
    ]);
    assert.equal(engine.combinations(engine.normalize([{name:'Storage',values:'128 GB,256 GB'}])).length,2);
    assert.equal(engine.combinations(engine.normalize([{name:'Storage',values:'128 GB,256 GB'},{name:'Capacity',values:'A,B'},{name:'Material',values:'Steel'}])).length,4);
    assert.deepEqual(clean(engine.combinations([])),[]);
});

test('invalid names, missing values and payload expansion are bounded', () => {
    for(const raw of [
        [{name:'Size',values:'S'},{name:' size ',values:'L'}],
        [{name:'',values:'S'}],[{name:'Flavor',values:', ,'}],
        [{name:'__proto__',values:'S'}],[{name:'Bad[name]',values:'S'}],
        [{name:'Flavor',values:Array.from({length:21},(_,i)=>`Value ${i}`).join(',')}],
        Array.from({length:4},(_,i)=>({name:`Group ${i}`,values:'One'})),
    ]) assert.throws(()=>engine.normalize(raw));
    assert.throws(()=>engine.combinations(engine.normalize([{name:'First',values:'1,2,3,4,5,6,7,8'},{name:'Second',values:'1,2,3,4,5,6,7'}])),/50/);
});

test('regeneration preserves unchanged price stock SKU and weight even when group order changes', () => {
    const old = {options:{Flavor:'Chocolate','Pack Size':'Single'},price:'35.50',stock:'9',sku:'CHOC-SINGLE',weight:'0.125'};
    const previous = new Map([[engine.identity(old.options),old]]);
    const groups = engine.normalize([{name:'Pack Size',values:'Single'},{name:'Flavor',values:'chocolate,Vanilla'}]);
    const rows = engine.regenerate(groups,previous);
    assert.equal(rows[0].price,'35.50');assert.equal(rows[0].stock,'9');
    assert.equal(rows[0].sku,'CHOC-SINGLE');assert.equal(rows[0].weight,'0.125');
    assert.equal(rows[1].price,undefined);
});

// Execute the actual page script with a small DOM double, without browser or DB access.
class Element {
    constructor(tag='div') { this.tag=tag;this.children=[];this.dataset={};this.handlers={};this.value='';this.textContent='';this.disabled=false;this.hidden=false; }
    append(...children) { for(const child of children) {child.parent=this;this.children.push(child);} }
    appendChild(child) {this.append(child);return child;}
    replaceChildren(...children) {this.children=[];this.append(...children);}
    remove() {this.parent.children=this.parent.children.filter(child=>child!==this);}
    addEventListener(type,handler) {(this.handlers[type]??=[]).push(handler);}
    fire(type,event={}) {for(const handler of this.handlers[type]||[])handler(event);}
    setAttribute(name,value) {this[name]=value;}
    focus() {} scrollIntoView() {}
    setCustomValidity(value) {this.validationMessage=value;}
    querySelectorAll(selector) {
        const match = node => selector==='input' ? node.tag==='input'
            : selector==='button[type=submit]' ? node.tag==='button'&&node.type==='submit'
            : /^\[data-/.test(selector) ? Object.hasOwn(node.dataset,selector.slice(6,-1).replace(/-([a-z])/g,(_,letter)=>letter.toUpperCase())) : false;
        return this.children.flatMap(child=>[...(match(child)?[child]:[]),...child.querySelectorAll(selector)]);
    }
    querySelector(selector) {return this.querySelectorAll(selector)[0]||null;}
}
function harness(old={}) {
    const ids=Object.fromEntries(['universalProductForm','optionGroups','generatedVariants','simplePricing','productMode','optionFeedback','variantCount','generateVariants','addOption','pricingHelp','images','imagePreview','imageUploadStatus'].map(id=>[id,new Element()]));
    ids.simplePricing.append(new Element('input'),new Element('input'),new Element('input'));
    ids.universalProductForm.append(ids.simplePricing,ids.generatedVariants);
    const submit=new Element('button');submit.type='submit';ids.universalProductForm.append(submit);
    const window={handlers:{},confirm:()=>true,addEventListener(type,handler){this.handlers[type]=handler;}};
    let pageSource=script('product-create-script');
    for(const key of ['Groups','Variants','Mode','Colors']) pageSource=pageSource.replace(new RegExp(`const old${key} = .*?;`),`const old${key} = ${JSON.stringify(old[key.toLowerCase()]??(key==='Mode'?'simple':[]))};`);
    const sandbox={document:{getElementById:id=>ids[id],createElement:tag=>new Element(tag),addEventListener:(_,fn)=>fn()},window,SukiProductOptions:engine,URL:{createObjectURL:file=>'blob:'+file.name,revokeObjectURL(){}},DataTransfer:class{constructor(){this.files=[];this.items={add:file=>this.files.push(file)};}}};
    vm.runInNewContext(pageSource,sandbox);
    const group = (name,values) => {
        ids.addOption.fire('click');const row=ids.optionGroups.children.at(-1);
        row.querySelector('[data-option-name]').value=name;row.querySelector('[data-option-values]').value=values;ids.optionGroups.fire('input');return row;
    };
    return {ids,window,submit,group};
}

test('page generates independent fields, preserves edited rows and safely returns to simple mode', () => {
    const {ids,group}=harness();
    const flavor=group('Flavor','Chocolate,Vanilla');group('Pack Size','Single,Pack of 6');
    ids.generateVariants.fire('click');
    assert.equal(ids.generatedVariants.children.length,4);assert.equal(ids.productMode.value,'variants');
    assert.equal(ids.simplePricing.hidden,true);assert.equal(ids.simplePricing.children[0].disabled,true);
    const first=ids.generatedVariants.children[0];
    const fields=first.querySelectorAll('[data-field]');
    for(const [index,value] of ['35.50','50','CHOC-SINGLE','0.125'].entries())fields[index].value=value;
    flavor.querySelector('[data-option-values]').value='Chocolate,Vanilla,Strawberry';ids.optionGroups.fire('input');
    ids.generateVariants.fire('click');
    assert.equal(ids.generatedVariants.children.length,6);
    assert.deepEqual(ids.generatedVariants.children[0].querySelectorAll('[data-field]').map(input=>input.value),['35.50','50','CHOC-SINGLE','0.125']);
    assert.equal(ids.generatedVariants.children[4].querySelectorAll('[data-field]')[0].value,'');
    while(ids.optionGroups.children.length)ids.optionGroups.children[0].children.at(-1).fire('click');
    assert.equal(ids.generatedVariants.children.length,0);assert.equal(ids.productMode.value,'simple');
    assert.equal(ids.simplePricing.hidden,false);assert.equal(ids.simplePricing.children[0].disabled,false);
});

test('changed options block submission; canceled removal preserves data; validation input restores rows', () => {
    const {ids,window,group}=harness({groups:[{name:'Flavor',values:'Chocolate'}],mode:'variants',variants:[{options:{Flavor:'Chocolate'},price:'35.00',stock:'5',sku:'C',weight:'0.2'}]});
    assert.equal(ids.generatedVariants.children.length,1);
    assert.equal(ids.generatedVariants.children[0].querySelectorAll('[data-field]')[0].value,'35.00');
    window.confirm=()=>false;
    ids.optionGroups.children[0].children.at(-1).fire('click');assert.equal(ids.optionGroups.children.length,1);
    group('Pack Size','Single');let blocked=false;
    ids.universalProductForm.fire('submit',{preventDefault(){blocked=true;}});
    assert.equal(blocked,true);assert.match(ids.optionFeedback.textContent,/Generate/);
});

test('pending photo removal updates FileList and cover order without uploading', () => {
    const {ids}=harness();
    ids.images.files=[{name:'one.png',type:'image/png',size:20},{name:'two.png',type:'image/png',size:20}];
    ids.images.fire('change');assert.equal(ids.imagePreview.children.length,2);
    ids.imagePreview.children[0].children.at(-1).fire('click');
    assert.equal(ids.images.files.length,1);assert.equal(ids.images.files[0].name,'two.png');
    assert.match(ids.imagePreview.children[0].children[1].textContent,/Cover · two.png/);
    ids.images.files=Array.from({length:21},()=>({name:'x.png',type:'image/png',size:20}));ids.images.fire('change');
    assert.match(ids.images.validationMessage,/up to 20/);
    ids.images.files=[{name:'large.png',type:'image/png',size:4*1024*1024+1}];ids.images.fire('change');
    assert.match(ids.images.validationMessage,/no larger than 4 MB/);
});
