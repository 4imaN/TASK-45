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
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('route');
            $table->string('payload_hash');
            $table->unsignedSmallInteger('response_code');
            $table->json('response_body');
            $table->timestamp('created_at')->useCurrent();
            $table->timestamp('expires_at');

            $table->index('user_id');
            $table->index('expires_at');
        });

        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action');
            $table->string('auditable_type')->nullable();
            $table->unsignedBigInteger('auditable_id')->nullable();
            $table->json('old_values')->nullable();
            $table->json('new_values')->nullable();
            $table->string('ip_address')->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['auditable_type', 'auditable_id']);
            $table->index(['user_id', 'created_at']);
            $table->index('action');
        });

        Schema::create('file_assets', function (Blueprint $table) {
            $table->id();
            $table->string('filename');
            $table->string('original_filename');
            $table->string('mime_type');
            $table->unsignedInteger('size_bytes');
            $table->string('checksum');
            $table->string('storage_path');
            $table->foreignId('uploaded_by')->constrained('users')->cascadeOnDelete();
            $table->string('attachable_type')->nullable();
            $table->unsignedBigInteger('attachable_id')->nullable();
            $table->timestamps();

            $table->index('uploaded_by');
            $table->index(['attachable_type', 'attachable_id']);
            $table->index('checksum');
        });

        Schema::create('file_access_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('file_asset_id')->constrained('file_assets')->cascadeOnDelete();
            $table->foreignId('accessed_by')->constrained('users')->cascadeOnDelete();
            $table->enum('access_type', ['download', 'view', 'delete']);
            $table->string('ip_address')->nullable();
            $table->timestamps();

            $table->index('file_asset_id');
            $table->index('accessed_by');
            $table->index('access_type');
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');

            $table->index('last_activity');
            $table->index('user_id');
        });

        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });

        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->integer('expiration');
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->integer('expiration');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
        Schema::dropIfExists('failed_jobs');
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('file_access_logs');
        Schema::dropIfExists('file_assets');
        Schema::dropIfExists('audit_logs');
        Schema::dropIfExists('idempotency_keys');
    }
};
