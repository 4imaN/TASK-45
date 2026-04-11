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
        Schema::create('loan_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->nullable()->constrained('inventory_lots')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'cancelled',
                'checked_out', 'returned', 'overdue',
            ])->default('pending');
            $table->timestamp('requested_at')->useCurrent();
            $table->date('due_date')->nullable();
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('resource_id');
            $table->index('status');
            $table->index('due_date');
            $table->index('class_id');
            $table->index('assignment_id');
        });

        Schema::create('reservation_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('venue_id')->nullable()->constrained('venues')->nullOnDelete();
            $table->foreignId('venue_time_slot_id')->nullable()->constrained('venue_time_slots')->nullOnDelete();
            $table->enum('reservation_type', ['equipment', 'venue']);
            $table->enum('status', [
                'pending', 'approved', 'rejected', 'cancelled', 'fulfilled', 'expired',
            ])->default('pending');
            $table->date('start_date');
            $table->date('end_date');
            $table->text('notes')->nullable();
            $table->string('idempotency_key')->unique();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('resource_id');
            $table->index('status');
            $table->index(['start_date', 'end_date']);
            $table->index('reservation_type');
        });

        Schema::create('approvals', function (Blueprint $table) {
            $table->id();
            $table->string('approvable_type');
            $table->unsignedBigInteger('approvable_id');
            $table->foreignId('approved_by')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['approved', 'rejected']);
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['approvable_type', 'approvable_id']);
            $table->index('approved_by');
            $table->index('status');
        });

        Schema::create('checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('loan_request_id')->constrained('loan_requests')->cascadeOnDelete();
            $table->foreignId('checked_out_by')->constrained('users')->cascadeOnDelete();
            $table->foreignId('checked_out_to')->constrained('users')->cascadeOnDelete();
            $table->foreignId('inventory_lot_id')->constrained('inventory_lots')->cascadeOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamp('checked_out_at')->useCurrent();
            $table->date('due_date');
            $table->timestamp('returned_at')->nullable();
            $table->string('condition_at_checkout')->nullable();
            $table->string('condition_at_return')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('loan_request_id');
            $table->index('checked_out_to');
            $table->index('checked_out_by');
            $table->index('inventory_lot_id');
            $table->index('due_date');
            $table->index('returned_at');
        });

        Schema::create('checkins', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained('checkouts')->cascadeOnDelete();
            $table->foreignId('checked_in_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('checked_in_at')->useCurrent();
            $table->string('condition')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('checkout_id');
            $table->index('checked_in_by');
        });

        Schema::create('renewals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('checkout_id')->constrained('checkouts')->cascadeOnDelete();
            $table->foreignId('renewed_by')->constrained('users')->cascadeOnDelete();
            $table->date('original_due_date');
            $table->date('new_due_date');
            $table->unsignedInteger('renewal_number')->default(1);
            $table->timestamps();

            $table->index('checkout_id');
            $table->index('renewed_by');
        });

        Schema::create('waitlists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamp('requested_at')->useCurrent();
            $table->timestamp('notified_at')->nullable();
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('expired_at')->nullable();
            $table->timestamps();

            $table->index(['resource_id', 'position']);
            $table->index('user_id');
            $table->index('fulfilled_at');
            $table->index('expired_at');
        });

        Schema::create('reminder_events', function (Blueprint $table) {
            $table->id();
            $table->string('remindable_type');
            $table->unsignedBigInteger('remindable_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('reminder_type', ['upcoming_due', 'overdue', 'hold_expiry']);
            $table->timestamp('scheduled_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('acknowledged_at')->nullable();
            $table->timestamps();

            $table->index(['remindable_type', 'remindable_id']);
            $table->index('user_id');
            $table->index('scheduled_at');
            $table->index('sent_at');
        });

        Schema::create('intervention_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action_type');
            $table->text('reason');
            $table->json('details')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('action_type');
            $table->index('resolved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('intervention_logs');
        Schema::dropIfExists('reminder_events');
        Schema::dropIfExists('waitlists');
        Schema::dropIfExists('renewals');
        Schema::dropIfExists('checkins');
        Schema::dropIfExists('checkouts');
        Schema::dropIfExists('approvals');
        Schema::dropIfExists('reservation_requests');
        Schema::dropIfExists('loan_requests');
    }
};
