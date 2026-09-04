<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

// Test bootstrap - sets up mocks for FA functions
if (!function_exists('db_query')) {
    function db_query($sql, $err = '')
    {
        return \Ksfraser\FrontAccounting\StockReservations\tests\Mocks\db_query($sql);
    }
}

if (!function_exists('db_fetch_assoc')) {
    function db_fetch_assoc($result)
    {
        return \Ksfraser\FrontAccounting\StockReservations\tests\Mocks\db_fetch_assoc($result);
    }
}

if (!function_exists('db_insert_id')) {
    function db_insert_id()
    {
        return \Ksfraser\FrontAccounting\StockReservations\tests\Mocks\db_insert_id();
    }
}