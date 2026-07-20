@if (session('status'))
    <div
        data-flash-toast
        data-flash-toast-duration="4000"
        role="status"
        aria-live="polite"
        @class([
            'mb-5 rounded-xl border p-4 text-sm',
            'company-feedback-success' => ($variant ?? 'panel') === 'company',
            'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-200' => ($variant ?? 'panel') === 'panel',
        ])
    >{{ session('status') }}</div>
@endif
