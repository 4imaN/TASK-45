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
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('user_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('roles')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['user_id', 'role_id']);
            $table->index('role_id');
        });

        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });

        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('department_id');
            $table->index('is_active');
        });

        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')->constrained('courses')->cascadeOnDelete();
            $table->string('name');
            $table->string('section')->nullable();
            $table->string('semester')->nullable();
            $table->unsignedSmallInteger('year')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('course_id');
            $table->index('is_active');
        });

        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->date('due_date')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('class_id');
            $table->index('due_date');
        });

        Schema::create('permission_scopes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('course_id')->nullable()->constrained('courses')->nullOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->foreignId('assignment_id')->nullable()->constrained('assignments')->nullOnDelete();
            $table->foreignId('department_id')->nullable()->constrained('departments')->nullOnDelete();
            $table->enum('scope_type', ['full', 'course', 'class', 'assignment', 'department']);
            $table->foreignId('granted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('user_id');
            $table->index('scope_type');
            $table->index('granted_by');
        });

        Schema::create('allowlists', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->index('user_id');
        });

        Schema::create('blacklists', function (Blueprint $table) {
            $table->id();
            $table->string('scope_type');
            $table->unsignedBigInteger('scope_id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->text('reason')->nullable();
            $table->foreignId('added_by')->constrained('users')->cascadeOnDelete();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['scope_type', 'scope_id']);
            $table->index('user_id');
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('blacklists');
        Schema::dropIfExists('allowlists');
        Schema::dropIfExists('permission_scopes');
        Schema::dropIfExists('assignments');
        Schema::dropIfExists('classes');
        Schema::dropIfExists('courses');
        Schema::dropIfExists('departments');
        Schema::dropIfExists('user_roles');
        Schema::dropIfExists('roles');
    }
};
