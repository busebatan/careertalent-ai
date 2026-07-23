@php
    $canManage = in_array('positions.write', $permissions, true);
@endphp

<section class="panel-card p-6" x-data="companyPositionQuestions({
    positionId: @js($position['id']),
    initialQuestions: @js($position['questions'] ?? [])
})">
    <div class="flex flex-wrap items-center justify-between gap-4 border-b border-slate-200 pb-5 dark:border-slate-800">
        <div>
            <h2 class="text-xl font-bold text-slate-900 dark:text-white">{{ __('company.applications.questions_title') }}</h2>
            <p class="panel-muted mt-1 text-sm">{{ __('company.applications.questions_subtitle') }}</p>
        </div>
        @if($canManage)
            <button @click="openAddModal()" type="button" class="company-btn-primary">
                <i data-lucide="plus" class="h-4 w-4"></i>
                {{ __('company.applications.add_question') }}
            </button>
        @endif
    </div>

    <!-- Questions List -->
    <div class="mt-6 space-y-4">
        <template x-if="questions.length === 0">
            <div class="rounded-xl border border-dashed border-slate-300 p-8 text-center dark:border-slate-700">
                <p class="panel-muted text-sm">{{ __('company.applications.no_questions') }}</p>
            </div>
        </template>

        <template x-for="(q, index) in questions" :key="q.id">
            <div class="flex flex-wrap items-start justify-between gap-4 rounded-xl border border-slate-200 p-5 dark:border-slate-800 bg-white dark:bg-slate-900/60 shadow-sm">
                <div class="space-y-2 max-w-2xl">
                    <div class="flex items-center gap-3">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-slate-100 dark:bg-slate-800 text-xs font-bold text-slate-600 dark:text-slate-300" x-text="index + 1"></span>
                        <h4 class="font-bold text-base text-slate-900 dark:text-white" x-text="q.question_text"></h4>
                        <span x-show="q.is_required" class="rounded bg-rose-100 px-2 py-0.5 text-[10px] font-bold uppercase text-rose-700 dark:bg-rose-950/50 dark:text-rose-300">Zorunlu</span>
                    </div>
                    <div class="flex items-center gap-2 text-xs panel-muted pl-9">
                        <span>Soru Tipi:</span>
                        <span class="font-semibold text-slate-700 dark:text-slate-300 uppercase" x-text="q.question_type"></span>
                    </div>
                    <template x-if="q.question_type === 'single_choice' && q.options && q.options.length > 0">
                        <div class="pl-9 mt-2">
                            <span class="text-xs font-medium text-slate-500 block mb-1">Seçenekler:</span>
                            <div class="flex flex-wrap gap-1.5">
                                <template x-for="opt in q.options" :key="opt">
                                    <span class="rounded-md border border-slate-200 px-2.5 py-1 text-xs text-slate-700 dark:border-slate-800 dark:text-slate-300 bg-slate-50 dark:bg-slate-800/50" x-text="opt"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                </div>
                
                @if($canManage)
                    <div class="flex items-center gap-2">
                        <button @click="openEditModal(q)" type="button" class="rounded-lg p-2 text-slate-500 hover:bg-slate-100 hover:text-slate-800 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                            Düzenle
                        </button>
                        <button @click="deleteQuestion(q.id)" type="button" class="rounded-lg p-2 text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-950/30">
                            Sil
                        </button>
                    </div>
                @endif
            </div>
        </template>
    </div>

    <!-- Question Modal Form -->
    <div x-show="isFormOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeFormModal()"></div>
        <div class="relative w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white" x-text="editingId ? 'Soruyu Düzenle' : 'Yeni Soru Ekle'"></h3>
            
            <form @submit.prevent="saveQuestion()" class="mt-4 space-y-4">
                <div>
                    <label class="panel-label block mb-1">{{ __('company.applications.question_text') }}</label>
                    <input type="text" x-model="form.question_text" required class="panel-input-block" placeholder="Örn: Kaç yıl deneyiminiz var?">
                </div>

                <div>
                    <label class="panel-label block mb-1">{{ __('company.applications.question_type') }}</label>
                    <select x-model="form.question_type" class="panel-input-block">
                        <option value="text">{{ __('company.applications.type_text') }}</option>
                        <option value="number">{{ __('company.applications.type_number') }}</option>
                        <option value="single_choice">{{ __('company.applications.type_single_choice') }}</option>
                    </select>
                </div>

                <div x-show="form.question_type === 'single_choice'">
                    <label class="panel-label block mb-1">{{ __('company.applications.options_label') }}</label>
                    <textarea x-model="form.options_text" rows="3" class="panel-input-block text-xs" placeholder="Seçenek 1&#10;Seçenek 2&#10;Seçenek 3"></textarea>
                </div>

                <div class="flex items-center gap-2">
                    <input type="checkbox" id="is_required_chk" x-model="form.is_required" class="h-4 w-4 rounded border-slate-300 text-primary-600 focus:ring-primary-500">
                    <label for="is_required_chk" class="text-sm font-medium text-slate-700 dark:text-slate-300">{{ __('company.applications.is_required') }}</label>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-200 pt-4 dark:border-slate-800">
                    <button type="button" @click="closeFormModal()" class="panel-btn-secondary">İptal</button>
                    <button type="submit" class="company-btn-primary">{{ __('company.applications.save_question') }}</button>
                </div>
            </form>
        </div>
    </div>
