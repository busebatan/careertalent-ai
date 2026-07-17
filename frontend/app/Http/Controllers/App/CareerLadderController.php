<?php

namespace App\Http\Controllers\App;

use App\Services\CareerTalentApiClient;
use App\Services\PanelTargetRoleStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CareerLadderController extends PanelController
{
    public function show(CareerTalentApiClient $api)
    {
        $result = $api->currentCareerAnalysis();
        $analysis = ($result['ok'] ?? false) && is_array($result['body'] ?? null) ? $result['body'] : [];
        $roles = $this->normalizeLadder(is_array($analysis['career_ladder'] ?? null) ? $analysis['career_ladder'] : []);

        return $this->panelView('app.career-ladder', [
            'careerLadder' => $roles,
            'careerTierMeta' => $this->tierMeta(),
            'fromApi' => $roles !== [],
            'selectedTarget' => PanelTargetRoleStore::get(),
            'careerEngineError' => $analysis['error_message'] ?? ($result['error'] ?? null),
        ]);
    }

    public function select(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', 'string', 'in:role,custom,job_url'],
            'role_id' => ['nullable', 'string', 'max:120'],
            'target_role' => ['nullable', 'string', 'max:120'],
            'job_url' => ['nullable', 'url', 'max:2048'],
        ]);

        if ($validated['mode'] === 'role') {
            $role = collect($this->currentLadder())->firstWhere('id', $validated['role_id'] ?? '');
            abort_if(! is_array($role), 422, 'Seçilen rol bulunamadı.');
            $target = PanelTargetRoleStore::putLadderRole($role);
        } elseif ($validated['mode'] === 'custom') {
            $request->validate(['target_role' => ['required', 'string', 'min:2', 'max:120']]);
            $target = PanelTargetRoleStore::putCustomRole((string) $validated['target_role']);
        } else {
            $request->validate(['job_url' => ['required', 'url', 'max:2048']]);
            $target = PanelTargetRoleStore::putJobUrl((string) $validated['job_url']);
        }

        abort_if($target === [], 502, 'Kariyer hedefi API’ye kaydedilemedi.');

        return redirect()->route('panel.roadmap');
    }

    /** @return list<array<string, mixed>> */
    private function currentLadder(): array
    {
        $result = app(CareerTalentApiClient::class)->currentCareerAnalysis();
        $body = $result['body'] ?? [];

        return ($result['ok'] ?? false) && is_array($body)
            ? $this->normalizeLadder(is_array($body['career_ladder'] ?? null) ? $body['career_ladder'] : [])
            : [];
    }

    /** @param list<array<string, mixed>> $roles */
    private function normalizeLadder(array $roles): array
    {
        return array_map(static function (array $role): array {
            $rawTier = strtoupper((string) ($role['tier'] ?? 'B'));
            $swot = is_array($role['swot'] ?? null) ? $role['swot'] : [];
            $weaknesses = is_array($swot['weaknesses'] ?? null) ? $swot['weaknesses'] : [];

            return [
                ...$role,
                'id' => (string) ($role['id'] ?? \Illuminate\Support\Str::slug((string) ($role['title'] ?? 'role'))),
                'tier' => ['A' => 'ready', 'B' => 'near', 'C' => 'reachable'][$rawTier] ?? 'near',
                'tier_label' => $role['tier_label'] ?? $rawTier,
                'gap_count' => (int) ($role['gap_count'] ?? count($weaknesses)),
                'gaps_summary' => (string) ($role['gaps_summary'] ?? implode(', ', $weaknesses)),
                'weeks_estimate' => $role['weeks_estimate'] ?? null,
                'swot' => [
                    'strengths' => is_array($swot['strengths'] ?? null) ? $swot['strengths'] : [],
                    'weaknesses' => $weaknesses,
                    'opportunities' => is_array($swot['opportunities'] ?? null) ? $swot['opportunities'] : [],
                    'threats' => is_array($swot['threats'] ?? null) ? $swot['threats'] : [],
                ],
            ];
        }, array_values(array_filter($roles, 'is_array')));
    }

    /** @return array<string, array{heading: string, hint: string}> */
    private function tierMeta(): array
    {
        $english = app()->getLocale() === 'en';

        return [
            'ready' => ['heading' => $english ? 'A · Ready now' : 'A · Şimdi hazır', 'hint' => $english ? 'Strongest fit' : 'En güçlü uyum'],
            'near' => ['heading' => $english ? 'B · Near target' : 'B · Yakın hedef', 'hint' => $english ? 'Close gaps first' : 'Önce boşlukları kapat'],
            'reachable' => ['heading' => $english ? 'C · Peak target' : 'C · Zirve hedef', 'hint' => $english ? 'Reachable upper level' : 'Ulaşılabilecek üst seviye'],
        ];
    }
}
