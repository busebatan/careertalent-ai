<?php

namespace App\Data;

/**
 * CV AI yetenek radar demo verisi.
 */
class PanelSkillRadarData
{
    /**
     * @return array{overall_match: int, analyzed_at: string, target_role: string, skills: list<array{label: string, score: int, target: int}>}
     */
    public static function analysis(string $locale = 'tr'): array
    {
        $targetRole = $locale === 'en' ? 'Junior Data Analyst' : 'Junior Veri Analisti';

        $skills = $locale === 'en'
            ? [
                ['label' => 'SQL', 'score' => 85, 'target' => 90],
                ['label' => 'Python', 'score' => 72, 'target' => 85],
                ['label' => 'Excel', 'score' => 88, 'target' => 80],
                ['label' => 'Statistics', 'score' => 68, 'target' => 75],
                ['label' => 'Visualization', 'score' => 45, 'target' => 70],
                ['label' => 'Communication', 'score' => 76, 'target' => 80],
                ['label' => 'English', 'score' => 62, 'target' => 75],
                ['label' => 'Domain knowledge', 'score' => 58, 'target' => 65],
            ]
            : [
                ['label' => 'SQL', 'score' => 85, 'target' => 90],
                ['label' => 'Python', 'score' => 72, 'target' => 85],
                ['label' => 'Excel', 'score' => 88, 'target' => 80],
                ['label' => 'İstatistik', 'score' => 68, 'target' => 75],
                ['label' => 'Görselleştirme', 'score' => 45, 'target' => 70],
                ['label' => 'İletişim', 'score' => 76, 'target' => 80],
                ['label' => 'İngilizce', 'score' => 62, 'target' => 75],
                ['label' => 'Alan bilgisi', 'score' => 58, 'target' => 65],
            ];

        return [
            'overall_match' => 72,
            'analyzed_at' => $locale === 'en' ? 'Jun 28, 2026' : '28 Haz 2026',
            'target_role' => $targetRole,
            'skills' => $skills,
        ];
    }
}
