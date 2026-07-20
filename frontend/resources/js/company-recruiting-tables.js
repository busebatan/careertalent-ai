function matchesQuery(needle, values) {
    if (!needle) {
        return true;
    }

    return values.some((value) => String(value || '').toLowerCase().includes(needle));
}

export function companyApplications(config) {
    const applications = Array.isArray(config.applications) ? config.applications : [];

    return {
        applications,
        query: '',
        stageFilter: 'all',
        positionFilter: 'all',
        stageOptions: Array.isArray(config.stageOptions) ? config.stageOptions : [],
        positionOptions: Array.isArray(config.positionOptions) ? config.positionOptions : [],
        labels: config.labels || {},

        filteredApplications() {
            const needle = this.query.trim().toLowerCase();

            return this.applications.filter((application) => {
                if (this.stageFilter !== 'all' && application.current_stage !== this.stageFilter) {
                    return false;
                }
                if (this.positionFilter !== 'all' && application.position_title !== this.positionFilter) {
                    return false;
                }

                return matchesQuery(needle, [
                    application.candidate_name,
                    application.candidate_email,
                    application.position_title,
                ]);
            });
        },

        isVisible(application) {
            return this.filteredApplications().some((item) => item.id === application.id);
        },

        visibleCount() {
            return this.filteredApplications().length;
        },
    };
}

export function companyAssessments(config) {
    const assessments = Array.isArray(config.assessments) ? config.assessments : [];

    return {
        assessments,
        query: '',
        statusFilter: 'all',
        positionFilter: 'all',
        statusOptions: Array.isArray(config.statusOptions) ? config.statusOptions : [],
        positionOptions: Array.isArray(config.positionOptions) ? config.positionOptions : [],
        labels: config.labels || {},

        filteredAssessments() {
            const needle = this.query.trim().toLowerCase();

            return this.assessments.filter((assessment) => {
                if (this.statusFilter !== 'all' && assessment.status !== this.statusFilter) {
                    return false;
                }
                if (this.positionFilter !== 'all' && assessment.position_title !== this.positionFilter) {
                    return false;
                }

                return matchesQuery(needle, [
                    assessment.candidate_name,
                    assessment.position_title,
                    assessment.title,
                ]);
            });
        },

        isVisible(assessment) {
            return this.filteredAssessments().some((item) => item.id === assessment.id);
        },

        visibleCount() {
            return this.filteredAssessments().length;
        },
    };
}
