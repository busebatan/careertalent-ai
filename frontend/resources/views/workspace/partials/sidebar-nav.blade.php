@foreach ($workspaceNav as $group)
    @if (($group['label'] ?? '') !== '')
        <p class="mb-1 mt-6 px-3 text-xs font-semibold uppercase tracking-wide text-slate-500 first:mt-0 dark:text-slate-400">
            {{ $group['label'] }}
        </p>
    @endif
    @foreach ($group['items'] as $item)
        <a href="{{ route($item['route']) }}" @click="sidebarOpen = false"
            class="panel-nav-link {{ request()->routeIs($item['route'].'*') ? 'panel-nav-link-active' : '' }}">
            @include('app.partials.sidebar-nav-icon', ['icon' => $item['icon']])
            <span class="truncate">{{ $item['label'] }}</span>
        </a>
    @endforeach
@endforeach
