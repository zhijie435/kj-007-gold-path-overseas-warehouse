<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wms_callback_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->comment('仓库ID');
            $table->string('wms_provider', 50)->comment('WMS服务商');
            $table->string('callback_type', 50)->comment('回调类型: inventory, shipment, tracking, order_status, stock_adjust');
            $table->string('wms_order_no', 100)->nullable()->comment('WMS单号');
            $table->unsignedBigInteger('dropship_order_id')->nullable()->comment('关联代发单ID');
            $table->string('reference_no', 100)->nullable()->comment('参考号(运单号/SKU等)');
            $table->string('request_id', 100)->unique()->nullable()->comment('请求ID(幂等)');
            $table->string('status', 20)->default('received')->comment('处理状态: received, processing, success, failed, retry');
            $table->text('request_headers')->nullable();
            $table->longText('request_body');
            $table->longText('response_body')->nullable();
            $table->string('error_code', 50)->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->dateTime('processed_at')->nullable();
            $table->ipAddress('source_ip')->nullable();
            $table->unsignedBigInteger('processed_by')->nullable();
            $table->json('extra_data')->nullable();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->foreign('dropship_order_id')->references('id')->on('dropship_orders')->onDelete('set null');
            $table->foreign('processed_by')->references('id')->on('users')->onDelete('set null');
            $table->index('warehouse_id');
            $table->index('callback_type');
            $table->index('status');
            $table->index('wms_order_no');
            $table->index('dropship_order_id');
            $table->index('reference_no');
            $table->index('created_at');
            $table->index(['callback_type', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('wms_callback_logs');
    }
};
