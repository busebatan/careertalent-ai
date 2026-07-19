@extends('admin.layouts.app')

@section('title', __('admin.organizations.title'))

@section('content')
@php
    $types = trans('admin.organizations.types');
    $sizes = trans('admin.organizations.sizes');
    $statuses = trans('admin.organizations.statuses');
    $plans = trans('admin.organizations.plans');
    $canWrite = $isSuperAdmin || in_array('organizations.write', $adminPermissions, true);
    $organizationLabels = [
        'detail_error' => __('admin.organizations.detail_error'),
        'date_unknown' => __('admin.organizations.date_unknown'),
        'empty_section' => __('admin.organizations.empty_section'),
    ];
    $detailUrlTemplate = route('admin.organizations.show', ['organization' => '__ID__']);
    $panelUrlTemplate = route('company.dashboard', ['organizationSlug' => '__SLUG__']);
@endphp
<div class="mx-auto max-w-7xl"
    x-data="adminOrganizations({
        organizations: @js($organizations),
        canWrite: @js($canWrite),
        detailUrlTemplate: @js($detailUrlTemplate),
        panelUrlTemplate: @js($panelUrlTemplate),
        dateLocale: @js(str_replace('_', '-', app()->getLocale())),
        typeLabels: @js($types),
        sizeLabels: @js($sizes),
        statusLabels: @js($statuses),
        planLabels: @js($plans),
        labels: @js($organizationLabels),
    })">
    <header class="mb-8 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="mb-1 text-2xl font-bold text-slate-900 dark:text-white">{{ __('admin.organizations.title') }}</h1>
            <p class="text-slate-600 dark:text-slate-400">{{ __('admin.organizations.subtitle') }}</p>
        </div>
        <span class="rounded-full bg-slate-100 px-3 py-1 text-xs dark:bg-slate-800">{{ trans_choice('admin.organizations.total', $organizationsTotal, ['count' => $organizationsTotal]) }}</span>
    </header>

    @if (session('status'))<p class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-700 dark:text-emerald-200">{{ session('status') }}</p>@endif
    @if (session('company_invite_url'))<div class="mb-5 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-4"><p class="text-sm font-semibold">{{ __('admin.organizations.owner_invite_link') }}</p><input class="panel-input-block mt-2" readonly value="{{ session('company_invite_url') }}"></div>@endif
    @if ($adminError)<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $adminError }}</p>@endif
    @if ($errors->has('organizations'))<p class="mb-5 rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200">{{ $errors->first('organizations') }}</p>@endif

    @if ($canWrite)
    <section class="panel-card mb-8 p-6">
        <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h2 class="text-lg font-semibold text-slate-900 dark:text-white">{{ __('admin.organizations.create') }}</h2>
                <p class="panel-muted mt-1 text-sm">{{ __('admin.organizations.create_hint') }}</p>
            </div>
        </div>
        <form method="post" action="{{ route('admin.organizations.store') }}" class="grid gap-5 md:grid-cols-2 xl:grid-cols-4">
            @csrf
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.name') }}<input class="panel-input-block mt-2" name="name" value="{{ old('name') }}" required></label>
            <div class="rounded-xl border border-slate-200 bg-slate-50 p-4 text-sm dark:border-slate-800 dark:bg-slate-950/50 xl:col-span-2">
                <span class="font-medium">{{ __('admin.organizations.profile_url') }}</span>
                <p class="panel-muted mt-2 text-xs">{{ __('admin.organizations.profile_url_auto') }}</p>
            </div>
            <label class="text-sm font-medium">{{ __('admin.organizations.type') }}<select class="panel-input-block mt-2" name="organization_type">@foreach ($types as $value => $label)<option value="{{ $value }}" @selected(old('organization_type', 'employer') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.size') }}<select class="panel-input-block mt-2" name="size_band">@foreach ($sizes as $value => $label)<option value="{{ $value }}" @selected(old('size_band', 'smb') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.status') }}<select class="panel-input-block mt-2" name="status">@foreach ($statuses as $value => $label)<option value="{{ $value }}" @selected(old('status', 'onboarding') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium">{{ __('admin.organizations.plan') }}<select class="panel-input-block mt-2" name="plan_code">@foreach ($plans as $value => $label)<option value="{{ $value }}" @selected(old('plan_code', 'pilot') === $value)>{{ $label }}</option>@endforeach</select></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.billing_email') }}<input class="panel-input-block mt-2" name="billing_email" type="email" value="{{ old('billing_email') }}" required></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.owner_email') }}<input class="panel-input-block mt-2" name="owner_email" type="email" value="{{ old('owner_email') }}" required></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.website') }}<input class="panel-input-block mt-2" name="website" type="url" value="{{ old('website') }}" placeholder="https://"></label>
            <label class="text-sm font-medium xl:col-span-2">{{ __('admin.organizations.logo_url') }}<input class="panel-input-block mt-2" name="logo_url" type="url" value="{{ old('logo_url') }}" placeholder="https://"></label>
            <label class="text-sm font-medium xl:col-span-4">{{ __('admin.organizations.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description" maxlength="1000">{{ old('description') }}</textarea></label>
            <div class="xl:col-span-4"><button class="admin-btn-primary" type="submit">{{ __('admin.organizations.create') }}</button></div>
        </form>
    </section>
    @endif

    <section class="panel-card overflow-hidden">
        <div class="border-b border-slate-200 p-5 dark:border-slate-800">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="text-sm xl:col-span-2">
                    <span class="font-medium">{{ __('admin.organizations.search') }}</span>
                    <input class="panel-input-block mt-2" type="search" x-model="query" placeholder="{{ __('admin.organizations.search_placeholder') }}">
                </label>
                <label class="text-sm">
                    <span class="font-medium">{{ __('admin.organizations.filter_status') }}</span>
                    <select class="panel-input-block mt-2" x-model="statusFilter">
                        <option value="all">{{ __('admin.organizations.filter_status_all') }}</option>
                        @foreach ($statuses as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
                <label class="text-sm">
                    <span class="font-medium">{{ __('admin.organizations.filter_type') }}</span>
                    <select class="panel-input-block mt-2" x-model="typeFilter">
                        <option value="all">{{ __('admin.organizations.filter_type_all') }}</option>
                        @foreach ($types as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach
                    </select>
                </label>
            </div>
        </div>

        <div class="overflow-x-auto">
            <table class="admin-data-table">
                <thead>
                    <tr>
                        <th scope="col">{{ __('admin.organizations.table_name') }}</th>
                        <th scope="col">{{ __('admin.organizations.billing_email') }}</th>
                        <th scope="col">{{ __('admin.organizations.slug') }}</th>
                        <th scope="col">{{ __('admin.organizations.type') }}</th>
                        <th scope="col">{{ __('admin.organizations.status') }}</th>
                        <th scope="col">{{ __('admin.organizations.plan') }}</th>
                        <th scope="col">{{ __('admin.organizations.table_members') }}</th>
                        @if ($canWrite)
                            <th scope="col" class="text-right">{{ __('admin.organizations.table_actions') }}</th>
                        @endif
                    </tr>
                </thead>
                <tbody>
                    <template x-if="filteredOrganizations().length === 0">
                        <tr>
                            <td colspan="{{ $canWrite ? 8 : 7 }}" class="px-4 py-8 text-center text-sm text-slate-500">{{ __('admin.organizations.no_results') }}</td>
                        </tr>
                    </template>
                    <template x-for="organization in filteredOrganizations()" :key="organization.id">
                        <tr>
                            <td>
                                <button type="button"
                                    class="admin-student-name-link"
                                    @click="openDrawer(organization)"
                                    :aria-label="`${organization.name} — {{ __('admin.organizations.view_detail') }}`">
                                    <span x-text="organization.name"></span>
                                </button>
                            </td>
                            <td class="text-slate-600 dark:text-slate-300" x-text="organization.billing_email"></td>
                            <td class="text-slate-500" x-text="organization.slug"></td>
                            <td x-text="label(typeLabels, organization.organization_type)"></td>
                            <td>
                                <span class="rounded-full px-2.5 py-1 text-xs font-medium"
                                    :class="organization.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-slate-100 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                                    x-text="label(statusLabels, organization.status)"></span>
                            </td>
                            <td x-text="label(planLabels, organization.plan_code)"></td>
                            <td x-text="organization.members_count"></td>
                            @if ($canWrite)
                                <td class="text-right">
                                    <button type="button" class="admin-btn-secondary" @click="openDrawer(organization)">{{ __('admin.organizations.edit') }}</button>
                                </td>
                            @endif
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </section>

    <div x-show="drawerOpen" x-cloak class="admin-drawer-backdrop" @click="closeDrawer()" aria-hidden="true"></div>
    <aside x-show="drawerOpen" x-cloak class="admin-drawer-panel" role="dialog" aria-modal="true" :aria-label="selected ? selected.name : '{{ __('admin.organizations.drawer_title') }}'">
        <header class="flex items-start justify-between gap-4 border-b border-slate-200 px-6 py-5 dark:border-slate-800">
            <div class="min-w-0">
                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ __('admin.organizations.drawer_title') }}</p>
                <h2 class="mt-1 truncate text-xl font-bold text-slate-900 dark:text-white" x-text="selected?.name"></h2>
                <p class="panel-muted mt-1 truncate text-sm" x-text="selected?.billing_email"></p>
            </div>
            <button type="button" class="admin-btn-secondary shrink-0" @click="closeDrawer()">{{ __('admin.organizations.close_drawer') }}</button>
        </header>

        <div class="admin-drawer-body">
            <template x-if="drawerLoading">
                <p class="text-sm text-slate-500">{{ __('admin.organizations.detail_loading') }}</p>
            </template>
            <template x-if="drawerError">
                <p class="rounded-xl border border-red-500/30 bg-red-500/10 px-4 py-3 text-sm text-red-700 dark:text-red-200" x-text="drawerError"></p>
            </template>

            <template x-if="detail && !drawerLoading">
                <div>
                    <section class="admin-detail-section !mt-0 !border-t-0 !pt-0">
                        <h3 class="text-base font-semibold">{{ __('admin.organizations.section_overview') }}</h3>
                        <dl class="admin-detail-list">
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.slug') }}</dt>
                                <dd x-text="detail.slug"></dd>
                            </div>
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.profile_url') }}</dt>
                                <dd><a class="admin-accent-text break-all" :href="panelUrl(detail.slug)" target="_blank" rel="noreferrer" x-text="panelUrl(detail.slug)"></a></dd>
                            </div>
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2" x-show="detail.website">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.website') }}</dt>
                                <dd><a class="admin-accent-text break-all" :href="detail.website" target="_blank" rel="noreferrer" x-text="detail.website"></a></dd>
                            </div>
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.description') }}</dt>
                                <dd x-text="detail.description || labels.empty_section"></dd>
                            </div>
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2" x-show="detail.logo_url">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.logo_url') }}</dt>
                                <dd class="truncate" x-text="detail.logo_url"></dd>
                            </div>
                            <div class="admin-detail-row grid gap-1 sm:grid-cols-2">
                                <dt class="text-xs uppercase text-slate-500">{{ __('admin.organizations.created_at', ['date' => '']) }}</dt>
                                <dd x-text="formatDate(detail.created_at)"></dd>
                            </div>
                        </dl>
                    </section>

                    @if ($canWrite)
                        <section class="admin-detail-section">
                            <h3 class="text-base font-semibold">{{ __('admin.organizations.section_settings') }}</h3>
                            <form method="post" :action="`{{ url('/admin/kurumlar') }}/${detail.id}`" class="mt-4 grid gap-4 md:grid-cols-2" :key="detail.id">
                                @csrf
                                @method('PATCH')
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.name') }}<input class="panel-input-block mt-2" name="name" :value="detail.name" required></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.slug') }}<input class="panel-input-block mt-2" name="slug" :value="detail.slug" required></label>
                                <label class="text-sm">{{ __('admin.organizations.type') }}<select class="panel-input-block mt-2" name="organization_type">@foreach ($types as $value => $label)<option value="{{ $value }}" :selected="detail.organization_type === '{{ $value }}'">{{ $label }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('admin.organizations.size') }}<select class="panel-input-block mt-2" name="size_band">@foreach ($sizes as $value => $label)<option value="{{ $value }}" :selected="detail.size_band === '{{ $value }}'">{{ $label }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('admin.organizations.status') }}<select class="panel-input-block mt-2" name="status">@foreach ($statuses as $value => $label)<option value="{{ $value }}" :selected="detail.status === '{{ $value }}'">{{ $label }}</option>@endforeach</select></label>
                                <label class="text-sm">{{ __('admin.organizations.plan') }}<select class="panel-input-block mt-2" name="plan_code">@foreach ($plans as $value => $label)<option value="{{ $value }}" :selected="detail.plan_code === '{{ $value }}'">{{ $label }}</option>@endforeach</select></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.billing_email') }}<input class="panel-input-block mt-2" name="billing_email" type="email" :value="detail.billing_email" required></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.website') }}<input class="panel-input-block mt-2" name="website" type="url" :value="detail.website || ''"></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.logo_url') }}<input class="panel-input-block mt-2" name="logo_url" type="url" :value="detail.logo_url || ''"></label>
                                <label class="text-sm md:col-span-2">{{ __('admin.organizations.description') }}<textarea class="panel-input-block mt-2 min-h-28" name="description" maxlength="1000" x-model="detail.description"></textarea></label>
                                <div class="md:col-span-2"><button class="admin-btn-primary" type="submit">{{ __('admin.organizations.save') }}</button></div>
                            </form>
                        </section>

                        <section class="admin-detail-section">
                            <h3 class="text-base font-semibold">{{ __('admin.organizations.owner_invite') }}</h3>
                            <form method="post" :action="`{{ url('/admin/kurumlar') }}/${detail.id}/sahip-daveti`" class="mt-4 flex flex-wrap items-end gap-3">
                                @csrf
                                <label class="min-w-64 flex-1 text-sm">{{ __('admin.organizations.owner_email') }}<input class="panel-input-block mt-2" name="owner_email" type="email" required></label>
                                <button class="panel-btn-secondary" type="submit">{{ __('admin.organizations.owner_invite') }}</button>
                            </form>
                        </section>
                    @endif

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.organizations.section_members') }}</h3>
                        <template x-if="detail.members.length">
                            <ul class="admin-detail-list">
                                <template x-for="member in detail.members" :key="member.id">
                                    <li class="admin-detail-row">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium" x-text="member.full_name"></p>
                                            <span class="rounded-full bg-slate-200 px-2 py-0.5 text-xs uppercase dark:bg-slate-800" x-text="member.role"></span>
                                        </div>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span x-text="member.email"></span>
                                            · <span x-text="member.status"></span>
                                            <span x-show="member.created_at"> · <span x-text="formatDate(member.created_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!detail.members.length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>

                    <section class="admin-detail-section">
                        <h3 class="text-base font-semibold">{{ __('admin.organizations.section_invitations') }}</h3>
                        <template x-if="pendingInvitations().length">
                            <ul class="admin-detail-list">
                                <template x-for="invitation in pendingInvitations()" :key="invitation.id">
                                    <li class="admin-detail-row">
                                        <div class="flex flex-wrap items-center justify-between gap-2">
                                            <p class="font-medium" x-text="invitation.email"></p>
                                            <span class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-700 dark:text-amber-300" x-text="invitation.role"></span>
                                        </div>
                                        <p class="panel-muted mt-1 text-xs">
                                            <span x-show="invitation.expires_at">{{ __('admin.organizations.invitation_expires') }}: <span x-text="formatDate(invitation.expires_at)"></span></span>
                                        </p>
                                    </li>
                                </template>
                            </ul>
                        </template>
                        <template x-if="!pendingInvitations().length">
                            <p class="panel-muted mt-3 text-sm" x-text="labels.empty_section"></p>
                        </template>
                    </section>
                </div>
            </template>
        </div>
    </aside>
</div>
@endsection
