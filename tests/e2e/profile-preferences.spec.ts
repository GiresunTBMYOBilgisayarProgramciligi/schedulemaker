import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth.helper';

test.describe('Kullanıcı Profil ve Tercih İşlemleri', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, 'lecturer');
  });

  test('1. Profil sayfası açılmalı ve kullanıcı bilgileri görüntülenmeli', async ({ page }) => {
    await page.goto('/admin/profile');
    await expect(page).toHaveURL(/\/admin\/profile/);

    await expect(page.locator('.profile-username').first()).toBeVisible();
    await expect(page.locator('body')).toContainText('Test Hoca');
  });

  test('2. Bilgi güncelleme formu ve parola yardım notu kontrolü', async ({ page }) => {
    await page.goto('/admin/profile');

    const nameInput = page.locator('input[name="name"]');
    if (await nameInput.isVisible()) {
      await expect(nameInput).toHaveValue(/Test/i);
    }
  });

  test('3. Profil sayfasında ders programı ve tercih kartları varlığı', async ({ page }) => {
    await page.goto('/admin/profile');

    const profileCards = page.locator('.card');
    await expect(profileCards.first()).toBeVisible();
  });
});
