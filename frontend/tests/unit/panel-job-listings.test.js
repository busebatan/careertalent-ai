import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { panelJobListings } from '../../resources/js/panel-job-listings.js';

const jobs = [
    {
        is_demo: true,
        organization: { name: 'Demo Kurum' },
        position: { title: 'Junior Veri Analisti', workplace_type: 'hybrid', employment_type: 'full_time', location: 'İstanbul' },
    },
    {
        is_demo: false,
        organization: { name: 'ACME Teknoloji' },
        position: { title: 'Backend Developer', workplace_type: 'remote', employment_type: 'contract', location: 'Ankara', public_path: '/apply/acme/backend-ABC' },
    },
];

describe('panelJobListings', () => {
    it('filters by search, workplace and employment type together', () => {
        const state = panelJobListings(jobs, {});
        state.query = 'acme';
        state.workplace = 'remote';
        state.employment = 'contract';

        assert.deepEqual(state.filteredItems.map((item) => item.position.title), ['Backend Developer']);
    });

    it('keeps demo application non-persistent and returns real public application path', () => {
        const state = panelJobListings(jobs, {});

        assert.equal(state.applicationPath(jobs[0]), null);
        assert.equal(state.demoNotice, true);
        assert.equal(state.applicationPath(jobs[1]), '/apply/acme/backend-ABC');
        assert.equal(state.demoNotice, false);
    });
});
