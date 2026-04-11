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
        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('imported_by')->constrained('users')->cascadeOnDelete();
            $table->string('filename');
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('processed_rows')->default(0);
            $table->unsignedInteger('valid_rows')->default(0);
            $table->unsignedInteger('invalid_rows')->default(0);
            $table->unsignedInteger('duplicate_rows')->default(0);
            $table->enum('status', ['pending', 'processing', 'completed', 'validated', 'failed'])->default('pending');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('imported_by');
            $table->index('status');
        });

        Schema::create('import_validation_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('row_number');
            $table->json('original_data');
            $table->json('validation_errors')->nullable();
            $table->boolean('is_duplicate')->default(false);
            $table->foreignId('duplicate_of_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->enum('status', ['valid', 'invalid', 'duplicate', 'remediated', 'skipped'])->default('valid');
            $table->foreignId('remediated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remediated_at')->nullable();
            $table->timestamps();

            $table->index('batch_id');
            $table->index('status');
            $table->index('is_duplicate');
        });

        Schema::create('duplicate_candidates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_a_id')->constrained('resources')->cascadeOnDelete();
            $table->foreignId('resource_b_id')->nullable()->constrained('resources')->nullOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->enum('match_type', ['exact', 'near']);
            $table->decimal('match_score', 5, 2);
            $table->enum('status', ['pending', 'confirmed', 'dismissed'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('resource_a_id');
            $table->index('resource_b_id');
            $table->index('batch_id');
            $table->index('status');
            $table->index('match_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('duplicate_candidates');
        Schema::dropIfExists('import_validation_results');
        Schema::dropIfExists('import_batches');
    }
};
