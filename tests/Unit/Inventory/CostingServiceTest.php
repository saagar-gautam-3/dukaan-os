<?php

declare(strict_types=1);

namespace Tests\Unit\Inventory;

use App\Services\CostingService;
use PHPUnit\Framework\TestCase;

class CostingServiceTest extends TestCase
{
    protected CostingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CostingService;
    }

    public function test_weighted_average_calculation(): void
    {
        // 10 units @ 100 + 10 units @ 120 = 20 units @ 110
        $result = $this->service->calculateWeightedAverage('10.000', '100.00', '10.000', '120.00');
        $this->assertSame('110.00', $result);

        // 20 units @ 110 + 30 units @ 150 = 50 units (2200 + 4500 = 6700 / 50 = 134)
        $result2 = $this->service->calculateWeightedAverage('20.000', '110.00', '30.000', '150.00');
        $this->assertSame('134.00', $result2);
    }

    public function test_zero_or_negative_current_quantity_adopts_incoming_cost(): void
    {
        // Zero current stock adopts incoming cost directly
        $result = $this->service->calculateWeightedAverage('0.000', '100.00', '5.000', '140.00');
        $this->assertSame('140.00', $result);

        // Negative current stock adopts incoming cost directly
        $resultNegative = $this->service->calculateWeightedAverage('-2.000', '100.00', '5.000', '140.00');
        $this->assertSame('140.00', $resultNegative);
    }

    public function test_zero_incoming_quantity_retains_current_cost(): void
    {
        $result = $this->service->calculateWeightedAverage('15.000', '85.50', '0.000', '120.00');
        $this->assertSame('85.50', $result);
    }
}
