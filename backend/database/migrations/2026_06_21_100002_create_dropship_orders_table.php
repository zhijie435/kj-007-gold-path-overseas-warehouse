<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_orders', function (Blueprint $table) {
            $table->id();
            $table->string('dropship_no', 50)->unique()->comment('一件代发单号');
            $table->unsignedBigInteger('order_id')->nullable()->comment('关联平台订单ID');
            $table->string('external_order_no', 100)->nullable()->comment('外部订单号(电商平台)');
            $table->unsignedBigInteger('warehouse_id')->comment('发货海外仓ID');
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->unsignedBigInteger('distributor_id')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->string('source_channel', 50)->default('manual')->comment('来源渠道: manual, shopify, amazon, tiktok, shopee, lazada');
            $table->enum('fulfillment_type', ['auto', 'manual', 'semi_auto'])->default('auto')->comment('履约方式');
            $table->string('wms_order_no', 100)->nullable()->comment('WMS系统单号');
            $table->string('shipping_method_code', 50)->nullable()->comment('物流渠道代码');
            $table->string('tracking_no', 100)->nullable()->comment('运单号');
            $table->string('carrier_name', 100)->nullable()->comment('承运商');

            $table->string('receiver_name', 100);
            $table->string('receiver_phone', 50);
            $table->string('receiver_email', 100)->nullable();
            $table->string('receiver_country', 10);
            $table->string('receiver_state', 100)->nullable();
            $table->string('receiver_city', 100)->nullable();
            $table->string('receiver_postal_code', 50)->nullable();
            $table->text('receiver_address');

            $table->integer('total_items')->default(0)->comment('商品件数');
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('shipping_fee', 10, 2)->default(0)->comment('运费');
            $table->decimal('handling_fee', 10, 2)->default(0)->comment('操作费');
            $table->decimal('insurance_fee', 10, 2)->default(0)->comment('保险费');
            $table->decimal('duty_fee', 10, 2)->default(0)->comment('关税');
            $table->decimal('total_cost', 15, 2)->default(0)->comment('总费用');
            $table->decimal('declared_value', 15, 2)->default(0)->comment('申报价值');
            $table->string('currency', 10)->default('USD')->comment('结算币种');
            $table->decimal('weight', 8, 3)->default(0)->comment('实际重量kg');
            $table->decimal('volume_weight', 8, 3)->default(0)->comment('体积重量kg');

            $table->enum('status', [
                'draft', 'pending_review', 'auto_review_pass', 'review_pass', 'review_reject',
                'pushing', 'push_success', 'push_failed',
                'processing', 'picked', 'packed', 'shipped',
                'in_transit', 'customs', 'delivered', 'completed',
                'cancelled', 'returned', 'exception'
            ])->default('draft');

            $table->dateTime('reviewed_at')->nullable();
            $table->unsignedBigInteger('reviewed_by')->nullable();
            $table->string('review_remark', 500)->nullable();
            $table->dateTime('pushed_at')->nullable();
            $table->dateTime('shipped_at')->nullable();
            $table->dateTime('delivered_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();

            $table->integer('push_attempts')->default(0)->comment('推单尝试次数');
            $table->text('push_error')->nullable()->comment('推单失败原因');
            $table->json('tracking_history')->nullable()->comment('物流轨迹');
            $table->json('customs_info')->nullable()->comment('报关信息');
            $table->json('extra_data')->nullable()->comment('扩展数据');
            $table->text('remark')->nullable();

            $table->softDeletes();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders')->onDelete('set null');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->onDelete('restrict');
            $table->foreign('supplier_id')->references('id')->on('suppliers')->onDelete('set null');
            $table->foreign('distributor_id')->references('id')->on('distributors')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('reviewed_by')->references('id')->on('users')->onDelete('set null');

            $table->index('order_id');
            $table->index('warehouse_id');
            $table->index('dropship_no');
            $table->index('external_order_no');
            $table->index('wms_order_no');
            $table->index('tracking_no');
            $table->index('status');
            $table->index('source_channel');
            $table->index(['receiver_country', 'status']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_orders');
    }
};
