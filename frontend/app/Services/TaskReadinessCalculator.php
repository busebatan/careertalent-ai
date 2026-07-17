<?php

namespace App\Services;

class TaskReadinessCalculator
{
    /** @param list<array<string, mixed>> $tasks */
    public static function summary(array $tasks, ?array $target, ?array $analysis = null): array
    {
        $baseline = self::baselineFromTarget($target, $analysis);
        $done = self::doneCount($tasks);
        $total = count($tasks);

        return [
            'baseline' => $baseline,
            'readiness' => self::percent($tasks, $baseline),
            'done' => $done,
            'total' => $total,
            'target_ready' => self::isTargetReady($tasks, $baseline),
        ];
    }

    /** @param list<array<string, mixed>> $tasks */
    public static function percent(array $tasks, int $baseline = 0): int
    {
        $baseline = self::clamp($baseline);
        $total = count($tasks);

        if ($total === 0) {
            return $baseline;
        }

        $progress = self::doneCount($tasks) / $total;

        return (int) round($baseline + ((100 - $baseline) * $progress));
    }

    /** @param array<string, mixed>|null $target */
    /** @param array<string, mixed>|null $analysis */
    public static function baselineFromTarget(?array $target, ?array $analysis = null): int
    {
        if (! is_array($target)) {
            return 0;
        }

        if (isset($target['readiness'])) {
            return self::clamp((int) $target['readiness']);
        }

        $title = (string) ($target['title'] ?? '');
        if ($title === '' || ! is_array($analysis)) {
            return 0;
        }

        foreach (is_array($analysis['career_ladder'] ?? null) ? $analysis['career_ladder'] : [] as $role) {
            if (is_array($role) && ($role['title'] ?? null) === $title) {
                return self::clamp((int) ($role['readiness'] ?? 0));
            }
        }

        return 0;
    }

    /** @param list<array<string, mixed>> $tasks */
    public static function isTargetReady(array $tasks, int $baseline = 0): bool
    {
        $total = count($tasks);

        if ($baseline <= 0 || $total === 0) {
            return false;
        }

        return self::doneCount($tasks) === $total;
    }

    /** @param list<array<string, mixed>> $tasks */
    public static function doneCount(array $tasks): int
    {
        return count(array_filter($tasks, static fn (array $task): bool => self::isDone($task)));
    }

    /** @param array<string, mixed> $task */
    public static function isDone(array $task): bool
    {
        if (array_key_exists('done', $task)) {
            return (bool) $task['done'];
        }

        if (array_key_exists('completed', $task)) {
            return (bool) $task['completed'];
        }

        return in_array($task['status'] ?? '', ['completed', 'accepted'], true);
    }

    private static function clamp(int $value): int
    {
        return max(0, min(100, $value));
    }
}
