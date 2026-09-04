<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\StockReservations\ReservationRepository;

/**
 * FA Platform adapter for stock service.
 *
 * Uses FA's stock_master table and db_* functions.
 *
 * @BABOK Related: FR-QA-001-001
 * @since 1.0.0
 */
class FaStockServiceAdapter implements StockServiceInterface
{
    /** @var string */
    private $stockTable;

    /** @var ReservationRepository */
    private $reservationRepository;

    public function __construct(
        ReservationRepository $reservationRepository,
        string $stockTable = '0_stock_master'
    ) {
        $this->reservationRepository = $reservationRepository;
        $this->stockTable = $stockTable;
    }

    /**
     * @inheritDoc
     */
    public function getQuantityOnHand(string $itemCode): float
    {
        $sql = "SELECT quantity FROM {$this->stockTable} WHERE stock_id = ?";

        if (!function_exists('db_fetch_assoc')) {
            throw new \RuntimeException('FA db functions not available');
        }

        $result = db_query($sql, "Cannot fetch quantity on hand for {$itemCode}");
        $row = db_fetch_assoc($result);

        if ($row === false) {
            return 0.0;
        }

        return (float) ($row['quantity'] ?? 0);
    }

    /**
     * @inheritDoc
     */
    public function getAvailableQuantity(string $itemCode): float
    {
        $onHand = $this->getQuantityOnHand($itemCode);
        $reserved = $this->reservationRepository->getTotalReserved($itemCode);

        return $onHand - $reserved;
    }
}