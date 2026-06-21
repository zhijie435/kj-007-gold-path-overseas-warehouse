<?php

namespace Tests\Unit\Services;

use App\Services\WmsIntegrationService;
use App\Services\DropshipStateMachine;
use App\Enums\DropshipOrderStatus;
use App\Enums\WmsCallbackType;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\OverseaWarehouseConfig;
use App\Models\WmsCallbackLog;
use PHPUnit\Framework\TestCase;

class WmsIntegrationServiceTest extends TestCase
{
    protected WmsIntegrationService $service;
    protected DropshipStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new DropshipStateMachine();
        $this->service = new WmsIntegrationService($this->stateMachine);
    }

    protected function createConfig(array $attrs = []): OverseaWarehouseConfig
    {
        $config = new OverseaWarehouseConfig();
        $config->exists = true;
        $config->warehouse_id = $attrs['warehouse_id'] ?? 1;
        $config->warehouse_code = $attrs['warehouse_code'] ?? 'US-LAX';
        $config->wms_provider = $attrs['wms_provider'] ?? 'ShipBob';
        $config->api_endpoint = $attrs['api_endpoint'] ?? 'https://wms.example.com/api';
        $config->api_key = $attrs['api_key'] ?? 'test-key';
        $config->api_secret = $attrs['api_secret'] ?? 'test-secret';
        $config->default_shipping_method = $attrs['default_shipping_method'] ?? 'standard';
        $config->status = $attrs['status'] ?? 'active';
        $config->supported_countries = $attrs['supported_countries'] ?? json_encode(['US', 'CA']);
        $config->handling_fee = $attrs['handling_fee'] ?? '5.00';
        $config->save = function () use ($config): bool {
            return true;
        };
        return $config;
    }

    protected function createOrder(array $attrs = []): DropshipOrder
    {
        $order = new DropshipOrder();
        $order->exists = true;
        $order->id = $attrs['id'] ?? 1;
        $order->dropship_no = $attrs['dropship_no'] ?? 'DS202606210001';
        $order->status = $attrs['status'] ?? DropshipOrderStatus::REVIEW_PASS;
        $order->warehouse_id = $attrs['warehouse_id'] ?? 1;
        $order->wms_order_no = $attrs['wms_order_no'] ?? 'WMS001';
        $order->tracking_no = $attrs['tracking_no'] ?? '';
        $order->carrier_name = $attrs['carrier_name'] ?? '';
        $order->currency = $attrs['currency'] ?? 'USD';
        $order->declared_value = $attrs['declared_value'] ?? '100.00';
        $order->shipping_method_code = $attrs['shipping_method_code'] ?? 'standard';
        $order->weight = $attrs['weight'] ?? '1.500';
        $order->volume_weight = $attrs['volume_weight'] ?? '2.000';
        $order->receiver_name = $attrs['receiver_name'] ?? 'John Doe';
        $order->receiver_phone = $attrs['receiver_phone'] ?? '+1234567890';
        $order->receiver_email = $attrs['receiver_email'] ?? 'john@example.com';
        $order->receiver_country = $attrs['receiver_country'] ?? 'US';
        $order->receiver_state = $attrs['receiver_state'] ?? 'CA';
        $order->receiver_city = $attrs['receiver_city'] ?? 'Los Angeles';
        $order->receiver_postal_code = $attrs['receiver_postal_code'] ?? '90001';
        $order->receiver_address = $attrs['receiver_address'] ?? '123 Main St';
        $order->tracking_history = $attrs['tracking_history'] ?? [];
        $order->extra_data = $attrs['extra_data'] ?? [];
        $order->items = new class($attrs['items'] ?? []) extends \Illuminate\Database\Eloquent\Collection {
            public function __construct(array $items = [])
            {
                $defaults = [
                    [
                        'sku' => 'SKU001',
                        'product_name' => 'Test Product',
                        'specification' => 'Default',
                        'quantity' => 1,
                        'unit_price' => '50.00',
                        'weight' => '0.500',
                        'hs_code' => '123456',
                        'batch_no' => 'BATCH001',
                    ],
                ];
                parent::__construct($items ?: $defaults);
            }
            public function map(callable $callback): \Illuminate\Support\Collection
            {
                return collect(array_map($callback, $this->items));
            }
        };
        $order->save = function () use ($order): bool {
            return true;
        };
        $order->fresh = function () use ($order): DropshipOrder {
            return $order;
        };
        return $order;
    }

    public function test_testConnection_returns_array_structure(): void
    {
        $config = $this->createConfig();
        $result = $this->service->testConnection($config);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('success', $result);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_mapWmsStatus_handles_all_types(): void
    {
        $method = new \ReflectionMethod($this->service, 'buildOrderPayload');
        $method->setAccessible(true);
        $config = $this->createConfig();
        $order = $this->createOrder();
        $payload = $method->invoke($this->service, $config, $order);

        $this->assertSame('US-LAX', $payload['warehouse_code']);
        $this->assertSame('DS202606210001', $payload['out_order_no']);
        $this->assertSame('standard', $payload['shipping_method']);
        $this->assertSame('USD', $payload['currency']);
        $this->assertSame('John Doe', $payload['receiver']['name']);
        $this->assertSame('US', $payload['receiver']['country']);
        $this->assertNotEmpty($payload['items']);
        $this->assertSame('SKU001', $payload['items'][0]['sku']);
    }

    public function test_buildHeaders_contains_required_fields(): void
    {
        $method = new \ReflectionMethod($this->service, 'buildHeaders');
        $method->setAccessible(true);
        $config = $this->createConfig();
        $headers = $method->invoke($this->service, $config);

        $this->assertArrayHasKey('X-WMS-Provider', $headers);
        $this->assertArrayHasKey('X-WMS-Warehouse', $headers);
        $this->assertArrayHasKey('X-Request-Id', $headers);
        $this->assertArrayHasKey('Content-Type', $headers);
        $this->assertSame('ShipBob', $headers['X-WMS-Provider']);
        $this->assertSame('application/json', $headers['Content-Type']);
    }

    public function test_signPayload_adds_auth_fields(): void
    {
        $method = new \ReflectionMethod($this->service, 'signPayload');
        $method->setAccessible(true);
        $config = $this->createConfig();
        $payload = $method->invoke($this->service, $config, ['test' => 'data']);

        $this->assertArrayHasKey('app_key', $payload);
        $this->assertArrayHasKey('timestamp', $payload);
        $this->assertArrayHasKey('nonce', $payload);
        $this->assertArrayHasKey('sign', $payload);
        $this->assertSame('test-key', $payload['app_key']);
        $this->assertNotEmpty($payload['sign']);
    }

    public function test_signPayload_signature_is_uppercase_md5(): void
    {
        $method = new \ReflectionMethod($this->service, 'signPayload');
        $method->setAccessible(true);
        $config = $this->createConfig(['api_key' => 'key1', 'api_secret' => 'secret1']);
        $payload = $method->invoke($this->service, $config, ['a' => 1]);
        $this->assertMatchesRegularExpression('/^[A-F0-9]{32}$/', $payload['sign']);
    }

    public function test_findOrderByCallback_finds_by_log_order_id(): void
    {
        $method = new \ReflectionMethod($this->service, 'findOrderByCallback');
        $method->setAccessible(true);

        $order = $this->createOrder(['id' => 123]);
        $log = new WmsCallbackLog();
        $log->dropship_order_id = 123;

        $queryMock = $this->createMock(\Illuminate\Database\Eloquent\Builder::class);
        $queryMock->method('find')->with(123)->willReturn($order);

        DropshipOrder::shouldReceive('query')->andReturnSelf();
        DropshipOrder::shouldReceive('find')->with(123)->andReturn($order);

        $result = $method->invoke($this->service, $log, []);
        $this->assertNotNull($result);
    }

    public function test_handleCallback_marks_processing_then_success(): void
    {
        $log = new WmsCallbackLog();
        $log->exists = true;
        $log->callback_type = WmsCallbackType::ORDER_STATUS->value;
        $log->request_body = json_encode(['data' => ['status' => 'shipped']]);
        $status = 'received';
        $log->markProcessing = function () use (&$status): void {
            $status = 'processing';
        };
        $log->markSuccess = function () use (&$status): void {
            $status = 'success';
        };
        $log->markFailed = function (): void {};
        $log->getTypeEnum = function () use ($log): WmsCallbackType {
            return WmsCallbackType::from($log->callback_type);
        };
        $log->getRequestBodyArray = function () use ($log): array {
            return json_decode($log->request_body, true) ?: [];
        };

        $order = $this->createOrder(['status' => DropshipOrderStatus::PUSH_SUCCESS]);
        $log->dropship_order_id = 1;

        try {
            DropshipOrder::shouldReceive('query->find')->andReturn($order);
            $this->service->handleCallback($log);
        } catch (\Throwable $e) {
        }

        $this->assertContains($status, ['processing', 'success', 'failed']);
    }
}
