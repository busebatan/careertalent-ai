# Veri katmanı (referans)

İş verisi ve parse mantığı **FastAPI backend** tarafında tutulur (`backend/app/services/`, `backend/app/models/`).

Yiğit'in veri çıktıları (`data/roles/`, scraper scriptleri) Döne'nin backend servislerine entegre edilir.

Laravel frontend yalnızca `CareerTalentApiClient` ile API'yi çağırır; Gemini veya veritabanı iş mantığı burada **olmamalı**.
