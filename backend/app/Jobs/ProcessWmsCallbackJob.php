<?php

namespace App\Jobs;

use App\Models\WmsCallbackLog;
use App\Services\WmsIntegrationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessWmsCallbackJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $maxExceptions = 2;

    public function __construct(
        public WmsCallbackLog $log,
    ) {}

    public function backoff(): array
    {
        return [60, 300, 900];
    }

    public function retryUntil(): ?\DateTime
    {
        return now()->addHours(12);
    }

    public function handle(WmsIntegrationService $service): void
    {
        $this->log->refresh();

        if ($this->log->isSuccess() || $this->log->isProcessing()) {
            Log::info(sprintf(
                '[ProcessWmsCallbackJob] 回调日志 %s 状态为 %s，跳过处理',
                $this->log->id,
                $this->log->status
            ));
            $this->delete();
            return;
        }

        try {
            $service->handleCallback($this->log);

            Log::info(sprintf(
                '[ProcessWmsCallbackJob] 回调日志 %s 处理成功，类型：%s',
                $this->log->id,
                $this->log->callback_type->value
            ));
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[ProcessWmsCallbackJob] 回调日志 %s 处理失败（第 %d 次尝试）：%s',
                $this->log->id,
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

        $this->log->refresh();

        if (!$this->log->isSuccess() && !$this->log->isFailed()) {
            try {
                $this->log->markFailed(
                    'JOB_FAILED',
                    sprintf('队列处理最终失败：%s', $exception->getMessage()),
                    1
                );
            } catch (Throwable $e) {
                Log::error(sprintf(
                    '[ProcessWmsCallbackJob] 更新回调日志 %s 失败状态时出错：%s',
                    $this->log->id,
                    $e->getMessage()
                ));
            }
        }

        Log::critical(sprintf(
            '[ProcessWmsCallbackJob] 回调日志 %s 最终处理失败：%s',
            $this->log->id,
            $exception->getMessage()
        ));
    }

    protected function handleFinalFailure(Throwable $exception): void
    {
        $this->log->refresh();

        try {
            if (!$this->log->isSuccess() && !$this->log->isFailed()) {
                $this->log->markFailed(
                    'FINAL_FAILURE',
                    sprintf('连续失败 %d 次：%s', $this->attempts(), $exception->getMessage()),
                    1
                );
            }
        } catch (Throwable $e) {
            Log::error(sprintf(
                '[ProcessWmsCallbackJob] 更新回调日志 %s 最终失败状态时出错：%s',
                $this->log->id,
                $e->getMessage()
            ));
        }

        Log::alert(sprintf(
            '[ProcessWmsCallbackJob][ALERT] 回调日志 %s 连续失败 %d 次，请人工介入。错误：%s',
            $this->log->id,
            $this->attempts(),
            $exception->getMessage()
        ));
    }

    public function uniqueId(): string
    {
        return 'wms_callback_' . $this->log->id;
    }

    public function displayName(): string
    {
        return sprintf('WMS回调处理[%s]', $this->log->callback_type->label() ?? $this->log->id);
    }

    public function tags(): array
    {
        return [
            'wms_callback:' . $this->log->id,
            'callback_type:' . ($this->log->callback_type->value ?? ''),
            'warehouse:' . ($this->log->warehouse_id ?? ''),
            'dropship_order:' . ($this->log->dropship_order_id ?? ''),
        ];
    }
}
