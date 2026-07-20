<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Lang;

class CvBuilderController extends PanelController
{
    public function show(Request $request, CareerTalentApiClient $api)
    {
        $analysisResult = $api->currentCareerAnalysis();
        $profileResult = $api->careerProfile();
        $profile = ($profileResult['ok'] ?? false) && is_array($profileResult['body'] ?? null) ? $profileResult['body'] : [];
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $hasCvAnalysis = ($analysis['status'] ?? null) === 'ready';

        $cvDraft = $this->blankCvDraft($profile);
        $restoredFromHistory = false;
        if ($request->filled('cvDocument')) {
            $document = $api->cvDocument((string) $request->query('cvDocument'));
            $snapshot = ($document['ok'] ?? false) ? ($document['body']['builder_data'] ?? null) : null;
            if (is_array($snapshot) && isset($snapshot['tr'], $snapshot['en'])) {
                $cvDraft = $snapshot;
                $restoredFromHistory = true;
            }
        }

        return $this->panelView('app.cv-builder', [
            'cvDraft' => $cvDraft,
            'restoredFromHistory' => $restoredFromHistory,
            'cvLabels' => $this->cvLabelsForJs(),
            'skillRadar' => $this->skillRadar($analysis),
            'hasCvAnalysis' => $hasCvAnalysis,
            'cvFileName' => '',
        ]);
    }

    /** @param array<string, mixed> $profile */
    private function blankCvDraft(array $profile): array
    {
        $personal = [
            'full_name' => (string) ($profile['full_name'] ?? ''), 'email' => (string) ($profile['email'] ?? ''),
            'phone' => (string) ($profile['phone'] ?? ''), 'location' => (string) ($profile['location'] ?? ''),
            'linkedin' => (string) ($profile['linkedin'] ?? ''), 'summary' => '',
        ];
        $locale = static fn (): array => [
            'personal' => $personal, 'education' => [], 'experience' => [], 'skills' => [],
            'projects' => [], 'certificates' => [], 'enabledOptional' => [], 'optional' => [],
        ];
        return ['tr' => $locale(), 'en' => $locale()];
    }

    private function skillRadar(array $analysis): array
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
        return [
            'skills' => $skills,
            'target_role' => (string) ($analysis['current_role'] ?? ''),
            'analyzed_at' => (string) ($analysis['created_at'] ?? ''),
            'analysis_id' => (string) ($analysis['id'] ?? ''),
            'file_name' => (string) ($analysis['file_name'] ?? 'cv'),
            'source' => (string) ($analysis['source'] ?? ''),
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
}
