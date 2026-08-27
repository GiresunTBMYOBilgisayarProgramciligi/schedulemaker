import { Page } from '@playwright/test';

export type UserRoleType = 'admin' | 'manager' | 'department_head' | 'lecturer' | 'secretary';

export const TEST_USERS: Record<UserRoleType, { mail: string; password: string }> = {
  admin: { mail: 'testadmin@schedulemaker.loc', password: '123456' },
  manager: { mail: 'testmanager@schedulemaker.loc', password: '123456' },
  department_head: { mail: 'testdepthead@schedulemaker.loc', password: '123456' },
  lecturer: { mail: 'testlecturer@schedulemaker.loc', password: '123456' },
  secretary: { mail: 'testsecretary@schedulemaker.loc', password: '123456' },
};

/**
 * Belirtilen role sahip test kullanıcısı ile oturum açar.
 */
export async function loginAs(page: Page, role: UserRoleType = 'admin') {
  const credentials = TEST_USERS[role];

  await page.goto('/auth/login');
  await page.fill('input[name="mail"]', credentials.mail);
  await page.fill('input[name="password"]', credentials.password);

  await Promise.all([
    page.waitForURL(/\/admin/, { timeout: 10000 }),
    page.click('button[type="submit"]')
  ]);
}

/**
 * Kolaylık fonksiyonu: Admin olarak oturum açar.
 */
export async function loginAsAdmin(page: Page) {
  return loginAs(page, 'admin');
}
