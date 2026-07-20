<?php

namespace App\Services;

use App\Support\PortalAuthSession;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

class CareerTalentApiClient
{
    public function baseUrl(): string
    {
        return rtrim(config('services.careertalent.api_url'), '/');
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function health(): array
    {
        return $this->getJson('/health', 3);
    }

    /**
     * @param  array{full_name: string, email: string, password: string}  $payload
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function register(array $payload): array
    {
        return $this->postJson('/api/v1/auth/register', $payload, 10);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function login(string $email, string $password): array
    {
        try {
            $response = Http::asForm()->timeout(10)->post($this->baseUrl().'/api/v1/auth/login', [
                'username' => $email,
                'password' => $password,
            ]);

            return $this->normalizeResponse($response);
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function me(string $accessToken): array
    {
        try {
            $response = Http::withToken($accessToken)
                ->timeout(10)
                ->get($this->baseUrl().'/api/v1/auth/me');

            return $this->normalizeResponse($response);
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    public function updatePreferredLocale(string $locale): array
    {
        return $this->patchJson('/api/v1/auth/me/locale', [
            'preferred_locale' => $locale,
        ], 120);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function adminDashboard(): array
    {
        return $this->getJson('/api/v1/admin/dashboard', 10);
    }

    public function adminProfile(): array
    {
        return $this->getJson('/api/v1/admin/profile', 10);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminProfile(array $payload): array
    {
        return $this->patchJson('/api/v1/admin/profile', $payload, 15);
    }

    public function adminAccounts(): array
    {
        return $this->getJson('/api/v1/admin/accounts', 10);
    }

    /** @param array<string, mixed> $payload */
    public function createAdminAccount(array $payload): array
    {
        return $this->postJson('/api/v1/admin/accounts', $payload, 15);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminAccount(int $userId, array $payload): array
    {
        return $this->patchJson('/api/v1/admin/accounts/'.$userId, $payload, 15);
    }

    public function deleteAdminAccount(int $userId): array
    {
        return $this->deleteJson('/api/v1/admin/accounts/'.$userId, 15);
    }

    public function adminOrganizations(): array
    {
        return $this->getJson('/api/v1/admin/organizations', 10);
    }

    public function adminOrganizationDetail(string $organizationId): array
    {
        return $this->getJson('/api/v1/admin/organizations/'.rawurlencode($organizationId), 15);
    }

    /** @param array<string, mixed> $payload */
    public function createAdminOrganization(array $payload): array
    {
        return $this->postJson('/api/v1/admin/organizations', $payload, 15);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminOrganization(string $organizationId, array $payload): array
    {
        return $this->patchJson('/api/v1/admin/organizations/'.rawurlencode($organizationId), $payload, 15);
    }

    public function deleteAdminOrganization(string $organizationId): array
    {
        return $this->deleteJson('/api/v1/admin/organizations/'.rawurlencode($organizationId), 15);
    }

    public function inviteAdminOrganizationOwner(string $organizationId, string $email): array
    {
        return $this->postJson('/api/v1/admin/organizations/'.rawurlencode($organizationId).'/owner-invitations', [
            'email' => $email,
            'role' => 'owner',
        ], 15);
    }

    public function adminStudents(): array
    {
        return $this->getJson('/api/v1/admin/students', 10);
    }

    public function adminStudentDetail(int $userId): array
    {
        return $this->getJson('/api/v1/admin/students/'.$userId, 15);
    }

    /** @param array<string, mixed> $payload */
    public function createAdminStudent(array $payload): array
    {
        return $this->postJson('/api/v1/admin/students', $payload, 15);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminStudent(int $userId, array $payload): array
    {
        return $this->patchJson('/api/v1/admin/students/'.$userId, $payload, 15);
    }

    public function deleteAdminStudent(int $userId): array
    {
        return $this->deleteJson('/api/v1/admin/students/'.$userId, 15);
    }

    public function adminApplications(): array
    {
        return $this->getJson('/api/v1/admin/applications', 10);
    }

    /** @param array<string, mixed> $payload */
    public function createAdminApplication(array $payload): array
    {
        return $this->postJson('/api/v1/admin/applications', $payload, 15);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminApplication(string $applicationId, array $payload): array
    {
        return $this->patchJson('/api/v1/admin/applications/'.rawurlencode($applicationId), $payload, 15);
    }

    public function deleteAdminApplication(string $applicationId): array
    {
        return $this->deleteJson('/api/v1/admin/applications/'.rawurlencode($applicationId), 15);
    }

    public function adminInterviews(): array
    {
        return $this->getJson('/api/v1/admin/interviews', 10);
    }

    /** @param array<string, mixed> $payload */
    public function createAdminInterview(array $payload): array
    {
        return $this->postJson('/api/v1/admin/interviews', $payload, 120);
    }

    /** @param array<string, mixed> $payload */
    public function updateAdminInterview(string $interviewId, array $payload): array
    {
        return $this->patchJson('/api/v1/admin/interviews/'.rawurlencode($interviewId), $payload, 15);
    }

    public function deleteAdminInterview(string $interviewId): array
    {
        return $this->deleteJson('/api/v1/admin/interviews/'.rawurlencode($interviewId), 15);
    }

    public function companyContext(?string $accessToken = null): array
    {
        if ($accessToken !== null) {
            try {
                return $this->normalizeResponse(Http::withToken($accessToken)->timeout(10)->get($this->baseUrl().'/api/v1/company/context'));
            } catch (ConnectionException $exception) {
                return $this->connectionError($exception);
            }
        }

        return $this->getJson('/api/v1/company/context', 10);
    }

    public function companyOrganizationProfile(string $slug): array
    {
        return $this->getJson('/api/v1/company/organizations/'.rawurlencode($slug), 10);
    }

    public function companyDashboard(string $organizationId, string $period = '30d'): array
    {
        return $this->getJson('/api/v1/company/dashboard?'.http_build_query(['period' => $period]), 10, ['X-Organization-ID' => $organizationId]);
    }

    public function companyPositions(string $organizationId, ?string $status = null): array
    {
        $query = $status !== null ? '?'.http_build_query(['status' => $status]) : '';

        return $this->getJson('/api/v1/company/positions'.$query, 10, ['X-Organization-ID' => $organizationId]);
    }

    /** @param array<string, mixed> $payload */
    public function createCompanyPosition(string $organizationId, array $payload): array
    {
        return $this->postJson('/api/v1/company/positions', $payload, 15, ['X-Organization-ID' => $organizationId]);
    }

    /** @param array<string, mixed> $payload */
    public function updateCompanyPosition(string $organizationId, string $positionId, array $payload): array
    {
        return $this->patchJson('/api/v1/company/positions/'.rawurlencode($positionId), $payload, 15, ['X-Organization-ID' => $organizationId]);
    }

    public function deleteCompanyPosition(string $organizationId, string $positionId): array
    {
        return $this->deleteJson('/api/v1/company/positions/'.rawurlencode($positionId), 15, ['X-Organization-ID' => $organizationId]);
    }

    /** @param array<string, string> $filters */
    public function companyApplications(string $organizationId, array $filters = []): array
    {
        $query = $filters !== [] ? '?'.http_build_query($filters) : '';

        return $this->getJson('/api/v1/company/applications'.$query, 10, ['X-Organization-ID' => $organizationId]);
    }

    public function companyAssessments(string $organizationId): array
    {
        return $this->getJson('/api/v1/company/assessments', 10, ['X-Organization-ID' => $organizationId]);
    }

    public function companyMembers(string $organizationId): array
    {
        return $this->getJson('/api/v1/company/members', 10, ['X-Organization-ID' => $organizationId]);
    }

    public function inviteCompanyMember(string $organizationId, array $payload): array
    {
        return $this->postJson('/api/v1/company/invitations', $payload, 15, ['X-Organization-ID' => $organizationId]);
    }

    public function updateCompanyMember(string $organizationId, string $membershipId, array $payload): array
    {
        return $this->patchJson('/api/v1/company/members/'.rawurlencode($membershipId), $payload, 15, ['X-Organization-ID' => $organizationId]);
    }

    public function updateCompanyOrganization(string $organizationId, array $payload): array
    {
        return $this->patchJson('/api/v1/company/organization', $payload, 15, ['X-Organization-ID' => $organizationId]);
    }

    public function companyInvitation(string $token): array
    {
        return $this->getJson('/api/v1/company/invitations/'.rawurlencode($token), 10);
    }

    public function acceptCompanyInvitation(string $token, array $payload): array
    {
        return $this->postJson('/api/v1/company/invitations/'.rawurlencode($token).'/accept', $payload, 15);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function adminModule(string $module): array
    {
        return $this->getJson('/api/v1/admin/modules/'.rawurlencode($module), 10);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function adminCareerData(string $resource): array
    {
        return $this->getJson('/api/v1/admin/career-data/'.rawurlencode($resource), 10);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function createAdminCareerData(string $resource, array $payload): array
    {
        return $this->postJson('/api/v1/admin/career-data/'.rawurlencode($resource), $payload, 15);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function updateAdminCareerData(string $resource, int $record, array $payload): array
    {
        return $this->putJson('/api/v1/admin/career-data/'.rawurlencode($resource).'/'.$record, $payload, 15);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function deleteAdminCareerData(string $resource, int $record): array
    {
        return $this->deleteJson('/api/v1/admin/career-data/'.rawurlencode($resource).'/'.$record, 15);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function panel(string $endpoint): array
    {
        return $this->getJson('/api/v1/panel/'.ltrim($endpoint, '/'), 10);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function analyzePanelJob(string $url): array
    {
        return $this->postJson('/api/v1/panel/job-matches/analyze', ['url' => $url], 15);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function savePanelTarget(array $target): array
    {
        return $this->putJson('/api/v1/panel/target', $target, 10);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function panelTarget(): array
    {
        return $this->getJson('/api/v1/panel/target', 5);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function parseJobListing(string $url): array
    {
        return $this->postJson('/api/v1/panel/job-listings/parse', ['url' => $url], 12);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function analyzeCv(UploadedFile $file): array
    {
        try {
            $response = $this->request(120)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->baseUrl().'/api/v1/cv/analyze');

            return $this->normalizeResponse($response);
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    /** @param array<string, mixed> $payload */
    public function analyzeCvTextQueued(array $payload): array
    {
        return $this->postJson('/api/v1/cv/analyze-text', $payload, 120);
    }

    public function cvDocuments(): array
    {
        return $this->getJson('/api/v1/cv/documents', 15);
    }

    public function cvDocument(string $documentId): array
    {
        return $this->getJson('/api/v1/cv/documents/'.rawurlencode($documentId), 15);
    }

    public function archiveGeneratedCv(UploadedFile $file, string $displayName, string $language, string $builderData): array
    {
        try {
            $response = $this->request(45)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->baseUrl().'/api/v1/cv/documents/generated', [
                    'display_name' => $displayName,
                    'language' => $language,
                    'builder_data' => $builderData,
                ]);

            if (! $response->successful()) {
                return $this->normalizeResponse($response);
            }

            return ['ok' => true, 'status' => $response->status(), 'body' => $response->json(), 'error' => null];
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    public function archiveCurrentCv(string $documentId): array
    {
        return $this->patchJson('/api/v1/cv/documents/'.rawurlencode($documentId).'/archive', [], 15);
    }

    public function analyzeCvDocument(string $documentId): array
    {
        return $this->postJson('/api/v1/cv/documents/'.rawurlencode($documentId).'/analyze', [], 120);
    }

    public function deleteCvDocument(string $documentId): array
    {
        return $this->deleteJson('/api/v1/cv/documents/'.rawurlencode($documentId), 15);
    }

    public function downloadCvDocument(string $documentId): array
    {
        try {
            $response = $this->request(30)->get($this->baseUrl().'/api/v1/cv/documents/'.rawurlencode($documentId).'/download');

            return ['ok' => $response->successful(), 'status' => $response->status(), 'content' => $response->body(), 'content_type' => $response->header('Content-Type'), 'content_disposition' => $response->header('Content-Disposition')];
        } catch (ConnectionException $exception) {
            return ['ok' => false, 'status' => 502, 'content' => '', 'content_type' => null, 'content_disposition' => null];
        }
    }

    public function careerAnalysis(string $analysisId): array
    {
        return $this->getJson('/api/v1/career/analysis/'.rawurlencode($analysisId), 10);
    }

    public function currentCareerAnalysis(): array
    {
        return $this->getJson('/api/v1/career/analysis/current', 10);
    }

    public function resetCareer(string $scope): array
    {
        return $this->postJson('/api/v1/career/reset', ['scope' => $scope], 15);
    }

    public function careerJobs(): array
    {
        return $this->getJson('/api/v1/career/jobs', 15);
    }

    /** @param array<string, mixed> $payload */
    public function analyzeCareerJob(array $payload): array
    {
        return $this->postJson('/api/v1/career/jobs/analyze', $payload, 20);
    }

    public function careerJob(string $jobId): array
    {
        return $this->getJson('/api/v1/career/jobs/'.rawurlencode($jobId), 15);
    }

    public function saveCareerJob(string $jobId): array
    {
        return $this->postJson('/api/v1/career/jobs/'.rawurlencode($jobId).'/save', [], 15);
    }

    /** @param list<string> $suggestionIds */
    public function applyCareerJobSuggestions(string $jobId, array $suggestionIds): array
    {
        return $this->postJson('/api/v1/career/jobs/'.rawurlencode($jobId).'/apply', ['suggestion_ids' => $suggestionIds], 20);
    }

    public function deleteCareerJob(string $jobId): array
    {
        try {
            return $this->normalizeResponse($this->request(15)->delete($this->baseUrl().'/api/v1/career/jobs/'.rawurlencode($jobId)));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    public function careerTargets(): array
    {
        return $this->getJson('/api/v1/career/targets', 10);
    }

    public function careerTarget(string $targetId): array
    {
        return $this->getJson('/api/v1/career/targets/'.rawurlencode($targetId), 10);
    }

    /** @param array<string, mixed> $payload */
    public function createCareerTarget(array $payload): array
    {
        return $this->postJson('/api/v1/career/targets', $payload, 30);
    }

    public function careerTargetTasks(string $targetId): array
    {
        return $this->getJson('/api/v1/career/targets/'.rawurlencode($targetId).'/tasks', 10);
    }

    public function careerTask(string $taskId): array
    {
        return $this->getJson('/api/v1/career/tasks/'.rawurlencode($taskId), 10);
    }

    public function careerProfile(): array
    {
        return $this->getJson('/api/v1/career/profile', 10);
    }

    /** @param array<string, mixed> $payload */
    public function updateCareerProfile(array $payload): array
    {
        return $this->putJson('/api/v1/career/profile', $payload, 15);
    }

    public function careerChat(): array
    {
        return $this->getJson('/api/v1/career/chat', 10);
    }

    public function sendCareerChat(string $message): array
    {
        return $this->postJson('/api/v1/career/chat', ['message' => $message], 90);
    }

    public function clearCareerChat(): array
    {
        return $this->deleteJson('/api/v1/career/chat', 15);
    }

    public function currentInterview(): array
    {
        return $this->getJson('/api/v1/career/interviews/current', 15);
    }

    public function startInterview(string $language = 'tr'): array
    {
        return $this->postJson('/api/v1/career/interviews', ['language' => $language], 90);
    }

    public function scoreInterviewAnswer(string $interviewId, array $payload): array
    {
        return $this->postJson('/api/v1/career/interviews/'.rawurlencode($interviewId).'/answers', $payload, 90);
    }

    public function personalTasks(?string $targetId = null): array
    {
        return $this->getJson('/api/v1/career/personal-tasks'.($targetId ? '?target_id='.rawurlencode($targetId) : ''), 10);
    }

    public function createPersonalTask(array $payload): array
    {
        return $this->postJson('/api/v1/career/personal-tasks', $payload, 15);
    }

    public function updatePersonalTask(string $taskId, array $payload): array
    {
        return $this->patchJson('/api/v1/career/personal-tasks/'.rawurlencode($taskId), $payload, 15);
    }

    public function updateCareerTaskNote(string $taskId, ?string $note): array
    {
        return $this->patchJson('/api/v1/career/tasks/'.rawurlencode($taskId).'/note', ['note' => $note], 15);
    }

    public function updateCareerTaskStatus(string $taskId, string $status): array
    {
        return $this->patchJson('/api/v1/career/tasks/'.rawurlencode($taskId), ['status' => $status], 15);
    }

    public function deletePersonalTask(string $taskId): array
    {
        return $this->deleteJson('/api/v1/career/personal-tasks/'.rawurlencode($taskId), 15);
    }

    public function careerApplications(): array
    {
        return $this->getJson('/api/v1/career/applications', 15);
    }

    public function createCareerApplication(array $payload): array
    {
        return $this->postJson('/api/v1/career/applications', $payload, 15);
    }

    public function updateCareerApplication(string $applicationId, array $payload): array
    {
        return $this->patchJson('/api/v1/career/applications/'.rawurlencode($applicationId), $payload, 15);
    }

    public function markCareerJobApplied(string $jobId): array
    {
        return $this->postJson('/api/v1/career/jobs/'.rawurlencode($jobId).'/application', [], 15);
    }

    /** @param array<string, mixed> $payload */
    public function submitCareerEvidence(string $taskId, array $payload): array
    {
        return $this->postJson('/api/v1/career/tasks/'.rawurlencode($taskId).'/evidence', $payload, 30);
    }

    public function submitCareerEvidenceFile(string $taskId, UploadedFile $file): array
    {
        try {
            $response = $this->request(30)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->baseUrl().'/api/v1/career/tasks/'.rawurlencode($taskId).'/evidence/upload');

            return $this->normalizeResponse($response);
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    public function submitSkillEvidenceLink(string $skill, string $targetId, string $url): array
    {
        return $this->postJson('/api/v1/career/skill-evidence/link', [
            'skill' => $skill,
            'target_id' => $targetId,
            'url' => $url,
        ], 30);
    }

    public function submitSkillEvidenceFile(string $skill, string $targetId, UploadedFile $file): array
    {
        try {
            $response = $this->request(30)
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->baseUrl().'/api/v1/career/skill-evidence/upload', [
                    'skill' => $skill,
                    'target_id' => $targetId,
                ]);

            return $this->normalizeResponse($response);
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    public function clearSkillEvidence(string $skill, string $targetId): array
    {
        $query = http_build_query(['skill' => $skill, 'target_id' => $targetId]);

        return $this->deleteJson('/api/v1/career/skill-evidence?'.$query, 15);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    public function analyzeCvText(string $cvText, string $fileName): array
    {
        return $this->postJson('/api/v1/cv/analyze-text', [
            'cv_text' => $cvText,
            'file_name' => $fileName,
        ], 120);
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    private function getJson(string $path, int $timeout, array $headers = []): array
    {
        try {
            return $this->normalizeResponse($this->request($timeout, $headers)->get($this->baseUrl().$path));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    private function postJson(string $path, array $payload, int $timeout, array $headers = []): array
    {
        try {
            return $this->normalizeResponse($this->request($timeout, $headers)->post($this->baseUrl().$path, $payload));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    private function putJson(string $path, array $payload, int $timeout): array
    {
        try {
            return $this->normalizeResponse($this->request($timeout)->put($this->baseUrl().$path, $payload));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    private function patchJson(string $path, array $payload, int $timeout, array $headers = []): array
    {
        try {
            return $this->normalizeResponse($this->request($timeout, $headers)->patch($this->baseUrl().$path, $payload));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    private function deleteJson(string $path, int $timeout, array $headers = []): array
    {
        try {
            return $this->normalizeResponse($this->request($timeout, $headers)->delete($this->baseUrl().$path));
        } catch (ConnectionException $exception) {
            return $this->connectionError($exception);
        }
    }

    /**
     * @return array{ok: bool, status: ?int, body: ?array<string, mixed>, error: ?string}
     */
    private function normalizeResponse($response): array
    {
        if (! $response->successful()) {
            $detail = $response->json('detail');
            $message = (is_array($detail) ? ($detail['message'] ?? $detail['code'] ?? null) : $detail)
                ?? $response->json('message')
                ?? $response->body();

            return [
                'ok' => false,
                'status' => $response->status(),
                'body' => null,
                'error' => is_string($message) ? $message : 'API isteği başarısız',
            ];
        }

        return [
            'ok' => true,
            'status' => $response->status(),
            'body' => $response->json(),
            'error' => null,
        ];
    }

    private function request(int $timeout, array $headers = []): PendingRequest
    {
        $request = Http::timeout($timeout)->withHeaders($headers);
        $token = PortalAuthSession::token(request());

        return $token !== null
            ? $request->withToken($token)
            : $request;
    }

    /**
     * @return array{ok: bool, status: ?int, body: null, error: string}
     */
    private function connectionError(ConnectionException $exception): array
    {
        return [
            'ok' => false,
            'status' => null,
            'body' => null,
            'error' => $exception->getMessage(),
        ];
    }
}
