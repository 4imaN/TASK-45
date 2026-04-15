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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('username')->unique();
            // email is encrypted at the application layer — ciphertext can
            // exceed 255 chars, and since each encrypt uses a fresh IV the
            // ciphertext is non-deterministic, so a DB-level UNIQUE index
            // on it would be meaningless. Store as TEXT and enforce
            // uniqueness elsewhere if ever needed.
            $table->text('email')->nullable();
            $table->string('password');
            $table->string('display_name')->nullable();
            $table->text('phone')->nullable();
            $table->boolean('force_password_change')->default(true);
            $table->enum('account_status', ['active', 'suspended', 'held'])->default('active');
            $table->timestamp('last_login_at')->nullable();
            $table->integer('failed_login_attempts')->default(0);
            $table->timestamp('locked_until')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_status');
            $table->index('last_login_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
