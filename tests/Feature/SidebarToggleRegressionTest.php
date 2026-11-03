<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class SidebarToggleRegressionTest extends TestCase
{
    public function test_js_sidebar_toggle_selector_exists_in_topbar_layout(): void
    {
        $appJs = file_get_contents(resource_path('js/app.js'));
        $this->assertIsString($appJs);

        preg_match("/getElementById\\('([^']+)'\\)/", $appJs, $matches);
        $this->assertNotEmpty($matches, 'app.js must look up the sidebar toggle by id');

        // Only consider the sidebar toggle lookup (the one assigned to sidebarToggle)
        preg_match("/const sidebarToggle = document\\.getElementById\\('([^']+)'\\)/", $appJs, $m);
        $this->assertNotEmpty($m, 'sidebar toggle lookup must be present');
        $jsId = $m[1];

        $topbar = file_get_contents(resource_path('views/layouts/partials/topbar.blade.php'));
        $this->assertIsString($topbar);
        $this->assertStringContainsString('id="'.$jsId.'"', $topbar,
            "The id the JS looks up ({$jsId}) must exist in topbar.blade.php");
    }
}
