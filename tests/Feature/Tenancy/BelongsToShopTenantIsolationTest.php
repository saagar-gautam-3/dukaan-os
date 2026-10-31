<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

class BelongsToShopTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_belongs_to_shop_model_has_factory_and_is_tenant_isolated(): void
    {
        $models = $this->tenantModels();

        $this->assertNotEmpty($models);

        foreach ($models as $modelClass) {
            $this->assertTrue(
                method_exists($modelClass, 'factory'),
                "Tenant model {$modelClass} must have a factory."
            );

            $shopB = Shop::factory()->create(['name' => 'Shop B']);

            /** @var Model $record */
            $record = $modelClass::factory()->create();

            $id = $record->getKey();

            // Read isolation
            TenantContext::runAs($shopB, function () use ($modelClass, $id) {
                $this->assertNull(
                    $modelClass::find($id),
                    "Shop B must not read {$modelClass} #{$id} from Shop A."
                );
                $this->assertSame(
                    0,
                    $modelClass::whereKey($id)->update(['id' => $id]),
                    "Shop B must not update {$modelClass} #{$id} from Shop A."
                );
                $this->assertSame(
                    0,
                    $modelClass::whereKey($id)->delete(),
                    "Shop B must not delete {$modelClass} #{$id} from Shop A."
                );
            });
        }
    }

    /**
     * @return array<int, class-string<Model>>
     */
    private function tenantModels(): array
    {
        $models = [];
        $dir = app_path('Models');
        foreach (glob($dir.'/*.php') as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');
            if (! class_exists($class)) {
                continue;
            }
            $reflection = new ReflectionClass($class);
            if (! $reflection->isSubclassOf(Model::class) || $reflection->isAbstract()) {
                continue;
            }
            $traits = class_uses_recursive($class);
            if (isset($traits['App\\Traits\\BelongsToShop'])) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }
}
