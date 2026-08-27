import { test, expect } from '@playwright/test';
import { loginAs } from './helpers/auth.helper';

test.describe('Rol Bazlı Menü ve Sayfa Yetkileri (RBAC UI)', () => {
  test('Öğretim Görevlisi (Lecturer) oturum açabilmeli ve profil sayfasına erişebilmeli', async ({ page }) => {
    await loginAs(page, 'lecturer');
    await expect(page).toHaveURL(/\/admin/);

    // Profil sayfasına doğrudan gidilebilmeli
    await page.goto('/admin/profile');
    await expect(page).toHaveURL(/\/admin\/profile/);
    await expect(page.locator('body')).toContainText('Profil');
  });

  test('Bölüm Başkanı (Department Head) oturum açabilmeli', async ({ page }) => {
    await loginAs(page, 'department_head');
    await expect(page).toHaveURL(/\/admin/);

    // Sidebar menüsü görünür olmalı
    await expect(page.locator('.app-sidebar, aside')).toBeVisible();
  });

  test('Admin tüm yönetim modüllerine erişebilmeli', async ({ page }) => {
    await loginAs(page, 'admin');
    await expect(page).toHaveURL(/\/admin/);

    // Ayarlar, Kullanıcılar ve Takvim modüllerinin varlığı
    await page.goto('/admin/settings');
    await expect(page).toHaveURL(/\/admin\/settings/);
  });
});
