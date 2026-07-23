<?php

namespace App\Services;

use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;

class HarvardCvPdfRenderer
{
    /** @var array<string, array<string, int>> */
    private const OPTIONAL_FIELDS = [
        'awards' => ['title' => 300, 'issuer' => 300, 'date' => 120, 'details' => 2000],
        'volunteer' => ['organization' => 300, 'location' => 180, 'role' => 300, 'start' => 80, 'end' => 80],
        'publications' => ['title' => 300, 'publisher' => 300, 'date' => 120, 'link' => 500, 'description' => 3000],
        'courses' => ['name' => 300, 'institution' => 300, 'date' => 120, 'description' => 3000],
        'languages' => ['language' => 180, 'level' => 180],
        'leadership' => ['organization' => 300, 'location' => 180, 'role' => 300, 'start' => 80, 'end' => 80],
        'affiliations' => ['name' => 300, 'role' => 300, 'start' => 80, 'end' => 80],
        'references' => ['name' => 300, 'title' => 300, 'organization' => 300, 'contact' => 500],
        'interests' => ['items' => 2000],
        'research' => ['title' => 300, 'institution' => 300, 'start' => 80, 'end' => 80, 'description' => 3000],
        'additional' => ['body' => 4000],
    ];

    /**
     * @param  array<string, mixed>  $locales
     */
    public function download(array $locales, string $language): Response
    {
        $language = $this->normalizeLanguage($language);
        $cv = $this->normalizeLocale($locales[$language] ?? []);
        $filename = $this->filename($cv);

        return Pdf::driver('chrome')
            ->view('pdf.harvard-cv', [
                'cv' => $cv,
                'language' => $language,
            ])
            ->format('a4')
            ->margins(12, 12, 12, 12, 'mm')
            ->download($filename)
            ->toResponse(request());
    }

    /**
     * Explicit operational smoke: it launches Chrome and returns raw PDF bytes.
     * Do not call from normal feature tests.
     */
    public function smoke(): string
    {
        $response = $this->download([
            'en' => [
                'personal' => [
                    'full_name' => 'CareerTalent PDF Smoke Test',
                    'email' => 'smoke@example.test',
                ],
                'summary' => 'Server-side Chrome PDF rendering smoke test.',
            ],
        ], 'en');

        return $response->getContent();
    }

    /**
     * @param  array<string, mixed>  $cv
     */
    private function filename(array $cv): string
    {
        $name = Str::slug($cv['personal']['full_name'] ?? '');

        return ($name !== '' ? $name : 'cv').'.pdf';
    }

    private function normalizeLanguage(string $language): string
    {
        return in_array($language, ['tr', 'en'], true) ? $language : 'tr';
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeLocale(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $personal = is_array($value['personal'] ?? null) ? $value['personal'] : [];
        $enabledOptional = array_values(array_unique(array_filter(
            is_array($value['enabledOptional'] ?? null) ? $value['enabledOptional'] : [],
            fn ($key): bool => is_string($key) && array_key_exists($key, self::OPTIONAL_FIELDS),
        )));

        return [
            'personal' => [
                'full_name' => $this->text($personal['full_name'] ?? '', 180),
                'email' => $this->text($personal['email'] ?? '', 254),
                'phone' => $this->text($personal['phone'] ?? '', 80),
                'location' => $this->text($personal['location'] ?? '', 180),
                'linkedin' => $this->text($personal['linkedin'] ?? '', 254),
                'summary' => $this->text($personal['summary'] ?? $value['summary'] ?? '', 4000),
            ],
            'education' => $this->entries($value['education'] ?? [], [
                'institution' => 300, 'location' => 180, 'degree' => 300,
                'start' => 80, 'end' => 80, 'details' => 2000,
            ]),
            'experience' => $this->experience($value['experience'] ?? []),
            'skills' => $this->entries($value['skills'] ?? [], ['category' => 180, 'items' => 2000]),
            'projects' => $this->entries($value['projects'] ?? [], [
                'name' => 300, 'link' => 500, 'start' => 80, 'end' => 80, 'description' => 3000,
            ]),
            'certificates' => $this->entries($value['certificates'] ?? [], ['name' => 300, 'issuer' => 300, 'date' => 120]),
            'enabledOptional' => $enabledOptional,
            'optional' => $this->optionalEntries($value['optional'] ?? [], $enabledOptional),
        ];
    }

    /**
     * @param  list<string>  $enabled
     * @return array<string, list<array<string, mixed>>>
     */
    private function optionalEntries(mixed $value, array $enabled): array
    {
        $value = is_array($value) ? $value : [];
        $optional = [];

        foreach ($enabled as $key) {
            $entries = [];
            $sourceEntries = is_array($value[$key] ?? null) ? array_slice($value[$key], 0, 100) : [];
            foreach ($sourceEntries as $source) {
                if (! is_array($source)) {
                    continue;
                }
                $entry = [];
                foreach (self::OPTIONAL_FIELDS[$key] as $field => $limit) {
                    $entry[$field] = $this->text($source[$field] ?? '', $limit);
                }
                if (in_array($key, ['volunteer', 'leadership'], true)) {
                    $bullets = [];
                    foreach (is_array($source['bullets'] ?? null) ? array_slice($source['bullets'], 0, 50) : [] as $bullet) {
                        $bullet = $this->text($bullet, 2000);
                        if ($bullet !== '') {
                            $bullets[] = $bullet;
                        }
                    }
                    $entry['bullets'] = $bullets;
                }
                $stringValues = array_filter($entry, 'is_string');
                if (implode('', $stringValues) !== '' || ($entry['bullets'] ?? []) !== []) {
                    $entries[] = $entry;
                }
            }
            if ($entries !== []) {
                $optional[$key] = $entries;
            }
        }

        return $optional;
    }

    /**
     * @param  array<string, int>  $fields
     * @return list<array<string, string>>
     */
    private function entries(mixed $value, array $fields): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];
        foreach (array_slice($value, 0, 100) as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $normalized = [];
            foreach ($fields as $field => $limit) {
                $normalized[$field] = $this->text($entry[$field] ?? '', $limit);
            }
            if (implode('', $normalized) !== '') {
                $entries[] = $normalized;
            }
        }

        return $entries;
    }

    /**
     * @return list<array{organization: string, location: string, title: string, start: string, end: string, bullets: list<string>}>
     */
    private function experience(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        $entries = [];
        foreach (array_slice($value, 0, 100) as $entry) {
            if (! is_array($entry)) {
                continue;
            }
            $bullets = [];
            foreach (is_array($entry['bullets'] ?? null) ? array_slice($entry['bullets'], 0, 50) : [] as $bullet) {
                $bullet = $this->text($bullet, 2000);
                if ($bullet !== '') {
                    $bullets[] = $bullet;
                }
            }
            $normalized = [
                'organization' => $this->text($entry['organization'] ?? '', 300),
                'location' => $this->text($entry['location'] ?? '', 180),
                'title' => $this->text($entry['title'] ?? '', 300),
                'start' => $this->text($entry['start'] ?? '', 80),
                'end' => $this->text($entry['end'] ?? '', 80),
                'bullets' => $bullets,
            ];
            if (implode('', array_filter($normalized, 'is_string')) !== '' || $bullets !== []) {
                $entries[] = $normalized;
            }
        }

        return $entries;
    }

    private function text(mixed $value, int $limit): string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return '';
        }

        return Str::limit(trim((string) $value), $limit, '');
    }
}
