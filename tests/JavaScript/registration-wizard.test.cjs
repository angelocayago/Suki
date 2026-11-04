const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../resources/js/registration-wizard.js'),'utf8');

class Node {
    constructor(tag='div',className=''){this.tag=tag;this.className=className;this.children=[];this.dataset={};this.handlers={};this.hidden=false;this.value='value';this.valid=true;this.type='text';this.classList={toggle(){}};}
    append(...nodes){for(const node of nodes){node.parentElement?.removeChild(node);node.parentElement=this;this.children.push(node);}}
    prepend(node){node.parentElement?.removeChild(node);node.parentElement=this;this.children.unshift(node);}
    removeChild(node){this.children=this.children.filter(child=>child!==node);node.parentElement=null;}
    replaceChildren(){this.children=[];}
    setAttribute(key,value){this[key]=value;}
    addEventListener(type,fn){this.handlers[type]=fn;}
    focus(){this.focused=true;}
    checkValidity(){return this.valid;}
    reportValidity(){this.reported=true;return this.valid;}
    matches(selector){return selector==='input[name="_token"]'?this.tag==='input'&&this.name==='_token':selector.startsWith('.')?this.className.split(' ').includes(selector.slice(1)):selector==='[name]'?!!this.name:selector==='[name="registration_role_choice"]:checked'?this.name==='registration_role_choice'&&this.checked:selector.split(', ').includes(this.tag);}
    querySelectorAll(selector){return this.children.flatMap(child=>[...(child.matches(selector)?[child]:[]),...child.querySelectorAll(selector)]);}
    querySelector(selector){return this.querySelectorAll(selector)[0]||null;}
    click(){this.handlers.click?.();}
    set innerHTML(html){
        this.replaceChildren();
        if(html.includes('registration-actions__back'))for(const name of ['back','next','submit']){const button=new Node('button','registration-actions__'+name);button.type=name==='submit'?'submit':'button';this.append(button);}
        else if(html.includes('registration-panel__heading'))this.append(new Node('h2','registration-panel__heading'),new Node('p','registration-panel__hint'));
        else if(html.includes('registration_role_choice'))for(const value of ['logistics','rider']){const input=new Node('input');input.name='registration_role_choice';input.type='radio';input.value=value;input.checked=value==='logistics';const label=new Node('label');label.append(input);this.append(label);}
        else this.append(new Node('span'),new Node('span'));
    }
}
function page(kind='seller',errors=[]){
    const form=new Node('form');form.dataset={wizardKind:kind,errorFields:JSON.stringify(errors),riderUrl:'/rider/apply'};form.action='/register/'+kind;form.method='POST';form.noValidate=false;
    const names={buyer:['first_name','last_name','email','phone','government_id','password','password_confirmation','terms'],seller:['first_name','last_name','email','phone','password','password_confirmation','business_name','category_id','valid_id','business_permit','terms'],rider:['first_name','last_name','email','phone','password','address','vehicle_type','plate_number','government_id','terms'],logistics:['first_name','last_name','email','phone','password','business_name','government_id','terms']}[kind];
    const token=new Node('input');token.name='_token';token.type='hidden';token.value='original-csrf';form.append(token);
    const legacy=new Node();form.append(legacy);const controls={};
    for(const name of names){const control=new Node('input');control.name=name;if(/password/.test(name))control.type='password';if(/government_id|valid_id|business_permit/.test(name))control.type='file';const label=new Node('label');label.append(control);legacy.append(label);controls[name]=control;}
    let initialise,redirect;
    const sandbox={CSS:{escape:value=>value},window:{location:{assign:value=>{redirect=value;}}},document:{createElement:tag=>new Node(tag),querySelector:()=>null,querySelectorAll:()=>[form],addEventListener(type,fn){initialise=fn;}}};
    vm.runInNewContext(source,sandbox);initialise();
    return {form,controls,initialise,get redirect(){return redirect;},panels:form.querySelectorAll('.registration-panel'),next:form.querySelector('.registration-actions__next'),back:form.querySelector('.registration-actions__back')};
}

test('all role wizards retain original fields, CSRF, endpoints and a single initialization',()=>{
    for(const kind of ['buyer','seller','rider','logistics']){
        const p=page(kind);p.initialise();
        assert.equal(p.form.querySelectorAll('.registration-actions__submit').length,1);assert.equal(p.form.action,'/register/'+kind);assert.equal(p.form.method,'POST');
        assert.equal(p.form.querySelectorAll('[name]').find(node=>node.name==='_token').value,'original-csrf');
        p.controls.first_name.valid=false;p.next.click();assert.equal(p.panels[0].hidden,false);assert.equal(p.controls.first_name.reported,true);
        p.controls.first_name.valid=true;for(let i=0;i<p.panels.length-1;i++)p.next.click();
        let prevented=false;p.form.handlers.submit({preventDefault(){prevented=true;}});assert.equal(prevented,false);
        assert.equal(p.panels.at(-1).querySelectorAll('.registration-review__row').some(row=>row.children[0].textContent==='password'),false);
    }
});

test('server errors on a later step reveal earlier required fields before native submission',()=>{
    const p=page('seller',['business_permit']);p.controls.password.valid=false;
    p.next.click();assert.equal(p.panels.at(-1).hidden,false);
    assert.equal(p.form.noValidate,true,'wizard must control validation of hidden required fields');
    let prevented=false;p.form.handlers.submit({preventDefault(){prevented=true;}});
    assert.equal(prevented,true);assert.equal(p.panels[1].hidden,false);assert.equal(p.controls.password.reported,true);
});

test('logistics role choice keeps the existing rider application destination',()=>{
    const p=page('logistics');p.next.click();p.next.click();
    p.form.querySelectorAll('[name]').filter(control=>control.name==='registration_role_choice').forEach(control=>control.checked=control.value==='rider');
    p.next.click();assert.equal(p.redirect,'/rider/apply');
});
