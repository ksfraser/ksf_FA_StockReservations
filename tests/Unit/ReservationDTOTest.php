<?php
declare(strict_types=1);

namespace Ksfraser\Tests\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\StockReservations\ReservationDTO;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReservationDTO.
 *
 * @BABOK Related: FR-QA-001-001
 * @since 1.0.0
 */
class ReservationDTOTest extends TestCase
{
    public function testConstructorSetsProperties(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10.0, 1, 1, 'reserved');

        $this->assertEquals('TEST-001', $dto->getItemCode());
        $this->assertEquals('SO-001', $dto->getOrderNo());
        $this->assertEquals(10.0, $dto->getQuantity());
        $this->assertEquals(1, $dto->getOrderLine());
        $this->assertEquals(1, $dto->getReservedBy());
        $this->assertEquals('reserved', $dto->getStatus());
        $this->assertInstanceOf(\DateTimeInterface::class, $dto->getReservedAt());
    }

    public function testFromArrayCreatesDTO(): void
    {
        $data = [
            'id' => 5,
            'item_code' => 'TEST-002',
            'order_no' => 'SO-002',
            'order_line' => 2,
            'quantity' => 25.5,
            'status' => 'picked',
            'reserved_at' => '2026-01-15 10:30:00',
            'reserved_by' => 3,
            'picked_at' => '2026-01-16 14:00:00',
            'picked_by' => 4,
        ];

        $dto = ReservationDTO::fromArray($data);

        $this->assertEquals(5, $dto->getId());
        $this->assertEquals('TEST-002', $dto->getItemCode());
        $this->assertEquals('SO-002', $dto->getOrderNo());
        $this->assertEquals(2, $dto->getOrderLine());
        $this->assertEquals(25.5, $dto->getQuantity());
        $this->assertEquals('picked', $dto->getStatus());
        $this->assertEquals(3, $dto->getReservedBy());
        $this->assertEquals(4, $dto->getPickedBy());
    }

    public function testWithStatusReturnsClone(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10.0, 1, 1, 'reserved');
        $newDto = $dto->withStatus('picked');

        $this->assertEquals('reserved', $dto->getStatus());
        $this->assertEquals('picked', $newDto->getStatus());
        $this->assertNotSame($dto, $newDto);
    }

    public function testWithPickedSetsCorrectFields(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10.0, 1, 1, 'reserved');
        $pickedDto = $dto->withPicked(5);

        $this->assertEquals('picked', $pickedDto->getStatus());
        $this->assertEquals(5, $pickedDto->getPickedBy());
        $this->assertInstanceOf(\DateTimeInterface::class, $pickedDto->getPickedAt());
    }

    public function testWithShippedSetsCorrectFields(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10.0, 1, 1, 'reserved');
        $shippedDto = $dto->withShipped(6);

        $this->assertEquals('shipped', $shippedDto->getStatus());
        $this->assertEquals(6, $shippedDto->getShippedBy());
        $this->assertInstanceOf(\DateTimeInterface::class, $shippedDto->getShippedAt());
    }

    public function testToArrayContainsAllFields(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10.0, 1, 1, 'reserved');
        $array = $dto->toArray();

        $this->assertArrayHasKey('item_code', $array);
        $this->assertArrayHasKey('order_no', $array);
        $this->assertArrayHasKey('quantity', $array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('reserved_at', $array);
        $this->assertArrayHasKey('reserved_by', $array);
    }

    public function testQuantityIsFloat(): void
    {
        $dto = new ReservationDTO('TEST-001', 'SO-001', 10, 1, 1, 'reserved');
        $this->assertIsFloat($dto->getQuantity());
        $this->assertEquals(10.0, $dto->getQuantity());
    }
}