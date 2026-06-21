<?php

namespace Tests\Unit\Enums;

use App\Enums\AutomationRuleType;
use PHPUnit\Framework\TestCase;

class AutomationRuleTypeTest extends TestCase
{
    public function test_all_types_have_labels(): void
    {
        foreach (AutomationRuleType::cases() as $case) {
            $this->assertNotEmpty($case->label());
            $this->assertNotEmpty($case->description());
            $this->assertNotEmpty($case->category());
        }
    }

    public function test_label_matches_expected(): void
    {
        $this->assertSame('自动审核', AutomationRuleType::AUTO_REVIEW->label());
        $this->assertSame('自动分仓', AutomationRuleType::AUTO_ASSIGN_WAREHOUSE->label());
        $this->assertSame('自动分配物流', AutomationRuleType::AUTO_ASSIGN_SHIPPING->label());
        $this->assertSame('自动推单到WMS', AutomationRuleType::AUTO_PUSH_WMS->label());
        $this->assertSame('自动取消订单', AutomationRuleType::AUTO_CANCEL_ORDER->label());
    }

    public function test_category_groups_correctly(): void
    {
        $this->assertSame('订单处理', AutomationRuleType::AUTO_REVIEW->category());
        $this->assertSame('订单处理', AutomationRuleType::AUTO_SPLIT_ORDER->category());
        $this->assertSame('WMS集成', AutomationRuleType::AUTO_PUSH_WMS->category());
        $this->assertSame('WMS集成', AutomationRuleType::AUTO_SYNC_TRACKING->category());
        $this->assertSame('异常处理', AutomationRuleType::AUTO_CANCEL_ORDER->category());
        $this->assertSame('消息通知', AutomationRuleType::AUTO_NOTIFICATION->category());
    }

    public function test_options_returns_array_structure(): void
    {
        $options = AutomationRuleType::options();
        $this->assertIsArray($options);
        $this->assertNotEmpty($options);
        foreach ($options as $option) {
            $this->assertArrayHasKey('value', $option);
            $this->assertArrayHasKey('label', $option);
            $this->assertArrayHasKey('description', $option);
        }
    }

    public function test_groupedOptions_groups_by_category(): void
    {
        $grouped = AutomationRuleType::groupedOptions();
        $this->assertArrayHasKey('订单处理', $grouped);
        $this->assertArrayHasKey('WMS集成', $grouped);
        $this->assertArrayHasKey('异常处理', $grouped);
        $this->assertArrayHasKey('消息通知', $grouped);
    }

    public function test_tryFrom_returns_correct_case(): void
    {
        $this->assertSame(AutomationRuleType::AUTO_REVIEW, AutomationRuleType::tryFrom('auto_review'));
        $this->assertSame(AutomationRuleType::AUTO_PUSH_WMS, AutomationRuleType::tryFrom('auto_push_wms'));
        $this->assertNull(AutomationRuleType::tryFrom('invalid_type'));
    }
}
