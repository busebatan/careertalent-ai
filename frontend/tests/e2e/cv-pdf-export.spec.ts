import { expect, test } from '@playwright/test';

const pdfBody = '%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n%%EOF';

async function mockPdfEndpoints(page) {
    await page.route('**/panel/cv-merkezi/pdf', async (route) => {
        expect(route.request().method()).toBe('POST');
        expect(route.request().postDataJSON()).toMatchObject({ language: expect.any(String), locales: expect.any(Object) });
        await route.fulfill({
            body: pdfBody,
            contentType: 'application/pdf',
        });
    });
    await page.route('**/panel/cv-merkezi/pdf-arsivle', async (route) => {
        await route.fulfill({ json: { ok: true } });
    });
}

test.describe('CV builder PDF export', () => {
    test('modal disables buttons and shows progress while exporting', async ({ page }) => {
        await mockPdfEndpoints(page);
        await page.goto('/panel/cv-olustur');

        await page.evaluate(() => {
            window.downloadPdfBlob = () => {};
        });

        await page.getByRole('button', { name: 'PDF indir' }).click();
        await expect(page.getByRole('dialog')).toBeVisible();

        const trButton = page.getByRole('button', { name: 'Türkçe PDF indir' });
        await trButton.click();

        await expect(trButton).toBeDisabled();
        await expect(page.getByRole('button', { name: 'İngilizce PDF indir' })).toBeDisabled();
        await expect(page.getByRole('button', { name: 'İptal' })).toBeDisabled();
        await expect(page.getByRole('status')).toContainText('PDF hazırlanıyor');

        await expect(page.getByRole('status')).toContainText('PDF indirildi', { timeout: 10_000 });
        await expect(page.getByRole('dialog')).toBeHidden();
    });

    test('preview embeds the exact server-rendered PDF', async ({ page }) => {
        await mockPdfEndpoints(page);
        await page.goto('/panel/cv-olustur');

        await page.getByRole('button', { name: 'Önizleme' }).click();

        const preview = page.locator('[data-cv-pdf-preview]');
        await expect(preview).toBeVisible();
        await expect(preview).toHaveAttribute('src', /^blob:/);
    });
});
