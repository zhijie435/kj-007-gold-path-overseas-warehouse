<?php

namespace App\Enums;

enum AutomationRuleType: string
{
    case AUTO_REVIEW = 'auto_review';
    case AUTO_ASSIGN_WAREHOUSE = 'auto_assign_warehouse';
    case AUTO_ASSIGN_SHIPPING = 'auto_assign_shipping';
    case AUTO_PUSH_WMS = 'auto_push_wms';
    case AUTO_SPLIT_ORDER = 'auto_split_order';
    case AUTO_COMBINE_ORDER = 'auto_combine_order';
    case AUTO_SYNC_TRACKING = 'auto_sync_tracking';
    case AUTO_SYNC_INVENTORY = 'auto_sync_inventory';
    case AUTO_CANCEL_ORDER = 'auto_cancel_order';
    case AUTO_NOTIFICATION = 'auto_notification';

    public function label(): string
    {
        return match ($this) {
            self::AUTO_REVIEW => '自动审核',
            self::AUTO_ASSIGN_WAREHOUSE => '自动分仓',
            self::AUTO_ASSIGN_SHIPPING => '自动分配物流',
            self::AUTO_PUSH_WMS => '自动推单到WMS',
            self::AUTO_SPLIT_ORDER => '自动拆单',
            self::AUTO_COMBINE_ORDER => '自动合单',
            self::AUTO_SYNC_TRACKING => '自动同步物流轨迹',
            self::AUTO_SYNC_INVENTORY => '自动同步库存',
            self::AUTO_CANCEL_ORDER => '自动取消订单',
            self::AUTO_NOTIFICATION => '自动通知',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::AUTO_REVIEW => '满足条件的订单自动通过审核',
            self::AUTO_ASSIGN_WAREHOUSE => '根据收货地址自动分配最优海外仓',
            self::AUTO_ASSIGN_SHIPPING => '根据重量/国家/时效自动分配物流渠道',
            self::AUTO_PUSH_WMS => '审核通过后自动推送到WMS系统',
            self::AUTO_SPLIT_ORDER => '按仓库/商品属性自动拆分订单',
            self::AUTO_COMBINE_ORDER => '同收货地址的多单合并发货',
            self::AUTO_SYNC_TRACKING => '定时拉取物流轨迹并更新',
            self::AUTO_SYNC_INVENTORY => '定时从WMS同步库存数量',
            self::AUTO_CANCEL_ORDER => '超时未处理自动取消订单',
            self::AUTO_NOTIFICATION => '状态变更触发消息通知',
        };
    }

    public function category(): string
    {
        return match ($this) {
            self::AUTO_REVIEW,
            self::AUTO_ASSIGN_WAREHOUSE,
            self::AUTO_ASSIGN_SHIPPING,
            self::AUTO_SPLIT_ORDER,
            self::AUTO_COMBINE_ORDER => '订单处理',
            self::AUTO_PUSH_WMS,
            self::AUTO_SYNC_TRACKING,
            self::AUTO_SYNC_INVENTORY => 'WMS集成',
            self::AUTO_CANCEL_ORDER => '异常处理',
            self::AUTO_NOTIFICATION => '消息通知',
        };
    }

    public static function groupedOptions(): array
    {
        $groups = [];
        foreach (self::cases() as $case) {
            $groups[$case->category()][] = [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ];
        }
        return $groups;
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'description' => $case->description(),
        ], self::cases());
    }
}
