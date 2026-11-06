const {test} = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs'), path = require('node:path'), vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../resources/views/buyer/partials/catalog-script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({url='https://suki.test/shop', deferred=false, failure=false, invalid=false}={}) {
    const node = (value='',type='text') => ({value,type,checked:false,dataset:{},handlers:{},attributes:{},textContent:'',innerHTML:'old results',
        addEventListener(kind, fn) {(this.handlers[kind]??=[]).push(fn);},
        setAttribute(key,value) {this.attributes[key]=value;}, getAttribute(key) {return this.attributes[key];}, removeAttribute(key) {delete this.attributes[key];}, getClientRects() {return [1];}, reportValidity() {return true;}, focus() {this.focused=true;}});
    const fields=Object.fromEntries(['q','category','seller','in_stock','min_price','max_price','sort','rating'].map(name=>[name,node(name==='sort'?'newest':name==='in_stock'?'1':'',name==='in_stock'?'checkbox':'text')]));
    const form=node(),results=node(),status=node(),window=node(),navbars=[node(),node()];
    form.action='https://suki.test/shop';form.elements={namedItem:name=>fields[name]};
    form.querySelectorAll=()=>[fields.category,fields.seller,fields.sort,fields.in_stock];
    Object.entries(fields).forEach(([name,field])=>field.name=name);
    const categories=['clothing','shirts','food','snacks','tees','pants',...Array.from({length:9},(_,i)=>'extra-'+i)].map((slug,index)=>Object.assign(node(String(index+1),'checkbox'),{dataset:{categorySlug:slug,categoryParent:({1:'1',3:'3',4:'2',5:'1'})[index]||''},closest:()=>null}));
    const branches=['1','2','3'].map(id=>{
        const summary=node(),svg={style:{}};summary.querySelector=()=>svg;
        return Object.assign(node(),{dataset:{catalogBranch:id},open:false,querySelector:()=>summary,parentElement:{closest:()=>null}});
    });
    branches[1].parentElement.closest=()=>branches[0];
    categories[1].closest=()=>branches[0];categories[3].closest=()=>branches[2];categories[4].closest=()=>branches[1];
    const prices=[node(),node()], chips=[node(),node(),node()], apply=node(), sort=node(), mobile=node(), sidebar=node();
    const ratingInputs=['','5','4','3','2','1'].map(value=>node(value,'radio')),loading=node(),priceError=node(),close=node(),drawerApply=node(),backdrop=node(),showMore=node(),categorySearch=node(),clearCategories=node(),categoryCount=node();
    const isBelow=(input,id)=>input.value===id||input.dataset.categoryParent&&(input.dataset.categoryParent===id||isBelow(categories.find(n=>n.value===input.dataset.categoryParent),id));
    const roots=categories.filter(input=>!input.dataset.categoryParent).map(input=>Object.assign(node(),{textContent:categories.filter(n=>isBelow(n,input.value)).map(n=>n.dataset.categorySlug).join(' '),querySelectorAll:selector=>selector==='[data-catalog-category-input]'?categories.filter(n=>isBelow(n,input.value)):[]}));
    sidebar.querySelector=()=>close;sidebar.querySelectorAll=()=>[close];
    prices[0].dataset.catalogPrice='min_price';prices[1].dataset.catalogPrice='max_price';
    chips[0].dataset={catalogCategory:'clothing',categoryId:'1'};chips[1].dataset={catalogCategory:'food',categoryId:'3'};chips[2].dataset={catalogCategory:'',categoryId:''};
    const classes=new Set(['hidden']);sidebar.classList={contains:key=>classes.has(key),add:key=>classes.add(key),remove:key=>classes.delete(key),toggle(key,force){const add=force===undefined?!classes.has(key):force;add?classes.add(key):classes.delete(key);}};
    const requests=[],entries=[],timers=new Map(); let timerId=0;
    const document={getElementById:id=>({catalogFilters:form,catalogResults:results,catalogStatus:status,catalogSidebar:sidebar,catalogLoading:loading,catalogPriceError:priceError,catalogBackdrop:backdrop})[id],querySelectorAll:selector=>({'[data-catalog-navbar-search]':navbars,'[data-catalog-category-input]':categories,'[data-catalog-category]':chips,'[data-catalog-price]':prices,'[data-catalog-apply-price]':[apply],'[data-catalog-sort]':[sort],'[data-catalog-mobile-filters]':[mobile],'[data-catalog-branch]':branches,'[data-catalog-rating]':ratingInputs,'[data-catalog-root]':roots,'[data-catalog-category-search]':[categorySearch],'[data-catalog-show-more]':[showMore],'[data-catalog-close]':[close],'[data-catalog-drawer-apply]':[drawerApply],'[data-catalog-clear-categories]':[clearCategories],'[data-catalog-category-count]':[categoryCount]})[selector]||[]};
    window.location={href:url,origin:'https://suki.test'};
    const history={state:null,pushState(state,_title,value) {this.state=state;entries.push(value);window.location.href=value;},replaceState(state,_title,value) {this.state=state;window.location.href=value;}};
    const response=(html='new results',count=2)=>({ok:true,status:200,json:async()=>({html,count})});
    const fetch=(href,options)=>{
        const request={href,options};requests.push(request);
        if(deferred)return new Promise((resolve,reject)=>Object.assign(request,{resolve,reject}));
        if(failure)return Promise.reject(Error('Network unavailable'));
        if(invalid)return Promise.resolve({ok:false,status:422,json:async()=>({errors:{max_price:['Maximum price must be at least minimum price.']}})});
        return Promise.resolve(response());
    };
    const eventFor=(extra={})=>({defaultPrevented:false,button:0,preventDefault(){this.defaultPrevented=true;},...extra});
    const dispatch=(target,kind,extra={})=>{const event=eventFor(extra);for(const fn of target.handlers[kind]??[])fn(event);return event;};
    const settle=()=>new Promise(resolve=>setImmediate(resolve));
    const fire=async(target,kind,extra={})=>{const event=dispatch(target,kind,extra);await settle();return event;};
    form.requestSubmit=()=>dispatch(form,'submit');apply.click=()=>dispatch(apply,'click');
    const context={document,window,history,fetch,URL,AbortController,
        setTimeout(fn){timers.set(++timerId,fn);return timerId;},clearTimeout(id){timers.delete(id);}};
    const run=()=>vm.runInNewContext(source,context);run();
    const link=(href,{reset=false,pagination=false,filter=false}={})=>({href,hasAttribute:name=>name==='data-catalog-reset'?reset:name==='data-catalog-filter-link'?filter:false,closest:selector=>selector==='a[href]'?linkTarget:selector==='[data-catalog-pagination]'&&pagination?{}:null});
    let linkTarget;
    const click=async(href,options={},extra={})=>{linkTarget=link(href,options);return fire(results,'click',{target:linkTarget,...extra});};
    return {form,fields,results,status,window,navbars,requests,entries,timers,fire,settle,response,click,run,categories,prices,chips,apply,sort,mobile,sidebar,branches,history,ratingInputs,loading,priceError,close,drawerApply,backdrop,showMore,categorySearch,clearCategories,categoryCount,roots};
}

