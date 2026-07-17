<?php

namespace Tests\Unit;

use App\Services\TaskReadinessCalculator;
use PHPUnit\Framework\TestCase;

class TaskReadinessCalculatorTest extends TestCase
{
    public function test_returns_zero_when_no_tasks_are_completed_without_baseline(): void
    {
        $tasks = [
            ['id' => '1', 'status' => 'pending'],
            ['id' => '2', 'status' => 'queued'],
            ['id' => '3', 'completed' => false],
        ];

        $this->assertSame(0, TaskReadinessCalculator::percent($tasks));
        $this->assertSame(0, TaskReadinessCalculator::doneCount($tasks));
    }

    public function test_returns_baseline_when_no_tasks_exist(): void
    {
        $this->assertSame(60, TaskReadinessCalculator::percent([], 60));
    }

    public function test_hybrid_progress_from_baseline(): void
    {
        $tasks = array_fill(0, 10, ['status' => 'pending']);
        $tasks[0]['status'] = 'completed';

        $this->assertSame(64, TaskReadinessCalculator::percent($tasks, 60));
    }

    public function test_reaches_one_hundred_when_all_tasks_done(): void
    {
        $tasks = array_fill(0, 10, ['status' => 'completed']);

        $this->assertSame(100, TaskReadinessCalculator::percent($tasks, 60));
        $this->assertTrue(TaskReadinessCalculator::isTargetReady($tasks, 60));
    }

    public function test_summary_resolves_baseline_from_career_ladder(): void
    {
        $target = ['title' => 'Chief Technology Officer (CTO)'];
        $analysis = [
            'career_ladder' => [
                ['title' => 'Chief Technology Officer (CTO)', 'readiness' => 20],
            ],
        ];
        $tasks = [['status' => 'pending']];

        $summary = TaskReadinessCalculator::summary($tasks, $target, $analysis);

        $this->assertSame(20, $summary['baseline']);
        $this->assertSame(20, $summary['readiness']);
        $this->assertFalse($summary['target_ready']);
    }

    public function test_counts_completed_and_accepted_tasks(): void
    {
        $tasks = [
            ['id' => '1', 'status' => 'completed'],
            ['id' => '2', 'status' => 'accepted'],
            ['id' => '3', 'status' => 'pending'],
            ['id' => '4', 'completed' => true],
        ];

        $this->assertSame(3, TaskReadinessCalculator::doneCount($tasks));
        $this->assertSame(75, TaskReadinessCalculator::percent($tasks));
    }
}
