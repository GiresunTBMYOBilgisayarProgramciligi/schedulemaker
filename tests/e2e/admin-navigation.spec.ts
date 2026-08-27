import { test, expect } from '@playwright/test';

test.describe('Yönetim Paneli ve Menü Gezinimi', () => {
  test('Oturum açmamış kullanıcı admin sayfalarına erişememeli ve login sayfasına yönlendirilmeli', async ({ page }) => {
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/auth\/login/);

    await page.goto('/admin/listusers');
    await expect(page).toHaveURL(/\/auth\/login/);

    await page.goto('/admin/listlessons');
    await expect(page).toHaveURL(/\/auth\/login/);
  });
});
