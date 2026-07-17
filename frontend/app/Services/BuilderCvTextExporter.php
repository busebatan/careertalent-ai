<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * CV oluşturucu JSON taslağını analiz metnine çevirir.
 */
class BuilderCvTextExporter
{
    /** @param array<string, array<string, mixed>> $locales */
    public static function hasCareerContent(array $locales, string $locale = 'tr'): bool
    {
        $cv = $locales[$locale] ?? $locales['tr'] ?? [];
        $personal = $cv['personal'] ?? [];
        if (mb_strlen(trim((string) ($personal['summary'] ?? ''))) >= 30) {
            return true;
        }

        foreach (['experience', 'education', 'skills', 'projects', 'certificates'] as $section) {
            foreach ($cv[$section] ?? [] as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $values = $item;
                if ($section === 'experience') {
                    $values[] = implode(' ', array_filter($item['bullets'] ?? [], 'is_string'));
                }
                foreach ($values as $value) {
                    if (is_string($value) && trim($value) !== '') {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param  array<string, array<string, mixed>>  $locales
     */
    public static function toText(array $locales, string $locale = 'tr'): string
    {
        $cv = $locales[$locale] ?? $locales['tr'] ?? [];
        $lines = [];

        $personal = $cv['personal'] ?? [];
        if ($name = trim((string) ($personal['full_name'] ?? ''))) {
            $lines[] = $name;
        }
        foreach (['email', 'phone', 'location', 'linkedin'] as $field) {
            if ($value = trim((string) ($personal[$field] ?? ''))) {
                $lines[] = $value;
            }
        }
        if ($summary = trim((string) ($personal['summary'] ?? ''))) {
            $lines[] = 'Özet: '.$summary;
        }

        foreach ($cv['experience'] ?? [] as $exp) {
            $lines[] = implode(' | ', array_filter([
                trim((string) ($exp['title'] ?? '')),
                trim((string) ($exp['organization'] ?? '')),
                trim((string) ($exp['start'] ?? '')).'-'.trim((string) ($exp['end'] ?? '')),
            ]));
            foreach ($exp['bullets'] ?? [] as $bullet) {
                if ($bullet = trim((string) $bullet)) {
                    $lines[] = '- '.$bullet;
                }
            }
        }

        foreach ($cv['education'] ?? [] as $edu) {
            $lines[] = implode(' | ', array_filter([
                trim((string) ($edu['degree'] ?? '')),
                trim((string) ($edu['institution'] ?? '')),
                trim((string) ($edu['details'] ?? '')),
            ]));
        }

        foreach ($cv['skills'] ?? [] as $skill) {
            $category = trim((string) ($skill['category'] ?? ''));
            $items = trim((string) ($skill['items'] ?? ''));
            if ($items !== '') {
                $lines[] = ($category !== '' ? $category.': ' : '').$items;
            }
        }

        foreach ($cv['projects'] ?? [] as $project) {
            $lines[] = implode(' | ', array_filter([
                trim((string) ($project['name'] ?? '')),
                trim((string) ($project['description'] ?? '')),
            ]));
        }

        foreach ($cv['certificates'] ?? [] as $cert) {
            $lines[] = implode(' | ', array_filter([
                trim((string) ($cert['name'] ?? '')),
                trim((string) ($cert['issuer'] ?? '')),
                trim((string) ($cert['date'] ?? '')),
            ]));
        }

        return trim(implode("\n", array_filter($lines)));
    }

    public static function fileName(array $locales, string $locale = 'tr'): string
    {
        $cv = $locales[$locale] ?? $locales['tr'] ?? [];
        $rawName = trim((string) ($cv['personal']['full_name'] ?? 'cv'));

        return Str::slug($rawName ?: 'cv').'-builder.json';
    }
}
