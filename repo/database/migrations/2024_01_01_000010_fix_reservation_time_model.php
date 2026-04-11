<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('venue_time_slots', function (Blueprint $table) {
            $table->foreignId('reserved_by_reservation_id')->nullable()->constrained('reservation_requests')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('venue_time_slots', function (Blueprint $table) {
            $table->dropForeign(['reserved_by_reservation_id']);
            $table->dropColumn('reserved_by_reservation_id');
        });
    }
};
