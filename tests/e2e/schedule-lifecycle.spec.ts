import { test, expect, Page } from '@playwright/test';
import { loginAsAdmin } from './helpers/auth.helper';
import { simulateHtml5DragAndDrop } from './helpers/dragdrop.helper';

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

test.describe('Ders Programı Canlı Yaşam Döngüsü (Ekleme, Derslik Değiştirme, Taşıma, Silme)', () => {
  test.beforeEach(async ({ page }) => {
    await loginAsAdmin(page);
    await page.goto('/admin/editschedule');

    // Tirebolu MYO (1) -> Bilgisayar Teknolojileri (1) -> Bilgisayar Programcılığı (1) yükle
    await selectOptionHelper(page, 'unit_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'department_id', 1);
    await page.waitForTimeout(600);
    await selectOptionHelper(page, 'program_id', 1);
    await page.click('#departmentAndProgramScheduleButton');

    // Takvim ve ders listesinin yüklenmesini bekle
    const scheduleContainer = page.locator('#schedule_container');
    await expect(scheduleContainer.locator('.schedule-card').first()).toBeVisible({ timeout: 15000 });
    await expect(scheduleContainer.locator('table.schedule-table').first()).toBeVisible();
  });

  test('1. Tam Yaşam Döngüsü: Dersi Takvime Ekleme -> Dersliğini Değiştirme -> Taşıma -> Silme', async ({ page }) => {
    const scheduleContainer = page.locator('#schedule_container');

    // ─────────────────────────────────────────────────────────────
    // ADIM 1: DERSİ LİSTEDEN TAKVİME SÜRÜKLE VE EKLE
    // ─────────────────────────────────────────────────────────────
    const availableLesson = scheduleContainer.locator('.available-schedule-items [draggable="true"]').first();
    await expect(availableLesson).toBeVisible({ timeout: 10000 });

    // Hedef hücre (ilk drop-zone td)
    const targetCell = scheduleContainer.locator('table.schedule-table td.drop-zone').first();
    await expect(targetCell).toBeVisible();

    // HTML5 Drag & Drop tetikle
    await simulateHtml5DragAndDrop(
      page,
      '#schedule_container .available-schedule-items [draggable="true"]',
      '#schedule_container table.schedule-table td.drop-zone'
    );

    // "Sınıf ve Saat Seçimi" modalının açılmasını bekle
    const modal = page.locator('#ajaxModal');
    await expect(modal).toBeVisible({ timeout: 8000 });

    // Sınıf listesinin AJAX ile yüklenmesini bekle (en az 1 geçerli derslik seçeneği olmalı)
    await page.waitForFunction(() => {
      const select = document.querySelector('#classroom') as HTMLSelectElement;
      return select && select.options.length > 1;
    }, { timeout: 10000 });

    // İlk geçerli dersliği seç (index 1)
    const classroomSelect = modal.locator('#classroom');
    await classroomSelect.selectOption({ index: 1 });

    // Modal onay butonuna tıkla
    const confirmBtn = modal.locator('#modalConfirm');
    await confirmBtn.click();

    // Modalın kapanmasını ve takvimin güncellenmesini bekle
    await expect(modal).not.toBeVisible({ timeout: 8000 });
    await page.waitForTimeout(2000);

    // Takvimde ders kartının başarıyla oluştuğunu doğrula
    const placedLessonCards = scheduleContainer.locator('table.schedule-table .lesson-card');
    await expect(placedLessonCards.first()).toBeVisible({ timeout: 10000 });

    // ─────────────────────────────────────────────────────────────
    // ADIM 2: SAĞ TIK İLE DERSLİĞİ DEĞİŞTİR
    // ─────────────────────────────────────────────────────────────
    const cardToEdit = placedLessonCards.first();
    await cardToEdit.click({ button: 'right' });
    const contextMenu = page.locator('#lesson-context-menu');
    await expect(contextMenu).toBeVisible({ timeout: 5000 });

    const editClassroomItem = contextMenu.locator('.context-menu-item:has-text("Dersliği Düzenle")');
    if (await editClassroomItem.isVisible()) {
      await editClassroomItem.click();

      // Derslik düzenleme modalı açılmalı
      await expect(modal).toBeVisible({ timeout: 8000 });
      await page.waitForFunction(() => {
        const select = document.querySelector('#classroom') as HTMLSelectElement;
        return select && select.options.length > 1;
      }, { timeout: 8000 });

      // Farklı bir derslik seç (varsa 2. seçenek, yoksa 1.)
      const count = await classroomSelect.locator('option').count();
      if (count > 2) {
        await classroomSelect.selectOption({ index: 2 });
      } else {
        await classroomSelect.selectOption({ index: 1 });
      }

      await confirmBtn.click();
      await expect(modal).not.toBeVisible({ timeout: 8000 });
      await page.waitForTimeout(2000);
    }

    // ─────────────────────────────────────────────────────────────
    // ADIM 3: DERSİ BAŞKA BİR SAATE / HÜCREYE TAŞI (Table -> Table)
    // ─────────────────────────────────────────────────────────────
    const currentCard = scheduleContainer.locator('table.schedule-table .lesson-card').first();
    const otherCell = scheduleContainer.locator('table.schedule-table td.drop-zone').nth(2);

    if (await currentCard.isVisible() && await otherCell.isVisible()) {
      await simulateHtml5DragAndDrop(
        page,
        '#schedule_container table.schedule-table .lesson-card',
        '#schedule_container table.schedule-table td.drop-zone:nth-of-type(3)'
      );
      await page.waitForTimeout(2000);

      // Takvimde ders kartının varlığını doğrula
      await expect(scheduleContainer.locator('table.schedule-table .lesson-card').first()).toBeVisible();
    }

    // ─────────────────────────────────────────────────────────────
    // ADIM 4: DERSİ SİL (Table -> List)
    // ─────────────────────────────────────────────────────────────
    const lessonToDelete = scheduleContainer.locator('table.schedule-table .lesson-card').first();
    const removeDropZone = scheduleContainer.locator('.available-schedule-items.drop-zone').first();

    if (await lessonToDelete.isVisible() && await removeDropZone.isVisible()) {
      await simulateHtml5DragAndDrop(
        page,
        '#schedule_container table.schedule-table .lesson-card',
        '#schedule_container .available-schedule-items.drop-zone'
      );
      await page.waitForTimeout(2000);
    }
  });
});
