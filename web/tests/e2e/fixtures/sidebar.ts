import { expect, type Page } from '@playwright/test';

/**
 * Open the main sidebar drawer on mobile viewports.
 *
 * On mobile-chromium the `<aside id="sidebar">` is off-canvas until
 * the topbar hamburger (`[data-mobile-menu]`) flips
 * `data-mobile-open="true"`. Section accordion links (#1490) live
 * inside that aside, so visibility / click assertions must open the
 * drawer first.
 */
export async function openMobileSidebar(page: Page): Promise<void> {
    const sidebar = page.locator('#sidebar');
    const open = await sidebar.getAttribute('data-mobile-open');
    if (open === 'true') {
        await expect(sidebar).toBeVisible();
        return;
    }

    const hamburger = page.locator('[data-mobile-menu]');
    await expect(hamburger).toBeVisible();
    await hamburger.click();
    await expect(sidebar).toHaveAttribute('data-mobile-open', 'true');
    await expect(sidebar).toBeVisible();
}
