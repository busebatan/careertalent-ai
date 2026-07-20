<?php

namespace App\Http\Controllers\App;

use App\Services\BuilderCvTextExporter;
use App\Services\CareerTalentApiClient;
use App\Support\PortalAuthSession;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CvUploadController extends PanelController
{
    public function status(string $analysisId, CareerTalentApiClient $api): JsonResponse
    {
        $result = $api->careerAnalysis($analysisId);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? __('panel.profile.cv_analyze_failed'),
            ], $result['status'] ?? 502);
        }

        $body = $result['body'] ?? [];
        return response()->json($body);
    }

    public function stream(string $analysisId, CareerTalentApiClient $api): StreamedResponse
    {
        $upstream = $api->careerAnalysisStreamUrl($analysisId);
        $token = PortalAuthSession::token(request());

        return response()->stream(function () use ($upstream, $token): void {
            $handle = curl_init($upstream);
            if ($handle === false) {
                echo "event: error\ndata: {\"message\":\"CV analiz akışı başlatılamadı\"}\n\n";
                return;
            }

            $headers = ['Accept: text/event-stream'];
            if ($token !== null) {
                $headers[] = 'Authorization: Bearer '.$token;
            }

            curl_setopt_array($handle, [
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => false,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 200,
                CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk): int {
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();

                    return strlen($chunk);
                },
            ]);

            curl_exec($handle);
            curl_close($handle);
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function analyze(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $request->validate([
            'cv' => ['required', 'file', 'mimes:pdf', 'max:10240'],
        ]);

        $file = $request->file('cv');
        $result = $api->analyzeCv($file);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? __('panel.profile.cv_analyze_failed'),
            ], $result['status'] ?? 502);
        }

        $body = $result['body'] ?? [];
        return response()->json([
            'status' => $body['status'] ?? 'queued',
            'analysis_id' => $body['analysis_id'] ?? null,
            'file_name' => $file->getClientOriginalName(),
            'skill_radar' => $body['skill_radar'] ?? null,
            'career_ladder' => $body['career_ladder'] ?? [],
            'redirect' => ($body['status'] ?? null) === 'ready' ? route('panel.career-ladder') : null,
        ], ($body['status'] ?? null) === 'queued' ? 202 : 200);
    }

    public function analyzeBuilder(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'locales' => ['required', 'array'],
            'locale' => ['nullable', 'string', 'in:tr,en'],
        ]);

        $locale = $validated['locale'] ?? app()->getLocale();
        $locales = $validated['locales'];
        if (! BuilderCvTextExporter::hasCareerContent($locales, $locale)) {
            return response()->json([
                'message' => __('panel.cv_builder.analyze_too_short'),
            ], 422);
        }
        $cvText = BuilderCvTextExporter::toText($locales, $locale);

        if (strlen($cvText) < 40) {
            return response()->json([
                'message' => __('panel.cv_builder.analyze_too_short'),
            ], 422);
        }

        $fileName = BuilderCvTextExporter::fileName($locales, $locale);
        $result = $api->analyzeCvTextQueued([
            'cv_text' => $cvText,
            'file_name' => $fileName,
        ]);

        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? __('panel.profile.cv_analyze_failed'),
            ], $result['status'] ?? 502);
        }

        $body = $result['body'] ?? [];
        return response()->json([
            'status' => $body['status'] ?? 'queued',
            'analysis_id' => $body['analysis_id'] ?? null,
            'file_name' => $fileName,
            'skill_radar' => $body['skill_radar'] ?? null,
            'career_ladder' => $body['career_ladder'] ?? [],
            'redirect' => ($body['status'] ?? null) === 'ready' ? route('panel.cv-builder') : null,
        ], ($body['status'] ?? null) === 'queued' ? 202 : 200);
    }

    public function clear(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'scope' => ['required', 'string', 'in:analysis,plan,all'],
        ]);
        $result = $api->resetCareer($validated['scope']);
        if (! ($result['ok'] ?? false)) {
            return response()->json([
                'message' => $result['error'] ?? __('panel.skill_radar.reset_failed'),
            ], $result['status'] ?? 502);
        }

        return response()->json($result['body'] ?? ['status' => 'cleared']);
    }

    public function archiveGeneratedPdf(Request $request, CareerTalentApiClient $api): JsonResponse
    {
        $validated = $request->validate([
            'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'display_name' => ['required', 'string', 'max:250'],
            'language' => ['required', 'in:tr,en'],
            'builder_data' => ['required', 'json', 'max:1000000'],
        ]);
        $result = $api->archiveGeneratedCv($request->file('pdf'), $validated['display_name'], $validated['language'], $validated['builder_data']);
        return ($result['ok'] ?? false) ? response()->json($result['body'], 201) : response()->json(['message' => $result['error'] ?? 'CV arşivlenemedi'], $result['status'] ?? 502);
    }

}
