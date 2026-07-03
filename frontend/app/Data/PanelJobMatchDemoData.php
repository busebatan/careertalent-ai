<?php

namespace App\Data;

/**
 * İlan eşleştirme sayfası demo verisi.
 */
class PanelJobMatchDemoData
{
    /**
     * @return list<string>
     */
    public static function userSkills(): array
    {
        return ['SQL', 'Python', 'Pandas', 'Excel', 'Tableau', 'Statistics', 'İngilizce'];
    }

    /**
     * @return list<string>
     */
    public static function seedUrls(): array
    {
        return [
            'https://www.kariyer.net/is-ilani/junior-veri-analisti-fintech',
            'https://www.linkedin.com/jobs/view/data-analyst-remote-123456',
        ];
    }
}
