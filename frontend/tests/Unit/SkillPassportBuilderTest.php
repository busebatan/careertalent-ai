<?php

namespace Tests\Unit;

use App\Services\SkillPassportBuilder;
use PHPUnit\Framework\TestCase;

class SkillPassportBuilderTest extends TestCase
{
    public function test_builds_radar_items_with_task_mapping_and_status(): void
    {
        $builder = new SkillPassportBuilder();

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
                ],
                [
                    'id' => 'task-done',
                    'title' => 'Excel dashboard',
                    'status' => 'completed',
                    'skill_impacts' => ['Excel'],
                ],
            ],
        );

        $this->assertSame(62, $passport['score']);
        $this->assertSame(['SQL', 'Python'], $passport['gaps']);
        $this->assertCount(3, $passport['items']);

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
        $this->assertSame('review', $sql['status']);

        $this->assertNotNull($excel);
        $this->assertSame('verified', $excel['status']);
    }
}
