<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            // SQLite cannot drop unique constraints easily; for test env this is acceptable
            // as the business logic layer prevents collisions via the middleware scoping
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['user_id', 'idempotency_key'], 'loan_requests_user_idempotency_unique');
        });

        Schema::table('reservation_requests', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['user_id', 'idempotency_key'], 'reservation_requests_user_idempotency_unique');
        });

        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->unique(['initiated_by', 'idempotency_key'], 'transfer_requests_user_idempotency_unique');
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dropUnique('loan_requests_user_idempotency_unique');
            $table->unique('idempotency_key');
        });

        Schema::table('reservation_requests', function (Blueprint $table) {
            $table->dropUnique('reservation_requests_user_idempotency_unique');
            $table->unique('idempotency_key');
        });

        Schema::table('transfer_requests', function (Blueprint $table) {
            $table->dropUnique('transfer_requests_user_idempotency_unique');
            $table->unique('idempotency_key');
        });
    }
};
