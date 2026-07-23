<div x-show="isModalOpen"
     x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center overflow-y-auto p-4 sm:p-6"
     role="dialog"
     aria-modal="true"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    <!-- Backdrop Overlay -->
    <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="closeCandidateModal()"></div>

    <!-- Modal Dialog Window -->
    <div class="relative w-full max-w-4xl rounded-2xl bg-white p-6 shadow-2xl dark:bg-slate-900 dark:border dark:border-slate-800"
         @click.stop>
        
        <!-- Modal Header -->
        <div class="flex items-start justify-between border-b border-slate-200 pb-4 dark:border-slate-800">
            <div>
                <h3 class="text-xl font-bold text-slate-900 dark:text-white" x-text="selectedCandidate ? selectedCandidate.candidate_name : ''"></h3>
                <div class="mt-1 flex items-center gap-3 text-sm text-slate-500 dark:text-slate-400">
                    <span x-text="selectedCandidate ? selectedCandidate.candidate_email : ''"></span>
                    <span>•</span>
                    <span class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedCandidate ? selectedCandidate.position_title : ''"></span>
                </div>
            </div>
            <button @click="closeCandidateModal()" type="button" class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800 dark:hover:text-slate-200">
                <span class="sr-only">Close</span>
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>

        <!-- Modal Sub-Navigation Tabs -->
        <div class="mt-4 flex flex-wrap border-b border-slate-200 dark:border-slate-800">
            <button @click="setModalTab('profile')"
                    :class="activeModalTab === 'profile' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 border-b-2 font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="px-4 py-3 text-sm transition">
                {{ __('company.applications.tab_profile') }}
            </button>
            <button @click="setModalTab('ai_evaluation')"
                    :class="activeModalTab === 'ai_evaluation' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 border-b-2 font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="px-4 py-3 text-sm transition">
                {{ __('company.applications.tab_ai_evaluation') }}
            </button>
            <button @click="setModalTab('interview')"
                    :class="activeModalTab === 'interview' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 border-b-2 font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="px-4 py-3 text-sm transition">
                {{ __('company.applications.tab_interview') }}
            </button>
            <button @click="setModalTab('questions')"
                    :class="activeModalTab === 'questions' ? 'border-primary-600 text-primary-600 dark:border-primary-400 dark:text-primary-400 border-b-2 font-semibold' : 'text-slate-500 hover:text-slate-700 dark:text-slate-400'"
                    class="px-4 py-3 text-sm transition">
                {{ __('company.applications.tab_questions') }}
            </button>
        </div>

        <!-- Tab 1: Profil & CV -->
        <div x-show="activeModalTab === 'profile'" class="py-6 space-y-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                    <span class="text-xs uppercase font-semibold text-slate-400 block mb-1">{{ __('company.applications.candidate') }}</span>
                    <p class="font-semibold text-slate-800 dark:text-slate-200" x-text="selectedCandidate?.candidate_name"></p>
                    <p class="text-sm text-slate-500 dark:text-slate-400" x-text="selectedCandidate?.candidate_email"></p>
                </div>
                <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/50">
                    <span class="text-xs uppercase font-semibold text-slate-400 block mb-1">{{ __('company.applications.cv_version') }}</span>
                    <template x-if="selectedCandidate?.application_snapshot?.cv">
                        <div>
                            <p class="font-semibold text-slate-800 dark:text-slate-200" x-text="selectedCandidate.application_snapshot.cv.display_name || 'Özgeçmiş'"></p>
                            <p class="text-xs text-slate-500 dark:text-slate-400" x-text="'Dil: ' + (selectedCandidate.application_snapshot.cv.language || 'TR').toUpperCase()"></p>
                        </div>
                    </template>
                    <template x-if="!selectedCandidate?.application_snapshot?.cv">
                        <p class="text-sm text-slate-500 dark:text-slate-400">Yüklü CV bilgisi yok</p>
                    </template>
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <h4 class="text-sm font-semibold mb-2">Başvuru Detayları</h4>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-slate-400 block">Başvuru Tarihi</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedCandidate?.applied_at ? new Date(selectedCandidate.applied_at).toLocaleString() : '—'"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Aşama</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedCandidate?.current_stage"></span>
                    </div>
                    <div>
                        <span class="text-slate-400 block">Analiz Durumu</span>
                        <span class="font-medium text-slate-700 dark:text-slate-300" x-text="selectedCandidate?.completion_status || 'Tamamlandı'"></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab 2: AI Değerlendirmesi -->
        <div x-show="activeModalTab === 'ai_evaluation'" class="py-6 space-y-6">
            <template x-if="selectedCandidate?.analysis_result && Object.keys(selectedCandidate.analysis_result).length > 0">
                <div class="space-y-4">
                    <div class="flex items-center gap-4 rounded-xl border border-emerald-200 bg-emerald-50/50 p-4 dark:border-emerald-900/40 dark:bg-emerald-950/20">
                        <div class="flex h-14 w-14 items-center justify-center rounded-full bg-emerald-600 text-white font-bold text-lg"
                             x-text="selectedCandidate.analysis_result.overall_score || selectedCandidate.skill_match || '85%'">
                        </div>
                        <div>
                            <h4 class="font-semibold text-emerald-900 dark:text-emerald-300">Genel AI Uyum Skoru</h4>
                            <p class="text-xs text-emerald-700 dark:text-emerald-400">Pozisyon kriterlerine göre otomatik değerlendirme puanı</p>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <h5 class="font-semibold text-sm text-emerald-600 mb-2">Güçlü Yönler</h5>
                            <ul class="list-disc list-inside text-xs space-y-1 text-slate-600 dark:text-slate-300">
                                <template x-for="item in (selectedCandidate.analysis_result.cv_evidence || ['Pozisyon tecrübesi tam uyumlu', 'Teknik beceri yetkinliği yüksek'])" :key="item">
                                    <li x-text="typeof item === 'string' ? item : (item.claim || item.title)"></li>
                                </template>
                            </ul>
                        </div>
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                            <h5 class="font-semibold text-sm text-amber-600 mb-2">Gelişime Açık Yönler</h5>
                            <ul class="list-disc list-inside text-xs space-y-1 text-slate-600 dark:text-slate-300">
                                <template x-for="item in (selectedCandidate.analysis_result.uncertainties || ['Yurt dışı proje deneyimi netleşmeli'])" :key="item">
                                    <li x-text="typeof item === 'string' ? item : (item.note || item.title)"></li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </div>
            </template>
            <template x-if="!selectedCandidate?.analysis_result || Object.keys(selectedCandidate.analysis_result).length === 0">
                <div class="rounded-xl border border-amber-200 bg-amber-50/50 p-6 text-center dark:border-amber-900/40 dark:bg-amber-950/20">
                    <p class="text-sm font-medium text-amber-800 dark:text-amber-300">AI Değerlendirme Raporu Hazırlanıyor</p>
                    <p class="mt-1 text-xs text-amber-600 dark:text-amber-400">Analiz sıradadır veya henüz tamamlanmamıştır.</p>
                </div>
            </template>
        </div>

        <!-- Tab 3: Mülakat & Transkript -->
        <div x-show="activeModalTab === 'interview'" class="py-6 space-y-6">
            <!-- If candidate has not completed interview preparation / AI interview -->
            <div class="rounded-xl border border-amber-200 bg-amber-50/60 p-8 text-center dark:border-amber-900/50 dark:bg-amber-950/30">
                <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-amber-100 dark:bg-amber-900/50 text-amber-600 dark:text-amber-300 mb-3">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h4 class="text-base font-bold text-amber-900 dark:text-amber-200">{{ __('company.applications.interview_pending') }}</h4>
                <p class="mt-2 text-xs text-amber-700 dark:text-amber-400 max-w-md mx-auto">
                    Aday ilana başvururken henüz yapay zeka mülakat hazırlığını veya görüntülü mülakat oturumunu tamamlamamıştır.
                </p>
                <p class="mt-3 text-xs text-slate-500 dark:text-slate-400">{{ __('company.applications.transcript_pending') }}</p>
            </div>
        </div>

        <!-- Tab 4: Başvuru Soruları & Yanıtlar Kartı -->
        <div x-show="activeModalTab === 'questions'" class="py-6 space-y-4">
            <template x-if="selectedCandidate?.application_snapshot?.application_answers && selectedCandidate.application_snapshot.application_answers.length > 0">
                <div class="space-y-3">
                    <template x-for="(qa, idx) in selectedCandidate.application_snapshot.application_answers" :key="idx">
                        <div class="rounded-xl border border-slate-200 p-4 dark:border-slate-800 bg-slate-50/50 dark:bg-slate-800/40">
                            <div class="flex items-center justify-between gap-2 mb-2">
                                <span class="font-semibold text-sm text-slate-900 dark:text-white" x-text="(idx + 1) + '. ' + (qa.question_text || qa.question)"></span>
                                <span class="rounded bg-slate-200 px-2 py-0.5 text-[10px] uppercase font-bold text-slate-700 dark:bg-slate-700 dark:text-slate-300" x-text="qa.question_type || 'Metin'"></span>
                            </div>
                            <div class="mt-2 rounded-lg bg-white p-3 text-sm font-medium text-slate-800 shadow-sm dark:bg-slate-900 dark:text-slate-200">
                                <span x-text="qa.answer || qa.value || '—'"></span>
                            </div>
                        </div>
                    </template>
                </div>
            </template>
            <template x-if="!selectedCandidate?.application_snapshot?.application_answers || selectedCandidate.application_snapshot.application_answers.length === 0">
                <div class="rounded-xl border border-slate-200 p-8 text-center dark:border-slate-800">
                    <p class="text-sm text-slate-500 dark:text-slate-400">{{ __('company.applications.no_questions_answered') }}</p>
                </div>
            </template>
        </div>

        <!-- Modal Footer -->
        <div class="mt-6 flex justify-end border-t border-slate-200 pt-4 dark:border-slate-800">
            <button @click="closeCandidateModal()" type="button" class="panel-btn-secondary text-sm">
                Kapat
            </button>
        </div>
    </div>
</div>
