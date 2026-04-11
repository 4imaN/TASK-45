<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // Upgrade due_date from date to datetime for hour-level precision.
        // Supports the prompt requirement: "reminders starting 48 hours before due time."
        // Only runs on MySQL; SQLite stores dates as text and handles datetime transparently.
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->dateTime('due_date')->nullable()->change();
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->dateTime('due_date')->change();
        });

        Schema::table('renewals', function (Blueprint $table) {
            $table->dateTime('original_due_date')->change();
            $table->dateTime('new_due_date')->change();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            return;
        }

        Schema::table('loan_requests', function (Blueprint $table) {
            $table->date('due_date')->nullable()->change();
        });

        Schema::table('checkouts', function (Blueprint $table) {
            $table->date('due_date')->change();
        });

        Schema::table('renewals', function (Blueprint $table) {
            $table->date('original_due_date')->change();
            $table->date('new_due_date')->change();
        });
    }
};
