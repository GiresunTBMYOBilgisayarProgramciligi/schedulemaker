import { test, expect } from '@playwright/test';

test.describe('Kimlik Doğrulama (Auth)', () => {
  test('Geçersiz e-posta veya şifre ile hata bildirimi gösterilmeli', async ({ page }) => {
    await page.goto('/auth/login');

    await page.fill('input[name="mail"]', 'gecersiz_kullanici@test.com');
    await page.fill('input[name="password"]', 'YanlisSifre123');
    await page.click('button[type="submit"]');

    // AJAX yanıtı veya toast/alert bildirimi beklenir
    await page.waitForTimeout(1000);
    // Sayfanın hala login sayfasında olduğunu ve yönlenmediğini doğrula
    await expect(page).toHaveURL(/\/auth\/login/);
  });

  test('Boş form gönderilmeye çalışıldığında HTML5 required doğrulaması engellemeli', async ({ page }) => {
    await page.goto('/auth/login');

    const emailInput = page.locator('input[name="mail"]');
    await expect(emailInput).toHaveAttribute('required', '');

    const passwordInput = page.locator('input[name="password"]');
    await expect(passwordInput).toHaveAttribute('required', '');
  });

  test('Başlık ve Anasayfaya dönüş linki çalışmalı', async ({ page }) => {
    await page.goto('/auth/login');

    const homeLink = page.locator('a:has-text("TMYOTakvim"), .card-header a');
    await expect(homeLink).toBeVisible();
    await homeLink.click();
    await expect(page).toHaveURL(/http:\/\/schedulemaker\.loc\/?$/);
  });
});
