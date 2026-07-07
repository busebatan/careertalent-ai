<?php

namespace App\Services;

class PanelRoadmapPlanner
{
    /**
     * @param  array<string, mixed>  $baseStats
     * @param  list<array<string, mixed>>  $baseTasks
     * @param  array<string, mixed>|null  $target
     * @return array{stats: array<string, mixed>, tasks: list<array<string, mixed>>, target: ?array<string, mixed>}
     */
    public static function plan(array $baseStats, array $baseTasks, ?array $target): array
    {
        if (! $target) {
            return ['stats' => $baseStats, 'tasks' => $baseTasks, 'target' => null];
        }

        $stats = array_merge($baseStats, [
            'career' => (string) ($target['title'] ?? $baseStats['career'] ?? 'Hedef rol'),
            'readiness' => (int) ($target['readiness'] ?? $baseStats['readiness'] ?? 0),
        ]);

        return [
            'stats' => $stats,
            'tasks' => self::tasksForTarget($target),
            'target' => $target,
        ];
    }

    /**
     * @param  array<string, mixed>  $target
     * @return list<array<string, mixed>>
     */
    private static function tasksForTarget(array $target): array
    {
        $title = (string) ($target['title'] ?? 'Hedef rol');
        $source = (string) ($target['source'] ?? 'ladder');
        $weaknesses = self::weaknesses($target);
        $tasks = [];

        if ($source === 'job_url') {
            $tasks[] = self::task('job-url-review', 'İlan gereksinimlerini çıkar: '.$title, 'İlandaki zorunlu yetenekleri, araçları ve deneyim beklentisini listele.');
            $tasks[] = self::task('job-url-cv-keywords', 'CV anahtar kelimelerini ilana göre güncelle', 'CV özet, yetenek ve proje alanlarına ilandaki doğru kelimeleri ekle.');
        } elseif ($source === 'custom') {
            $tasks[] = self::task('custom-role-research', $title.' rol gereksinimlerini araştır', 'En az 3 ilanı karşılaştır; ortak yetenekleri ve araçları çıkar.');
            $tasks[] = self::task('custom-role-gap', $title.' için gap listesini netleştir', 'CV yeteneklerinle rol beklentilerini yan yana yaz.');
        } else {
            $tasks[] = self::task('role-cv-align', $title.' için CV uyumunu güncelle', 'CV özetini, proje maddelerini ve yetenek sırasını hedef role göre düzenle.');
        }

        foreach (array_slice($weaknesses, 0, 3) as $index => $gap) {
            $tasks[] = self::task('gap-'.($index + 1).'-'.md5($gap), $gap.' kanıtı oluştur', $gap.' için mini proje, sertifika veya portfolio çıktısı hazırla.');
        }

        $tasks[] = self::task('portfolio-proof', $title.' portfolio kanıtı ekle', 'Hedef role uygun 1 ölçülebilir proje çıktısını GitHub/portfolio linkiyle görünür yap.');
        $tasks[] = self::task('application-plan', $title.' başvuru planı hazırla', '3 uygun ilan seç, CV varyantını hazırla ve takip tarihlerini görevlerine ekle.');

        return array_slice($tasks, 0, 6);
    }

    /**
     * @param  array<string, mixed>  $target
     * @return list<string>
     */
    private static function weaknesses(array $target): array
    {
        $swot = $target['swot'] ?? null;
        $items = is_array($swot) && isset($swot['weaknesses']) && is_array($swot['weaknesses'])
            ? array_values(array_filter($swot['weaknesses'], 'is_string'))
            : [];

        if ($items !== [] && $items !== ['Belirgin eksik yok']) {
            return $items;
        }

        $summary = (string) ($target['gaps_summary'] ?? '');
        $parts = array_values(array_filter(array_map('trim', explode(',', $summary))));

        return $parts !== [] ? $parts : ['Rol gereksinimleri', 'Portfolio kanıtı', 'CV anahtar kelimeleri'];
    }

    /**
     * @return array{id: string, title: string, done: bool, hint: string}
     */
    private static function task(string $id, string $title, string $hint): array
    {
        return [
            'id' => $id,
            'title' => $title,
            'done' => false,
            'hint' => $hint,
        ];
    }
}
