<?php

namespace App\Jobs;

use App\Enums\DropshipOrderStatus;
use App\Models\DropshipOrder;
use App\Services\OverseaDropshipService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessDropshipOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public int $maxExceptions = 3;

    public function __construct(
        public DropshipOrder $order,
    ) {}

    public function backoff(): array
    {
        return [60, 180, 300, 600, 900];
    }

    public function retryUntil(): ?\DateTime
    {
        return now()->addHours(24);
    }

    public function handle(OverseaDropshipService $service): void
    {
        $this->order->refresh();

        $currentStatus = $this->order->getStatusEnum();
        $pushable = [
            DropshipOrderStatus::REVIEW_PASS,
            DropshipOrderStatus::AUTO_REVIEW_PASS,
            DropshipOrderStatus::PUSH_FAILED,
        ];

        if (!in_array($currentStatus, $pushable, true)) {
            Log::info(sprintf(
                '[ProcessDropshipOrderJob] 代发单 %s 当前状态 %s 不满足推送条件，跳过',
                $this->order->dropship_no,
                $currentStatus->value
            ));
            $this->delete();
            return;
        }

        if (empty($this->order->warehouse_id)) {
            Log::warning(sprintf(
                '[ProcessDropshipOrderJob] 代发单 %s 未分配海外仓，跳过推送',
                $this->order->dropship_no
            ));
            $this->fail(new \RuntimeException('未分配海外仓'));
            return;
        }

        try {
            $service->pushToWms($this->order);

            Log::info(sprintf(
                '[ProcessDropshipOrderJob] 代发单 %s 推送WMS成功，WMS单号：%s',
                $this->order->dropship_no,
                $this->order->wms_order_no ?? '-'
            ));
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[ProcessDropshipOrderJob] 代发单 %s 推送WMS失败（第 %d 次尝试）：%s',
                $this->order->dropship_no,
                $this->attempts(),
                $e->getMessage()
            ));

            if ($this->attempts() >= $this->tries) {
                $this->handleFinalFailure($e);
            }

            throw $e;
        }
    }

    public function failed(?Throwable $exception): void
    {
        if ($exception === null) {
            return;
        }

        $this->order->refresh();

        if ($this->order->getStatusEnum() === DropshipOrderStatus::PUSHING) {
            $this->order->status = DropshipOrderStatus::PUSH_FAILED;
        }

        $this->order->push_error = sprintf(
            '[%s] %s (尝试 %d 次)',
            now()->toDateTimeString(),
            $exception->getMessage(),
            $this->attempts()
        );

        $extraData = $this->order->extra_data ?? [];
        $extraData['job_failure'] = [
            'time' => now()->toDateTimeString(),
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'attempts' => $this->attempts(),
            'trace' => $exception->getTraceAsString(),
        ];
        $this->order->extra_data = $extraData;
        $this->order->save();

        Log::critical(sprintf(
            '[ProcessDropshipOrderJob] 代发单 %s 最终推送失败，已标记为 PUSH_FAILED：%s',
            $this->order->dropship_no,
            $exception->getMessage()
        ));
    }

    protected function handleFinalFailure(Throwable $exception): void
    {
        $this->order->refresh();

        try {
            if ($this->order->getStatusEnum() === DropshipOrderStatus::PUSHING) {
                $this->order->status = DropshipOrderStatus::PUSH_FAILED;
                $this->order->save();
            }
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[ProcessDropshipOrderJob] 更新代发单 %s 最终失败状态时出错：%s',
                $this->order->dropship_no,
                $e->getMessage()
            ));
        }

        $this->sendFailureNotification($exception);
    }

    protected function sendFailureNotification(Throwable $exception): void
    {
        Log::alert(sprintf(
            '[ProcessDropshipOrderJob][ALERT] 代发单 %s 推送WMS连续失败 %d 次，请人工介入。错误：%s',
            $this->order->dropship_no,
            $this->attempts(),
            $exception->getMessage()
        ));
    }

    public function uniqueId(): string
    {
        return 'dropship_push_' . $this->order->id;
    }

    public function displayName(): string
    {
        return sprintf('代发单推送[%s]', $this->order->dropship_no ?? $this->order->id);
    }

    public function tags(): array
    {
        return [
            'dropship_order:' . $this->order->id,
            'dropship_no:' . ($this->order->dropship_no ?? ''),
            'warehouse:' . ($this->order->warehouse_id ?? ''),
        ];
    }
}
