<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;

class JobListingsController extends PanelController
{
    public function index(CareerTalentApiClient $api)
    {
        $result = $api->publicPositions(['limit' => 100, 'offset' => 0]);
        $realItems = $result['ok'] && is_array(data_get($result, 'body.items'))
            ? collect(data_get($result, 'body.items'))
                ->filter(fn ($item) => is_array($item) && is_array(data_get($item, 'position')))
                ->map(fn (array $item) => array_merge($item, ['is_demo' => false]))
                ->values()
                ->all()
            : [];

        return $this->panelView('app.job-listings', [
            'jobListings' => array_merge([$this->demoListing()], $realItems),
            'listingsError' => $result['ok'] ? null : ($result['error'] ?? __('panel.job_listings.load_error')),
        ]);
    }

    /** @return array<string, mixed> */
    private function demoListing(): array
    {
        return [
            'is_demo' => true,
            'organization' => [
                'name' => __('panel.job_listings.demo_organization'),
                'slug' => 'demo-kurum',
                'website' => null,
                'logo_url' => null,
            ],
            'position' => [
                'id' => 'demo-position',
                'public_id' => 'DEMO',
                'public_path' => null,
                'title' => __('panel.job_listings.demo_title'),
                'department' => __('panel.job_listings.demo_department'),
                'level' => 'junior',
                'employment_type' => 'full_time',
                'workplace_type' => 'hybrid',
                'location' => __('panel.job_listings.demo_location'),
                'description' => __('panel.job_listings.demo_description'),
                'responsibilities' => __('panel.job_listings.demo_responsibilities'),
                'must_have_skills' => ['Excel', 'SQL', 'Power BI'],
                'preferred_skills' => ['Python'],
                'application_deadline' => null,
                'status' => 'published',
                'application_open' => true,
                'estimated_application_minutes' => 8,
                'estimated_assessment_minutes' => null,
            ],
            'source' => null,
        ];
    }
}
