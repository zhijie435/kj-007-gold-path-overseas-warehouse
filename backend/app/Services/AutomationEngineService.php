<?php

namespace App\Services;

use App\Enums\AutomationRuleType;
use App\Enums\DropshipOrderStatus;
use App\Models\AutomationRule;
use App\Models\DropshipOrder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

class AutomationEngineService
{
    public function executeRulesForOrder(DropshipOrder $order, string $stage): array
    {
        $results = [
            'executed' => 0,
            'success' => 0,
            'failed' => 0,
            'skipped' => 0,
            'rules' => [],
        ];

        $typeMap = $this->stageToTypeMap($stage);
        if (empty($typeMap)) {
            return $results;
        }

        $rules = $this->getRulesForStage($order, $typeMap);

        foreach ($rules as $rule) {
            $ruleResult = [
                'rule_id' => $rule->id,
                'rule_name' => $rule->name,
                'rule_type' => $rule->getTypeEnum()->value,
                'evaluated' => false,
                'matched' => false,
                'executed' => false,
                'success' => false,
                'error' => null,
            ];

            try {
                $ruleResult['evaluated'] = true;
                $matched = $this->evaluateRule($rule, $order);
                $ruleResult['matched'] = $matched;

                if (!$matched) {
                    $results['skipped']++;
                    $results['rules'][] = $ruleResult;
                    continue;
                }

                $ruleResult['executed'] = true;
                $results['executed']++;

                $success = $this->executeAction($rule, $order);
                $ruleResult['success'] = $success;

                if ($success) {
                    $results['success']++;
                    $rule->incrementTrigger(true);
                } else {
                    $results['failed']++;
                    $rule->incrementTrigger(false);
                }

                $results['rules'][] = $ruleResult;

                if ($rule->stop_chain) {
                    break;
                }
            } catch (\Throwable $e) {
                $ruleResult['success'] = false;
                $ruleResult['error'] = $e->getMessage();
                $results['failed']++;
                $results['rules'][] = $ruleResult;
                $rule->incrementTrigger(false);
            }
        }

        return $results;
    }

    public function evaluateRule(AutomationRule $rule, DropshipOrder $order): bool
    {
        if (!$rule->matchesBasicConditions(['amount' => (float) ($order->total_cost ?? 0)])) {
            return false;
        }

        if (!empty($rule->warehouse_id) && (int) $rule->warehouse_id !== (int) ($order->warehouse_id ?? 0)) {
            return false;
        }

        if (!empty($rule->country_code) && strtoupper((string) $rule->country_code) !== strtoupper((string) ($order->receiver_country ?? ''))) {
            return false;
        }

        if (!empty($rule->source_channel) && (string) $rule->source_channel !== (string) ($order->source_channel ?? '')) {
            return false;
        }

        $conditions = $rule->conditions ?? [];
        if (empty($conditions)) {
            return true;
        }

        return $this->evaluateConditionGroup($conditions, $order);
    }

    public function executeAction(AutomationRule $rule, DropshipOrder $order): bool
    {
        $actions = $rule->actions ?? [];
        if (empty($actions)) {
            return true;
        }

        $allSuccess = true;

        foreach ($actions as $action) {
            $actionType = $action['type'] ?? null;
            $actionParams = $action['params'] ?? [];

            try {
                $success = match ($rule->getTypeEnum()) {
                    AutomationRuleType::AUTO_REVIEW => $this->actionAutoReview($order, $actionParams),
                    AutomationRuleType::AUTO_ASSIGN_WAREHOUSE => $this->actionAssignWarehouse($order, $actionParams),
                    AutomationRuleType::AUTO_ASSIGN_SHIPPING => $this->actionAssignShipping($order, $actionParams),
                    AutomationRuleType::AUTO_PUSH_WMS => $this->actionPushWms($order, $actionParams),
                    AutomationRuleType::AUTO_CANCEL_ORDER => $this->actionCancelOrder($order, $actionParams),
                    AutomationRuleType::AUTO_SYNC_TRACKING => $this->actionSyncTracking($order, $actionParams),
                    AutomationRuleType::AUTO_SYNC_INVENTORY => $this->actionSyncInventory($order, $actionParams),
                    AutomationRuleType::AUTO_SPLIT_ORDER => $this->actionSplitOrder($order, $actionParams),
                    AutomationRuleType::AUTO_COMBINE_ORDER => $this->actionCombineOrder($order, $actionParams),
                    AutomationRuleType::AUTO_NOTIFICATION => $this->actionSendNotification($order, $actionParams),
                };

                if (!$success) {
                    $allSuccess = false;
                }
            } catch (\Throwable $e) {
                report($e);
                $allSuccess = false;
            }
        }

        return $allSuccess;
    }

