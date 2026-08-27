import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Derslik, Bölüm ve Program Yönetimi (Admin CRUD)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
  });

  test('1. Yeni Derslik Ekleme Formu ve Sayfa Kontrolü', async ({ page }) => {
    await page.goto('/admin/addclassroom');
    await expect(page).toHaveURL(/\/admin\/addclassroom/);

    const nameInput = page.locator('#name, input[name="name"]');
    await expect(nameInput.first()).toBeVisible();
    await expect(nameInput.first()).toHaveAttribute('required', '');
  });

  test('2. Yeni Bölüm Ekleme Formu ve Sayfa Kontrolü', async ({ page }) => {
    await page.goto('/admin/adddepartment');
    await expect(page).toHaveURL(/\/admin\/adddepartment/);

    const nameInput = page.locator('#name, input[name="name"]');
    await expect(nameInput.first()).toBeVisible();
  });

  test('3. Yeni Program Ekleme Formu ve Sayfa Kontrolü', async ({ page }) => {
    await page.goto('/admin/addprogram');
    await expect(page).toHaveURL(/\/admin\/addprogram/);

    const nameInput = page.locator('#name, input[name="name"]');
    await expect(nameInput.first()).toBeVisible();
  });
});
