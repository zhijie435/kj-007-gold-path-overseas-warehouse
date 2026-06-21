<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 200)->comment('规则名称');
            $table->string('code', 100)->unique()->comment('规则编码');
            $table->enum('type', [
                'auto_review',
                'auto_assign_warehouse',
                'auto_assign_shipping',
                'auto_push_wms',
                'auto_split_order',
                'auto_combine_order',
                'auto_sync_tracking',
                'auto_sync_inventory',
                'auto_cancel_order',
                'auto_notification'
            ])->comment('规则类型');
            $table->text('description')->nullable();
            $table->integer('priority')->default(0)->comment('执行优先级，数字越大越先执行');
            $table->json('conditions')->nullable()->comment('触发条件');
            $table->json('actions')->comment('执行动作配置');
            $table->unsignedBigInteger('warehouse_id')->nullable()->comment('绑定仓库');
            $table->string('country_code', 10)->nullable()->comment('适用国家');
            $table->string('source_channel', 50)->nullable()->comment('适用渠道');
            $table->decimal('min_order_amount', 15, 2)->nullable()->comment('最小订单金额');
            $table->decimal('max_order_amount', 15, 2)->nullable()->comment('最大订单金额');
            $table->time('active_time_start')->nullable()->comment('生效时间段-开始');
            $table->time('active_time_end')->nullable()->comment('生效时间段-结束');
            $table->json('weekdays')->nullable()->comment('生效星期');
            $table->boolean('is_enabled')->default(true)->comment('是否启用');
            $table->boolean('stop_chain')->default(false)->comment('命中后是否停止后续规则');
            $table->unsignedBigInteger('created_by');
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->dateTime('last_triggered_at')->nullable();
            $table->unsignedInteger('trigger_count')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('set null');
            $table->index('type');
            $table->index(['type', 'is_enabled']);
            $table->index('warehouse_id');
            $table->index('priority');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
