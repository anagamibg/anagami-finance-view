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
        Schema::table('invoice_settings', function (Blueprint $table) {
            if (!Schema::hasColumn('invoice_settings', 'template_header')) {
                $table->string('template_header')->nullable()->after('template');
            }

            if (!Schema::hasColumn('invoice_settings', 'template_footer')) {
                $table->string('template_footer')->nullable()->after('template_header');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_settings', function (Blueprint $table) {
            if (Schema::hasColumn('invoice_settings', 'template_footer')) {
                $table->dropColumn('template_footer');
            }

            if (Schema::hasColumn('invoice_settings', 'template_header')) {
                $table->dropColumn('template_header');
            }
        });
    }
};
