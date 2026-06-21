<?php

namespace App\Enums;

enum WmsCallbackType: string
{
    case INVENTORY = 'inventory';
    case SHIPMENT = 'shipment';
    case TRACKING = 'tracking';
    case ORDER_STATUS = 'order_status';
    case STOCK_ADJUST = 'stock_adjust';

    public function label(): string
    {
        return match ($this) {
            self::INVENTORY => '库存同步',
            self::SHIPMENT => '发货通知',
            self::TRACKING => '物流轨迹',
            self::ORDER_STATUS => '订单状态变更',
            self::STOCK_ADJUST => '库存调整',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::INVENTORY => 'warning',
            self::SHIPMENT => 'success',
            self::TRACKING => 'primary',
            self::ORDER_STATUS => 'info',
            self::STOCK_ADJUST => 'danger',
        };
    }

    public static function options(): array
    {
        return array_map(fn ($case) => [
            'value' => $case->value,
            'label' => $case->label(),
            'color' => $case->color(),
        ], self::cases());
    }
}
