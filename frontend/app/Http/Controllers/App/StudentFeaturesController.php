<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;
use App\Services\SkillPassportBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudentFeaturesController extends PanelController
{
    public function skillPassport(CareerTalentApiClient $api, SkillPassportBuilder $builder)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $target = PanelTargetRoleStore::get();
        $tasks = [];
        $taskError = null;

        if (is_array($target) && ! empty($target['id'])) {
            $result = $api->careerTargetTasks((string) $target['id']);
            $taskBody = $result['body'] ?? [];
            $tasks = is_array($taskBody) && array_is_list($taskBody) ? $taskBody : ($taskBody['tasks'] ?? []);
            $taskError = ($result['ok'] ?? false) ? null : ($result['error'] ?? null);
        }

        return $this->panelView('app.skill-passport', [
            'passport' => $builder->build($analysis, is_array($tasks) ? $tasks : []),
            'selectedTarget' => $target,
            'careerEngineError' => $taskError,
        ]);
    }

    public function submitSkillEvidence(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'skill' => ['required', 'string', 'min:1', 'max:120'],
            'target_id' => ['required', 'string', 'max:36'],
            'kind' => ['required', 'in:link,file'],
            'url' => ['nullable', 'url', 'max:2048'],
            'evidence_file' => ['nullable', 'file', 'max:10240'],
        ]);

        if ($validated['kind'] === 'file') {
            $file = $request->file('evidence_file');
            abort_unless($file, 422, 'Kanıt dosyası gerekli.');
            $result = $api->submitSkillEvidenceFile($validated['skill'], $validated['target_id'], $file);
        } else {
            $result = $api->submitSkillEvidenceLink($validated['skill'], $validated['target_id'], (string) ($validated['url'] ?? ''));
        }

        return $this->apiJson($result);
    }

    public function clearSkillEvidence(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'skill' => ['required', 'string', 'min:1', 'max:120'],
            'target_id' => ['required', 'string', 'max:36'],
        ]);

        return $this->apiJson($api->clearSkillEvidence($validated['skill'], $validated['target_id']));
    }

    public function interview(CareerTalentApiClient $api)
    {
        $historyResult = $api->interviewHistory();
        $historyBody = $historyResult['body'] ?? [];

        return $this->panelView('app.interview', [
            'interviewHistory' => ($historyResult['ok'] ?? false) && is_array($historyBody)
                ? ($historyBody['items'] ?? [])
                : [],
            'interviewError' => ($historyResult['ok'] ?? false) ? null : $historyResult['error'],
        ]);
    }

    public function applications(CareerTalentApiClient $api)
    {
        $result = $api->careerApplications();

        return $this->panelView('app.applications', [
            'applications' => ($result['ok'] ?? false) && is_array($result['body'] ?? null) ? $result['body'] : [],
            'applicationsError' => ($result['ok'] ?? false) ? null : $result['error'],
        ]);
    }

    public function createApplication(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'company'            => ['nullable', 'string', 'max:160'],
            'role'               => ['nullable', 'string', 'max:200'],
            'next_action'        => ['nullable', 'string', 'max:300'],
            // Platform başvurusu için ek alanlar
            'position_id'        => ['nullable', 'string', 'max:80'],
            'cv_version_id'      => ['nullable', 'string', 'max:36'],
            'cv_document_id'     => ['nullable', 'string', 'max:64'],
            'is_platform_apply'  => ['sometimes', 'boolean'],
        ]);

        $payload = [
            'company'        => $validated['company'] ?? 'Kurum',
            'role'           => $validated['role'] ?? 'Pozisyon',
            'next_action'    => $validated['next_action'] ?? null,
            'position_id'    => $validated['position_id'] ?? null,
            'cv_version_id'  => $validated['cv_version_id'] ?? null,
            'is_platform_apply' => (bool) ($validated['is_platform_apply'] ?? false),
        ];

        return $this->apiJson($api->createCareerApplication($payload));
    }


    public function updateApplication(Request $request, string $applicationId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'stage' => ['nullable', 'in:applied,interview,offer,rejected'],
            'next_action' => ['nullable', 'string', 'max:300'], 'note' => ['nullable', 'string', 'max:4000'],
        ]);
        return $this->apiJson($api->updateCareerApplication($applicationId, $validated));
    }

    public function startInterview(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'language' => ['nullable', 'string', 'in:tr,en'],
        ]);

        return $this->apiJson($api->startInterview($validated['language'] ?? 'tr'));
    }

    public function scoreInterview(Request $request, string $interviewId, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate(['question_id' => ['required', 'string', 'max:80'], 'answer' => ['required', 'string', 'min:20', 'max:8000']]);
        return $this->apiJson($api->scoreInterviewAnswer($interviewId, $validated));
    }

    public function interviewHistory(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'offset' => ['nullable', 'integer', 'min:0'],
        ]);

        return $this->apiJson($api->interviewHistory(
            (int) ($validated['limit'] ?? 20),
            (int) ($validated['offset'] ?? 0),
        ));
    }

    public function interviewDetail(string $interviewId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->interviewDetail($interviewId));
    }

    public function retryInterview(string $interviewId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiJson($api->retryInterview($interviewId));
    }

    private function apiJson(array $result): JsonResponse
    {
        return ($result['ok'] ?? false)
            ? response()->json($result['body'] ?? [], $result['status'] ?: 200)
            : response()->json(['message' => $result['error'] ?? 'İşlem tamamlanamadı'], $result['status'] ?? 502);
    }

}
