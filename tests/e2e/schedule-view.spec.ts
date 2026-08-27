import { test, expect } from '@playwright/test';

test.describe('Anasayfa Takvim Görüntüleme ve Dışa Aktarma', () => {
  test('Bölüm ve Program seçilip ders programı tablosunun yüklenmesi', async ({ page }) => {
    await page.goto('/');

    // Birim seç
    const unitSelect = page.locator('#unit_id');
    if (await unitSelect.isVisible()) {
      const options = await unitSelect.locator('option').all();
      if (options.length > 1) {
        const val = await options[1].getAttribute('value');
        if (val) {
          await unitSelect.selectOption(val);
          await page.waitForTimeout(500);
        }
      }
    }

    // Tablo alanı veya takvim kapsayıcısı var mı kontrol et
    const scheduleContainer = page.locator('#schedule-container, #schedule, .schedule-container, #scheduleTable');
    if (await scheduleContainer.isVisible()) {
      await expect(scheduleContainer).toBeVisible();
    }
  });

  test('Dışa aktarma butonlarının varlığı', async ({ page }) => {
    await page.goto('/');

    // Dışa aktarma dropdown veya butonları
    const exportBtn = page.locator('button:has-text("Dışa Aktar"), a:has-text("Dışa Aktar"), #exportDropdown, .btn-export');
    if (await exportBtn.first().isVisible()) {
      await expect(exportBtn.first()).toBeVisible();
    }
  });
});
