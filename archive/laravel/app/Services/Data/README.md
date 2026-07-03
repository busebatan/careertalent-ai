# Veri & Analiz Modülü (Yiğit)

Bu klasör veri pipeline'ına ait servisleri içerir:

| Dosya (planlanan) | Ne yapar? |
|-------------------|-----------|
| `CvParseService.php` | PDF → ham metin → yapılandırılmış profil JSON |
| `JobScraperService.php` | İş ilanı verisi toplama (Kariyer.net vb.) |
| `EmbeddingService.php` | Yetenek / ilan vektör benzerliği |
| `GapAnalysisService.php` | Eksik yetenek + hazırlık % hesabı |

Çıktılar Döne'nin backend servislerine ve `data/` klasörüne beslenir.

Scraper scriptleri: `scripts/scrapers/`
