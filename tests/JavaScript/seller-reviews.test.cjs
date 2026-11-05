const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/reviews/seller-partials/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

// Execute the real Seller script in a DOM double; visual layout is checked
// separately by the Blade/CSS checks and still needs a browser visual review.
function harness({tab='product'}={}) {
    let focused;
    function element() {
        return {
            value:'', name:'', type:'', checked:false, disabled:false, hidden:false,
            handlers:{}, attributes:{}, dataset:{}, children:[], textContent:'',
            addEventListener(type,callback) { this.handlers[type] = callback; },
            setAttribute(key,value) { this.attributes[key] = value; },
            removeAttribute(key) { delete this.attributes[key]; },
            focus() { focused = this; },
            appendChild(child) { this.children.push(child); },
            replaceChildren() { this.children = []; },
            querySelector() { return this.children[0]?.children[0]; },
        };
    }
    const ids = Object.fromEntries(['sellerReviewFilters','reviewFilterToggle','reviewFilterPanel','reviewSearch','reviewApply','reviewProductSearch','reviewProductId','reviewProductResults','reviewProductStatus','reviewProductClear'].map(id=>[id,element()]));
    ids.reviewFilterPanel.hidden = true; ids.reviewProductResults.hidden = true;
    ids.reviewSearch.name = 'search'; ids.reviewProductId.name = 'product';
    ids.reviewProductSearch.dataset.url = '/seller/reviews?lookup=products';
    const radios = ['','5','4','3','2','1'].map(value=>Object.assign(element(),{name:'rating',type:'radio',value,checked:value===''}));
    const tabInput=Object.assign(element(),{name:'tab',value:tab});
    const fields = [ids.reviewSearch,...(tab==='product'?[ids.reviewProductId]:[]),tabInput,...radios];
    ids.sellerReviewFilters.querySelectorAll = () => fields;
    const submissions = [];
    ids.sellerReviewFilters.requestSubmit = () => {
        const event = {preventDefault(){ this.prevented = true; }};
        ids.sellerReviewFilters.handlers.submit(event);
        if (!event.prevented) submissions.push(Object.fromEntries(fields.filter(input=>!input.disabled && (input.type !== 'radio' || input.checked)).map(input=>[input.name,input.value])));
    };
    const image = Object.assign(element(),{complete:false,naturalWidth:48});
    const alreadyBroken = Object.assign(element(),{complete:true,naturalWidth:0});
    function composer() {
        const form = element(), button = element(), textarea = element(), counter = element(), undo = element(), cancel = element(), status = element(), summary = element();
        button.textContent = 'Save reply'; undo.hidden = true;
        const editor = {open:true, querySelector:()=>summary};
        const templates = [
            'Thank you for your review! We appreciate your support and hope to serve you again.',
            'Maraming salamat po sa inyong review at suporta sa aming shop!',
            'We truly appreciate your feedback and support. Thank you for choosing our shop!',
            "Thank you for the great rating! We're glad you had a positive experience.",
        ].map(message=>Object.assign(element(),{dataset:{reviewTemplate:message}}));
        const selectors = {'button[type="submit"]':button,'textarea[name="response"]':textarea,'[data-review-counter]':counter,'[data-review-undo]':undo,'[data-review-cancel]':cancel,'[data-review-template-status]':status};
        form.querySelector = selector=>selectors[selector]; form.querySelectorAll=()=>templates;
        form.closest=()=>editor; form.reportValidity=()=>true;
        return {form,button,textarea,counter,undo,cancel,status,summary,editor,templates};
    }
    const first=composer(), second=composer(), reply=first.form, replyButton=first.button;
    const windowEvents = [], timers = new Map(), fetches = []; let timerId = 0;
    vm.runInNewContext(source,{
        document:{
            addEventListener:(_,callback)=>callback(),
            querySelectorAll:selector=>selector==='[data-review-product-image]'?[image,alreadyBroken]:[reply,second.form],
            getElementById:id=>tab==='shop' && id.startsWith('reviewProduct') ? null : ids[id], createElement:element,
        },
        window:{location:{href:'http://localhost/seller/reviews'},addEventListener:(_,callback)=>windowEvents.push(callback)},
        URL, AbortController,
        setTimeout(callback,delay){ timers.set(++timerId,{callback,delay}); return timerId; },
        clearTimeout(id){ timers.delete(id); },
        async fetch(url){ fetches.push(url.toString()); return {ok:true,json:async()=>[{id:1,name:'Blue Product'}]}; },
    });
    async function flush(delay) { for (const [id,timer] of [...timers]) if(timer.delay===delay){ timers.delete(id); await timer.callback(); } }
    return {ids,radios,submissions,image,alreadyBroken,reply,replyButton,first,second,windowEvents,fetches,flush,getFocused:()=>focused};
}

test('broken product thumbnails are hidden while successful thumbnails remain visible', () => {
    const {image,alreadyBroken} = harness();
    assert.equal(image.hidden,false); assert.equal(alreadyBroken.hidden,true);
    image.handlers.error(); assert.equal(image.hidden,true);
});

test('filters toggle, keep selections and restore keyboard focus on Escape', () => {
    const {ids,radios,getFocused} = harness();
    assert.equal(ids.reviewFilterPanel.hidden,true);
    ids.reviewFilterToggle.handlers.click(); assert.equal(ids.reviewFilterToggle.attributes['aria-expanded'],'true');
    radios.forEach(radio=>radio.checked=radio.value==='4');
    ids.reviewFilterPanel.handlers.keydown({key:'Escape'});
    assert.equal(ids.reviewFilterPanel.hidden,true); assert.equal(getFocused(),ids.reviewFilterToggle);
    ids.reviewFilterToggle.handlers.click(); assert.equal(radios.find(radio=>radio.checked).value,'4');
});

