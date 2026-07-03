"""
CareerTalent AI — Streamlit MVP arayüzü
Backend API'ye bağlanır. Sprint 1'de adım adım genişletilir.
"""

import streamlit as st

API_URL = "http://localhost:8000"

st.set_page_config(page_title="CareerTalent AI", page_icon="🚀", layout="wide")

st.title("🚀 CareerTalent AI")
st.caption("YZTA Bootcamp — Kariyer Yol Arkadaşın")

# Sağlık kontrolü
try:
    import httpx

    resp = httpx.get(f"{API_URL}/health", timeout=3.0)
    if resp.status_code == 200:
        st.success("Backend bağlantısı: OK")
    else:
        st.warning("Backend yanıt verdi ama beklenmeyen durum.")
except Exception:
    st.error(
        "Backend'e bağlanılamadı. `docker compose up` ile API'yi başlatın "
        f"({API_URL})"
    )

st.divider()

tab1, tab2, tab3 = st.tabs(["📄 CV Yükle", "🎯 Hedef Meslek", "🗺️ Yol Haritam"])

with tab1:
    st.subheader("CV'ni Yükle")
    st.info("PDF formatında CV yükle. Sistem yeteneklerini otomatik çıkaracak.")
    uploaded = st.file_uploader("CV dosyası (PDF)", type=["pdf"])
    if uploaded:
        st.write(f"Dosya: {uploaded.name} — Sprint 1'de API'ye gönderilecek.")

with tab2:
    st.subheader("Hedef Mesleğini Seç")
    roles = [
        "Veri Analisti",
        "Makine Öğrenmesi Mühendisi",
        "Backend Geliştirici",
        "Frontend Geliştirici",
        "İş Analisti",
    ]
    selected = st.selectbox("Gitmek istediğin meslek", roles)
    if st.button("Analiz Et"):
        st.write(f"Seçilen: **{selected}** — Sprint 2'de gap analizi burada görünecek.")

with tab3:
    st.subheader("Yol Haritam")
    st.info("Hedef meslek seçildikten sonra haftalık plan burada listelenecek.")
    st.progress(0, text="Hazırlık: %0 — henüz plan oluşturulmadı")
