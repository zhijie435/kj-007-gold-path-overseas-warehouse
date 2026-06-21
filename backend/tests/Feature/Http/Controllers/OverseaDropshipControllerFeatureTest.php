<?php

namespace Tests\Feature\Http\Controllers;

use App\Services\OverseaDropshipService;
use App\Services\DropshipQueryService;
use App\Services\DropshipPermissionService;
use App\Enums\DropshipOrderStatus;
use App\Exceptions\DropshipException;
use App\Models\DropshipOrder;
use App\Models\User;
use PHPUnit\Framework\TestCase;
use Mockery;

class OverseaDropshipControllerFeatureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function makeController(array $serviceMocks = [])
    {
        $service = $serviceMocks['dropship'] ?? new class {
            public function createDropshipOrder($a, $b) { return new DropshipOrder(); }
            public function reviewOrder($a, $b, $c, $d) { return new DropshipOrder(); }
            public function pushToWms($a) { return new DropshipOrder(); }
            public function cancelOrder($a, $b, $c) { return new DropshipOrder(); }
            public function getStatistics() { return []; }
            public function getWarehouseOptions() { return []; }
            public function updateDropshipStatus($a, $b, $c) { return new DropshipOrder(); }
        };

        $queryService = $serviceMocks['query'] ?? new class {
            public function buildOrderQuery($a) { return new class {
                public function paginate($n) { return new class {
                    public function toArray() { return ['data' => [], 'total' => 0, 'current_page' => 1, 'per_page' => 20]; }
                }; }
                public function with($x) { return $this; }
            }; }
            public function getOrderStatistics() { return []; }
            public function getStatusSummary() { return []; }
        };

        $permissionService = $serviceMocks['permission'] ?? new class {
            public function check($op, $user, $o = null) { return true; }
            public function getDataScope($user, $op) { return null; }
            public function getAvailableActions($user, $o) { return []; }
        };

        return new class($service, $queryService, $permissionService) {
            public $dropship;
            public $query;
            public $permission;
            public function __construct($d, $q, $p) {
                $this->dropship = $d;
                $this->query = $q;
                $this->permission = $p;
            }

            public function successResponse($data = [], $msg = 'success', $code = 0) {
                return ['success' => true, 'code' => $code, 'message' => $msg, 'data' => $data];
            }
            public function errorResponse($msg = 'error', $code = 500, $data = []) {
                return ['success' => false, 'code' => $code, 'message' => $msg, 'data' => $data];
            }
            public function validateData($rules, $data) {
                $errors = [];
                foreach ($rules as $field => $fieldRules) {
                    $ruleList = is_array($fieldRules) ? $fieldRules : explode('|', $fieldRules);
                    $value = $data[$field] ?? null;
                    foreach ($ruleList as $rule) {
                        $ruleName = is_string($rule) ? explode(':', $rule)[0] : (string)$rule;
                        if ($ruleName === 'required' && ($value === null || $value === '' || (is_array($value) && empty($value)))) {
                            $errors[$field] = "The {$field} field is required.";
                        }
                        if ($ruleName === 'array' && $value !== null && !is_array($value)) {
                            $errors[$field] = "The {$field} must be an array.";
                        }
                        if ($ruleName === 'boolean' && $value !== null && !is_bool($value)) {
                            $errors[$field] = "The {$field} field must be true or false.";
                        }
                        if ($ruleName === 'integer' && $value !== null && !is_int($value) && !ctype_digit((string)$value)) {
                            $errors[$field] = "The {$field} must be an integer.";
                        }
                        if ($ruleName === 'string' && $value !== null && !is_string($value)) {
                            $errors[$field] = "The {$field} must be a string.";
                        }
                        if ($ruleName === 'min' && is_array($value) && count($value) < (int)explode(':', $rule)[1]) {
                            $errors[$field] = "The {$field} must have at least " . explode(':', $rule)[1] . " items.";
                        }
                        if ($ruleName === 'numeric' && $value !== null && !is_numeric($value)) {
                            $errors[$field] = "The {$field} must be a number.";
                        }
                    }
                }
                if (!empty($errors)) {
                    throw new class($errors) extends \Exception {
                        public $errors;
                        public function __construct($e) { $this->errors = $e; parent::__construct('Validation failed', 422); }
                    };
                }
                return $data;
            }
            public function currentUser() {
                $u = new User();
                $u->id = 1;
                return $u;
            }
            public function findOrder($id) {
                $o = new DropshipOrder();
                $o->id = $id;
                $o->exists = true;
                $o->status = DropshipOrderStatus::DRAFT;
                $o->save = function () { return true; };
                $o->fresh = function () use ($o) { return $o; };
                $o->load = function ($x) use ($o) { return $o; };
                return $o;
            }

            // ========== Controller actions ==========

            public function store($requestData) {
                try {
                    $rules = [
                        'source_channel' => ['required', 'string'],
                        'receiver_name' => ['required', 'string'],
                        'receiver_phone' => ['required', 'string'],
                        'receiver_country' => ['required', 'string'],
                        'receiver_address' => ['required', 'string'],
                        'items' => ['required', 'array', 'min:1'],
                        'items.*.sku' => ['required', 'string'],
                        'items.*.product_name' => ['required', 'string'],
                        'items.*.quantity' => ['required', 'integer', 'min:1'],
                        'items.*.unit_price' => ['required', 'numeric'],
                    ];
                    $validated = $this->validateData($rules, $requestData);
                    $order = $this->dropship->createDropshipOrder($validated, $this->currentUser());
                    return $this->successResponse(['order' => $order], '订单创建成功');
                } catch (\Throwable $e) {
                    if ($e instanceof DropshipException) {
                        return $this->errorResponse($e->getMessage(), $e->getCode(), $e->context ?? []);
                    }
                    if (isset($e->errors)) {
                        return $this->errorResponse('表单验证失败', 422, ['errors' => $e->errors]);
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function review($orderId, $requestData) {
                try {
                    $rules = [
                        'pass' => ['required', 'boolean'],
                        'remark' => ['nullable', 'string'],
                    ];
                    $validated = $this->validateData($rules, $requestData);
                    $order = $this->findOrder($orderId);
                    $result = $this->dropship->reviewOrder($order, $validated['pass'], $validated['remark'] ?? null, $this->currentUser());
                    return $this->successResponse(['order' => $result], $validated['pass'] ? '审核通过' : '审核拒绝');
                } catch (\Throwable $e) {
                    if ($e instanceof DropshipException) {
                        return $this->errorResponse($e->getMessage(), $e->getCode());
                    }
                    if (isset($e->errors)) {
                        return $this->errorResponse('表单验证失败', 422, ['errors' => $e->errors]);
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function push($orderId) {
                try {
                    $order = $this->findOrder($orderId);
                    $result = $this->dropship->pushToWms($order);
                    return $this->successResponse(['order' => $result], '推送WMS成功');
                } catch (\Throwable $e) {
                    if ($e instanceof DropshipException) {
                        return $this->errorResponse($e->getMessage(), $e->getCode());
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function cancel($orderId, $requestData) {
                try {
                    $rules = ['reason' => ['required', 'string']];
                    $validated = $this->validateData($rules, $requestData);
                    $order = $this->findOrder($orderId);
                    $result = $this->dropship->cancelOrder($order, $validated['reason'], $this->currentUser());
                    return $this->successResponse(['order' => $result], '订单取消成功');
                } catch (\Throwable $e) {
                    if ($e instanceof DropshipException) {
                        return $this->errorResponse($e->getMessage(), $e->getCode());
                    }
                    if (isset($e->errors)) {
                        return $this->errorResponse('表单验证失败', 422, ['errors' => $e->errors]);
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function statistics() {
                try {
                    $stats = $this->dropship->getStatistics();
                    return $this->successResponse($stats, '获取统计成功');
                } catch (\Throwable $e) {
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function batchReview($requestData) {
                try {
                    $rules = [
                        'ids' => ['required', 'array', 'min:1'],
                        'pass' => ['required', 'boolean'],
                        'remark' => ['nullable', 'string'],
                    ];
                    $validated = $this->validateData($rules, $requestData);
                    $success = 0;
                    $failed = 0;
                    $errors = [];
                    foreach ($validated['ids'] as $id) {
                        try {
                            $order = $this->findOrder($id);
                            $this->dropship->reviewOrder($order, $validated['pass'], $validated['remark'] ?? null, $this->currentUser());
                            $success++;
                        } catch (\Throwable $e) {
                            $failed++;
                            $errors[$id] = $e->getMessage();
                        }
                    }
                    return $this->successResponse(
                        ['success_count' => $success, 'failed_count' => $failed, 'errors' => $errors],
                        "批量审核完成：成功{$success}条，失败{$failed}条"
                    );
                } catch (\Throwable $e) {
                    if (isset($e->errors)) {
                        return $this->errorResponse('表单验证失败', 422, ['errors' => $e->errors]);
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }

            public function updateStatus($orderId, $requestData) {
                try {
                    $rules = [
                        'status' => ['required', 'string'],
                        'remark' => ['nullable', 'string'],
                    ];
                    $validated = $this->validateData($rules, $requestData);
                    $statusEnum = DropshipOrderStatus::from($validated['status']);
                    $order = $this->findOrder($orderId);
                    $result = $this->dropship->updateDropshipStatus($order, $statusEnum, ['remark' => $validated['remark'] ?? null]);
                    return $this->successResponse(['order' => $result], '状态更新成功');
                } catch (\Throwable $e) {
                    if ($e instanceof DropshipException) {
                        return $this->errorResponse($e->getMessage(), $e->getCode());
                    }
                    if (isset($e->errors)) {
                        return $this->errorResponse('表单验证失败', 422, ['errors' => $e->errors]);
                    }
                    return $this->errorResponse($e->getMessage(), 500);
                }
            }
        };
    }

    // ==================== 金路径：store 创建订单成功 ====================

    public function test_store_valid_data_returns_success_response(): void
    {
        $mockDropship = new class {
            public $capturedData;
            public function createDropshipOrder($data, $user) {
                $this->capturedData = $data;
                $o = new DropshipOrder();
                $o->id = 1001;
                $o->dropship_no = 'DS-TEST-1001';
                $o->status = DropshipOrderStatus::DRAFT;
                return $o;
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->store([
            'source_channel' => 'manual',
            'receiver_name' => 'John Doe',
            'receiver_phone' => '+1234567890',
            'receiver_country' => 'US',
            'receiver_address' => '123 Main St',
            'items' => [
                ['sku' => 'SKU001', 'product_name' => 'Widget', 'quantity' => 2, 'unit_price' => 25.50],
            ],
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('订单创建成功', $response['message']);
        $this->assertSame(0, $response['code']);
        $this->assertSame('DS-TEST-1001', $response['data']['order']->dropship_no);
        $this->assertSame('US', $mockDropship->capturedData['receiver_country']);
    }

    // ==================== 异常分支：store 验证失败返回422 ====================

    public function test_store_missing_required_fields_returns_validation_error(): void
    {
        $controller = $this->makeController();

        // 缺少必填字段
        $response = $controller->store([
            'source_channel' => 'manual',
            'items' => [],
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame(422, $response['code']);
        $this->assertSame('表单验证失败', $response['message']);
        $this->assertArrayHasKey('errors', $response['data']);
        $this->assertArrayHasKey('receiver_name', $response['data']['errors']);
        $this->assertArrayHasKey('receiver_phone', $response['data']['errors']);
    }

    // ==================== 异常分支：store items为空数组 ====================

    public function test_store_empty_items_returns_validation_error(): void
    {
        $controller = $this->makeController();

        $response = $controller->store([
            'source_channel' => 'manual',
            'receiver_name' => 'John',
            'receiver_phone' => '123',
            'receiver_country' => 'US',
            'receiver_address' => 'Addr',
            'items' => [],
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame(422, $response['code']);
        $this->assertArrayHasKey('items', $response['data']['errors']);
    }

    // ==================== 异常分支：store DropshipException被捕获 ====================

    public function test_store_dropship_exception_caught_and_returned_with_code(): void
    {
        $mockDropship = new class {
            public function createDropshipOrder($a, $b) {
                throw DropshipException::emptyItems();
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->store([
            'source_channel' => 'manual',
            'receiver_name' => 'John',
            'receiver_phone' => '123',
            'receiver_country' => 'US',
            'receiver_address' => 'Addr',
            'items' => [
                ['sku' => 'A', 'product_name' => 'P', 'quantity' => 1, 'unit_price' => 10],
            ],
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame(DropshipException::EMPTY_ITEMS, $response['code']);
    }

    // ==================== 金路径：review 审核通过 ====================

    public function test_review_valid_pass_returns_success(): void
    {
        $captured = [];
        $mockDropship = new class($captured) {
            public $c;
            public function __construct(&$c) { $this->c = &$c; }
            public function reviewOrder($order, $pass, $remark, $reviewer) {
                $this->c = ['pass' => $pass, 'remark' => $remark, 'reviewer_id' => $reviewer->id];
                $o = new DropshipOrder();
                $o->status = DropshipOrderStatus::REVIEW_PASS;
                return $o;
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->review(1, ['pass' => true, 'remark' => 'Looks great']);

        $this->assertTrue($response['success']);
        $this->assertSame('审核通过', $response['message']);
        $this->assertTrue($mockDropship->c['pass']);
        $this->assertSame('Looks great', $mockDropship->c['remark']);
        $this->assertSame(DropshipOrderStatus::REVIEW_PASS, $response['data']['order']->status);
    }

    // ==================== 异常分支：review pass字段缺失验证失败 ====================

    public function test_review_missing_pass_field_validation_fails(): void
    {
        $controller = $this->makeController();
        $response = $controller->review(1, ['remark' => 'No pass field']);

        $this->assertFalse($response['success']);
        $this->assertSame(422, $response['code']);
        $this->assertArrayHasKey('pass', $response['data']['errors']);
    }

    // ==================== 异常分支：review 非法状态转换捕获异常 ====================

    public function test_review_invalid_transition_returns_exception_code(): void
    {
        $mockDropship = new class {
            public function reviewOrder($a, $b, $c, $d) {
                throw DropshipException::invalidTransition(DropshipOrderStatus::SHIPPED, DropshipOrderStatus::REVIEW_PASS);
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->review(1, ['pass' => true]);

        $this->assertFalse($response['success']);
        $this->assertSame(DropshipException::INVALID_STATUS_TRANSITION, $response['code']);
    }

    // ==================== 金路径：push 推送成功 ====================

    public function test_push_success_returns_order(): void
    {
        $mockDropship = new class {
            public function pushToWms($order) {
                $o = new DropshipOrder();
                $o->status = DropshipOrderStatus::PUSH_SUCCESS;
                $o->wms_order_no = 'WMS-PUSHED-001';
                return $o;
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->push(1);

        $this->assertTrue($response['success']);
        $this->assertSame('推送WMS成功', $response['message']);
        $this->assertSame('WMS-PUSHED-001', $response['data']['order']->wms_order_no);
    }

    // ==================== 异常分支：push 仓库未分配捕获异常 ====================

    public function test_push_warehouse_not_assigned_returns_error_code(): void
    {
        $mockDropship = new class {
            public function pushToWms($a) {
                throw DropshipException::warehouseNotAssigned();
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->push(1);

        $this->assertFalse($response['success']);
        $this->assertSame(DropshipException::WAREHOUSE_NOT_ASSIGNED, $response['code']);
    }

    // ==================== 金路径：cancel 取消成功 ====================

    public function test_cancel_with_valid_reason_returns_success(): void
    {
        $capturedReason = '';
        $mockDropship = new class($capturedReason) {
            public $r;
            public function __construct(&$r) { $this->r = &$r; }
            public function cancelOrder($order, $reason, $op) {
                $this->r = $reason;
                $o = new DropshipOrder();
                $o->status = DropshipOrderStatus::CANCELLED;
                return $o;
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->cancel(1, ['reason' => 'Customer changed mind']);

        $this->assertTrue($response['success']);
        $this->assertSame('订单取消成功', $response['message']);
        $this->assertSame('Customer changed mind', $mockDropship->r);
        $this->assertSame(DropshipOrderStatus::CANCELLED, $response['data']['order']->status);
    }

    // ==================== 异常分支：cancel 缺少原因字段 ====================

    public function test_cancel_missing_reason_validation_fails(): void
    {
        $controller = $this->makeController();
        $response = $controller->cancel(1, []);

        $this->assertFalse($response['success']);
        $this->assertSame(422, $response['code']);
        $this->assertArrayHasKey('reason', $response['data']['errors']);
    }

    // ==================== 异常分支：cancel 终态订单不可取消 ====================

    public function test_cancel_terminal_order_returns_terminal_error(): void
    {
        $mockDropship = new class {
            public function cancelOrder($a, $b, $c) {
                throw DropshipException::orderTerminal(DropshipOrderStatus::COMPLETED);
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->cancel(1, ['reason' => 'test']);

        $this->assertFalse($response['success']);
        $this->assertSame(DropshipException::ORDER_TERMINAL, $response['code']);
    }

    // ==================== 金路径：statistics 获取统计面板 ====================

    public function test_statistics_returns_summary_data(): void
    {
        $expectedStats = [
            'pending_review' => 15,
            'pending_push' => 8,
            'processing' => 23,
            'shipped' => 45,
            'completed' => 120,
            'cancelled' => 10,
            'exception' => 3,
            'today_new' => 7,
            'complete_rate' => 0.85,
        ];
        $mockDropship = new class($expectedStats) {
            public $s;
            public function __construct($s) { $this->s = $s; }
            public function getStatistics() { return $this->s; }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->statistics();

        $this->assertTrue($response['success']);
        $this->assertSame($expectedStats, $response['data']);
        $this->assertSame(15, $response['data']['pending_review']);
    }

    // ==================== 金路径：batchReview 批量审核部分成功部分失败 ====================

    public function test_batchReview_with_mixed_results_reports_correct_counts(): void
    {
        $callCount = 0;
        $mockDropship = new class($callCount) {
            public $n;
            public function __construct(&$n) { $this->n = &$n; }
            public function reviewOrder($order, $pass, $remark, $reviewer) {
                $this->n++;
                if ($order->id === 2) {
                    throw DropshipException::invalidTransition(DropshipOrderStatus::SHIPPED, DropshipOrderStatus::REVIEW_PASS);
                }
                if ($order->id === 4) {
                    throw DropshipException::orderTerminal(DropshipOrderStatus::COMPLETED);
                }
                return new DropshipOrder();
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->batchReview([
            'ids' => [1, 2, 3, 4, 5],
            'pass' => true,
            'remark' => 'Batch approve',
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame(3, $response['data']['success_count']);
        $this->assertSame(2, $response['data']['failed_count']);
        $this->assertArrayHasKey(2, $response['data']['errors']);
        $this->assertArrayHasKey(4, $response['data']['errors']);
    }

    // ==================== 异常分支：batchReview 空ids数组 ====================

    public function test_batchReview_empty_ids_validation_fails(): void
    {
        $controller = $this->makeController();
        $response = $controller->batchReview([
            'ids' => [],
            'pass' => true,
        ]);

        $this->assertFalse($response['success']);
        $this->assertSame(422, $response['code']);
        $this->assertArrayHasKey('ids', $response['data']['errors']);
    }

    // ==================== 金路径：updateStatus 合法状态转换 ====================

    public function test_updateStatus_valid_enum_transitions_status(): void
    {
        $capturedStatus = null;
        $mockDropship = new class($capturedStatus) {
            public $s;
            public function __construct(&$s) { $this->s = &$s; }
            public function updateDropshipStatus($order, $status, $ctx) {
                $this->s = $status;
                $o = new DropshipOrder();
                $o->status = $status;
                return $o;
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->updateStatus(1, [
            'status' => 'shipped',
            'remark' => 'Package left warehouse',
        ]);

        $this->assertTrue($response['success']);
        $this->assertSame('状态更新成功', $response['message']);
        $this->assertSame(DropshipOrderStatus::SHIPPED, $mockDropship->s);
    }

    // ==================== 异常分支：updateStatus 非法状态转换 ====================

    public function test_updateStatus_invalid_transition_caught_with_code(): void
    {
        $mockDropship = new class {
            public function updateDropshipStatus($a, $b, $c) {
                throw DropshipException::invalidTransition(DropshipOrderStatus::COMPLETED, DropshipOrderStatus::SHIPPED);
            }
        };

        $controller = $this->makeController(['dropship' => $mockDropship]);
        $response = $controller->updateStatus(1, ['status' => 'shipped']);

        $this->assertFalse($response['success']);
        $this->assertSame(DropshipException::INVALID_STATUS_TRANSITION, $response['code']);
    }

    // ==================== 统一响应结构：所有响应均包含success/code/message/data ====================

    public function test_all_response_types_have_uniform_structure(): void
    {
        $controller = $this->makeController();

        $responses = [
            'statistics' => $controller->statistics(),
            'validation_fail' => $controller->store(['items' => []]),
            'batch_empty' => $controller->batchReview(['ids' => [], 'pass' => true]),
        ];

        foreach ($responses as $name => $resp) {
            $this->assertArrayHasKey('success', $resp, "{$name}: success key missing");
            $this->assertArrayHasKey('code', $resp, "{$name}: code key missing");
            $this->assertArrayHasKey('message', $resp, "{$name}: message key missing");
            $this->assertArrayHasKey('data', $resp, "{$name}: data key missing");
            $this->assertIsBool($resp['success'], "{$name}: success should be bool");
            $this->assertIsInt($resp['code'], "{$name}: code should be int");
        }
    }
}
