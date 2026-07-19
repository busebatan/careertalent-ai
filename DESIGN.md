# careertalent-ai Design Memory

Created: 2026-07-13
Global baseline: `/opt/codex-harness/agents/design-memory/GLOBAL_DESIGN.md`

## Product and audience

- Product: AI destekli CV analizi, kariyer rotası ve görev yönetimi sunan CareerTalent AI.
- Primary audience: Kariyerini ölçülebilir adımlarla geliştirmek isteyen öğrenci ve erken/orta kariyer profesyoneli.
- Main user job: Güvenle kullanıcı paneline girmek veya hesap oluşturmak; yöneticinin yanlış portala girmesini önlemek.
- Business conversion: Pazarlama yüzeyinden kullanıcı kaydı ve panel oturumuna net, düşük sürtünmeli geçiş.

## Brand register

- Personality: Ölçülebilir, sakin, teknik fakat insani; güven veren kariyer rehberi.
- Materials and subject-world references: Kariyer rotası, yetenek radarı, ilerleme çizgisi, CV belgesi, doğrulama sinyali.
- Desired emotional response: “Doğru yerdeyim; sonraki adımım belli.”
- Forbidden tone: Generic SaaS gradient, glassmorphism yığını, sahte metrik, çalışmayan sosyal giriş, panel/admin belirsizliği.

## Visual system

- Typography posture: Bricolage Grotesque başlık, Manrope gövde, IBM Plex Mono sistem/veri etiketi.
- Density and spacing: Auth için tek görevli, nefesli fakat boş görünmeyen orta yoğunluk.
- Color function: Slate/ink zemin; emerald kullanıcı eylemi; amber yalnız admin güvenlik ayrımı. Company paneli slate yüzey, `#0F766E` ana teal eylem, `#10B981` dekoratif marka sinyali ve koyu temada `#5EEAD4` vurgu metni kullanır; admin amber tokenlarını miras almaz.
- Grid and composition: Panel formu masaüstünde sağda; admin formu solda. Mobilde form ilk erişilebilir görev olarak yukarı taşınır.
- Imagery: Dekoratif stok görsel yok; kariyer rotası ve analiz sinyallerinden türetilen kod-native grafikler.
- Motion budget: Bir giriş sekansı, form durum geçişi ve sınırlı rota/sinyal hareketi; reduced-motion desteği zorunlu.

## Keep

- Mevcut logo işareti, slate/emerald pazarlama renkleri, Türkçe/İngilizce içerik yapısı ve gerçek e-posta/şifre auth sözleşmesi korunur.
- Yeni kanonik rotalar `/panel/login`, `/panel/register`, `/admin/login`; eski `/giris`, `/kayit` yönlendirilir.

## Avoid

- Pazarlama header/footer içinde küçük auth kartı, panel/admin için aynı görünüm, sosyal giriş maketi, mor gradient, kart mozaiği, gereksiz glow ve pill kalabalığı.

## Accepted directions

<!-- Structured selection decisions are appended here by design_memory.py. -->

- 2026-07-13 — **A / Rota Çizgisi**: CV → radar → hedef meslek → görev akışını doğrudan anlatıyor. Panel sağ form/emerald, admin sol form/amber ayrımını koruyor. İki bağımsız kör eleştirmenin ortak kazananı; resmi skor `9.10/10`.
- 2026-07-19 — **Company / Kurumsal emerald**: Mevcut slate/emerald marka ailesini korur; derin teal ana eylem ve aktif navigasyonla B2B çalışma alanını kullanıcı panelinden ölçülü biçimde ayırır. Kartlar nötr kalır, emerald yalnız marka sinyali olur, admin amber kimliği korunur.

## Rejected directions

<!-- Record why a direction failed, not only that it failed. -->

- **B / Analiz Masası**: Güçlü teknik görünüm; auth görevi için daha yoğun. Kariyer yolculuğu A kadar hızlı okunmuyor.
- **C / Eşik**: Sakin ve insani; açık editorial yüzey mevcut koyu panel diliyle A kadar doğrudan bağ kurmuyor.

## Auth integration contract

- Canonical: `/panel/login`, `/panel/register`, `/admin/login`.
- Legacy `/giris` ve `/kayit`: kalıcı yönlendirme.
- Gerçek backend alanları dışında sahte SSO veya parola kurtarma kontrolü yok.
