# ADR 0001: CV PDF üretimi (istemci vs sunucu)

**Durum:** Uygulandı
**Tarih:** 2026-06-29
**Güncelleme:** 2026-07-23

## Bağlam

Harvard format CV PDF, `html2pdf.js` ile tarayıcıda raster görsel olarak üretiliyordu. Uzun içerikte canvas sayfa sınırından kesilebildiği için başlık ve satırlar iki sayfa arasında kırılabiliyordu.

## Karar

1. PDF, Laravel üzerinden yerel Chrome sürücüsüyle sunucuda A4 olarak üretilir.
2. Açık önizleme ve indirme aynı PDF blob'unu kullanır.
3. `html2pdf.js`, `html2canvas` ve jsPDF tabanlı CV export zinciri kaldırılır.
4. `@page`, `break-after` ve `break-inside` kuralları sunucu PDF şablonunun parçasıdır.

## Gerekçe (sunucu tarafı hedef)

- A4 ölçüsü ve 12 mm kenar boşluğu tek renderer tarafından belirlenir.
- Metin seçilebilir ve aranabilir kalır.
- Önizleme ile indirilen/arşivlenen dosya aynı binary çıktıdır.
- html2canvas raster kesim ve tarayıcıya bağlı export farkı ortadan kalkar.

## Sonuçlar

- Endpoint: `POST /panel/cv-merkezi/pdf`
- Renderer: `App\Services\HarvardCvPdfRenderer`
- Şablon: `resources/views/pdf/harvard-cv.blade.php`
- Sürücü: `spatie/laravel-pdf` + `chrome-php/chrome`
- Chrome yolu `LARAVEL_PDF_CHROME_BINARY` ile değiştirilebilir.

## Doğrulama

- Feature test: doğrulama, PDF response, A4/12 mm renderer sözleşmesi
- JS unit test: sunucu PDF isteği ve content-type kontrolü
- Gerçek Chrome smoke: A4 sayfa ölçüsü, seçilebilir metin, sayfa sınırı ve son satır kontrolü
