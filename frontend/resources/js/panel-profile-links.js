export const PROFILE_PLATFORM_OPTIONS = [
    'GitHub',
    'GitLab',
    'Behance',
    'Dribbble',
    'Figma',
    'Instagram',
    'YouTube',
    'TikTok',
    'X',
    'Medium',
    'Website',
    'Portfolio',
];

function normalizeLink(link, index) {
    return {
        id: link.id || `profile-link-${index}-${Date.now()}`,
        platform: String(link.platform || ''),
        url: String(link.url || ''),
    };
}

function matchesPlatform(option, query) {
    return option.toLowerCase().includes(query.trim().toLowerCase());
}

export function profileSocialLinks(seedLinks, storageKey = 'panel-profile-links', profile = {}, saveUrl = '') {
    return {
        links: [],
        profile: { full_name: '', phone: '', location: '', headline: '', linkedin: '', ...profile },
        saveUrl,
        saving: false,
        saved: false,
        error: '',
        maxLinks: 8,
        platformOptions: PROFILE_PLATFORM_OPTIONS,
        openLinkId: null,
        dropdownUp: false,
        init() {
            let stored = null;
            if (!this.saveUrl) try { stored = JSON.parse(localStorage.getItem(storageKey) || 'null'); } catch { stored = null; }
            const source = Array.isArray(stored) ? stored : (Array.isArray(seedLinks) ? seedLinks : []);
            this.links = source.map(normalizeLink);
            if (!this.links.length) {
                this.addLink();
            }
        },
        addLink() {
            if (this.links.length >= this.maxLinks) {
                return;
            }
            this.links.push(normalizeLink({}, this.links.length));
            this.persist();
        },
        removeLink(link) {
            if (this.openLinkId === link.id) {
                this.closeDropdown();
            }
            this.links = this.links.filter((item) => item.id !== link.id);
            if (!this.links.length) {
                this.addLink();
            }
            this.persist();
        },
        persist() {
            if (!this.saveUrl) localStorage.setItem(storageKey, JSON.stringify(this.links.map(({ platform, url }) => ({ platform, url }))));
        },
        async save() {
            if (!this.saveUrl || this.saving) return;
            this.saving = true; this.saved = false; this.error = '';
            const token = document.querySelector('meta[name="csrf-token"]')?.content;
            try {
                const response = await fetch(this.saveUrl, { method: 'PUT', headers: { ...(token ? { 'X-CSRF-TOKEN': token } : {}), 'Content-Type': 'application/json', Accept: 'application/json' }, body: JSON.stringify({ ...this.profile, social_links: this.links.filter((item) => item.platform.trim() && item.url.trim()).map(({ platform, url }) => ({ platform, url })) }) });
                const payload = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(payload.message || 'Profil kaydedilemedi');
                this.profile = { ...this.profile, ...payload }; this.saved = true;
            } catch (error) { this.error = error?.message || 'Profil kaydedilemedi'; }
            finally { this.saving = false; }
        },
        closeDropdown() {
            this.openLinkId = null;
            this.dropdownUp = false;
        },
        isDropdownOpen(link) {
            return this.openLinkId === link.id;
        },
        filteredPlatforms(link) {
            const query = String(link.platform || '').trim();
            if (!query) {
                return this.platformOptions;
            }

            const matches = this.platformOptions.filter((option) => matchesPlatform(option, query));
            const exact = this.platformOptions.find((option) => option.toLowerCase() === query.toLowerCase());
            if (exact && !matches.includes(exact)) {
                matches.unshift(exact);
            }

            return matches;
        },
        syncDropdownPlacement(linkId) {
            const root = this.$el?.querySelector(`[data-link-id="${linkId}"]`);
            if (!root) {
                return;
            }

            const rect = root.getBoundingClientRect();
            const menuHeight = 208;
            const viewportHeight = globalThis.innerHeight ?? 0;
            const spaceBelow = viewportHeight - rect.bottom;
            const spaceAbove = rect.top;
            this.dropdownUp = spaceBelow < menuHeight && spaceAbove > spaceBelow;
        },
        openDropdown(link) {
            this.openLinkId = link.id;
            this.$nextTick(() => this.syncDropdownPlacement(link.id));
        },
        toggleDropdown(link) {
            if (this.isDropdownOpen(link)) {
                this.closeDropdown();
                return;
            }
            this.openDropdown(link);
        },
        onPlatformFocus(link) {
            this.openDropdown(link);
        },
        onPlatformInput(link) {
            this.openLinkId = link.id;
            this.$nextTick(() => this.syncDropdownPlacement(link.id));
            this.persist();
        },
        selectPlatform(link, platform) {
            link.platform = platform;
            this.closeDropdown();
            this.persist();
        },
    };
}
