import { chromium } from 'playwright';
import { strict as assert } from 'node:assert';

const baseURL = (process.env.PANEL_SMOKE_BASE_URL ?? process.env.PLAYWRIGHT_BASE_URL ?? 'https://careertalent.ygtlabs.ai').replace(/\/$/, '');

const panelPages = [
  { path: '/panel', text: 'Hoş geldin' },
  { path: '/panel/yol-haritasi', text: 'Yol Haritası' },
  { path: '/panel/gorevlerim', text: 'Görevlerim' },
  { path: '/panel/egitim-onerileri', text: 'Eğitim Önerileri' },
  { path: '/panel/ilan-eslestirme', text: 'İlan Eşleştirme' },
  { path: '/panel/is-radari', text: 'İş Radarı' },
  { path: '/panel/basvuru-takibi', text: 'Başvuru Takibi' },
  { path: '/panel/yetenek-pasaportu', text: 'Yetenek Pasaportu' },
  { path: '/panel/mulakat-simulasyonu', text: 'Mülakat Simülasyonu' },
  { path: '/panel/kariyer-merdiveni', text: 'Kariyer merdiveni' },
  { path: '/panel/profil', text: 'Profil bilgileri' },
  { path: '/panel/cv-olustur', text: 'CV Oluştur' },
  { path: '/panel/sohbet', text: 'Sohbet' },
];

const browser = await chromium.launch({ headless: true });
const page = await browser.newPage();
const pageErrors = [];
page.on('pageerror', (error) => pageErrors.push(error.message));

try {
  for (const panelPage of panelPages) {
    const response = await page.goto(`${baseURL}${panelPage.path}`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
    assert(response, `${panelPage.path} response yok`);
    assert(response.ok(), `${panelPage.path} HTTP ${response.status()}`);

    const bodyText = await page.locator('body').innerText({ timeout: 10_000 });
    assert(bodyText.includes(panelPage.text), `${panelPage.path} metin yok: ${panelPage.text}`);
    assert(!bodyText.includes('Internal Server Error'), `${panelPage.path} 500 içerik gösteriyor`);
  }

  await page.goto(`${baseURL}/panel`, { waitUntil: 'domcontentloaded', timeout: 30_000 });
  const dashboardText = await page.locator('body').innerText({ timeout: 10_000 });
  assert(dashboardText.includes('API bağlı'), 'Dashboard API bağlı rozeti göstermiyor');
  assert(
    dashboardText.includes('Google Data Analytics Certificate') || dashboardText.includes('SQLBolt Interactive SQL'),
    'Dashboard FastAPI/hedefe özel eğitim verisini göstermiyor',
  );
  assert.deepEqual(pageErrors, [], `Browser runtime error: ${pageErrors.join(' | ')}`);

  console.log(`panel-navigation-smoke ok: ${panelPages.length} page, base=${baseURL}`);
} finally {
  await browser.close();
}
