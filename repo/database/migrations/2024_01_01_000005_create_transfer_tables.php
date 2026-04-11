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
        Schema::create('transfer_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->cascadeOnDelete();
            $table->foreignId('from_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('to_department_id')->constrained('departments')->cascadeOnDelete();
            $table->foreignId('initiated_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', [
                'pending', 'approved', 'in_transit', 'completed', 'rejected', 'cancelled',
            ])->default('pending');
            $table->unsignedInteger('quantity')->default(1);
            $table->text('reason')->nullable();
            $table->string('idempotency_key')->unique();
            $table->timestamps();

            $table->index('resource_id');
            $table->index('inventory_lot_id');
            $table->index('from_department_id');
            $table->index('to_department_id');
            $table->index('initiated_by');
            $table->index('status');
        });

        Schema::create('custody_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_request_id')->constrained('transfer_requests')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->cascadeOnDelete();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->enum('custody_type', ['source_hold', 'in_transit', 'destination_received']);
            $table->foreignId('custodian_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('ended_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transfer_request_id');
            $table->index('inventory_lot_id');
            $table->index('department_id');
            $table->index('custodian_id');
            $table->index('custody_type');
        });

        Schema::create('department_handoffs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transfer_request_id')->constrained('transfer_requests')->cascadeOnDelete();
            $table->foreignId('from_custodian_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('to_custodian_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('handed_off_at')->useCurrent();
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('transfer_request_id');
            $table->index('from_custodian_id');
            $table->index('to_custodian_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('department_handoffs');
        Schema::dropIfExists('custody_records');
        Schema::dropIfExists('transfer_requests');
    }
};