test('initial URL restores all filters and desktop/mobile navbar search',()=>{
    const h=harness({url:'https://suki.test/shop?q=blue&category=shirts&seller=shop-one&min_price=10&max_price=50&in_stock=1&sort=price_desc&page=2'});
    assert.equal(h.fields.q.value,'blue');assert.equal(h.fields.category.value,'__selected');assert.equal(h.fields.seller.value,'shop-one');
    assert.equal(h.fields.min_price.value,'10');assert.equal(h.fields.max_price.value,'50');assert.equal(h.fields.in_stock.checked,true);assert.equal(h.fields.sort.value,'price_desc');
    assert.ok(h.navbars.every(input=>input.value==='blue'));assert.equal(h.requests.length,0);
});

test('Apply filters uses GET, resets page, updates results/pagination and commits one URL',async()=>{
    const h=harness({url:'https://suki.test/shop?category=shirts&page=3'});h.fields.q.value='cotton shirt';h.fields.in_stock.checked=true;
    assert.equal((await h.fire(h.form,'submit')).defaultPrevented,true);
    const request=h.requests[0],url=new URL(request.href);
    assert.equal(url.searchParams.get('q'),'cotton shirt');assert.equal(url.searchParams.get('categories[]'),'2');assert.equal(url.searchParams.get('in_stock'),'1');assert.equal(url.searchParams.has('page'),false);
    assert.equal(request.options.headers.Accept,'application/json');assert.equal(request.options.credentials,'same-origin');
    assert.equal(h.results.innerHTML,'new results');assert.equal(h.results.attributes['aria-busy'],'false');assert.equal(h.entries.length,1);assert.match(h.status.textContent,/2 products found/);
});

