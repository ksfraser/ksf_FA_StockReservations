<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

/**
 * Interface for stock quantity queries.
 *
 * @since 1.0.0
 */
interface StockServiceInterface
{
    /**
     * Get quantity on hand for an item.
     *
     * @param string $itemCode
     * @return float
     *
     * @since 1.0.0
     */
    public function getQuantityOnHand(string $itemCode): float;

    /**
     * Get available quantity (on_hand - reserved).
     *
     * @param string $itemCode
     * @return float
     *
     * @since 1.0.0
     */
    public function getAvailableQuantity(string $itemCode): float;
}