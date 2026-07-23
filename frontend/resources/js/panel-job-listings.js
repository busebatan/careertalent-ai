export function panelJobListings(initialItems, labels, initialCvDocuments = [], initialCvVersions = []) {
    return {
        items: Array.isArray(initialItems) ? initialItems : [],
        cvDocuments: Array.isArray(initialCvDocuments) ? initialCvDocuments : [],
        cvVersions: Array.isArray(initialCvVersions) ? initialCvVersions : [],
        labels: labels || {},
        query: '',
        workplace: '',
        employment: '',
        activeJob: null,

        // Başvuru Modal State
        applicationJob: null,
        demoApplicationOpen: false,
        selectedCvId: '',
        selectedVersionId: '',
        demoConsent: false,
        demoSubmitted: false,
        applicationSubmitting: false,
        applicationError: '',
        applicationSubmitted: false,
        applicationAnswers: {},

        // Route URLs — Blade şablonundan x-init ile set edilecek
        applyUrl: '',

        get filteredItems() {
            const query = this.query.trim().toLocaleLowerCase();
            return this.items.filter((item) => {
                const position = item?.position || {};
                const organization = item?.organization || {};
                const searchable = [position.title, organization.name, position.department, position.location]
                    .filter(Boolean)
                    .join(' ')
                    .toLocaleLowerCase();
                return (!query || searchable.includes(query))
                    && (!this.workplace || position.workplace_type === this.workplace)
                    && (!this.employment || position.employment_type === this.employment);
            });
        },

        openDetails(job) {
            this.activeJob = job;
        },

        closeDetails() {
            this.activeJob = null;
        },

        /** Tüm ilanlar için platform içi başvuru modalını açar (is_demo şartı YOK) */
        beginApplication(job) {
            this.applicationJob = job;
            this.applicationSubmitted = false;
            this.applicationError = '';
            this.applicationSubmitting = false;
            this.demoConsent = false;
            this.demoSubmitted = false;
            this.applicationAnswers = {};

            // CV sürümü önceliği: is_main olanı seç, yoksa ilki
            this.selectedVersionId =
                this.cvVersions.find((v) => v.is_main)?.id ||
                this.cvVersions[0]?.id ||
                '';

            // Geriye dönük uyum: eski cvDocuments dropdown için
            this.selectedCvId =
                this.cvDocuments.find((d) => d?.is_current)?.id ||
                this.cvDocuments[0]?.id ||
                '';

            this.demoApplicationOpen = true;
        },

        closeDemoApplication() {
            this.demoApplicationOpen = false;
            this.applicationJob = null;
        },

        /**
         * Demo başvuru (sadece başarı ekranı, API kaydı yok).
         * Gerçek başvurular için completeApplication() kullanılır.
         */
        completeDemoApplication() {
            if (!this.selectedCvId || !this.demoConsent) return false;
            this.demoSubmitted = true;
            return true;
        },

        /**
         * Platform içi başvuru — tüm ilanlar için.
         * Backend'e POST /panel/basvurularim gönderir ve Başvurularım'a kayıt oluşturur.
         */
        async completeApplication() {
            if (!this.demoConsent) return;
            if (!this.selectedVersionId && !this.selectedCvId) return;

            const job = this.applicationJob;
            const questions = job?.position?.questions || [];

            // Zorunlu başvuru soruları kontrolü
            for (const q of questions) {
                if (q.is_required && (!this.applicationAnswers[q.id] || !String(this.applicationAnswers[q.id]).trim())) {
                    this.applicationError = 'Lütfen tüm zorunlu soruları yanıtlayınız.';
                    return;
                }
            }

            this.applicationSubmitting = true;
            this.applicationError = '';

            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            const formattedAnswers = questions.map((q) => ({
                question_id: q.id,
                question_text: q.question_text,
                question_type: q.question_type,
                answer: this.applicationAnswers[q.id] || '',
            }));

            try {
                const response = await fetch(this.applyUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        ...(token ? { 'X-CSRF-TOKEN': token } : {}),
                    },
                    body: JSON.stringify({
                        company: job?.organization?.name || 'Kurum',
                        role: job?.position?.title || 'Pozisyon',
                        position_id: job?.position?.public_id || job?.position?.id || null,
                        cv_version_id: this.selectedVersionId || null,
                        cv_document_id: this.selectedCvId || null,
                        application_answers: formattedAnswers,
                        is_platform_apply: true,
                    }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    throw new Error(data.message || 'Başvuru gönderilemedi');
                }

                this.applicationSubmitted = true;
            } catch (err) {
                this.applicationError = err.message;
            } finally {
                this.applicationSubmitting = false;
            }
        },

        workplaceLabel(value) {
            return this.labels.workplaces?.[value] || value || this.labels.unspecified;
        },

        employmentLabel(value) {
            return this.labels.employment?.[value] || value || this.labels.unspecified;
        },

        levelLabel(value) {
            return this.labels.levels?.[value] || value || this.labels.unspecified;
        },

        skills(job) {
            const position = job?.position || {};
            return [...(position.must_have_skills || []), ...(position.preferred_skills || [])];
        },

        formatDeadline(value) {
            if (!value) return this.labels.noDeadline;
            const date = new Date(value);
            if (Number.isNaN(date.getTime())) return this.labels.noDeadline;
            return new Intl.DateTimeFormat(this.labels.locale || 'tr-TR', { day: '2-digit', month: 'long', year: 'numeric' }).format(date);
        },
    };
}
