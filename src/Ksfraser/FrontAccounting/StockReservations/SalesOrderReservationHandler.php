<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

/**
 * Handles sales order → reservation integration.
 *
 * This is a pure FA adapter that bridges FA's cart object to our reservation system.
 * Traps sales order creation via hooks and creates/releases reservations accordingly.
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */
class SalesOrderReservationHandler
{
    /** @var ReservationService */
    private $reservationService;

    /** @var StockServiceInterface */
    private $stockService;

    public function __construct(
        ReservationService $reservationService,
        StockServiceInterface $stockService
    ) {
        $this->reservationService = $reservationService;
        $this->stockService = $stockService;
    }

    /**
     * Handle sales order post-write: create reservations.
     *
     * @param object $cart FA cart object with line_items, order_no
     *
     * @since 1.0.0
     */
    public function onSalesOrderCreated(object $cart): void
    {
        $orderNo = $cart->order_no ?? 0;
        if ($orderNo <= 0) {
            return;
        }

        $userId = $this->getCurrentUserId();
        $orderLines = $this->extractOrderLines($cart);

        if (empty($orderLines)) {
            return;
        }

        $insufficientStock = $this->checkInsufficientStock($orderLines);

        if (!empty($insufficientStock)) {
            $this->handleInsufficientStock($insufficientStock, $orderNo);
            return;
        }

        try {
            $this->reservationService->createReservationsForOrder($orderLines, (string) $orderNo, $userId);
        } catch (\Exception $e) {
            error_log('StockReservations: Failed to create reservations - ' . $e->getMessage());
        }
    }

    /**
     * Handle sales order void: release reservations.
     *
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    public function onSalesOrderVoided(int $orderNo): void
    {
        if ($orderNo <= 0) {
            return;
        }

        try {
            $this->reservationService->releaseOrderReservations((string) $orderNo);
        } catch (\Exception $e) {
            error_log('StockReservations: Failed to release reservations - ' . $e->getMessage());
        }
    }

    /**
     * Extract order lines from cart.
     *
     * @param object $cart
     * @return array
     *
     * @since 1.0.0
     */
    private function extractOrderLines(object $cart): array
    {
        $lines = [];

        if (!is_array($cart->line_items)) {
            return $lines;
        }

        foreach ($cart->line_items as $line) {
            if (!is_object($line)) {
                continue;
            }
            if (($line->quantity ?? 0) <= 0) {
                continue;
            }
            $lines[] = [
                'item_code' => $line->stock_id ?? '',
                'quantity' => (float) $line->quantity,
                'order_line' => $line->line_number ?? 1,
            ];
        }

        return $lines;
    }

    /**
     * Check for insufficient stock across all order lines.
     *
     * @param array $orderLines
     * @return array Items with insufficient stock
     *
     * @since 1.0.0
     */
    private function checkInsufficientStock(array $orderLines): array
    {
        $insufficient = [];

        foreach ($orderLines as $line) {
            $itemCode = $line['item_code'];
            $requestedQty = $line['quantity'];
            $availableQty = $this->stockService->getAvailableQuantity($itemCode);

            if ($availableQty < $requestedQty) {
                $insufficient[] = [
                    'item_code' => $itemCode,
                    'requested' => $requestedQty,
                    'available' => $availableQty,
                    'shortage' => $requestedQty - $availableQty,
                ];
            }
        }

        return $insufficient;
    }

    /**
     * Handle insufficient stock: create suggested PO and display warning.
     *
     * @param array $insufficientItems
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    private function handleInsufficientStock(array $insufficientItems, int $orderNo): void
    {
        $shortages = [];
        foreach ($insufficientItems as $item) {
            $shortages[] = sprintf(
                '%s: requested %s, available %s (short %s)',
                $item['item_code'],
                $item['requested'],
                $item['available'],
                $item['shortage']
            );
        }

        $message = sprintf(
            _("Stock Reservations: Insufficient stock for Sales Order #%d. Suggested PO created. Shortages:\n%s"),
            $orderNo,
            implode("\n", $shortages)
        );

        if (function_exists('display_warning')) {
            display_warning($message);
        }

        $this->createSuggestedPurchaseOrders($insufficientItems, $orderNo);

        error_log('StockReservations: ' . $message);
    }

<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

/**
 * Handles sales order → reservation integration.
 *
 * This is a pure FA adapter that bridges FA's cart object to our reservation system.
 * Traps sales order creation via hooks and creates/releases reservations accordingly.
 * Emits hooks for other modules (like SuggestedPO) to react to insufficient stock.
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */
class SalesOrderReservationHandler
{
    /** @var ReservationService */
    private $reservationService;

    /** @var StockServiceInterface */
    private $stockService;

