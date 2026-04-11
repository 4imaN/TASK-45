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
        Schema::create('membership_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('max_active_loans');
            $table->unsignedInteger('max_loan_days');
            $table->unsignedInteger('max_renewals');
            $table->decimal('points_multiplier', 3, 2)->default(1.00);
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('tier_id')->constrained('membership_tiers')->cascadeOnDelete();
            $table->enum('status', ['active', 'suspended', 'expired'])->default('active');
            $table->timestamp('started_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('tier_id');
            $table->index('status');
            $table->index('expires_at');
        });

        Schema::create('entitlement_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->foreignId('tier_id')->nullable()->constrained('membership_tiers')->nullOnDelete();
            $table->string('resource_type')->nullable();
            $table->unsignedInteger('quantity');
            $table->string('unit');
            $table->unsignedInteger('validity_days');
            $table->unsignedInteger('price_in_cents')->default(0);
            $table->timestamps();

            $table->index('tier_id');
        });

        Schema::create('entitlement_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('entitlement_packages')->cascadeOnDelete();
            $table->foreignId('membership_id')->nullable()->constrained('memberships')->nullOnDelete();
            $table->unsignedInteger('remaining_quantity');
            $table->timestamp('granted_at')->useCurrent();
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index('user_id');
            $table->index('package_id');
            $table->index('membership_id');
            $table->index('expires_at');
        });

        Schema::create('entitlement_consumptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grant_id')->constrained('entitlement_grants')->cascadeOnDelete();
            $table->unsignedInteger('quantity');
            $table->timestamp('consumed_at')->useCurrent();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('grant_id');
            $table->index('resource_id');
            $table->index('consumed_at');
        });

        Schema::create('stored_value_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('amount_cents');
            $table->unsignedInteger('balance_after_cents');
            $table->enum('transaction_type', ['deposit', 'redemption', 'refund', 'adjustment']);
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->string('idempotency_key')->unique()->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('transaction_type');
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('points');
            $table->unsignedInteger('balance_after');
            $table->enum('transaction_type', ['earned', 'spent', 'adjustment', 'expired']);
            $table->string('description');
            $table->string('reference_type')->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('transaction_type');
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('hold_type', ['frequency', 'high_value', 'manual', 'system']);
            $table->text('reason');
            $table->enum('status', ['active', 'released', 'expired'])->default('active');
            $table->timestamp('triggered_at')->useCurrent();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('released_at')->nullable();
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('release_reason')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index('hold_type');
            $table->index('status');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('holds');
        Schema::dropIfExists('points_ledger');
        Schema::dropIfExists('stored_value_ledger');
        Schema::dropIfExists('entitlement_consumptions');
        Schema::dropIfExists('entitlement_grants');
        Schema::dropIfExists('entitlement_packages');
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_tiers');
    }
};
