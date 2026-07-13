<?php

namespace App\Services;

use Illuminate\Support\Str;

class PanelTargetRoleStore
{
    public const SESSION_KEY = 'panel_target_role';

    /**
     * @param  array<string, mixed>  $role
     * @return array<string, mixed>
     */
    public static function putLadderRole(array $role): array
    {
        return self::persist([
            'source' => 'ladder',
            'role_id' => (string) ($role['id'] ?? Str::slug((string) ($role['title'] ?? 'hedef-rol'))),
            'title' => (string) ($role['title'] ?? 'Hedef rol'),
            'readiness' => (int) ($role['readiness'] ?? 0),
            'gap_count' => (int) ($role['gap_count'] ?? 0),
            'gaps_summary' => (string) ($role['gaps_summary'] ?? ''),
            'weeks_estimate' => $role['weeks_estimate'] ?? null,
            'swot' => $role['swot'] ?? null,
            'required_skills' => self::skillsFromRole($role),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function putCustomRole(string $title): array
    {
        $cleanTitle = trim($title);

        return self::persist([
            'source' => 'custom',
            'role_id' => 'custom-'.Str::slug($cleanTitle),
            'title' => $cleanTitle,
            'readiness' => 35,
            'gap_count' => 4,
            'gaps_summary' => 'Rol gereksinimleri, portfolio, CV uyumu, başvuru planı',
            'weeks_estimate' => '4–8 hafta',
            'required_skills' => ['Rol gereksinimleri', 'Portfolio kanıtı', 'CV anahtar kelimeleri'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public static function putJobUrl(string $url): array
    {
        $cleanUrl = trim($url);
        $parsed = self::parseJobListing($cleanUrl);
        $title = (string) ($parsed['title'] ?? self::titleFromUrl($cleanUrl));
        $host = parse_url($cleanUrl, PHP_URL_HOST) ?: 'ilan';

        return self::persist([
            'source' => 'job_url',
            'role_id' => (string) ($parsed['role_id'] ?? 'job-'.Str::slug($host.'-'.$title)),
            'title' => 'İlan hedefi: '.$title,
            'job_url' => (string) ($parsed['url'] ?? $cleanUrl),
            'readiness' => 30,
            'gap_count' => max(3, count($parsed['required_skills'] ?? [])),
            'gaps_summary' => implode(', ', $parsed['required_skills'] ?? ['İlan gereksinimleri', 'anahtar kelimeler', 'CV uyumu']),
            'weeks_estimate' => '2–4 hafta',
            'parsed_from' => $parsed['parsed_from'] ?? 'url',
            'required_skills' => $parsed['required_skills'] ?? ['İlan gereksinimleri', 'CV anahtar kelimeleri'],
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $result = app(CareerTalentApiClient::class)->careerTargets();
        $body = $result['body'] ?? null;
        $targets = is_array($body) && array_is_list($body) ? $body : ($body['targets'] ?? []);
        if (! ($result['ok'] ?? false) || ! is_array($targets)) {
            return null;
        }

        $eligible = array_values(array_filter($targets, static fn ($target) => is_array($target) && in_array($target['status'] ?? null, ['active', 'ready', 'queued'], true)));
        usort($eligible, static function (array $left, array $right): int {
            $rank = ['active' => 0, 'ready' => 1, 'queued' => 2];
            return ($rank[$left['status'] ?? 'queued'] ?? 9) <=> ($rank[$right['status'] ?? 'queued'] ?? 9);
        });

        if ($eligible !== []) {
            return $eligible[0];
        }

        return null;
    }

    public static function storageKey(): string
    {
        $target = self::get();
        if (! $target) {
            return 'panel-weekly-tasks-default';
        }

        return 'panel-weekly-tasks-'.Str::slug((string) ($target['role_id'] ?? $target['title'] ?? 'target'));
    }

    /**
     * @param  array<string, mixed>  $target
     * @return array<string, mixed>
     */
    private static function persist(array $target): array
    {
        $result = app(CareerTalentApiClient::class)->createCareerTarget(array_filter([
            'title' => $target['title'] ?? null,
            'source' => $target['source'] ?? 'custom',
            'job_url' => $target['job_url'] ?? null,
        ], static fn ($value) => $value !== null));
        $apiTarget = $result['body']['target'] ?? $result['body'] ?? null;
        if (($result['ok'] ?? false) && is_array($apiTarget)) {
            return $apiTarget;
        }

        return [];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parseJobListing(string $url): array
    {
        $result = app(CareerTalentApiClient::class)->parseJobListing($url);
        $body = $result['body'] ?? null;

        return (($result['ok'] ?? false) && is_array($body)) ? $body : [];
    }

    private static function titleFromUrl(string $url): string
    {
        $path = trim((string) (parse_url($url, PHP_URL_PATH) ?: ''), '/');
        $slug = $path !== '' ? basename($path) : (parse_url($url, PHP_URL_HOST) ?: 'İş ilanı');

        return Str::of($slug)->replace(['-', '_'], ' ')->title()->toString();
    }

    /**
     * @param  array<string, mixed>  $role
     * @return list<string>
     */
    private static function skillsFromRole(array $role): array
    {
        $swot = $role['swot'] ?? null;
        if (is_array($swot) && isset($swot['weaknesses']) && is_array($swot['weaknesses'])) {
            return array_values(array_filter($swot['weaknesses'], 'is_string'));
        }

        $summary = (string) ($role['gaps_summary'] ?? '');

        return array_values(array_filter(array_map('trim', explode(',', $summary))));
    }
}
