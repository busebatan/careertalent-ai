<?php

namespace Tests\Feature;

use App\Http\Middleware\EnsureApiAuthenticated;
use App\Http\Middleware\EnsureApiCandidate;
use App\Http\Middleware\EnsureApiCompany;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class CompanyPositionsCoreTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withMiddleware([
            EnsureApiAuthenticated::class,
            EnsureApiCandidate::class,
            EnsureApiCompany::class,
        ]);
    }

    private function companyUser(): array
    {
        return ['id' => 44, 'full_name' => 'Acme Owner', 'email' => 'owner@acme.test', 'is_active' => true, 'is_admin' => false, 'role' => 'company', 'preferred_locale' => 'tr'];
    }

    private function candidateUser(): array
    {
        return ['id' => 77, 'full_name' => 'Aday Kullanıcı', 'email' => 'aday@example.com', 'is_active' => true, 'is_admin' => false, 'role' => 'student', 'preferred_locale' => 'tr'];
    }

    private function membership(): array
    {
        return [
            'organization_id' => 'org-1', 'organization_name' => 'Acme Teknoloji', 'organization_slug' => 'acme',
            'organization_type' => 'employer', 'organization_status' => 'active', 'plan_code' => 'growth',
            'billing_email' => 'billing@acme.test', 'website' => 'https://acme.test', 'role' => 'owner',
            'permissions' => ['dashboard.view', 'positions.view', 'positions.write', 'positions.delete', 'ats_config.view', 'ats_config.write', 'applications.view', 'applications.write', 'assessments.view', 'assessments.write', 'scorecards.view', 'scorecards.submit', 'organization.update', 'members.view', 'members.invite', 'members.manage'],
        ];
    }

    private function position(array $overrides = []): array
    {
        return array_replace_recursive([
            'id' => 'position-1', 'title' => 'Backend Developer', 'department' => 'Engineering', 'level' => 'mid',
            'employment_type' => 'full_time', 'workplace_type' => 'remote', 'location' => 'İstanbul',
            'status' => 'published', 'opened_at' => '2026-07-20T10:00:00Z', 'application_deadline' => '2026-08-15T20:59:00Z',
            'application_count' => 12, 'assessment_completed_count' => 7, 'shortlisted_count' => 3,
            'recruiter_membership_id' => 'm-recruiter', 'recruiter_name' => 'Ece İK',
            'technical_manager_membership_id' => 'm-tech', 'technical_manager_name' => 'Mert Teknik',
            'responsibilities' => 'Laravel servislerini geliştirmek',
            'must_have_skills' => ['Laravel', 'MySQL'], 'preferred_skills' => ['Redis'], 'learnable_skills' => ['Kubernetes'],
            'experience_expectation' => '3+ yıl backend deneyimi', 'language_work_authorization' => 'İngilizce B2',
            'public_id' => '01J8K7D2M', 'public_path' => '/apply/acme/backend-developer-01J8K7D2M',
            'application_open' => true, 'estimated_application_minutes' => 8, 'estimated_assessment_minutes' => 35,
            'evaluation_config' => ['estimated_application_minutes' => 8, 'estimated_assessment_minutes' => 35],
        ], $overrides);
    }

    private function detailPayload(): array
    {
        return [
            'position' => $this->position(),
            'ats_config' => [
                'provider' => 'generic', 'system_name' => 'Acme ATS',
                'organization_terms' => ['Screen=Ön eleme'], 'position_terms' => ['Proof=Proje kanıtı'],
                'effective_terms' => ['Screen=Ön eleme', 'Proof=Proje kanıtı'],
                'organization_notes' => 'Kurum notu', 'position_notes' => 'Laravel kanıtını proje üzerinden doğrula.',
                'candidate_analysis_instructions' => 'CV kanıtı yoksa belirsiz işaretle.',
            ],
            'criteria_versions' => [[
                'id' => 'criteria-2', 'version_number' => 2, 'status' => 'draft',
                'ai_suggestions' => ['ambiguous_requirements' => ['Ölçeklenebilir sistem ifadesi belirsiz.'], 'recommended_weights' => ['Laravel' => 40]],
                'criteria' => ['must_have' => ['Laravel'], 'weights' => ['Laravel' => 40], 'preconditions' => ['language_work_authorization' => 'İngilizce B2']],
            ]],
            'active_criteria_version' => ['id' => 'criteria-1', 'version_number' => 1, 'status' => 'approved', 'criteria' => []],
            'ai_analyses' => [['id' => 'analysis-1', 'criteria_version_id' => 'criteria-2', 'status' => 'queued', 'result' => [], 'created_at' => '2026-07-20T10:00:00Z']],
            'share_links' => [[
                'id' => 'link-1', 'label' => 'LinkedIn Temmuz', 'channel' => 'linkedin', 'short_code' => 'LNK-7FK29',
                'short_path' => '/a/LNK-7FK29', 'click_count' => 326, 'application_count' => 48,
                'assessment_completed_count' => 31, 'is_active' => true,
            ]],
            'applications' => [[
                'id' => 'application-1', 'candidate_name' => 'Aday Kullanıcı', 'candidate_email' => 'aday@example.com',
                'stage' => 'new', 'completion_status' => 'not_requested', 'missing_documents' => ['cv'], 'last_action_at' => '2026-07-20T10:00:00Z',
            ]], 'assessments' => [], 'comparison' => [],
            'activities' => [['event_type' => 'position.published', 'details' => ['status' => 'published'], 'actor_name' => 'Acme Owner', 'occurred_at' => '2026-07-20T10:00:00Z']],
            'members' => [],
        ];
    }

    public function test_positions_are_the_company_core_with_requested_status_tabs_and_metrics(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) {
                return Http::response($this->companyUser());
            }
            if (str_ends_with($request->url(), '/api/v1/company/context')) {
                return Http::response(['memberships' => [$this->membership()]]);
            }
            if (str_contains($request->url(), '/api/v1/company/positions')) {
                return Http::response([
                    'items' => [$this->position()],
                    'status_counts' => ['draft' => 2, 'published' => 6, 'paused' => 1, 'closed' => 0, 'archived' => 0],
                ]);
            }

            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar')
            ->assertOk()
            ->assertSee('companyPositions', false)
            ->assertSee('Tüm durumlar')
            ->assertSee('Taslak')
            ->assertSee('Yayında')
            ->assertSee('Başvurusu durduruldu')
            ->assertSee('company-status-badge--success', false)
            ->assertSee('goToPosition', false)
            ->assertSee('showUrlTemplate', false);
    }

    public function test_position_create_form_forwards_full_hiring_contract(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_ends_with($request->url(), '/api/v1/company/positions')) return Http::response(['id' => 'position-1'], 201);
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/yeni')
            ->assertOk()
            ->assertSee('İlan metnini yapıştır')
            ->assertSee('Vazgeçilmez yetenekler')
            ->assertSee('Öğrenilebilir yetenekler')
            ->assertSee('İşe başlangıç hedefi')
            ->assertSee('ATS terimleri');

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->post('/acme/pozisyonlar', [
                'title' => 'Backend Developer', 'department' => 'Engineering', 'level' => 'mid',
                'employment_type' => 'full_time', 'workplace_type' => 'remote', 'location' => 'İstanbul',
                'responsibilities' => 'API geliştirme', 'must_have_skills' => "Laravel\nMySQL",
                'preferred_skills' => 'Redis', 'learnable_skills' => 'Kubernetes',
                'experience_expectation' => '3 yıl', 'language_work_authorization' => 'İngilizce B2',
                'application_deadline' => '2026-08-15', 'target_start_date' => '2026-09-01',
                'ats_terms' => "Screen=Ön eleme", 'ats_notes' => 'Proje kanıtı iste', 'status' => 'draft',
                'description' => 'Takım ve ürün özeti', 'estimated_application_minutes' => 8, 'estimated_assessment_minutes' => 35,
            ])
            ->assertRedirect('/acme/pozisyonlar/position-1');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/company/positions')
            && $request->method() === 'POST'
            && $request->data()['must_have_skills'] === ['Laravel', 'MySQL']
            && $request->data()['ats_terms'] === ['Screen=Ön eleme']
            && $request->data()['evaluation_config']['estimated_assessment_minutes'] === 35);
    }

    public function test_position_detail_exposes_all_operational_tabs_and_human_approval_actions(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_contains($request->url(), '/api/v1/company/positions/position-1')) return Http::response($this->detailPayload());
            return Http::response([], 404);
        });

        $response = $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/position-1?tab=requirements');

        $response->assertOk()
            ->assertSee('Genel Bakış')
            ->assertSee('Gereksinimler')
            ->assertSee('Başvurular')
            ->assertSee('Değerlendirme')
            ->assertSee('Aday Karşılaştırma')
            ->assertSee('Etkinlik Geçmişi')
            ->assertSee('Yayınla ve Paylaş')
            ->assertSee('Ayarlar')
            ->assertSee('AI taslağı — kurum onayı bekliyor')
            ->assertSee('Ölçüt sürümünü onayla')
            ->assertSee('Ölçeklenebilir sistem ifadesi belirsiz.')
            ->assertSee('data-position-analysis', false);

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/position-1?tab=settings')
            ->assertOk()
            ->assertSee('name="must_have_skills"', false)
            ->assertSee('name="recruiter_membership_id"', false)
            ->assertSee('name="assessment_template_id"', false)
            ->assertSee('name="estimated_assessment_minutes"', false);
    }

    public function test_position_ai_analysis_status_is_read_back_from_backend(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_ends_with($request->url(), '/api/v1/company/positions/position-1/ai-analyses/analysis-1')) return Http::response([
                'id' => 'analysis-1', 'criteria_version_id' => 'criteria-2', 'status' => 'completed', 'result' => [],
            ]);
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->getJson('/acme/pozisyonlar/position-1/ai-analiz/analysis-1')
            ->assertOk()
            ->assertJsonPath('status', 'completed');
    }

    public function test_recruiter_can_record_candidate_stage_note_and_human_decision(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_ends_with($request->url(), '/api/v1/company/positions/position-1') && $request->method() === 'GET') return Http::response($this->detailPayload());
            if (str_ends_with($request->url(), '/api/v1/company/positions/position-1/applications/application-1') && $request->method() === 'PATCH') return Http::response([
                'id' => 'application-1', 'current_stage' => 'shortlisted', 'first_reviewed_at' => '2026-07-20T10:00:00Z', 'updated_at' => '2026-07-20T10:01:00Z',
            ]);
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/position-1?tab=applications')
            ->assertOk()
            ->assertSee('Aşama / not / karar')
            ->assertSee('Analiz istenmedi')
            ->assertSee('Eksik belgeler')
            ->assertDontSee('not_requested');

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->patch('/acme/pozisyonlar/position-1/adaylar/application-1', [
                'stage' => 'shortlisted', 'note' => 'Teknik kanıt güçlü', 'decision' => 'İnsan kısa liste kararı',
                'idempotency_key' => 'frontend-action-001',
            ])->assertRedirect();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/company/positions/position-1/applications/application-1')
            && $request->method() === 'PATCH'
            && $request->data()['stage'] === 'shortlisted'
            && $request->data()['note'] === 'Teknik kanıt güçlü'
            && $request->data()['decision'] === 'İnsan kısa liste kararı');
    }

    public function test_application_completion_status_follows_english_panel_locale(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) {
                return Http::response([...$this->companyUser(), 'preferred_locale' => 'en']);
            }
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_contains($request->url(), '/api/v1/company/positions/position-1')) return Http::response($this->detailPayload());
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/position-1?tab=applications')
            ->assertOk()
            ->assertSee('Analysis not requested')
            ->assertSee('Missing documents')
            ->assertSee('Human review')
            ->assertDontSee('not_requested')
            ->assertDontSee('Tamamlanma durumu');
    }

    public function test_ats_dictionary_uses_granular_permission_and_backend_list_contract(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_ends_with($request->url(), '/api/v1/company/ats-config') && $request->method() === 'GET') return Http::response([
                'organization_id' => 'org-1', 'provider' => 'greenhouse', 'system_name' => 'Acme ATS',
                'terms' => ['Screen=Ön eleme'], 'notes' => 'İK notu', 'candidate_analysis_instructions' => 'Kanıt yoksa belirsiz işaretle.',
            ]);
            if (str_ends_with($request->url(), '/api/v1/company/ats-config') && $request->method() === 'PATCH') return Http::response($request->data());
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/ats-sozlugu')
            ->assertOk()
            ->assertSee('name="system_name"', false)
            ->assertSee('name="candidate_analysis_instructions"', false)
            ->assertSee('Screen=Ön eleme');

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->patch('/acme/ats-sozlugu', [
                'provider' => 'greenhouse', 'system_name' => 'Acme ATS', 'terms' => "Screen=Ön eleme\nOnsite=Teknik görüşme",
                'notes' => 'İK notu', 'candidate_analysis_instructions' => 'Kanıt yoksa belirsiz işaretle.',
            ])->assertRedirect();

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/company/ats-config')
            && $request->method() === 'PATCH'
            && $request->data()['terms'] === ['Screen=Ön eleme', 'Onsite=Teknik görüşme']
            && $request->data()['system_name'] === 'Acme ATS'
            && $request->data()['candidate_analysis_instructions'] === 'Kanıt yoksa belirsiz işaretle.');
    }

    public function test_share_tab_shows_canonical_and_channel_links_without_fake_metrics(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->companyUser());
            if (str_ends_with($request->url(), '/api/v1/company/context')) return Http::response(['memberships' => [$this->membership()]]);
            if (str_contains($request->url(), '/api/v1/company/positions/position-1')) return Http::response($this->detailPayload());
            return Http::response([], 404);
        });

        $this->withSession(['company_auth.access_token' => 'company-token'])
            ->get('/acme/pozisyonlar/position-1?tab=share')
            ->assertOk()
            ->assertSee('Ana başvuru bağlantısı')
            ->assertSee('backend-developer-01J8K7D2M')
            ->assertSee('LinkedIn Temmuz')
            ->assertSee('326')
            ->assertSee('48')
            ->assertSee('31')
            ->assertSee('Yeni takip bağlantısı oluştur')
            ->assertSee('value="company_website"', false)
            ->assertSee('name="agency_reference"', false)
            ->assertSee('name="employee_reference"', false);
    }

    public function test_public_position_is_guest_visible_and_candidate_returns_after_login(): void
    {
        Http::fake(function (Request $request) {
            if (str_contains($request->url(), '/api/v1/public/apply/acme/backend-developer-01J8K7D2M')) return Http::response([
                'organization' => ['name' => 'Acme Teknoloji', 'slug' => 'acme'],
                'position' => $this->position(), 'source' => null,
            ]);
            return Http::response([], 404);
        });

        $this->get('/apply/acme/backend-developer-01J8K7D2M?source=LNK-7FK29')
            ->assertOk()
            ->assertSee('Acme Teknoloji')
            ->assertSee('Backend Developer')
            ->assertSee('Başvuruyu başlat')
            ->assertSee('35 dakika')
            ->assertSee('Diğer kurumlara yaptığınız başvurular paylaşılmaz');

        $this->withMiddleware([EnsureApiAuthenticated::class, EnsureApiCandidate::class])
            ->get('/apply/acme/backend-developer-01J8K7D2M/baslat?source=LNK-7FK29')
            ->assertRedirect('/panel/login')
            ->assertSessionHas('url.intended', url('/apply/acme/backend-developer-01J8K7D2M/baslat?source=LNK-7FK29'));
    }

    public function test_channel_short_link_is_resolved_by_backend_before_redirect(): void
    {
        Http::fake([
            '*/api/v1/public/a/LNK-7FK29' => Http::response([
                'organization' => ['name' => 'Acme Teknoloji', 'slug' => 'acme'],
                'position' => $this->position(),
                'source' => ['short_code' => 'LNK-7FK29', 'channel' => 'linkedin'],
            ]),
        ]);

        $this->get('/a/LNK-7FK29')
            ->assertRedirect('/apply/acme/backend-developer-01J8K7D2M?source=LNK-7FK29');

        Http::assertSent(fn (Request $request): bool => str_ends_with($request->url(), '/api/v1/public/a/LNK-7FK29'));
    }

    public function test_candidate_selects_owned_cv_and_submits_consent_snapshot(): void
    {
        Http::fake(function (Request $request) {
            if (str_ends_with($request->url(), '/api/v1/auth/me')) return Http::response($this->candidateUser());
            if (str_contains($request->url(), '/api/v1/public/positions/01J8K7D2M/applications')) {
                return Http::response(['id' => 'application-1', 'position_id' => 'position-1', 'current_stage' => 'new', 'analysis_status' => 'queued', 'created' => true, 'applied_at' => '2026-07-20T10:00:00Z'], 201);
            }
            if (str_contains($request->url(), '/api/v1/public/apply/acme/backend-developer-01J8K7D2M')) {
                return Http::response(['organization' => ['name' => 'Acme Teknoloji', 'slug' => 'acme'], 'position' => $this->position(), 'source' => null]);
            }
            if (str_ends_with($request->url(), '/api/v1/cv/documents')) {
                return Http::response([['id' => 'cv-1', 'display_name' => 'Backend CV', 'is_current' => true]]);
            }
            return Http::response([], 404);
        });

        $session = ['auth.access_token' => 'candidate-token', 'auth.user' => $this->candidateUser()];
        $this->withMiddleware([EnsureApiAuthenticated::class, EnsureApiCandidate::class])
            ->withSession($session)
            ->get('/apply/acme/backend-developer-01J8K7D2M/baslat?source=LNK-7FK29')
            ->assertOk()
            ->assertSee('Backend CV')
            ->assertSee('Onayla ve başvur');

        $this->withMiddleware([EnsureApiAuthenticated::class, EnsureApiCandidate::class])
            ->withSession($session)
            ->post('/apply/acme/backend-developer-01J8K7D2M', [
                'cv_document_id' => 'cv-1', 'source' => 'LNK-7FK29', 'consent' => '1',
            ])
            ->assertOk()
            ->assertSee('Başvurunuz alındı');

        Http::assertSent(fn (Request $request): bool => str_contains($request->url(), '/api/v1/public/positions/01J8K7D2M/applications')
            && $request->method() === 'POST'
            && $request->data()['cv_document_id'] === 'cv-1'
            && $request->data()['consent']['accepted'] === true
            && $request->data()['share_link_code'] === 'LNK-7FK29');
    }

    public function test_candidate_registration_returns_to_the_public_job_flow(): void
    {
        Http::fake([
            '*/api/v1/auth/register' => Http::response($this->candidateUser(), 201),
            '*/api/v1/auth/login' => Http::response(['access_token' => 'candidate-token', 'token_type' => 'bearer']),
        ]);
        $intended = '/apply/acme/backend-developer-01J8K7D2M/baslat?source=LNK-7FK29';

        $this->withSession(['url.intended' => $intended])
            ->post('/panel/register', [
                'name' => 'Aday Kullanıcı', 'email' => 'aday@example.com',
                'password' => 'GucluParola123!', 'password_confirmation' => 'GucluParola123!',
            ])
            ->assertRedirect($intended)
            ->assertSessionHas('auth.access_token', 'candidate-token');
    }
}
