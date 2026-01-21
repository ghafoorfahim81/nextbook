<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\UserStatus;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('username')->index()->nullable();
            $table->string('email')->index();
            $table->string('locale', 10)->default('en');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->enum('status', UserStatus::values())->default(UserStatus::ACTIVE->value);
            $table->ulid('created_by')->nullable()->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable(); 
            $table->ulid('branch_id')->index()->nullable();
            $table->unique(['branch_id', 'username', 'deleted_at']);
            $table->unique(['branch_id', 'email', 'deleted_at']);
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('updated_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id');
            $table->ulid('user_id')->nullable()->index();
            $table->timestamp(column: 'login_time')->nullable();
            $table->timestamp('logout_time')->nullable();
            $table->integer(column: 'last_activity')->index();
            $table->string(column: 'ip_address', length: 45)->nullable();
            $table->text(column: 'user_agent')->nullable();
            $table->string(column: 'device_info', length: 255)->nullable();
            $table->string(column: 'browser_info', length: 255)->nullable();
            $table->boolean(column: 'is_active')->default(true);
            $table->longText(column: 'payload');
        });

        Schema::create('password_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('user_id')->nullable()->index();
            $table->string('password'); 
            $table->timestamp(column: 'changed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
