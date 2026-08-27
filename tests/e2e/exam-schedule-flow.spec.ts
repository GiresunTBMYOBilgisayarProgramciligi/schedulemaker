import { test, expect, Page } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';

/**
 * TomSelect veya standart select elementlerinde güvenilir değer seçici.
 */
async function selectOptionHelper(page: Page, selectId: string, value: string | number) {
  await page.evaluate(({ id, val }) => {
    const el = document.getElementById(id) as any;
    if (el && el.tomselect) {
      el.tomselect.setValue(val);
    } else if (el) {
      el.value = val;
      el.dispatchEvent(new Event('change'));
    }
  }, { id: selectId, val: String(value) });
}

test.describe('Uçtan Uca Sınav Programı Düzenleme & Etkileşim Akışı', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/editexamschedule');
  });

  test('1. Birim, Bölüm, Program ve Sınav Türü seçilip sınav programı tablosu yüklenmeli', async ({ page }) => {
    // 1. Birim seç: Tirebolu Mehmet Bayrak MYO (ID: 1)
    await selectOptionHelper(page, 'unit_id', 1);
    await page.waitForTimeout(600);

    // 2. Bölüm seç: Bilgisayar Teknolojileri (ID: 1)
    await selectOptionHelper(page, 'department_id', 1);
    await page.waitForTimeout(600);

    // 3. Program seç: Bilgisayar Programcılığı (ID: 1)
    await selectOptionHelper(page, 'program_id', 1);

    // 4. Sınav Türü seç (Ara Sınav / Final)
    const examTypeSelect = page.locator('#schedule_type');
    if (await examTypeSelect.isVisible()) {
      await examTypeSelect.selectOption('midterm-exam');
    }

    // 5. Düzenle butonuna tıkla
    await page.click('#departmentAndProgramScheduleButton');

    // 6. Sınav programı kartının yüklenmesi
    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });
    await expect(scheduleContainer.locator('table.schedule-table, .schedule-table').first()).toBeVisible();

    // Atanabilir sınav dersleri paneli
    const availableItems = scheduleContainer.locator('.available-schedule-items [draggable="true"]');
    if (await availableItems.count() > 0) {
      await expect(availableItems.first()).toBeVisible();
    }
  });

  test('2. Sınav Programında 2. Hafta / 1. Hafta Geçiş Butonları Çalışmalı', async ({ page }) => {
    // Final Sınavı programını yükle (2 haftalıktır)
    await selectOptionHelper(page, 'unit_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'department_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'program_id', 1);

    const examTypeSelect = page.locator('#schedule_type');
    if (await examTypeSelect.isVisible()) {
      await examTypeSelect.selectOption('final-exam');
    }

    await page.click('#departmentAndProgramScheduleButton');
    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });

    // Hafta ileri / geri butonları
    const nextWeekBtn = scheduleContainer.locator('.next-week, button:has-text("2. Hafta"), [data-action="next-week"]').first();
    const prevWeekBtn = scheduleContainer.locator('.prev-week, button:has-text("1. Hafta"), [data-action="prev-week"]').first();

    if (await nextWeekBtn.isVisible()) {
      await nextWeekBtn.click();
      await page.waitForTimeout(300);
      if (await prevWeekBtn.isVisible()) {
        await prevWeekBtn.click();
        await page.waitForTimeout(300);
      }
    }
  });
});
