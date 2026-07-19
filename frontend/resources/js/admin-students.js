export function adminStudents(config) {
    const labels = config.labels || {};
    const studentsBaseUrl = config.studentsBaseUrl || '';

    return {
        students: Array.isArray(config.students) ? config.students : [],
        query: '',
        statusFilter: 'all',
        localeFilter: 'all',
        drawerOpen: false,
        drawerLoading: false,
        drawerError: '',
        selected: null,
        detail: null,
        confirmOpen: false,
        confirmType: null,
        confirmStudent: null,
        labels,
        canWrite: Boolean(config.canWrite),
        canDelete: Boolean(config.canDelete),
        detailUrlTemplate: config.detailUrlTemplate || '',
        studentsBaseUrl,
        dateLocale: config.dateLocale || 'tr-TR',

        filteredStudents() {
            const needle = this.query.trim().toLowerCase();

            return this.students.filter((student) => {
                if (this.statusFilter === 'active' && !student.is_active) {
                    return false;
                }
                if (this.statusFilter === 'inactive' && student.is_active) {
                    return false;
                }
                if (this.localeFilter !== 'all' && student.preferred_locale !== this.localeFilter) {
                    return false;
                }
                if (!needle) {
                    return true;
                }

                return (student.full_name || '').toLowerCase().includes(needle)
                    || (student.email || '').toLowerCase().includes(needle);
            });
        },

        async openDrawer(student) {
            this.selected = student;
            this.drawerOpen = true;
            this.drawerLoading = true;
            this.drawerError = '';
            this.detail = null;

            try {
                const url = this.detailUrlTemplate.replace('__ID__', String(student.id));
                const response = await fetch(url, { headers: { Accept: 'application/json' } });
                const body = await response.json().catch(() => ({}));
                if (!response.ok) {
                    throw new Error(body.message || labels.detail_error);
                }
                this.detail = body;
            } catch (error) {
                this.drawerError = error?.message || labels.detail_error;
            } finally {
                this.drawerLoading = false;
            }
        },

        closeDrawer() {
            this.drawerOpen = false;
            this.selected = null;
            this.detail = null;
            this.drawerError = '';
        },

        formatDate(value) {
            if (!value) {
                return labels.date_unknown;
            }

            try {
                return new Intl.DateTimeFormat(this.dateLocale, {
                    dateStyle: 'medium',
                    timeStyle: 'short',
                }).format(new Date(value));
            } catch {
                return value;
            }
        },

        openDeactivateConfirm(student) {
            this.confirmStudent = student;
            this.confirmType = 'deactivate';
            this.confirmOpen = true;
        },

        openActivateConfirm(student) {
            this.confirmStudent = student;
            this.confirmType = 'activate';
            this.confirmOpen = true;
        },

        closeConfirm() {
            this.confirmOpen = false;
            this.confirmType = null;
            this.confirmStudent = null;
        },

        confirmTitle() {
            if (this.confirmType === 'deactivate') {
                return labels.confirm_deactivate_title;
            }
            if (this.confirmType === 'activate') {
                return labels.confirm_activate_title;
            }

            return '';
        },

        confirmMessage() {
            if (this.confirmType === 'deactivate') {
                return labels.confirm_deactivate;
            }
            if (this.confirmType === 'activate') {
                return labels.confirm_activate;
            }

            return '';
        },

        submitConfirmedAction() {
            const student = this.confirmStudent;
            if (!student || !this.confirmType || !studentsBaseUrl) {
                return;
            }

            const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = `${studentsBaseUrl}/${student.id}`;

            const addField = (name, value) => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = name;
                input.value = value;
                form.appendChild(input);
            };

            if (csrf) {
                addField('_token', csrf);
            }

            if (this.confirmType === 'deactivate') {
                addField('_method', 'DELETE');
            } else {
                addField('_method', 'PATCH');
                addField('full_name', student.full_name);
                addField('email', student.email);
                addField('preferred_locale', student.preferred_locale);
                addField('is_active', '1');
            }

            document.body.appendChild(form);
            form.submit();
            this.closeConfirm();
        },
    };
}
