@props([
    'group',
    'active' => false,
    'expanded' => false,
    'idPrefix' => 'dashboard-nav',
])

@php
    $groupId = (string) ($group['id'] ?? \Illuminate\Support\Str::slug($group['label'] ?? 'group'));
    $panelId = $idPrefix.'-'.$groupId;
    // Use Js::from - @js() inside Blade component attributes is escaped and left literal.
    $groupIdJs = \Illuminate\Support\Js::from($groupId);
@endphp

<div data-nav-group="{{ $groupId }}">
    <button
        type="button"
        class="flex min-h-12 w-full items-center gap-3 rounded-xl px-3 py-2.5 text-left text-sm font-semibold transition-colors focus-ring {{ $active ? 'text-primary' : 'text-text-primary hover:bg-muted/50' }}"
        @click="toggleGroup({{ $groupIdJs }})"
        @keydown.arrow-right.prevent="openGroup({{ $groupIdJs }})"
        @keydown.arrow-left.prevent="closeGroup({{ $groupIdJs }})"
        :aria-expanded="isOpen({{ $groupIdJs }}).toString()"
        aria-controls="{{ $panelId }}"
    >
        <x-dashboard.icon :name="$group['icon'] ?? 'grid'" class="h-[22px] w-[22px] {{ $active ? 'text-primary' : 'text-text-secondary' }}" />
        <span class="min-w-0 flex-1 truncate">{{ $group['label'] ?? '' }}</span>
        <span
            class="inline-flex h-4 w-4 shrink-0 items-center justify-center text-text-muted transition-transform duration-200 motion-reduce:transition-none"
            :class="isOpen({{ $groupIdJs }}) ? 'rotate-180' : ''"
            aria-hidden="true"
        >
            <x-dashboard.icon name="chevron-down" class="h-4 w-4" />
        </span>
    </button>

    <div
        id="{{ $panelId }}"
        class="dashboard-nav-panel {{ $expanded ? 'is-expanded' : '' }}"
        :class="{ 'is-expanded': isOpen({{ $groupIdJs }}) }"
    >
        <div class="min-h-0 overflow-hidden">
            <ul class="space-y-0.5 pb-2 pt-1" role="list">
                @foreach (($group['children'] ?? []) as $child)
                    @php($childActive = \App\Support\DashboardNavigation::isActive($child))
                    <li>
                        <x-dashboard.nav-item :item="$child" :active="$childActive" child />
                    </li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
