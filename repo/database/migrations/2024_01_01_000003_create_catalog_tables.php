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
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->enum('resource_type', ['equipment', 'venue', 'entitlement_package']);
            $table->string('category');
            $table->string('subcategory')->nullable();
            $table->foreignId('department_id')->constrained('departments')->cascadeOnDelete();
            $table->string('vendor')->nullable();
            $table->string('manufacturer')->nullable();
            $table->string('model_number')->nullable();
            $table->enum('status', ['active', 'delisted', 'sensitive', 'maintenance'])->default('active');
            $table->boolean('is_sensitive')->default(false);
            $table->json('metadata')->nullable();
            $table->json('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('resource_type');
            $table->index('status');
            $table->index('department_id');
            $table->index('category');
            $table->index('is_sensitive');
        });

        Schema::create('inventory_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->string('lot_number')->unique();
            $table->unsignedInteger('total_quantity');
            $table->unsignedInteger('serviceable_quantity');
            $table->string('location')->nullable();
            $table->enum('condition', ['new', 'good', 'fair', 'poor'])->default('good');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index('resource_id');
            $table->index('condition');
        });

        Schema::create('venues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('resource_id')->constrained('resources')->cascadeOnDelete();
            $table->unsignedInteger('capacity');
            $table->string('location');
            $table->string('building')->nullable();
            $table->string('floor')->nullable();
            $table->json('amenities')->nullable();
            $table->timestamps();

            $table->index('resource_id');
        });

        Schema::create('venue_time_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venue_id')->constrained('venues')->cascadeOnDelete();
            $table->date('date');
            $table->time('start_time');
            $table->time('end_time');
            $table->boolean('is_available')->default(true);
            $table->timestamps();

            $table->unique(['venue_id', 'date', 'start_time', 'end_time']);
            $table->index(['venue_id', 'date']);
            $table->index('is_available');
        });

        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->string('value');
            $table->foreignId('parent_id')->nullable()->constrained('taxonomy_terms')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['type', 'value']);
            $table->index('type');
            $table->index('parent_id');
            $table->index('is_active');
        });

        Schema::create('vendor_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias');
            $table->string('canonical_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('canonical_name');
        });

        Schema::create('manufacturer_aliases', function (Blueprint $table) {
            $table->id();
            $table->string('alias');
            $table->string('canonical_name');
            $table->enum('status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('canonical_name');
        });

        Schema::create('prohibited_terms', function (Blueprint $table) {
            $table->id();
            $table->string('term')->unique();
            $table->enum('severity', ['block', 'warn'])->default('block');
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('severity');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('prohibited_terms');
        Schema::dropIfExists('manufacturer_aliases');
        Schema::dropIfExists('vendor_aliases');
        Schema::dropIfExists('taxonomy_terms');
        Schema::dropIfExists('venue_time_slots');
        Schema::dropIfExists('venues');
        Schema::dropIfExists('inventory_lots');
        Schema::dropIfExists('resources');
    }
};
