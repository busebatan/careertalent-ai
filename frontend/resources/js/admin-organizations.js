export function adminOrganizations(config) {
    const labels = config.labels || {};

    return {
        organizations: Array.isArray(config.organizations) ? config.organizations : [],
        query: '',
        statusFilter: 'all',
        typeFilter: 'all',
        planFilter: 'all',
        drawerOpen: false,
        drawerLoading: false,
        drawerError: '',
        selected: null,
        detail: null,
        labels,
        typeLabels: config.typeLabels || {},
        sizeLabels: config.sizeLabels || {},
        statusLabels: config.statusLabels || {},
        planLabels: config.planLabels || {},
        canWrite: Boolean(config.canWrite),
        detailUrlTemplate: config.detailUrlTemplate || '',
        panelUrlTemplate: config.panelUrlTemplate || '',
        dateLocale: config.dateLocale || 'tr-TR',

        filteredOrganizations() {
            const needle = this.query.trim().toLowerCase();

            return this.organizations.filter((organization) => {
                if (this.statusFilter !== 'all' && organization.status !== this.statusFilter) {
                    return false;
                }
                if (this.typeFilter !== 'all' && organization.organization_type !== this.typeFilter) {
                    return false;
                }
                if (this.planFilter !== 'all' && organization.plan_code !== this.planFilter) {
                    return false;
                }
                if (!needle) {
                    return true;
                }

                return (organization.name || '').toLowerCase().includes(needle)
                    || (organization.billing_email || '').toLowerCase().includes(needle)
                    || (organization.slug || '').toLowerCase().includes(needle);
            });
        },

        async openDrawer(organization) {
            this.selected = organization;
            this.drawerOpen = true;
            this.drawerLoading = true;
            this.drawerError = '';
            this.detail = null;

            try {
                const url = this.detailUrlTemplate.replace('__ID__', encodeURIComponent(organization.id));
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

        label(map, value) {
            return map?.[value] || value || labels.empty_section;
        },

        panelUrl(slug) {
            return (this.panelUrlTemplate || '').replace('__SLUG__', encodeURIComponent(slug || ''));
        },

        pendingInvitations() {
            return (this.detail?.invitations || []).filter((item) => !item.accepted_at);
        },
    };
}
