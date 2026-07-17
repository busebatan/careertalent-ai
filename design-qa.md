# Design QA — Dashboard CV boş durumu

- Kaynak görsel: `docs/sprintler/screenshots/sprint-2/panel-dashboard.png`
- Uygulama kanıtı: `docs/sprintler/screenshots/sprint-2/panel-dashboard-verified.png`
- Viewport: `1440x900`
- Durum: koyu tema, oturum açık, CV analizi yok

## Karşılaştırma

- Büyük kesik çizgili radar kartı, ikon, başlık, açıklama ve iki CTA kaynakla aynı konum ve ölçüde.
- `CV yükle` → `/panel/hesap#cv-yukle`.
- `CV oluştur` → `/panel/cv-merkezi`.
- Ayrı `CV ve profil` kartı yok.
- Yatay taşma yok; tarayıcı runtime hatası yok.
- Piksel karşılaştırması: 1.296.000 pikselin 1.882'si farklı (`%0,145`); fark yalnız dinamik tarih/saat alanında.

## Sonuç

passed
