<?php

namespace App\Services;

use Illuminate\Support\Str;

/**
 * Demo ilan eşleştirme motoru. Faz 2'de FastAPI + gerçek ilan parse ile değişecek.
 */
class JobMatchAnalyzer
{
    /**
     * @param  list<string>  $userSkills
     */
    public function __construct(
        private readonly array $userSkills,
        private readonly int $readinessBase = 42,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     url: string,
     *     title: string,
     *     company: string,
     *     source: string,
     *     match_score: int,
     *     matched_skills: list<string>,
     *     missing_skills: list<string>,
     *     recommendation: string,
     *     analyzed_at: string
     * }
     */
    public function analyze(string $url): array
    {
        $normalizedUrl = $this->normalizeUrl($url);
        $catalog = $this->resolveCatalogEntry($normalizedUrl);
        $requiredSkills = $catalog['required_skills'];

        [$matched, $missing] = $this->skillOverlap($requiredSkills);
        $overlapRatio = count($requiredSkills) > 0
            ? count($matched) / count($requiredSkills)
            : 0.5;

        $matchScore = (int) round(min(95, max(18, ($overlapRatio * 70) + ($this->readinessBase * 0.3))));

        return [
            'id' => Str::uuid()->toString(),
            'url' => $normalizedUrl,
            'title' => $catalog['title'],
            'company' => $catalog['company'],
            'source' => $catalog['source'],
            'match_score' => $matchScore,
            'matched_skills' => $matched,
            'missing_skills' => $missing,
            'recommendation' => $this->recommendationFor($matchScore),
            'analyzed_at' => now()->toIso8601String(),
        ];
    }

    private function normalizeUrl(string $url): string
    {
        $trimmed = trim($url);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('URL boş olamaz.');
        }

        if (! preg_match('#^https?://#i', $trimmed)) {
            $trimmed = 'https://'.$trimmed;
        }

        if (filter_var($trimmed, FILTER_VALIDATE_URL) === false) {
            throw new \InvalidArgumentException('Geçerli bir ilan linki girin.');
        }

        $host = parse_url($trimmed, PHP_URL_HOST);
        if (! is_string($host) || $host === '' || ! str_contains($host, '.')) {
            throw new \InvalidArgumentException('Geçerli bir ilan linki girin.');
        }

        return $trimmed;
    }

    /**
     * @return array{title: string, company: string, source: string, required_skills: list<string>}
     */
    private function resolveCatalogEntry(string $url): array
    {
        $lower = Str::lower($url);

        foreach ($this->catalog() as $entry) {
            if (Str::contains($lower, Str::lower($entry['url_contains']))) {
                return [
                    'title' => $entry['title'],
                    'company' => $entry['company'],
                    'source' => $entry['source'],
                    'required_skills' => $entry['required_skills'],
                ];
            }
        }

        $host = parse_url($url, PHP_URL_HOST) ?? 'bilinmeyen';
        $source = $this->detectSource($host);
        $slug = $this->slugFromUrl($url);
        $requiredSkills = $this->inferSkillsFromText($lower);

        if ($requiredSkills === []) {
            $requiredSkills = ['SQL', 'Python', 'Excel', 'İletişim', 'Problem çözme'];
        }

        return [
            'title' => $slug !== '' ? Str::headline(str_replace('-', ' ', $slug)) : __('panel.job_matches.generic_title'),
            'company' => $this->prettyHost($host),
            'source' => $source,
            'required_skills' => $requiredSkills,
        ];
    }

    /**
     * @return list<array{url_contains: string, title: string, company: string, source: string, required_skills: list<string>}>
     */
    private function catalog(): array
    {
        return [
            [
                'url_contains' => 'kariyer.net/is-ilani/junior-veri-analisti',
                'title' => 'Junior Veri Analisti',
                'company' => 'FinTech A.Ş.',
                'source' => 'kariyer.net',
                'required_skills' => ['SQL', 'Python', 'Excel', 'Tableau', 'İngilizce'],
            ],
            [
                'url_contains' => 'kariyer.net/is-ilani/bi-analisti',
                'title' => 'BI Analisti',
                'company' => 'Perakende Grubu',
                'source' => 'kariyer.net',
                'required_skills' => ['SQL', 'Power BI', 'DAX', 'Excel', 'Veri modelleme'],
            ],
            [
                'url_contains' => 'linkedin.com/jobs/view',
                'title' => 'Data Analyst (Remote)',
                'company' => 'Global SaaS Co.',
                'source' => 'LinkedIn',
                'required_skills' => ['SQL', 'Python', 'Statistics', 'Tableau', 'İngilizce'],
            ],
            [
                'url_contains' => 'linkedin.com/jobs',
                'title' => 'Analytics Specialist',
                'company' => 'Tech Scale-up',
                'source' => 'LinkedIn',
                'required_skills' => ['SQL', 'Python', 'Pandas', 'A/B testing', 'İngilizce'],
            ],
        ];
    }

    private function detectSource(string $host): string
    {
        $host = Str::lower($host);

        if (Str::contains($host, 'kariyer.net')) {
            return 'kariyer.net';
        }

        if (Str::contains($host, 'linkedin.com')) {
            return 'LinkedIn';
        }

        if (Str::contains($host, 'indeed.')) {
            return 'Indeed';
        }

        return $this->prettyHost($host);
    }

    private function prettyHost(string $host): string
    {
        return Str::of($host)->replace('www.', '')->title()->toString();
    }

    private function slugFromUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        $segments = array_values(array_filter(explode('/', $path)));

        return Str::lower((string) end($segments));
    }

    /**
     * @return list<string>
     */
    private function inferSkillsFromText(string $text): array
    {
        $dictionary = [
            'sql' => 'SQL',
            'python' => 'Python',
            'pandas' => 'Pandas',
            'excel' => 'Excel',
            'tableau' => 'Tableau',
            'power bi' => 'Power BI',
            'power-bi' => 'Power BI',
            'dax' => 'DAX',
            'statistics' => 'Statistics',
            'istatistik' => 'Statistics',
            'veri analist' => 'Veri analizi',
            'data analyst' => 'Veri analizi',
            'machine learning' => 'Machine Learning',
            'r ' => 'R',
            'ingilizce' => 'İngilizce',
            'english' => 'İngilizce',
        ];

        $found = [];
        foreach ($dictionary as $needle => $skill) {
            if (Str::contains($text, $needle) && ! in_array($skill, $found, true)) {
                $found[] = $skill;
            }
        }

        return $found;
    }

    /**
     * @param  list<string>  $requiredSkills
     * @return array{0: list<string>, 1: list<string>}
     */
    private function skillOverlap(array $requiredSkills): array
    {
        $normalizedUser = collect($this->userSkills)
            ->map(fn (string $skill) => Str::lower($skill))
            ->all();

        $matched = [];
        $missing = [];

        foreach ($requiredSkills as $required) {
            $requiredLower = Str::lower($required);
            $has = collect($normalizedUser)->contains(function (string $userSkill) use ($requiredLower) {
                return Str::contains($userSkill, $requiredLower)
                    || Str::contains($requiredLower, $userSkill);
            });

            if ($has) {
                $matched[] = $required;
            } else {
                $missing[] = $required;
            }
        }

        return [$matched, $missing];
    }

    private function recommendationFor(int $score): string
    {
        if ($score >= 70) {
            return 'apply';
        }

        if ($score >= 50) {
            return 'prepare';
        }

        return 'wait';
    }
}
