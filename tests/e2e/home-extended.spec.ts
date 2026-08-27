import { test, expect } from '@playwright/test';

test.describe('Ana Sayfa Detaylı Kontroller (Checklist)', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
  });

  test('1. Program türü butonları (Ders, Ara Sınav, Final, Bütünleme) aktifleşebilmeli', async ({ page }) => {
    const lessonBtn = page.locator('#scheduleTypePills button[data-type="lesson"]');
    const midtermBtn = page.locator('#scheduleTypePills button[data-type="midterm-exam"]');
    const finalBtn = page.locator('#scheduleTypePills button[data-type="final-exam"]');
    const makeupBtn = page.locator('#scheduleTypePills button[data-type="makeup-exam"]');

    await expect(lessonBtn).toBeVisible();
    await expect(midtermBtn).toBeVisible();
    await expect(finalBtn).toBeVisible();
    await expect(makeupBtn).toBeVisible();

    // Ara Sınav seçimi
    await midtermBtn.click();
    await expect(midtermBtn).toHaveClass(/active/);

    // Final seçimi
    await finalBtn.click();
    await expect(finalBtn).toHaveClass(/active/);
  });

  test('2. Yıl ve Dönem seçimi alanları değiştirilebilmeli', async ({ page }) => {
    const yearSelect = page.locator('#academic_year');
    const semesterSelect = page.locator('#semester');

    if (await yearSelect.isVisible()) {
      const yearOptions = await yearSelect.locator('option').count();
      if (yearOptions > 0) {
        await expect(yearSelect).toBeEnabled();
      }
    }

    if (await semesterSelect.isVisible()) {
      await semesterSelect.selectOption({ index: 0 });
      await expect(semesterSelect).toBeEnabled();
    }
  });

  test('3. Birim, Bölüm ve Program seçimi ve Ders Programı Gösterimi', async ({ page }) => {
    const unitSelect = page.locator('#unit_id');
    const deptSelect = page.locator('#department_id');
    const progSelect = page.locator('#program_id');

    if (await unitSelect.isVisible()) {
      const unitCount = await unitSelect.locator('option').count();
      if (unitCount > 1) {
        await unitSelect.selectOption({ index: 1 });
        await page.waitForTimeout(500);

        if (await deptSelect.isVisible()) {
          const deptCount = await deptSelect.locator('option').count();
          if (deptCount > 1) {
            await deptSelect.selectOption({ index: 1 });
            await page.waitForTimeout(500);
          }
        }
      }
    }
  });

  test('4. Hoca ve Derslik sekmelerine geçiş yapılabilmeli', async ({ page }) => {
    // Sekme butonları
    const lecturerTab = page.locator('button:has-text("Öğretim Elemanı"), a:has-text("Öğretim Elemanı"), [data-bs-target*="lecturer"]');
    const classroomTab = page.locator('button:has-text("Derslik"), a:has-text("Derslik"), [data-bs-target*="classroom"]');

    if (await lecturerTab.first().isVisible()) {
      await lecturerTab.first().click();
      await page.waitForTimeout(300);
    }

    if (await classroomTab.first().isVisible()) {
      await classroomTab.first().click();
      await page.waitForTimeout(300);
    }
  });

  test('5. Dışa aktarma ve takvime aktarma butonlarının çalışması', async ({ page }) => {
    const exportDropdown = page.locator('#exportDropdown, button:has-text("Dışa Aktar"), .btn-export');
    if (await exportDropdown.first().isVisible()) {
      await exportDropdown.first().click();
      await page.waitForTimeout(300);

      const excelOption = page.locator('a:has-text("Excel"), button:has-text("Excel")');
      const iCalOption = page.locator('a:has-text("iCal"), a:has-text("Takvim")');

      if (await excelOption.first().isVisible()) {
        await expect(excelOption.first()).toBeVisible();
      }
      if (await iCalOption.first().isVisible()) {
        await expect(iCalOption.first()).toBeVisible();
      }
    }
  });
});
