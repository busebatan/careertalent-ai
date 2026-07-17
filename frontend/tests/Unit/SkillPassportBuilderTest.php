<?php

namespace Tests\Unit;

use App\Services\SkillPassportBuilder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SkillPassportBuilderTest extends TestCase
{
    public function test_builds_radar_items_with_task_mapping_and_status(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'radar' => [
                    ['label' => 'SQL', 'score' => 53, 'target' => 80],
                    ['label' => 'Python', 'score' => 70, 'target' => 75],
                ],
            ],
            [
                [
                    'id' => 'task-sql',
                    'title' => 'SQL portfolio',
                    'status' => 'pending',
                    'skill_impacts' => ['SQL'],
                    'has_evidence' => false,
                    'evidence_verified' => false,
                ],
                [
                    'id' => 'task-done',
                    'title' => 'Excel dashboard',
                    'status' => 'completed',
                    'skill_impacts' => ['Excel'],
                    'has_evidence' => false,
                    'evidence_verified' => false,
                ],
            ],
        );

        $this->assertSame(62, $passport['score']);
        $this->assertSame(['SQL', 'Python'], $passport['gaps']);
        $this->assertCount(3, $passport['items']);
        $this->assertSame(0, $passport['verified']);

        $sql = null;
        $excel = null;
        foreach ($passport['items'] as $item) {
            if ($item['skill'] === 'SQL') {
                $sql = $item;
            }
            if ($item['skill'] === 'Excel') {
                $excel = $item;
            }
        }

        $this->assertNotNull($sql);
        $this->assertSame('task-sql', $sql['task_id']);
        $this->assertSame('missing', $sql['status']);
        $this->assertNotNull($excel);
        $this->assertSame('waiting', $excel['status']);
    }

    public function test_marks_skill_verified_only_when_evidence_is_accepted(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'radar' => [
                    ['label' => 'SAP ERP', 'score' => 50, 'target' => 75],
                ],
            ],
            [
                [
                    'id' => 'task-sap',
                    'title' => 'Complete SAP FI/CO advanced training',
                    'status' => 'completed',
                    'skill_impacts' => ['SAP ERP'],
                    'has_evidence' => false,
                    'evidence_verified' => false,
                ],
            ],
        );

        $this->assertSame('waiting', $passport['items'][0]['status']);
        $this->assertSame(0, $passport['verified']);
    }

    public function test_extra_task_skill_without_evidence_is_missing_until_task_is_completed(): void
    {
        $builder = new SkillPassportBuilder;

        $pendingPassport = $builder->build(
            ['radar' => []],
            [
                [
                    'id' => 'task-sap',
                    'title' => 'Complete SAP FI/CO advanced training',
                    'status' => 'pending',
                    'skill_impacts' => ['SAP ERP'],
                    'has_evidence' => false,
                    'evidence_verified' => false,
                ],
            ],
        );

        $this->assertCount(1, $pendingPassport['items']);
        $this->assertSame('SAP ERP', $pendingPassport['items'][0]['skill']);
        $this->assertSame('missing', $pendingPassport['items'][0]['status']);
        $this->assertSame(0, $pendingPassport['verified']);

        $completedPassport = $builder->build(
            ['radar' => []],
            [
                [
                    'id' => 'task-sap',
                    'title' => 'Complete SAP FI/CO advanced training',
                    'status' => 'completed',
                    'skill_impacts' => ['SAP ERP'],
                    'has_evidence' => false,
                    'evidence_verified' => false,
                ],
            ],
        );

        $this->assertSame('waiting', $completedPassport['items'][0]['status']);
        $this->assertSame(0, $completedPassport['verified']);
    }

    public function test_ai_extracted_cv_skill_is_verified_without_a_task(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'skills' => [
                    ['name' => 'Financial Modeling', 'score' => 90],
                ],
                'radar' => [
                    ['label' => 'Financial Modeling', 'score' => 90, 'target' => 75],
                ],
            ],
            [],
        );

        $this->assertSame('verified', $passport['items'][0]['status']);
        $this->assertSame('AI CV analizi', $passport['items'][0]['evidence']);
        $this->assertSame('AI CV', $passport['items'][0]['type']);
        $this->assertSame('CV taramasında doğrulandı', $passport['items'][0]['impact']);
        $this->assertSame(1, $passport['verified']);
    }

    public function test_radar_only_skill_is_missing_even_when_score_meets_target(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'skills' => [],
                'radar' => [
                    ['label' => 'Financial Modeling', 'score' => 90, 'target' => 75],
                ],
            ],
            [],
        );

        $this->assertSame('missing', $passport['items'][0]['status']);
        $this->assertSame(0, $passport['verified']);
    }

    public function test_submitted_but_unverified_evidence_is_review_not_verified(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'radar' => [
                    ['label' => 'SAP ERP', 'score' => 50, 'target' => 75],
                ],
            ],
            [
                [
                    'id' => 'task-sap',
                    'title' => 'SAP proof',
                    'status' => 'pending',
                    'skill_impacts' => ['SAP ERP'],
                    'has_evidence' => true,
                    'evidence_pending' => true,
                    'evidence_verified' => false,
                ],
            ],
        );

        $this->assertSame('review', $passport['items'][0]['status']);
        $this->assertSame('AI kanıt incelemesi sürüyor', $passport['items'][0]['impact']);
        $this->assertSame(0, $passport['verified']);
    }

    public function test_rejected_evidence_is_red_missing_contract_with_feedback(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            ['radar' => [['label' => 'SAP ERP', 'score' => 40, 'target' => 75]]],
            [[
                'id' => 'task-sap',
                'title' => 'SAP proof',
                'status' => 'revision_required',
                'skill_impacts' => ['SAP ERP'],
                'has_evidence' => true,
                'evidence_verified' => false,
                'feedback' => 'Sertifika adı eşleşmedi.',
            ]],
        );

        $this->assertSame('revision', $passport['items'][0]['status']);
        $this->assertSame('Kanıt eksik', $passport['items'][0]['impact']);
        $this->assertSame('Sertifika adı eşleşmedi.', $passport['items'][0]['feedback']);
        $this->assertSame(0, $passport['verified']);
    }

    public function test_verified_skill_appears_when_evidence_was_accepted(): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'radar' => [
                    ['label' => 'Excel', 'score' => 60, 'target' => 80],
                ],
            ],
            [
                [
                    'id' => 'task-excel',
                    'title' => 'Excel dashboard',
                    'status' => 'completed',
                    'skill_impacts' => ['Excel'],
                    'has_evidence' => true,
                    'evidence_verified' => true,
                ],
            ],
        );

        $this->assertSame('verified', $passport['items'][0]['status']);
        $this->assertSame(1, $passport['verified']);
    }

    /**
     * @param  array<string, mixed>  $task
     */
    #[DataProvider('taskEvidenceStatusProvider')]
    public function test_task_evidence_status_matrix(array $task, string $expectedStatus, string $expectedImpact): void
    {
        $builder = new SkillPassportBuilder;

        $passport = $builder->build(
            [
                'skills' => [],
                'radar' => [
                    ['label' => 'SAP ERP', 'score' => 40, 'target' => 75],
                ],
            ],
            [[
                'id' => 'task-sap',
                'title' => 'SAP kanıt görevi',
                'skill_impacts' => ['SAP ERP'],
                'feedback' => null,
                ...$task,
            ]],
        );

        $item = $passport['items'][0];
        $this->assertSame($expectedStatus, $item['status']);
        $this->assertSame($expectedImpact, $item['impact']);
        $this->assertSame($expectedStatus === 'verified' ? 1 : 0, $passport['verified']);
    }

    /**
     * @return array<string, array{array<string, mixed>, string, string}>
     */
    public static function taskEvidenceStatusProvider(): array
    {
        return [
            'not started and no evidence' => [[
                'status' => 'pending',
                'has_evidence' => false,
                'evidence_pending' => false,
                'evidence_verified' => false,
            ], 'missing', 'Hedef: %75 · Kanıt eksik'],
            'task checked and evidence absent' => [[
                'status' => 'completed',
                'has_evidence' => false,
                'evidence_pending' => false,
                'evidence_verified' => false,
            ], 'waiting', 'Görev tamamlandı · kanıt bekleniyor'],
            'uploaded evidence pending AI review' => [[
                'status' => 'pending',
                'has_evidence' => true,
                'evidence_pending' => true,
                'evidence_verified' => false,
            ], 'review', 'AI kanıt incelemesi sürüyor'],
            'queued AI review without hydrated evidence flags' => [[
                'status' => 'queued',
                'has_evidence' => false,
                'evidence_pending' => false,
                'evidence_verified' => false,
            ], 'review', 'AI kanıt incelemesi sürüyor'],
            'rejected evidence needs replacement' => [[
                'status' => 'revision_required',
                'has_evidence' => true,
                'evidence_pending' => false,
                'evidence_verified' => false,
            ], 'revision', 'Kanıt eksik'],
            'accepted evidence is approved' => [[
                'status' => 'completed',
                'has_evidence' => true,
                'evidence_pending' => false,
                'evidence_verified' => true,
            ], 'verified', 'AI kanıt incelemesi tamamlandı'],
        ];
    }
}
