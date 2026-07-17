<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobMatchesController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        $jobs = $api->careerJobs();
        $analysis = $api->currentCareerAnalysis();
        $skills = collect($analysis['body']['skills'] ?? [])->pluck('name')->filter()->values()->all();
        $radar = collect($analysis['body']['radar'] ?? []);

        return $this->panelView('app.job-matches', [
            'seedJobs' => $jobs['ok'] && is_array($jobs['body']) ? $jobs['body'] : [],
            'userSkills' => $skills,
            'readiness' => (int) round((float) ($radar->avg('score') ?? 0)),
        ]);
    }

    public function analyze(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $payload = $request->validate([
            'source_url' => ['nullable', 'url:http,https', 'max:2048', 'required_without:job_text'],
            'job_text' => ['nullable', 'string', 'min:40', 'max:30000', 'required_without:source_url'],
        ]);
        return $this->apiResponse($api->analyzeCareerJob($payload));
    }

    public function status(string $jobId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiResponse($api->careerJob($jobId));
    }

    public function save(string $jobId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiResponse($api->saveCareerJob($jobId));
    }

    public function markApplied(string $jobId, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->markCareerJobApplied($jobId);
        return ($result['ok'] ?? false)
            ? response()->json($result['body'] ?? [], $result['status'] ?: 200)
            : response()->json(['message' => $result['error'] ?? 'Başvuru kaydedilemedi'], $result['status'] ?? 502);
    }

    public function apply(string $jobId, Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $payload = $request->validate(['suggestion_ids' => ['required', 'array', 'min:1', 'max:20'], 'suggestion_ids.*' => ['string', 'max:36']]);
        return $this->apiResponse($api->applyCareerJobSuggestions($jobId, $payload['suggestion_ids']));
    }

    public function destroy(string $jobId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiResponse($api->deleteCareerJob($jobId));
    }

    private function apiResponse(array $result): JsonResponse
    {
        return response()->json($result['ok'] ? $result['body'] : ['message' => $result['error'] ?? __('panel.job_matches.error_generic')], $result['ok'] ? ($result['status'] ?? 200) : ($result['status'] ?? 502));
    }
}