    public function __construct(
        ReservationService $reservationService,
        StockServiceInterface $stockService
    ) {
        $this->reservationService = $reservationService;
        $this->stockService = $stockService;
    }

    /**
     * Handle sales order post-write: create reservations.
     *
     * @param object $cart FA cart object (NOT modified - read only)
     *
     * @since 1.0.0
     */
    public function onSalesOrderCreated(object $cart): void
    {
        $orderNo = $cart->order_no ?? 0;
        if ($orderNo <= 0) {
            return;
        }

        $userId = $this->getCurrentUserId();
        $orderLines = $this->extractOrderLines($cart);

        if (empty($orderLines)) {
            return;
        }

        $insufficientStock = $this->checkInsufficientStock($orderLines);

        if (!empty($insufficientStock)) {
            $this->handleInsufficientStock($insufficientStock, $orderNo);
            return;
        }

        try {
            $this->reservationService->createReservationsForOrder($orderLines, (string) $orderNo, $userId);
        } catch (\Exception $e) {
            error_log('StockReservations: Failed to create reservations - ' . $e->getMessage());
        }
    }

    /**
     * Handle sales order void: release reservations.
     *
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    public function onSalesOrderVoided(int $orderNo): void
    {
        if ($orderNo <= 0) {
            return;
        }

        try {
            $this->reservationService->releaseOrderReservations((string) $orderNo);
        } catch (\Exception $e) {
            error_log('StockReservations: Failed to release reservations - ' . $e->getMessage());
        }
    }

    /**
     * Extract order lines from cart (read-only, no modification).
     *
     * @param object $cart
     * @return array
     *
     * @since 1.0.0
     */
    private function extractOrderLines(object $cart): array
    {
        $lines = [];

        if (!is_array($cart->line_items)) {
            return $lines;
        }

        foreach ($cart->line_items as $line) {
            if (!is_object($line)) {
                continue;
            }
            if (($line->quantity ?? 0) <= 0) {
                continue;
            }
            $lines[] = [
                'item_code' => $line->stock_id ?? '',
                'quantity' => (float) $line->quantity,
                'order_line' => $line->line_number ?? 1,
            ];
        }

        return $lines;
    }

    /**
     * Check for insufficient stock across all order lines.
     *
     * @param array $orderLines
     * @return array Items with insufficient stock
     *
     * @since 1.0.0
     */
    private function checkInsufficientStock(array $orderLines): array
    {
        $insufficient = [];

        foreach ($orderLines as $line) {
            $itemCode = $line['item_code'];
            $requestedQty = $line['quantity'];
            $availableQty = $this->stockService->getAvailableQuantity($itemCode);

            if ($availableQty < $requestedQty) {
                $insufficient[] = [
                    'item_code' => $itemCode,
                    'requested' => $requestedQty,
                    'available' => $availableQty,
                    'shortage' => $requestedQty - $availableQty,
                ];
            }
        }

        return $insufficient;
    }

    /**
     * Handle insufficient stock: emit hook for other modules (e.g. SuggestedPO).
     *
     * @param array $insufficientItems
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    private function handleInsufficientStock(array $insufficientItems, int $orderNo): void
    {
        $shortages = [];
        foreach ($insufficientItems as $item) {
            $shortages[] = sprintf(
                '%s: requested %s, available %s (short %s)',
                $item['item_code'],
                $item['requested'],
                $item['available'],
                $item['shortage']
            );
        }

        $message = sprintf(
            _("Stock Reservations: Insufficient stock for Sales Order #%d. Shortages:\n%s"),
            $orderNo,
            implode("\n", $shortages)
        );

        if (function_exists('display_warning')) {
            display_warning($message);
        }

        error_log('StockReservations: ' . $message);

        $this->broadcastInsufficientStockHook($insufficientItems, $orderNo);
    }

    /**
     * Broadcast hook so other modules (like SuggestedPO) can react.
     *
     * @param array $insufficientItems
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    private function broadcastInsufficientStockHook(array $insufficientItems, int $orderNo): void
    {
        $data = [
            'module' => 'ksf_FA_StockReservations',
            'event' => 'insufficient_stock',
            'order_no' => $orderNo,
            'items' => $insufficientItems,
            'timestamp' => date('Y-m-d H:i:s'),
        ];

        if (function_exists('hook_invoke_all')) {
            hook_invoke_all('stock_reservation_insufficient', $data);
        }
    }

    /**
     * Get current user ID from session.
     *
     * @return int
     *
     * @since 1.0.0
     */
    private function getCurrentUserId(): int
    {
        if (isset($_SESSION['wa_current_user']->user)) {
            return (int) $_SESSION['wa_current_user']->user;
        }
        return 0;
    }
}
