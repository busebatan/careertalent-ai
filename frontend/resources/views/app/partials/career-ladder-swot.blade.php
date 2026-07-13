<div class="panel-swot-cell">
    <p class="mb-1 text-xs font-medium text-emerald-400">Güçlü (S)</p>
    <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
        @foreach ($role['swot']['strengths'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
<div class="panel-swot-cell">
    <p class="mb-1 text-xs font-medium text-rose-400">Zayıf (W)</p>
    <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
        @foreach ($role['swot']['weaknesses'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
<div class="panel-swot-cell">
    <p class="mb-1 text-xs font-medium text-sky-400">Fırsat (O)</p>
    <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
        @foreach ($role['swot']['opportunities'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
<div class="panel-swot-cell">
    <p class="mb-1 text-xs font-medium text-amber-400">Tehdit (T)</p>
    <ul class="list-inside list-disc text-xs text-slate-600 dark:text-slate-400">
        @foreach ($role['swot']['threats'] as $item)
            <li>{{ $item }}</li>
        @endforeach
    </ul>
</div>
