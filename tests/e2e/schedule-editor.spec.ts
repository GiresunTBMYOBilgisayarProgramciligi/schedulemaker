import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Ders ve Sınav Programı Düzenleme (Schedule Editor)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Ders Programı Düzenleme sayfası açılmalı ve filtreler yüklenmeli', async ({ page }) => {
    await page.goto('/admin/editschedule');
    await expect(page).toHaveURL(/\/admin\/editschedule/);

    // Filtreleme ve takvim arayüz bileşenleri
    await expect(page.locator('#department_id')).toBeAttached();
    await expect(page.locator('#program_id')).toBeAttached();
  });

  test('Sınav Programı Düzenleme sayfası açılmalı', async ({ page }) => {
    await page.goto('/admin/editexamschedule');
    await expect(page).toHaveURL(/\/admin\/editexamschedule/);

    await expect(page.locator('#department_id')).toBeAttached();
  });

  test('Dışa Aktarma (Export) ve Yayınlama (Publish) sayfaları açılmalı', async ({ page }) => {
    await page.goto('/admin/exportschedule');
    await expect(page.locator('body')).toContainText('Dışa Aktar');

    await page.goto('/admin/publishschedule');
    await expect(page.locator('body')).toContainText('Yayın');
  });

  test('Ayarlar ve Log sayfaları açılmalı', async ({ page }) => {
    await page.goto('/admin/settings');
    await expect(page.locator('body')).toContainText('Ayar');

    await page.goto('/admin/logs');
    await expect(page.locator('body')).toContainText('Log');
  });
});
