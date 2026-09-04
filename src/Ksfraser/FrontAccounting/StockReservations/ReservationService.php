<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\Common\Exceptions\ReservationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Service for managing stock reservations.
 *
 * Validates stock availability and creates/releases reservations.
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */
class ReservationService
{
    public const STATUS_RESERVED = 'reserved';
    public const STATUS_PICKED = 'picked';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_RELEASED = 'released';

    /** @var ReservationRepository */
    private $repository;

    /** @var StockServiceInterface */
    private $stockService;

    /** @var LoggerInterface */
    private $logger;

    public function __construct(
        ReservationRepository $repository,
        StockServiceInterface $stockService,
        ?LoggerInterface $logger = null
    ) {
        $this->repository = $repository;
        $this->stockService = $stockService;
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Create a reservation if stock is available.
     *
     * @param string $itemCode
     * @param string $orderNo
     * @param float $quantity
     * @param int $orderLine
     * @param int $userId
     * @return ReservationDTO
     * @throws ReservationException
     *
     * @since 1.0.0
     */
    public function createReservation(
        string $itemCode,
        string $orderNo,
        float $quantity,
        int $orderLine = 1,
        int $userId = 0
    ): ReservationDTO {
        $this->logger->debug('Creating reservation', compact('itemCode', 'orderNo', 'quantity'));

        if ($quantity <= 0) {
            throw new ReservationException('Reservation quantity must be positive');
        }

        $available = $this->stockService->getAvailableQuantity($itemCode);

        if ($available < $quantity) {
            throw new ReservationException(
                "Insufficient stock for {$itemCode}: available {$available}, requested {$quantity}"
            );
        }

        $reservation = new ReservationDTO(
            $itemCode,
            $orderNo,
            $quantity,
            $orderLine,
            $userId,
            self::STATUS_RESERVED
        );

        return $this->repository->create($reservation);
    }

    /**
     * Create reservations for all line items in an order.
     *
     * @param array $orderLines Array of ['item_code' => '', 'quantity' => 0, 'order_line' => 1]
     * @param string $orderNo
     * @param int $userId
     * @return ReservationDTO[]
     * @throws ReservationException
     *
     * @since 1.0.0
     */
    public function createReservationsForOrder(array $orderLines, string $orderNo, int $userId = 0): array
    {
        $reservations = [];

        foreach ($orderLines as $line) {
            $reservation = $this->createReservation(
                $line['item_code'],
                $orderNo,
                (float) $line['quantity'],
                (int) ($line['order_line'] ?? 1),
                $userId
            );
            $reservations[] = $reservation;
        }

        return $reservations;
    }

    /**
     * Release all reservations for an order.
     *
     * @param string $orderNo
     * @return int Number released
     *
     * @since 1.0.0
     */
    public function releaseOrderReservations(string $orderNo): int
    {
        $reservations = $this->repository->findByOrder($orderNo);
        $released = 0;

        foreach ($reservations as $reservation) {
            if ($reservation->getStatus() === self::STATUS_RESERVED) {
                $this->repository->release($reservation->getId());
                $released++;
            }
        }

        $this->logger->info("Released {$released} reservations for order {$orderNo}");
        return $released;
    }

    /**
     * Pick a reservation (mark as picked).
     *
     * @param int $reservationId
     * @param int $userId
     * @return bool
     *
     * @since 1.0.0
     */
    public function pickReservation(int $reservationId, int $userId): bool
    {
        return $this->repository->updateStatus($reservationId, self::STATUS_PICKED, $userId);
    }

    /**
     * Ship a reservation (mark as shipped).
     *
     * @param int $reservationId
     * @param int $userId
     * @return bool
     *
     * @since 1.0.0
     */
    public function shipReservation(int $reservationId, int $userId): bool
    {
        return $this->repository->updateStatus($reservationId, self::STATUS_SHIPPED, $userId);
    }

    /**
     * Get available quantity (on_hand - reserved).
     *
     * @param string $itemCode
     * @return float
     *
     * @since 1.0.0
     */
    public function getAvailableQuantity(string $itemCode): float
    {
        return $this->stockService->getAvailableQuantity($itemCode);
    }

    /**
     * Get all reservations for an item.
     *
     * @param string $itemCode
     * @return ReservationDTO[]
     *
     * @since 1.0.0
     */
    public function getReservationsForItem(string $itemCode): array
    {
        return $this->repository->findByItem($itemCode);
    }

    /**
     * Get all reservations for an order.
     *
     * @param string $orderNo
     * @return ReservationDTO[]
     *
     * @since 1.0.0
     */
    public function getReservationsForOrder(string $orderNo): array
    {
        return $this->repository->findByOrder($orderNo);
    }
}