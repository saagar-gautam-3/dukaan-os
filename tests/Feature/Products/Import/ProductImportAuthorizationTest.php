<?php

declare(strict_types=1);

namespace Tests\Feature\Products\Import;

use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_a1_guest_redirected(): void
    {
        $response = $this->get(route('products.import.create'));
        $response->assertRedirect(route('login'));
    }
}
