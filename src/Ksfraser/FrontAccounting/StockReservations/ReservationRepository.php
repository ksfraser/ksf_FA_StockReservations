<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\Common\Exceptions\ReservationException;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Repository for stock reservation database operations.
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */
class ReservationRepository
{
    /** @var \ksfraser\CommonDb\Contract\DbConnectionInterface */
    private $db;

    /** @var LoggerInterface */
    private $logger;

    /** @var string */
    private $tableName;

    public function __construct(
        \ksfraser\CommonDb\Contract\DbConnectionInterface $db,
        ?LoggerInterface $logger = null,
        string $tableName = '0_ksf_stock_reservations'
    ) {
        $this->db = $db;
        $this->logger = $logger ?? new NullLogger();
        $this->tableName = $tableName;
    }

    /**
     * Create a new reservation.
     *
     * @param ReservationDTO $reservation
     * @return ReservationDTO
     * @throws ReservationException
     *
     * @since 1.0.0
     */
    public function create(ReservationDTO $reservation): ReservationDTO
    {
        $this->logger->debug('Creating reservation', [
            'item_code' => $reservation->getItemCode(),
            'order_no' => $reservation->getOrderNo(),
            'quantity' => $reservation->getQuantity(),
        ]);

        $sql = "INSERT INTO {$this->tableName}
                (item_code, order_no, order_line, quantity, status, reserved_at, reserved_by, notes)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

        try {
            $this->db->executeUpdate($sql, [
                $reservation->getItemCode(),
                $reservation->getOrderNo(),
                $reservation->getOrderLine(),
                $reservation->getQuantity(),
                $reservation->getStatus(),
                $reservation->getReservedAt()->format('Y-m-d H:i:s'),
                $reservation->getReservedBy(),
                $reservation->getNotes(),
            ]);

            $id = $this->db->lastInsertId();
            $this->logger->info('Reservation created', ['id' => $id]);

            return $this->findById((int) $id);
        } catch (\Throwable $e) {
            $this->logger->error('Failed to create reservation', [
                'error' => $e->getMessage(),
            ]);
            throw new ReservationException(
                "Failed to create reservation: {$e->getMessage()}",
                (int) $e->getCode(),
                $e
            );
        }
    }

    /**
     * Find reservation by ID.
     *
     * @param int $id
     * @return ReservationDTO|null
     *
     * @since 1.0.0
     */
    public function findById(int $id): ?ReservationDTO
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE id = ?";
        $row = $this->db->fetchAssoc($sql, [$id]);

        if ($row === false) {
            return null;
        }

        return ReservationDTO::fromArray($row);
    }

    /**
     * Find all reservations for an order.
     *
     * @param string $orderNo
     * @return ReservationDTO[]
     *
     * @since 1.0.0
     */
    public function findByOrder(string $orderNo): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE order_no = ? ORDER BY order_line";
        $rows = $this->db->fetchAll($sql, [$orderNo]);

        return array_map(fn(array $row) => ReservationDTO::fromArray($row), $rows);
    }

    /**
     * Find all reservations for an item.
     *
     * @param string $itemCode
     * @param string|null $status Filter by status
     * @return ReservationDTO[]
     *
     * @since 1.0.0
     */
    public function findByItem(string $itemCode, ?string $status = null): array
    {
        $sql = "SELECT * FROM {$this->tableName} WHERE item_code = ?";
        $params = [$itemCode];

        if ($status !== null) {
            $sql .= " AND status = ?";
            $params[] = $status;
        }

        $sql .= " ORDER BY reserved_at";

        $rows = $this->db->fetchAll($sql, $params);
        return array_map(fn(array $row) => ReservationDTO::fromArray($row), $rows);
    }

    /**
     * Get total reserved quantity for an item.
     *
     * @param string $itemCode
     * @param string|null $excludeStatus Don't count these statuses
     * @return float
     *
     * @since 1.0.0
     */
    public function getTotalReserved(string $itemCode, ?array $excludeStatus = null): float
    {
        $sql = "SELECT COALESCE(SUM(quantity), 0) as total FROM {$this->tableName}
                WHERE item_code = ? AND status NOT IN ('shipped', 'cancelled', 'released')";

        $params = [$itemCode];

        if ($excludeStatus !== null) {
            $placeholders = implode(',', array_fill(0, count($excludeStatus), '?'));
            $sql .= " AND status NOT IN ({$placeholders})";
            $params = array_merge($params, $excludeStatus);
        }

        $result = $this->db->fetchAssoc($sql, $params);
        return (float) ($result['total'] ?? 0);
    }

    /**
     * Update reservation status.
     *
     * @param int $id
     * @param string $status
     * @param int|null $userId
     * @return bool
     *
     * @since 1.0.0
     */
    public function updateStatus(int $id, string $status, ?int $userId = null): bool
    {
        $sql = "UPDATE {$this->tableName} SET status = ?";

        if ($status === 'picked' && $userId !== null) {
            $sql .= ", picked_at = NOW(), picked_by = ?";
        } elseif ($status === 'shipped' && $userId !== null) {
            $sql .= ", shipped_at = NOW(), shipped_by = ?";
        }

        $sql .= " WHERE id = ?";

        $params = $userId !== null && in_array($status, ['picked', 'shipped'])
            ? [$status, $userId, $id]
            : [$status, $id];

        $affected = $this->db->executeUpdate($sql, $params);
        return $affected > 0;
    }

    /**
     * Release reservation by ID.
     *
     * @param int $id
     * @return bool
     *
     * @since 1.0.0
     */
    public function release(int $id): bool
    {
        return $this->updateStatus($id, 'released');
    }

    /**
     * Delete reservation by ID.
     *
     * @param int $id
     * @return bool
     *
     * @since 1.0.0
     */
    public function delete(int $id): bool
    {
        $sql = "DELETE FROM {$this->tableName} WHERE id = ?";
        $affected = $this->db->executeUpdate($sql, [$id]);
        return $affected > 0;
    }

    /**
     * Delete all reservations for an order.
     *
     * @param string $orderNo
     * @return int Number deleted
     *
     * @since 1.0.0
     */
    public function deleteByOrder(string $orderNo): int
    {
        $sql = "DELETE FROM {$this->tableName} WHERE order_no = ? AND status NOT IN ('shipped')";
        return $this->db->executeUpdate($sql, [$orderNo]);
    }
}