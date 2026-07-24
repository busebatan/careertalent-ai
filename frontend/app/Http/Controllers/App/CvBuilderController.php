<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class CvBuilderController extends PanelController
{
    public function show(Request $request, CareerTalentApiClient $api)
    {
        $analysisResult = $api->latestCareerAnalysis();
        $profileResult = $api->careerProfile();
        $profile = ($profileResult['ok'] ?? false) && is_array($profileResult['body'] ?? null) ? $profileResult['body'] : [];
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $hasCvAnalysis = ($analysis['status'] ?? null) === 'ready';
        $documentsResult = $api->cvDocuments();
        $documents = ($documentsResult['ok'] ?? false) && is_array($documentsResult['body'] ?? null) ? $documentsResult['body'] : [];
        $currentCv = collect($documents)->first(
            fn ($item) => is_array($item) && ($item['is_current'] ?? false)
        );
        $versionsResult = $api->cvVersions();
        $versions = ($versionsResult['ok'] ?? false) && is_array($versionsResult['body'] ?? null) ? $versionsResult['body'] : [];

        $cvDraft = $this->blankCvDraft($profile);
        $restoredFromHistory = false;
        $builderImportMeta = [];
        $builderImportMissingFields = [];
        $builderImportDocumentId = '';
        $builderImportDocument = null;
        $activeBuilderVersionId = '';
        if ($request->filled('cvDocument')) {
            $document = $api->cvDocument((string) $request->query('cvDocument'));
            $builderImportDocument = ($document['ok'] ?? false) && is_array($document['body'] ?? null)
                ? $document['body']
                : null;
            $snapshot = is_array($builderImportDocument) ? ($builderImportDocument['builder_data'] ?? null) : null;
            if (is_array($snapshot) && isset($snapshot['tr'], $snapshot['en'])) {
                unset($snapshot['_meta']);
                $cvDraft = $snapshot;
                $restoredFromHistory = true;
            }
        } elseif ($request->filled('cvVersion')) {
            $version = collect($versions)->first(
                fn ($item) => is_array($item) && (string) ($item['id'] ?? '') === (string) $request->query('cvVersion')
            );
            $language = is_array($version) ? (string) ($version['language'] ?? '') : '';
            $payload = is_array($version) ? ($version['payload'] ?? null) : null;
            if (in_array($language, ['tr', 'en'], true) && is_array($payload)) {
                $cvDraft[$language] = $payload;
                $restoredFromHistory = true;
                $activeBuilderVersionId = (string) ($version['id'] ?? '');
            }
        }

        if (! is_array($builderImportDocument)) {
            $noticeVersion = $request->filled('cvVersion')
                ? ($version ?? null)
                : collect($versions)->first(
                    fn ($item) => is_array($item) && ($item['is_main'] ?? false) === true
                );
            $noticeDocumentId = is_array($noticeVersion)
                ? trim((string) ($noticeVersion['source_document_id'] ?? ''))
                : '';
            if ($noticeDocumentId !== '') {
                $document = $api->cvDocument($noticeDocumentId);
                $builderImportDocument = ($document['ok'] ?? false) && is_array($document['body'] ?? null)
                    ? $document['body']
                    : null;
            }
        }

        if (is_array($builderImportDocument)
            && ! ($builderImportDocument['builder_import_notice_dismissed'] ?? false)) {
            $snapshot = $builderImportDocument['builder_data'] ?? null;
            $builderImportMeta = is_array($snapshot) && is_array($snapshot['_meta'] ?? null)
                ? $snapshot['_meta']
                : [];
            if ($builderImportMeta !== []) {
                $builderImportDocumentId = trim((string) ($builderImportDocument['id'] ?? ''));
                $missingByLocale = is_array($builderImportMeta['missing_fields'] ?? null)
                    ? $builderImportMeta['missing_fields']
                    : [];
                $builderImportMissingFields = collect($missingByLocale)
                    ->filter(fn ($items) => is_array($items))
                    ->flatten()
                    ->filter(fn ($item) => is_string($item) && $item !== '')
                    ->unique()
                    ->values()
                    ->all();
            }
        }

        return $this->panelView('app.cv-builder', [
            'cvDraft' => $cvDraft,
            'restoredFromHistory' => $restoredFromHistory,
            'builderImportMeta' => $builderImportMeta,
            'builderImportMissingFields' => $builderImportMissingFields,
            'builderImportDocumentId' => $builderImportDocumentId,
            'builderDocumentId' => is_array($builderImportDocument)
                ? (string) ($builderImportDocument['id'] ?? '')
                : '',
            'activeBuilderVersionId' => $activeBuilderVersionId,
            'cvLabels' => $this->cvLabelsForJs(),
            'skillRadar' => $this->skillRadar(
                $analysis,
                is_array($currentCv) ? (string) ($currentCv['display_name'] ?? '') : '',
                is_array($currentCv) ? (string) ($currentCv['kind'] ?? '') : '',
            ),
            'hasCvAnalysis' => $hasCvAnalysis,
            'analysisStatus' => (string) ($analysis['status'] ?? ''),
            'analysisId' => (string) ($analysis['id'] ?? ''),
            'cvFileName' => is_array($currentCv) ? (string) ($currentCv['display_name'] ?? '') : '',
            'currentCv' => $currentCv,
        ]);
    }

    /** @param array<string, mixed> $profile */
    private function blankCvDraft(array $profile): array
    {
        $emptyLocale = [
            'personal' => [
                'full_name' => '',
                'email' => '',
                'phone' => '',
                'location' => '',
                'linkedin' => '',
                'summary' => '',
            ],
            'education' => [],
            'experience' => [],
            'skills' => [],
            'projects' => [],
            'certificates' => [],
            'enabledOptional' => [],
            'optional' => [],
        ];
        $draft = [
            'tr' => $emptyLocale,
            'en' => $emptyLocale,
        ];
        $profilePersonal = [
            'full_name' => (string) ($profile['full_name'] ?? ''),
            'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''),
            'location' => (string) ($profile['location'] ?? ''),
            'linkedin' => (string) ($profile['linkedin'] ?? ''),
        ];

        foreach (['tr', 'en'] as $locale) {
            foreach ($profilePersonal as $key => $value) {
                if ($value !== '') {
                    $draft[$locale]['personal'][$key] = $value;
                }
            }
        }

        return $draft;
    }

    private function skillRadar(array $analysis, string $fallbackFileName = '', string $documentKind = ''): array
    {
        $skills = array_values(array_filter(array_map(static function ($item): ?array {
            if (! is_array($item) || ! isset($item['label'])) {
                return null;
            }
            return ['label' => (string) $item['label'], 'score' => (int) ($item['score'] ?? 0), 'target' => (int) ($item['target'] ?? 0)];
        }, is_array($analysis['radar'] ?? null) ? $analysis['radar'] : [])));
        if ($skills === []) {
            return [];
        }
        $fileName = trim((string) ($analysis['file_name'] ?? '')) ?: $fallbackFileName ?: 'cv';
        $source = trim((string) ($analysis['source'] ?? ''));
        if ($source === '') {
            $source = match ($documentKind) {
                'uploaded' => 'upload',
                'generated' => 'text',
                default => '',
            };
        }

        return [
            'skills' => $skills,
            'target_role' => (string) ($analysis['current_role'] ?? ''),
            'analyzed_at' => (string) ($analysis['created_at'] ?? ''),
            'analysis_id' => (string) ($analysis['id'] ?? ''),
            'file_name' => $fileName,
            'source' => $source,
            'cv_document_id' => (string) ($analysis['cv_document_id'] ?? ''),
            'overall_match' => (int) round(array_sum(array_column($skills, 'score')) / count($skills)),
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function cvLabelsForJs(): array
    {
        $labels = [];

        foreach (['tr', 'en'] as $locale) {
            $labels[$locale] = Lang::get('panel.cv_builder', [], $locale);
        }

        return $labels;
    }

    public function listVersions(CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->cvVersions();
        return $this->apiResponse($result);
    }

    public function createVersion(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $payload = $request->validate([
            'version_name' => ['required', 'string', 'max:160'],
            'language' => ['required', 'in:tr,en'],
            'is_main' => ['required', 'boolean'],
            'payload' => ['required', 'array'],
        ]);
        $result = $api->createCvVersion($payload);
        return $this->apiResponse($result);
    }

    public function updateVersion(Request $request, string $id, CareerTalentApiClient $api): JsonResponse
    {
        $payload = $request->validate([
            'version_name' => ['sometimes', 'string', 'max:160'],
            'language' => ['sometimes', 'in:tr,en'],
            'is_main' => ['sometimes', 'boolean'],
            'payload' => ['sometimes', 'array'],
        ]);
        $result = $api->updateCvVersion($id, $payload);
        return $this->apiResponse($result);
    }

    public function deleteVersion(string $id, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->deleteCvVersion($id);
        return $this->apiResponse($result);
    }

    public function builderDraftStatus(string $documentId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiResponse($api->cvDocument($documentId));
    }

    public function queueBuilderDraft(string $documentId, CareerTalentApiClient $api): JsonResponse
    {
        return $this->apiResponse($api->queueCvBuilderDraft($documentId));
    }

    public function activateBuilderDraft(
        Request $request,
        string $documentId,
        CareerTalentApiClient $api,
    ): RedirectResponse {
        $payload = $request->validate([
            'language' => ['required', 'in:tr,en'],
        ]);
        $result = $api->activateCvBuilderDraft($documentId, $payload['language']);
        if (! ($result['ok'] ?? false)) {
            return back()->withErrors([
                'cv' => $result['error'] ?? __('panel.profile.cv_builder_import_failed'),
            ]);
        }

        return redirect()->route('panel.cv-builder', ['cvDocument' => $documentId]);
    }

    public function dismissBuilderImportNotice(
        string $documentId,
        CareerTalentApiClient $api,
    ): JsonResponse {
        return $this->apiResponse($api->dismissCvBuilderImportNotice($documentId));
    }

    private function apiResponse(array $result): JsonResponse
    {
        return response()->json(
            $result['ok'] ? $result['body'] : ['message' => $result['error'] ?? __('panel.job_matches.error_generic')],
            $result['ok'] ? ($result['status'] ?? 200) : ($result['status'] ?? 502)
        );
    }
}
