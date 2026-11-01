const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/reviews/seller-partials/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

// Execute the real Seller script in a DOM double; visual layout is checked
// separately by the Blade/CSS checks and still needs a browser visual review.
function harness() {
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
    const fields = [ids.reviewSearch,ids.reviewProductId,...radios];
    ids.sellerReviewFilters.querySelectorAll = () => fields;
    const submissions = [];
    ids.sellerReviewFilters.requestSubmit = () => {
        const event = {preventDefault(){ this.prevented = true; }};
        ids.sellerReviewFilters.handlers.submit(event);
        if (!event.prevented) submissions.push(Object.fromEntries(fields.filter(input=>!input.disabled && (input.type !== 'radio' || input.checked)).map(input=>[input.name,input.value])));
    };
    const image = Object.assign(element(),{complete:false,naturalWidth:48});
    const alreadyBroken = Object.assign(element(),{complete:true,naturalWidth:0});
    const reply = element(), replyButton = element(); replyButton.textContent = 'Save reply';
    reply.querySelector = () => replyButton; reply.reportValidity = () => true;
    const windowEvents = [], timers = new Map(), fetches = []; let timerId = 0;
    vm.runInNewContext(source,{
        document:{
            addEventListener:(_,callback)=>callback(),
            querySelectorAll:selector=>selector==='[data-review-product-image]'?[image,alreadyBroken]:[reply],
            getElementById:id=>ids[id], createElement:element,
        },
        window:{location:{href:'http://localhost/seller/reviews'},addEventListener:(_,callback)=>windowEvents.push(callback)},
        URL, AbortController,
        setTimeout(callback,delay){ timers.set(++timerId,{callback,delay}); return timerId; },
        clearTimeout(id){ timers.delete(id); },
        async fetch(url){ fetches.push(url.toString()); return {ok:true,json:async()=>[{id:1,name:'Blue Product'}]}; },
    });
    async function flush(delay) { for (const [id,timer] of [...timers]) if(timer.delay===delay){ timers.delete(id); await timer.callback(); } }
    return {ids,radios,submissions,image,alreadyBroken,reply,replyButton,windowEvents,fetches,flush,getFocused:()=>focused};
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
    assert.deepEqual(submissions,[{search:'quality'}]);
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
