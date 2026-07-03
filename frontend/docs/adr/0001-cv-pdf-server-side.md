# ADR 0001: CV PDF üretimi (istemci vs sunucu)

**Durum:** Kabul edildi (aşamalı)  
**Tarih:** 2026-06-29

## Bağlam

Harvard format CV PDF şu an `html2pdf.js` ile tarayıcıda üretiliyor. CDN bağımlılığı kaldırıldı; Vite dynamic import ile `html2pdf` chunk yükleniyor.

## Karar

1. **Şimdi:** İstemci tarafı `exportHarvardCvPdf` (html2pdf + clone) kullanılmaya devam eder.
2. **Sonra:** Sunucu tarafı PDF (Dompdf veya Snappy) Sprint 2+ backlog.

## Gerekçe (sunucu tarafı hedef)

- ATS uyumlu sabit font/margin (Georgia 11pt Harvard şablonu)
- html2canvas render farkları ve taşma riski azalır
- İndirme güvenilirliği (özellikle mobil / düşük RAM)

## Sonuçlar

- Şimdilik bundle ~600KB html2pdf chunk (yalnızca indirme tıklanınca)
- Dompdf geçişinde: `POST /panel/cv-olustur/pdf` + Blade/HTML şablon paylaşımı
- PHPUnit feature + Playwright E2E mevcut akışı korur

## Yapılacaklar (Dompdf fazı)

- [ ] `barryvdh/laravel-dompdf` veya `spatie/browsershot` değerlendirmesi
- [ ] `resources/views/pdf/harvard-cv.blade.php` (önizleme ile aynı markup)
- [ ] Feature test: PDF response `Content-Type: application/pdf`
