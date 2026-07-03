<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Data\PanelJobMatchDemoData;
use App\Services\JobMatchAnalyzer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobMatchesController extends PanelController
{
    public function show()
    {
        $analyzer = $this->analyzer();

        return $this->panelView('app.job-matches', [
            'seedJobs' => array_map(
                fn (string $url) => $analyzer->analyze($url),
                PanelJobMatchDemoData::seedUrls(),
            ),
            'userSkills' => PanelJobMatchDemoData::userSkills(),
            'readiness' => PanelDemoData::stats()['readiness'],
        ]);
    }

    public function analyze(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
        ]);

        try {
            $result = $this->analyzer()->analyze($validated['url']);
        } catch (\InvalidArgumentException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }

        return response()->json(['job' => $result]);
    }

    private function analyzer(): JobMatchAnalyzer
    {
        return new JobMatchAnalyzer(
            PanelJobMatchDemoData::userSkills(),
            PanelDemoData::stats()['readiness'],
        );
    }
}
