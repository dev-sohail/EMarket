@php
    // One source of truth for the tab strip: adding a section here adds it to
    // the nav. Each entry keeps its own icon and active-state check.
    $analyticsSections = [
        ['route' => 'admin.analytics.overview', 'label' => 'Overview', 'icon' => 'fa-chart-line'],
        ['route' => 'admin.analytics.products', 'label' => 'Product Performance', 'icon' => 'fa-box'],
        ['route' => 'admin.analytics.visitors', 'label' => 'Visitor Traffic', 'icon' => 'fa-users'],
        ['route' => 'admin.analytics.customers', 'label' => 'Customers', 'icon' => 'fa-user-group'],
        ['route' => 'admin.analytics.marketing', 'label' => 'Marketing', 'icon' => 'fa-bullhorn'],
        ['route' => 'admin.analytics.inventory', 'label' => 'Inventory', 'icon' => 'fa-warehouse'],
        ['route' => 'admin.analytics.realtime', 'label' => 'Real-time', 'icon' => 'fa-bolt'],
        ['route' => 'admin.analytics.custom-report', 'label' => 'Custom Report', 'icon' => 'fa-file-export'],
    ];
@endphp

<nav aria-label="Analytics sections" class="bg-[var(--color-paper)] rounded-lg border hairline p-2 mb-6">
    <div class="flex flex-wrap gap-1">
        @foreach($analyticsSections as $section)
            @php $isActive = request()->routeIs($section['route']); @endphp
            <a href="{{ route($section['route']) }}"
               @if($isActive) aria-current="page" @endif
               class="inline-flex items-center gap-2 px-3 py-2 rounded-lg text-sm font-medium transition-colors {{ $isActive ? 'bg-[var(--color-red)] text-white' : 'text-[var(--color-ink-soft)] hover:bg-[var(--color-paper-dim)] hover:text-[var(--color-ink)]' }}">
                <i class="fa-solid {{ $section['icon'] }} w-4 h-4 flex items-center justify-center"></i>
                {{ $section['label'] }}
            </a>
        @endforeach
    </div>
</nav>