test('category and stock controls submit their state and unchecked stock is omitted',async()=>{
    const h=harness();h.fields.category.value='clothing';await h.fire(h.fields.category,'change');
    assert.equal(new URL(h.requests[0].href).searchParams.get('categories[]'),'1');
    assert.equal(new URL(h.requests[0].href).searchParams.has('in_stock'),false);
    h.fields.in_stock.checked=true;await h.fire(h.fields.in_stock,'change');assert.equal(new URL(h.requests[1].href).searchParams.get('in_stock'),'1');
});

test('filtered pagination preserves parameters and Reset clears all filters',async()=>{
    const h=harness();await h.click('https://suki.test/shop?q=blue&category=shirts&sort=price_asc&page=2',{pagination:true});
    assert.equal(h.fields.q.value,'blue');assert.equal(h.fields.category.value,'__selected');assert.equal(h.fields.sort.value,'price_asc');assert.equal(h.results.focused,true);
    assert.equal(new URL(h.requests[0].href).searchParams.get('page'),'2');
    await h.click('https://suki.test/shop',{reset:true});
    assert.equal(h.fields.q.value,'');assert.equal(h.fields.category.value,'');assert.equal(h.fields.seller.value,'');assert.equal(h.fields.in_stock.checked,false);assert.equal(h.fields.sort.value,'newest');
    assert.equal(h.window.location.href,'https://suki.test/shop');
});

test('Back/Forward restores filters and reloads results without creating history entries',async()=>{
    const h=harness();
    h.window.location.href='https://suki.test/shop?q=older&category=food&min_price=0&in_stock=1&sort=price_desc';
    await h.fire(h.window,'popstate');
    assert.equal(h.fields.q.value,'older');assert.equal(h.fields.category.value,'food');assert.equal(h.fields.min_price.value,'0');assert.equal(h.fields.in_stock.checked,true);assert.equal(h.fields.sort.value,'price_desc');assert.equal(h.entries.length,0);
    h.window.location.href='https://suki.test/shop';await h.fire(h.window,'popstate');
    assert.equal(h.fields.q.value,'');assert.equal(h.fields.in_stock.checked,false);assert.equal(h.fields.sort.value,'newest');assert.equal(h.requests.length,2);assert.equal(h.entries.length,0);
});

test('stale AJAX responses cannot overwrite a more recent search or its loading state',async()=>{
    const h=harness({deferred:true});h.fields.q.value='first';await h.fire(h.form,'submit');h.fields.q.value='second';await h.fire(h.form,'submit');
    assert.equal(h.requests[0].options.signal.aborted,true);assert.equal(h.results.attributes['aria-busy'],'true');
    h.requests[1].resolve(h.response('second cards and pagination',1));await h.settle();
    h.requests[0].resolve(h.response('stale cards',99));await h.settle();
    assert.equal(h.results.innerHTML,'second cards and pagination');assert.equal(h.results.attributes['aria-busy'],'false');assert.match(h.window.location.href,/q=second/);assert.equal(h.entries.length,1);
});

test('search input debounces requests and a newer edit cancels pending work',async()=>{
    const h=harness({deferred:true});h.fields.q.value='a';await h.fire(h.fields.q,'input');h.fields.q.value='ab';await h.fire(h.fields.q,'input');
    assert.equal(h.timers.size,1);assert.equal(h.requests.length,0);[...h.timers.values()][0]();await h.settle();
    assert.equal(new URL(h.requests[0].href).searchParams.get('q'),'ab');
    h.fields.q.value='abc';await h.fire(h.fields.q,'input');assert.equal(h.requests[0].options.signal.aborted,true);
});

