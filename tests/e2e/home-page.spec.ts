import { test, expect } from '@playwright/test';

test.describe('Ana Sayfa (Public Home)', () => {
  test('Ana sayfa başarıyla yüklenmeli ve başlık doğrulanmalı', async ({ page }) => {
    await page.goto('/');

    // Sayfa başlığı ve arayüz kontrolleri
    await expect(page).toHaveTitle(/Anasayfa/i);
    await expect(page.locator('header.navbar, .navbar')).toBeVisible();

    // Filtre alanlarının (Birim, Bölüm, Program, Hoca, Derslik) varlığı
    await expect(page.locator('#unit_id, select[name="unit_id"]')).toBeVisible();
    await expect(page.locator('#department_id, select[name="department_id"]')).toBeVisible();
    await expect(page.locator('#program_id, select[name="program_id"]')).toBeVisible();
  });

  test('Birim ve Bölüm seçimi filtreleri etkileşimi', async ({ page }) => {
    await page.goto('/');

    const unitSelect = page.locator('#unit_id');
    if (await unitSelect.isVisible()) {
      const optionsCount = await unitSelect.locator('option').count();
      if (optionsCount > 1) {
        await unitSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);
      }
    }
  });

  test('Giriş Yap / Yönetim Paneli butonu giriş sayfasına yönlendirmeli', async ({ page }) => {
    await page.goto('/');

    const loginBtn = page.locator('a[href*="/login"], a[href*="/admin"]');
    if (await loginBtn.first().isVisible()) {
      await loginBtn.first().click();
      await expect(page).toHaveURL(/\/(login|admin)/);
    }
  });
});
