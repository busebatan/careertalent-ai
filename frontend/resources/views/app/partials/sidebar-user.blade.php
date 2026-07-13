@php
    $displayName = $panelUser['name'] ?? __('panel.profile.name');
    $nameParts = preg_split('/\s+/u', trim($displayName)) ?: [];
    $initials = mb_strtoupper(
        mb_substr($nameParts[0] ?? '', 0, 1).mb_substr($nameParts[count($nameParts) - 1] ?? '', 0, 1)
    );
    $avatarUrl = $panelUser['avatar_url'] ?? null;
@endphp

<a href="{{ route('panel.account') }}"
    class="mb-4 flex items-center gap-3 rounded-lg px-1 py-1 transition hover:bg-slate-100 dark:hover:bg-slate-800/50"
    aria-label="{{ __('panel.nav.account') }}: {{ $displayName }}">
    @if ($avatarUrl)
        <img src="{{ $avatarUrl }}" alt="" width="40" height="40"
            class="h-10 w-10 shrink-0 rounded-full object-cover ring-2 ring-emerald-500/25 dark:ring-emerald-400/30">
    @else
        <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-emerald-600 text-sm font-semibold text-white ring-2 ring-emerald-500/25 dark:bg-emerald-500 dark:ring-emerald-400/30"
            aria-hidden="true">{{ $initials }}</span>
    @endif
    <span class="min-w-0 truncate text-sm font-medium text-slate-900 dark:text-slate-100">{{ $displayName }}</span>
</a>