</section>

<script>
function companyPositionQuestions(config) {
    return {
        positionId: config.positionId,
        questions: config.initialQuestions || [],
        isFormOpen: false,
        editingId: null,
        form: {
            question_text: '',
            question_type: 'text',
            options_text: '',
            is_required: true,
        },
        openAddModal() {
            this.editingId = null;
            this.form = { question_text: '', question_type: 'text', options_text: '', is_required: true };
            this.isFormOpen = true;
        },
        openEditModal(q) {
            this.editingId = q.id;
            this.form = {
                question_text: q.question_text,
                question_type: q.question_type,
                options_text: (q.options || []).join('\n'),
                is_required: q.is_required,
            };
            this.isFormOpen = true;
        },
        closeFormModal() {
            this.isFormOpen = false;
        },
        async saveQuestion() {
            const options = this.form.question_type === 'single_choice'
                ? this.form.options_text.split('\n').map(s => s.trim()).filter(Boolean)
                : [];
            
            const payload = {
                question_text: this.form.question_text,
                question_type: this.form.question_type,
                options: options,
                is_required: this.form.is_required,
                sort_order: this.questions.length,
            };

            try {
                const url = this.editingId 
                    ? `/api/v1/company/positions/${this.positionId}/questions/${this.editingId}`
                    : `/api/v1/company/positions/${this.positionId}/questions`;
                
                const method = this.editingId ? 'PUT' : 'POST';
                const res = await fetch(url, {
                    method: method,
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                    body: JSON.stringify(payload)
                });
                
                if (res.ok) {
                    const saved = await res.json();
                    if (this.editingId) {
                        const idx = this.questions.findIndex(q => q.id === this.editingId);
                        if (idx !== -1) this.questions[idx] = saved;
                    } else {
                        this.questions.push(saved);
                    }
                    this.closeFormModal();
                }
            } catch (err) {
                // Silently handled per guidelines
            }
        },
        async deleteQuestion(id) {
            if (!confirm('Bu soruyu silmek istediğinize emin misiniz?')) return;
            try {
                const res = await fetch(`/api/v1/company/positions/${this.positionId}/questions/${id}`, { method: 'DELETE' });
                if (res.ok) {
                    this.questions = this.questions.filter(q => q.id !== id);
                }
            } catch (err) {
                // Silently handled per guidelines
            }
        }
    };
}
</script>
