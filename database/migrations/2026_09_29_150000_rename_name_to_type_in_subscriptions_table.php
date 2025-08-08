<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up()
    {
        if (Schema::hasColumn('subscriptions', 'name') && !Schema::hasColumn('subscriptions', 'type')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->renameColumn('name', 'type');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('subscriptions', 'type') && !Schema::hasColumn('subscriptions', 'name')) {
            Schema::table('subscriptions', function (Blueprint $table) {
                $table->renameColumn('type', 'name');
            });
        }
    }

};
