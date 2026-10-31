<?php
/**
 * 商城模块 - 安装脚本
 *
 * 创建产品表和订单表
 */

use zap\db\Schema;
// --- 产品表 ---
if (!Schema::hasTable('shop_product')) {
    Schema::create('shop_product', function ($table) {
        $table->increments('id');
        $table->string('title', 200)->comment('产品名称');
        $table->string('slug', 200)->comment('URL 别名');
        $table->string('image', 500)->default('')->comment('主图');
        $table->text('images')->nullable()->comment('多图 JSON');
        $table->decimal('price', 10, 2)->default('0.00')->comment('价格');
        $table->decimal('origin_price', 10, 2)->default('0.00')->comment('原价');
        $table->integer('stock')->default(0)->comment('库存');
        $table->string('unit', 20)->default('件')->comment('单位');
        $table->tinyint('status')->default(1)->comment('1上架 0下架');
        $table->integer('sort')->default(0)->comment('排序');
        $table->text('summary')->nullable()->comment('简介');
        $table->text('content')->nullable()->comment('详情');
        $table->timestamp('created_at');
        $table->timestamp('updated_at');

        $table->addPrimaryKey('id');
    });
}

// --- 订单表 ---
if (!Schema::hasTable('shop_order')) {
    Schema::create('shop_order', function ($table) {
        $table->increments('id');
        $table->string('order_no', 32)->unique()->comment('订单号');
        $table->decimal('total_price', 10, 2)->default('0.00')->comment('总价');
        $table->string('contact_name', 50)->default('')->comment('联系人');
        $table->string('contact_phone', 20)->default('')->comment('联系电话');
        $table->string('contact_address', 500)->default('')->comment('地址');
        $table->string('remark', 500)->default('')->comment('备注');
        $table->string('status', 20)->default('pending')->comment('pending/paid/shipped/done/cancel');
        $table->timestamp('created_at');
        $table->timestamp('updated_at');

        $table->addPrimaryKey('id');
    });
}

// --- 订单商品明细 ---
if (!Schema::hasTable('shop_order_item')) {
    Schema::create('shop_order_item', function ($table) {
        $table->increments('id');
        $table->integer('order_id');
        $table->integer('product_id');
        $table->string('product_title', 200)->comment('快照：产品名');
        $table->decimal('price', 10, 2)->comment('快照：单价');
        $table->integer('quantity')->default(1)->comment('数量');
        $table->timestamp('created_at');

        $table->addPrimaryKey('id');
    });
}

return true;
