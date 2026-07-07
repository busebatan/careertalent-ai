<?php

namespace App\Http\Controllers\App;

use App\Data\PanelDemoData;
use App\Services\PanelCvAnalysisStore;
use App\Services\PanelTargetRoleStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CareerLadderController extends PanelController
{
    public function show()
    {
        $data = $this->panelApiData('career-ladder', [
            'career_ladder' => PanelDemoData::careerLadder(),
            'career_tier_meta' => PanelDemoData::careerTierMeta(),
        ]);
        $cvLadder = PanelCvAnalysisStore::careerLadder();
        $ladder = $cvLadder ?? $data['career_ladder'];

        return $this->panelView('app.career-ladder', [
            'careerLadder' => $ladder,
            'careerTierMeta' => $data['career_tier_meta'],
            'fromApi' => $cvLadder !== null,
            'selectedTarget' => PanelTargetRoleStore::get(),
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
            PanelTargetRoleStore::putLadderRole($role);
        }

        if ($validated['mode'] === 'custom') {
            $request->validate(['target_role' => ['required', 'string', 'min:2', 'max:120']]);
            PanelTargetRoleStore::putCustomRole((string) $validated['target_role']);
        }

        if ($validated['mode'] === 'job_url') {
            $request->validate(['job_url' => ['required', 'url', 'max:2048']]);
            PanelTargetRoleStore::putJobUrl((string) $validated['job_url']);
        }

        return redirect()->route('panel.roadmap');
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function currentLadder(): array
    {
        $cvLadder = PanelCvAnalysisStore::careerLadder();
        if ($cvLadder !== null) {
            return $cvLadder;
        }

        $data = $this->panelApiData('career-ladder', [
            'career_ladder' => PanelDemoData::careerLadder(),
            'career_tier_meta' => PanelDemoData::careerTierMeta(),
        ]);

        return $data['career_ladder'];
    }
}
