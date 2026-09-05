<?php
declare(strict_types=1);

define('SS_ksf_FA_StockReservations', 145 << 8);

if (file_exists(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

require_once __DIR__ . '/ComposerDependencies.php';
\ksfraser\FrontAccounting\Common\Utils\ComposerDependencies::ensure(__DIR__);

class hooks_ksf_FA_StockReservations extends hooks
{
    var $module_name = 'ksf_FA_StockReservations';
    var $version = '2.4.19-1.0.0';

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
     * Broadcast stock_reservation_insufficient when insufficient stock detected.
     *
     * @param array &$data {
     *     @var string $module
     *     @var string $event
     *     @var int $order_no
     *     @var array $items List of ['item_code' => '', 'shortage' => float]
     *     @var string $timestamp
     * }
     */
}