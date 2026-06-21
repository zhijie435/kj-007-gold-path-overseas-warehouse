<?php

namespace Tests\Unit\Models;

use App\Models\AutomationRule;
use App\Enums\AutomationRuleType;
use PHPUnit\Framework\TestCase;

class AutomationRuleTest extends TestCase
{
    protected function createRule(array $attrs = []): AutomationRule
    {
        $rule = new AutomationRule();
        foreach ($attrs as $key => $value) {
            $rule->{$key} = $value;
        }
        return $rule;
    }

    public function test_isEnabled_returns_true_when_enabled(): void
    {
        $rule = $this->createRule(['is_enabled' => true]);
        $this->assertTrue($rule->isEnabled());
    }

    public function test_isEnabled_returns_false_when_disabled(): void
    {
        $rule = $this->createRule(['is_enabled' => false]);
        $this->assertFalse($rule->isEnabled());
    }

    public function test_successRate_no_triggers_returns_zero(): void
    {
        $rule = $this->createRule(['trigger_count' => 0, 'success_count' => 0]);
        $this->assertSame(0.0, $rule->successRate());
    }

    public function test_successRate_calculates_percentage(): void
    {
        $rule = $this->createRule(['trigger_count' => 100, 'success_count' => 85]);
        $this->assertSame(85.0, $rule->successRate());
    }

    public function test_successRate_rounds(): void
    {
        $rule = $this->createRule(['trigger_count' => 3, 'success_count' => 1]);
        $this->assertSame(33.33, $rule->successRate());
    }

    public function test_isWithinAmountRange_within_range(): void
    {
        $rule = $this->createRule(['min_order_amount' => 10.00, 'max_order_amount' => 100.00]);
        $this->assertTrue($rule->isWithinAmountRange(50.00));
        $this->assertTrue($rule->isWithinAmountRange(10.00));
        $this->assertTrue($rule->isWithinAmountRange(100.00));
    }

    public function test_isWithinAmountRange_outside_range(): void
    {
        $rule = $this->createRule(['min_order_amount' => 10.00, 'max_order_amount' => 100.00]);
        $this->assertFalse($rule->isWithinAmountRange(5.00));
        $this->assertFalse($rule->isWithinAmountRange(150.00));
    }

    public function test_isWithinAmountRange_no_bounds(): void
    {
        $rule = $this->createRule();
        $this->assertTrue($rule->isWithinAmountRange(999999.00));
    }

    public function test_matchesBasicConditions_checks_all(): void
    {
        $rule = $this->createRule([
            'is_enabled' => true,
            'min_order_amount' => null,
            'max_order_amount' => null,
        ]);
        $this->assertTrue($rule->matchesBasicConditions());
    }

    public function test_matchesBasicConditions_disabled_fails(): void
    {
        $rule = $this->createRule(['is_enabled' => false]);
        $this->assertFalse($rule->matchesBasicConditions());
    }

    public function test_matchesBasicConditions_amount_out_of_range(): void
    {
        $rule = $this->createRule([
            'is_enabled' => true,
            'min_order_amount' => 100.00,
        ]);
        $this->assertFalse($rule->matchesBasicConditions(['amount' => 50.00]));
    }

    public function test_incrementTrigger_increments_counts_success(): void
    {
        $rule = $this->createRule([
            'trigger_count' => 5,
            'success_count' => 3,
            'failed_count' => 2,
        ]);
        $rule->exists = true;
        $rule->wasRecentlyCreated = false;
        $rule->save = function () use ($rule): bool {
            $rule->exists = true;
            return true;
        };

        $rule->incrementTrigger(true);
        $this->assertSame(6, $rule->trigger_count);
        $this->assertSame(4, $rule->success_count);
        $this->assertSame(2, $rule->failed_count);
        $this->assertNotNull($rule->last_triggered_at);
    }

    public function test_incrementTrigger_increments_counts_failed(): void
    {
        $rule = $this->createRule([
            'trigger_count' => 5,
            'success_count' => 3,
            'failed_count' => 2,
        ]);
        $rule->exists = true;
        $rule->wasRecentlyCreated = false;
        $rule->save = function () use ($rule): bool {
            return true;
        };

        $rule->incrementTrigger(false);
        $this->assertSame(6, $rule->trigger_count);
        $this->assertSame(3, $rule->success_count);
        $this->assertSame(3, $rule->failed_count);
    }
}
