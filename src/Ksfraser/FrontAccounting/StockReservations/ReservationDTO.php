<?php
declare(strict_types=1);

namespace Ksfraser\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\Common\Exceptions\ReservationException;

/**
 * Reservation data transfer object.
 *
 * @BABOK Related: FR-QA-001-001
 * @since 1.0.0
 */
class ReservationDTO
{
    /** @var int|null */
    private $id;

    /** @var string */
    private $itemCode;

    /** @var string */
    private $orderNo;

    /** @var int */
    private $orderLine;

    /** @var float */
    private $quantity;

    /** @var string */
    private $status;

    /** @var \DateTimeInterface */
    private $reservedAt;

    /** @var int */
    private $reservedBy;

    /** @var \DateTimeInterface|null */
    private $pickedAt;

    /** @var int|null */
    private $pickedBy;

    /** @var \DateTimeInterface|null */
    private $shippedAt;

    /** @var int|null */
    private $shippedBy;

    /** @var string|null */
    private $notes;

    public function __construct(
        string $itemCode,
        string $orderNo,
        float $quantity,
        int $orderLine = 1,
        int $reservedBy = 0,
        ?string $status = 'reserved'
    ) {
        $this->itemCode = $itemCode;
        $this->orderNo = $orderNo;
        $this->orderLine = $orderLine;
        $this->quantity = $quantity;
        $this->reservedBy = $reservedBy;
        $this->status = $status ?? 'reserved';
        $this->reservedAt = new \DateTimeImmutable();
    }

    public static function fromArray(array $data): self
    {
        $dto = new self(
            $data['item_code'],
            $data['order_no'],
            (float) $data['quantity'],
            (int) ($data['order_line'] ?? 1),
            (int) ($data['reserved_by'] ?? 0),
            $data['status'] ?? 'reserved'
        );
        $dto->id = isset($data['id']) ? (int) $data['id'] : null;
        $dto->reservedAt = isset($data['reserved_at'])
            ? new \DateTimeImmutable($data['reserved_at'])
            : new \DateTimeImmutable();
        if (isset($data['picked_at'])) {
            $dto->pickedAt = new \DateTimeImmutable($data['picked_at']);
        }
        if (isset($data['picked_by'])) {
            $dto->pickedBy = (int) $data['picked_by'];
        }
        if (isset($data['shipped_at'])) {
            $dto->shippedAt = new \DateTimeImmutable($data['shipped_at']);
        }
        if (isset($data['shipped_by'])) {
            $dto->shippedBy = (int) $data['shipped_by'];
        }
        $dto->notes = $data['notes'] ?? null;
        return $dto;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getItemCode(): string
    {
        return $this->itemCode;
    }

    public function getOrderNo(): string
    {
        return $this->orderNo;
    }

    public function getOrderLine(): int
    {
        return $this->orderLine;
    }

    public function getQuantity(): float
    {
        return $this->quantity;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getReservedAt(): \DateTimeInterface
    {
        return $this->reservedAt;
    }

    public function getReservedBy(): int
    {
        return $this->reservedBy;
    }

    public function getPickedAt(): ?\DateTimeInterface
    {
        return $this->pickedAt;
    }

    public function getPickedBy(): ?int
    {
        return $this->pickedBy;
    }

    public function getShippedAt(): ?\DateTimeInterface
    {
        return $this->shippedAt;
    }

    public function getShippedBy(): ?int
    {
        return $this->shippedBy;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    public function withStatus(string $status): self
    {
        $clone = clone $this;
        $clone->status = $status;
        return $clone;
    }

    public function withPicked(int $userId): self
    {
        $clone = clone $this;
        $clone->status = 'picked';
        $clone->pickedAt = new \DateTimeImmutable();
        $clone->pickedBy = $userId;
        return $clone;
    }

    public function withShipped(int $userId): self
    {
        $clone = clone $this;
        $clone->status = 'shipped';
        $clone->shippedAt = new \DateTimeImmutable();
        $clone->shippedBy = $userId;
        return $clone;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->itemCode,
            'order_no' => $this->orderNo,
            'order_line' => $this->orderLine,
            'quantity' => $this->quantity,
            'status' => $this->status,
            'reserved_at' => $this->reservedAt->format('Y-m-d H:i:s'),
            'reserved_by' => $this->reservedBy,
            'picked_at' => $this->pickedAt?->format('Y-m-d H:i:s'),
            'picked_by' => $this->pickedBy,
            'shipped_at' => $this->shippedAt?->format('Y-m-d H:i:s'),
            'shipped_by' => $this->shippedBy,
            'notes' => $this->notes,
        ];
    }
}