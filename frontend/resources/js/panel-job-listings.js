export function panelJobListings(initialItems, labels) {
    return {
        items: Array.isArray(initialItems) ? initialItems : [],
        labels: labels || {},
        query: '',
        workplace: '',
        employment: '',
        activeJob: null,
        demoNotice: false,

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
            this.demoNotice = false;
        },

        closeDetails() {
            this.activeJob = null;
        },

        applicationPath(job) {
            this.demoNotice = Boolean(job?.is_demo);
            return job?.is_demo ? null : (job?.position?.public_path || null);
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
