<?php

namespace App\Http\Controllers;

use App\Enums\AutomationRuleType;
use App\Enums\DropshipOrderStatus;
use App\Http\Resources\AutomationRuleResource;
use App\Models\AutomationRule;
use App\Models\DropshipOrder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Enum;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AutomationRuleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $query = AutomationRule::query()
            ->with(['warehouse', 'creator', 'updater'])
            ->when($request->filled('type'), fn ($q) => $q->byType($request->string('type')))
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')))
            ->when($request->filled('country_code'), fn ($q) => $q->byCountry($request->string('country_code')))
            ->when($request->filled('source_channel'), fn ($q) => $q->byChannel($request->string('source_channel')))
            ->when($request->filled('is_enabled'), function ($q) use ($request) {
                $q->where('is_enabled', $request->boolean('is_enabled'));
            })
            ->when($request->filled('keyword'), function ($q) use ($request) {
                $keyword = $request->string('keyword');
                $q->where(function ($sub) use ($keyword) {
                    $sub->where('name', 'like', "%{$keyword}%")
                        ->orWhere('code', 'like', "%{$keyword}%")
                        ->orWhere('description', 'like', "%{$keyword}%");
                });
            })
            ->ordered();

        $perPage = $request->integer('per_page', 20);
        $rules = $perPage > 0 ? $query->paginate($perPage) : $query->get();

        return AutomationRuleResource::collection($rules);
    }

    public function show(AutomationRule $rule): AutomationRuleResource
    {
        $rule->load(['warehouse', 'creator', 'updater']);

        return new AutomationRuleResource($rule);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:64', 'unique:automation_rules,code'],
            'type' => ['required', new Enum(AutomationRuleType::class)],
            'description' => ['nullable', 'string', 'max:512'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'source_channel' => ['nullable', 'string', 'max:32'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'active_time_start' => ['nullable', 'date_format:H:i:s'],
            'active_time_end' => ['nullable', 'date_format:H:i:s'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'is_enabled' => ['nullable', 'boolean'],
            'stop_chain' => ['nullable', 'boolean'],
        ]);

        $userId = $request->user()?->id;
        $validated['created_by'] = $userId;
        $validated['updated_by'] = $userId;
        $validated['priority'] = $validated['priority'] ?? 10;
        $validated['is_enabled'] = $validated['is_enabled'] ?? false;
        $validated['stop_chain'] = $validated['stop_chain'] ?? false;

        $rule = AutomationRule::create($validated);

        return response()->json([
            'success' => true,
            'data' => new AutomationRuleResource($rule->load('warehouse')),
        ], HttpResponse::HTTP_CREATED);
    }

    public function update(Request $request, AutomationRule $rule): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:128'],
            'code' => ['sometimes', 'string', 'max:64', 'unique:automation_rules,code,' . $rule->id],
            'type' => ['sometimes', new Enum(AutomationRuleType::class)],
            'description' => ['nullable', 'string', 'max:512'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100'],
            'conditions' => ['nullable', 'array'],
            'actions' => ['nullable', 'array'],
            'warehouse_id' => ['nullable', 'integer', 'exists:warehouses,id'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'source_channel' => ['nullable', 'string', 'max:32'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'max_order_amount' => ['nullable', 'numeric', 'min:0'],
            'active_time_start' => ['nullable', 'date_format:H:i:s'],
            'active_time_end' => ['nullable', 'date_format:H:i:s'],
            'weekdays' => ['nullable', 'array'],
            'weekdays.*' => ['integer', 'min:1', 'max:7'],
            'is_enabled' => ['nullable', 'boolean'],
            'stop_chain' => ['nullable', 'boolean'],
        ]);

        $validated['updated_by'] = $request->user()?->id;

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'data' => new AutomationRuleResource($rule->fresh()->load('warehouse')),
        ]);
    }

    public function destroy(AutomationRule $rule): JsonResponse
    {
        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => '删除成功',
        ]);
    }

    public function toggleEnabled(AutomationRule $rule): JsonResponse
    {
        $rule->is_enabled = !$rule->is_enabled;
        $rule->updated_by = request()->user()?->id;
        $rule->save();

        return response()->json([
            'success' => true,
            'data' => new AutomationRuleResource($rule->fresh()),
            'message' => $rule->is_enabled ? '规则已启用' : '规则已停用',
        ]);
    }

    public function trigger(AutomationRule $rule): JsonResponse
    {
        if (!$rule->isEnabled()) {
            return response()->json([
                'success' => false,
                'message' => '规则未启用，无法触发',
            ], HttpResponse::HTTP_BAD_REQUEST);
        }

        try {
            $automationService = app(\App\Services\AutomationEngineService::class);
            $result = $automationService->manualTrigger($rule->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'rule_id' => $rule->id,
                    'rule_name' => $rule->name,
                    'rule_type' => $rule->getTypeEnum()->label(),
                    'processed_count' => $result['processed'],
                    'success_count' => $result['success'],
                    'failed_count' => $result['failed'],
                    'errors' => array_slice(array_filter($result['details'], fn ($d) => !empty($d['error'])), 0, 10),
                    'triggered_at' => $rule->fresh()->last_triggered_at?->toDateTimeString(),
                    'total_trigger_count' => $rule->fresh()->trigger_count,
                    'total_success_count' => $rule->fresh()->success_count,
                    'total_failed_count' => $rule->fresh()->failed_count,
                    'success_rate' => $rule->fresh()->successRate() . '%',
                ],
                'message' => "手动触发完成：处理 {$result['processed']} 条，成功 {$result['success']} 条，失败 {$result['failed']} 条",
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => '规则执行失败：' . $e->getMessage(),
            ], HttpResponse::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    public function typeOptions(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => AutomationRuleType::groupedOptions(),
        ]);
    }

    public function statistics(Request $request): JsonResponse
    {
        $baseQuery = AutomationRule::query()
            ->when($request->filled('warehouse_id'), fn ($q) => $q->byWarehouse($request->integer('warehouse_id')));

        $totalRules = (clone $baseQuery)->count();
        $enabledRules = (clone $baseQuery)->enabled()->count();
        $totalTriggers = (clone $baseQuery)->sum('trigger_count');
        $totalSuccess = (clone $baseQuery)->sum('success_count');
        $totalFailed = (clone $baseQuery)->sum('failed_count');

        $typeStats = [];
        foreach (AutomationRuleType::cases() as $type) {
            $typeQuery = (clone $baseQuery)->byType($type);
            $typeStats[$type->value] = [
                'label' => $type->label(),
                'category' => $type->category(),
                'rule_count' => (clone $typeQuery)->count(),
                'enabled_count' => (clone $typeQuery)->enabled()->count(),
                'trigger_count' => (int) (clone $typeQuery)->sum('trigger_count'),
                'success_count' => (int) (clone $typeQuery)->sum('success_count'),
                'failed_count' => (int) (clone $typeQuery)->sum('failed_count'),
            ];
        }

        $topRules = (clone $baseQuery)
            ->orderByDesc('trigger_count')
            ->limit(5)
            ->get(['id', 'name', 'type', 'trigger_count', 'success_count', 'failed_count'])
            ->map(function ($rule) {
                return [
                    'id' => $rule->id,
                    'name' => $rule->name,
                    'type' => $rule->getTypeEnum()->label(),
                    'trigger_count' => $rule->trigger_count,
                    'success_count' => $rule->success_count,
                    'failed_count' => $rule->failed_count,
                    'success_rate' => $rule->successRate() . '%',
                ];
            })
            ->toArray();

        return response()->json([
            'success' => true,
            'data' => [
                'total_rules' => $totalRules,
                'enabled_rules' => $enabledRules,
                'disabled_rules' => $totalRules - $enabledRules,
                'total_triggers' => (int) $totalTriggers,
                'total_success' => (int) $totalSuccess,
                'total_failed' => (int) $totalFailed,
                'overall_success_rate' => $totalTriggers > 0 ? round(($totalSuccess / $totalTriggers) * 100, 2) . '%' : '0%',
                'type_statistics' => $typeStats,
                'top_rules' => $topRules,
            ],
        ]);
    }
}