    public function getRulesByType(string $type): array
    {
        $enumType = AutomationRuleType::tryFrom($type);
        if ($enumType === null) {
            throw new InvalidArgumentException(sprintf('无效的规则类型：%s', $type));
        }

        return AutomationRule::query()
            ->enabled()
            ->byType($enumType)
            ->ordered()
            ->get()
            ->all();
    }

    public function manualTrigger(int $ruleId): array
    {
        $rule = AutomationRule::query()->find($ruleId);
        if ($rule === null) {
            throw new RuntimeException(sprintf('规则 ID=%d 不存在', $ruleId));
        }

        if (!$rule->isEnabled()) {
            throw new RuntimeException('规则未启用');
        }

        $orders = $this->getEligibleOrdersForRule($rule);
        $results = [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'total_eligible' => count($orders),
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => [],
        ];

        foreach ($orders as $order) {
            $detail = [
                'order_id' => $order->id,
                'dropship_no' => $order->dropship_no,
                'evaluated' => false,
                'matched' => false,
                'executed' => false,
                'success' => false,
                'error' => null,
            ];

            try {
                $detail['evaluated'] = true;
                $matched = $this->evaluateRule($rule, $order);
                $detail['matched'] = $matched;

                if (!$matched) {
                    $results['details'][] = $detail;
                    continue;
                }

                $detail['executed'] = true;
                $results['processed']++;

                $success = $this->executeAction($rule, $order);
                $detail['success'] = $success;

                if ($success) {
                    $results['success']++;
                } else {
                    $results['failed']++;
                }

                $rule->incrementTrigger($success);
            } catch (\Throwable $e) {
                $detail['success'] = false;
                $detail['error'] = $e->getMessage();
                $results['failed']++;
                $rule->incrementTrigger(false);
            }

            $results['details'][] = $detail;
        }

        return $results;
    }

    protected function stageToTypeMap(string $stage): array
    {
        $map = [
            'order_created' => [
                AutomationRuleType::AUTO_ASSIGN_WAREHOUSE,
                AutomationRuleType::AUTO_ASSIGN_SHIPPING,
                AutomationRuleType::AUTO_NOTIFICATION,
            ],
            'order_submitted' => [
                AutomationRuleType::AUTO_REVIEW,
                AutomationRuleType::AUTO_ASSIGN_WAREHOUSE,
                AutomationRuleType::AUTO_ASSIGN_SHIPPING,
                AutomationRuleType::AUTO_SPLIT_ORDER,
                AutomationRuleType::AUTO_COMBINE_ORDER,
                AutomationRuleType::AUTO_NOTIFICATION,
            ],
            'review_passed' => [
                AutomationRuleType::AUTO_PUSH_WMS,
                AutomationRuleType::AUTO_NOTIFICATION,
            ],
            'order_exception' => [
                AutomationRuleType::AUTO_CANCEL_ORDER,
                AutomationRuleType::AUTO_NOTIFICATION,
            ],
            'scheduled' => [
                AutomationRuleType::AUTO_SYNC_TRACKING,
                AutomationRuleType::AUTO_SYNC_INVENTORY,
                AutomationRuleType::AUTO_CANCEL_ORDER,
            ],
        ];

        return $map[$stage] ?? [];
    }

    protected function getRulesForStage(DropshipOrder $order, array $types): array
    {
        if (empty($types)) {
            return [];
        }

        $typeValues = array_map(fn (AutomationRuleType $t) => $t->value, $types);

        return AutomationRule::query()
            ->enabled()
            ->whereIn('type', $typeValues)
            ->byWarehouse($order->warehouse_id)
            ->byCountry($order->receiver_country ?? '')
            ->byChannel($order->source_channel ?? '')
            ->ordered()
            ->get()
            ->all();
    }

    protected function evaluateConditionGroup(array $conditions, DropshipOrder $order): bool
    {
        $logic = strtoupper($conditions['logic'] ?? 'AND');
        $rules = $conditions['rules'] ?? $conditions;

        if ($logic === 'AND') {
            foreach ($rules as $rule) {
                if (isset($rule['rules']) || isset($rule['logic'])) {
                    if (!$this->evaluateConditionGroup($rule, $order)) {
                        return false;
                    }
                } else {
                    if (!$this->evaluateSingleCondition($rule, $order)) {
                        return false;
                    }
                }
            }
            return true;
        }

        foreach ($rules as $rule) {
            if (isset($rule['rules']) || isset($rule['logic'])) {
                if ($this->evaluateConditionGroup($rule, $order)) {
                    return true;
                }
            } else {
                if ($this->evaluateSingleCondition($rule, $order)) {
                    return true;
                }
            }
        }
        return false;
    }

