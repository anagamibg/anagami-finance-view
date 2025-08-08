<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_merchant_entity')) {
                $table->string('worldpay_merchant_entity')->nullable();
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_username')) {
                $table->text('worldpay_username')->nullable();
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_password')) {
                $table->text('worldpay_password')->nullable();
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_checkout_id')) {
                $table->string('worldpay_checkout_id')->nullable();
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_webhook_secret')) {
                $table->text('worldpay_webhook_secret')->nullable();
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_environment')) {
                $table->enum('worldpay_environment', ['sandbox', 'live'])->default('sandbox');
            }

            if (!Schema::hasColumn('payment_gateway_credentials', 'worldpay_status')) {
                $table->enum('worldpay_status', ['active', 'deactive'])->default('deactive');
            }
        });

        Schema::table('invoice_recurring', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_recurring', 'worldpay_token')) {
                $table->text('worldpay_token')->nullable();
            }

            if (!Schema::hasColumn('invoice_recurring', 'worldpay_scheme_reference')) {
                $table->string('worldpay_scheme_reference', 64)->nullable();
            }

            if (!Schema::hasColumn('invoice_recurring', 'worldpay_sub_status')) {
                $table->tinyInteger('worldpay_sub_status')->default(0);
            }
        });
    }

    public function down()
    {
        Schema::table('payment_gateway_credentials', function (Blueprint $table) {
            $table->dropColumn([
                'worldpay_merchant_entity',
                'worldpay_username',
                'worldpay_password',
                'worldpay_checkout_id',
                'worldpay_webhook_secret',
                'worldpay_environment',
                'worldpay_status',
            ]);
        });

        Schema::table('invoice_recurring', function (Blueprint $table) {
            $table->dropColumn([
                'worldpay_token',
                'worldpay_scheme_reference',
                'worldpay_sub_status',
            ]);
        });
    }

};
