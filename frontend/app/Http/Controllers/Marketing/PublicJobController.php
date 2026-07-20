<?php

namespace App\Http\Controllers\Marketing;

use App\Http\Controllers\Controller;
use App\Services\CareerTalentApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicJobController extends Controller
{
    public function show(Request $request, string $organizationSlug, string $positionPath, CareerTalentApiClient $api): View|RedirectResponse
    {
        $source = $this->source($request);
        $result = $api->publicJob($organizationSlug, $positionPath);
        abort_unless($result['ok'], $result['status'] === 404 ? 404 : 503, $result['error']);
        $canonicalPath = basename((string) data_get($result, 'body.position.public_path', $positionPath));
        if ($canonicalPath !== $positionPath) {
            return redirect()->route('public.jobs.show', array_filter([
                'organizationSlug' => (string) data_get($result, 'body.organization.slug', $organizationSlug),
                'positionPath' => $canonicalPath,
                'source' => $source,
            ]), 301);
        }

        return view('marketing.jobs.show', [
            'job' => $result['body'],
            'source' => $source,
        ]);
    }

    public function start(Request $request, string $organizationSlug, string $positionPath, CareerTalentApiClient $api): View
    {
        $source = $this->source($request);
        $job = $api->publicJob($organizationSlug, $positionPath);
        abort_unless($job['ok'], $job['status'] === 404 ? 404 : 503, $job['error']);

        $documents = $api->cvDocuments();

        return view('marketing.jobs.review', [
            'job' => $job['body'],
            'source' => $source,
            'documents' => $documents['ok'] && is_array($documents['body']) ? $documents['body'] : [],
            'documentsError' => $documents['ok'] ? null : $documents['error'],
            'candidate' => $request->attributes->get('auth.user', []),
        ]);
    }

    public function submit(Request $request, string $organizationSlug, string $positionPath, CareerTalentApiClient $api): View|RedirectResponse
    {
        $payload = $request->validate([
            'cv_document_id' => ['required', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:32', 'regex:/^[A-Za-z0-9-]+$/'],
            'consent' => ['accepted'],
        ]);
        $job = $api->publicJob($organizationSlug, $positionPath);
        if (! $job['ok']) {
            return back()->withInput()->withErrors(['application' => $job['error']]);
        }
        $publicId = data_get($job, 'body.position.public_id');
        if (! is_string($publicId) || $publicId === '') {
            return back()->withInput()->withErrors(['application' => __('company_positions.public.unavailable')]);
        }
        $result = $api->submitPublicJobApplication($publicId, [
            'cv_document_id' => $payload['cv_document_id'],
            'share_link_code' => $payload['source'] ?? null,
            'consent' => ['accepted' => true, 'version' => '2026-07-20'],
            'selected_projects' => [],
        ]);

        if (! $result['ok']) {
            return back()->withInput()->withErrors(['application' => $result['error']]);
        }

        return view('marketing.jobs.submitted', [
            'application' => $result['body'],
            'alreadyExists' => ! (bool) ($result['body']['created'] ?? false),
        ]);
    }

    public function shortLink(string $shortCode, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->resolvePublicJobShareLink($shortCode);
        abort_unless($result['ok'], $result['status'] === 404 ? 404 : 503, $result['error']);

        $organization = data_get($result, 'body.organization.slug');
        $positionPath = basename((string) data_get($result, 'body.position.public_path', ''));
        $source = data_get($result, 'body.source.short_code', $shortCode);
        abort_unless(is_string($organization) && $organization !== '' && $positionPath !== '', 502);
        $target = route('public.jobs.show', [
            'organizationSlug' => $organization,
            'positionPath' => $positionPath,
            'source' => $source,
        ], false);

        return redirect()->to($target);
    }

    private function source(Request $request): ?string
    {
        $source = $request->query('source');

        return is_string($source) && preg_match('/^[A-Za-z0-9-]{3,32}$/', $source) === 1
            ? $source
            : null;
    }
}
