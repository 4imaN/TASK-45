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
        Schema::create('recommendation_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('context_type');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->timestamp('generated_at')->useCurrent();
            $table->json('parameters')->nullable();
            $table->timestamps();

            $table->index('user_id');
            $table->index(['context_type', 'context_id']);
            $table->index('generated_at');
        });

        Schema::create('rule_traces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('recommendation_batches')->cascadeOnDelete();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->unsignedInteger('rank');
            $table->decimal('score', 8, 4);
            $table->json('contributing_factors');
            $table->json('applied_filters');
            $table->boolean('excluded')->default(false);
            $table->string('exclusion_reason')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('resource_id');
            $table->index(['batch_id', 'rank']);
            $table->index('excluded');
        });

        Schema::create('manual_overrides', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->nullable()->constrained('recommendation_batches')->nullOnDelete();
            $table->foreignId('resource_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('overridden_by')->constrained('users')->cascadeOnDelete();
            $table->string('override_type');
            $table->text('reason');
            $table->json('previous_state')->nullable();
            $table->json('new_state')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('resource_id');
            $table->index('overridden_by');
            $table->index('override_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_overrides');
        Schema::dropIfExists('rule_traces');
        Schema::dropIfExists('recommendation_batches');
    }
};
