@php
    /** @var string $action */
    /** @var array $filters */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $types */
    /** @var \Illuminate\Support\Collection|\Illuminate\Database\Eloquent\Collection|array $categories */
    $showIndustry = $showIndustry ?? false;
    $showFramework = $showFramework ?? false;
    $showParentCategory = $showParentCategory ?? false;
    $parents = $parents ?? collect();
@endphp
<form method="GET" action="{{ $action }}" class="flex flex-wrap gap-2 w-full lg:w-auto items-end">
    <div class="flex-1 min-w-[10rem]">
        <label class="sr-only" for="catalog-q">Search</label>
        <input id="catalog-q" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Search…" class="w-full rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
    </div>

    @if(!empty($types))
        <div>
            <label class="sr-only" for="catalog-type">Type</label>
            <select id="catalog-type" name="type" class="rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
                <option value="">All types</option>
                @foreach($types as $type)
                    @php
                        $value = is_object($type) && property_exists($type, 'value') ? $type->value : (is_array($type) ? ($type['value'] ?? '') : (string) $type);
                        $label = is_object($type) && method_exists($type, 'label') ? $type->label() : (is_array($type) ? ($type['label'] ?? $value) : $value);
                    @endphp
                    <option value="{{ $value }}" @selected(($filters['type'] ?? '') === $value)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($showParentCategory && $parents->isNotEmpty())
        <div x-data="listingCategoryForm(@js($parents->map(fn ($p) => ['id' => $p->id, 'name' => $p->name, 'children' => $p->children->map(fn ($c) => ['id' => $c->id, 'name' => $c->name])->values()])->values()), {{ (int) ($filters['parent'] ?? 0) }}, {{ (int) ($filters['category'] ?? 0) }})">
            <label class="sr-only" for="catalog-parent">Parent category</label>
            <select id="catalog-parent" name="parent" x-model.number="parentId" class="rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2 mb-2 sm:mb-0">
                <option value="0">All groups</option>
                <template x-for="parent in parents" :key="parent.id">
                    <option :value="parent.id" x-text="parent.name"></option>
                </template>
            </select>
            <label class="sr-only" for="catalog-category">Category</label>
            <select id="catalog-category" name="category" x-model.number="categoryId" class="rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
                <option value="0">All categories</option>
                <template x-for="child in children" :key="child.id">
                    <option :value="child.id" x-text="child.name"></option>
                </template>
            </select>
        </div>
    @elseif(!empty($categories) && (is_countable($categories) ? count($categories) : $categories->isNotEmpty()))
        <div>
            <label class="sr-only" for="catalog-category">Category</label>
            <select id="catalog-category" name="category" class="rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected((int) ($filters['category'] ?? 0) === (int) $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
    @endif

    @if($showIndustry)
        <div>
            <label class="sr-only" for="catalog-industry">Industry</label>
            <input id="catalog-industry" type="text" name="industry" value="{{ $filters['industry'] ?? '' }}" placeholder="Industry" class="w-full sm:w-32 rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
        </div>
    @endif

    @if($showFramework)
        <div>
            <label class="sr-only" for="catalog-framework">Framework</label>
            <input id="catalog-framework" type="text" name="framework" value="{{ $filters['framework'] ?? '' }}" placeholder="Framework" class="w-full sm:w-32 rounded-xl bg-slate-900/60 border-white/10 text-sm px-3 py-2">
        </div>
    @endif

    <button type="submit" class="px-4 py-2 rounded-xl bg-primary text-white text-sm font-bold">Filter</button>
</form>
