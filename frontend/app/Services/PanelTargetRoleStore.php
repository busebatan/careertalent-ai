<?php

namespace App\Services;

use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

class PanelTargetRoleStore
{
    public const SESSION_KEY = 'panel_target_role';

    /**
     * @param  array<string, mixed>  $role
     */
    public static function putLadderRole(array $role): void
    {
        Session::put(self::SESSION_KEY, [
            'source' => 'ladder',
            'role_id' => (string) ($role['id'] ?? Str::slug((string) ($role['title'] ?? 'hedef-rol'))),
            'title' => (string) ($role['title'] ?? 'Hedef rol'),
            'readiness' => (int) ($role['readiness'] ?? 0),
            'gap_count' => (int) ($role['gap_count'] ?? 0),
            'gaps_summary' => (string) ($role['gaps_summary'] ?? ''),
            'weeks_estimate' => $role['weeks_estimate'] ?? null,
            'swot' => $role['swot'] ?? null,
            'selected_at' => now()->toIso8601String(),
        ]);
    }

    public static function putCustomRole(string $title): void
    {
        $cleanTitle = trim($title);

        Session::put(self::SESSION_KEY, [
            'source' => 'custom',
            'role_id' => 'custom-'.Str::slug($cleanTitle),
            'title' => $cleanTitle,
            'readiness' => 35,
            'gap_count' => 4,
            'gaps_summary' => 'Rol gereksinimleri, portfolio, CV uyumu, başvuru planı',
            'weeks_estimate' => '4–8 hafta',
            'selected_at' => now()->toIso8601String(),
        ]);
    }

    public static function putJobUrl(string $url): void
    {
        $cleanUrl = trim($url);
        $host = parse_url($cleanUrl, PHP_URL_HOST) ?: 'ilan';
        $host = preg_replace('/^www\./', '', (string) $host) ?: 'ilan';
        $path = trim((string) (parse_url($cleanUrl, PHP_URL_PATH) ?: ''), '/');
        $slug = $path !== '' ? Str::of(basename($path))->replace(['-', '_'], ' ')->title()->toString() : Str::of($host)->title()->toString();

        Session::put(self::SESSION_KEY, [
            'source' => 'job_url',
            'role_id' => 'job-'.Str::slug($host.'-'.$slug),
            'title' => 'İlan hedefi: '.$slug,
            'job_url' => $cleanUrl,
            'readiness' => 30,
            'gap_count' => 5,
            'gaps_summary' => 'İlan gereksinimleri, anahtar kelimeler, CV uyumu, başvuru hazırlığı',
            'weeks_estimate' => '2–4 hafta',
            'selected_at' => now()->toIso8601String(),
        ]);
    }

    /**
     * @return array<string, mixed>|null
     */
    public static function get(): ?array
    {
        $target = Session::get(self::SESSION_KEY);

        return is_array($target) ? $target : null;
    }

    public static function storageKey(): string
    {
        $target = self::get();
        if (! $target) {
            return 'panel-weekly-tasks-default';
        }

        return 'panel-weekly-tasks-'.Str::slug((string) ($target['role_id'] ?? $target['title'] ?? 'target'));
    }
}
