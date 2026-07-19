<?php

return [
    'brand' => 'CareerTalent Company',
    'nav' => ['general' => 'Genel', 'organization' => 'Kurum Yönetimi', 'dashboard' => 'Kurum Özeti', 'team' => 'Ekip ve Yetkiler', 'profile' => 'Kurum Profili', 'open_menu' => 'Menüyü aç', 'marketing_site' => 'Ana site'],
    'header' => ['secure_context' => 'Kurum bağlamı doğrulandı'],
    'organization' => ['active' => 'Aktif kurum'],
    'roles' => ['owner' => 'Kurum Sahibi', 'admin' => 'Kurum Yöneticisi', 'recruiter' => 'İşe Alım Uzmanı', 'hiring_manager' => 'Teknik Yönetici', 'viewer' => 'Görüntüleyici'],
    'status' => ['active' => 'Aktif', 'suspended' => 'Askıda'],
    'permissions' => [
        'dashboard.view' => 'Kurum özetini görüntüleme',
        'organization.update' => 'Kurum profilini güncelleme',
        'members.view' => 'Ekip üyelerini görüntüleme',
        'members.invite' => 'Ekip üyesi davet etme',
        'members.manage' => 'Ekip üyelerini ve yetkilerini yönetme',
    ],
    'dashboard' => [
        'title' => 'Kurum Özeti', 'subtitle' => 'Kurum hesabı, ekip erişimi ve onboarding durumunun gerçek özeti.',
        'members_total' => 'Toplam ekip üyesi', 'members_active' => 'Aktif üye', 'invitations' => 'Bekleyen davet',
        'foundation_title' => 'Güvenli kurum temeli hazır', 'foundation_text' => 'Pozisyon ve aday değerlendirme modüllerinden önce ekip rollerini ve kurum bilgilerini tamamlayın.',
        'manage_team' => 'Ekibi yönet',
    ],
    'profile' => ['title' => 'Kurum Profili', 'subtitle' => 'Aday ve ekip ekranlarında kullanılacak kurum bilgileri.', 'name' => 'Kurum adı', 'billing_email' => 'Fatura e-postası', 'website' => 'Web sitesi', 'save' => 'Bilgileri kaydet', 'updated' => 'Kurum profili güncellendi.'],
    'team' => [
        'title' => 'Ekip ve Yetkiler', 'subtitle' => 'Her kullanıcı yalnız kendisine verilen kurum yetkilerini görür ve kullanır.',
        'invite_title' => 'Yeni ekip üyesi davet et', 'email' => 'E-posta', 'role' => 'Rol', 'status_label' => 'Durum', 'permissions' => 'Yetkiler',
        'invite' => 'Davet oluştur', 'invited' => 'Ekip daveti oluşturuldu.', 'invite_link' => 'Tek kullanımlık davet bağlantısı',
        'edit' => 'Üye ve yetkileri düzenle', 'save' => 'Kaydet', 'updated' => 'Ekip üyeliği güncellendi.',
        'pending' => 'Bekleyen davetler', 'empty' => 'Henüz ekip üyesi yok.',
    ],
];
