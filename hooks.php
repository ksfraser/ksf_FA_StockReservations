<?php
declare(strict_types=1);

define('SS_ksf_FA_StockReservations', 145 << 8);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/ComposerDependencies.php';

class hooks_ksf_FA_StockReservations extends hooks
{
    var $module_name = 'ksf_FA_StockReservations';
    var $version = '2.4.19-1.0.0';

    function install_extension($check_only=true)
    {
        if (!$check_only) {
            \ksfraser\FrontAccounting\Common\Utils\ComposerDependencies::ensure(__DIR__);
        }
        return true;
    }

    function activate_extension($company, $check_only=true)
    {
        if (!file_exists(dirname(__FILE__) . '/sql/install.sql')) {
            return true;
        }

        $updates = array(
            'sql/install.sql' => array(
                '0_ksf_stock_reservations',
            ),
        );

        return $this->update_databases($company, $updates, $check_only);
    }

    function deactivate_extension($company, $check_only=true)
    {
        return true;
    }

    function getModuleConstants(&$data, $opts = [])
    {
        $data['constants']['SS_ksf_FA_StockReservations'] = SS_ksf_FA_StockReservations;
        $data['constants']['SA_ksf_FA_STOCKRESERVATIONS'] = SS_ksf_FA_StockReservations | 1;
        $data['constants']['SA_ksf_FA_STOCKRESERVATIONS_VIEW'] = SS_ksf_FA_StockReservations | 2;
        return $data;
    }

    function getModuleCapabilities(&$data, $opts = [])
    {
        $data['capabilities']['stock_reservations'] = [
            'create' => 'SA_ksf_FA_STOCKRESERVATIONS',
            'view' => 'SA_ksf_FA_STOCKRESERVATIONS_VIEW',
            'release' => 'SA_ksf_FA_STOCKRESERVATIONS',
            'pick' => 'SA_ksf_FA_STOCKRESERVATIONS',
            'ship' => 'SA_ksf_FA_STOCKRESERVATIONS',
        ];
        return $data;
    }

    public function hasCapability(&$data, $opts = null)
    {
        $capability = isset($opts['capability']) ? $opts['capability'] : (isset($data['capability']) ? $data['capability'] : null);
        if ($capability === null) {
            $data['has_capability'] = false;
            return false;
        }
        $caps = ['create', 'view', 'release', 'pick', 'ship'];
        $hasCapability = in_array($capability, $caps);
        $data['has_capability'] = $hasCapability;
        return $hasCapability;
    }

    public function respondToCapabilityRequest(&$data, $opts = null)
    {
        $request = isset($opts['request']) ? $opts['request'] : (isset($data['request']) ? $data['request'] : 'capabilities');
        $data['request'] = $request;
        $data['module'] = $this->module_name;

        if (strpos($request, 'has:') === 0) {
            $capability = substr($request, 4);
            return $this->hasCapability($data, ['capability' => $capability]);
        }

        switch ($request) {
            case 'capabilities':
                $data['capabilities'] = $this->getModuleCapabilities($data, $opts);
                return $data['capabilities'];
            default:
                return null;
        }
    }

    function install_access()
    {
        $security_sections[SS_ksf_FA_StockReservations] = _("Stock Reservations");
        $security_areas['SA_ksf_FA_STOCKRESERVATIONS'] = array(
            SS_ksf_FA_StockReservations | 1,
            _("Manage Stock Reservations")
        );
        $security_areas['SA_ksf_FA_STOCKRESERVATIONS_VIEW'] = array(
            SS_ksf_FA_StockReservations | 2,
            _("View Stock Reservations")
        );
        return array($security_areas, $security_sections);
    }

    /**
     * FA hook: db_postwrite — fires after ANY transaction is written.
     * We trap ST_SALESORDER to reserve stock, ST_CUSTDELIVERY to release stock.
     *
     * @param object $cart
     * @param int $trans_type
     */
    function db_postwrite($cart, $trans_type)
    {
        if ($trans_type == ST_SALESORDER) {
            $this->handleSalesOrderCreated($cart);
        } elseif ($trans_type == ST_CUSTDELIVERY) {
            $this->handleDeliveryCreated($cart);
        }
    }

    /**
     * FA hook: db_prevoid — fires before a transaction is voided.
     * We trap ST_SALESORDER to release reserved stock.
     *
     * @param int $trans_type
     * @param int $trans_no
     */
    function db_prevoid($trans_type, $trans_no)
    {
        if ($trans_type == ST_SALESORDER) {
            $this->handleSalesOrderVoided($trans_no);
        }
    }

