<?php

namespace App\Data;

/**
 * Sprint 1–2 panel demo verisi. API bağlanınca FastAPI'den gelecek.
 */
class PanelDemoData
{
    public static function stats(): array
    {
        return [
            'readiness' => 42,
            'career' => 'Veri Analisti',
            'weekly_tasks_total' => 3,
            'weekly_tasks_done' => 1,
        ];
    }

    /**
     * @return list<array{id: string, title: string, provider: string, url: string, price_type: string, price_label: string, has_certificate: bool, skills: list<string>}>
     */
    public static function learningResources(): array
    {
        return [
            [
                'id' => '1',
                'title' => 'Google Data Analytics Certificate',
                'provider' => 'Coursera',
                'url' => 'https://www.coursera.org/professional-certificates/google-data-analytics',
                'price_type' => 'paid',
                'price_label' => '1.400 ₺/ay',
                'price_range' => '500-2000',
                'has_certificate' => true,
                'skills' => ['SQL', 'Spreadsheet', 'R'],
            ],
            [
                'id' => '2',
                'title' => 'SQL Tutorial — Full Database Course',
                'provider' => 'YouTube · freeCodeCamp',
                'url' => 'https://www.youtube.com/watch?v=HXV3zeQKqGY',
                'price_type' => 'free',
                'price_label' => 'Ücretsiz',
                'price_range' => '0-500',
                'has_certificate' => false,
                'skills' => ['SQL'],
            ],
            [
                'id' => '3',
                'title' => 'Python for Data Analysis',
                'provider' => 'Udemy',
                'url' => 'https://www.udemy.com/course/python-for-data-analysis/',
                'price_type' => 'paid',
                'price_label' => '899 ₺',
                'price_range' => '500-2000',
                'has_certificate' => true,
                'skills' => ['Python', 'Pandas'],
            ],
            [
                'id' => '4',
                'title' => 'Khan Academy — Statistics & Probability',
                'provider' => 'Khan Academy',
                'url' => 'https://www.khanacademy.org/math/statistics-probability',
                'price_type' => 'free',
                'price_label' => 'Ücretsiz',
                'price_range' => '0-500',
                'has_certificate' => false,
                'skills' => ['Statistics'],
            ],
            [
                'id' => '5',
                'title' => 'AWS Certified Cloud Practitioner',
                'provider' => 'AWS',
                'url' => 'https://aws.amazon.com/certification/certified-cloud-practitioner/',
                'price_type' => 'paid',
                'price_label' => '2.500 ₺ sınav',
                'price_range' => '2000+',
                'has_certificate' => true,
                'skills' => ['Cloud'],
            ],
            [
                'id' => '6',
                'title' => 'Data Analyst Bootcamp',
                'provider' => 'Udemy',
                'url' => 'https://www.udemy.com/course/the-data-analyst-course/',
                'price_type' => 'paid',
                'price_label' => '449 ₺',
                'price_range' => '0-500',
                'has_certificate' => true,
                'skills' => ['Excel', 'SQL', 'Python'],
            ],
        ];
    }

