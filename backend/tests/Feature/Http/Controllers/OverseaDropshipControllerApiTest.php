<?php

namespace Tests\Feature\Http\Controllers;

use PHPUnit\Framework\TestCase;

class OverseaDropshipControllerApiTest extends TestCase
{
    protected string $routesFile;
    protected string $routesContent;

    protected function setUp(): void
    {
        parent::setUp();
        $this->routesFile = __DIR__ . '/../../../../routes/dropship_api.php';
        $this->routesContent = file_get_contents($this->routesFile);
    }

    public function test_order_routes_use_auth_middleware(): void
    {
        $this->assertStringContainsString(
            "middleware('auth:sanctum')",
            $this->routesContent,
            'Dropship orders routes should use auth:sanctum middleware'
        );
    }

    public function test_public_wms_callback_route_exists(): void
    {
        $this->assertStringContainsString(
            'wms/callback/{warehouseId}',
            $this->routesContent,
            'WMS callback route should exist outside auth middleware'
        );
    }

    public function test_order_endpoints_are_registered(): void
    {
        $endpoints = [
            'statistics',
            'batch-review',
            'batch-push',
            'status-options',
            'channel-options',
        ];
        foreach ($endpoints as $endpoint) {
            $this->assertStringContainsString(
                "/dropship/{$endpoint}",
                $this->routesContent,
                "Dropship {$endpoint} endpoint should be registered"
            );
        }
    }

    public function test_order_specific_routes_use_order_binding(): void
    {
        $orderActions = [
            'review',
            'push',
            'retry-push',
            'update-status',
            'cancel',
            'sync-tracking',
        ];
        foreach ($orderActions as $action) {
            $this->assertStringContainsString(
                "/orders/{order}/{$action}",
                $this->routesContent,
                "Order {$action} route should use {order} binding"
            );
        }
    }

    public function test_api_resource_orders_uses_correct_names(): void
    {
        $this->assertStringContainsString(
            "parameters(['orders' => 'order'])",
            $this->routesContent,
            'Orders apiResource should use correct parameter binding'
        );
    }

    public function test_controller_resources_are_imported(): void
    {
        $this->assertStringContainsString(
            'use App\Http\Controllers\OverseaDropshipController',
            $this->routesContent
        );
        $this->assertStringContainsString(
            'use App\Http\Controllers\AutomationRuleController',
            $this->routesContent
        );
        $this->assertStringContainsString(
            'use App\Http\Controllers\OverseaWarehouseConfigController',
            $this->routesContent
        );
        $this->assertStringContainsString(
            'use App\Http\Controllers\WmsCallbackLogController',
            $this->routesContent
        );
    }

    public function test_warehouse_config_routes_exist(): void
    {
        $this->assertStringContainsString('warehouse-configs', $this->routesContent);
        $this->assertStringContainsString('toggle-status', $this->routesContent);
        $this->assertStringContainsString('test-connection', $this->routesContent);
        $this->assertStringContainsString('sync-inventory', $this->routesContent);
        $this->assertStringContainsString('sync-tracking', $this->routesContent);
    }

    public function test_automation_rule_routes_exist(): void
    {
        $this->assertStringContainsString('automation-rules', $this->routesContent);
        $this->assertStringContainsString('toggle-enabled', $this->routesContent);
        $this->assertStringContainsString('trigger', $this->routesContent);
    }

    public function test_wms_callback_log_routes_exist(): void
    {
        $this->assertStringContainsString('wms-callback-logs', $this->routesContent);
        $this->assertStringContainsString('statistics', $this->routesContent);
    }

    public function test_store_method_validates_required_fields(): void
    {
        $controllerFile = __DIR__ . '/../../../../app/Http/Controllers/OverseaDropshipController.php';
        $content = file_get_contents($controllerFile);

        $requiredFields = [
            "'source_channel'",
            "'receiver_name'",
            "'receiver_phone'",
            "'receiver_country'",
            "'receiver_address'",
            "'items'",
        ];
        foreach ($requiredFields as $field) {
            $this->assertStringContainsString(
                $field,
                $content,
                "Store method should validate {$field}"
            );
        }
    }

    public function test_review_method_validates_pass_field(): void
    {
        $controllerFile = __DIR__ . '/../../../../app/Http/Controllers/OverseaDropshipController.php';
        $content = file_get_contents($controllerFile);
        $this->assertStringContainsString("'pass' => ['required', 'boolean']", $content);
    }

    public function test_batch_operations_require_ids_array(): void
    {
        $controllerFile = __DIR__ . '/../../../../app/Http/Controllers/OverseaDropshipController.php';
        $content = file_get_contents($controllerFile);
        $this->assertStringContainsString("'ids' => ['required', 'array', 'min:1']", $content);
    }

    public function test_status_update_validates_enum(): void
    {
        $controllerFile = __DIR__ . '/../../../../app/Http/Controllers/OverseaDropshipController.php';
        $content = file_get_contents($controllerFile);
        $this->assertStringContainsString('Enum(DropshipOrderStatus::class)', $content);
    }

    public function test_items_validation_requires_sku_and_quantity(): void
    {
        $controllerFile = __DIR__ . '/../../../../app/Http/Controllers/OverseaDropshipController.php';
        $content = file_get_contents($controllerFile);
        $this->assertStringContainsString("'items.*.sku'", $content);
        $this->assertStringContainsString("'items.*.product_name'", $content);
        $this->assertStringContainsString("'items.*.quantity'", $content);
        $this->assertStringContainsString("'items.*.unit_price'", $content);
    }
}
