<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dropship_order_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('dropship_order_id');
            $table->unsignedBigInteger('product_id')->nullable();
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('sku', 100)->comment('SKU编码');
            $table->string('product_name')->comment('商品名称');
            $table->string('specification', 200)->nullable()->comment('规格');
            $table->string('unit', 20)->default('pcs');
            $table->integer('quantity')->default(1)->comment('下单数量');
            $table->integer('shipped_quantity')->default(0)->comment('实际发货数量');
            $table->decimal('unit_price', 12, 2)->default(0);
            $table->decimal('subtotal', 15, 2)->default(0);
            $table->decimal('unit_cost', 12, 2)->default(0)->comment('单件成本');
            $table->decimal('weight', 8, 3)->default(0)->comment('单件重量kg');
            $table->string('hs_code', 50)->nullable()->comment('海关编码');
            $table->string('batch_no', 50)->nullable()->comment('批次号');
            $table->json('serial_numbers')->nullable()->comment('序列号列表');
            $table->text('remark')->nullable();
            $table->timestamps();

            $table->foreign('dropship_order_id')->references('id')->on('dropship_orders')->onDelete('cascade');
            $table->foreign('product_id')->references('id')->on('products')->onDelete('set null');
            $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('set null');
            $table->index('dropship_order_id');
            $table->index('sku');
            $table->index('product_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropship_order_items');
    }
};
