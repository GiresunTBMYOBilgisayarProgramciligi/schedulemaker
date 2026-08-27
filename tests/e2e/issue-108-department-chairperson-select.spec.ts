import { test, expect } from '@playwright/test';

test('Issue #108: Bölüm ekleme sayfasında bölüm başkanı seçimi ve tüm hocaları getirme butonu testi', async ({ page }) => {
  await page.goto('http://schedulemaker.loc/');

  // 1. Yönetici Girişi
  await page.getByRole('link', { name: ' Yönetim Paneli Girişi' }).click();
  await page.getByRole('textbox', { name: 'Email (required)' }).fill('testadmin@schedulemaker.loc');
  await page.getByRole('textbox', { name: 'Parola (required)' }).fill('123456');
  await page.getByRole('button', { name: 'Giriş Yap' }).click();
  await page.waitForURL(/\/admin/);

  // 2. Bölüm Ekle Sayfasına Git
  await page.goto('http://schedulemaker.loc/admin/adddepartment');

  // "Tüm Hocalar" butonunun varlığını doğrula
  const btnLoadAll = page.locator('#btn-load-all-chairpersons');
  await expect(btnLoadAll).toBeVisible();

  // 3. Üst Birim Seçildiğinde Otomatik Hocaların Yüklenmesi
  const unitResponsePromise = page.waitForResponse(response =>
    response.url().includes('/ajax/getLecturersList') && response.status() === 200
  );
  await page.evaluate(() => {
    const el = document.querySelector('#unit_id') as any;
    if (el && el.tomselect) {
      const keys = Object.keys(el.tomselect.options);
      const validKey = keys.find(k => k && k !== '0' && k !== '');
      if (validKey) {
        el.tomselect.setValue(validKey);
        el.dispatchEvent(new Event('change'));
      }
    }
  });
  await unitResponsePromise;

  // 4. "Tüm Hocalar" Butonuna Tıkla ve AJAX Yanıtını Kontrol Et
  const allLecturersPromise = page.waitForResponse(response =>
    response.url().includes('/ajax/getAllLecturersList') && response.status() === 200
  );
  await btnLoadAll.click();
  await allLecturersPromise;

  // Toast bildirimini kontrol et
  await expect(page.locator('.toast-body').first()).toContainText(/Tüm üniversite hocaları/i);

  // 5. Formu Doldur ve Kaydet
  const deptName = `Test Bölüm ${Date.now()}`;
  await page.getByRole('textbox', { name: 'Adı (required)' }).fill(deptName);

  // Bölüm Başkanı Seç
  await page.evaluate(() => {
    const el = document.querySelector('#chairperson_id') as any;
    if (el && el.tomselect) {
      const keys = Object.keys(el.tomselect.options);
      const validKey = keys.find(k => k && k !== '0' && k !== '');
      if (validKey) el.tomselect.setValue(validKey);
    }
  });

  const savePromise = page.waitForResponse(response =>
    response.url().includes('/ajax/addDepartment') && response.status() === 200
  );
  await page.getByRole('button', { name: 'Ekle' }).click();
  await savePromise;

  // 6. Bölüm Listesinde Arama Yaparak Doğrulama
  await page.goto('http://schedulemaker.loc/admin/listdepartments');
  const searchInput = page.locator('input[type="search"]');
  if (await searchInput.isVisible()) {
    await searchInput.fill(deptName);
    await page.waitForTimeout(500);
  }
  await expect(page.locator('table.dataTable, table')).toContainText(deptName);
});