test('validation/network failures preserve results and announce recovery instead of broken navigation',async()=>{
    for(const options of [{failure:true},{invalid:true}]) {
        const h=harness(options);await h.fire(h.form,'submit');assert.equal(h.results.innerHTML,'old results');assert.equal(h.entries.length,0);assert.equal(h.results.attributes['aria-busy'],'false');assert.match(h.status.textContent,/Previous results are still shown/);
        if(options.invalid)assert.match(h.status.textContent,/Maximum price/);
    }
});

test('delegated pagination is initialized once and ordinary product/modified clicks keep native navigation',async()=>{
    const h=harness();h.run();assert.equal(h.form.handlers.submit.length,1);assert.equal(h.results.handlers.click.length,2);
    assert.equal((await h.click('https://suki.test/product/shirt')).defaultPrevented,false);
    assert.equal((await h.click('https://suki.test/shop?page=2',{pagination:true},{ctrlKey:true})).defaultPrevented,false);
    assert.equal(h.requests.length,0);
    await h.click('https://suki.test/shop?page=2',{pagination:true});await h.click('https://suki.test/shop?page=3',{pagination:true});assert.equal(h.requests.length,2);
});


test('restored sidebar categories, price apply and category chips share the catalog filters',async()=>{
    const h=harness({url:'https://suki.test/shop?q=blue&category=shirts&min_price=10&page=3'});
    assert.equal(h.categories[1].checked,true);assert.equal(h.prices[0].value,'10');assert.equal(h.chips[0].attributes['aria-current'],'false');
    h.categories[0].checked=true;await h.fire(h.categories[0],'change');
    assert.equal(h.categories[1].checked,true);assert.deepEqual(new URL(h.requests[0].href).searchParams.getAll('categories[]'),['1']);
    h.prices[0].value='20';h.prices[1].value='80';await h.fire(h.apply,'click');
    assert.equal(new URL(h.requests[1].href).searchParams.get('min_price'),'20');assert.equal(new URL(h.requests[1].href).searchParams.get('max_price'),'80');
    await h.fire(h.chips[1],'click');assert.equal(new URL(h.requests[2].href).searchParams.get('categories[]'),'3');
    assert.equal(new URL(h.requests[2].href).searchParams.get('q'),'blue');assert.equal(new URL(h.requests[2].href).searchParams.has('page'),false);
});

test('restored toolbar sort and mobile Filters work after delegated AJAX replacement',async()=>{
    const h=harness();const sort={value:'price_desc',matches:selector=>selector==='[data-catalog-sort]'};
    await h.fire(h.results,'change',{target:sort});assert.equal(h.fields.sort.value,'price_desc');assert.equal(h.sort.value,'price_desc');
    const button={closest:selector=>selector==='[data-catalog-mobile-filters]'?h.mobile:null};
    await h.fire(h.results,'click',{target:button});assert.equal(h.sidebar.classList.contains('catalog-drawer-open'),true);assert.equal(h.mobile.attributes['aria-expanded'],'true');
    await h.fire(h.form,'submit');assert.equal(h.mobile.attributes['aria-expanded'],'true');
    await h.fire(h.results,'click',{target:button});assert.equal(h.sidebar.classList.contains('catalog-drawer-open'),false);
    h.window.location.href='https://suki.test/shop?category=shirts&min_price=5&sort=price_asc';await h.fire(h.window,'popstate');
    assert.equal(h.categories[1].checked,true);assert.equal(h.prices[0].value,'5');assert.equal(h.sort.value,'price_asc');assert.equal(h.chips[0].attributes['aria-current'],'false');
});


