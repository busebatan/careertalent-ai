# Statik tanıtım sitesi (prod)

Bu dizindeki HTML dosyaları **üretilmiş çıktıdır**. Kaynak tek yerde:

`frontend/resources/views/marketing/*.blade.php`

## Üret

```bash
bash scripts/build-landing.sh
```

## Prod routing

`deploy/nginx/careertalent.conf`:

| Yol | Sunucu |
|-----|--------|
| `/`, `/ozellikler`, `/nasil-calisir`, `/bootcamp` | `landing/` statik |
| `/panel/*` | Laravel (PHP-FPM) |
| `/api/*` | FastAPI |

Lokal geliştirmede `php artisan serve` ile Blade doğrudan render edilir; drift olmaması için marketing değişikliğinden sonra `build-landing.sh` koşulmalıdır.
