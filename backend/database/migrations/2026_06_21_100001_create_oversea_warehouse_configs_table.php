<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oversea_warehouse_configs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('warehouse_id')->unique()->comment('关联仓库ID');
            $table->string('wms_provider', 50)->default('custom')->comment('WMS服务商: custom, shipbob, easypost, 4px, yanwen');
            $table->string('api_endpoint')->nullable()->comment('API地址');
            $table->string('api_key')->nullable()->comment('API Key');
            $table->string('api_secret')->nullable()->comment('API Secret');
            $table->string('warehouse_code', 50)->nullable()->comment('海外仓仓库编码');
            $table->string('default_shipping_method', 50)->nullable()->comment('默认物流渠道');
            $table->decimal('handling_fee', 10, 2)->default(0)->comment('操作费/件');
            $table->decimal('storage_fee_per_cbm', 10, 2)->default(0)->comment('仓储费/CBM/天');
            $table->integer('sla_processing_hours')->default(24)->comment('订单处理SLA(小时)');
            $table->boolean('auto_push_enabled')->default(false)->comment('是否自动推单到WMS');
            $table->boolean('auto_sync_inventory')->default(false)->comment('是否自动同步库存');
            $table->integer('inventory_sync_interval_min')->default(60)->comment('库存同步间隔(分钟)');
            $table->boolean('auto_sync_tracking')->default(false)->comment('是否自动回传物流轨迹');
            $table->integer('tracking_sync_interval_min')->default(120)->comment('物流轨迹同步间隔(分钟)');
            $table->text('supported_countries')->nullable()->comment('支持发货国家JSON');
            $table->json('extra_config')->nullable()->comment('扩展配置');
            $table->enum('status', ['active', 'inactive', 'testing', 'error'])->default('inactive');
            $table->dateTime('last_inventory_sync_at')->nullable();
            $table->dateTime('last_tracking_sync_at')->nullable();
            $table->dateTime('last_api_call_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('cascade');
            $table->index('wms_provider');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oversea_warehouse_configs');
    }
};
