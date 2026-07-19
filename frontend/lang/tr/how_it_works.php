<?php

return [

    'title' => 'Nasıl Çalışır — CareerTalent AI',

    'hero' => [
        'eyebrow'         => 'Süreç',
        'title'           => 'CV\'den teklife, tek bir rota.',
        'subtitle'        => 'CareerTalent AI CV\'ni okur, eksiklerini kapatacak planı çıkarır ve seni mülakata hazırlar — üç adımda, baştan sona.',
        'cta_primary'     => 'Ücretsiz başla',
        'cta_secondary'   => 'Genel bakışı izle',
        'video_url_label' => 'app.careertalent.ai/overview',
        'video_duration'  => '2:14',
        'video_caption'   => 'Sürecin tamamını anlatan iki dakikalık video — çok yakında.',
    ],

    'stats' => [
        'eyebrow' => 'Rakamlarla',
        'items'   => [
            ['value' => 3,   'suffix' => '',      'label' => 'CV\'den teklife giden adım sayısı'],
            ['value' => 500, 'suffix' => '+',     'label' => 'pratik mülakat sorusu havuzu'],
            ['value' => 21,  'suffix' => ' gün',  'label' => 'ortalama, mülakata hazır olma süresi'],
            ['value' => 100, 'suffix' => '%',     'label' => 'tamamen kendi CV\'ne özel'],
        ],
    ],

    'process' => [
        'eyebrow'    => 'Adım adım',
        'title'      => 'Rotayı takip et.',
        'subtitle'   => 'Aşağıdaki her adım bir sonrakini besler — burada attığın hiçbir adım boşa gitmez.',
        'powered_by' => 'Bu adımda kullanılanlar',
        'step_label' => 'Adım',
    ],

    // Order here is the order the steps render in — insertion order is preserved.
    'steps' => [
        'analyze' => [
            'nav_label'      => 'Analiz',
            'chips'          => ['CV Merkezi', 'CV Oluşturucu'],
            'title'          => 'Bir kez yükle, her şeyi gör.',
            'desc'           => 'CV\'ni yükle, CareerTalent AI onu tam bir işe alım uzmanı gibi okusun — becerilerini ayrıştırır, eksiklerini işaretler ve hedeflediğin rollere göre uyum puanını radar grafiğinde önüne koyar.',
            'path'           => 'cv-merkezi',
            'video_label'    => 'İzle: Analiz ve Optimizasyon',
            'benefits_label' => 'Neler kazanırsın',
            'benefits'       => [
                'İşe alım uzmanlarının gerçekten baktığı bir beceri dökümü',
                'Hedeflediğin her rol için ayrı bir uyum puanı',
                'Etkisine göre sıralanmış, net bir eksik listesi',
            ],
        ],
        'plan' => [
            'nav_label'      => 'Plan',
            'chips'          => ['Kariyer Rotam', 'Yetenek Pasaportu'],
            'title'          => 'Eksikleri plana dönüştür.',
            'desc'           => 'Eksik olan her beceri, kişisel yol haritanda bir göreve dönüşür. Kendi hızında ilerle; öğrendiklerin Yetenek Pasaportu\'nda kanıt olarak birikin.',
            'path'           => 'kariyer-rotam',
            'video_label'    => 'İzle: Rotanı Planla',
            'benefits_label' => 'Neler kazanırsın',
            'benefits'       => [
                'Gerçek eksiklerinden oluşturulan kişisel bir yol haritası',
                'Programına uyacak büyüklükte görevler',
                'Kanıt olarak dolan bir Yetenek Pasaportu',
            ],
        ],
        'land' => [
            'nav_label'      => 'Kazan',
            'chips'          => ['İş Fırsatları', 'Mülakat Hazırlığı'],
            'title'          => 'Güvenle başvur.',
            'desc'           => 'Canlı iş ilanlarına karşı anlık uyum puanını gör, mülakat simülatörüyle pratik yap ve her başvurunu tek bir yerden takip et — ilk mesajdan teklife kadar.',
            'path'           => 'is-firsatlari',
            'video_label'    => 'İzle: İşi Kazan',
            'benefits_label' => 'Neler kazanırsın',
            'benefits'       => [
                'Gerçek iş ilanlarına karşı canlı uyum puanları',
                'Prova yapabileceğin bir mülakat simülatörü',
                'Her başvuruyu takip edebileceğin tek bir panel',
            ],
        ],
    ],

    'video' => [
        'toast_hero' => 'Video çok yakında',
        'toast_step' => 'Çok yakında',
    ],

    'demo' => [
        'eyebrow'       => 'Sen de dene',
        'title'         => 'Bir uyum puanının neye benzediğini gör.',
        'subtitle'      => 'Bir hedef rol seç, CareerTalent AI\'nin bir CV\'yi ona göre nasıl okuduğunu izle.',
        'role_label'    => 'Hedef rol',
        'score_label'   => 'Uyum puanı',
        'matched_label' => 'Zaten eşleşen',
        'gaps_label'    => 'Eksik olan',
        'caption'       => 'Örnek amaçlıdır — gerçek puanın kendi CV\'ne göre hesaplanır.',
        'cta'           => 'Gerçek puanını almak için CV\'ni yükle',
        'roles'         => [
            [
                'key'     => 'dev',
                'label'   => 'Yazılım Geliştirici',
                'score'   => 72,
                'matched' => ['Git & sürüm kontrolü', 'REST API entegrasyonu', 'Takım içinde kod incelemesi'],
                'gaps'    => ['Bulut altyapısı (AWS/GCP)', 'Otomatik test yazımı'],
            ],
            [
                'key'     => 'data',
                'label'   => 'Veri Analisti',
                'score'   => 61,
                'matched' => ['SQL sorguları', 'Excel/Sheets ile raporlama'],
                'gaps'    => ['Python ile veri temizleme', 'Görselleştirme araçları (Tableau/Power BI)', 'Temel istatistiksel test bilgisi'],
            ],
            [
                'key'     => 'pm',
                'label'   => 'Ürün Yöneticisi',
                'score'   => 55,
                'matched' => ['Kullanıcı görüşmeleri yürütme'],
                'gaps'    => ['Yol haritası önceliklendirme', 'Metrik tanımlama (KPI)', 'Teknik ekiplerle çalışma'],
            ],
        ],
    ],

    'faq' => [
        'eyebrow' => 'Sorular',
        'title'   => 'Başlamadan önce',
        'items'   => [
            [
                'q' => 'CareerTalent AI ücretsiz mi?',
                'a' => 'Evet — hesap oluşturmak, CV\'ni yüklemek ve ilk uyum puanını almak tamamen ücretsiz.',
            ],
            [
                'q' => 'Sürecin tamamı ne kadar sürer?',
                'a' => 'İlk analizini almak iki dakikadan kısa sürer. Eksiklerini kapatmak kendi hızına bağlı — kimi bir hafta sonunda bitirir, kimi birkaç haftaya yayar.',
            ],
            [
                'q' => 'Başlamak için bitmiş bir CV\'ye ihtiyacım var mı?',
                'a' => 'Hayır. Taslak ya da eski bir CV bile yeterli — CareerTalent AI tam olarak neyi ekleyip neyi düzeltmen gerektiğini gösterir.',
            ],
            [
                'q' => 'Hangi dilleri destekliyor?',
                'a' => 'Platform ve analizlerin hem Türkçe hem İngilizce olarak sunuluyor — istediğin zaman site menüsünden değiştirebilirsin.',
            ],
        ],
    ],

    'sticky_cta' => [
        'text'   => 'Kendi uyum puanını görmeye hazır mısın?',
        'button' => 'Ücretsiz başla',
    ],

    'closing' => [
        'title'         => 'Kendi rotanı gör.',
        'desc'          => 'CV\'ni yükle, iki dakikadan kısa sürede ilk uyum puanını al.',
        'cta_primary'   => 'Ücretsiz başla',
        'cta_secondary' => 'Giriş yap',
    ],

];