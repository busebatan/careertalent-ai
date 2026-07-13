<?php

namespace App\Services;

class SkillPassportBuilder
{
    /**
     * @param  array<string, mixed>  $analysis
     * @param  list<array<string, mixed>>  $tasks
     * @return array{score: int, verified: int, total: int, gaps: list<string>, items: list<array<string, mixed>>}
     */
    public function build(array $analysis, array $tasks): array
    {
        $radar = is_array($analysis['radar'] ?? null) ? $analysis['radar'] : [];
        $verifiedSkills = $this->verifiedSkills($tasks);
        $items = [];

        foreach ($radar as $skill) {
            if (! is_array($skill) || ! isset($skill['label'])) {
                continue;
            }

            $label = (string) $skill['label'];
            $score = (int) ($skill['score'] ?? 0);
            $target = (int) ($skill['target'] ?? 0);
            $task = $this->taskForSkill($tasks, $label);
            $status = $this->skillStatus($label, $score, $target, $task, $verifiedSkills);

            $items[] = [
                'skill' => $label,
                'level' => '%'.$score,
                'score' => $score,
                'target' => $target,
                'evidence' => $status === 'verified'
                    ? (string) (($task['title'] ?? '') ?: 'AI kanıt incelemesi')
                    : ($score >= $target ? 'AI CV analizi' : 'CV analizi · gap'),
                'impact' => 'Hedef: %'.$target,
                'type' => $status === 'verified' ? 'AI evidence' : 'AI radar',
                'status' => $status,
                'task_id' => is_array($task) ? ($task['id'] ?? null) : null,
                'task_title' => is_array($task) ? (string) ($task['title'] ?? '') : '',
                'feedback' => is_array($task) ? ($task['feedback'] ?? null) : null,
            ];
        }

        foreach ($tasks as $task) {
            if (! is_array($task) || ($task['status'] ?? null) !== 'completed') {
                continue;
            }

            foreach (is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : [] as $skill) {
                $label = (string) $skill;
                if ($this->itemExists($items, $label)) {
                    continue;
                }

                $items[] = [
                    'skill' => $label,
                    'level' => 'Doğrulandı',
                    'score' => 100,
                    'target' => 100,
                    'evidence' => (string) ($task['title'] ?? ''),
                    'impact' => 'AI kanıt incelemesi tamamlandı',
                    'type' => 'AI evidence',
                    'status' => 'verified',
                    'task_id' => $task['id'] ?? null,
                    'task_title' => (string) ($task['title'] ?? ''),
                    'feedback' => $task['feedback'] ?? null,
                ];
            }
        }

        $score = $radar === []
            ? 0
            : (int) round(array_sum(array_map(static fn ($item) => (int) ($item['score'] ?? 0), $radar)) / count($radar));

        $gaps = array_values(array_filter(array_map(static function ($item): ?string {
            if (! is_array($item) || (int) ($item['target'] ?? 0) <= (int) ($item['score'] ?? 0)) {
                return null;
            }

            return (string) ($item['label'] ?? '');
        }, $radar)));

        $completed = count(array_filter($tasks, static fn ($task) => is_array($task) && ($task['status'] ?? null) === 'completed'));

        return [
            'score' => $score,
            'verified' => $completed,
            'total' => max(count($radar), count($tasks)),
            'gaps' => $gaps,
            'items' => $items,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return list<string>
     */
    private function verifiedSkills(array $tasks): array
    {
        $skills = [];
        foreach ($tasks as $task) {
            if (! is_array($task) || ($task['status'] ?? null) !== 'completed') {
                continue;
            }
            foreach (is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : [] as $skill) {
                $skills[] = mb_strtolower((string) $skill);
            }
        }

        return array_values(array_unique($skills));
    }

    /**
     * @param  list<array<string, mixed>>  $tasks
     * @return array<string, mixed>|null
     */
    private function taskForSkill(array $tasks, string $skill): ?array
    {
        $needle = mb_strtolower($skill);
        $pending = null;

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            $impacts = array_map(static fn ($item) => mb_strtolower((string) $item), is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : []);
            if (! in_array($needle, $impacts, true)) {
                continue;
            }

            if (($task['status'] ?? null) === 'completed') {
                return $task;
            }

            $pending ??= $task;
        }

        return $pending;
    }

    /**
     * @param  list<string>  $verifiedSkills
     */
    private function skillStatus(string $skill, int $score, int $target, ?array $task, array $verifiedSkills): string
    {
        if (in_array(mb_strtolower($skill), $verifiedSkills, true)) {
            return 'verified';
        }

        if (is_array($task)) {
            $status = (string) ($task['status'] ?? 'pending');
            if (in_array($status, ['completed', 'accepted'], true)) {
                return 'verified';
            }
            if (in_array($status, ['pending', 'reviewing', 'queued'], true)) {
                return 'review';
            }
            if ($status === 'revision_required') {
                return 'revision';
            }
        }

        return $score >= $target ? 'review' : 'missing';
    }

    /**
     * @param  list<array<string, mixed>>  $items
     */
    private function itemExists(array $items, string $skill): bool
    {
        $needle = mb_strtolower($skill);
        foreach ($items as $item) {
            if (mb_strtolower((string) ($item['skill'] ?? '')) === $needle) {
                return true;
            }
        }

        return false;
    }
}
