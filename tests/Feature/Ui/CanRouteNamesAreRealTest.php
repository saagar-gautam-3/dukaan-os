<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Support\RoutePermissionMap;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

final class CanRouteNamesAreRealTest extends TestCase
{
    public function test_every_can_route_literal_is_a_real_mapped_route(): void
    {
        $offenders = [];

        foreach ([base_path('app'), resource_path('views')] as $directory) {
            foreach (File::allFiles($directory) as $file) {
                $path = $file->getPathname();
                $relative = str_replace(base_path().DIRECTORY_SEPARATOR, '', $path);

                foreach (file($path, FILE_IGNORE_NEW_LINES) as $index => $line) {
                    if (! preg_match_all("/(?:can_route|@canRoute)\s*\(\s*['\"]([^'\"]+)['\"]/", $line, $matches)) {
                        continue;
                    }

                    foreach ($matches[1] as $name) {
                        if (Route::has($name) && RoutePermissionMap::has($name)) {
                            continue;
                        }

                        $offenders[] = sprintf('%s:%d -> %s', $relative, $index + 1, $name);
                    }
                }
            }
        }

        $this->assertSame([], $offenders, "Unmapped or unknown can_route() names:\n".implode("\n", $offenders));
    }
}
