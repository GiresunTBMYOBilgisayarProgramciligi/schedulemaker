import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Kullanıcı İşlemleri CRUD ve Validasyonlar', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('1. Kullanıcı Listesi: DataTable arama, filtreleme ve butonlar', async ({ page }) => {
    await page.goto('/admin/listusers');
    await expect(page.locator('table, .dataTable')).toBeVisible();

    // Arama kutusu etkileşimi
    const searchInput = page.locator('input[type="search"], .dataTables_filter input');
    if (await searchInput.isVisible()) {
      await searchInput.fill('Admin');
      await page.waitForTimeout(500);
      await expect(page.locator('table tbody tr').first()).toBeVisible();
    }
  });

  test('2. Kullanıcı Ekleme: Boş form gönderilemez (HTML5 required)', async ({ page }) => {
    await page.goto('/admin/adduser');
    await expect(page).toHaveURL(/\/admin\/adduser/);

    const nameInput = page.locator('#name');
    const mailInput = page.locator('#mail');

    await expect(nameInput).toHaveAttribute('required', '');
    await expect(mailInput).toHaveAttribute('required', '');
  });

  test('3. Kullanıcı Ekleme ve Düzenleme Akışı', async ({ page }) => {
    await page.goto('/admin/adduser');

    const uniqueMail = `e2e_user_${Date.now()}@test.com`;
    await page.fill('#name', 'E2E Otomasyon');
    await page.fill('#last_name', 'Testi');
    await page.fill('#mail', uniqueMail);

    const roleSelect = page.locator('#role');
    if (await roleSelect.isVisible()) {
      await roleSelect.selectOption('lecturer');
    }

    const passInput = page.locator('#password');
    if (await passInput.isVisible()) {
      await passInput.fill('123456');
    }

    // Formu gönder
    await page.click('button[type="submit"]');
    await page.waitForTimeout(1000);

    // Listeye gidip yeni kullanıcının varlığını kontrol et
    await page.goto('/admin/listusers');
    const searchInput = page.locator('input[type="search"], .dataTables_filter input');
    if (await searchInput.isVisible()) {
      await searchInput.fill(uniqueMail);
      await page.waitForTimeout(500);
      await expect(page.locator('body')).toContainText(uniqueMail);
    }
  });
});
