<?php

declare(strict_types=1);

namespace Tests\Unit\Tenancy;

use App\Models\Shop;
use App\Tenancy\TenantContext;
use Exception;
use Tests\TestCase;

class TenantContextTest extends TestCase
{
    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_run_as_sets_tenant_and_restores_previous_context(): void
    {
        $shop1 = new Shop(['name' => 'Shop One']);
        $shop1->id = 1;

        $shop2 = new Shop(['name' => 'Shop Two']);
        $shop2->id = 2;

        TenantContext::setShop($shop1);
        $this->assertSame(1, TenantContext::getShopId());

        $executed = false;
        $result = TenantContext::runAs($shop2, function (Shop $current) use (&$executed) {
            $executed = true;
            $this->assertSame(2, TenantContext::getShopId());
            $this->assertSame('Shop Two', $current->name);

            return 'success';
        });

        $this->assertTrue($executed);
        $this->assertSame('success', $result);
        $this->assertSame(1, TenantContext::getShopId());
    }

    public function test_run_as_restores_context_even_when_exception_is_thrown(): void
    {
        $shopOriginal = new Shop(['name' => 'Original Shop']);
        $shopOriginal->id = 99;

        $shopTemporary = new Shop(['name' => 'Temporary Shop']);
        $shopTemporary->id = 100;

        TenantContext::setShop($shopOriginal);

        try {
            TenantContext::runAs($shopTemporary, function () {
                $this->assertSame(100, TenantContext::getShopId());
                throw new Exception('Something broke inside runAs');
            });
            $this->fail('Exception should have been thrown');
        } catch (Exception $e) {
            $this->assertSame('Something broke inside runAs', $e->getMessage());
        }

        $this->assertSame(99, TenantContext::getShopId());
    }
}
