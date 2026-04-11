<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('resource_id')->constrained('departments')->nullOnDelete();
        });

        // Backfill: set lot department_id from parent resource's department_id
        DB::statement('UPDATE inventory_lots SET department_id = (SELECT department_id FROM resources WHERE resources.id = inventory_lots.resource_id)');
    }

    public function down(): void
    {
        Schema::table('inventory_lots', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
    }
};
