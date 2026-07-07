<?php

namespace App\Services;

use Illuminate\Support\Str;

class PanelLearningPlanner
{
    /**
     * @param  list<array<string, mixed>>  $baseResources
     * @param  array<string, mixed>|null  $target
     * @return list<array<string, mixed>>
     */
    public static function resources(array $baseResources, ?array $target): array
    {
        if (! $target) {
            return $baseResources;
        }

        $skills = self::targetSkills($target);
        $resources = [];

        foreach (array_slice($skills, 0, 6) as $skill) {
            $resources[] = self::resourceForSkill($skill, (string) ($target['title'] ?? 'Hedef rol'));
        }

        return self::dedupe([...$resources, ...$baseResources]);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return list<string>
     */
    public static function targetSkills(array $target): array
    {
        $required = $target['required_skills'] ?? null;
        if (is_array($required) && $required !== []) {
            return array_values(array_filter($required, 'is_string'));
        }

        $swot = $target['swot'] ?? null;
        if (is_array($swot) && isset($swot['weaknesses']) && is_array($swot['weaknesses']) && $swot['weaknesses'] !== []) {
            return array_values(array_filter($swot['weaknesses'], 'is_string'));
        }

        $summary = (string) ($target['gaps_summary'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(',', $summary))));

        return $parts !== [] ? $parts : ['Rol gereksinimleri', 'Portfolio kanıtı', 'CV anahtar kelimeleri'];
    }

    /**
     * @return array<string, mixed>
     */
    private static function resourceForSkill(string $skill, string $targetTitle): array
    {
        $key = self::normalize($skill);
        $catalog = [
            'sql' => ['SQLBolt Interactive SQL', 'SQLBolt', 'https://sqlbolt.com/', 'free', 'Ücretsiz', false, ['SQL']],
            'python' => ['Python for Everybody', 'Coursera', 'https://www.py4e.com/', 'free', 'Ücretsiz', true, ['Python']],
            'pandas' => ['Pandas Getting Started', 'pandas.pydata.org', 'https://pandas.pydata.org/docs/getting_started/index.html', 'free', 'Ücretsiz', false, ['Pandas', 'Python']],
            'power-bi' => ['Microsoft Power BI Learning Path', 'Microsoft Learn', 'https://learn.microsoft.com/training/powerplatform/power-bi', 'free', 'Ücretsiz', true, ['Power BI', 'DAX']],
            'excel' => ['Excel for Data Analysis', 'Microsoft Learn', 'https://support.microsoft.com/training/excel', 'free', 'Ücretsiz', false, ['Excel', 'Spreadsheet']],
            'tableau' => ['Tableau Free Training Videos', 'Tableau', 'https://www.tableau.com/learn/training', 'free', 'Ücretsiz', false, ['Tableau', 'Dashboard']],
            'veri-gorsellestirme' => ['Data Visualization Course', 'Kaggle Learn', 'https://www.kaggle.com/learn/data-visualization', 'free', 'Ücretsiz', false, ['Visualization', 'Dashboard']],
            'react' => ['React Learn', 'react.dev', 'https://react.dev/learn', 'free', 'Ücretsiz', false, ['React', 'JavaScript']],
            'javascript' => ['JavaScript Algorithms and Data Structures', 'freeCodeCamp', 'https://www.freecodecamp.org/learn/javascript-algorithms-and-data-structures-v8/', 'free', 'Ücretsiz', true, ['JavaScript']],
            'typescript' => ['TypeScript Handbook', 'Microsoft', 'https://www.typescriptlang.org/docs/handbook/intro.html', 'free', 'Ücretsiz', false, ['TypeScript']],
            'docker' => ['Docker Getting Started', 'Docker Docs', 'https://docs.docker.com/get-started/', 'free', 'Ücretsiz', false, ['Docker']],
            'rest-api' => ['API Fundamentals Student Expert', 'Postman Academy', 'https://academy.postman.com/', 'free', 'Ücretsiz', true, ['REST API', 'Postman']],
            'fastapi' => ['FastAPI Tutorial', 'FastAPI Docs', 'https://fastapi.tiangolo.com/tutorial/', 'free', 'Ücretsiz', false, ['FastAPI', 'Python']],
            'django' => ['Writing your first Django app', 'Django Docs', 'https://docs.djangoproject.com/en/stable/intro/tutorial01/', 'free', 'Ücretsiz', false, ['Django', 'Python']],
            'scikit-learn' => ['Intro to Machine Learning', 'Kaggle Learn', 'https://www.kaggle.com/learn/intro-to-machine-learning', 'free', 'Ücretsiz', false, ['Machine Learning', 'Scikit-learn']],
            'product-analytics' => ['Product Analytics Micro-Course', 'Amplitude Academy', 'https://academy.amplitude.com/', 'free', 'Ücretsiz', true, ['Product Analytics', 'Funnel', 'Cohort']],
            'a-b-test' => ['A/B Testing by Google', 'Udacity', 'https://www.udacity.com/course/ab-testing--ud257', 'free', 'Ücretsiz', false, ['A/B Test', 'Experiment']],
            'roadmap' => ['Product Strategy Basics', 'Product School', 'https://productschool.com/blog/product-strategy/product-strategy', 'free', 'Ücretsiz', false, ['Roadmap', 'Prioritization']],
            'figma' => ['Figma Learn', 'Figma', 'https://help.figma.com/hc/en-us/categories/360002051613-Learn-design', 'free', 'Ücretsiz', false, ['Figma', 'Prototype']],
            'git' => ['Git and GitHub for Beginners', 'freeCodeCamp', 'https://www.freecodecamp.org/news/git-and-github-for-beginners/', 'free', 'Ücretsiz', false, ['Git', 'GitHub']],
            'agile-scrum' => ['Agile with Atlassian Jira', 'Coursera', 'https://www.coursera.org/learn/agile-atlassian-jira', 'free', 'Ücretsiz', true, ['Agile', 'Scrum', 'Jira']],
            'jira' => ['Jira Fundamentals', 'Atlassian University', 'https://university.atlassian.com/student/path/815443-jira-fundamentals', 'free', 'Ücretsiz', false, ['Jira']],
            'iletisim' => ['Business Communication Skills', 'Coursera', 'https://www.coursera.org/learn/wharton-communication-skills', 'free', 'Ücretsiz', true, ['Communication', 'Stakeholder']],
        ];

        $match = $catalog[$key] ?? null;
        if (! $match) {
            $query = urlencode($skill.' '.$targetTitle.' course');
            $match = [
                $skill.' için hedef rol kaynağı',
                'Google Search',
                'https://www.google.com/search?q='.$query,
                'free',
                'Araştır',
                false,
                [$skill],
            ];
        }

        return [
            'id' => 'target-'.Str::slug($targetTitle.'-'.$skill),
            'title' => $match[0],
            'provider' => $match[1],
            'url' => $match[2],
            'price_type' => $match[3],
            'price_label' => $match[4],
            'price_range' => '0-500',
            'has_certificate' => $match[5],
            'skills' => $match[6],
        ];
    }

    private static function normalize(string $skill): string
    {
        return Str::slug(Str::lower(str_replace(['/', '+'], ' ', $skill)));
    }

    /**
     * @param  list<array<string, mixed>>  $resources
     * @return list<array<string, mixed>>
     */
    private static function dedupe(array $resources): array
    {
        $seen = [];
        $out = [];
        foreach ($resources as $resource) {
            $id = (string) ($resource['id'] ?? md5((string) ($resource['title'] ?? '')));
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            $out[] = $resource;
        }

        return $out;
    }
}
