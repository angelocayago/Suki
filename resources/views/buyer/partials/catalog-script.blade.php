<script>
(() => {
    const form = document.getElementById('catalogFilters');
    if (!form || form.dataset.initialized || typeof fetch !== 'function') return;
    form.dataset.initialized = 'true';
    const results = document.getElementById('catalogResults');
    const status = document.getElementById('catalogStatus');
    const fields = ['q', 'seller', 'in_stock', 'min_price', 'max_price', 'sort', 'rating'];
    const sidebar = document.getElementById('catalogSidebar');
    const categories = document.querySelectorAll('[data-catalog-category-input]');
    const chips = document.querySelectorAll('[data-catalog-category]');
    const prices = document.querySelectorAll('[data-catalog-price]');
    const branches = document.querySelectorAll('[data-catalog-branch]');
    let selectedCategories = new Set();
    const loading = document.getElementById('catalogLoading');
    const priceError = document.getElementById('catalogPriceError');
    const backdrop = document.getElementById('catalogBackdrop');
    const roots = document.querySelectorAll('[data-catalog-root]');
    const searchCategory = document.querySelectorAll('[data-catalog-category-search]')[0];
    let showAll = false, drawerOpen = false, lastFocus;
    let timer, controller, sequence = 0;
    const byId = id => [...categories].find(input => input.value === id);
    const below = (id, parent) => {
        let input = byId(id); const seen = new Set();
        while (input && !seen.has(input.value)) { if (input.value === parent) return true; seen.add(input.value); input = byId(input.dataset.categoryParent); }
        return false;
    };
    const covered = id => [...selectedCategories].some(parent => below(id, parent));
    const childrenOf = id => [...categories].filter(input => input.dataset.categoryParent === id);
    const fullyChecked = id => covered(id) || (childrenOf(id).length > 0 && childrenOf(id).every(child => fullyChecked(child.value)));
    function categoryVisibility() {
        const term = (searchCategory?.value || '').trim().toLowerCase();
        roots.forEach((root, index) => {
            const active = [...root.querySelectorAll('[data-catalog-category-input]')].some(input => input.checked || input.indeterminate);
            root.hidden = term ? !root.textContent.toLowerCase().includes(term) : !showAll && index >= 8 && !active;
            if (term && !root.hidden) root.querySelectorAll('[data-catalog-branch]').forEach(branch => branch.open = true);
        });
        document.querySelectorAll('[data-catalog-show-more]').forEach(button => { button.hidden = roots.length <= 8 || Boolean(term); button.textContent = showAll ? 'Show less' : 'Show more'; button.setAttribute('aria-expanded', String(showAll)); });
        syncBranches();
    }
    function setDrawer(open) {
        drawerOpen = open;
        document.body?.classList.toggle('catalog-has-drawer', open);
        sidebar?.classList.toggle('catalog-drawer-open', open);
        if (backdrop) backdrop.hidden = !open;
        if (open) { lastFocus = document.activeElement; sidebar?.setAttribute('role', 'dialog'); sidebar?.setAttribute('aria-modal', 'true'); sidebar?.querySelector('[data-catalog-close]')?.focus(); }
        else { sidebar?.removeAttribute?.('role'); sidebar?.removeAttribute?.('aria-modal'); lastFocus?.focus(); }
        syncControls();
    }
    const expandedIds = () => [...branches].filter(branch => branch.open).map(branch => branch.dataset.catalogBranch);
    const findCategory = value => [...categories].find(input => input.value === value || input.dataset.categorySlug === value);
    function syncBranches() {
        branches.forEach(branch => {
            const summary = branch.querySelector('summary');
            summary.setAttribute('aria-expanded', String(branch.open));
            summary.querySelector('svg').style.transform = branch.open ? 'rotate(90deg)' : '';
        });
    }
    function syncControls() {
        categories.forEach(input => {
            input.checked = fullyChecked(input.value);
            input.indeterminate = !input.checked && [...selectedCategories].some(id => below(id, input.value));
        });
        categoryVisibility();
        document.querySelectorAll('[data-catalog-category-count]').forEach(badge => badge.textContent = selectedCategories.size ? '(' + selectedCategories.size + ')' : '');
        document.querySelectorAll('[data-catalog-rating]').forEach(input => input.checked = input.value === form.elements.namedItem('rating')?.value);
        const only = selectedCategories.size === 1 ? findCategory([...selectedCategories][0]) : null;
        const summary = selectedCategories.size > 1 || (selectedCategories.size > 0 && (!only || only.dataset.categoryParent));
        form.elements.namedItem('category').value = summary ? '__selected' : only?.dataset.categorySlug || '';
        document.querySelectorAll('[data-catalog-category-placeholder]').forEach(option => {
            option.hidden = !summary;
            option.textContent = `${selectedCategories.size} ${selectedCategories.size === 1 ? 'category' : 'categories'} selected`;
        });
        prices.forEach(input => input.value = form.elements.namedItem(input.dataset.catalogPrice).value);
        document.querySelectorAll('[data-catalog-sort]').forEach(input => input.value = form.elements.namedItem('sort').value);
        chips.forEach(chip => {
            const selected = chip.dataset.categoryId ? covered(chip.dataset.categoryId) : selectedCategories.size === 0;
            chip.setAttribute('aria-current', selected ? 'true' : 'false');
            chip.className = `shrink-0 px-4 py-2.5 rounded-lg border text-sm font-medium transition ${selected ? 'bg-[#1F6F5B] text-white border-[#1F6F5B]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#1F6F5B] hover:text-[#1F6F5B]'}`;
            const url = new URL(window.location.href);
            url.searchParams.delete('page');
            url.searchParams.delete('category');
            url.searchParams.delete('categories[]');
            [...url.searchParams.keys()].filter(key => /^categories\[\d+\]$/.test(key)).forEach(key => url.searchParams.delete(key));
            if (chip.dataset.categoryId) url.searchParams.set('categories[]', chip.dataset.categoryId);
            chip.href = url.href;
        });
        document.querySelectorAll('[data-catalog-mobile-filters]').forEach(button => button.setAttribute('aria-expanded', String(drawerOpen)));
    }
    function restore(url, expanded = null) {
        fields.forEach(name => {
            const field = form.elements.namedItem(name);
            if (!field) return;
            if (field.type === 'checkbox') field.checked = url.searchParams.get(name) === '1';
            else field.value = name === 'rating' ? (/^[1-5]$/.test(url.searchParams.get(name) || '') ? url.searchParams.get(name) : '') : url.searchParams.get(name) || (name === 'sort' ? 'newest' : '');
        });
        const values = [...url.searchParams].filter(([key]) => /^categories\[(?:\d*)\]$/.test(key)).map(([, value]) => value);
        const legacy = findCategory(url.searchParams.get('category'));
        selectedCategories = new Set(values.length ? values : legacy ? [legacy.value] : []);
        branches.forEach(branch => branch.open = expanded ? expanded.includes(branch.dataset.catalogBranch) : false);
        if (!expanded) categories.forEach(input => {
            if (!selectedCategories.has(input.value)) return;
            let branch = input.closest('[data-catalog-branch]');
            while (branch) {
                branch.open = true;
                branch = branch.parentElement.closest('[data-catalog-branch]');
            }
        });
        syncBranches();
        document.querySelectorAll('[data-catalog-navbar-search]').forEach(input => input.value = url.searchParams.get('q') || '');
        syncControls();
    }
    function cancelPending() {
        clearTimeout(timer);
        controller?.abort();
        sequence++;
        results.setAttribute('aria-busy', 'false');
        if (loading) loading.hidden = true;
    }
    async function load(value, {push = true, focus = false, expanded = expandedIds()} = {}) {
        const url = new URL(value, window.location.href);
        if (url.origin !== window.location.origin) return;
        cancelPending();
        const request = sequence;
        controller = new AbortController();
        restore(url, expanded);
        status.textContent = 'Loading products…';
        status.classList?.add('sr-only');
        results.setAttribute('aria-busy', 'true');
        if (loading) loading.hidden = false;
        try {
            const response = await fetch(url.href, {headers:{Accept:'application/json'}, credentials:'same-origin', cache:'no-store', signal:controller.signal});
            if (response.status === 422) {
                const invalid = await response.json();
                throw new Error(Object.values(invalid.errors || {}).flat().join(' ') || 'Please check your filters.');
            }
            if (!response.ok) throw new Error('Could not load products. Please try Apply filters again.');
            const payload = await response.json();
            if (request !== sequence) return;
            if (typeof payload.html !== 'string' || !Number.isInteger(payload.count)) throw new Error('Could not load products. Please try Apply filters again.');
            results.innerHTML = payload.html;
            if (push && url.href !== window.location.href) history.pushState({catalogExpanded:expandedIds()}, '', url.href);
            syncControls();
            window.lucide?.createIcons();
            status.textContent = `${payload.count} products found.`;
            if (focus) results.focus();
            if (!drawerOpen && window.matchMedia?.('(max-width: 767px)').matches) results.scrollIntoView?.({block:'start', behavior:'smooth'});
        } catch (error) {
            if (request !== sequence || error.name === 'AbortError') return;
            status.textContent = `${error.message || 'Could not load products. Please try again.'} Previous results are still shown.`;
            status.classList?.remove('sr-only');
        } finally {
            if (request === sequence) { results.setAttribute('aria-busy', 'false'); if (loading) loading.hidden = true; }
        }
    }
    form.addEventListener('submit', event => {
        event.preventDefault();
        const minimum = form.elements.namedItem('min_price').value, maximum = form.elements.namedItem('max_price').value;
        if (minimum !== '' && maximum !== '' && Number(minimum) > Number(maximum)) { if (priceError) priceError.textContent = 'Minimum price must not exceed maximum price.'; return; }
        if (priceError) priceError.textContent = '';
        const url = new URL(form.action);
        fields.forEach(name => {
            const field = form.elements.namedItem(name);
            if (field && (field.type !== 'checkbox' || field.checked) && field.value !== '') url.searchParams.set(name, field.value);
        });
        [...selectedCategories].sort((a, b) => Number(a) - Number(b)).forEach(id => url.searchParams.append('categories[]', id));
        load(url.href);
    });
    form.querySelectorAll('select, input[type="checkbox"]').forEach(field => field.addEventListener('change', () => {
        if (field.name === 'category') {
            const category = findCategory(field.value);
            selectedCategories = new Set(category ? [category.value] : []);
        }
        form.requestSubmit();
    }));
    form.elements.namedItem('q').addEventListener('input', () => {
        cancelPending();
        status.textContent = '';
        timer = setTimeout(() => form.requestSubmit(), 350);
    });
    const follow = event => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const link = event.target.closest('a[href]');
        if (!link || (!link.hasAttribute('data-catalog-reset') && !link.hasAttribute('data-catalog-filter-link') && !link.closest('[data-catalog-pagination]'))) return;
        event.preventDefault();
        load(link.href, {focus:true, expanded:link.hasAttribute('data-catalog-reset') ? [] : expandedIds()});
    };
    form.addEventListener('click', follow);
    results.addEventListener('click', follow);
    sidebar?.addEventListener('click', follow);
    categories.forEach(input => input.addEventListener('change', () => {
        if (input.checked) {
            selectedCategories = new Set([...selectedCategories].filter(id => !below(id, input.value)));
            selectedCategories.add(input.value);
        } else {
            const effective = [...categories].filter(item => covered(item.value)).map(item => item.value);
            const remaining = effective.filter(id => !below(id, input.value) && !below(input.value, id));
            selectedCategories = new Set(remaining.filter(id => !remaining.some(other => other !== id && below(id, other))));
        }
        form.requestSubmit();
    }));
    chips.forEach(chip => chip.addEventListener('click', event => {
        if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        event.preventDefault();
        selectedCategories = new Set(chip.dataset.categoryId ? [chip.dataset.categoryId] : []);
        form.requestSubmit();
    }));
    function applyPrices() {
        if (![...prices].every(input => input.reportValidity())) return false;
        const min = [...prices].find(input => input.dataset.catalogPrice === 'min_price')?.value;
        const max = [...prices].find(input => input.dataset.catalogPrice === 'max_price')?.value;
        if (min !== '' && max !== '' && Number(min) > Number(max)) {
            if (priceError) priceError.textContent = 'Minimum price must not exceed maximum price.';
            return false;
        }
        prices.forEach(input => form.elements.namedItem(input.dataset.catalogPrice).value = input.value);
        form.requestSubmit();
        return true;
    }
    document.querySelectorAll('[data-catalog-apply-price]').forEach(button => button.addEventListener('click', applyPrices));
    document.querySelectorAll('[data-catalog-drawer-apply]').forEach(button => button.addEventListener('click', () => { if (applyPrices()) setDrawer(false); }));
    results.addEventListener('change', event => {
        if (!event.target.matches('[data-catalog-sort]')) return;
        form.elements.namedItem('sort').value = event.target.value;
        form.requestSubmit();
    });
    results.addEventListener('click', event => {
        if (!event.target.closest('[data-catalog-mobile-filters]') || !sidebar) return;
        setDrawer(!drawerOpen);
    });
    branches.forEach(branch => branch.addEventListener('toggle', () => {
        syncBranches();
        if (results.getAttribute('aria-busy') !== 'true') history.replaceState({...history.state, catalogExpanded:expandedIds()}, '', window.location.href);
    }));
    document.querySelectorAll('[data-catalog-rating]').forEach(input => {
        input.addEventListener('click', () => { const field = form.elements.namedItem('rating'); if (field.value === input.value && input.value) { field.value = ''; form.requestSubmit(); } });
        input.addEventListener('change', () => { form.elements.namedItem('rating').value = input.value; form.requestSubmit(); });
    });
    document.querySelectorAll('[data-catalog-clear-categories]').forEach(button => button.addEventListener('click', () => { selectedCategories.clear(); form.requestSubmit(); }));
    document.querySelectorAll('[data-catalog-show-more]').forEach(button => button.addEventListener('click', () => { showAll = !showAll; categoryVisibility(); }));
    searchCategory?.addEventListener('input', categoryVisibility);
    prices.forEach(input => input.addEventListener('keydown', event => { if (event.key === 'Enter') { event.preventDefault(); document.querySelectorAll('[data-catalog-apply-price]')[0]?.click(); } }));
    document.querySelectorAll('[data-catalog-close]').forEach(button => button.addEventListener('click', () => setDrawer(false)));
    backdrop?.addEventListener('click', () => setDrawer(false));
    window.addEventListener('resize', () => {
        if (drawerOpen && window.matchMedia?.('(min-width:1024px)').matches) setDrawer(false);
    });
    sidebar?.addEventListener('keydown', event => {
        if (!drawerOpen) return;
        if (event.key === 'Escape') { event.preventDefault(); setDrawer(false); }
        if (event.key === 'Tab') {
            const focusable = [...sidebar.querySelectorAll('button,a,input,select,summary')].filter(node => !node.disabled && node.getClientRects().length);
            if (!focusable.length) return;
            const first = focusable[0], last = focusable[focusable.length - 1];
            if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last.focus(); }
            else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first.focus(); }
        }
    });
    const pills = document.querySelectorAll('[data-catalog-pills]')[0];
    if (pills) {
        let drag;
        pills.addEventListener('dragstart', event => event.preventDefault());
        pills.addEventListener('pointerdown', event => { if (event.pointerType === 'mouse' && event.button === 0) drag = {x:event.clientX, left:pills.scrollLeft, moved:false}; });
        pills.addEventListener('pointermove', event => { if (!drag || !event.buttons) return; if (Math.abs(event.clientX - drag.x) > 5) drag.moved = true; if (drag.moved) { event.preventDefault(); pills.scrollLeft = drag.left - (event.clientX - drag.x); pills.classList.add('is-dragging'); } });
        pills.addEventListener('click', event => { if (drag?.moved) { event.preventDefault(); event.stopImmediatePropagation(); } }, true);
        window.addEventListener('pointerup', () => { pills.classList.remove('is-dragging'); setTimeout(() => drag = null, 0); });
    }
    window.addEventListener('popstate', event => load(window.location.href, {push:false, expanded:event.state?.catalogExpanded ?? null}));
    restore(new URL(window.location.href), history.state?.catalogExpanded ?? null);
})();
</script>
