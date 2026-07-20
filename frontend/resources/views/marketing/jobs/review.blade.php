@extends('marketing.layouts.marketing')
@section('title', __('company_positions.public.start'))
@section('content')
@php $position = $job['position']; $organization = $job['organization']; $positionPath = basename((string) ($position['public_path'] ?? request()->route('positionPath'))); @endphp
<section class="min-h-[calc(100vh-80px)] bg-[#080b18] px-5 py-16 text-white sm:px-8">
    <div class="mx-auto max-w-3xl">
        <a class="inline-flex items-center gap-2 text-sm font-semibold text-emerald-300" href="{{ route('public.jobs.show', ['organizationSlug' => $organization['slug'] ?? request()->route('organizationSlug'), 'positionPath' => $positionPath, 'source' => $source]) }}"><i data-lucide="arrow-left" class="h-4 w-4" aria-hidden="true"></i>{{ $position['title'] }}</a>
        <div class="mt-6 rounded-[28px] border border-white/10 bg-white/[0.045] p-7 sm:p-10">
            <p class="text-sm font-semibold text-emerald-300">{{ $candidate['full_name'] ?? '' }}</p>
            <h1 class="mt-2 text-3xl font-bold">{{ __('company_positions.public.review_title', ['company' => $organization['name'] ?? 'Kurum']) }}</h1>
            <div class="mt-6 grid gap-4 sm:grid-cols-2"><div class="rounded-2xl bg-white/5 p-5"><h2 class="font-bold">Paylaşılacaklar</h2><ul class="mt-3 space-y-2 text-sm text-slate-300"><li>✓ Seçtiğiniz CV sürümü</li><li>✓ Pozisyonla ilgili proje ve yetenekler</li><li>✓ İletişim bilgileriniz</li><li>✓ Bu ilana ait değerlendirme sonucu</li></ul></div><div class="rounded-2xl bg-white/5 p-5"><h2 class="font-bold">Paylaşılmayacaklar</h2><ul class="mt-3 space-y-2 text-sm text-slate-300"><li>– Diğer kurumlara yaptığınız başvurular</li><li>– Diğer kurumların notları</li><li>– Paylaşmadığınız değerlendirmeler</li><li>– Kişisel gelişim planınızdaki özel notlar</li></ul></div></div>
            @if($documentsError)<div class="mt-6 rounded-xl bg-red-500/10 p-4 text-sm text-red-200">{{ $documentsError }}</div>@endif
            @if($documents === [])
                <div class="mt-6 rounded-2xl border border-dashed border-white/20 p-6 text-center"><p class="text-slate-300">{{ __('company_positions.public.no_cv') }}</p><a class="mt-4 inline-flex rounded-xl bg-emerald-500 px-5 py-3 font-bold text-slate-950" href="{{ route('panel.cv-builder') }}">CV yükle veya oluştur</a></div>
            @else
                <form class="mt-7 space-y-6" method="post" action="{{ route('public.jobs.submit', ['organizationSlug' => $organization['slug'] ?? request()->route('organizationSlug'), 'positionPath' => $positionPath]) }}">@csrf<input type="hidden" name="source" value="{{ $source }}"><fieldset><legend class="font-bold">{{ __('company_positions.public.choose_cv') }}</legend><div class="mt-3 space-y-3">@foreach($documents as $document)<label class="flex cursor-pointer items-center gap-3 rounded-xl border border-white/10 p-4 transition hover:border-emerald-400/30"><input type="radio" name="cv_document_id" value="{{ $document['id'] }}" @checked($document['is_current'] ?? false) required><span><strong class="block">{{ $document['display_name'] }}</strong><span class="text-xs text-slate-400">{{ $document['kind'] ?? 'CV' }}</span></span></label>@endforeach</div></fieldset><label class="flex items-start gap-3 rounded-xl bg-emerald-400/10 p-4 text-sm text-emerald-100"><input class="mt-1" type="checkbox" name="consent" value="1" required><span>{{ __('company_positions.public.consent') }}</span></label><button class="w-full rounded-xl bg-emerald-500 px-6 py-3.5 font-bold text-slate-950 transition hover:bg-emerald-400" type="submit">{{ __('company_positions.public.submit') }}</button></form>
            @endif
        </div>
    </div>
</section>
@endsection
