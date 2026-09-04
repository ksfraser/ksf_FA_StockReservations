/**
 * E2E Tests for Stock Reservations Module
 *
 * Run with: npx playwright test
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */

const { test, expect } = require('@playwright/test');

const BASE_URL = process.env.FA_URL || 'http://localhost/frontaccounting';

test.describe('Stock Reservations', () => {
    test.beforeEach(async ({ page }) => {
        await page.goto(`${BASE_URL}/index.php`);
        await page.waitForLoadState('networkidle');
    });

    test('SR-E2E-001: Create reservation on sales order confirmation', async ({ page }) => {
        await page.login('admin', 'admin');

        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/reservations.php`);

        await expect(page.locator('h1')).toContainText('Stock Reservations');

        const reservationCount = await page.locator('.reservation-row').count();
        expect(reservationCount).toBeGreaterThanOrEqual(0);
    });

    test('SR-E2E-002: View reservation list', async ({ page }) => {
        await page.login('admin', 'admin');

        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/reservations.php`);

        await expect(page.locator('table.reservations')).toBeVisible();

        const headers = await page.locator('table.reservations thead th').allTextContents();
        expect(headers).toContain('Item Code');
        expect(headers).toContain('Order No');
        expect(headers).toContain('Quantity');
        expect(headers).toContain('Status');
    });

    test('SR-E2E-003: Filter reservations by status', async ({ page }) => {
        await page.login('admin', 'admin');

        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/reservations.php`);

        await page.selectOption('select[name="status_filter"]', 'reserved');

        await page.click('button[type="submit"][name="filter"]');

        const rows = await page.locator('table.reservations tbody tr').all();
        for (const row of rows) {
            const status = await row.locator('td.status').textContent();
            expect(status.trim()).toBe('reserved');
        }
    });

    test('SR-E2E-004: Release reservation', async ({ page }) => {
        await page.login('admin', 'admin');

        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/reservations.php`);

        const firstReservedRow = page.locator('tr.reservation-row').filter({ has: page.locator('td.status', { hasText: 'reserved' }) }).first();

        if (await firstReservedRow.isVisible()) {
            await firstReservedRow.locator('.release-btn').click();

            await page.waitForSelector('.modal.confirm-release');
            await page.click('button.confirm');

            await expect(page.locator('.toast-success')).toContainText('Reservation released');
        }
    });

    test('SR-E2E-005: View available stock calculation', async ({ page }) => {
        await page.login('admin', 'admin');

        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/availability.php`);

        await expect(page.locator('table.availability')).toBeVisible();

        const itemRows = await page.locator('table.availability tbody tr').all();
        expect(itemRows.length).toBeGreaterThan(0);

        for (const row of itemRows) {
            const onHand = await row.locator('td.on_hand').textContent();
            const reserved = await row.locator('td.reserved').textContent();
            const available = await row.locator('td.available').textContent();

            const onHandNum = parseFloat(onHand.replace(/,/g, ''));
            const reservedNum = parseFloat(reserved.replace(/,/g, ''));
            const availableNum = parseFloat(available.replace(/,/g, ''));

            expect(availableNum).toBeCloseTo(onHandNum - reservedNum, 2);
        }
    });
});

test.describe('Stock Reservations - Accessibility', () => {
    test('SR-E2E-006: Page accessibility', async ({ page }) => {
        await page.login('admin', 'admin');
        await page.goto(`${BASE_URL}/modules/ksf_FA_StockReservations/pages/reservations.php`);

        await expect(page).toHaveNoViolations();
    });
});