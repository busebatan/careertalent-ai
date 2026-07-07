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
            $tasks[] = self::gapTask($gap, $title, $index + 1);
        }

        $tasks[] = self::task('portfolio-proof', $title.' portfolio kanıtı ekle', 'Hedef role uygun 1 ölçülebilir proje çıktısını GitHub/portfolio linkiyle görünür yap.');
        $tasks[] = self::task('application-plan', $title.' başvuru planı hazırla', '3 uygun ilan seç, CV varyantını hazırla ve takip tarihlerini görevlerine ekle.');

        return array_slice($tasks, 0, 6);
    }


    /**
     * @return array{id: string, title: string, done: bool, hint: string}
     */
    private static function gapTask(string $gap, string $targetTitle, int $index): array
    {
        $key = str($gap)->lower()->slug()->toString();
        $templates = [
            'sql' => ['SQL case pratiği yap', 'JOIN, GROUP BY ve window function içeren küçük bir case çöz; sonucu GitHub veya not olarak ekle.'],
            'python' => ['Python mini proje üret', 'CSV okuma, temizleme ve temel analiz yapan kısa bir notebook hazırla.'],
            'pandas' => ['Pandas veri temizleme kanıtı ekle', 'Eksik veri, gruplama ve pivot içeren bir notebook çıktısı oluştur.'],
            'power-bi' => ['Power BI dashboard taslağı hazırla', '3 KPI, 2 grafik ve kısa insight notu olan bir dashboard ekranı oluştur.'],
            'excel' => ['Excel analiz dosyası hazırla', 'Pivot table, lookup ve basit grafik içeren role uygun bir analiz dosyası oluştur.'],
            'tableau' => ['Tableau görselleştirme kanıtı ekle', 'Hedef role uygun veri setiyle en az 2 grafik yayınla veya ekran görüntüsü ekle.'],
            'veri-gorsellestirme' => ['Dashboard insight notu yaz', 'Hedef role uygun 3 metrik seç; grafik ve 5 maddelik yorumla karar önerisi üret.'],
            'product-analytics' => ['Product analytics funnel case çöz', 'Activation, retention ve conversion metriklerini örnek funnel üzerinde yorumla.'],
            'a-b-test' => ['A/B test notu hazırla', 'Hipotez, metrik, örneklem ve karar kriteri içeren 1 sayfalık test planı yaz.'],
            'roadmap' => ['Roadmap önceliklendirme çalışması yap', 'RICE veya MoSCoW ile 5 feature önceliklendir; karar gerekçelerini yaz.'],
            'rest-api' => ['REST API mini endpoint tasarla', 'Request/response örneği, hata durumları ve kısa test notu hazırla.'],
            'fastapi' => ['FastAPI endpoint kanıtı ekle', 'Pydantic model, route ve kısa API test çıktısı olan mini servis hazırla.'],
            'django' => ['Django CRUD pratiği yap', 'Model, view ve form içeren küçük bir CRUD akışı hazırla.'],
            'react' => ['React component pratiği yap', 'Form state, validation ve listeleme içeren küçük bir component oluştur.'],
            'javascript' => ['JavaScript problem seti tamamla', 'Array/object işlemleri ve async fetch içeren 3 küçük örnek hazırla.'],
            'typescript' => ['TypeScript type pratiği yap', 'Interface, union type ve generic kullanan küçük bir form/veri modeli oluştur.'],
            'docker' => ['Docker çalışma kanıtı ekle', 'Basit app için Dockerfile ve çalıştırma komutlarını notla.'],
            'git' => ['Git/GitHub portfolio düzeni kur', 'README, branch ve commit geçmişi okunabilir bir örnek repo hazırla.'],
            'agile-scrum' => ['Scrum senaryo notu hazırla', 'Backlog, sprint goal ve acceptance criteria içeren kısa bir örnek sprint planı yaz.'],
            'jira' => ['Jira ticket pratiği yap', 'Epic, story ve bug ticket örneklerini acceptance criteria ile hazırla.'],
            'figma' => ['Figma wireframe kanıtı ekle', 'Hedef role uygun tek ekran wireframe ve kısa kullanıcı akışı notu hazırla.'],
            'scikit-learn' => ['ML baseline notebook hazırla', 'Basit train/test split, model metriği ve sonuç yorumu içeren notebook üret.'],
            'iletisim' => ['Stakeholder iletişim notu yaz', 'Hedef rol için 1 sayfalık durum özeti, risk ve sonraki adım mesajı hazırla.'],
        ];

        $template = $templates[$key] ?? [$gap.' kanıtı oluştur', $gap.' için '.$targetTitle.' hedefiyle ilişkili mini proje, sertifika veya portfolio çıktısı hazırla.'];

        return self::task('gap-'.$index.'-'.md5($gap), $template[0], $template[1]);
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

        $required = $target['required_skills'] ?? null;
        if (is_array($required) && $required !== []) {
            return array_values(array_filter($required, 'is_string'));
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
