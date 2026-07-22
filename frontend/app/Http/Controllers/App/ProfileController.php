<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProfileController extends PanelController
{
    public function account(CareerTalentApiClient $api)
    {
        return $this->accountView($api, 'profil');
    }

    public function update(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'full_name' => ['required', 'string', 'min:2', 'max:100'],
            'phone' => ['nullable', 'string', 'max:40'], 'location' => ['nullable', 'string', 'max:160'],
            'headline' => ['nullable', 'string', 'max:240'], 'linkedin' => ['nullable', 'url', 'max:2048'],
            'social_links' => ['array', 'max:12'], 'social_links.*.platform' => ['required', 'string', 'max:80'],
            'social_links.*.url' => ['required', 'url', 'max:2048'],
        ]);
        $result = $api->updateCareerProfile($validated);

        return ($result['ok'] ?? false) ? response()->json($result['body']) : response()->json(['message' => $result['error']], $result['status'] ?? 502);
    }

    private function accountView(CareerTalentApiClient $api, string $initialTab)
    {
        $result = $api->careerProfile();
        $defaults = [
            'full_name' => session('auth.user.full_name', ''), 'email' => session('auth.user.email', ''),
            'phone' => '', 'location' => '', 'headline' => '', 'linkedin' => '', 'social_links' => [],
            'uploaded_cv' => ['name' => null, 'uploaded_at' => null],
        ];
        $profile = array_replace($defaults, ($result['ok'] ?? false) && is_array($result['body'] ?? null) ? $result['body'] : []);
        $documentsResult = $api->cvDocuments();
        $documents = ($documentsResult['ok'] ?? false) && is_array($documentsResult['body'] ?? null) ? $documentsResult['body'] : [];
        $cvHistory = array_values(array_filter($documents, fn ($item) => is_array($item)));
        $analysisResult = $api->currentCareerAnalysis();
        $analysis = ($analysisResult['ok'] ?? false) && is_array($analysisResult['body'] ?? null) ? $analysisResult['body'] : [];
        $hasReadyHistoryAnalysis = ($analysis['status'] ?? null) === 'ready'
            && in_array(($analysis['source'] ?? null), ['archive_uploaded', 'archive_generated'], true);

        return $this->panelView('app.account', [
            'profile' => $profile,
            'cvHistory' => $cvHistory,
            'hasReadyHistoryAnalysis' => $hasReadyHistoryAnalysis,
            'initialTab' => $initialTab,
            'profileError' => ($result['ok'] ?? false) ? null : $result['error'],
        ]);
    }

    public function archiveCurrent(string $documentId, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->archiveCurrentCv($documentId);

        return ($result['ok'] ?? false)
            ? $this->cvTabRedirect()->with('cv_status', __('panel.profile.cv_archived'))
            : $this->cvTabRedirect()->withErrors(['cv' => $result['error'] ?? 'CV arşivlenemedi']);
    }

    public function destroyCv(string $documentId, CareerTalentApiClient $api): RedirectResponse
    {
        $result = $api->deleteCvDocument($documentId);

        return ($result['ok'] ?? false)
            ? $this->cvTabRedirect()->with('cv_status', __('panel.profile.cv_deleted'))
            : $this->cvTabRedirect()->withErrors(['cv' => $result['error'] ?? 'CV silinemedi']);
    }

    public function analyzeCv(string $documentId, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->analyzeCvDocument($documentId);

        return ($result['ok'] ?? false)
            ? response()->json($result['body'], $result['status'] ?? 202)
            : response()->json(['message' => $result['error'] ?? __('panel.profile.cv_analyze_failed')], $result['status'] ?? 502);
    }

    public function downloadCv(string $documentId, CareerTalentApiClient $api): Response
    {
        $result = $api->downloadCvDocument($documentId);
        abort_unless($result['ok'] ?? false, $result['status'] ?? 404);

        return response($result['content'], 200, [
            'Content-Type' => $result['content_type'] ?: 'application/pdf',
            'Content-Disposition' => $result['content_disposition'] ?: 'attachment; filename="cv.pdf"',
        ]);
    }

    private function cvTabRedirect(): RedirectResponse
    {
        return redirect()->to(route('panel.account').'#cv-yukle');
    }
}
