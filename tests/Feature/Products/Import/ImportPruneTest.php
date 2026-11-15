<?php

declare(strict_types=1);

namespace Tests\Feature\Products\Import;

use App\Models\ProductImport;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ImportPruneTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    /** @return array{0: Shop, 1: User} */
    protected function createShopWithOwner(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        return [$shop, $user];
    }

    public function test_prune_removes_old_files_and_keeps_new_ones(): void
    {
        [$shop] = $this->createShopWithOwner();

        $oldPath = 'imports/'.$shop->id.'/old.csv';
        $newPath = 'imports/'.$shop->id.'/new.csv';
        $oldFull = storage_path('app/'.$oldPath);
        $newFull = storage_path('app/'.$newPath);
        @mkdir(dirname($oldFull), 0755, true);
        file_put_contents($oldFull, 'old');
        file_put_contents($newFull, 'new');

        $oldImport = ProductImport::factory()->create([
            'shop_id' => $shop->id,
            'stored_path' => $oldPath,
            'created_at' => now()->subDays(10),
        ]);
        $newImport = ProductImport::factory()->create([
            'shop_id' => $shop->id,
            'stored_path' => $newPath,
            'created_at' => now()->subDay(),
        ]);

        $this->artisan('imports:prune', ['--days' => 7])->assertExitCode(0);

        $this->assertFileDoesNotExist($oldFull);
        $this->assertFileExists($newFull);
        $this->assertNull($oldImport->fresh()->stored_path);
        $this->assertSame($newPath, $newImport->fresh()->stored_path);
    }

    public function test_prune_does_not_touch_other_shops(): void
    {
        [$shopA] = $this->createShopWithOwner();
        [$shopB] = $this->createShopWithOwner();

        $pathB = 'imports/'.$shopB->id.'/b.csv';
        $fullB = storage_path('app/'.$pathB);
        @mkdir(dirname($fullB), 0755, true);
        file_put_contents($fullB, 'b');

        $importB = ProductImport::factory()->create([
            'shop_id' => $shopB->id,
            'stored_path' => $pathB,
            'created_at' => now()->subDay(),
        ]);

        TenantContext::setShop($shopA);

        $this->artisan('imports:prune', ['--days' => 7])->assertExitCode(0);

        $this->assertFileExists($fullB);
        $this->assertSame($pathB, $importB->fresh()->stored_path);
    }

    public function test_prune_handles_missing_file_gracefully(): void
    {
        [$shop] = $this->createShopWithOwner();

        $missingPath = 'imports/'.$shop->id.'/missing.csv';
        $import = ProductImport::factory()->create([
            'shop_id' => $shop->id,
            'stored_path' => $missingPath,
            'created_at' => now()->subDays(10),
        ]);

        $this->artisan('imports:prune', ['--days' => 7])->assertExitCode(0);
        $this->assertNull($import->fresh()->stored_path);
    }

    public function test_expired_error_download_shows_friendly_message(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $import = ProductImport::factory()->create([
            'shop_id' => $shop->id,
            'stored_path' => null,
            'created_at' => now()->subDays(10),
            'errors_json' => [['line' => 2, 'reason' => 'bad']],
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.errors', $import));

        $response->assertStatus(404);
        $response->assertSee(__('products.import_file_expired'));
    }

    public function test_schedule_lists_prune_command(): void
    {
        $this->artisan('schedule:list')->expectsOutputToContain('imports:prune');
    }
}
