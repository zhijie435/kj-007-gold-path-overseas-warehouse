<?php

use App\Http\Controllers\AutomationRuleController;
use App\Http\Controllers\OverseaDropshipController;
use App\Http\Controllers\OverseaWarehouseConfigController;
use App\Http\Controllers\WmsCallbackLogController;
use Illuminate\Support\Facades\Route;

Route::prefix('wms')->name('wms.')->group(function () {
    Route::post('/callback/{warehouseId}', [WmsCallbackLogController::class, 'handleWmsCallback'])
        ->name('callback');
});

Route::middleware('auth:sanctum')->group(function () {

    Route::prefix('dropship')->name('dropship.')->group(function () {
        Route::get('/statistics', [OverseaDropshipController::class, 'statistics'])
            ->name('statistics');
        Route::post('/batch-review', [OverseaDropshipController::class, 'batchReview'])
            ->name('batch-review');
        Route::post('/batch-push', [OverseaDropshipController::class, 'batchPush'])
            ->name('batch-push');
        Route::get('/status-options', [OverseaDropshipController::class, 'statusOptions'])
            ->name('status-options');
        Route::get('/channel-options', [OverseaDropshipController::class, 'channelOptions'])
            ->name('channel-options');

        Route::post('/orders/{order}/review', [OverseaDropshipController::class, 'review'])
            ->name('orders.review');
        Route::post('/orders/{order}/push', [OverseaDropshipController::class, 'push'])
            ->name('orders.push');
        Route::post('/orders/{order}/retry-push', [OverseaDropshipController::class, 'retryPush'])
            ->name('orders.retry-push');
        Route::post('/orders/{order}/update-status', [OverseaDropshipController::class, 'updateStatus'])
            ->name('orders.update-status');
        Route::post('/orders/{order}/cancel', [OverseaDropshipController::class, 'cancel'])
            ->name('orders.cancel');

        Route::apiResource('orders', OverseaDropshipController::class)
            ->parameters(['orders' => 'order'])
            ->names([
                'index' => 'orders.index',
                'show' => 'orders.show',
                'store' => 'orders.store',
                'update' => 'orders.update',
                'destroy' => 'orders.destroy',
            ]);
    });

    Route::prefix('warehouse-configs')->name('warehouse-configs.')->group(function () {
        Route::get('/status-options', [OverseaWarehouseConfigController::class, 'statusOptions'])
            ->name('status-options');
        Route::get('/provider-options', [OverseaWarehouseConfigController::class, 'providerOptions'])
            ->name('provider-options');

        Route::post('/{config}/toggle-status', [OverseaWarehouseConfigController::class, 'toggleStatus'])
            ->name('toggle-status');
        Route::post('/{config}/test-connection', [OverseaWarehouseConfigController::class, 'testConnection'])
            ->name('test-connection');
        Route::post('/{config}/sync-inventory', [OverseaWarehouseConfigController::class, 'syncInventory'])
            ->name('sync-inventory');
        Route::post('/{config}/sync-tracking', [OverseaWarehouseConfigController::class, 'syncTracking'])
            ->name('sync-tracking');
    });

    Route::apiResource('warehouse-configs', OverseaWarehouseConfigController::class)
        ->parameters(['warehouse-configs' => 'config']);

    Route::prefix('automation-rules')->name('automation-rules.')->group(function () {
        Route::get('/type-options', [AutomationRuleController::class, 'typeOptions'])
            ->name('type-options');
        Route::get('/statistics', [AutomationRuleController::class, 'statistics'])
            ->name('statistics');

        Route::post('/{rule}/toggle-enabled', [AutomationRuleController::class, 'toggleEnabled'])
            ->name('toggle-enabled');
        Route::post('/{rule}/trigger', [AutomationRuleController::class, 'trigger'])
            ->name('trigger');
    });

    Route::apiResource('automation-rules', AutomationRuleController::class)
        ->parameters(['automation-rules' => 'rule']);

    Route::prefix('wms-callback-logs')->name('wms-callback-logs.')->group(function () {
        Route::get('/statistics', [WmsCallbackLogController::class, 'statistics'])
            ->name('statistics');
        Route::post('/{log}/retry', [WmsCallbackLogController::class, 'retry'])
            ->name('retry');
    });

    Route::apiResource('wms-callback-logs', WmsCallbackLogController::class)
        ->parameters(['wms-callback-logs' => 'log'])
        ->only(['index', 'show']);
});
