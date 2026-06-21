<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;

class WmsCallbackLogControllerTest extends TestCase
{
    public function test_controller_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Http\Controllers\WmsCallbackLogController::class)
        );
    }

    public function test_wms_callback_route_outside_auth(): void
    {
        $routesFile = __DIR__ . '/../../../../routes/dropship_api.php';
        $content = file_get_contents($routesFile);

        $lines = explode("\n", $content);
        $callbackLine = null;
        $authLine = null;
        foreach ($lines as $i => $line) {
            if (strpos($line, 'wms/callback') !== false) {
                $callbackLine = $i;
            }
            if (strpos($line, "middleware('auth:sanctum')") !== false) {
                $authLine = $i;
            }
        }
        $this->assertNotNull($callbackLine);
        $this->assertNotNull($authLine);
        $this->assertLessThan($authLine, $callbackLine, 'WMS callback should be outside auth middleware');
    }

    public function test_callback_log_routes_are_index_and_show_only(): void
    {
        $routesFile = __DIR__ . '/../../../../routes/dropship_api.php';
        $content = file_get_contents($routesFile);
        $this->assertStringContainsString("only(['index', 'show'])", $content);
    }
}
