<?php
declare(strict_types=1);

define('SS_ksf_FA_StockReservations', 145 << 8);

class hooks_ksf_FA_StockReservations extends hooks
{
    var $module_name = 'ksf_FA_StockReservations';
    var $version = '2.4.19-1.0.0';

    function install_extension($company, $force = false)
    {
        parent::install_extension($company, $force);

        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            return false;
        }
        require_once $autoload;

        $updates = [
            'sql/install.sql' => '0_ksf_stock_reservations',
        ];

        foreach ($updates as $file => $table) {
            $sqlFile = __DIR__ . '/' . $file;
            if (file_exists($sqlFile)) {
                $sql = file_get_contents($sqlFile);
                $sql = str_replace('0_', get_company_preference($company)['_prefix'], $sql);
                run_db_import($sql, $company);
            }
        }

        return true;
    }

    function activate_extension($company, $force = false)
    {
        $this->install_extension($company, $force);
        add_security_section(SS_ksf_FA_StockReservations, 'Stock Reservations', 'SA_INVENTORY');
        return true;
    }

    function deactivate_extension($company, $force = false)
    {
        $uninstallFile = __DIR__ . '/sql/uninstall.sql';
        if (file_exists($uninstallFile)) {
            $sql = file_get_contents($uninstallFile);
            run_db_import($sql, $company);
        }

        remove_security_section(SS_ksf_FA_StockReservations);
        return parent::deactivate_extension($company, $force);
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

    function hook_invoke_all($hook, &$data)
    {
        $autoload = __DIR__ . '/vendor/autoload.php';
        if (!file_exists($autoload)) {
            return null;
        }
        require_once $autoload;

        return parent::hook_invoke_all($hook, $data);
    }

    /**
     * FA hook: db_postwrite — fires after ANY transaction is written.
     * We trap ST_SALESORDER (sales order) to create reservations.
     *
     * @param object $cart Cart object with ->trans_type, ->line_items, ->order_no
     * @param int $trans_type Transaction type constant
     *
     * @since 1.0.0
     */
    function db_postwrite(&$cart, $trans_type)
    {
        if ($trans_type != ST_SALESORDER) {
            return;
        }

        $order_no = is_object($cart) ? $cart->order_no : 0;
        if ($order_no <= 0) {
            return;
        }

        $this->createReservationsFromOrder($cart);
    }

    /**
     * FA hook: db_prevoid — fires before ANY transaction is voided.
     * We trap ST_SALESORDER to release reservations.
     *
     * @param int $trans_type Transaction type constant
     * @param int $trans_no Transaction number
     *
     * @since 1.0.0
     */
    function db_prevoid($trans_type, $trans_no)
    {
        if ($trans_type != ST_SALESORDER) {
            return;
        }

        $this->releaseReservationsForOrder($trans_no);
    }

    /**
     * Create reservations from a sales order cart.
     *
     * @param object $cart
     *
     * @since 1.0.0
     */
    private function createReservationsFromOrder(&$cart)
    {
        if (!class_exists(\Ksfraser\FrontAccounting\StockReservations\ReservationService::class)) {
            return;
        }

        $db = new \ksfraser\CommonDb\Adapter\FaDbAdapter(TB_PREF);
        $stockService = new \Ksfraser\FrontAccounting\StockReservations\FaStockServiceAdapter(
            new \Ksfraser\FrontAccounting\StockReservations\ReservationRepository($db)
        );
        $service = new \Ksfraser\FrontAccounting\StockReservations\ReservationService(
            new \Ksfraser\FrontAccounting\StockReservations\ReservationRepository($db),
            $stockService
        );

        $userId = isset($_SESSION['wa_current_user']->user) ? (int) $_SESSION['wa_current_user']->user : 0;
        $orderLines = [];

        foreach ($cart->line_items as $line) {
            if (is_object($line) && $line->quantity > 0) {
                $orderLines[] = [
                    'item_code' => $line->stock_id,
                    'quantity' => $line->quantity,
                    'order_line' => $line->line_number,
                ];
            }
        }

        if (!empty($orderLines)) {
            try {
                $service->createReservationsForOrder($orderLines, $cart->order_no, $userId);
            } catch (\Exception $e) {
                error_log('StockReservations: Failed to create reservations - ' . $e->getMessage());
            }
        }
    }

    /**
     * Release reservations when a sales order is voided.
     *
     * @param int $orderNo
     *
     * @since 1.0.0
     */
    private function releaseReservationsForOrder(int $orderNo)
    {
        if (!class_exists(\Ksfraser\FrontAccounting\StockReservations\ReservationService::class)) {
            return;
        }

        $db = new \ksfraser\CommonDb\Adapter\FaDbAdapter(TB_PREF);
        $stockService = new \Ksfraser\FrontAccounting\StockReservations\FaStockServiceAdapter(
            new \Ksfraser\FrontAccounting\StockReservations\ReservationRepository($db)
        );
        $service = new \Ksfraser\FrontAccounting\StockReservations\ReservationService(
            new \Ksfraser\FrontAccounting\StockReservations\ReservationRepository($db),
            $stockService
        );

        try {
            $service->releaseOrderReservations((string) $orderNo);
        } catch (\Exception $e) {
            error_log('StockReservations: Failed to release reservations - ' . $e->getMessage());
        }
    }
}