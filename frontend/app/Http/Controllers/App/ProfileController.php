<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
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

    public function destroyCvs(Request $request, CareerTalentApiClient $api): RedirectResponse
    {
        $validator = Validator::make($request->all(), [
            'document_ids' => ['required', 'array', 'min:1', 'max:50'],
            'document_ids.*' => ['required', 'string', 'distinct', 'max:64'],
        ]);
        if ($validator->fails()) {
            return $this->cvTabRedirect()->withErrors($validator)->withInput();
        }

        $documentIds = $validator->validated()['document_ids'];

        $deleted = 0;
        $failed = 0;

        foreach ($documentIds as $documentId) {
            $result = $api->deleteCvDocument($documentId);
            if ($result['ok'] ?? false) {
                $deleted++;
            } else {
                $failed++;
            }
        }

        $redirect = $this->cvTabRedirect();

        if ($deleted > 0) {
            $redirect->with('cv_status', __('panel.profile.cv_bulk_deleted', ['count' => $deleted]));
        }

        if ($failed > 0) {
            $message = $deleted > 0
                ? __('panel.profile.cv_bulk_partial', ['deleted' => $deleted, 'failed' => $failed])
                : __('panel.profile.cv_bulk_failed');

            $redirect->withErrors(['cv' => $message]);
        }

        return $redirect;
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

    public function previewCv(string $documentId, CareerTalentApiClient $api): Response
    {
        $result = $api->downloadCvDocument($documentId);
        abort_unless($result['ok'] ?? false, $result['status'] ?? 404);

        $content = (string) ($result['content'] ?? '');
        $contentType = strtolower((string) ($result['content_type'] ?? ''));
        $hasPdfSignature = str_contains(substr($content, 0, 1024), '%PDF-');
        abort_unless(str_contains($contentType, 'application/pdf') && $hasPdfSignature, 415);

        return response($content, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="cv.pdf"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function cvTabRedirect(): RedirectResponse
    {
        return redirect()->to(route('panel.account').'#cv-yukle');
    }
}
