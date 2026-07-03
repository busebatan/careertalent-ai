@extends('app.layouts.app')

@section('title', __('panel.profile.title'))

@section('content')
<div class="mx-auto max-w-3xl" x-data="{ tab: window.location.hash === '#cv-yukle' ? 'cv' : 'profil' }">
    <header class="mb-8">
        <h1 class="mb-1 text-2xl font-bold">{{ __('panel.profile.title') }}</h1>
        <p class="text-slate-600 dark:text-slate-400">{{ __('panel.profile.subtitle') }}</p>
    </header>

    <div class="mb-6 flex flex-wrap gap-2 border-b border-slate-200 pb-4 dark:border-slate-800">
        <button type="button" @click="tab = 'profil'"
            :class="tab === 'profil' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_profile') }}
        </button>
        <button type="button" @click="tab = 'giris'"
            :class="tab === 'giris' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_login') }}
        </button>
        <button type="button" @click="tab = 'cv'"
            :class="tab === 'cv' ? 'bg-slate-100 font-medium text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-slate-200'"
            class="rounded-lg px-4 py-2 text-sm">
            {{ __('panel.profile.tab_cv') }}
        </button>
    </div>

    <section x-show="tab === 'profil'" x-cloak class="space-y-6">
        <form class="panel-card space-y-4 p-6" onsubmit="return false;">
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.name') }}</label>
                    <input type="text" value="{{ $profile['name'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.phone') }}</label>
                    <input type="tel" value="{{ $profile['phone'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.location') }}</label>
                    <input type="text" value="{{ $profile['location'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.headline') }}</label>
                    <input type="text" value="{{ $profile['headline'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.linkedin') }}</label>
                    <input type="url" value="{{ $profile['linkedin'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.github') }}</label>
                    <input type="url" value="{{ $profile['github'] }}"
                        class="panel-input-block focus:border-emerald-500 focus:outline-none">
                </div>
            </div>
            <div class="flex flex-wrap items-center gap-3 pt-2">
                <button type="button" disabled class="panel-btn-disabled">
                    {{ __('panel.profile.save_soon') }}
                </button>
                <button type="button" @click="alert(@js(__('panel.profile.feature_coming_soon')))"
                    class="rounded-xl border border-violet-500/50 px-4 py-2 text-sm text-violet-600 hover:bg-violet-50 dark:text-violet-300 dark:hover:bg-violet-500/10">
                    {{ __('panel.profile.ai_edit') }}
                </button>
            </div>
        </form>
    </section>

    <section x-show="tab === 'giris'" x-cloak class="space-y-6">
        <form class="panel-card space-y-4 p-6" onsubmit="return false;">
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.email') }}</label>
                <input type="email" value="{{ $profile['email'] }}"
                    class="panel-input-block">
                <p class="mt-1 text-xs text-slate-500">{{ __('panel.profile.email_hint') }}</p>
            </div>
            <button type="button" disabled class="panel-btn-disabled">
                {{ __('panel.profile.update_email_soon') }}
            </button>
        </form>

        <form class="panel-card space-y-4 p-6" onsubmit="return false;">
            <h2 class="font-semibold">{{ __('panel.profile.change_password') }}</h2>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.current_password') }}</label>
                <input type="password" autocomplete="current-password"
                    class="panel-input-block">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.new_password') }}</label>
                <input type="password" autocomplete="new-password"
                    class="panel-input-block">
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-500">{{ __('panel.profile.confirm_password') }}</label>
                <input type="password" autocomplete="new-password"
                    class="panel-input-block">
            </div>
            <button type="button" disabled class="panel-btn-disabled">
                {{ __('panel.profile.update_password_soon') }}
            </button>
        </form>
    </section>

    <section id="cv-yukle" x-show="tab === 'cv'" x-cloak x-data="profileCvUpload(@js(app()->getLocale()))">
        <div class="panel-card p-6">
            <h2 class="mb-2 font-semibold">{{ __('panel.profile.cv_file_title') }}</h2>
            <p class="mb-6 text-sm text-slate-600 dark:text-slate-400">
                {!! __('panel.profile.cv_file_desc', [
                    'link' => '<a href="'.route('panel.cv-builder').'" class="text-emerald-600 hover:underline dark:text-emerald-400">'.e(__('panel.profile.cv_file_link')).'</a>',
                ]) !!}
            </p>

            <template x-if="fileName">
                <div class="mb-4 flex items-center justify-between rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3">
                    <span class="text-sm text-emerald-700 dark:text-emerald-300" x-text="fileName"></span>
                    <button type="button" @click="removeCv()" class="text-xs text-slate-500 hover:text-red-500">{{ __('panel.profile.remove') }}</button>
                </div>
            </template>

            <label class="panel-upload-zone">
                <span class="mb-2 text-3xl">📄</span>
                <span class="mb-1 text-sm font-medium text-slate-800 dark:text-slate-200">{{ __('panel.profile.upload_drag') }}</span>
                <span class="text-xs text-slate-500">{{ __('panel.profile.upload_hint') }}</span>
                <input type="file" accept="application/pdf,.pdf" class="hidden"
                    @change="onFileSelect($event)">
            </label>

            <p class="mt-4 text-xs text-slate-500">
                @if ($profile['uploaded_cv']['uploaded_at'])
                    {{ __('panel.profile.last_upload', ['date' => $profile['uploaded_cv']['uploaded_at']]) }}
                @else
                    {{ __('panel.profile.no_cv') }}
                @endif
            </p>
        </div>
    </section>
</div>
@endsection
