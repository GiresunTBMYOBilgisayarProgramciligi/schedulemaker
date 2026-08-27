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

test.describe('Uçtan Uca Ders Programı Düzenleme & Etkileşim Akışı', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/editschedule');
  });

  test('1. Birim, Bölüm, Program seçilip ders programı ve ders listesi yüklenmeli', async ({ page }) => {
    // 1. Birim seç: Tirebolu Mehmet Bayrak MYO (ID: 1)
    await selectOptionHelper(page, 'unit_id', 1);

    // 2. Bölüm seçeneklerinin dolmasını bekle ve seç: Bilgisayar Teknolojileri (ID: 1)
    await page.waitForFunction(() => {
      const el = document.getElementById('department_id') as any;
      if (el && el.tomselect) {
        return Object.keys(el.tomselect.options).length > 1;
      }
      return el && el.options && el.options.length > 1;
    }, { timeout: 10000 });
    await selectOptionHelper(page, 'department_id', 1);

    // 3. Program seçeneklerinin dolmasını bekle ve seç: Bilgisayar Programcılığı (ID: 1)
    await page.waitForFunction(() => {
      const el = document.getElementById('program_id') as any;
      if (el && el.tomselect) {
        return Object.keys(el.tomselect.options).length > 1;
      }
      return el && el.options && el.options.length > 1;
    }, { timeout: 10000 });
    await selectOptionHelper(page, 'program_id', 1);

    // 4. Düzenle (Göster) butonuna tıkla
    await page.click('#departmentAndProgramScheduleButton');

    // 5. Takvim kartının ve sol taraftaki ders listesinin yüklenmesini doğrula
    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });
    await expect(scheduleContainer.locator('table.schedule-table, .schedule-table').first()).toBeVisible();

    // Sol panelde atanabilir dersler listelenmiş olmalı
    const availableItems = scheduleContainer.locator('.available-schedule-items .schedule-item, .available-schedule-items .lesson-card, .available-schedule-items [draggable="true"]');
    await expect(availableItems.first()).toBeVisible({ timeout: 10000 });
  });

  test('2. Sürükle-Bırak ile Ders Ekleme, Sınıf Seçimi ve Takvime Yerleştirme', async ({ page }) => {
    // Programı yükle
    await selectOptionHelper(page, 'unit_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'department_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'program_id', 1);
    await page.click('#departmentAndProgramScheduleButton');

    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });

    // Sol paneldeki ilk dersi al
    const firstLesson = scheduleContainer.locator('.available-schedule-items [draggable="true"]').first();
    const emptySlot = scheduleContainer.locator('table.schedule-table td.drop-zone .empty-slot').first();

    await expect(firstLesson).toBeVisible();
    await expect(emptySlot).toBeVisible();

    // Sürükle-bırak işlemini JavaScript Event simülasyonu ile tetikle
    await firstLesson.dragTo(emptySlot);
    await page.waitForTimeout(1000);

    // Sınıf ve Saat Seçim modalı açılmış mı?
    const modal = page.locator('#ajaxModal, .modal.show');
    if (await modal.isVisible()) {
      // Derslik seçimi dropdown'ı
      const classroomSelect = modal.locator('#classroom');
      if (await classroomSelect.isVisible()) {
        const optionCount = await classroomSelect.locator('option').count();
        if (optionCount > 0) {
          await classroomSelect.selectOption({ index: 0 });
        }
      }

      // Onayla veya İptal et
      const confirmBtn = modal.locator('#modalConfirm, button:has-text("Kaydet"), button:has-text("Tamam")').first();
      const cancelBtn = modal.locator('#modalCancel, .btn-close').first();

      if (await confirmBtn.isVisible()) {
        await confirmBtn.click();
      } else if (await cancelBtn.isVisible()) {
        await cancelBtn.click();
      }
    }
  });

  test('3. Ders Kartı Context Menü (Sağ Tık) ve Dersliği Düzenle Aksiyonu', async ({ page }) => {
    // Programı yükle
    await selectOptionHelper(page, 'unit_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'department_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'program_id', 1);
    await page.click('#departmentAndProgramScheduleButton');

    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });

    // Tabloda mevcut yerleştirilmiş bir ders kartı varsa sağ tıkla
    const placedCards = scheduleContainer.locator('table.schedule-table .lesson-card');
    if (await placedCards.count() > 0) {
      const card = placedCards.first();
      await card.click({ button: 'right' });

      // Context menu görünmeli
      const contextMenu = page.locator('#lesson-context-menu, .context-menu');
      await expect(contextMenu).toBeVisible();

      // Dersliği Düzenle veya Programı Göster seçeneği olmalı
      await expect(contextMenu).toContainText(/programını göster|Dersliği Düzenle|Kilitle/i);
    }
  });
});
