<?php

declare(strict_types=1);

namespace Tests\Feature\Schema;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class SchemaConsistencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_fillable_attribute_exists_as_a_column(): void
    {
        $modelDir = app_path('Models');
        foreach (glob($modelDir.'/*.php') as $file) {
            $class = 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);
            if (! class_exists($class)) {
                continue;
            }

            $model = new $class;
            $fillable = $model->getFillable();
            if ($fillable === []) {
                continue;
            }

            $table = $model->getTable();
            $this->assertTrue(Schema::hasTable($table), "Missing table for {$class}");

            foreach ($fillable as $attribute) {
                $this->assertTrue(
                    Schema::hasColumn($table, $attribute),
                    "Missing column {$table}.{$attribute} for {$class}"
                );
            }
        }
    }
}
