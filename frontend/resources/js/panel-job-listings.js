export function panelJobListings(initialItems, labels, initialCvDocuments = []) {
    return {
        items: Array.isArray(initialItems) ? initialItems : [],
        cvDocuments: Array.isArray(initialCvDocuments) ? initialCvDocuments : [],
        labels: labels || {},
        query: '',
        workplace: '',
        employment: '',
        activeJob: null,
        demoApplicationOpen: false,
        selectedCvId: '',
        demoConsent: false,
        demoSubmitted: false,

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

        beginApplication(job) {
            if (!job?.is_demo) {
                this.demoApplicationOpen = false;
                return job?.position?.public_path || null;
            }
            this.demoSubmitted = false;
            this.demoConsent = false;
            this.selectedCvId = this.cvDocuments.find((document) => document?.is_current)?.id
                || this.cvDocuments[0]?.id
                || '';
            this.demoApplicationOpen = true;
            return null;
        },

        closeDemoApplication() {
            this.demoApplicationOpen = false;
        },

        completeDemoApplication() {
            if (!this.selectedCvId || !this.demoConsent) return false;
            this.demoSubmitted = true;
            return true;
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
