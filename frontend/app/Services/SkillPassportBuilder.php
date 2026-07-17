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
        $cvVerifiedSkills = $this->cvVerifiedSkills($analysis);
        $items = [];

        foreach ($radar as $skill) {
            if (! is_array($skill) || ! isset($skill['label'])) {
                continue;
            }

            $label = (string) $skill['label'];
            $score = (int) ($skill['score'] ?? 0);
            $target = (int) ($skill['target'] ?? 0);
            $task = $this->taskForSkill($tasks, $label);
            $status = $this->skillStatus($label, $task, $verifiedSkills, $cvVerifiedSkills);
            $verifiedByTask = is_array($task) && $this->taskHasVerifiedEvidence($task);
            $verifiedByCv = in_array(mb_strtolower($label), $cvVerifiedSkills, true);

            $items[] = [
                'skill' => $label,
                'level' => '%'.$score,
                'score' => $score,
                'target' => $target,
                'evidence' => $verifiedByTask
                    ? (string) (($task['title'] ?? '') ?: 'AI kanıt incelemesi')
                    : ($verifiedByCv
                        ? 'AI CV analizi'
                    : (is_array($task) && ($task['title'] ?? '') !== ''
                        ? (string) $task['title']
                        : 'CV analizi · kanıt bekliyor')),
                'impact' => $verifiedByCv && ! $verifiedByTask
                    ? 'CV taramasında doğrulandı'
                    : $this->taskImpact($status, $target),
                'type' => $verifiedByTask ? 'AI evidence' : ($verifiedByCv ? 'AI CV' : 'AI radar'),
                'status' => $status,
                'task_id' => is_array($task) ? ($task['id'] ?? null) : null,
                'task_title' => is_array($task) ? (string) ($task['title'] ?? '') : '',
                'feedback' => is_array($task) ? ($task['feedback'] ?? null) : null,
            ];
        }

        foreach ($tasks as $task) {
            if (! is_array($task)) {
                continue;
            }

            foreach (is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : [] as $skill) {
                $label = (string) $skill;
                if ($this->itemExists($items, $label)) {
                    continue;
                }

                $status = $this->skillStatus($label, $task, $verifiedSkills, $cvVerifiedSkills);

                $items[] = [
                    'skill' => $label,
                    'level' => $status === 'verified' ? '%100' : '%0',
                    'score' => $status === 'verified' ? 100 : 0,
                    'target' => 100,
                    'evidence' => (string) ($task['title'] ?? ''),
                    'impact' => in_array(mb_strtolower($label), $cvVerifiedSkills, true)
                        ? 'CV taramasında doğrulandı'
                        : $this->taskImpact($status),
                    'type' => $status === 'verified'
                        ? (in_array(mb_strtolower($label), $verifiedSkills, true) ? 'AI evidence' : 'AI CV')
                        : 'AI radar',
                    'status' => $status,
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

        $verified = count(array_filter($items, static fn ($item) => ($item['status'] ?? '') === 'verified'));

        return [
            'score' => $score,
            'verified' => $verified,
            'total' => max(count($radar), count($items)),
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
            if (! is_array($task) || ! $this->taskHasVerifiedEvidence($task)) {
                continue;
            }
            foreach (is_array($task['skill_impacts'] ?? null) ? $task['skill_impacts'] : [] as $skill) {
                $skills[] = mb_strtolower((string) $skill);
            }
        }

        return array_values(array_unique($skills));
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @return list<string>
     */
    private function cvVerifiedSkills(array $analysis): array
    {
        $skills = [];
        foreach (is_array($analysis['skills'] ?? null) ? $analysis['skills'] : [] as $skill) {
            $name = is_array($skill) ? ($skill['name'] ?? null) : $skill;
            if (is_string($name) && trim($name) !== '') {
                $skills[] = mb_strtolower(trim($name));
            }
        }

        return array_values(array_unique($skills));
    }

    /**
     * @param  array<string, mixed>  $task
     */
    private function taskHasVerifiedEvidence(array $task): bool
    {
        return (bool) ($task['evidence_verified'] ?? false);
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

            if ($this->taskHasVerifiedEvidence($task)) {
                return $task;
            }

            $pending ??= $task;
        }

        return $pending;
    }

    /**
     * @param  list<string>  $verifiedSkills
     */
    private function skillStatus(string $skill, ?array $task, array $verifiedSkills, array $cvVerifiedSkills): string
    {
        if (in_array(mb_strtolower($skill), $verifiedSkills, true)
            || in_array(mb_strtolower($skill), $cvVerifiedSkills, true)) {
            return 'verified';
        }

        if (is_array($task)) {
            if ($this->taskHasVerifiedEvidence($task)) {
                return 'verified';
            }

            $status = (string) ($task['status'] ?? 'pending');

            if ($status === 'revision_required') {
                return 'revision';
            }

            if (($task['evidence_pending'] ?? false) || (($task['has_evidence'] ?? false) && ! $this->taskHasVerifiedEvidence($task)) || in_array($status, ['reviewing', 'queued'], true)) {
                return 'review';
            }

            if ($status === 'completed') {
                return 'waiting';
            }

            return 'missing';
        }

        return 'missing';
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

    private function taskImpact(string $status, ?int $target = null): string
    {
        return match ($status) {
            'verified' => 'AI kanıt incelemesi tamamlandı',
            'review' => 'AI kanıt incelemesi sürüyor',
            'revision' => 'Kanıt eksik',
            'waiting' => 'Görev tamamlandı · kanıt bekleniyor',
            default => $target === null ? 'Kanıt eksik' : 'Hedef: %'.$target.' · Kanıt eksik',
        };
    }
}