    protected function evaluateSingleCondition(array $condition, DropshipOrder $order): bool
    {
        $field = $condition['field'] ?? '';
        $operator = strtolower($condition['operator'] ?? 'eq');
        $value = $condition['value'] ?? null;

        $actualValue = $this->getOrderFieldValue($order, $field);

        return match ($operator) {
            'eq', '=' => $actualValue == $value,
            'ne', '!=' => $actualValue != $value,
            'gt', '>' => $actualValue > $value,
            'gte', '>=' => $actualValue >= $value,
            'lt', '<' => $actualValue < $value,
            'lte', '<=' => $actualValue <= $value,
            'contains', 'like' => is_string($actualValue) && str_contains(strtolower($actualValue), strtolower((string) $value)),
            'not_contains' => is_string($actualValue) && !str_contains(strtolower($actualValue), strtolower((string) $value)),
            'in' => is_array($value) && in_array($actualValue, $value, true),
            'not_in' => is_array($value) && !in_array($actualValue, $value, true),
            'empty', 'null' => $actualValue === null || $actualValue === '' || (is_array($actualValue) && empty($actualValue)),
            'not_empty', 'not_null' => $actualValue !== null && $actualValue !== '' && !(is_array($actualValue) && empty($actualValue)),
            'between' => is_array($value) && count($value) === 2 && $actualValue >= $value[0] && $actualValue <= $value[1],
            'starts_with' => is_string($actualValue) && str_starts_with(strtolower($actualValue), strtolower((string) $value)),
            'ends_with' => is_string($actualValue) && str_ends_with(strtolower($actualValue), strtolower((string) $value)),
            default => false,
        };
    }

    protected function getOrderFieldValue(DropshipOrder $order, string $field): mixed
    {
        $fieldMap = [
            'receiver_country' => 'receiver_country',
            'receiver_state' => 'receiver_state',
            'receiver_city' => 'receiver_city',
            'country' => 'receiver_country',
            'state' => 'receiver_state',
            'city' => 'receiver_city',
            'total_cost' => 'total_cost',
            'amount' => 'total_cost',
            'total_items' => 'total_items',
            'weight' => 'weight',
            'source_channel' => 'source_channel',
            'channel' => 'source_channel',
            'warehouse_id' => 'warehouse_id',
            'shipping_method_code' => 'shipping_method_code',
            'shipping_method' => 'shipping_method_code',
            'currency' => 'currency',
            'status' => fn () => $order->getStatusEnum()->value,
            'declared_value' => 'declared_value',
            'shipping_fee' => 'shipping_fee',
            'duty_fee' => 'duty_fee',
            'has_insurance' => fn () => (float) ($order->insurance_fee ?? 0) > 0,
            'insurance_fee' => 'insurance_fee',
            'is_overweight' => fn () => (float) ($order->weight ?? 0) > 30,
            'is_bulk' => fn () => (int) ($order->total_items ?? 0) > 10,
            'is_high_value' => fn () => (float) ($order->total_cost ?? 0) > 1000,
            'item_count' => fn () => $order->items->count(),
            'sku_list' => fn () => $order->items->pluck('sku')->toArray(),
            'postal_code' => 'receiver_postal_code',
            'is_remote_area' => fn () => $this->checkRemoteArea($order),
        ];

        $key = $fieldMap[$field] ?? null;
        if ($key === null) {
            return $order->{$field} ?? null;
        }

        if ($key instanceof \Closure) {
            return $key();
        }

        return $order->{$key} ?? null;
    }

    protected function checkRemoteArea(DropshipOrder $order): bool
    {
        return false;
    }

    protected function actionAutoReview(DropshipOrder $order, array $params): bool
    {
        $currentStatus = $order->getStatusEnum();
        if (!in_array($currentStatus, [DropshipOrderStatus::DRAFT, DropshipOrderStatus::PENDING_REVIEW], true)) {
            return false;
        }

        $service = app(OverseaDropshipService::class);
        $service->reviewOrder($order, true, $params['remark'] ?? '系统自动审核通过', null);

        $order->refresh();
        $this->executeRulesForOrder($order, 'review_passed');

        return true;
    }

