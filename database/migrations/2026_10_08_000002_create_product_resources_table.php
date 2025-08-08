<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('product_resources')) {
            Schema::create('product_resources', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('company_id')->nullable()->index();
                $table->unsignedInteger('product_id')->index();
                $table->string('type', 20); // video | document
                $table->string('title')->nullable();
                $table->string('source', 20)->default('url'); // url | file
                $table->text('url')->nullable();
                $table->string('filename')->nullable();
                $table->string('hashname')->nullable();
                $table->string('size')->nullable();
                $table->unsignedInteger('added_by')->nullable();
                $table->timestamps();

                $table->foreign('company_id')->references('id')->on('companies')->onUpdate('CASCADE')->onDelete('CASCADE');
                $table->foreign('product_id')->references('id')->on('products')->onUpdate('CASCADE')->onDelete('CASCADE');
                $table->foreign('added_by')->references('id')->on('users')->onUpdate('CASCADE')->onDelete('SET NULL');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_resources');
    }
};
