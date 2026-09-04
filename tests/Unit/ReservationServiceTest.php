<?php
declare(strict_types=1);

namespace Ksfraser\Tests\FrontAccounting\StockReservations;

use Ksfraser\FrontAccounting\StockReservations\ReservationService;
use Ksfraser\FrontAccounting\StockReservations\ReservationRepository;
use Ksfraser\FrontAccounting\StockReservations\StockServiceInterface;
use Ksfraser\FrontAccounting\Common\Exceptions\ReservationException;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReservationService.
 *
 * @BABOK Related: FR-QA-001-001, FR-QA-001-002
 * @since 1.0.0
 */
class ReservationServiceTest extends TestCase
{
    private ReservationService $service;
    private ReservationRepository $mockRepository;
    private StockServiceInterface $mockStockService;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(ReservationRepository::class);
        $this->mockStockService = $this->createMock(StockServiceInterface::class);
        $this->service = new ReservationService(
            $this->mockRepository,
            $this->mockStockService
        );
    }

    public function testCreateReservationSuccess(): void
    {
        $this->mockStockService
            ->expects($this->once())
            ->method('getAvailableQuantity')
            ->with('TEST-001')
            ->willReturn(100.0);

        $this->mockRepository
            ->expects($this->once())
            ->method('create')
            ->willReturnCallback(function ($dto) {
                $this->assertEquals('TEST-001', $dto->getItemCode());
                $this->assertEquals('SO-001', $dto->getOrderNo());
                $this->assertEquals(10.0, $dto->getQuantity());
                return $dto;
            });

        $result = $this->service->createReservation('TEST-001', 'SO-001', 10.0, 1, 1);

        $this->assertEquals('TEST-001', $result->getItemCode());
        $this->assertEquals(10.0, $result->getQuantity());
    }

    public function testCreateReservationInsufficientStock(): void
    {
        $this->mockStockService
            ->expects($this->once())
            ->method('getAvailableQuantity')
            ->with('TEST-001')
            ->willReturn(5.0);

        $this->mockRepository
            ->expects($this->never())
            ->method('create');

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->service->createReservation('TEST-001', 'SO-001', 10.0, 1, 1);
    }

    public function testCreateReservationZeroQuantityThrows(): void
    {
        $this->mockStockService
            ->expects($this->never())
            ->method('getAvailableQuantity');

        $this->expectException(ReservationException::class);
        $this->expectExceptionMessage('positive');

        $this->service->createReservation('TEST-001', 'SO-001', 0, 1, 1);
    }

    public function testCreateReservationNegativeQuantityThrows(): void
    {
        $this->expectException(ReservationException::class);

        $this->service->createReservation('TEST-001', 'SO-001', -5.0, 1, 1);
    }

    public function testCreateReservationsForOrderProcessesAllLines(): void
    {
        $orderLines = [
            ['item_code' => 'TEST-001', 'quantity' => 10, 'order_line' => 1],
            ['item_code' => 'TEST-002', 'quantity' => 20, 'order_line' => 2],
        ];

        $this->mockStockService
            ->method('getAvailableQuantity')
            ->willReturn(100.0);

        $this->mockRepository
            ->expects($this->exactly(2))
            ->method('create')
            ->willReturnCallback(function ($dto) {
                return $dto;
            });

        $results = $this->service->createReservationsForOrder($orderLines, 'SO-001', 1);

        $this->assertCount(2, $results);
        $this->assertEquals('TEST-001', $results[0]->getItemCode());
        $this->assertEquals('TEST-002', $results[1]->getItemCode());
    }

    public function testReleaseOrderReservationsReleasesReservedOnly(): void
    {
        $reservations = [
            \Ksfraser\FrontAccounting\StockReservations\ReservationDTO::fromArray([
                'id' => 1,
                'item_code' => 'TEST-001',
                'order_no' => 'SO-001',
                'quantity' => 10,
                'status' => 'reserved',
            ]),
            \Ksfraser\FrontAccounting\StockReservations\ReservationDTO::fromArray([
                'id' => 2,
                'item_code' => 'TEST-002',
                'order_no' => 'SO-001',
                'quantity' => 20,
                'status' => 'shipped',
            ]),
        ];

        $this->mockRepository
            ->expects($this->once())
            ->method('findByOrder')
            ->with('SO-001')
            ->willReturn($reservations);

        $this->mockRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'released');

        $released = $this->service->releaseOrderReservations('SO-001');

        $this->assertEquals(1, $released);
    }

    public function testPickReservationCallsRepository(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'picked', 5)
            ->willReturn(true);

        $result = $this->service->pickReservation(1, 5);

        $this->assertTrue($result);
    }

    public function testShipReservationCallsRepository(): void
    {
        $this->mockRepository
            ->expects($this->once())
            ->method('updateStatus')
            ->with(1, 'shipped', 5)
            ->willReturn(true);

        $result = $this->service->shipReservation(1, 5);

        $this->assertTrue($result);
    }

    public function testGetAvailableQuantityDelegatesToStockService(): void
    {
        $this->mockStockService
            ->expects($this->once())
            ->method('getAvailableQuantity')
            ->with('TEST-001')
            ->willReturn(75.0);

        $result = $this->service->getAvailableQuantity('TEST-001');

        $this->assertEquals(75.0, $result);
    }
}