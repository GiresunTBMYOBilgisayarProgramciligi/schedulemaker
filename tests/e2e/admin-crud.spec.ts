import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Yönetici Paneli (Admin CRUD)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('Admin paneli dashboard bileşenleri ve kartları görüntülenmeli', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('.app-wrapper, .wrapper, .main-header')).toBeVisible();

    const sidebar = page.locator('.app-sidebar, .main-sidebar, aside');
    await expect(sidebar).toBeVisible();
  });

  test('Kullanıcı listesi sayfası açılmalı ve kullanıcılar listelenmeli', async ({ page }) => {
    await page.goto('/admin/listusers');
    await expect(page.locator('.card').first()).toBeVisible();
  });

  test('Ders listesi sayfası açılmalı', async ({ page }) => {
    await page.goto('/admin/listlessons');
    await expect(page.locator('.card').first()).toBeVisible();
  });

  test('Derslik listesi sayfası açılmalı', async ({ page }) => {
    await page.goto('/admin/listclassrooms');
    await expect(page.locator('.card').first()).toBeVisible();
  });

  test('Bölüm ve Program listesi sayfaları açılmalı', async ({ page }) => {
    await page.goto('/admin/listdepartments');
    await expect(page.locator('.card').first()).toBeVisible();

    await page.goto('/admin/listprograms');
    await expect(page.locator('.card').first()).toBeVisible();
  });

  test('Akademik Birimler listesi açılmalı', async ({ page }) => {
    await page.goto('/admin/listunits');
    await expect(page.locator('.card').first()).toBeVisible();
  });

  test('Çıkış yapıldığında oturum sonlanmalı ve anasayfaya yönlenmeli', async ({ page }) => {
    await page.goto('/auth/logout');
    await expect(page).toHaveURL(/http:\/\/schedulemaker\.loc\/?$/);

    // Tekrar admin sayfasına girilmeye çalışıldığında login sayfasına atmalı
    await page.goto('/admin');
    await expect(page).toHaveURL(/\/auth\/login/);
  });
});
