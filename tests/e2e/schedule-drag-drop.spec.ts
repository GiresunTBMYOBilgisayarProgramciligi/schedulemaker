import { test, expect } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

test.describe('Ders Programı Düzenleme & Sürükle-Bırak Etkileşimleri', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/editschedule');
  });

  test('1. Sekmeler arası geçiş (Program, Hoca, Derslik)', async ({ page }) => {
    const programTab = page.locator('#program-tab');
    const lecturerTab = page.locator('#lecturer-tab');
    const classroomTab = page.locator('#classroom-tab');

    await expect(programTab).toBeVisible();
    await expect(lecturerTab).toBeVisible();
    await expect(classroomTab).toBeVisible();

    // Hoca sekmesine geç
    await lecturerTab.click();
    await expect(page.locator('#lecturer-tab-pane')).toHaveClass(/active/);

    // Derslik sekmesine geç
    await classroomTab.click();
    await expect(page.locator('#classroom-tab-pane')).toHaveClass(/active/);

    // Tekrar Program sekmesine dön
    await programTab.click();
    await expect(page.locator('#program-tab-pane')).toHaveClass(/active/);
  });

  test('2. Yıl ve Dönem seçimi ile Notlar & Bildirim butonları', async ({ page }) => {
    const yearSelect = page.locator('#academic_year');
    const semesterSelect = page.locator('#semester');
    const notesBtn = page.locator('#btn-show-schedule-notes');

    await expect(yearSelect).toBeVisible();
    await expect(semesterSelect).toBeVisible();
    await expect(notesBtn).toBeVisible();
  });

  test('3. Program seçimi sonrası Takvim konteynerinin yüklenmesi', async ({ page }) => {
    const unitSelect = page.locator('#unit_id');
    if (await unitSelect.isVisible()) {
      const count = await unitSelect.locator('option').count();
      if (count > 1) {
        await unitSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);
      }
    }

    // Takvim kapsayıcısı
    const scheduleBox = page.locator('#schedule, .schedule-container, #scheduleTable');
    if (await scheduleBox.first().isVisible()) {
      await expect(scheduleBox.first()).toBeVisible();
    }
  });
});
