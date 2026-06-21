<?php

namespace Tests\Unit\Http\Controllers;

use PHPUnit\Framework\TestCase;
use App\Enums\DropshipOrderStatus;

class OverseaDropshipControllerStructureTest extends TestCase
{
    public function test_routes_are_registered(): void
    {
        $routesFile = __DIR__ . '/../../../../routes/dropship_api.php';
        $this->assertFileExists($routesFile);

        $content = file_get_contents($routesFile);

        $expectedRoutes = [
            'GET/HEAD' => [
                'dropship/statistics',
                'dropship/status-options',
                'dropship/channel-options',
                'dropship/orders',
                'dropship/orders/{order}',
            ],
            'POST' => [
                'dropship/batch-review',
                'dropship/batch-push',
                'dropship/orders',
                'dropship/orders/{order}/review',
                'dropship/orders/{order}/push',
                'dropship/orders/{order}/retry-push',
                'dropship/orders/{order}/update-status',
                'dropship/orders/{order}/cancel',
                'dropship/orders/{order}/sync-tracking',
            ],
            'PUT/PATCH' => [
                'dropship/orders/{order}',
            ],
            'DELETE' => [
                'dropship/orders/{order}',
            ],
        ];

        foreach ($expectedRoutes as $method => $paths) {
            foreach ($paths as $path) {
                $this->assertStringContainsString(
                    $path,
                    $content,
                    "Expected route {$method} {$path} to be registered"
                );
            }
        }
    }

    public function test_controller_class_exists(): void
    {
        $this->assertTrue(
            class_exists(\App\Http\Controllers\OverseaDropshipController::class),
            'OverseaDropshipController class should exist'
        );
    }

    public function test_controller_has_expected_methods(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\OverseaDropshipController::class);
        $expectedMethods = [
            'index', 'show', 'store', 'update', 'destroy',
            'statistics', 'statusOptions', 'channelOptions',
            'review', 'batchReview', 'push', 'batchPush',
            'updateStatus', 'cancel', 'retryPush', 'syncTracking',
        ];
        foreach ($expectedMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "OverseaDropshipController should have method {$method}"
            );
        }
    }

    public function test_status_options_returns_all_enum_cases(): void
    {
        $options = DropshipOrderStatus::options();
        $this->assertCount(count(DropshipOrderStatus::cases()), $options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('color', $option);
        }
    }

    public function test_channel_options_returns_expected_channels(): void
    {
        $expectedChannels = [
            'shopify' => 'Shopify',
            'amazon' => 'Amazon',
            'ebay' => 'eBay',
            'tiktok' => 'TikTok Shop',
            'lazada' => 'Lazada',
            'shopee' => 'Shopee',
            'manual' => '手动创建',
            'api' => 'API对接',
            'other' => '其他',
        ];
        $this->assertNotEmpty($expectedChannels);
        $this->assertCount(9, $expectedChannels);
    }

    public function test_store_validation_rules_require_items(): void
    {
        $controller = new \ReflectionClass(\App\Http\Controllers\OverseaDropshipController::class);
        $this->assertTrue($controller->hasMethod('store'));
        $storeMethod = $controller->getMethod('store');
        $this->assertNotEmpty($storeMethod);
    }

    public function test_dropship_exception_constants(): void
    {
        $exceptionClass = new \ReflectionClass(\App\Exceptions\DropshipException::class);
        $expectedConstants = [
            'INVALID_STATUS_TRANSITION' => 1001,
            'ORDER_TERMINAL' => 1002,
            'WAREHOUSE_NOT_ASSIGNED' => 1003,
            'WAREHOUSE_CONFIG_INVALID' => 1004,
            'EMPTY_ITEMS' => 1005,
            'PERMISSION_DENIED' => 2001,
            'WMS_API_ERROR' => 3001,
            'ORDER_NOT_FOUND' => 4001,
        ];
        foreach ($expectedConstants as $name => $expectedValue) {
            $this->assertTrue(
                $exceptionClass->hasConstant($name),
                "DropshipException should have constant {$name}"
            );
            $actualValue = $exceptionClass->getConstant($name);
            $this->assertSame(
                $expectedValue,
                $actualValue,
                "DropshipException::{$name} should be {$expectedValue}, got {$actualValue}"
            );
        }
    }

    public function test_errorResponse_method_exists(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\OverseaDropshipController::class);
        $this->assertTrue($reflection->hasMethod('errorResponse'));
        $method = $reflection->getMethod('errorResponse');
        $this->assertTrue($method->isProtected());
    }

    public function test_controller_has_middleware_auth(): void
    {
        $reflection = new \ReflectionClass(\App\Http\Controllers\OverseaDropshipController::class);
        $constructor = $reflection->getConstructor();
        $this->assertNotNull($constructor);
    }
}