test('subtree selections and partial parents survive collapse, paging, reset and history',async()=>{
    const h=harness({url:'https://suki.test/shop?categories%5B0%5D=2&categories%5B1%5D=4'});
    assert.equal(h.categories[1].checked,true);assert.equal(h.categories[3].checked,true);
    assert.equal(h.branches[0].open,true);assert.equal(h.branches[2].open,true);
    h.categories[0].checked=true;await h.fire(h.categories[0],'change');
    assert.deepEqual(new URL(h.requests[0].href).searchParams.getAll('categories[]'),['1','4']);
    h.categories[1].checked=false;await h.fire(h.categories[1],'change');assert.equal(h.categories[0].checked,false);assert.equal(h.categories[0].indeterminate,true);
    h.branches[0].open=false;await h.fire(h.branches[0],'toggle');
    assert.equal(h.categories[0].indeterminate,true);assert.equal(h.requests.length,2);
    assert.equal(h.branches[0].querySelector().attributes['aria-expanded'],'false');
    const saved=JSON.parse(JSON.stringify(h.history.state));
    await h.click('https://suki.test/shop?categories%5B0%5D=1&categories%5B1%5D=4&page=2',{pagination:true});
    assert.equal(h.branches[0].open,false);assert.equal(h.categories[3].checked,true);
    await h.click('https://suki.test/shop',{reset:true});assert.ok(h.categories.every(input=>!input.checked));assert.ok(h.branches.every(branch=>!branch.open));
    h.window.location.href='https://suki.test/shop?categories[]=1&categories[]=4';await h.fire(h.window,'popstate',{state:saved});
    assert.equal(h.categories[0].checked,true);assert.equal(h.categories[3].checked,true);assert.equal(h.branches[0].open,false);assert.equal(h.branches[2].open,true);
});

test('nested descendant selections open actual ancestor branches, and expansion makes no catalog request',async()=>{
    const h=harness({url:'https://suki.test/shop?categories[]=5'});
    assert.equal(h.categories[4].checked,true);assert.equal(h.branches[0].open,true);assert.equal(h.branches[1].open,true);assert.equal(h.branches[2].open,false);
    h.branches[1].open=false;await h.fire(h.branches[1],'toggle');assert.equal(h.requests.length,0);assert.equal(h.categories[4].checked,true);
    h.fields.q.value='cotton';await h.fire(h.form,'submit');assert.equal(h.branches[1].open,false);assert.equal(h.categories[4].checked,true);
    await h.fire(h.chips[0],'click');await h.fire(h.chips[0],'click');
    assert.deepEqual(new URL(h.requests[2].href).searchParams.getAll('categories[]'),['1']);
    assert.equal(h.categories[4].checked,true);assert.equal(h.chips[0].attributes['aria-current'],'true');
});


test('rating radios submit, clear on repeated selection, and restore across history and reset',async()=>{
    const h=harness({url:'https://suki.test/shop?rating=4&min_price=10'});
    assert.equal(h.ratingInputs[2].checked,true);assert.equal(h.fields.rating.value,'4');
    await h.fire(h.ratingInputs[2],'click');assert.equal(h.fields.rating.value,'');assert.equal(h.ratingInputs[0].checked,true);assert.equal(new URL(h.requests[0].href).searchParams.has('rating'),false);
    await h.fire(h.ratingInputs[1],'change');assert.equal(new URL(h.requests[1].href).searchParams.get('rating'),'5');
    h.window.location.href='https://suki.test/shop?rating=3&categories[]=2';await h.fire(h.window,'popstate');
    assert.equal(h.ratingInputs[3].checked,true);assert.equal(h.branches[0].open,true);
    await h.click('https://suki.test/shop',{reset:true});assert.equal(h.ratingInputs[0].checked,true);
    const invalid=harness({url:'https://suki.test/shop?rating=invalid'});assert.equal(invalid.fields.rating.value,'');assert.equal(invalid.ratingInputs[0].checked,true);
});

test('parent selects its whole subtree; child deselection produces an indeterminate parent',async()=>{
    const h=harness();h.categories[0].checked=true;await h.fire(h.categories[0],'change');
    assert.equal(h.categories[1].checked,true);assert.equal(h.categories[4].checked,true);assert.equal(h.categories[5].checked,true);
    h.categories[1].checked=false;await h.fire(h.categories[1],'change');
    assert.equal(h.categories[0].checked,false);assert.equal(h.categories[0].indeterminate,true);assert.equal(h.categories[4].checked,false);assert.equal(h.categories[5].checked,true);
    assert.deepEqual(new URL(h.requests[1].href).searchParams.getAll('categories[]'),['6']);
    h.categories[0].checked=true;await h.fire(h.categories[0],'change');h.categories[0].checked=false;await h.fire(h.categories[0],'change');
    assert.equal(h.categories[1].checked,false);assert.equal(h.categories[5].checked,false);assert.equal(h.categories[0].indeterminate,false);
});