    /**
     * Handle new sales order — reserve stock and emit stock_reserved.
     *
     * @param object $cart
     */
    private function handleSalesOrderCreated($cart): void
    {
        $orderNo = is_object($cart) ? $cart->order_no : 0;
        if ($orderNo <= 0) {
            return;
        }

        $items = $this->extractLineItems($cart);
        if (empty($items)) {
            return;
        }

        $reserved = $this->reserveStock($orderNo, $items);
        if (empty($reserved)) {
            $this->emitInsufficientStock($orderNo, $items);
            return;
        }

        $data = [
            'module'      => 'ksf_FA_StockReservations',
            'event'       => 'stock_reserved',
            'timestamp'   => date('Y-m-d H:i:s'),
            'so_order_no' => $orderNo,
            'items'       => $reserved,
        ];

        hook_invoke_all('stock_reserved', $data);
    }

    /**
     * Handle delivery — release reserved stock.
     *
     * @param object $cart
     */
    private function handleDeliveryCreated($cart): void
    {
        $deliveryNo = is_object($cart) ? ($cart->delivery_number ?? 0) : 0;
        if ($deliveryNo <= 0) {
            return;
        }

        $released = $this->releaseStockForDelivery($deliveryNo);
        if (empty($released)) {
            return;
        }

        $data = [
            'module'        => 'ksf_FA_StockReservations',
            'event'         => 'stock_released',
            'timestamp'     => date('Y-m-d H:i:s'),
            'delivery_no'   => $deliveryNo,
            'items'        => $released,
            'reason'       => 'delivered',
        ];

        hook_invoke_all('stock_released', $data);
    }

    /**
     * Handle void — release reserved stock.
     *
     * @param int $trans_no
     */
    private function handleSalesOrderVoided($trans_no): void
    {
        $released = $this->releaseStockForOrder($trans_no);
        if (empty($released)) {
            return;
        }

        $data = [
            'module'       => 'ksf_FA_StockReservations',
            'event'        => 'stock_released',
            'timestamp'    => date('Y-m-d H:i:s'),
            'so_order_no'  => $trans_no,
            'items'        => $released,
            'reason'       => 'voided',
        ];

        hook_invoke_all('stock_released', $data);
    }

    /**
     * Emit stock_insufficient event when stock check fails.
     *
     * @param int $orderNo
     * @param array $items
     */
    private function emitInsufficientStock($orderNo, $items): void
    {
        $insufficient = [];
        foreach ($items as $item) {
            $available = $this->getAvailableQty($item['stock_id']);
            if ($available < $item['quantity']) {
                $insufficient[] = [
                    'stock_id'  => $item['stock_id'],
                    'requested' => $item['quantity'],
                    'available' => $available,
                ];
            }
        }

        if (empty($insufficient)) {
            return;
        }

        $data = [
            'module'      => 'ksf_FA_StockReservations',
            'event'       => 'stock_insufficient',
            'timestamp'   => date('Y-m-d H:i:s'),
            'so_order_no' => $orderNo,
            'items'       => $insufficient,
        ];

        hook_invoke_all('stock_insufficient', $data);
    }

    /**
     * Extract line items from cart.
     *
     * @param object $cart
     * @return array
     */
    private function extractLineItems($cart): array
    {
        if (!is_object($cart) || !isset($cart->line_items)) {
            return [];
        }

        $items = [];
        foreach ($cart->line_items as $line) {
            $items[] = [
                'stock_id' => $line->stock_id,
                'quantity' => $line->quantity,
            ];
        }
        return $items;
    }

    /**
     * Reserve stock for an order.
     * TODO: Implement actual reservation logic.
     *
     * @param int $orderNo
     * @param array $items
     * @return array
     */
    private function reserveStock($orderNo, $items): array
    {
        return [];
    }

    /**
     * Release stock for a delivery.
     * TODO: Implement actual release logic.
     *
     * @param int $deliveryNo
     * @return array
     */
    private function releaseStockForDelivery($deliveryNo): array
    {
        return [];
    }

    /**
     * Release stock for a voided order.
     * TODO: Implement actual release logic.
     *
     * @param int $orderNo
     * @return array
     */
    private function releaseStockForOrder($orderNo): array
    {
        return [];
    }

    /**
     * Get available quantity for a stock item.
     * TODO: Implement actual qty check.
     *
     * @param string $stockId
     * @return float
     */
    private function getAvailableQty($stockId): float
    {
        return 0.0;
    }
}