    /**
     * @return list<array{
     *     id: string,
     *     tier: string,
     *     tier_label: string,
     *     title: string,
     *     readiness: int,
     *     gap_count: int,
     *     gaps_summary: string,
     *     weeks_estimate: string|null,
     *     swot: array{strengths: list<string>, weaknesses: list<string>, opportunities: list<string>, threats: list<string>}
     * }>
     */
    public static function careerLadder(): array
    {
        return [
            [
                'id' => 'junior-da',
                'tier' => 'ready',
                'tier_label' => 'A — Hazır',
                'title' => 'Junior Veri Analisti',
                'readiness' => 78,
                'gap_count' => 3,
                'gaps_summary' => 'Tableau, İngilizce B2, portfolio',
                'weeks_estimate' => null,
                'swot' => [
                    'strengths' => ['SQL', 'Excel', 'staj deneyimi'],
                    'weaknesses' => ['Tableau', 'portfolio eksik'],
                    'opportunities' => ['TR\'de junior DA talebi yüksek'],
                    'threats' => ['Çok sayıda bootcamp mezunu aday'],
                ],
            ],
            [
                'id' => 'bi-analyst',
                'tier' => 'near',
                'tier_label' => 'B — Yakın',
                'title' => 'BI Analisti',
                'readiness' => 61,
                'gap_count' => 5,
                'gaps_summary' => 'Power BI, DAX, veri modelleme, dashboard, sunum',
                'weeks_estimate' => '4–8 hafta',
                'swot' => [
                    'strengths' => ['SQL', 'Excel', 'analitik düşünme'],
                    'weaknesses' => ['Power BI', 'DAX'],
                    'opportunities' => ['KOBİ\'lerde BI rolü artıyor'],
                    'threats' => ['Otomatik dashboard araçları'],
                ],
            ],
            [
                'id' => 'ml-engineer',
                'tier' => 'reachable',
                'tier_label' => 'C — Ulaşılabilir',
                'title' => 'Makine Öğrenmesi Mühendisi',
                'readiness' => 28,
                'gap_count' => 12,
                'gaps_summary' => 'Python ileri, sklearn, deploy, matematik, proje',
                'weeks_estimate' => '~6 ay',
                'swot' => [
                    'strengths' => ['Temel Python', 'istatistik giriş'],
                    'weaknesses' => ['ML framework', 'model deploy', 'derin öğrenme'],
                    'opportunities' => ['YZTA bootcamp ML modülleri'],
                    'threats' => ['Senior ML aday yoğunluğu'],
                ],
            ],
        ];
    }

    public static function careerTierMeta(): array
    {
        return [
            'ready' => ['heading' => 'A — Hazır', 'hint' => '%70 ve üzeri · şimdi başvuruya yakın'],
            'near' => ['heading' => 'B — Yakın', 'hint' => '4–8 haftalık planla ulaşılabilir'],
            'reachable' => ['heading' => 'C — Ulaşılabilir', 'hint' => 'Uzun vade · eksikler tamamlanınca mümkün'],
        ];
    }

    /**
     * @return list<array{id: string, title: string, done: bool}>
     */
    public static function weeklyTasks(): array
    {
        return [
            [
                'id' => '1',
                'title' => 'SQL modülü 1: SELECT ve JOIN',
                'done' => false,
                'hint' => 'Haftalık 2 saat ayır; modül sonunda mini quiz çöz.',
            ],
            [
                'id' => '2',
                'title' => 'Mini proje: satış verisi analizi',
                'done' => false,
                'hint' => 'Jupyter veya Google Sheets ile örnek veri seti üzerinde çalış.',
            ],
            [
                'id' => '3',
                'title' => 'CV\'ni güncel yeteneklerle yenile',
                'done' => true,
                'hint' => 'CV oluşturucuda kaydet; yetenek radarını güncelle.',
            ],
        ];
    }

    /**
     * @return array{
     *     name: string,
     *     email: string,
     *     phone: string,
     *     location: string,
     *     headline: string,
     *     linkedin: string,
     *     github: string,
     *     uploaded_cv: array{name: string|null, uploaded_at: string|null}
     * }
     */
    /**
     * @return array{name: string, avatar_url: string|null}
     */
    public static function panelUser(): array
    {
        return [
            'name' => 'Ayşe Yılmaz',
            'avatar_url' => null,
        ];
    }

    public static function profile(): array
    {
        return [
            'name' => self::panelUser()['name'],
            'email' => 'ayse.yilmaz@ornek.edu.tr',
            'phone' => '+90 532 000 00 00',
            'location' => 'İstanbul, Türkiye',
            'headline' => 'Veri Analisti adayı · SQL · Python',
            'linkedin' => 'https://linkedin.com/in/ayseyilmaz',
            'github' => 'https://github.com/ayseyilmaz',
            'uploaded_cv' => [
                'name' => null,
                'uploaded_at' => null,
            ],
        ];
    }