test('category Show more/search and category-only Clear preserve the other filters',async()=>{
    const h=harness({url:'https://suki.test/shop?rating=4&q=cotton'});
    assert.equal(h.roots.filter(root=>!root.hidden).length,8);assert.equal(h.showMore.hidden,false);
    await h.fire(h.showMore,'click');assert.equal(h.roots.filter(root=>!root.hidden).length,11);assert.equal(h.showMore.textContent,'Show less');
    h.categorySearch.value='shirts';await h.fire(h.categorySearch,'input');assert.equal(h.roots.filter(root=>!root.hidden).length,1);assert.equal(h.showMore.hidden,true);
    h.categories[0].checked=true;await h.fire(h.categories[0],'change');await h.fire(h.clearCategories,'click');
    const query=new URL(h.requests[1].href).searchParams;assert.equal(query.get('rating'),'4');assert.equal(query.get('q'),'cotton');assert.equal(query.has('categories[]'),false);
});

test('price range error prevents fetching and skeleton visibility follows the latest request',async()=>{
    const h=harness({deferred:true});h.fields.min_price.value='200';h.fields.max_price.value='100';await h.fire(h.form,'submit');
    assert.equal(h.requests.length,0);assert.match(h.priceError.textContent,/Minimum price/);
    h.fields.max_price.value='300';await h.fire(h.form,'submit');assert.equal(h.priceError.textContent,'');assert.equal(h.loading.hidden,false);
    h.requests[0].resolve(h.response());await h.settle();assert.equal(h.loading.hidden,true);
    const drawer=harness();const button={closest:selector=>selector==='[data-catalog-mobile-filters]'?drawer.mobile:null};await drawer.fire(drawer.results,'click',{target:button});
    assert.equal(drawer.backdrop.hidden,false);assert.equal(drawer.sidebar.attributes['aria-modal'],'true');
    await drawer.fire(drawer.sidebar,'keydown',{key:'Escape'});assert.equal(drawer.backdrop.hidden,true);assert.equal(drawer.sidebar.attributes['aria-modal'],undefined);
});


test('All pill and removable filter links preserve remaining filters without duplicate categories',async()=>{
    const h=harness({url:'https://suki.test/shop?categories[]=2&rating=4&min_price=100'});
    await h.fire(h.chips[2],'click');assert.equal(h.categories[1].checked,false);assert.equal(h.ratingInputs[2].checked,true);
    assert.equal(h.chips[2].attributes['aria-current'],'true');assert.equal(new URL(h.requests[0].href).searchParams.has('categories[]'),false);
    await h.click('https://suki.test/shop?min_price=100',{filter:true});assert.equal(h.fields.rating.value,'');assert.equal(h.fields.min_price.value,'100');
    assert.equal(h.results.focused,true);
});


test('Enter applies sidebar prices and mobile Apply validates the draft before closing',async()=>{
    const h=harness();h.prices[0].value='25';h.prices[1].value='100';await h.fire(h.prices[1],'keydown',{key:'Enter'});
    assert.equal(new URL(h.requests[0].href).searchParams.get('min_price'),'25');
    const button={closest:selector=>selector==='[data-catalog-mobile-filters]'?h.mobile:null};await h.fire(h.results,'click',{target:button});
    h.prices[0].value='200';h.prices[1].value='100';await h.fire(h.drawerApply,'click');assert.equal(h.backdrop.hidden,false);assert.equal(h.requests.length,1);
    h.prices[1].value='300';await h.fire(h.drawerApply,'click');assert.equal(h.backdrop.hidden,true);assert.equal(new URL(h.requests[1].href).searchParams.get('max_price'),'300');
});
