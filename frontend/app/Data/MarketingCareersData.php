<?php

namespace App\Data;

use Illuminate\Support\Facades\File;

/**
 * Marketing /meslekler sihirbazı demo kataloğu.
 * Gerçek veri geldiğinde yalnızca resources/data/careers-catalog.json güncellenir.
 */
class MarketingCareersData
{
    private static ?array $raw = null;

    public static function catalog(): array
    {
        if (self::$raw === null) {
            $path = resource_path('data/careers-catalog.json');
            self::$raw = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        }

        return self::$raw;
    }

    public static function forLocale(string $locale = 'tr'): array
    {
        $locale = in_array($locale, ['tr', 'en'], true) ? $locale : 'tr';
        $raw = self::catalog();

        $pickLabel = static function (array $labels) use ($locale): string {
            return $labels[$locale] ?? $labels['tr'] ?? '';
        };

        $salaryRanges = array_map(static function (array $range) use ($pickLabel) {
            return [
                'id' => $range['id'],
                'label' => $pickLabel($range['label']),
            ];
        }, $raw['salary_ranges']);

        $careers = array_map(static function (array $career) use ($pickLabel) {
            return [
                'id' => $career['id'],
                'label' => $pickLabel($career['label']),
                'sub_roles' => array_map(static function (array $sub) use ($pickLabel) {
                    return [
                        'id' => $sub['id'],
                        'label' => $pickLabel($sub['label']),
                        'target_role_ids' => $sub['target_role_ids'],
                    ];
                }, $career['sub_roles']),
                'target_roles' => array_map(static function (array $target) use ($pickLabel) {
                    return [
                        'id' => $target['id'],
                        'label' => $pickLabel($target['label']),
                        'skills' => array_map(static function (array $skill) use ($pickLabel) {
                            return [
                                'key' => $skill['key'],
                                'label' => $pickLabel($skill['label']),
                                'level' => $skill['level'],
                            ];
                        }, $target['skills']),
                    ];
                }, $career['target_roles']),
            ];
        }, $raw['careers']);

        return [
            'salary_ranges' => $salaryRanges,
            'careers' => $careers,
        ];
    }
}
