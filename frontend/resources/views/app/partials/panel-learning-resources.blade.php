@php($isPreview = ($mode ?? 'full') === 'preview')

<section
    @unless($isPreview)
        x-data="{
            resources: {{ Js::from($learningResources) }},
            priceType: 'all',
            priceRange: 'all',
            certificateOnly: false,
            labels: {{ Js::from([
                'resources' => __('panel.dashboard.resources_count', ['count' => '__COUNT__']),
                'skills' => __('panel.dashboard.skills'),
                'certificate' => __('panel.dashboard.certificate'),
                'free' => __('panel.dashboard.free_badge'),
                'visit' => __('panel.dashboard.visit_site'),
                'empty' => __('panel.dashboard.no_resources'),
            ]) }},
            get filtered() {
                return this.resources.filter(r => {
                    if (this.priceType !== 'all' && r.price_type !== this.priceType) return false;
                    if (this.priceRange !== 'all' && r.price_range !== this.priceRange) return false;
                    if (this.certificateOnly && !r.has_certificate) return false;
                    return true;
                });
            },
            resourceLabel(count) {
                return this.labels.resources.replace('__COUNT__', count);
            }
        }"
    @endunless
>
    @unless ($isPreview)
        <div class="mb-4 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
            <p class="text-sm text-slate-600 dark:text-slate-400">{{ __('panel.dashboard.learning_desc') }}</p>
            <p class="text-xs text-slate-500" x-text="resourceLabel(filtered.length)"></p>
        </div>

        <div class="mb-4 flex flex-wrap gap-2">
            <button type="button" @click="priceType = 'all'"
                :class="priceType === 'all' ? 'bg-emerald-500 text-white dark:text-slate-950' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-full px-3 py-1 text-xs font-medium transition">{{ __('panel.dashboard.filter_all') }}</button>
            <button type="button" @click="priceType = 'free'"
                :class="priceType === 'free' ? 'bg-emerald-500 text-white dark:text-slate-950' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-full px-3 py-1 text-xs font-medium transition">{{ __('panel.dashboard.filter_free') }}</button>
            <button type="button" @click="priceType = 'paid'"
                :class="priceType === 'paid' ? 'bg-emerald-500 text-white dark:text-slate-950' : 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300'"
                class="rounded-full px-3 py-1 text-xs font-medium transition">{{ __('panel.dashboard.filter_paid') }}</button>
            <span class="mx-1 hidden h-5 w-px bg-slate-300 dark:bg-slate-700 sm:inline"></span>
            <button type="button" @click="priceRange = 'all'"
                :class="priceRange === 'all' ? 'bg-slate-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                class="rounded-full px-3 py-1 text-xs transition">{{ __('panel.dashboard.filter_all_prices') }}</button>
            <button type="button" @click="priceRange = '0-500'"
                :class="priceRange === '0-500' ? 'bg-slate-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                class="rounded-full px-3 py-1 text-xs transition">0–500 ₺</button>
            <button type="button" @click="priceRange = '500-2000'"
                :class="priceRange === '500-2000' ? 'bg-slate-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                class="rounded-full px-3 py-1 text-xs transition">500–2000 ₺</button>
            <button type="button" @click="priceRange = '2000+'"
                :class="priceRange === '2000+' ? 'bg-slate-600 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                class="rounded-full px-3 py-1 text-xs transition">2000+ ₺</button>
            <button type="button" @click="certificateOnly = !certificateOnly"
                :class="certificateOnly ? 'bg-amber-600/80 text-white' : 'bg-slate-200 text-slate-600 dark:bg-slate-800 dark:text-slate-400'"
                class="rounded-full px-3 py-1 text-xs transition">{{ __('panel.dashboard.filter_cert') }}</button>
        </div>
    @endunless

    <div class="space-y-3">
        @if ($isPreview)
            @foreach (array_slice($learningResources, 0, 3) as $item)
                <article class="panel-entry flex flex-col gap-2 p-3">
                    <h3 class="text-sm font-medium text-slate-900 dark:text-slate-100">{{ $item['title'] }}</h3>
                    <p class="text-xs text-slate-500">{{ $item['provider'] }} · {{ $item['price_label'] }}</p>
                </article>
            @endforeach
        @else
            <template x-for="item in filtered" :key="item.id">
                <article class="panel-card flex flex-col gap-3 p-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="min-w-0">
                        <div class="mb-1 flex flex-wrap items-center gap-2">
                            <h3 class="font-medium text-slate-900 dark:text-slate-100" x-text="item.title"></h3>
                            <span x-show="item.has_certificate"
                                class="rounded-md bg-amber-100 px-2 py-0.5 text-xs text-amber-800 dark:bg-amber-950/60 dark:text-amber-300" x-text="labels.certificate"></span>
                            <span x-show="item.price_type === 'free'"
                                class="rounded-md bg-emerald-100 px-2 py-0.5 text-xs text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300" x-text="labels.free"></span>
                        </div>
                        <p class="text-sm text-slate-500">
                            <span x-text="item.provider"></span>
                            · <span x-text="item.price_label"></span>
                        </p>
                        <p class="mt-1 text-xs text-slate-500 dark:text-slate-600" x-text="labels.skills + ': ' + item.skills.join(', ')"></p>
                    </div>
                    <a :href="item.url" target="_blank" rel="noopener noreferrer"
                        class="shrink-0 rounded-xl bg-emerald-500 px-4 py-2 text-center text-sm font-semibold text-white hover:bg-emerald-400 dark:text-slate-950"
                        x-text="labels.visit + ' ↗'">
                    </a>
                </article>
            </template>
            <p x-show="filtered.length === 0" class="rounded-xl border border-dashed border-slate-300 py-8 text-center text-sm text-slate-500 dark:border-slate-700" x-text="labels.empty"></p>
        @endif
    </div>

    @unless ($isPreview)
        <p class="mt-4 text-xs text-slate-500 dark:text-slate-600">{{ __('panel.dashboard.external_note') }}</p>
    @endunless
</section>
