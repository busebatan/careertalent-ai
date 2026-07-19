@extends('admin.layouts.app')

@section('title', __('admin.students.title'))

@section('content')
@php
    $canWrite = $isSuperAdmin || in_array('students.write', $adminPermissions, true);
    $canDelete = $isSuperAdmin || in_array('students.delete', $adminPermissions, true);
    $studentLabels = [
        'detail_error' => __('admin.students.detail_error'),
        'date_unknown' => __('admin.students.date_unknown'),
        'confirm_deactivate_title' => __('admin.students.confirm_deactivate_title'),
        'confirm_deactivate' => __('admin.students.confirm_deactivate'),
        'confirm_activate_title' => __('admin.students.confirm_activate_title'),
        'confirm_activate' => __('admin.students.confirm_activate'),
        'active' => __('admin.students.active'),
        'inactive' => __('admin.students.inactive'),
        'cv_current' => __('admin.students.cv_current'),
        'no_profile' => __('admin.students.no_profile'),
        'empty_section' => __('admin.students.empty_section'),
    ];
    $detailUrlTemplate = route('admin.students.show', ['user' => '__ID__']);
    $studentsBaseUrl = url('/admin/ogrenciler');
@endphp
<div class="mx-auto max-w-7xl"
    x-data="adminStudents({
        students: @js($students),
        canWrite: @js($canWrite),
        canDelete: @js($canDelete),
        studentsBaseUrl: @js($studentsBaseUrl),
        detailUrlTemplate: @js($detailUrlTemplate),
        dateLocale: @js(str_replace('_', '-', app()->getLocale())),
        labels: @js($studentLabels),
    })">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.students.title') }}</h1>
            <p class="mt-1 text-slate-600 dark:text-slate-400">{{ __('admin.students.subtitle') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ __('admin.students.total', ['count' => $total]) }}</span>
    </header>

    @if (session('status'))
        <p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>
    @endif
    @if ($adminError)
        <p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>
    @endif
    @if ($errors->has('students'))
        <p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('students') }}</p>
    @endif

    @if ($canWrite)
        <section class="panel-card mb-8 p-6">
            <h2 class="mb-5 text-lg font-semibold">{{ __('admin.students.create') }}</h2>
            <form method="post" action="{{ route('admin.students.store') }}" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                @csrf
                <label class="text-sm">{{ __('admin.students.full_name') }}<input class="panel-input-block mt-2" name="full_name" value="{{ old('full_name') }}" required></label>
                <label class="text-sm">{{ __('admin.students.email') }}<input class="panel-input-block mt-2" name="email" type="email" value="{{ old('email') }}" required></label>
                <label class="text-sm">{{ __('admin.students.locale') }}<select class="panel-input-block mt-2" name="preferred_locale"><option value="tr">Türkçe</option><option value="en" @selected(old('preferred_locale') === 'en')>English</option></select></label>
                <label class="text-sm">{{ __('admin.students.temporary_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" minlength="8" required></label>
                <label class="text-sm">{{ __('admin.students.temporary_password_confirmation') }}<input class="panel-input-block mt-2" name="temporary_password_confirmation" type="password" minlength="8" required></label>
                <label class="text-sm">{{ __('admin.students.status') }}<select class="panel-input-block mt-2" name="is_active"><option value="1">{{ __('admin.students.active') }}</option><option value="0">{{ __('admin.students.inactive') }}</option></select></label>
                <div class="md:col-span-2 xl:col-span-3"><button class="admin-btn-primary" type="submit">{{ __('admin.students.create') }}</button></div>
            </form>
        </section>
    @endif

    <section class="panel-card overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm xl:col-span-2">
                    <span class="font-medium">{{ __('admin.students.search') }}</span>
                    <input class="panel-input-block mt-2" type="search" x-model="query" placeholder="{{ __('admin.students.search_placeholder') }}">
                </label>
                <label class="text-sm">
                    <span class="font-medium">{{ __('admin.students.filter_status') }}</span>
                    <select class="panel-input-block mt-2" x-model="statusFilter">
                        <option value="all">{{ __('admin.students.filter_status_all') }}</option>
                        <option value="active">{{ __('admin.students.active') }}</option>
                        <option value="inactive">{{ __('admin.students.inactive') }}</option>
                    </select>
                </label>
                <label class="text-sm">
                    <span class="font-medium">{{ __('admin.students.filter_locale') }}</span>
                    <select class="panel-input-block mt-2" x-model="localeFilter">
                        <option value="all">{{ __('admin.students.filter_locale_all') }}</option>
                        <option value="tr">Türkçe</option>
                        <option value="en">English</option>
                    </select>
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.students.table_name') }}</th>
                        <th scope="col">{{ __('admin.students.table_email') }}</th>
                        <th scope="col">{{ __('admin.students.table_locale') }}</th>
                        <th scope="col">{{ __('admin.students.table_status') }}</th>
                        <th scope="col">{{ __('admin.students.table_registered') }}</th>
                        @if ($canWrite || $canDelete)
                            <th scope="col" class="text-right">{{ __('admin.students.table_actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filteredStudents().length === 0">
                        <tr>
                            <td colspan="{{ ($canWrite || $canDelete) ? 6 : 5 }}" class="px-4 py-8 text-center text-sm text-slate-500">
                                {{ __('admin.students.no_results') }}
                            </td>
                        </tr>
                    </template>
                    <template x-for="student in filteredStudents()" :key="student.id">
                        <tr>
                            <td>
                                <button type="button"
                                    class="admin-student-name-link"
                                    @click="openDrawer(student)"
                                    :aria-label="`${student.full_name} — {{ __('admin.students.view_detail') }}`">
                                    <span x-text="student.full_name"></span>
                                </button>
                            </td>
                            <td class="text-slate-600 dark:text-slate-300" x-text="student.email"></td>
                            <td class="uppercase text-slate-500" x-text="student.preferred_locale"></td>
                            <td>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="student.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-red-500/10 text-red-700 dark:text-red-300'"
                                    x-text="student.is_active ? labels.active : labels.inactive"></span>
                            </td>
                            <td class="text-slate-500" x-text="formatDate(student.created_at)"></td>
                            @if ($canWrite || $canDelete)
                                <td class="text-right">
                                    <div class="flex flex-wrap justify-end gap-2">
                                        @if ($canWrite)
                                            <button type="button" class="admin-btn-secondary" @click="openDrawer(student)">{{ __('admin.students.edit') }}</button>
                                        @endif
                                        @if ($canDelete)
                                            <button type="button" class="admin-btn-danger" x-show="student.is_active" @click="openDeactivateConfirm(student)">{{ __('admin.students.delete') }}</button>
                                        @endif
                                        @if ($canWrite)
                                            <button type="button" class="admin-btn-success" x-show="!student.is_active" @click="openActivateConfirm(student)">{{ __('admin.students.activate') }}</button>
                                        @endif
                                    </div>
                                </td>
                            @endif
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="drawerOpen" x-cloak class="admin-drawer-backdrop" @click="closeDrawer()" aria-hidden="true"></div>
    <aside x-show="drawerOpen" x-cloak class="admin-drawer-panel" role="dialog" aria-modal="true" :aria-label="selected ? selected.full_name : '{{ __('admin.students.drawer_title') }}'">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('admin.students.drawer_title') }}</p>
                <h2 class="mt-1 truncate text-xl font-bold text-slate-900 dark:text-white" x-text="selected?.full_name"></h2>
                <p class="panel-muted mt-1 truncate text-sm" x-text="selected?.email"></p>
            </div>
            <button type="button" class="admin-btn-secondary shrink-0" @click="closeDrawer()">{{ __('admin.students.close_drawer') }}</button>
        </header>

        <div class="admin-drawer-body">
            <template x-if="drawerLoading">
                <p class="text-sm text-slate-500">{{ __('admin.students.detail_loading') }}</p>
            </template>
            <template x-if="drawerError">
                <p class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200" x-text="drawerError"></p>
            </template>

            <template x-if="detail && !drawerLoading">
                <div>
                    @if ($canWrite)
                        <section>
                            <h3 class="text-base font-semibold">{{ __('admin.students.section_account') }}</h3>
                            <form method="post" :action="`{{ url('/admin/ogrenciler') }}/${detail.id}`" class="mt-4 grid gap-4 md:grid-cols-2" :key="detail.id">
                                @csrf
                                @method('PATCH')
                                <label class="text-sm">{{ __('admin.students.full_name') }}<input class="panel-input-block mt-2" name="full_name" :value="detail.full_name" required></label>
                                <label class="text-sm">{{ __('admin.students.email') }}<input class="panel-input-block mt-2" name="email" type="email" :value="detail.email" required></label>
                                <label class="text-sm">{{ __('admin.students.locale') }}<select class="panel-input-block mt-2" name="preferred_locale"><option value="tr" :selected="detail.preferred_locale === 'tr'">Türkçe</option><option value="en" :selected="detail.preferred_locale === 'en'">English</option></select></label>
                                <label class="text-sm">{{ __('admin.students.status') }}<select class="panel-input-block mt-2" name="is_active"><option value="1" :selected="detail.is_active">{{ __('admin.students.active') }}</option><option value="0" :selected="!detail.is_active">{{ __('admin.students.inactive') }}</option></select></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.students.reset_password') }}<input class="panel-input-block mt-2" name="temporary_password" type="password" minlength="8"></label>
                                <div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.students.save') }}</button></div>
                            </form>
                        </section>
                    @endif

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_profile') }}</h3>
                        <template x-if="detail.profile">
                            <dl class="admin-detail-list">
                                <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                    <dt class="text-xs uppercase text-slate-500">{{ __('admin.students.profile_phone') }}</dt>
                                    <dd x-text="detail.profile.phone || labels.empty_section"></dd>
                                </div>
                                <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                    <dt class="text-xs uppercase text-slate-500">{{ __('admin.students.profile_location') }}</dt>
                                    <dd x-text="detail.profile.location || labels.empty_section"></dd>
                                </div>
                                <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                    <dt class="text-xs uppercase text-slate-500">{{ __('admin.students.profile_headline') }}</dt>
                                    <dd x-text="detail.profile.headline || labels.empty_section"></dd>
                                </div>
                                <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                    <dt class="text-xs uppercase text-slate-500">{{ __('admin.students.profile_linkedin') }}</dt>
                                    <dd class="truncate" x-text="detail.profile.linkedin || labels.empty_section"></dd>
                                </div>
                            </dl>
                        </template>
                        <template x-if="!detail.profile">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.no_profile"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_cvs') }}</h3>
                        <template x-if="detail.cv_documents.length">
                            <ul class="admin-detail-list">
                                <template x-for="cv in detail.cv_documents" :key="cv.id">
                                    <li class="admin-detail-row">
                                        <p class="font-medium" x-text="cv.display_name"></p>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span x-text="cv.kind"></span>
                                            <span x-show="cv.is_current"> · <span x-text="labels.cv_current"></span></span>
                                            <span x-show="cv.created_at"> · <span x-text="formatDate(cv.created_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.cv_documents.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_analyses') }}</h3>
                        <template x-if="detail.analyses.length">
                            <ul class="admin-detail-list">
                                <template x-for="analysis in detail.analyses" :key="analysis.id">
                                    <li class="admin-detail-row">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium" x-text="analysis.current_role || analysis.file_name || analysis.id"></p>
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs dark:bg-slate-800" x-text="analysis.status"></span>
                                        </div>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span>{{ __('admin.students.analysis_skills') }}: <span x-text="analysis.skill_count"></span></span>
                                            <span x-show="analysis.readiness_score !== null"> · {{ __('admin.students.analysis_readiness') }}: <span x-text="analysis.readiness_score"></span></span>
                                            <span x-show="analysis.created_at"> · <span x-text="formatDate(analysis.created_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.analyses.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_interviews') }}</h3>
                        <template x-if="detail.interviews.length">
                            <ul class="admin-detail-list">
                                <template x-for="interview in detail.interviews" :key="interview.id">
                                    <li class="admin-detail-row">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium" x-text="interview.target_role"></p>
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs uppercase dark:bg-slate-800" x-text="interview.status"></span>
                                        </div>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span x-text="interview.language.toUpperCase()"></span>
                                            · <span x-text="`${interview.question_count} {{ __('admin.students.interview_questions') }}`"></span>
                                            · <span x-text="`${interview.answer_count} {{ __('admin.students.interview_answers') }}`"></span>
                                            <span x-show="interview.average_score !== null"> · {{ __('admin.students.interview_score') }}: <span x-text="interview.average_score"></span></span>
                                            <span x-show="interview.created_at"> · <span x-text="formatDate(interview.created_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.interviews.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_applications') }}</h3>
                        <template x-if="detail.applications.length">
                            <ul class="admin-detail-list">
                                <template x-for="application in detail.applications" :key="application.id">
                                    <li class="admin-detail-row">
                                        <p class="font-medium"><span x-text="application.company"></span> · <span x-text="application.role"></span></p>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span x-text="application.stage"></span>
                                            <span x-show="application.applied_at"> · <span x-text="formatDate(application.applied_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.applications.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.students.section_targets') }}</h3>
                        <template x-if="detail.targets.length">
                            <ul class="admin-detail-list">
                                <template x-for="target in detail.targets" :key="target.id">
                                    <li class="admin-detail-row">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium" x-text="target.title"></p>
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs dark:bg-slate-800" x-text="target.status"></span>
                                        </div>
                                        <p class="panel-muted mt-1 text-xs" x-show="target.created_at" x-text="formatDate(target.created_at)"></p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.targets.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>
                </div>
            </template>
        </div>
    </aside>

    <div x-show="confirmOpen" x-cloak class="admin-confirm-modal" @keydown.escape.window="closeConfirm()">
        <div class="admin-confirm-backdrop" @click="closeConfirm()" aria-hidden="true"></div>
        <div class="admin-confirm-dialog" role="alertdialog" aria-modal="true" aria-labelledby="student-confirm-title" @click.stop>
            <h3 id="student-confirm-title" class="text-lg font-semibold text-slate-900 dark:text-white" x-text="confirmTitle()"></h3>
            <p class="mt-3 text-sm text-slate-600 dark:text-slate-300" x-text="confirmMessage()"></p>
            <p class="mt-2 text-sm font-medium text-slate-900 dark:text-white" x-show="confirmStudent" x-text="confirmStudent?.full_name"></p>
            <div class="mt-6 flex flex-wrap justify-end gap-3">
                <button type="button" class="admin-btn-secondary" @click="closeConfirm()">{{ __('admin.students.confirm_cancel') }}</button>
                <button type="button" class="admin-btn-primary" @click="submitConfirmedAction()">{{ __('admin.students.confirm_proceed') }}</button>
            </div>
        </div>
    </div>
</div>
@endsection
