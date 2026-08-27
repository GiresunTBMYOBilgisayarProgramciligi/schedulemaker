import { Page } from '@playwright/test';

/**
 * HTML5 Drag & Drop API'sini DataTransfer payload ile birlikte tam simüle eder.
 */
export async function simulateHtml5DragAndDrop(page: Page, sourceSelector: string, targetSelector: string) {
  await page.evaluate(({ src, dst }) => {
    const source = document.querySelector(src) as HTMLElement;
    const target = document.querySelector(dst) as HTMLElement;

    if (!source || !target) {
      console.error('Source or target not found:', { src, dst });
      return;
    }

    const dataTransfer = new DataTransfer();

    // 1. dragstart
    source.dispatchEvent(new DragEvent('dragstart', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dataTransfer
    }));

    // 2. dragover
    target.dispatchEvent(new DragEvent('dragover', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dataTransfer
    }));

    // 3. drop
    target.dispatchEvent(new DragEvent('drop', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dataTransfer
    }));

    // 4. dragend
    source.dispatchEvent(new DragEvent('dragend', {
      bubbles: true,
      cancelable: true,
      dataTransfer: dataTransfer
    }));
  }, { src: sourceSelector, dst: targetSelector });
}