    /**
     * CV oluşturucu başlangıç taslağı (Harvard / ATS, TR + EN).
     *
     * @return array{tr: array<string, mixed>, en: array<string, mixed>}
     */
    public static function cvDraft(): array
    {
        return [
            'tr' => self::cvDraftLocale('tr'),
            'en' => self::cvDraftLocale('en'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function cvDraftLocale(string $locale): array
    {
        if ($locale === 'en') {
            return [
                'personal' => [
                    'full_name' => 'Ayşe Yılmaz',
                    'email' => 'ayse.yilmaz@example.edu',
                    'phone' => '+90 532 000 00 00',
                    'location' => 'Istanbul, Turkey',
                    'linkedin' => 'linkedin.com/in/ayseyilmaz',
                    'summary' => 'Bootcamp student focused on data-driven decisions; experienced in SQL and Python for sales and user analytics in internships and academic projects.',
                ],
                'education' => [
                    [
                        'id' => 'edu-1',
                        'institution' => 'Istanbul University',
                        'degree' => 'Statistics, BSc',
                        'location' => 'Istanbul',
                        'start' => '2019',
                        'end' => '2023',
                        'details' => 'GPA: 3.4/4.0 · Relevant: Probability, Regression, Data Mining',
                    ],
                ],
                'experience' => [
                    [
                        'id' => 'exp-1',
                        'organization' => 'TechStart Inc.',
                        'title' => 'Data Analyst Intern',
                        'location' => 'Istanbul',
                        'start' => 'Jun 2023',
                        'end' => 'Aug 2023',
                        'bullets' => [
                            'Automated weekly sales reports with SQL, reducing reporting time by 40%.',
                            'Built customer segmentation analysis with Excel and Python (Pandas).',
                        ],
                    ],
                ],
                'skills' => [
                    ['id' => 'sk-1', 'category' => 'Technical', 'items' => 'SQL, Python, Pandas, Excel, Tableau (basic)'],
                    ['id' => 'sk-2', 'category' => 'Languages', 'items' => 'Turkish (native), English (B2)'],
                ],
                'projects' => [
                    [
                        'id' => 'prj-1',
                        'name' => 'E-commerce Sales Analysis',
                        'link' => 'github.com/ayseyilmaz/ecommerce-analysis',
                        'start' => '2024',
                        'end' => '2024',
                        'description' => 'Cohort and RFM analysis on 10K+ orders; visualizations in Jupyter notebooks.',
                    ],
                ],
                'certificates' => [
                    [
                        'id' => 'cert-1',
                        'name' => 'Google Data Analytics (in progress)',
                        'issuer' => 'Coursera',
                        'date' => '2025',
                    ],
                ],
                'enabledOptional' => [],
                'optional' => [],
            ];
        }

        return [
            'personal' => [
                'full_name' => 'Ayşe Yılmaz',
                'email' => 'ayse.yilmaz@ornek.edu.tr',
                'phone' => '+90 532 000 00 00',
                'location' => 'İstanbul, Türkiye',
                'linkedin' => 'linkedin.com/in/ayseyilmaz',
                'summary' => 'Veri odaklı kararlar üreten, SQL ve Python ile analiz yapan bootcamp öğrencisi. Staj ve akademik projelerde satış ve kullanıcı verisi analizi deneyimi.',
            ],
            'education' => [
                [
                    'id' => 'edu-1',
                    'institution' => 'İstanbul Üniversitesi',
                    'degree' => 'İstatistik, Lisans',
                    'location' => 'İstanbul',
                    'start' => '2019',
                    'end' => '2023',
                    'details' => 'GPA: 3.4/4.0 · İlgili dersler: Olasılık, Regresyon, Veri Madenciliği',
                ],
            ],
            'experience' => [
                [
                    'id' => 'exp-1',
                    'organization' => 'TechStart A.Ş.',
                    'title' => 'Veri Analisti Stajyeri',
                    'location' => 'İstanbul',
                    'start' => 'Haz 2023',
                    'end' => 'Ağu 2023',
                    'bullets' => [
                        'Haftalık satış raporlarını SQL ile otomatikleştirdim; raporlama süresini %40 azalttım.',
                        'Excel ve Python (Pandas) ile müşteri segmentasyonu analizi yaptım.',
                    ],
                ],
            ],
            'skills' => [
                ['id' => 'sk-1', 'category' => 'Teknik', 'items' => 'SQL, Python, Pandas, Excel, Tableau (temel)'],
                ['id' => 'sk-2', 'category' => 'Dil', 'items' => 'Türkçe (ana dil), İngilizce (B2)'],
            ],
            'projects' => [
                [
                    'id' => 'prj-1',
                    'name' => 'E-ticaret Satış Analizi',
                    'link' => 'github.com/ayseyilmaz/ecommerce-analysis',
                    'start' => '2024',
                    'end' => '2024',
                    'description' => '10K+ sipariş verisi üzerinde cohort ve RFM analizi; Jupyter notebook ile görselleştirme.',
                ],
            ],
            'certificates' => [
                [
                    'id' => 'cert-1',
                    'name' => 'Google Data Analytics (devam ediyor)',
                    'issuer' => 'Coursera',
                    'date' => '2025',
                ],
            ],
            'enabledOptional' => [],
            'optional' => [],
        ];
    }

    /**
     * @return array{score:int, verified:int, total:int, items:list<array<string,mixed>>, gaps:list<string>}
     */
    public static function skillPassport(): array
    {
        return [
            'score' => 68,
            'verified' => 5,
            'total' => 8,
            'items' => [
                ['skill' => 'SQL', 'level' => 'İleri', 'evidence' => 'E-ticaret satış analizi GitHub projesi', 'type' => 'GitHub', 'status' => 'verified', 'impact' => '10K+ sipariş üzerinde cohort/RFM analizi'],
                ['skill' => 'Python', 'level' => 'Orta', 'evidence' => 'Pandas notebook ve veri temizleme scriptleri', 'type' => 'Proje', 'status' => 'verified', 'impact' => 'Eksik veri temizleme + görselleştirme'],
                ['skill' => 'Dashboard', 'level' => 'Başlangıç', 'evidence' => 'Tableau satış panosu taslağı', 'type' => 'Portfolio', 'status' => 'review', 'impact' => '3 KPI, 2 segment grafiği'],
                ['skill' => 'İngilizce', 'level' => 'B2', 'evidence' => 'Sunum videosu ve teknik özet', 'type' => 'Video', 'status' => 'missing', 'impact' => 'Kanıt linki eklenmeli'],
            ],
            'gaps' => ['Power BI dashboard linki', 'Canlı proje demosu', 'İngilizce teknik sunum kaydı'],
        ];
    }

    /**
     * @return array{questions:list<array<string,mixed>>, rubric:list<string>}
     */
    public static function interviewSimulator(): array
    {
        return [
            'questions' => [
                ['role' => 'Junior Veri Analisti', 'type' => 'Teknik', 'question' => 'Bir satış tablosunda tekrar eden müşteri kayıtlarını nasıl temizler ve raporlarsın?', 'score' => 82, 'feedback' => 'SQL DISTINCT/JOIN, Pandas drop_duplicates ve kalite kontrol adımlarını söylediğinde güçlü cevap olur.'],
                ['role' => 'Junior Veri Analisti', 'type' => 'Davranışsal', 'question' => 'Eksik veri yüzünden teslim tarihi riskli olduğunda ekibe nasıl bilgi verirsin?', 'score' => 76, 'feedback' => 'Risk, seçenek ve net sonraki adım formatında cevap ver. STAR tekniği ekle.'],
                ['role' => 'BI Analisti', 'type' => 'Vaka', 'question' => 'Yönetim düşen dönüşüm oranını soruyor. İlk bakacağın 3 metrik nedir?', 'score' => 71, 'feedback' => 'Funnel kırılımı, trafik kaynağı ve segment bazlı dönüşüm analizi iyi başlangıç.'],
            ],
            'rubric' => ['Problem çerçevesi', 'Teknik doğruluk', 'Ölçülebilir etki', 'İletişim netliği'],
        ];
    }

    /**
     * @return array{columns:list<array<string,mixed>>, metrics:array<string,int>}
     */
    public static function applicationTracker(): array
    {
        return [
            'metrics' => ['active' => 6, 'interviews' => 2, 'offers' => 1],
            'columns' => [
                ['id' => 'saved', 'label' => 'Kaydedildi', 'items' => [
                    ['company' => 'Trendyol', 'role' => 'Junior Data Analyst', 'date' => '7 Tem', 'next' => 'CV uyumunu kontrol et'],
                    ['company' => 'Getir', 'role' => 'Product Data Intern', 'date' => '8 Tem', 'next' => 'Portfolio linki ekle'],
                ]],
                ['id' => 'applied', 'label' => 'Başvuruldu', 'items' => [
                    ['company' => 'Hepsiburada', 'role' => 'BI Analyst Intern', 'date' => '3 Tem', 'next' => 'Takip maili: 10 Tem'],
                ]],
                ['id' => 'interview', 'label' => 'Mülakat', 'items' => [
                    ['company' => 'Logo Yazılım', 'role' => 'Data Analyst', 'date' => '11 Tem', 'next' => 'SQL case pratiği'],
                ]],
            ],
        ];
    }

    /**
     * @return array{alerts:list<array<string,mixed>>, roles:list<string>, sources:list<string>}
     */
    public static function jobRadar(): array
    {
        return [
            'roles' => ['Junior Veri Analisti', 'BI Analisti', 'Product Analyst'],
            'sources' => ['LinkedIn', 'Kariyer.net', 'Remote'],
            'alerts' => [
                ['role' => 'Junior Veri Analisti', 'company' => 'Trendyol', 'source' => 'LinkedIn', 'match' => 84, 'salary' => '35-45K', 'gaps' => ['Tableau'], 'action' => 'Başvuruya hazır; Tableau projesini CV’ye ekle.'],
                ['role' => 'BI Analisti', 'company' => 'Logo Yazılım', 'source' => 'Kariyer.net', 'match' => 67, 'salary' => '30-40K', 'gaps' => ['Power BI', 'DAX'], 'action' => '2 haftalık Power BI mini proje sonrası başvur.'],
                ['role' => 'Product Analyst', 'company' => 'Remote EU Startup', 'source' => 'Remote', 'match' => 58, 'salary' => '€1.8-2.5K', 'gaps' => ['A/B test', 'İngilizce sunum'], 'action' => 'Önce vaka çalışması ve İngilizce pitch hazırla.'],
            ],
        ];
    }

    /**
     * @return array{experts:list<array<string,mixed>>, packages:list<array<string,string>>}
     */
    public static function mentorMarketplace(): array
    {
        return [
            'packages' => [
                ['name' => 'CV hızlı kontrol', 'price' => '₺299', 'delivery' => '24 saat'],
                ['name' => 'Portfolio review', 'price' => '₺499', 'delivery' => '48 saat'],
                ['name' => 'Mülakat provası', 'price' => '₺699', 'delivery' => 'Canlı 45 dk'],
            ],
            'experts' => [
                ['name' => 'Ece Kara', 'title' => 'Senior Data Analyst', 'company' => 'Fintech', 'rating' => 4.9, 'focus' => 'CV + SQL case', 'slots' => 'Salı 20:00'],
                ['name' => 'Mert Aydın', 'title' => 'BI Lead', 'company' => 'E-commerce', 'rating' => 4.8, 'focus' => 'Power BI portfolio', 'slots' => 'Perşembe 19:30'],
                ['name' => 'Selin Demir', 'title' => 'Talent Partner', 'company' => 'SaaS', 'rating' => 4.7, 'focus' => 'HR mülakat', 'slots' => 'Cumartesi 11:00'],
            ],
        ];
    }

    /**
     * @return array{prompts:list<array<string,string>>}
     */
    public static function chatAssistant(): array
    {
        return [
            'prompts' => [
                ['q' => 'Bu hafta hangi göreve odaklanmalıyım?', 'a' => 'Önce SQL mini projesini bitir. Çünkü Junior Veri Analisti için en yüksek gap Tableau değil, kanıtlanmış proje çıktısı.'],
                ['q' => 'Trendyol ilanına başvurmalı mıyım?', 'a' => 'Evet, %84 uyum var. Başvurmadan önce CV’ye satış analizi projesinin GitHub linkini ve Tableau ekran görüntüsünü ekle.'],
                ['q' => 'Mülakatta zayıf yanımı nasıl anlatayım?', 'a' => 'Power BI deneyiminin temel seviyede olduğunu söyle; bunu 2 haftalık dashboard projesiyle kapattığını somut örnekle bağla.'],
            ],
        ];
    }

}
