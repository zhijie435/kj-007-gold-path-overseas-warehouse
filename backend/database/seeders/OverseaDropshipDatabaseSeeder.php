<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class OverseaDropshipDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('=== 海外仓一件代发金路径 数据种子 ===');

        $this->seedUsers();
        $this->seedWarehouses();
        $this->seedOverseaWarehouseConfigs();
        $this->seedAutomationRules();
        $this->seedSampleDropshipOrders();

        $this->command->info('=== 数据种子完成 ===');
    }

    protected function seedUsers(): void
    {
        $this->command->info('创建用户...');

        $now = Carbon::now();

        $users = [
            [
                'id' => 1,
                'name' => '超级管理员',
                'email' => 'admin@example.com',
                'password' => Hash::make('admin123456'),
                'role' => 'admin',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => '运营主管',
                'email' => 'operator@example.com',
                'password' => Hash::make('operator123'),
                'role' => 'operator',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => '仓库管理员',
                'email' => 'warehouse@example.com',
                'password' => Hash::make('warehouse123'),
                'role' => 'warehouse',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($users as $user) {
            DB::table('users')->upsert($user, ['id'], ['name', 'email', 'password', 'role', 'updated_at']);
        }

        $this->command->info('  ✓ 用户创建完成 (3条)');
    }

    protected function seedWarehouses(): void
    {
        $this->command->info('创建仓库...');

        $now = Carbon::now();

        $warehouses = [
            [
                'id' => 1,
                'name' => '美国洛杉矶仓',
                'code' => 'US-LAX',
                'country' => 'US',
                'state' => 'CA',
                'city' => 'Los Angeles',
                'address' => '123 Logistics Ave, Los Angeles, CA 90001',
                'postal_code' => '90001',
                'contact_name' => 'John Smith',
                'contact_phone' => '+1-213-555-0100',
                'status' => 'active',
                'type' => 'oversea',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => '德国法兰克福仓',
                'code' => 'DE-FRA',
                'country' => 'DE',
                'state' => 'BY',
                'city' => 'Frankfurt',
                'address' => '456 Europastraße, Frankfurt am Main, 60327',
                'postal_code' => '60327',
                'contact_name' => 'Hans Müller',
                'contact_phone' => '+49-69-555-0100',
                'status' => 'active',
                'type' => 'oversea',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => '日本东京仓',
                'code' => 'JP-TOK',
                'country' => 'JP',
                'state' => 'Tokyo',
                'city' => 'Tokyo',
                'address' => '東京都渋谷区神宮前1-2-3',
                'postal_code' => '150-0001',
                'contact_name' => '山田太郎',
                'contact_phone' => '+81-3-5555-0100',
                'status' => 'active',
                'type' => 'oversea',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($warehouses as $warehouse) {
            DB::table('warehouses')->upsert($warehouse, ['id'], array_keys($warehouse));
        }

        $this->command->info('  ✓ 仓库创建完成 (3条)');
    }

    protected function seedOverseaWarehouseConfigs(): void
    {
        $this->command->info('创建海外仓WMS配置...');

        $now = Carbon::now();

        $configs = [
            [
                'warehouse_id' => 1,
                'wms_provider' => 'shipbob',
                'api_endpoint' => 'https://api.shipbob.com/1.0',
                'api_key' => 'sandbox_shipbob_key_us_lax',
                'api_secret' => 'sandbox_shipbob_secret_us_lax',
                'warehouse_code' => 'US-LAX',
                'default_shipping_method' => 'standard',
                'handling_fee' => 5.00,
                'storage_fee_per_cbm' => 15.00,
                'sla_processing_hours' => 24,
                'auto_push_enabled' => true,
                'auto_sync_inventory' => true,
                'inventory_sync_interval_min' => 60,
                'auto_sync_tracking' => true,
                'tracking_sync_interval_min' => 120,
                'supported_countries' => json_encode(['US', 'CA', 'MX']),
                'extra_config' => json_encode([
                    'sandbox_mode' => true,
                    'max_package_weight' => 30,
                    'label_format' => 'zpl',
                ]),
                'status' => 'active',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'warehouse_id' => 2,
                'wms_provider' => 'easypost',
                'api_endpoint' => 'https://api.easypost.com/v2',
                'api_key' => 'sandbox_easypost_key_de_fra',
                'api_secret' => 'sandbox_easypost_secret_de_fra',
                'warehouse_code' => 'DE-FRA',
                'default_shipping_method' => 'dhl_express',
                'handling_fee' => 8.00,
                'storage_fee_per_cbm' => 20.00,
                'sla_processing_hours' => 48,
                'auto_push_enabled' => true,
                'auto_sync_inventory' => true,
                'inventory_sync_interval_min' => 60,
                'auto_sync_tracking' => true,
                'tracking_sync_interval_min' => 120,
                'supported_countries' => json_encode(['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PL', 'UK']),
                'extra_config' => json_encode([
                    'sandbox_mode' => true,
                    'ioss_number' => 'IM1234567890',
                    'eori_number' => 'DE123456789012345',
                ]),
                'status' => 'testing',
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'warehouse_id' => 3,
                'wms_provider' => '4px',
                'api_endpoint' => 'https://api.4px.com/openapi',
                'api_key' => 'sandbox_4px_key_jp_tok',
                'api_secret' => 'sandbox_4px_secret_jp_tok',
                'warehouse_code' => 'JP-TOK',
                'default_shipping_method' => 'yamato',
                'handling_fee' => 800.00,
                'storage_fee_per_cbm' => 5000.00,
                'sla_processing_hours' => 12,
                'auto_push_enabled' => false,
                'auto_sync_inventory' => false,
                'inventory_sync_interval_min' => 60,
                'auto_sync_tracking' => false,
                'tracking_sync_interval_min' => 120,
                'supported_countries' => json_encode(['JP']),
                'extra_config' => json_encode([
                    'sandbox_mode' => true,
                    'currency' => 'JPY',
                ]),
                'status' => 'inactive',
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($configs as $config) {
            DB::table('oversea_warehouse_configs')->upsert(
                $config,
                ['warehouse_id'],
                array_diff(array_keys($config), ['warehouse_id'])
            );
        }

        $this->command->info('  ✓ 海外仓WMS配置完成 (3条)');
    }

    protected function seedAutomationRules(): void
    {
        $this->command->info('创建自动化规则...');

        $now = Carbon::now();

        $rules = [
            [
                'id' => 1,
                'name' => '美国订单自动分仓到洛杉矶仓',
                'code' => 'AUTO_ASSIGN_US_LAX',
                'type' => 'auto_assign_warehouse',
                'description' => '收货国家为美国/加拿大/墨西哥的订单自动分配到洛杉矶仓',
                'priority' => 100,
                'conditions' => json_encode([
                    'logic' => 'OR',
                    'rules' => [
                        ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'US'],
                        ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'CA'],
                        ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'MX'],
                    ],
                ]),
                'actions' => json_encode([
                    'warehouse_id' => 1,
                    'shipping_method_code' => 'standard',
                ]),
                'warehouse_id' => 1,
                'country_code' => 'US',
                'is_enabled' => true,
                'stop_chain' => true,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'name' => '欧盟订单自动分仓到法兰克福仓',
                'code' => 'AUTO_ASSIGN_EU_FRA',
                'type' => 'auto_assign_warehouse',
                'description' => '收货国家为欧盟国家的订单自动分配到法兰克福仓',
                'priority' => 90,
                'conditions' => json_encode([
                    'logic' => 'IN',
                    'rules' => [
                        ['field' => 'receiver_country', 'operator' => 'in', 'value' => ['DE', 'FR', 'IT', 'ES', 'NL', 'BE', 'AT', 'PL']],
                    ],
                ]),
                'actions' => json_encode([
                    'warehouse_id' => 2,
                    'shipping_method_code' => 'dhl_express',
                ]),
                'warehouse_id' => 2,
                'country_code' => 'DE',
                'is_enabled' => true,
                'stop_chain' => true,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'name' => '低价值订单自动审核通过',
                'code' => 'AUTO_REVIEW_LOW_VALUE',
                'type' => 'auto_review',
                'description' => '订单总金额小于等于500美元的订单自动审核通过',
                'priority' => 80,
                'conditions' => json_encode([
                    'logic' => 'AND',
                    'rules' => [
                        ['field' => 'total_cost', 'operator' => 'lte', 'value' => 500],
                        ['field' => 'source_channel', 'operator' => 'not_in', 'value' => ['manual']],
                    ],
                ]),
                'actions' => json_encode([
                    'auto_approve' => true,
                    'remark' => '低价值订单自动审核通过',
                ]),
                'is_enabled' => true,
                'stop_chain' => false,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'name' => '审核通过自动推单到WMS',
                'code' => 'AUTO_PUSH_WMS_AFTER_REVIEW',
                'type' => 'auto_push_wms',
                'description' => '审核通过的订单自动推送到WMS系统',
                'priority' => 70,
                'conditions' => json_encode([
                    'logic' => 'AND',
                    'rules' => [
                        ['field' => 'warehouse_id', 'operator' => 'notnull', 'value' => true],
                        ['field' => 'total_items', 'operator' => 'gt', 'value' => 0],
                    ],
                ]),
                'actions' => json_encode([
                    'retry_on_failure' => true,
                    'max_attempts' => 5,
                ]),
                'is_enabled' => true,
                'stop_chain' => false,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'name' => '异常订单自动通知运营',
                'code' => 'AUTO_NOTIFY_EXCEPTION',
                'type' => 'auto_notification',
                'description' => '订单状态变为异常时自动发送通知',
                'priority' => 10,
                'conditions' => json_encode([
                    'logic' => 'AND',
                    'rules' => [
                        ['field' => 'status', 'operator' => 'eq', 'value' => 'exception'],
                    ],
                ]),
                'actions' => json_encode([
                    'email' => ['operator@example.com'],
                    'sms' => [],
                    'webhook' => 'https://hooks.example.com/dropship-exception',
                    'template' => 'exception_alert',
                ]),
                'is_enabled' => true,
                'stop_chain' => false,
                'created_by' => 1,
                'updated_by' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        ];

        foreach ($rules as $rule) {
            DB::table('automation_rules')->upsert($rule, ['id'], array_diff(array_keys($rule), ['id']));
        }

        $this->command->info('  ✓ 自动化规则创建完成 (5条)');
    }

    protected function seedSampleDropshipOrders(): void
    {
        $this->command->info('创建示例代发订单...');

        $now = Carbon::now();

        $orders = [
            [
                'id' => 1,
                'dropship_no' => 'DS2026062100001',
                'order_id' => null,
                'external_order_no' => 'SHOPIFY-1001',
                'warehouse_id' => 1,
                'created_by' => 1,
                'source_channel' => 'shopify',
                'fulfillment_type' => 'auto',
                'wms_order_no' => 'WMS-LAX-0001',
                'shipping_method_code' => 'standard',
                'tracking_no' => '1Z999AA10123456784',
                'carrier_name' => 'UPS',
                'receiver_name' => 'Alice Johnson',
                'receiver_phone' => '+1-213-555-0101',
                'receiver_email' => 'alice@example.com',
                'receiver_country' => 'US',
                'receiver_state' => 'CA',
                'receiver_city' => 'Los Angeles',
                'receiver_postal_code' => '90001',
                'receiver_address' => '100 Main St, Apt 5B',
                'total_items' => 2,
                'subtotal' => 89.99,
                'shipping_fee' => 12.00,
                'handling_fee' => 5.00,
                'insurance_fee' => 2.00,
                'duty_fee' => 0.00,
                'total_cost' => 108.99,
                'declared_value' => 89.99,
                'currency' => 'USD',
                'weight' => 1.500,
                'volume_weight' => 2.000,
                'status' => 'shipped',
                'reviewed_at' => $now->subHours(5),
                'reviewed_by' => 1,
                'review_remark' => '自动审核通过',
                'pushed_at' => $now->subHours(4),
                'shipped_at' => $now->subHours(2),
                'push_attempts' => 1,
                'tracking_history' => json_encode([
                    ['time' => $now->subHours(2)->toDateTimeString(), 'status' => 'shipped', 'location' => 'Los Angeles, CA', 'description' => 'Package shipped from warehouse'],
                    ['time' => $now->subHours(1)->toDateTimeString(), 'status' => 'in_transit', 'location' => 'Ontario, CA', 'description' => 'Departed origin facility'],
                ]),
                'customs_info' => json_encode([
                    'hs_codes' => ['84713000', '85171210'],
                    'declared_items' => 2,
                ]),
                'extra_data' => json_encode([
                    'shopify_order_id' => 'gid://shopify/Order/1001',
                ]),
                'remark' => 'Shopify平台示例订单',
                'created_at' => $now->subHours(6),
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'dropship_no' => 'DS2026062100002',
                'order_id' => null,
                'external_order_no' => null,
                'warehouse_id' => 1,
                'created_by' => 2,
                'source_channel' => 'manual',
                'fulfillment_type' => 'manual',
                'wms_order_no' => null,
                'receiver_name' => 'Bob Smith',
                'receiver_phone' => '+1-415-555-0102',
                'receiver_email' => 'bob@example.com',
                'receiver_country' => 'US',
                'receiver_state' => 'CA',
                'receiver_city' => 'San Francisco',
                'receiver_postal_code' => '94105',
                'receiver_address' => '200 Market St',
                'total_items' => 1,
                'subtotal' => 150.00,
                'shipping_fee' => 8.00,
                'handling_fee' => 5.00,
                'insurance_fee' => 0.00,
                'duty_fee' => 0.00,
                'total_cost' => 163.00,
                'declared_value' => 150.00,
                'currency' => 'USD',
                'weight' => 0.800,
                'volume_weight' => 1.000,
                'status' => 'pending_review',
                'remark' => '手动创建订单，等待审核',
                'created_at' => $now->subHours(1),
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'dropship_no' => 'DS2026062100003',
                'order_id' => null,
                'external_order_no' => 'TIKTOK-5001',
                'warehouse_id' => 2,
                'created_by' => 1,
                'source_channel' => 'tiktok',
                'fulfillment_type' => 'auto',
                'wms_order_no' => null,
                'receiver_name' => 'Marie Dupont',
                'receiver_phone' => '+33-1-55-55-01-01',
                'receiver_email' => 'marie@example.fr',
                'receiver_country' => 'FR',
                'receiver_state' => 'IDF',
                'receiver_city' => 'Paris',
                'receiver_postal_code' => '75001',
                'receiver_address' => '10 Rue de Rivoli',
                'total_items' => 3,
                'subtotal' => 250.00,
                'shipping_fee' => 25.00,
                'handling_fee' => 8.00,
                'insurance_fee' => 5.00,
                'duty_fee' => 45.00,
                'total_cost' => 333.00,
                'declared_value' => 250.00,
                'currency' => 'EUR',
                'weight' => 2.500,
                'volume_weight' => 3.500,
                'status' => 'push_failed',
                'reviewed_at' => $now->subHours(3),
                'reviewed_by' => 1,
                'review_remark' => '自动审核通过',
                'push_attempts' => 3,
                'push_error' => 'WMS API timeout after 30s (尝试 3 次)',
                'extra_data' => json_encode([
                    'job_failure' => [
                        'time' => $now->subHour()->toDateTimeString(),
                        'message' => 'WMS API timeout after 30s',
                        'attempts' => 3,
                    ],
                ]),
                'remark' => 'TikTok平台订单，推送WMS失败',
                'created_at' => $now->subHours(5),
                'updated_at' => $now,
            ],
        ];

        foreach ($orders as $order) {
            DB::table('dropship_orders')->upsert($order, ['id'], array_diff(array_keys($order), ['id']));
        }

        $orderItems = [
            [
                'id' => 1,
                'dropship_order_id' => 1,
                'sku' => 'SKU-LAX-001',
                'product_name' => 'Wireless Headphones',
                'specification' => 'Black / Over-Ear',
                'quantity' => 1,
                'unit_price' => 59.99,
                'subtotal' => 59.99,
                'weight' => 0.500,
                'hs_code' => '85183000',
                'batch_no' => 'BATCH-LAX-202606',
                'created_at' => $now->subHours(6),
                'updated_at' => $now,
            ],
            [
                'id' => 2,
                'dropship_order_id' => 1,
                'sku' => 'SKU-LAX-002',
                'product_name' => 'Phone Case',
                'specification' => 'Clear / iPhone 15',
                'quantity' => 1,
                'unit_price' => 30.00,
                'subtotal' => 30.00,
                'weight' => 0.100,
                'hs_code' => '39269099',
                'batch_no' => 'BATCH-LAX-202606',
                'created_at' => $now->subHours(6),
                'updated_at' => $now,
            ],
            [
                'id' => 3,
                'dropship_order_id' => 2,
                'sku' => 'SKU-LAX-003',
                'product_name' => 'Smart Watch',
                'specification' => 'Silver / 42mm',
                'quantity' => 1,
                'unit_price' => 150.00,
                'subtotal' => 150.00,
                'weight' => 0.200,
                'hs_code' => '85171210',
                'batch_no' => 'BATCH-LAX-202606',
                'created_at' => $now->subHours(1),
                'updated_at' => $now,
            ],
            [
                'id' => 4,
                'dropship_order_id' => 3,
                'sku' => 'SKU-FRA-001',
                'product_name' => 'Bluetooth Speaker',
                'specification' => 'Portable',
                'quantity' => 2,
                'unit_price' => 80.00,
                'subtotal' => 160.00,
                'weight' => 1.000,
                'hs_code' => '85182000',
                'batch_no' => 'BATCH-FRA-202606',
                'created_at' => $now->subHours(5),
                'updated_at' => $now,
            ],
            [
                'id' => 5,
                'dropship_order_id' => 3,
                'sku' => 'SKU-FRA-002',
                'product_name' => 'USB-C Cable',
                'specification' => '2m / Braided',
                'quantity' => 1,
                'unit_price' => 30.00,
                'subtotal' => 30.00,
                'weight' => 0.100,
                'hs_code' => '85444210',
                'batch_no' => 'BATCH-FRA-202606',
                'created_at' => $now->subHours(5),
                'updated_at' => $now,
            ],
        ];

        foreach ($orderItems as $item) {
            DB::table('dropship_order_items')->upsert($item, ['id'], array_diff(array_keys($item), ['id']));
        }

        $this->command->info('  ✓ 示例代发订单创建完成 (3个订单, 5个商品项)');
    }
}