test('review keyword search debounces, sends clean criteria once and displays real submission state', async () => {
    const {ids,submissions,flush,windowEvents} = harness();
    ids.reviewSearch.value='qu'; ids.reviewSearch.handlers.input();
    ids.reviewSearch.value='quality'; ids.reviewSearch.handlers.input();
    assert.equal(submissions.length,0); await flush(300);
    assert.deepEqual(submissions,[{search:'quality',tab:'product'}]);
    assert.equal(ids.reviewApply.disabled,true); assert.equal(ids.sellerReviewFilters.attributes['aria-busy'],'true');
    ids.sellerReviewFilters.requestSubmit(); assert.equal(submissions.length,1);
    await flush(0); windowEvents.forEach(callback=>callback()); assert.equal(ids.reviewApply.disabled,false);
});

test('product selection uses lookup, stores an id, supports keyboard choice and clears to All Products', async () => {
    const {ids,fetches,flush,getFocused} = harness();
    ids.reviewProductSearch.value='blu'; ids.reviewProductSearch.handlers.input();
    ids.reviewProductSearch.value='blue'; ids.reviewProductSearch.handlers.input(); await flush(300);
    assert.equal(fetches.length,1); assert.match(fetches[0],/lookup=products/); assert.match(fetches[0],/q=blue/);
    let prevented = false;
    ids.reviewProductSearch.handlers.keydown({key:'ArrowDown',preventDefault(){ prevented=true; }});
    assert.equal(prevented,true);
    const button=ids.reviewProductResults.children[0].children[0]; assert.equal(getFocused(),button);
    button.handlers.click(); assert.equal(ids.reviewProductId.value,1); assert.equal(ids.reviewProductResults.hidden,true);
    ids.reviewProductClear.handlers.click(); assert.equal(ids.reviewProductId.value,''); assert.equal(ids.reviewProductSearch.value,'');
});

test('reply submit protects validation and accidental duplicate submission without changing reply content', () => {
    const {reply,replyButton,windowEvents} = harness();
    reply.handlers.submit({preventDefault(){ throw Error('Valid reply blocked'); }});
    assert.equal(replyButton.disabled,true); assert.equal(replyButton.textContent,'Saving…');
    let blocked = false; reply.handlers.submit({preventDefault(){blocked=true;}}); assert.equal(blocked,true);
    windowEvents.forEach(callback=>callback()); assert.equal(replyButton.disabled,false);
    reply.reportValidity=()=>false; blocked=false; reply.handlers.submit({preventDefault(){blocked=true;}}); assert.equal(blocked,true);
});

test('all four quick templates fill only their own composer and never auto-submit', () => {
    const h=harness();
    let submitted=0; h.reply.handlers.submit=()=>submitted++;
    h.second.textarea.value='Other review draft';
    h.first.templates.forEach(template=>{
        template.handlers.click();
        assert.equal(h.first.textarea.value,template.dataset.reviewTemplate);
        assert.equal(h.first.counter.textContent,`${Array.from(template.dataset.reviewTemplate).length}/500 characters`);
        assert.equal(h.getFocused(),h.first.textarea);
    });
    assert.equal(h.second.textarea.value,'Other review draft');
    assert.equal(submitted,0); assert.equal(h.fetches.length,0);
});

test('template text remains editable and erasable and Undo restores the previous manual draft', () => {
    const h=harness(), c=h.first;
    c.textarea.value='My personal reply'; c.templates[0].handlers.click();
    assert.equal(c.undo.hidden,false);
    c.textarea.value='Edited template 👋'; c.textarea.handlers.input();
    assert.equal(c.counter.textContent,'17/500 characters');
    c.undo.handlers.click(); assert.equal(c.textarea.value,'My personal reply'); assert.equal(c.undo.hidden,true);
    c.textarea.value=''; c.textarea.handlers.input(); assert.equal(c.counter.textContent,'0/500 characters');
    assert.equal(h.replyButton.disabled,false);
});

test('Cancel closes the editor without losing its draft or saving; manual reply still submits explicitly', () => {
    const h=harness(), c=h.first;
    c.textarea.value='Fully manual reply'; c.cancel.handlers.click();
    assert.equal(c.editor.open,false); assert.equal(c.textarea.value,'Fully manual reply');
    assert.equal(h.getFocused(),c.summary); assert.equal(h.replyButton.disabled,false);
    c.editor.open=true;
    h.reply.handlers.submit({preventDefault(){throw Error('Manual reply blocked');}});
    assert.equal(h.replyButton.disabled,true); assert.equal(c.textarea.value,'Fully manual reply');
    c.templates[0].handlers.click(); assert.equal(c.textarea.value,'Fully manual reply');
});

test('Shop tab needs no product picker while search, quick templates and manual replies remain functional', async () => {
    const h=harness({tab:'shop'});
    h.ids.reviewSearch.value='shop service'; h.ids.reviewSearch.handlers.input(); await h.flush(300);
    assert.deepEqual(h.submissions,[{search:'shop service',tab:'shop'}]);
    h.first.templates[1].handlers.click(); assert.match(h.first.textarea.value,/Maraming salamat/);
    h.first.textarea.value='Manual shop reply'; h.first.textarea.handlers.input();
    h.reply.handlers.submit({preventDefault(){throw Error('Valid Shop reply blocked');}});
    assert.equal(h.replyButton.disabled,true); assert.equal(h.first.textarea.value,'Manual shop reply');
    assert.equal(h.fetches.length,0);
});
