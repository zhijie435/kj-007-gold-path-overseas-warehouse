<?php

namespace Tests\Unit\Services;

use App\Services\AutomationEngineService;
use App\Services\DropshipStateMachine;
use App\Enums\AutomationRuleType;
use App\Enums\DropshipOrderStatus;
use App\Models\AutomationRule;
use App\Models\DropshipOrder;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class AutomationEngineServiceTest extends TestCase
{
    protected AutomationEngineService $service;
    protected DropshipStateMachine $stateMachine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->stateMachine = new DropshipStateMachine();
        $this->service = new AutomationEngineService($this->stateMachine);
    }

    protected function createOrder(array $attrs = []): DropshipOrder
    {
        $order = new DropshipOrder();
        $order->exists = true;
        $order->status = $attrs['status'] ?? DropshipOrderStatus::DRAFT;
        foreach ($attrs as $key => $value) {
            $order->{$key} = $value;
        }
        $order->save = function () use ($order): bool {
            return true;
        };
        $order->items = new class($attrs['items'] ?? []) extends \Illuminate\Database\Eloquent\Collection {
            public function __construct(array $items = [])
            {
                parent::__construct($items);
            }
            public function pluck($column, $key = null): \Illuminate\Support\Collection
            {
                return collect(array_map(fn ($i) => $i[$column] ?? null, $this->items));
            }
            public function count(): int
            {
                return count($this->items);
            }
        };
        return $order;
    }

    protected function createRule(array $attrs = []): AutomationRule
    {
        $rule = new AutomationRule();
        $rule->exists = true;
        $rule->id = $attrs['id'] ?? 1;
        $rule->name = $attrs['name'] ?? 'Test Rule';
        $rule->type = $attrs['type'] ?? AutomationRuleType::AUTO_REVIEW;
        $rule->is_enabled = $attrs['is_enabled'] ?? true;
        $rule->priority = $attrs['priority'] ?? 10;
        $rule->stop_chain = $attrs['stop_chain'] ?? false;
        $rule->trigger_count = 0;
        $rule->success_count = 0;
        $rule->failed_count = 0;
        foreach ($attrs as $key => $value) {
            if (!in_array($key, ['id', 'name', 'type', 'is_enabled', 'priority', 'stop_chain'], true)) {
                $rule->{$key} = $value;
            }
        }
        $rule->save = function () use ($rule): bool {
            return true;
        };
        return $rule;
    }

    public function test_evaluateRule_empty_conditions_returns_true(): void
    {
        $rule = $this->createRule();
        $order = $this->createOrder(['total_cost' => 50.00, 'receiver_country' => 'US']);
        $this->assertTrue($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_disabled_rule_returns_false(): void
    {
        $rule = $this->createRule(['is_enabled' => false]);
        $order = $this->createOrder();
        $this->assertFalse($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_warehouse_mismatch(): void
    {
        $rule = $this->createRule(['warehouse_id' => 5]);
        $order = $this->createOrder(['warehouse_id' => 10]);
        $this->assertFalse($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_warehouse_match(): void
    {
        $rule = $this->createRule(['warehouse_id' => 5]);
        $order = $this->createOrder(['warehouse_id' => 5]);
        $this->assertTrue($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_country_mismatch(): void
    {
        $rule = $this->createRule(['country_code' => 'US']);
        $order = $this->createOrder(['receiver_country' => 'GB']);
        $this->assertFalse($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_country_match_case_insensitive(): void
    {
        $rule = $this->createRule(['country_code' => 'us']);
        $order = $this->createOrder(['receiver_country' => 'US']);
        $this->assertTrue($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_channel_mismatch(): void
    {
        $rule = $this->createRule(['source_channel' => 'shopify']);
        $order = $this->createOrder(['source_channel' => 'amazon']);
        $this->assertFalse($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_amount_out_of_range(): void
    {
        $rule = $this->createRule(['min_order_amount' => 100.00]);
        $order = $this->createOrder(['total_cost' => 50.00]);
        $this->assertFalse($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_amount_within_range(): void
    {
        $rule = $this->createRule(['min_order_amount' => 10.00, 'max_order_amount' => 100.00]);
        $order = $this->createOrder(['total_cost' => 50.00]);
        $this->assertTrue($this->service->evaluateRule($rule, $order));
    }

    public function test_evaluateRule_condition_eq(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'logic' => 'AND',
                'rules' => [
                    ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'US'],
                ],
            ],
        ]);
        $order = $this->createOrder(['receiver_country' => 'US']);
        $this->assertTrue($this->service->evaluateRule($rule, $order));

        $order2 = $this->createOrder(['receiver_country' => 'GB']);
        $this->assertFalse($this->service->evaluateRule($rule, $order2));
    }

    public function test_evaluateRule_condition_gt_lt(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'logic' => 'AND',
                'rules' => [
                    ['field' => 'total_cost', 'operator' => 'gt', 'value' => 50],
                    ['field' => 'total_cost', 'operator' => 'lt', 'value' => 200],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 100.00])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 30.00])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 300.00])));
    }

    public function test_evaluateRule_condition_contains(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'rules' => [
                    ['field' => 'receiver_city', 'operator' => 'contains', 'value' => 'york'],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['receiver_city' => 'New York'])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['receiver_city' => 'London'])));
    }

    public function test_evaluateRule_condition_in(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'rules' => [
                    ['field' => 'receiver_country', 'operator' => 'in', 'value' => ['US', 'CA', 'GB']],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['receiver_country' => 'US'])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['receiver_country' => 'DE'])));
    }

    public function test_evaluateRule_condition_between(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'rules' => [
                    ['field' => 'total_cost', 'operator' => 'between', 'value' => [10, 100]],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 50])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 5])));
    }

    public function test_evaluateRule_or_logic(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'logic' => 'OR',
                'rules' => [
                    ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'US'],
                    ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'CA'],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['receiver_country' => 'US'])));
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['receiver_country' => 'CA'])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['receiver_country' => 'GB'])));
    }

    public function test_evaluateRule_nested_conditions(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'logic' => 'AND',
                'rules' => [
                    ['field' => 'total_cost', 'operator' => 'gt', 'value' => 50],
                    [
                        'logic' => 'OR',
                        'rules' => [
                            ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'US'],
                            ['field' => 'receiver_country', 'operator' => 'eq', 'value' => 'CA'],
                        ],
                    ],
                ],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 100, 'receiver_country' => 'US'])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 10, 'receiver_country' => 'US'])));
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 100, 'receiver_country' => 'DE'])));
    }

    public function test_evaluateRule_unknown_operator_returns_false(): void
    {
        $rule = $this->createRule([
            'conditions' => [
                'rules' => [
                    ['field' => 'total_cost', 'operator' => 'unknown_op', 'value' => 100],
                ],
            ],
        ]);
        $this->assertFalse($this->service->evaluateRule($rule, $this->createOrder(['total_cost' => 50])));
    }

    public function test_evaluateRule_condition_starts_with_ends_with(): void
    {
        $ruleStart = $this->createRule([
            'conditions' => [
                'rules' => [['field' => 'receiver_city', 'operator' => 'starts_with', 'value' => 'new']],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($ruleStart, $this->createOrder(['receiver_city' => 'New York'])));
        $this->assertFalse($this->service->evaluateRule($ruleStart, $this->createOrder(['receiver_city' => 'York New'])));

        $ruleEnd = $this->createRule([
            'conditions' => [
                'rules' => [['field' => 'receiver_city', 'operator' => 'ends_with', 'value' => 'city']],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($ruleEnd, $this->createOrder(['receiver_city' => 'Kansas City'])));
    }

    public function test_evaluateRule_empty_null_checks(): void
    {
        $ruleEmpty = $this->createRule([
            'conditions' => [
                'rules' => [['field' => 'tracking_no', 'operator' => 'empty', 'value' => '']],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($ruleEmpty, $this->createOrder(['tracking_no' => ''])));

        $ruleNotEmpty = $this->createRule([
            'conditions' => [
                'rules' => [['field' => 'tracking_no', 'operator' => 'not_empty', 'value' => '']],
            ],
        ]);
        $this->assertTrue($this->service->evaluateRule($ruleNotEmpty, $this->createOrder(['tracking_no' => 'TRK123'])));
    }

    public function test_getRulesByType_invalid_type_throws(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->service->getRulesByType('invalid_type');
    }

    public function test_executeRulesForOrder_invalid_stage_returns_empty(): void
    {
        $order = $this->createOrder();
        $result = $this->service->executeRulesForOrder($order, 'nonexistent_stage');
        $this->assertSame(0, $result['executed']);
        $this->assertSame(0, $result['success']);
        $this->assertSame(0, $result['failed']);
        $this->assertSame(0, $result['skipped']);
        $this->assertEmpty($result['rules']);
    }

    public function test_manualTrigger_rule_not_found_throws(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('规则 ID=999 不存在');
        $this->service->manualTrigger(999);
    }
}
