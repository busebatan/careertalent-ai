import { expect, test } from '@playwright/test';

test.describe('CV builder PDF export', () => {
    test('modal disables buttons and shows progress while exporting', async ({ page }) => {
        await page.goto('/panel/cv-olustur');

        await page.evaluate(() => {
            window.exportHarvardCvPdf = () =>
                new Promise((resolve) => {
                    setTimeout(resolve, 800);
                });
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

    test('loads html2pdf chunk when export runs', async ({ page }) => {
        await page.goto('/panel/cv-olustur');

        const chunkRequest = page.waitForResponse(
            (response) =>
                response.url().includes('/build/assets/html2pdf') && response.status() === 200,
            { timeout: 15_000 },
        );

        await page.getByRole('button', { name: 'PDF indir' }).click();
        await page.getByRole('button', { name: 'Türkçe PDF indir' }).click();

        await chunkRequest;
    });
});