    protected function actionAssignWarehouse(DropshipOrder $order, array $params): bool
    {
        $warehouseId = $params['warehouse_id'] ?? null;

        if ($warehouseId === null && !empty($order->receiver_country)) {
            $service = app(OverseaDropshipService::class);
            $config = $service->assignWarehouseByCountry($order->receiver_country);
            if ($config !== null) {
                $warehouseId = $config->warehouse_id;
            }
        }

        if ($warehouseId !== null) {
            $order->warehouse_id = $warehouseId;
            $order->save();
            return true;
        }

        return false;
    }

    protected function actionAssignShipping(DropshipOrder $order, array $params): bool
    {
        $shippingMethod = $params['shipping_method_code'] ?? null;

        if ($shippingMethod === null && !empty($order->warehouse_id)) {
            $config = \App\Models\OverseaWarehouseConfig::query()
                ->where('warehouse_id', $order->warehouse_id)
                ->active()
                ->first();
            if ($config !== null) {
                $shippingMethod = $config->default_shipping_method;
            }
        }

        if ($shippingMethod !== null) {
            $order->shipping_method_code = $shippingMethod;
            $order->save();
            return true;
        }

        return false;
    }

    protected function actionPushWms(DropshipOrder $order, array $params): bool
    {
        $currentStatus = $order->getStatusEnum();
        $pushable = [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
        ];
        if (!in_array($currentStatus, $pushable, true)) {
            return false;
        }

        $dispatch = (bool) ($params['async'] ?? true);

        if ($dispatch) {
            \App\Jobs\ProcessDropshipOrderJob::dispatch($order);
            return true;
        }

        $service = app(OverseaDropshipService::class);
        $service->pushToWms($order);
        return true;
    }

    protected function actionCancelOrder(DropshipOrder $order, array $params): bool
    {
        if ($order->getStatusEnum()->isTerminal()) {
            return false;
        }

        $service = app(OverseaDropshipService::class);
        $service->cancelOrder($order, $params['reason'] ?? '系统自动取消');
        return true;
    }

    protected function actionSyncTracking(DropshipOrder $order, array $params): bool
    {
        if (empty($order->warehouse_id) || empty($order->wms_order_no)) {
            return false;
        }

        $config = \App\Models\OverseaWarehouseConfig::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->active()
            ->first();

        if ($config === null) {
            return false;
        }

        $service = app(WmsIntegrationService::class);
        $service->fetchTracking($config, $order);
        return true;
    }

    protected function actionSyncInventory(DropshipOrder $order, array $params): bool
    {
        if (empty($order->warehouse_id)) {
            return false;
        }

        $config = \App\Models\OverseaWarehouseConfig::query()
            ->where('warehouse_id', $order->warehouse_id)
            ->active()
            ->first();

        if ($config === null) {
            return false;
        }

        $service = app(WmsIntegrationService::class);
        $service->fetchInventory($config);
        return true;
    }

    protected function actionSplitOrder(DropshipOrder $order, array $params): bool
    {
        return true;
    }

    protected function actionCombineOrder(DropshipOrder $order, array $params): bool
    {
        return true;
    }

    protected function actionSendNotification(DropshipOrder $order, array $params): bool
    {
        return true;
    }

    protected function getEligibleOrdersForRule(AutomationRule $rule): array
    {
        $query = DropshipOrder::query();

        match ($rule->getTypeEnum()) {
            AutomationRuleType::AUTO_REVIEW => $query->byStatus(DropshipOrderStatus::PENDING_REVIEW),
            AutomationRuleType::AUTO_ASSIGN_WAREHOUSE => $query->whereNull('warehouse_id'),
            AutomationRuleType::AUTO_ASSIGN_SHIPPING => $query->whereNull('shipping_method_code'),
            AutomationRuleType::AUTO_PUSH_WMS => $query->pendingPush(),
            AutomationRuleType::AUTO_SYNC_TRACKING => $query->inTransit(),
            AutomationRuleType::AUTO_CANCEL_ORDER => $query->byStatus(DropshipOrderStatus::PENDING_REVIEW)
                ->where('created_at', '<', now()->subHours(48)),
            default => $query->pendingPush(),
        };

        if (!empty($rule->warehouse_id)) {
            $query->byWarehouse($rule->warehouse_id);
        }
        if (!empty($rule->country_code)) {
            $query->byCountry($rule->country_code);
        }
        if (!empty($rule->source_channel)) {
            $query->byChannel($rule->source_channel);
        }

        return $query->limit(100)->get()->all();
    }
}
