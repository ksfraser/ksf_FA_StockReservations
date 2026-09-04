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
        add_pagesecurity($page_security, 'SA_ksf_FA_StockReservations', ['SA_INVENTORY']);
        return true;
    }

    function deactivate_extension($company, $force = false)
    {
        $prefix = get_company_preference($company)['_prefix'] ?? '0_';
        $sql = "DROP TABLE IF EXISTS {$prefix}ksf_stock_reservations";
        db_query($sql, "Cannot drop reservations table");

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
}