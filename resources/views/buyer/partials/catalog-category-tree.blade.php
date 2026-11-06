@foreach($nodes as $node)
    @php($children = $categoryGroups->get($node->id, collect()))
    <div class="catalog-category-node relative min-w-0" @if(!$node->parent_id) data-catalog-root @endif data-catalog-node="{{ $node->id }}">
        <label class="catalog-category-label flex min-w-0 items-start gap-3 pr-7 text-sm text-gray-600 cursor-pointer group">
            <input type="checkbox" name="categories[]" form="catalogFilters" value="{{ $node->id }}"
                   data-catalog-category-input data-category-slug="{{ $node->slug }}" data-category-parent="{{ $node->parent_id }}" data-category-name="{{ $node->name }}"
                   @checked(in_array($node->id, $selectedCategories, true))
                   class="w-4 h-4 shrink-0 rounded border-gray-300 text-[#1F6F5B] focus:ring-[#1F6F5B]">
            <span class="min-w-0 break-words group-hover:text-[#1F6F5B] transition">{{ $node->name }}</span>
        </label>
        @if($children->isNotEmpty())
            <details id="catalog-branch-{{ $node->id }}" data-catalog-branch="{{ $node->id }}" @if(in_array($node->id, $expandedCategories)) open @endif>
                <summary aria-controls="catalog-children-{{ $node->id }}" class="catalog-chevron absolute right-0 top-0 cursor-pointer list-none rounded p-1 text-[#1F6F5B] focus-visible:outline focus-visible:outline-2"
                         aria-label="Subcategories of {{ $node->name }}" aria-expanded="{{ in_array($node->id, $expandedCategories) ? 'true' : 'false' }}">
                    <svg aria-hidden="true" width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 6 6 6-6 6"/></svg>
                </summary>
                <div id="catalog-children-{{ $node->id }}" class="catalog-category-children ml-2 border-l border-gray-200 pl-3">
                    @include('buyer.partials.catalog-category-tree', ['nodes' => $children])
                </div>
            </details>
        @endif
    </div>
@endforeach
