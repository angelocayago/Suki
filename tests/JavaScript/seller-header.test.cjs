const { test } = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const source = fs.readFileSync(path.join(__dirname, '../../resources/views/seller/header/script.blade.php'), 'utf8').match(/<script>([\s\S]*?)<\/script>/)[1];

function harness({count=2,empty=false,failure=false,deferred=false}={}) {
    const document = {activeElement:null,handlers:{}};
    function element(tag='div') {
        return {
            tag, hidden:false, attributes:{}, handlers:{}, dataset:{}, children:[], textContent:'',
            addEventListener(type,callback){ this.handlers[type]=callback; },
            setAttribute(key,value){ this.attributes[key]=value; },
            removeAttribute(key){ delete this.attributes[key]; },
            focus(){ document.activeElement=this; },
            appendChild(child){ child.parentElement=this; this.children.push(child); },
            append(...children){ children.forEach(child=>this.appendChild(child)); },
            replaceChildren(){ this.children.forEach(child=>child.parentElement=null); this.children=[]; },
            contains(node){ while(node){ if(node===this)return true; node=node.parentElement; } return false; },
            querySelector(selector){
                if(selector==='[data-seller-dropdown-toggle]')return this.toggle;
                if(selector==='.seller-header-panel')return this.panel;
                if(selector==='[data-seller-avatar-fallback]')return this.fallback;
                const walk=node=>{ for(const child of node.children){ if(child.tag==='button'||child.tag==='a')return child; const found=walk(child);if(found)return found; } };
                return walk(this);
            },
        };
    }
    const root = element(), profile = element(), toggle = element('button'), profileToggle = element('button');
    const panel=element('section'), profilePanel=element('section'); panel.id='sellerNotificationPanel'; panel.hidden=true; profilePanel.hidden=true;
    panel.dataset={previewUrl:'/seller/notifications/preview',csrf:'fixture-csrf'};
    root.toggle=toggle; root.panel=panel; root.append(toggle,panel);
    profile.toggle=profileToggle; profile.panel=profilePanel; profile.append(profileToggle,profilePanel);
    const preview=element(), oldEntry=element('button'); oldEntry.textContent='Server rendered entry'; preview.append(oldEntry); panel.append(preview);
    const profileLink=element('a'); profilePanel.append(profileLink);
    const status=element(), photo=element('img'), fallback=element('span'), avatar=element();
    photo.complete=false; photo.naturalWidth=38; fallback.hidden=true; avatar.fallback=fallback; avatar.append(photo,fallback);
    const badges=[element('span'),element('span')], totals=[element('span')];
    const ids={sellerNotificationPanel:panel,sellerNotificationPreview:preview,sellerNotificationLoadStatus:status,sellerNotificationBell:toggle};
    document.addEventListener=(type,callback)=>type==='DOMContentLoaded'?callback():document.handlers[type]=callback;
    document.getElementById=id=>ids[id]; document.createElement=element;
    document.querySelectorAll=selector=>({
        '[data-seller-dropdown]':[root,profile], '[data-seller-avatar-photo]':[photo],
        '[data-seller-unread-badge]':badges, '[data-seller-unread-total]':totals,
    }[selector]);
    const calls=[];
    const response={ok:!failure,json:async()=>({unread_count:count,notifications:empty?[]:[{
        title:'<script>Unsafe title</script>',message:'Stored notification message',unread:true,date:'Nov 01, 2026',iso_date:'2026-11-01T12:00:00+08:00',open_url:'/seller/notifications/owned/open',
    }]})};
    let release;
    const pending=new Promise(resolve=>release=()=>resolve(response));
    vm.runInNewContext(source,{
        document,URL,
        window:{location:{href:'https://shop.test/seller/notifications',origin:'https://shop.test'}},
        async fetch(url,options){ calls.push({url,options});return deferred?pending:response; },
    });
    return {document,root,profile,toggle,profileToggle,panel,profilePanel,preview,oldEntry,profileLink,status,photo,fallback,badges,totals,calls,release,settle:()=>new Promise(resolve=>setImmediate(resolve))};
}

test('bell and profile open one at a time; outside click and Escape close with focus restoration', async()=>{
    const h=harness();
    h.toggle.handlers.click(); assert.equal(h.panel.hidden,false); assert.equal(h.toggle.attributes['aria-expanded'],'true');
    h.profileToggle.handlers.click(); assert.equal(h.panel.hidden,true); assert.equal(h.profilePanel.hidden,false);
    h.profile.handlers.keydown({key:'Escape',preventDefault(){}}); assert.equal(h.profilePanel.hidden,true); assert.equal(h.document.activeElement,h.profileToggle);
    h.profileToggle.handlers.click(); h.document.handlers.click({target:{}}); assert.equal(h.profilePanel.hidden,true);
    await h.settle();
});

test('keyboard navigation keeps focused server entries and closes when focus leaves',async()=>{
    const h=harness({deferred:true});
    h.toggle.focus(); h.root.handlers.keydown({key:'ArrowDown',preventDefault(){}});
    assert.equal(h.panel.hidden,false); assert.equal(h.document.activeElement,h.oldEntry);
    h.release(); await h.settle(); assert.equal(h.preview.children[0],h.oldEntry);
    h.root.handlers.focusout({relatedTarget:h.profileToggle}); assert.equal(h.panel.hidden,true);
    h.profileToggle.focus(); h.profile.handlers.keydown({key:'ArrowDown',preventDefault(){}}); assert.equal(h.document.activeElement,h.profileLink);
});

test('preview refresh uses GET, never marks on open, safely renders text and updates actual badges',async()=>{
    const h=harness({count:105,deferred:true});
    h.toggle.handlers.click(); h.toggle.handlers.click(); h.toggle.handlers.click(); assert.equal(h.calls.length,1);
    assert.equal(h.calls[0].url,'/seller/notifications/preview'); assert.equal(h.calls[0].options.method,undefined);
    h.release(); await h.settle();
    for(const badge of h.badges){ assert.equal(badge.hidden,false);assert.equal(badge.textContent,'99+'); }
    assert.equal(h.totals[0].textContent,'105'); assert.equal(h.toggle.attributes['aria-label'],'Notifications, 105 unread');
    const form=h.preview.children[0]; assert.equal(form.method,'POST'); assert.equal(form.action,'https://shop.test/seller/notifications/owned/open');
    assert.equal(form.children[0].name,'_token'); assert.equal(form.children[0].value,'fixture-csrf');
    assert.equal(form.children[1].children[0].textContent,'<script>Unsafe title</script>');
});

test('zero count hides badges and empty preview shows a real empty state',async()=>{
    const h=harness({count:0,empty:true});h.toggle.handlers.click();await h.settle();
    assert.equal(h.badges[0].hidden,true); assert.equal(h.badges[0].textContent,'');
    assert.equal(h.preview.children[0].textContent,'No notifications yet.'); assert.equal(h.toggle.attributes['aria-label'],'Notifications');
});

test('failed refresh retains server-rendered data and gives a retry path',async()=>{
    const h=harness({failure:true});h.toggle.handlers.click();await h.settle();
    assert.equal(h.preview.children[0],h.oldEntry); assert.match(h.status.textContent,/View All Notifications/); assert.equal(h.panel.attributes['aria-busy'],undefined);
});

test('broken owned profile photo uses the account initial fallback',()=>{
    const h=harness(); assert.equal(h.fallback.hidden,true);h.photo.handlers.error();assert.equal(h.photo.hidden,true);assert.equal(h.fallback.hidden,false);
});
