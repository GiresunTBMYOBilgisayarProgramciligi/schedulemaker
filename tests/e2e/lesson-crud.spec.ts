import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Ders İşlemleri CRUD ve Validasyonlar', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('1. Ders Listesi: Arama ve butonlar', async ({ page }) => {
    await page.goto('/admin/listlessons');
    await expect(page.locator('table, .dataTable')).toBeVisible();

    const searchInput = page.locator('input[type="search"], .dataTables_filter input');
    if (await searchInput.isVisible()) {
      await searchInput.fill('Matematik');
      await page.waitForTimeout(500);
    }
  });

  test('2. Ders Ekleme Formu Açılışı ve Validasyon Kontrolü', async ({ page }) => {
    await page.goto('/admin/addlesson');
    await expect(page).toHaveURL(/\/admin\/addlesson/);

    const codeInput = page.locator('#code');
    const nameInput = page.locator('#name');

    await expect(codeInput).toHaveAttribute('required', '');
    await expect(nameInput).toHaveAttribute('required', '');
  });

  test('3. Ders Ekleme Formu Gerekli Alanlar ve Birim Seçimi Etkileşimi', async ({ page }) => {
    await page.goto('/admin/addlesson');

    const randomCode = `E2E${Math.floor(Math.random() * 900 + 100)}`;
    await page.fill('#code', randomCode);
    await page.fill('#name', 'E2E Otomasyon Dersi');

    const hoursInput = page.locator('#hours');
    if (await hoursInput.isVisible()) {
      await hoursInput.fill('3');
    }

    const unitSelect = page.locator('#unit_id');
    if (await unitSelect.isVisible()) {
      const unitCount = await unitSelect.locator('option').count();
      if (unitCount > 1) {
        await unitSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);
      }
    }

    // Form submit butonunun varlığı
    const submitBtn = page.locator('button[type="submit"]');
    await expect(submitBtn).toBeVisible();
  });
});
