<?php

return [
    'brand' => 'CareerTalent Admin',
    'area_kicker' => 'Yönetim alanı',
    'header' => [
        'kicker' => 'Admin',
        'subtitle' => 'Öğrenci panelinden ayrı yönetim yüzeyi',
        'mobile_brand' => 'CareerTalent Admin',
    ],
    'nav' => [
        'dashboard' => 'Dashboard',
        'student_panel' => 'Öğrenci paneli',
        'marketing_site' => 'Tanıtım sitesi',
    ],
    'modules' => [
        'students' => [
            'title' => 'Öğrenciler',
            'description' => 'Aktif öğrenci hesapları ve CV/analiz durumu.',
        ],
        'readiness' => [
            'title' => 'Readiness Analizi',
            'description' => 'CV analizlerinin gerçek işlem durumu ve yetenek sayısı.',
        ],
        'skill-passport' => [
            'title' => 'Yetenek Pasaportu',
            'description' => 'Öğrencilerin yüklediği kanıt kayıtları.',
        ],
        'job-radar' => [
            'title' => 'İş Radarı',
            'description' => 'Öğrencilerin analiz ettiği iş ilanları.',
        ],
        'applications' => [
            'title' => 'Başvurular',
            'description' => 'Öğrencilerin kaydettiği başvuru kayıtları.',
        ],
        'interviews' => [
            'title' => 'Mülakatlar',
            'description' => 'Başlatılan mülakat simülasyonları.',
        ],
    ],
    'dashboard' => [
        'title' => 'Yönetim Özeti',
        'subtitle' => 'Yalnız aktif öğrenci hesapları ve ilişkili gerçek kayıtlar.',
        'recent_students' => 'Son kaydolan öğrenciler',
        'recent_students_hint' => 'Admin hesapları bu listede yer almaz.',
        'registered_at' => 'Kayıt: :date',
        'registered_unknown' => 'Kayıt: Bilinmiyor',
        'no_students' => 'Henüz gerçek öğrenci kaydı yok.',
        'records_count' => ':count kayıt',
        'open_records' => 'Kayıtları aç',
    ],
    'page' => [
        'records_title' => 'Gerçek kayıtlar',
        'records_hint' => ':total kayıt · En fazla son 50 kayıt gösterilir.',
        'empty_rows' => 'Bu modülde henüz gerçek kayıt yok.',
    ],
    'errors' => [
        'api_unavailable' => 'Yönetim verisi alınamadı: :error',
        'api_unavailable_generic' => 'Yönetim verisi alınamadı.',
    ],
    'notifications' => [
        [
            'id' => 'admin-notif-1',
            'title' => 'Yeni öğrenci kaydı',
            'body' => 'Son 24 saatte yeni öğrenci hesabı oluşturuldu.',
            'time' => 'Az önce',
            'unread' => true,
        ],
        [
            'id' => 'admin-notif-2',
            'title' => 'Kanıt inceleme kuyruğu',
            'body' => 'Yetenek pasaportu kanıtları incelenmeyi bekliyor.',
            'time' => '1 saat önce',
            'unread' => true,
        ],
    ],
];
